# OpenCart 4.0 Support — Research & Feasibility Analysis

**Date:** 2026-06-01
**Current Extension Version:** 04.01.0100
**Supported OC Versions:** 1.5, 2.0–2.3, 3.0

---

## Overview

This document summarizes the architectural differences between OpenCart 3.0 and OpenCart 4.0 and evaluates the effort required to port OpenCart Translate Expert Client to OC4.

**Overall difficulty: Moderate-to-Hard**

OC4 is the biggest architectural shift since OC 1.5 → 2.0. Almost every file needs some modification, but the core translation logic is framework-agnostic and does not require rewriting.

---

## Breaking Changes

### 1. PHP Namespaces (Medium Effort)

**OC3:** No namespaces. Classes named by convention:
```php
class ControllerExtensionModuleClientTranslateExpert extends Controller { }
class ModelExtensionModuleClientTranslateExpert extends Model { }
```

**OC4:** Full PHP namespaces required:
```php
namespace Opencart\Admin\Controller\Extension\ClientTranslateExpert\Module;

class ClientTranslateExpert extends \Opencart\System\Engine\Controller { }
```

```php
namespace Opencart\Admin\Model\Extension\ClientTranslateExpert\Module;

class ClientTranslateExpert extends \Opencart\System\Engine\Model { }
```

**Impact on our extension:** Controller, model, and all library classes need namespace declarations.

---

### 2. Self-Contained Extension Structure (Medium Effort)

**OC3:** Extension files are scattered across core directories:
```
admin/controller/extension/module/client_translate_expert.php
admin/model/extension/module/client_translate_expert.php
admin/view/template/extension/module/client_translate_expert.twig
system/library/client_translate_expert_core.php
system/library/client_translate_expert.php
system/library/client_localization_translate_expert.php
system/library/vh_google_translator.php
```

**OC4:** Extensions are self-contained packages under `extension/{name}/`:
```
extension/client_translate_expert/
  admin/
    controller/module/client_translate_expert.php
    model/module/client_translate_expert.php
    view/template/module/client_translate_expert.twig
    language/en-gb/module/client_translate_expert.php
  system/
    library/client_translate_expert_core.php
    library/client_translate_expert.php
    library/client_localization_translate_expert.php
    library/vh_google_translator.php
  install.json
```

**`install.json` required at package root:**
```json
{
    "name": "OpenCart Translate Expert Client",
    "version": "04.01.0100",
    "author": "CodeGuild",
    "link": "https://translator.codeguild.com.ua/"
}
```

**Packaging:** Zip must contain files directly (no wrapper folder), max 32MB, named `{name}.ocmod.zip`.

---

### 3. OCMOD Replaced by Events (Medium Effort)

**OC3:** Our `OpenCartTranslateExpertClient.ocmod.xml` modifies `admin/controller/common/header.php` and `admin/view/template/common/header.twig` to inject JS/CSS and config variables.

**OC4:**
- OCMOD was removed in OC 4.0.0, reintroduced in 4.0.2.3+ after community pushback
- OCMOD has limitations in OC 4.x (e.g., only works with `index()` method in 4.1)
- **Events are the recommended approach**

**Migration path:** Replace OCMOD with Event listeners registered in `install()`:

```php
public function install(): void {
    $this->load->model('setting/event');

    $this->model_setting_event->addEvent([
        'code'        => 'client_translate_expert_header',
        'description' => 'Inject Translate Expert JS/CSS into admin header',
        'trigger'     => 'admin/view/common/header/after',
        'action'      => 'extension/client_translate_expert/module/client_translate_expert.eventHeaderAfter',
        'status'      => 1,
        'sort_order'  => 0
    ]);
}

public function uninstall(): void {
    $this->load->model('setting/event');
    $this->model_setting_event->deleteEventByCode('client_translate_expert_header');
}
```

The `eventHeaderAfter` method would modify the rendered header output to inject our JS/CSS and config variables.

---

### 4. Route Changes (Low Effort)

**OC3:** Method separator is `/`:
```
extension/module/client_translate_expert/translate
extension/module/client_translate_expert/analize
```

**OC4:** Method separator is `.` (dot):
```
extension/client_translate_expert/module/client_translate_expert.translate
extension/client_translate_expert/module/client_translate_expert.analize
```

**Impact:** All `$this->url->link()` calls in the controller and all AJAX route strings in `client_translate_expert.js` need updating.

---

### 5. Bootstrap 3 → Bootstrap 5 (Medium Effort)

**OC3:** Bootstrap 3

**OC4:** Bootstrap 5

Key CSS/JS changes needed in the `.twig` template:

| Bootstrap 3 | Bootstrap 5 |
|-------------|-------------|
| `data-toggle="tab"` | `data-bs-toggle="tab"` |
| `data-target` | `data-bs-target` |
| `data-dismiss` | `data-bs-dismiss` |
| `.panel` / `.panel-body` | `.card` / `.card-body` |
| `.btn-default` | `.btn-secondary` |
| `.pull-right` / `.pull-left` | `.float-end` / `.float-start` |
| `.hidden` | `.d-none` |
| `$('#modal').modal('show')` | `new bootstrap.Modal(el).show()` |
| jQuery dependency assumed | jQuery no longer bundled by default |

**jQuery note:** OC4 admin still loads jQuery, but this could change. Our JS (`client_translate_expert.js`) relies heavily on jQuery — this should continue to work but is worth monitoring.

---

### 6. PHP 8.0+ Minimum (Low-Medium Effort)

