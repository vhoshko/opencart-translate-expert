<?php
// Heading
$_['heading_title']    = '<img src="view/image/translate_expert_client/translate-icon-32.png" alt="" style="height:1em;width:auto;vertical-align:-0.125em;margin-right:6px;" />Translate <span style="color:#f56b00;">Expert</span> (Full)';

// Text
$_['text_extension']   = 'Modules';
$_['text_success']     = 'Module settings updated!';
$_['text_edit']        = 'Editing a module';

$_['text_login_is_correct']        = '<div class="translate_expert_correct"> Authorization check succeeded. </div>';
$_['text_need_to_login'] = '<div class="translate_expert_incorrect"> Authorization check is NOT successful.</div><br> To receive the key, register or log in to the website <a href="https://github.com/vhoshko/opencart-translate-expert" target="_blank">https://github.com/vhoshko/opencart-translate-expert</a> ';
$_['text_need_to_pay'] = '<div class="translate_expert_incorrect"> You have run out or very few characters left to translate.</div><br> Go to the website and buy additional symbols <a href="https://github.com/vhoshko/opencart-translate-expert" target="_blank">https://github.com/vhoshko/opencart-translate-expert</a> ';
$_['text_no_need_to_pay'] = '<div class="translate_expert_correct"> You still have a lot of characters to translate.</div> ';
$_['text_about_page']        = '<div class="panel-body"><h2>Thank you for using our module!</h2><h4>Support <a href="https://github.com/vhoshko/opencart-translate-expert/issues" target="_blank"><b>GitHub Issues</b></a></h4><div class="alert bg-success"><h4>The module was tested on standard pages of the admin part, open an issue on GitHub if you need to adapt to any other pages.<p></p><p>All users are provided with free consultation and support on the operation of the module via GitHub Issues.</p></h4></div></div>';
$_['text_current_module_version']        = 'The current version of the module <b>{current_module_version}</b>';
$_['text_newest_module_version']        = 'The latest available version of the module <b>{newest_module_version}</b>';
$_['text_you_need_to_update_module']        = '<div class="translate_expert_incorrect">For the correct operation of the module, we recommend you upgrade the module to a newer version. <br>You can always find the latest version on the <a href="https://github.com/vhoshko/opencart-translate-expert/releases" target="_blank">https://github.com/vhoshko/opencart-translate-expert/releases</a></div>';
$_['text_you_have_the_newest_version_of_module']        = '<div class="translate_expert_correct">Congratulations you have the latest version of the module.</div>';
$_['text_debug_log']        = 'Debug log';
$_['text_clear_debug_log_success']	   = 'Success: You have successfully cleared your debug log!';
$_['text_clear_confirm']	   = 'Are you sure you want to delete the debug log file?';

