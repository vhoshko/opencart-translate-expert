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

## Event System History Across OpenCart Versions

The Event system is key to replacing OCMOD. Here's when it became available:

| OC Version | Events? | View events (inject JS/CSS)? | Notes |
|------------|---------|------------------------------|-------|
| **1.5** | No | No | No event system at all |
| **2.0–2.1** | Yes, limited | **No** | Controller/model events only, no view hooks |
| **2.2** | Yes, major rewrite | **Yes** | `admin/view/common/header/before` works. Switched from `pre`/`post` to `before`/`after` |
| **2.3** | Yes, same as 2.2 | **Yes** | No `sort_order` for events yet |
| **3.0** | Yes + sort_order | **Yes** | Buggy in 3.0.1.2 & 3.0.2.0 (admin `view/*/before` broken), fixed in 3.0.3+ |
| **4.0** | Yes, mandatory | **Yes** | Events are the only officially supported extension mechanism |

**Implication:** Events can replace OCMOD for OC 2.2+. OCMOD is only strictly needed for OC 1.5 and OC 2.0–2.1 (which are effectively dead — OC 2.0 is from 2015).

**Decision:** Keep OCMOD for OC 1.5–2.1. For OC 2.2+ (including 4.0), Events are the preferred approach. However, for the scope of this OC 4.0 port, we focus on Events for the OC 4.0 package only. Migrating OC 2.2/2.3/3.0 to Events is a separate initiative.

---

## Build vs Source: What Needs to Change Where

The current build system (`build.sh`) generates version-specific packages by sed-transforming the OC 2.3 source. This works for OC 1.5–3.0 because changes are renames and file moves. OC 4.0 needs **new code that doesn't exist in the source today**.

### What the build script CAN handle (~80%)

| Change | Build script approach |
|--------|----------------------|
| Route strings (`extension/module/` → `extension/client_translate_expert/module/`) | `sed` replacement |
| Method separator in JS (`/translate` → `.translate`) | `sed` replacement |
| Directory restructure into `extension/client_translate_expert/` | `mkdir` + `mv` |
| Settings prefix (`module_` — same as OC3) | Already done for OC3, reuse |
| Token (`user_token` — same as OC3) | Already done for OC3, reuse |
| Class name changes | `sed` replacement |
| Create `install.json` | `heredoc` in build script |
| Add namespace declarations to PHP files | `sed` insert at top of file |
| Change `extends Controller` → `extends \Opencart\System\Engine\Controller` | `sed` replacement |
| Remove OCMOD XML from OC4 package | `rm` in build script |

### What REQUIRES new source code (~20%)

| Change | Why build scripts can't handle it |
|--------|----------------------------------|
| **Event handler method (`eventHeaderAfter`)** | Entirely new PHP method (~30 lines) that injects JS/CSS into rendered header output. No existing code to transform. |
| **Event registration in `install()`/`uninstall()`** | New logic to register/remove events via `$this->model_setting_event->addEvent()`. Must be added to controller. |
| **Bootstrap 5 template (`.twig`)** | Too many CSS class changes (`panel` → `card`, `data-toggle` → `data-bs-toggle`, `pull-right` → `float-end`, `btn-default` → `btn-secondary`, `input-group-addon` → `input-group-text`, etc.) across a 338-line template to do reliably with sed. Needs a separate OC4-specific `.twig` template. |
| **Version branch in `client_translate_expert_core.php`** | Add `version_compare(VERSION, '4.0', '>=')` for module prefix (`extension/client_translate_expert/module`) and library loading path. |
| **JS char count display for Bootstrap 5** | The `showTranslatedCharCount()` function targets `#header > .nav.pull-right` and `#header > .container-fluid > .nav.navbar-right` — OC4's admin header uses different selectors. |

### Recommended source changes

1. **Add `eventHeaderAfter()` method to controller** — wrapped in a marker comment that the build script includes only for OC4
2. **Create OC4-specific `.twig` template** — `client_translate_expert.oc4.twig` in sources, copied by build script
3. **Add OC4 version branch in `_core.php`** — extend existing `version_compare` blocks
4. **Add OC4-aware selectors in JS** — extend `showTranslatedCharCount()` with OC4 header selectors

