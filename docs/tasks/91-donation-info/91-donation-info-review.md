# 91-donation-info review

Scope: `git diff HEAD` on branch `feat/91-donation-info`, plus the new files `docs/donate/*.png` and `tests/test_client_translate_expert_donation_info.php`. The review skips `docs/tasks/91-donation-info/tmp/` and `releases/`. The settled decisions in the brief (3 wallets, no fiat, no FUNDING.yml, QR images only in `docs/donate/`, text-only About card) are out of scope.

## Strengths

- Address correctness. A `grep -F` on each correct address finds exactly 1 match in each of these 7 files: `README.md`, the 2 store descriptions, the 3 language files and the new test. A regex search for any Tron, `bc1q` or `0x` 40-hex token finds no other token in these files. No file has a near-miss address.
- The language values are plain text. The templates add the markup. The OC4 template uses `{{ key }}` with no `|raw`. No value contains `&`, `<`, `>` or a quote, so the output is the same with and without Twig autoescape.
- The `.tpl` uses `<?php echo $key; ?>`. This is the same form as `te_about_card` and the other card heads in the file.
- The markup is balanced in both templates (`<p>`, `<strong>`, `<code>`, `<a>`, 2 nested `<div>`). It uses no Bootstrap class, so it works with BS3 (OC 2.x/3.0), BS5 (OC 4.x) and no Bootstrap (OC 1.5). The new card is a sibling of the existing `te-card` inside `#te-about-app`. The `.te-app .te-card` `margin-bottom` gives the space between the cards.
- The external link has `target="_blank"` and `rel="noopener noreferrer"`. The URL is a template literal, not a language value.
- The controller change adds the keys only to the existing `$te_text_key` whitelist. No new code path, no PHP 7+ syntax, no version-specific form.
- The ru-ru and uk-ua translations keep the meaning of each warning:
  - Tron: only the Tron network, not ERC-20 or BEP-20.
  - Bitcoin: only BTC on the Bitcoin network, native SegWit, starts with `bc1`.
  - Base: only the Base network (L2 for Ethereum), EVM address valid on other EVM networks, a transfer on Ethereum mainnet or another EVM network can fail to arrive.
  The Russian "Перевод" is the usual crypto word for a transfer. The Ukrainian "Переказ" is correct.
- The README table uses relative paths (`docs/donate/qr-*.png`) and `width="180"`. GitHub keeps the `width` attribute on `<img>`. The `## Donate` heading gives the anchor `#donate`. No other heading makes the same anchor.
- The CLAUDE.md entry follows ASD-STE100. Each sentence is 25 words or fewer, it has no semicolon, and the noun clusters are short. It uses the same form as the other test entries.
- All 16 PHP test scripts pass on this tree. They include the language key parity check in `test_client_translate_expert_localization.php` (1101 passed) and `test_oc4_twig_filters.php` (29 passed).
- The new test is compatible with PHP 5.6 and 8.x (`array()`, closure error handler, the 5-argument `file_get_contents`). Its output format matches `test_client_translate_expert_settings_cards.php`. A mutation probe confirms that it fails on a typo in any address in a language file or in the README.

## Issues

### Critical (must fix)

None.

### Important (should fix)

None.

### Minor (nice to have)

1. `tests/test_client_translate_expert_donation_info.php:44-49`: the docs check uses `strpos($src, $addr) !== false`. If a file has the correct address and also a wrong or old address, the test still passes. The probe `docs/tasks/91-donation-info/tmp/mutation_probe.sh` shows this: a second wrong Tron address in the README gives "51 passed, 0 failed". Fix: for each doc file, collect every address-like token and require that each token is one of the 3 correct addresses:

   ```php
   preg_match_all('/\bT[1-9A-HJ-NP-Za-km-z]{33}\b|\bbc1[0-9a-z]{25,87}\b|\b0x[0-9A-Fa-f]{40}\b/', $src, $m);
   $unknown = array_diff(array_unique($m[0]), array_values($wallets));
   check(!$unknown, "{$rel}: no unknown wallet address" . ($unknown ? ' (' . implode(', ', $unknown) . ')' : ''));
   ```

   Also check that each address occurs exactly 1 time (`substr_count($src, $addr) === 1`). Apply the same token check to the 3 language files.

2. `tests/test_client_translate_expert_donation_info.php:89-97`: the template check finds each key name as a substring anywhere in the file. If the key appears only in a comment, the check passes. The probe shows this case. The risk is low, because a human writes the templates. Fix (optional): match the output form, `'<?php echo $' . $k . '; ?>'` in the `.tpl` and `'{{ ' . $k . ' }}'` in the `.oc4.twig`.

3. `docs/store/description.en.md:102-114` and `docs/store/description.uk.md:102-114`: each wallet block has 3 lines with single line breaks. A Markdown renderer joins these lines into 1 paragraph ("Tron: TKB… Accepts: … Send only …"). The rest of the file uses lists for items like these. If the store form takes plain text, the text is correct as it is. If a Markdown renderer shows the text (GitHub preview or a store that renders Markdown), use a list for each wallet. An alternative is 2 trailing spaces at the end of each line:

   ```markdown
   - Tron: `TKB4TNF9GRCcJQHavDCaMbFQVsEp322BhU`
     - Accepts: TRX and tokens on the Tron network.
     - Send only on the Tron network. Do not use ERC-20 or BEP-20.
   ```

### No action

- Long addresses in `<code>` (42 characters for Bitcoin and Base) do not wrap in Bootstrap 3, because the string has no break point. The text can go out of the card only on a very narrow screen. The admin page is a desktop page, so no change is necessary. If necessary, add `word-break: break-all` to `.te-app .te-card__body code` in the CSS.
- The README rows put the long Base warning in a narrow column. GitHub wraps the cell text, so the table stays correct.
- The Base address checksum (EIP-55) is not checked in this review. The QR decode result is an established fact, and the address in every file equals the decoded string.

## Assessment

**Ready to merge?** Yes. The addresses are correct in all 7 files, the translations keep the warnings, the markup is safe for OC 1.5 through 4.x, and all tests pass. The 3 Minor items make the test stricter and the store Markdown cleaner. They do not block the merge.
