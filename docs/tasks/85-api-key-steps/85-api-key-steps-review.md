# 85-api-key-steps review

Scope: `git diff HEAD -- docs/store` (2 files, 28 insertions, 14 deletions). Requirements: issue #85 and `85-api-key-steps_plan.md`. The change is docs-only. No version bump and no build, by design.

## Strengths

- Both files have the same structure: 1 H2, 8 numbered steps in the same order, the unchanged closing line, a lead-in line and 2 bullet links. The step numbers go from 1 to 8 with no gap and no duplicate.
- The merge follows the plan. The user's 7 steps are all present. The kept "create a project" step (3) and the Connection card step (8) fill the gaps in the user's text.
- The uk H2 is the user's text verbatim: "Як отримати ключ Google Cloud Translate API". The en H2 keeps the module term "Cloud Translation API", as the plan says.
- Step 6 states the 300 USD credit and adds "the offer can change, so check the current offer" in both files.
- Step 8 uses the correct card label. I checked the language files:
  - en-gb `te_settings_card_connection` = "Connection" (`sources/upload/admin/language/en-gb/extension/module/client_translate_expert.php:93`)
  - uk-ua `te_settings_card_connection` = "Підключення" (`sources/upload/admin/language/uk-ua/extension/module/client_translate_expert.php:94`)
- Each URL occurs 1 time in each file. The bullets have the labels "Article" / "Video" and "Стаття" / "Відео".
- The Markdown is correct. Blank lines separate the list, the closing line, the lead-in line and the bullets. `git diff --check` finds no whitespace errors.
- UI quotes follow the plan: straight quotes in en, «» in uk.
- No other file outside `docs/tasks/` refers to the old section titles or to the 2 files.
- The en text is a correct translation of the uk text. The meaning of each step is the same.

## Issues

### Critical (must fix)

None.

### Important (should fix)

None.

### Minor (nice to have)

1. `docs/store/description.uk.md:76`: step 6 uses "включити виставлення рахунків", but step 5 uses "Увімкніть", and line 23 uses "увімкнений". This gives 2 verbs for the same action in one section. "Увімкнути" is the usual Ukrainian UI verb for "enable". Fix: change the text to "Вам, можливо, доведеться увімкнути виставлення рахунків." Keep it as it is only if "включити" is the user's own original wording.
2. `docs/store/description.en.md:76` and `docs/store/description.uk.md:76`: the new text writes "300 USD" / "300 доларів США". The "Settings and tools" section of the same files (line 63) writes "$20". Two currency formats occur in one file. The plan and the issue ask for "300 USD", so this is acceptable. If you want one format, change one of the two notations.
3. `docs/store/description.en.md:82` and `docs/store/description.uk.md:82`: the lead-in line says "A separate article tells how to get the key:" ("Окрема стаття про отримання ключа:"), but the list under it also has a video. This is a small mismatch. A possible fix: "A separate article and a video tell how to get the key:" ("Окрема стаття та відео про отримання ключа:"). The issue asks only for an "article" note, so this change is optional.
4. `docs/store/description.uk.md:77`: "Перейдіть до «Облікових даних»" changes the case of the UI label in the quotes. If the Google console shows "Облікові дані", a reader can still find it. A form that keeps the label as it is: "Відкрийте розділ «Облікові дані»". This is optional. The console names cannot be checked here.

## Assessment

**Ready to merge?** Yes. Both sections meet the issue and the plan. They have the same structure, the correct card labels, valid Markdown and no broken references. The 4 minor items are wording polish only.
