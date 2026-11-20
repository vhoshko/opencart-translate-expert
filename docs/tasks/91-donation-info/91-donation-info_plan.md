# 91-donation-info

## Settings

- Planning model: Opus
- Implementation model: Sonnet (`ote-implementer`)
- Review model: Opus
- Review findings rule: fix all findings. Skip a finding only with a verified reason, and write the reason in the review file.
- Build and tests: yes. The task changes files in `sources/`.
- Commit, push and PR: none. Do these only if the user asks later.
- Standing auto-approve: yes. On 19.11.2026 the user said "turn on auto approve mode". Do not ask questions and do not wait for plan approval.

## Context

GitHub issue #91 (https://github.com/vhoshko/opencart-translate-expert/issues/91) is the settled spec. Do not open its decisions again.

The project has no donation information. The owner has 3 crypto wallets (Tron, Bitcoin, Base) and one QR code image for each wallet. The task adds the donation information to the README, the About tab of the module admin page and the 2 store description files.

All OpenCart versions get the About tab change (1.5–3.0 through the `.tpl`, 4.x through the `.oc4.twig`). The QR images stay out of `sources/` and out of the OCMOD zips.

Wallets. Each address must be identical in every file:

| Network | Address | Accepts |
|---------|---------|---------|
| Tron | `TKB4TNF9GRCcJQHavDCaMbFQVsEp322BhU` | TRX and tokens on the Tron network |
| Bitcoin | `bc1qr38nutpnjeqygs8tnsypw32hqtgpe0wnuf3jnv` | BTC |
| Base | `0x5706F582478CE6C15Ef6489B103AC018280B0540` | ETH and tokens on the Base network |

Name no single token. Use the network warnings from the issue Notes:

- Tron: send only on the Tron network. Do not use ERC-20 or BEP-20.
- Bitcoin: send only BTC on the Bitcoin network. The address is a native SegWit address (starts with `bc1`).
- Base: send only on the Base network (an Ethereum L2). The address is an EVM address, so the same string is valid on other EVM networks. A transfer on Ethereum mainnet or on another EVM network may not arrive at the Base wallet.

Key files:

- `sources/upload/admin/view/template/extension/module/client_translate_expert.tpl:402-411`: `tab-about` with one `te-card` (head `$te_about_card`, body `$text_about_page`).
- `sources/upload/admin/view/template/extension/module/client_translate_expert.oc4.twig:400-409`: the same structure. The body is `{{ text_about_page|raw }}`.
- `sources/upload/admin/view/stylesheet/client_translate_expert.css:105-128`: the `.te-app .te-card*` styles.
- `sources/upload/admin/controller/extension/module/client_translate_expert.php:93`: loads the language. Line 207 sets `$data['text_about_page']`. Line 266 has the `$te_text_key` whitelist `foreach`. Its last item is `te_about_card`.
- `sources/upload/admin/language/en-gb/extension/module/client_translate_expert.php:103`, `ru-ru/...:104`, `uk-ua/...:104`: `te_about_card`.
- `tests/test_client_translate_expert_localization.php:60-98`: `checkModuleLanguageKeySets()` requires the same key set in the 3 language files.
- `tests/test_oc4_twig_filters.php`: static check of the `.twig` names and syntax.
- `build.sh:340-348`: rewrites `src="view/image/translate_expert_client/` in the OC4 language files.
- `README.md:120` (`## Support`) and `README.md:124` (`## License`).
- `docs/store/description.en.md:94` and `docs/store/description.uk.md:94` (Support section). An HTML comment block about images starts at line 98.
- `docs/tasks/91-donation-info/images/qr-bitcoin.png`, `qr-base.png`, `qr-tron.png`: the 3 QR images from the user.

## Approach