---

## Effort Estimate

### Source changes (~20% of work)

| Task | Effort | Details |
|------|--------|---------|
| Create `eventHeaderAfter()` method | 0.5 day | New PHP method in controller to inject JS/CSS via event |
| Update `install()`/`uninstall()` for events | 0.25 day | Add event registration/cleanup code |
| Create OC4 Bootstrap 5 `.twig` template | 1 day | Separate template: `panel→card`, `data-toggle→data-bs-toggle`, etc. |
| Add OC4 version branch in `_core.php` | 0.25 day | Extend existing `version_compare` blocks |
| Update JS for OC4 header selectors | 0.25 day | `showTranslatedCharCount()` targets different admin header structure |
| PHP 8.0 compatibility review & fixes | 0.5 day | Null handling, deprecated functions |

### Build script changes (~80% of work)

| Task | Effort | Details |
|------|--------|---------|
| Add OC4 target to `build.sh` | 0.5 day | New section: sed replacements, dir restructure, install.json |
| Namespace insertion via sed | 0.25 day | Add `namespace` declarations to PHP files |
| Route/class name transformations | 0.25 day | Similar to existing OC3 transforms |
| Directory restructure logic | 0.25 day | Rearrange into `extension/client_translate_expert/` layout |
| Remove OCMOD XML from OC4 package | trivial | `rm` in build script |

### Testing

| Task | Effort |
|------|--------|
| Testing & debugging on OC 4.0 | 1 day |

### Total: ~5 days

---

## Recommended Approach

1. **Start with the Event system** — write `eventHeaderAfter()` and event registration. This is the riskiest change and should be validated first on an OC4 test instance.

2. **Create OC4 Bootstrap 5 template** — add `client_translate_expert.oc4.twig` to sources. Too many CSS class changes for sed.

3. **Extend build script** — add OC4 target after the OC3 section. Base it on the OC3 variant (shares `user_token`, `module_` prefix), then apply OC4-specific transforms.

4. **Keep core library shared** — `client_translate_expert_core.php` and `vh_google_translator.php` stay as single-source files. Add `version_compare(VERSION, '4.0', '>=')` branches. Build script adds namespaces via sed.

5. **Package structure** — build script restructures into `extension/client_translate_expert/` layout and generates `install.json` from version/metadata.

6. **Test on OC 4.0.2.3+** — this version reintroduced OCMOD, giving a fallback if the Event approach has gaps.

### Implementation order

```
Phase 1: Source changes (new files/code)
  ├── 1a. eventHeaderAfter() method + install/uninstall event registration
  ├── 1b. OC4 Bootstrap 5 .twig template
  ├── 1c. version_compare branches in _core.php
  └── 1d. JS header selector updates

Phase 2: Build script (build.sh additions)
  ├── 2a. Copy from OC3 variant as base
  ├── 2b. Namespace insertion + class rename transforms
  ├── 2c. Route transforms (extension/client_translate_expert/module/ + dot separator)
  ├── 2d. Directory restructure into self-contained layout
  ├── 2e. Generate install.json
  ├── 2f. Copy OC4 .twig template (replacing OC3 version)
  └── 2g. Remove OCMOD XML

Phase 3: Testing
  └── 3a. Deploy and test on OC 4.0.2.3+ instance
```

---

## References

- [OpenCart 4 Extension Development Guide (GitHub)](https://github.com/IP-CAM/OC-4-Extension-Development-Guide)
- [Comprehensive Guide to Developing an Extension for OpenCart 4 (Playful Sparkle)](https://playfulsparkle.com/en-us/resources/articles/web-development/comprehensive-guide-to-developing-an-extension-for-opencart-4/)
- [Migrating OpenCart Extension from v3 to v4 (Peersol)](https://peersol.com/migrating-your-opencart-extension-from-v3-to-v4-a-guide-to-substantial-changes/)
- [OpenCart Events System (GitHub Wiki)](https://github.com/opencart/opencart/wiki/Events-System)
- [OpenCart Developer Guide: Extensions](https://docs.opencart.com/developer-guide/extensions)
