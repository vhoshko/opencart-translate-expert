# 85-api-key-steps

## Settings
- Planning model: Opus. Review model: Opus. The user confirmed this choice. The review is not independent, because the same model wrote the plan.
- Docs-only task. Run `ote-build-test` in checks-only mode before and after the change. Do not bump `_version.txt`. Do not build.
- No commit, no push and no PR. Do these only if the user asks later.
- The user gave no standing auto-approve for the review findings.
- Branch: `docs/85-api-key-steps` (from `origin/main`).

## Context
Issue #85: https://github.com/vhoshko/opencart-translate-expert/issues/85. Issue #83 added the 2 store description files. Their API key section is short. Store visitors need clearer steps and links to get the key.

Files to change (no other file):
- `docs/store/description.uk.md:69-78`, section "Ключ Google Cloud Translation API" (6 numbered steps and 1 closing line).
- `docs/store/description.en.md:69-78`, section "Google Cloud Translation API key" (6 numbered steps and 1 closing line).

The files are not in the OCMOD packages. All OC versions stay unchanged.

Current content of the section (same in both files):
1. Sign in and open the Google Cloud console (`https://console.cloud.google.com/`).
2. Create a project or choose one.
3. Enable the Cloud Translation API.
4. Set up billing if Google asks. Google can give a free trial credit. Check the current offer.
5. Create an API key in the credentials section.
6. Enter the key in the module settings, in the Connection card.

Closing line: "Google prices and rules can change. Check them in your Google Cloud account."

UI label of the card (verified by grep):
- en-gb: `te_settings_card_connection` = "Connection" (`sources/upload/admin/language/en-gb/extension/module/client_translate_expert.php:93`).
- uk-ua: `te_settings_card_connection` = "Підключення" (`sources/upload/admin/language/uk-ua/extension/module/client_translate_expert.php:94`).

Settled decisions from the user (do not open them again):
- uk H2: "Як отримати ключ Google Cloud Translate API" (verbatim, with "Translate").
- en H2: "How to get a Google Cloud Translation API key". Keep the module term "Cloud Translation API".
- Merge the user's steps with the 6 existing steps. Keep the Connection card step and the closing line.
- State the 300 USD credit for new users. Add a short note that the offer can change, and that the reader must check the current offer.
- Add a note that a separate article about the key exists. Put both links in both files. Label the YouTube link as a video.
- Keep the user's Ukrainian wording close. Fix only obvious typos, for example "статья" → "стаття".

This task reverses 2 rules of the task 83 plan: "no video links" and "no $300 credit as a fact". The user decided this in issue #85.

## Approach
Edit the uk file first, because the user gave the source text in Ukrainian. Then write the en file as a translation with the same order, step count and links. Merge the steps as in the list below. The list keeps the existing "create a project" step, because the API and the key belong to a project.

Merged steps (same order in both files):
1. Sign in to your Google account.
2. Go to Google Cloud Platform (the console): `https://console.cloud.google.com/`.
3. Create a project or select one (existing step 2).
4. In the search box, enter "Cloud Translation API" and select it.
5. Enable "Cloud Translation API".
6. Billing: you may need to enable billing. A new user can get 300 USD credit to try Google Cloud Platform. The offer can change, so check the current offer.
7. Go to Credentials and create an API key. At the top, select "Create credentials", then select "API key".
8. Enter the key in the module settings, in the Connection card ("Підключення" in uk).

After the list:
- The closing line about Google prices and rules (unchanged).
- A short note: a separate article tells how to get the key. Then the 2 links:
  - Article: `https://support.demollc.pw/133/poluchenie-klyucha-api-dlya-google-translate/`
  - Video: `https://www.youtube.com/watch?v=5hTlSGD4_zk`

Style rules (same as the other sections of each file):
- Plain Markdown. One H2 for the section. No H3.
- A numbered list for the steps, short sentences.
- Bare URLs, as the current step 1 uses. No HTML tags.
- Quote Google UI names with straight quotes in en and with «» in uk, as the uk file does for «Підключення».
- Do not use ASD-STE100 rules for the store text (it is store copy), but keep sentences short.

## Assumptions
- ❓ unverified: the Google console names ("Cloud Translation API", "Credentials", "Create credentials", "API key", the search box) are as in the user's text. This container has no browser and no Google access. If Google renamed an item, a reader cannot find it. The user wrote these steps, so the risk is low.
- ❓ unverified: the 300 USD credit for new users. No web check is possible here. The note "the offer can change, check the current offer" covers a change.
- ❓ unverified: both links work and show the correct content. The network allowlist blocks `support.demollc.pw` and `youtube.com`. If a link is dead, the reader gets an error page. The user gave the links in the issue.
- ❓ unverified: the language of the article and the video. The article URL looks Russian ("poluchenie-klyucha"). The user decided to put both links in both files, so the plan does not label a language.
- ❓ unverified: the user's original Ukrainian wording. The issue body gives the steps in English only. If the parent session has the original Ukrainian text, use it with only typo fixes. Else, translate the English steps into Ukrainian in the style of the current uk file.
- ✅ verified: the Connection card label is "Connection" (en-gb) and "Підключення" (uk-ua) (grep of the language files, see Context).
- ✅ verified: no other file links to the section title. A grep of `docs/store/*.md` and `README.md` for the old titles found only the 2 H2 lines.

## Steps
- [x] Run the checks-only tests (`ote-build-test`, "checks only", no bump, no build). Record the counts.
- [x] Edit `docs/store/description.uk.md`: replace the H2 and the steps of lines 69-78 with the merged list, the closing line and the links note.
- [x] Edit `docs/store/description.en.md`: write the same section as a translation of the uk section.
- [x] Docs checks:
  - Compare the 2 sections line by line: same step count, same order, same links, same closing line.
  - Grep both URLs in both files. Expect 1 match for each URL in each file.
  - Check the numbering: steps 1 to 8, with no gap and no duplicate.
  - Grep `## ` in both files. Expect the same H2 count as before (9) and the same order.
  - Grep "300" in both files. Expect the credit line and the "check the current offer" note in each file.
  - Grep "Connection" in en and "Підключення" in uk. Expect the last step to use the card label.
  - Grep "статья" in the uk file. Expect 0 matches.
- [x] Run the checks-only tests again. Record the counts.
- [x] Independent review (Opus, not independent, see Settings) → `85-api-key-steps-review.md`.
- [x] Address the review findings (the user picks which to fix or skip). If the fixes change only Markdown, skip the verify loop.
- [x] Re-review? If unreviewed fixes exist, ask the user. If yes, repeat the review → `85-api-key-steps-review-v2.md`.
- [x] Improvements pass → `85-api-key-steps-improvements.md`.

## Done
- Both store files now have the 8-step key section, the article note and the 2 links.
- The review (Opus, not independent) found no important issue. The user chose fixes for 3 of 4 minor findings. Finding 2 (300 USD and $20) is not a conflict, because they are different amounts.
- The user declined a re-review. The version stays 04.01.0216 (docs-only, no build). Checks-only tests passed before and after: 2 669 assertions.
- Unverified: Google console names, the 300 USD credit and both links. The container has no access to them.
- The plan said the Ukrainian source text was missing. The text was in the `/issue` arguments, and the uk file uses it.
- User asked to commit, push and open a PR.