// Entry;
$_['entry_server_key'] = 'Google Translate Api Key';
$_['button_show_key'] = 'Show key';
$_['button_hide_key'] = 'Hide key';
$_['button_cron_secret_generate'] = 'Generate';
$_['entry_cron_bulk_amount'] = 'Rows per cron run';
$_['help_cron_bulk_amount'] = 'The cron link translates up to this number of database rows in one run (1 to 100000). This number controls the size of a run. A run stops after 15 minutes at most. The PHP max_execution_time of the host and the timeout of the scheduler still apply, so choose a number that finishes in time. The cron link below includes this number as the bulk_amount parameter. If you change the number, copy the new link to the scheduler. A link without bulk_amount uses the saved value.';
$_['entry_cron_secret'] = 'Cron secret';
$_['help_cron_secret'] = 'Secret for the cron link: 16 to 128 characters, only letters, digits, <code>-</code> and <code>_</code>. Press the button to make a random secret. If the secret is empty, the cron link is off. Save the settings to apply the change.';
$_['entry_cron_link'] = 'Cron link';
$_['help_cron_link'] = 'Call this link from cron to translate the store data without the admin page. Example for crontab:<br><code>*/10 * * * * wget -q -O - "&lt;link&gt;"</code><br>The link uses the filters that are now selected in the analysis settings below and the secret from the Cron secret field. It translates all text columns of the selected tables. If no table is selected, it processes all tables in order. Each call translates one batch and returns JSON: <code>{"done":false,...}</code>. Call it again until <code>done</code> is <code>true</code>. Each run stops after 15 minutes at most. The PHP max_execution_time of the host and the timeout of the scheduler (for example the default read timeout of wget) still apply, so choose a bulk amount that finishes in time. Only one run works at a time. Maintenance mode blocks the link. Keep the link private: the secret is a part of it.';
$_['help_multi_select'] = 'Hold Ctrl (Cmd on Mac) and click to select more than one value. Hold Shift and click to select a range. Click in the list and press Ctrl+A (Cmd+A on Mac) to select all values. If nothing is selected, all values are used.';
$_['button_select_all'] = 'Select all';
$_['help_multi_select_limit'] = 'You can select up to %s items.';
$_['button_select_first'] = 'Select first %s';
$_['warning_cron_link_long'] = 'The link is longer than %s characters. Some servers and cron panels do not accept such a long link. Select fewer items in the filters.';
$_['button_clear_selection'] = 'Clear selection';
$_['error_cron_secret'] = 'The cron link is off. The cron secret is empty or not valid (16 to 128 characters). Enter a secret or press the Generate button, then save the settings.';
$_['text_cron_secret_unsaved'] = 'The secret is not saved. The cron link works only after you save the settings.';
$_['entry_limit'] = 'Limit';
$_['entry_width'] = 'Width';
$_['entry_height'] = 'Height';
$_['entry_status'] = 'Status';
$_['entry_login'] = 'Your login';
$_['entry_info'] = 'Information';
$_['entry_char_quantity'] = 'Number of characters available for translation';
$_['entry_analization'] = 'Analize non-translated strings';
$_['entity_begin_analization'] = 'Analyze';
$_['entry_analization_mode'] = 'Analysis mode';
$_['entry_stop_modal_process'] = 'Stop';
$_['entry_stop_modal_process_confirmation'] = 'Are you sure you want to stop current process?';
$_['entry_close'] = 'Close';
$_['entry_try_translate']     = 'Translation verification form<br>API key test';
$_['entry_try_translate_text']     = 'Translation verification form';
$_['entry_translate_expert_version']     = 'Module version';
$_['entry_php_version']     = 'PHP version';
$_['entry_opencart_version']= 'Opencart version';
$_['entry_debug_status']     = 'Debug mode status';
$_['entry_table']     = 'Table';
$_['entry_table_deep']     = 'Deep analize';
$_['entry_table_deep_description']     = 'Full analysis of the entire table (Turn off if the analysis takes a very long time)';

// remove translation
$_['entry_remove_translation_records_from_db'] = 'Remove translation value from the database';
$_['entity_begin_removing_data_from_db'] = 'Remove translation';
$_['entry_remove_translation_records_from_db_confirmation'] = 'WARNING! We strongly recommend making a backup of the database. Are you sure you want to delete the {langCode} language translation value from the database?';
$_['entry_removing_data_from_db_process_is_started'] = 'Removing data from the database is started.';

// char count
$_['entry_server_show_translation_char_count_globally'] = 'Show translation char count globally in the header';
$_['entry_server_reset_translation_char_count_on_start_month'] = 'Reset the character count for translations at the beginning of every month';
$_['entry_server_translation_char_count'] = 'Translation char count';

// excluded routes
$_['entry_excluded_routes'] = 'Do not load the module on these pages';
$_['help_excluded_routes'] = 'Enter one admin page route on each line. End a line with <code>*</code> to match all routes that start with it.<br>Examples:<br><code>catalog/product*</code> — the product list and all product pages<br><code>extension/module/my_module</code> — a module page in OpenCart 2.3–3.0<br><code>module/my_module</code> — a module page in OpenCart 1.5–2.2<br><code>extension/my_extension/module/my_module</code> — a module page in OpenCart 4<br>You can also paste a full admin page URL. The settings page of this module always loads the module.';