**OC3:** PHP 7.0+ (our extension supports PHP 5.6+)

**OC4:** PHP 8.0+ required (PHP 8.1+ recommended)

**Impact:**
- Drop PHP 5.6/7.x support for the OC4 package (other packages unchanged)
- Review code for PHP 8 compatibility issues:
  - Null handling is stricter (passing `null` to string functions triggers warnings)
  - Named arguments available (no action needed, but available)
  - Union types available (no action needed)
  - `match` expression available (no action needed)
- The `vh_google_translator.php` and core library should be reviewed for deprecated function usage

---

### 7. Permission System (Low Effort)

**OC3:**
```php
$this->user->hasPermission('modify', 'extension/module/client_translate_expert');
```

**OC4:**
```php
$this->user->hasPermission('modify', 'extension/client_translate_expert/module/client_translate_expert');
```

Same `access`/`modify` model, just updated route strings.

---

### 8. Library Loading (Medium Effort)

**OC3:** Libraries live in `system/library/` and are instantiated directly:
```php
$this->_library = new LibraryClientTranslateExpert($this, $debug);
```

**OC4:** Libraries must move into the extension directory and be namespaced. Loading options:
- Use PHP autoloader (namespace-based)
- Manual `require_once` with the extension path
- Register via OC4's startup event

The `client_translate_expert_core.php` (852 lines), `client_translate_expert.php`, `client_localization_translate_expert.php`, and `vh_google_translator.php` all need namespace declarations and path updates.

---

## What Does NOT Change

| Area | Details |
|------|---------|
| **Settings storage** | Still uses `module_` prefix (same as OC3) |
| **Token handling** | Still `user_token` in admin URLs (same as OC3) |
| **Template engine** | Still Twig (same as OC3) |
| **Database abstraction** | `$this->db->query()` pattern unchanged |
| **Core translation logic** | `vh_google_translator.php`, batch processing, table analysis — all framework-agnostic |
| **Google API integration** | No changes needed |
| **Character counting logic** | No changes needed |
| **`translate_expert_same_translations` table** | Schema unchanged |

---

## Version Comparison Matrix

| Feature | OC 1.5 | OC 2.0–2.2 | OC 2.3 | OC 3.0 | OC 4.0 |
|---------|--------|-----------|--------|--------|--------|
| PHP version | 5.6+ | 5.6+ | 7.0+ | 7.0+ | **8.0+** |
| Namespaces | No | No | No | No | **Yes** |
| Extension structure | Scattered | Scattered | Scattered | Scattered | **Self-contained** |
| Class naming | `ControllerModule...` | `ControllerModule...` | `ControllerExtensionModule...` | `ControllerExtensionModule...` | **Namespaced** |
| Template engine | PHP `.tpl` | PHP `.tpl` | PHP `.tpl` | Twig `.twig` | Twig `.twig` |
| Extension mechanism | OCMOD | OCMOD | OCMOD | OCMOD | **Events (preferred)** |
| Route method separator | `/` | `/` | `/` | `/` | **`.` (dot)** |
| Route prefix | `module/` | `module/` | `extension/module/` | `extension/module/` | **`extension/{ext}/module/`** |
| Token param | session | `token` | `token` | `user_token` | `user_token` |
| Settings prefix | none | none | none | `module_` | `module_` |
| Bootstrap version | 2.x | 3.x | 3.x | 3.x | **5.x** |

---

## Effort Estimate

| Task | Effort |
|------|--------|
| Restructure into self-contained extension dir | 0.5 day |
| Add PHP namespaces to all classes | 0.5 day |
| Replace OCMOD with Event system | 1 day |
| Update route strings (controller + JS) | 0.5 day |
| Update Twig template for Bootstrap 5 | 1 day |
| Update library loading mechanism | 0.5 day |
| PHP 8.0 compatibility review & fixes | 0.5 day |
| Update build system (`build.sh`) for OC4 target | 0.5 day |
| Testing & debugging | 1 day |
| **Total** | **~5 days** |

---

## Recommended Approach

1. **Create a separate OC4 source variant** — follow the existing pattern of `oc1.5/`, `oc2.0/`, `oc3.0/` build targets. Do not try to make one codebase serve both OC3 and OC4.

2. **Start with the Event system** — replacing OCMOD is the riskiest change and should be validated first.

3. **Keep library code shared** — the core translation logic (`_core.php`, `vh_google_translator.php`) can remain mostly identical between OC3 and OC4 packages, with namespace wrappers added for OC4.

4. **Add `version_compare(VERSION, '4.0', '>=')` branches** in `client_translate_expert_core.php` for the few places that check OC version at runtime.

5. **Test on OC 4.0.2.3+** — this is the version where OCMOD was reintroduced, giving a fallback if the Event approach has gaps.

---

## References

- [OpenCart 4 Extension Development Guide (GitHub)](https://github.com/IP-CAM/OC-4-Extension-Development-Guide)
- [Comprehensive Guide to Developing an Extension for OpenCart 4 (Playful Sparkle)](https://playfulsparkle.com/en-us/resources/articles/web-development/comprehensive-guide-to-developing-an-extension-for-opencart-4/)
- [Migrating OpenCart Extension from v3 to v4 (Peersol)](https://peersol.com/migrating-your-opencart-extension-from-v3-to-v4-a-guide-to-substantial-changes/)
- [OpenCart Events System (GitHub Wiki)](https://github.com/opencart/opencart/wiki/Events-System)
- [OpenCart Developer Guide: Extensions](https://docs.opencart.com/developer-guide/extensions)