Copy the 3 QR images to a new folder `docs/donate/`. The README gets a `## Donate` section with a 3-column HTML table: the network, the QR image at a small fixed width, the address, the accepted coins and the warning. The About tab gets a second `te-card` after the existing card. This card shows the 3 addresses as text, the accepted coins, the warnings and a link to the README Donate section (https://github.com/vhoshko/opencart-translate-expert#donate). The About tab shows no images. The new strings are `te_donate_*` keys in the 3 language files. The controller sends them to the templates through the `$te_text_key` whitelist. The store descriptions get a plain markdown section with the same data and a bare link to the README for the QR codes. A new static PHP test checks the addresses, the images and the template keys.

Design notes:

- Keep the language values plain text (no HTML). The templates add the markup (`<code>`, `<a>`, `<strong>`). Then the OC4 template uses `{{ key }}` with no `|raw`. The `.tpl` uses `<?php echo $key; ?>`.
- Put the GitHub URL in the templates as a literal `href`. Put only the link text in a language key.
- Do not use the path `view/image/translate_expert_client/` in new text. `build.sh` rewrites this path.
- Do not make the new card collapsible. `tests/test_client_translate_expert_settings_cards.php` checks only the 4 Settings cards by body id.

## Assumptions

- ✅ verified: each QR image decodes to the address of its own network. A CoreImage `CIDetector` script (macOS `swift`, no install) gave `qr-tron.png` → `TKB4TNF9GRCcJQHavDCaMbFQVsEp322BhU`, `qr-bitcoin.png` → `bc1qr38nutpnjeqygs8tnsypw32hqtgpe0wnuf3jnv`, `qr-base.png` → `0x5706F582478CE6C15Ef6489B103AC018280B0540` (19.11.2026). `zbarimg`, `pyzbar` and `cv2` are not installed. The Swift script is in the decode step below.
- ✅ verified: the About tab is at `.tpl:402-411` and `.oc4.twig:400-409`, with one `te-card` (read on 19.11.2026).
- ✅ verified: the controller whitelist at line 266 ends with `te_about_card` and sets `$data[$te_text_key] = $this->language->get($te_text_key)` at line 267.
- ✅ verified: `te_about_card` is at line 103 (en-gb) and line 104 (ru-ru, uk-ua).
- ✅ verified: `checkModuleLanguageKeySets()` fails if the key sets of the 3 language files differ.
- ✅ verified: the README has `## Support` at line 120 and `## License` at line 124. Both store descriptions have `## Support` / `## Підтримка` at line 94 and an HTML comment at line 98.
- ✅ verified: `build.sh` changes the OC4 language files only for `src="view/image/translate_expert_client/` (lines 342-347). New text without this path is safe.
- ✅ verified: the settings cards test finds cards by body id. A new card in `tab-about` without a body id does not affect it.
- ❓ unverified: GitHub renders `<img width="...">` in a README table at the given width. Not checked, because no push is planned. If false, the images show at full size (756 px). The page stays correct.
- ❓ unverified: the "Accepts" column and the network warnings. The issue says they are general network behavior, not confirmed in Trust Wallet. If false, the text can mislead a sender. The owner gave the text, so use it as given.
- ❓ unverified: the README anchor `#donate` for the heading `## Donate`. GitHub makes anchors from the lowercase heading text. Not checked on a pushed page. If false, the link opens the README top.

## Steps

- [x] Images: create `docs/donate/`. Copy `qr-bitcoin.png`, `qr-base.png` and `qr-tron.png` from `docs/tasks/91-donation-info/images/` with the same names. Run `cmp` on each pair.
- [x] QR decode: run the Swift script on the 3 files in `docs/donate/`. Each output must equal the address of its network. If `swift` is missing, try `zbarimg`, then `python3` with `pyzbar` or `cv2`. If no decoder exists, do not install system packages. Then mark this step unverified in Done. Script (put it in `docs/tasks/91-donation-info/tmp/qr.swift`):

  ```swift
  import CoreImage
  import Foundation
  for p in CommandLine.arguments.dropFirst() {
    guard let img = CIImage(contentsOf: URL(fileURLWithPath: p)) else { print("\(p): LOAD FAIL"); continue }
    let d = CIDetector(ofType: CIDetectorTypeQRCode, context: nil, options: [CIDetectorAccuracy: CIDetectorAccuracyHigh])!
    let f = d.features(in: img).compactMap { ($0 as? CIQRCodeFeature)?.messageString }
    print("\(p): \(f)")
  }
  ```

  Command: `swift docs/tasks/91-donation-info/tmp/qr.swift docs/donate/qr-tron.png docs/donate/qr-bitcoin.png docs/donate/qr-base.png`
- [x] README: add `## Donate` between `## Support` and `## License`. Write 1 short intro sentence. Add an HTML table with one column for each network (Tron, Bitcoin, Base). In each column, put:
  - the network name
  - `<img src="docs/donate/qr-<network>.png" width="180" alt="<Network> QR code">`
  - the address in `<code>`
  - the accepted coins
  - the network warning
- [x] Language strings (en-gb, ru-ru, uk-ua): add the new keys directly after `te_about_card`, in the same order in all 3 files. Use plain text with no HTML. Write real Russian and Ukrainian translations. Keep each address string identical in all 3 files. Keys:
  - `te_donate_card` (card title, for example "Support the project")
  - `te_donate_intro` (1 sentence)
  - `te_donate_network_tron`, `te_donate_network_bitcoin`, `te_donate_network_base`
  - `te_donate_address_tron`, `te_donate_address_bitcoin`, `te_donate_address_base` (the exact addresses)
  - `te_donate_accepts_tron`, `te_donate_accepts_bitcoin`, `te_donate_accepts_base`
  - `te_donate_warning_tron`, `te_donate_warning_bitcoin`, `te_donate_warning_base`
  - `te_donate_qr_link` (link text, for example "QR codes on GitHub")
- [x] Controller: add all new `te_donate_*` keys to the `$te_text_key` whitelist at line 266, after `te_about_card`. Keep PHP 5.6 syntax.
- [x] Templates (`.tpl` and `.oc4.twig`): in `tab-about`, add a new `te-card` after the existing card and inside `te-about-app`. The head is the card title. The body has:
  - the intro sentence
  - one block for each network: the network name in `<strong>`, the address in `<code>`, the accepted coins and the warning
  - a link `<a href="https://github.com/vhoshko/opencart-translate-expert#donate" target="_blank" rel="noopener noreferrer">` with the `te_donate_qr_link` text

  In the `.tpl`, use `<?php echo $te_donate_...; ?>`. In the `.oc4.twig`, use `{{ te_donate_... }}` with no filter. Do not use Twig 3.18-only syntax. Add CSS to `client_translate_expert.css` only if the default `te-card` styles are not sufficient.
- [x] Store descriptions: in `docs/store/description.en.md`, add `## Donate` after the Support section and before the HTML comment. In `docs/store/description.uk.md`, add `## Підтримати проєкт` at the same position. Use plain markdown and bare URLs. For each network, give the address, the accepted coins and the warning. Add the bare link https://github.com/vhoshko/opencart-translate-expert#donate for the QR codes. Add no images.
- [x] New test `tests/test_client_translate_expert_donation_info.php` (static, no DB, no API key). Use the `[PASS]` / `[FAIL]` output and the exit code of the other tests. Make any PHP warning fail the test. Checks:
  - each of the 3 addresses appears exactly as given in `README.md`, `docs/store/description.en.md` and `docs/store/description.uk.md`
  - in each of the 3 language files (loaded into `$_`), `te_donate_address_<network>` equals the exact address
  - `docs/donate/qr-tron.png`, `qr-bitcoin.png` and `qr-base.png` exist, are PNG files (signature check) and are referenced by `README.md`
  - `README.md` has the `## Donate` heading
  - the `.tpl` and the `.oc4.twig` reference each `te_donate_*` key of the en-gb file, and contain the link `https://github.com/vhoshko/opencart-translate-expert#donate`
  - the controller whitelist contains each `te_donate_*` key
  - no file under `sources/` references `docs/donate/` or `qr-*.png`
- [x] CLAUDE.md: add the new test command and a 1-line STE comment to the Tests code block, after the settings cards test. Add 1 short STE paragraph that tells what the test covers.
- [x] Mutation check of the new test: change 1 character of 1 address in a temp copy, or temporarily in 1 file. Make sure that the test fails. Then restore the file and make sure that the test passes.
- [x] Bump version + build + test: bump the patch in `_version.txt` (`04.01.0218` → `04.01.0219`). Run `./build.release.sh`. Run all tests in the CLAUDE.md Tests section, plus the new test. Unzip the `.full.zip` to a temp dir. Make sure that no package contains `qr-*.png` or `docs/donate`. Make sure that the oc4.x language files contain the 3 addresses.
- [x] Independent review → `91-donation-info-review.md` (Opus, `ote-code-reviewer`). Tell the reviewer to use `git diff HEAD` and `git status` for the new files. Tell the reviewer to check each address against the table in this plan.
- [x] Address review findings (fix all. Skip only with a verified reason, and write it in Findings).
- [x] Bump version + build + tests (verify loop).
- [x] Re-review? Standing auto-approve is on, so do not ask the user. If the fixes change logic (the test or the controller), do a second review → `91-donation-info-review-v2.md`. If the fixes change only text, skip the second review and record the reason in Done.
- [x] Improvements pass → `91-donation-info-improvements.md`.

## Notes

- The screenshots `docs/store/screenshots/en/06-about.png` and `docs/store/screenshots/uk/06-about.png` can become outdated, because the About tab gets a new card. An update is out of scope.
- No `docs/store/` description in Russian exists. The ru-ru change is only in the language file.

## Done

- Landed: donation info in `README.md` (QR images in `docs/donate/`), a new About tab card in the `.tpl` and `.oc4.twig`, 15 `te_donate_*` keys in 3 language files, the controller whitelist, the store descriptions (en, uk) and the new test `tests/test_client_translate_expert_donation_info.php` (66 checks).
- Final version: `04.01.0220`. The build and all tests pass. The QR images are in no package.
- QR decode: each image decodes to its address (CoreImage `CIDetector`, 19.11.2026).
- Review: 0 Important and 3 Minor findings in `91-donation-info-review.md`. All 3 are fixed. The 3 "no action" items are skipped, as the reviewer marked them. Second review skipped: the fixes changed only the test and the store text, and standing auto-approve was on.
- Open: the `#donate` anchor, the README image width and the "Accepts" text are unverified until the branch is on GitHub. The `06-about.png` store screenshots are now out of date. The stopped first implementer run left `lang.py`, `o.txt` and `README.bak` in `tmp/`.