// reset cache
$_['entry_reset_translation_cache'] = 'Reset translation cache';



// Tabs;
$_['tab_general'] = 'General';
$_['tab_translate_site'] = 'Website content translation';
$_['tab_localization']   = 'Catalog files';
$_['tab_admin_localization']   = 'Admin files';
$_['tab_debug']          = 'Debug';
$_['tab_about'] = 'About module';

// Value;
$_['value_analization_mode_only_empty'] = 'Search for empty values only';
$_['value_analization_mode_same_value'] = 'Search for duplicate values';
$_['value_analization_mode_both'] = 'Search for empty and identical values';


// js constants;

// Error;
$_['error_permission'] = 'You do not have permission to manage this module!';
$_['error_bad_filter'] = 'The filter values are not valid. Reload the page and select the filters again.';
$_['error_language_to_required'] = 'Select the language to remove the translations from.';
$_['te_site_error_bad_detail_request'] = 'The table, the column or the language is not valid.';
$_['error_width'] =' You must specify the width! ';
$_['error_height'] =' You must specify the height! ';
$_['error_unlicensed'] = 'You are using the unlicensed version of the module! <br> We recommend buying the module on the website <a href="https://github.com/vhoshko/opencart-translate-expert" target="_blank">https://github.com/vhoshko/opencart-translate-expert</a> or contact the module support via <a href="https://github.com/vhoshko/opencart-translate-expert/issues" target="_blank">GitHub Issues</a>';
$_['error_debug_log_warning']	   = 'Warning: Your debug log file %s is %s!';

$_['error_lang_from_folder_is_not_exists']	   = 'Localization files folder for "Language to translate from" not found: ';
$_['error_lang_from_and_lang_to_are_equal']	   = 'Select language to translate to';
$_['error_localization_save_target_invalid']	   = 'The target folder or the file path is not valid. The file is not saved.';
$_['error_localization_save_failed']	   = 'The file is not saved. Make sure that the web server can write to this folder:';
$_['error_localization_save_empty']	   = 'The file is empty. Nothing to save.';




$_['entry_table_product_filter'] = 'Product filter';
$_['entry_table_product_status_filter'] = 'Product status filter, only active products';
$_['entry_table_product_quantity_filter'] = 'Product quantity filter, only products with quantity > 0)';
$_['entry_table_product_category_id_filter'] = 'Product category filter';
$_['entry_table_product_stock_status_filter'] = 'Product stock status filter';
$_['entry_table_product_manufacturer_filter'] = 'Product manufacturer filter';

// Front-end strings of the new admin design (document.te_i18n on the module page)
$_['te_alert_details'] = 'Details';
$_['te_alert_close'] = 'Close';
$_['te_alert_not_json'] = 'The server did not send a JSON answer.';
$_['te_summary_month'] = 'This month: {count} / {limit}';
$_['te_summary_cost'] = 'Cost over free limit: {cost}';
$_['te_remove_completed'] = 'Removing data from the database is completed: {language}.';
$_['te_remove_start'] = 'Removing data from the database is started.';
$_['te_remove_table'] = 'Table {table}: {rows} values cleared.';
$_['te_remove_column'] = '- {column}: {rows} values cleared.';
$_['te_remove_finish'] = 'Removing data from the database is finished.';
$_['te_time_in_sec'] = 'Time: {sec} seconds';
$_['te_summary_status'] = 'Status';
$_['te_summary_enabled'] = 'Enabled';
$_['te_summary_disabled'] = 'Disabled';
$_['te_summary_api_key'] = 'API key';
$_['te_summary_key_set'] = 'Set';
$_['te_summary_key_not_set'] = 'Not set';

// Front-end strings for all admin pages (document.te_i18n_global, written by the admin header)
$_['te_global_char_count'] = 'Translated characters';
$_['te_global_reset'] = 'Reset';
$_['te_global_reset_confirm'] = 'Are you sure you want to reset the translation char count?';
$_['te_global_translate'] = 'Translate';
$_['te_global_error_internal'] = 'Internal error, more info in console log!';

