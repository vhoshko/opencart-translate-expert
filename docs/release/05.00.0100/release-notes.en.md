# Release 05.00.0100

Release date: 23.11.2026

## New features

- Support OpenCart 4.1
- Add a new admin panel design: Website content, Catalog files, Admin files, Settings, Debug and About tabs
- Add the translation of language files in the admin: list all files and edit translated values
- Scan and translate the catalog language files of extensions on OpenCart 4.x
- Add cron translation for database fields
- Add a setting to not load the module on selected admin routes
- Make the API client language code map a global setting
- Allow many values in the table, category and stock status filters, and add a multiselect manufacturer filter with a 100-item cap
- Mask the Google API key in the settings, with a button to show it
- Collapse the cron, excluded routes and language codes cards on the Settings tab
- Add a summary bar and return JSON data from the AJAX actions
- Use POST for removeDataFromDb and resetCache, show a resetCache message and return JSON errors on exceptions
- Remove the Reset button from the character count in the admin header
- Add the module icon and a colored part to the module name
- Add crypto donation info to the README, the About tab and the store descriptions
- Add the /release command to bump the version, tag, build and publish a GitHub release

## Fixes

- Split Google batch requests by text count to avoid "Too many text segments"
- Count the characters that go to Google in the analysis and in the translation run
- Keep the monthly character count when you save the settings
- Show the translated character count in the OpenCart 1.5 admin header
- Add the Font Awesome 4.4.0 font files to the OpenCart 1.5 package
- Read language file keys with PHP tokens, so the analysis finds values with PHP code
- Align the module admin language keys and move hard-coded UI text to the language files
- Select the second language as the default "to" language when no session value exists
- Stop the admin JavaScript error when a batch editor textarea id has no number
- Stop the admin JavaScript error when the URL has no route parameter
- Cast OCMOD header values, so unsaved settings do not break the admin JavaScript
- Install the release version of _version.txt in docker/install-module.sh

## Other changes

- Move the library files to translate_expert/ and rename them with the prefix translate_expert_
- Move the OpenCart version rewrites from build.sh into the module code
- Add Docker test stores for OpenCart 1.5, 2.0, 2.1, 2.2, 2.3, 3.0 and 4.0, and the uk-ua language
- Add the Claude Code workflow config and a JavaScript syntax check
- Rewrite the agent instructions in ASD-STE100 Simplified Technical English
- Set the /do workflow models to Opus plan, Sonnet implementation and Opus review
- Add store description files in English and Ukrainian, and expand the Google Cloud Translation API key steps
- Add the PoC of the new admin panel design and the JSON formats
- Update the README
- Update the About module: link GitHub and remove the translator.codeguild.com.ua references