// Website content tab (document.te_i18n, te_site_* )
$_['te_site_lang_from'] = 'Translate from';
$_['te_site_lang_to'] = 'to';
$_['te_site_lang_swap'] = 'Swap the languages';
$_['te_site_card_what'] = 'What to translate';
$_['te_site_more_filters'] = 'More filters';
$_['te_site_stop'] = 'Stop';
$_['te_site_log'] = 'Log';
$_['te_site_analyzing'] = 'Analysis in progress';
$_['te_site_translating'] = 'Translation: {table}';
$_['te_site_col_table'] = 'Table / column';
$_['te_site_col_chars'] = 'Characters';
$_['te_site_col_chars_lang'] = 'Characters ({code})';
$_['te_site_col_actions'] = 'Actions';
$_['te_site_total'] = 'Total';
$_['te_site_translate'] = 'Translate';
$_['te_site_translate_all'] = 'Translate all';
$_['te_site_details'] = 'Details';
$_['te_site_not_supported'] = 'not supported';
$_['te_site_done'] = 'done';
$_['te_site_toggle'] = 'Show or hide the columns of {table}';
$_['te_site_cnt_current'] = 'Now: {current}';
$_['te_site_cnt_table'] = 'Table {n} of {total}';
$_['te_site_cnt_request'] = 'Request {request}';
$_['te_site_cnt_chars'] = 'Characters found: {chars}';
$_['te_site_cnt_time'] = 'Time: {sec} s';
$_['te_site_cnt_translated'] = 'Translated characters: {chars}';
$_['te_site_translate_confirm'] = 'Are you sure you want to translate {table} ({lang})?';
$_['te_site_translate_all_confirm'] = 'Are you sure you want to translate all tables in the list ({lang})?';
$_['te_site_log_table_done'] = 'Done: {table}, translated characters: {chars}';
$_['te_site_detail_loading'] = 'Preparing detail info ...';
$_['te_site_detail_source'] = 'Source ({code})';
$_['te_site_detail_target'] = 'Target ({code})';
$_['te_site_empty'] = 'empty';
$_['te_site_identical'] = 'identical';
$_['te_site_detail_none'] = 'No values.';
$_['te_site_detail_more'] = 'Only the first 100 values show.';
$_['te_site_state_done'] = 'Done';
$_['te_site_state_stopped'] = 'Stopped';
$_['te_site_state_error'] = 'Error';
$_['te_loc_card_files'] = 'Language files';
$_['te_loc_analyze'] = 'Analyze files';
$_['te_loc_translate_all'] = 'Translate all';
$_['te_loc_analyzing'] = 'File analysis in progress';
$_['te_loc_translating'] = 'Translation: {path}';
$_['te_loc_cnt_files'] = 'Files {n} of {total}';
$_['te_loc_totals_files'] = 'Files: {count}';
$_['te_loc_totals_to_translate'] = 'Files to translate: {count}';
$_['te_loc_totals_values'] = 'Values to translate: {count}';
$_['te_loc_totals_chars'] = 'Characters to translate: {count}';
$_['te_loc_col_file'] = 'File';
$_['te_loc_col_keys'] = 'Keys to translate';
$_['te_loc_col_state'] = 'State';
$_['te_loc_state_missing'] = 'missing';
$_['te_loc_state_partial'] = 'partial';
$_['te_loc_state_saved'] = 'saved';
$_['te_loc_toggle'] = 'Show or hide the keys of {path}';
$_['te_loc_root'] = 'Extension: {name}';
$_['te_loc_expression'] = 'PHP code, copied unchanged';
$_['te_loc_source_file'] = 'Source file';
$_['te_loc_translate_file'] = 'Translate file';
$_['te_loc_save_file'] = 'Save file';
$_['te_loc_saved'] = 'Saved: {path}';
$_['te_loc_nothing_to_save'] = 'Nothing to save: {path}';
$_['te_loc_no_files'] = 'All files are translated.';
$_['te_loc_translate_all_confirm'] = 'Are you sure you want to translate and save all files in the list ({lang})?';
