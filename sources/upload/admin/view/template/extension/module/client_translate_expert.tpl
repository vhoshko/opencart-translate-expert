<?php echo $header; ?><?php if (isset($column_left)) echo $column_left; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <button type="submit" form="form-bestseller" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i><?php if (version_compare(VERSION, '2.0', '<')) { echo $button_save; } ?></button>
        <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i><?php if (version_compare(VERSION, '2.0', '<')) { echo $button_cancel; } ?></a></div>
      <h1><?php echo $heading_title; ?></h1>
      <ul class="breadcrumb">
        <?php foreach ($breadcrumbs as $breadcrumb) { ?>
        <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
        <?php } ?>
      </ul>
    </div>
  </div>
  <div class="container-fluid">
    <?php if ($error_warning) { ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
    <div class="te-app" id="te-app">
      <div class="te-alerts" id="te-summary-alerts"></div>
      <div class="te-summary" id="te-summary">
        <span class="te-summary__item"><?php echo $te_summary_status; ?>: <span class="te-badge <?php echo $te_summary_status_on ? 'te-badge--ok' : 'te-badge--muted'; ?>"><?php echo $te_summary_status_on ? $te_summary_enabled : $te_summary_disabled; ?></span></span>
        <span class="te-summary__item"><?php echo $te_summary_api_key; ?>: <span class="te-badge <?php echo $te_summary_key_set_on ? 'te-badge--ok' : 'te-badge--error'; ?>"><?php echo $te_summary_key_set_on ? $te_summary_key_set : $te_summary_key_not_set; ?></span></span>
        <div class="te-summary__meter" id="te-summary-count"></div>
      </div>
    </div>
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_edit; ?></h3>
      </div>
      <div class="panel-body">
        <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-bestseller" class="form-horizontal">
          <ul class="nav nav-tabs">
            <li class="<?php echo $tab_general_class; ?>"><a href="#tab-general" data-toggle="tab"><?php echo $tab_general; ?></a></li>
            <li><a href="#tab-translate-site" data-toggle="tab"><?php echo $tab_translate_site; ?></a></li>
            <li><a href="#tab-localization" data-toggle="tab"><?php echo $tab_localization; ?></a></li>
            <li><a href="#tab-admin-localization" data-toggle="tab"><?php echo $tab_admin_localization; ?></a></li>
            <li class="<?php echo $tab_debug_class; ?>"><a href="#tab-debug" data-toggle="tab"><?php echo $tab_debug; ?></a></li>
            <li><a href="#tab-about" data-toggle="tab"><?php echo $tab_about; ?></a></li>
          </ul>

          <div class="te-app te-langbar-wrap" id="te-langbar-app">
            <div class="te-langbar te-hidden">
              <label for="te-langbar-from"><?php echo $te_site_lang_from; ?></label>
              <select id="te-langbar-from" class="form-control te-langbar__select">
                <?php foreach ($languages as $language) { ?>
                <option value="<?php echo $language['language_id']; ?>" <?php if ($te_langIdFrom == $language['language_id']) { echo 'selected="selected"'; } ?>><?php echo $language['name']; ?></option>
                <?php } ?>
              </select>
              <button type="button" class="btn btn-default te-js-langbar-swap" title="<?php echo htmlspecialchars($te_site_lang_swap, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($te_site_lang_swap, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-exchange" aria-hidden="true"></i></button>
              <label for="te-langbar-to"><?php echo $te_site_lang_to; ?></label>
              <select id="te-langbar-to" class="form-control te-langbar__select">
                <?php $te_langIndex = 0; foreach ($languages as $language) { ?>
                <option value="<?php echo $language['language_id']; ?>" <?php if (empty($te_langIdTo) ? $te_langIndex == 1 : $te_langIdTo == $language['language_id']) { echo 'selected="selected"'; } ?>><?php echo $language['name']; ?></option>
                <?php $te_langIndex++; } ?>
              </select>
            </div>
          </div>

          <div class="tab-content">
				<div class="tab-pane <?php echo $tab_general_class; ?>" id="tab-general">
				  <div class="te-app" id="te-settings-app">
				    <div class="te-card">
				      <div class="te-card__head"><?php echo $te_settings_card_connection; ?></div>
				      <div class="te-card__body">
				        <div class="te-form">
				          <div class="te-field">
				            <label class="te-field__label" for="input-client_translate_expert_status"><?php echo $entry_status; ?></label>
				            <div class="te-field__control"><select name="client_translate_expert_status" id="input-client_translate_expert_status" class="form-control">
				            <?php if ($client_translate_expert_status) { ?>
				                                  <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
				                                  <option value="0"><?php echo $text_disabled; ?></option>
				                                  <?php } else { ?>
				                                  <option value="1"><?php echo $text_enabled; ?></option>
				                                  <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
				                                  <?php } ?>
				          </select></div>
				          </div>
				          <div class="te-field">
				            <label class="te-field__label" for="input-client_translate_expert_key"><?php echo $entry_server_key; ?></label>
				            <div class="te-field__control"><style>.te-key-masked { -webkit-text-security: disc; }</style>
				          <div class="input-group">
				            <input type="text" autocomplete="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other" name="client_translate_expert_key" value="<?php echo $client_translate_expert_key; ?>" placeholder="<?php echo $entry_server_key; ?>" id="input-client_translate_expert_key" class="form-control te-key-masked" />
				            <span class="input-group-btn"><button type="button" id="button-client_translate_expert_key_toggle" class="btn btn-default" data-text-show="<?php echo $button_show_key; ?>" data-text-hide="<?php echo $button_hide_key; ?>"><?php echo $button_show_key; ?></button></span>
				          </div></div>
				          </div>
				        </div>
				      </div>
				    </div>
				    <div class="te-card">
				      <div class="te-card__head"><?php echo $te_settings_card_usage; ?></div>
				      <div class="te-card__body">
				        <div class="te-form">
				          <div class="te-field">
				            <span class="te-field__label"><?php echo $entry_server_translation_char_count; ?></span>
				            <div class="te-field__control"><div id="input-client_translate_expert_char_count"><?php echo $client_translate_expert_char_count ? $client_translate_expert_char_count : 0; ?></div></div>
				          </div>
				          <div class="te-field">
				            <span class="te-field__label"></span>
				            <div class="te-field__control"><div class="checkbox"><label for="input-client_translate_expert_show_char_count_globally"><input type="checkbox" name="client_translate_expert_show_char_count_globally" value="1" id="input-client_translate_expert_show_char_count_globally" <?php if ($client_translate_expert_show_char_count_globally == 1) { ?>checked="checked"<?php } ?>/> <?php echo $entry_server_show_translation_char_count_globally; ?></label></div></div>
				          </div>
				          <div class="te-field">
				            <span class="te-field__label"></span>
				            <div class="te-field__control"><div class="checkbox"><label for="input-client_translate_expert_reset_char_count_on_start_month"><input type="checkbox" name="client_translate_expert_reset_char_count_on_start_month" value="1" id="input-client_translate_expert_reset_char_count_on_start_month" <?php if ($client_translate_expert_reset_char_count_on_start_month == 1) { ?>checked="checked"<?php } ?>/> <?php echo $entry_server_reset_translation_char_count_on_start_month; ?></label></div></div>
				          </div>
				        </div>
				      </div>
				    </div>
				    <div class="te-card">
				      <div class="te-card__head"><?php echo $te_settings_card_cron; ?></div>
				      <div class="te-card__body">
				        <div class="te-form">
				          <div class="te-field">
				            <label class="te-field__label" for="te-cron-bulk-amount"><?php echo $entry_cron_bulk_amount; ?></label>
				            <div class="te-field__control"><input type="number" min="1" max="100000" step="1" name="client_translate_expert_cron_bulk_amount" value="<?php echo (int)$client_translate_expert_cron_bulk_amount; ?>" id="te-cron-bulk-amount" class="form-control" /></div>
				            <div class="te-field__help"><?php echo $help_cron_bulk_amount; ?></div>
				          </div>
				          <div class="te-field">
				            <label class="te-field__label" for="te-cron-secret"><?php echo $entry_cron_secret; ?></label>
				            <div class="te-field__control"><div class="input-group">
				            <input type="text" autocomplete="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other" maxlength="128" name="client_translate_expert_cron_secret" value="<?php echo htmlspecialchars($client_translate_expert_cron_secret, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo $entry_cron_secret; ?>" id="te-cron-secret" class="form-control" />
				            <span class="input-group-btn"><button type="button" id="te-cron-secret-generate" class="btn btn-default"><?php echo $button_cron_secret_generate; ?></button></span>
				          </div>
				          <div class="text-warning" id="te-cron-secret-unsaved" style="display:none"><?php echo $text_cron_secret_unsaved; ?></div></div>
				            <div class="te-field__help"><?php echo $help_cron_secret; ?></div>
				          </div>
				        </div>
				      </div>
				    </div>
				    <div class="te-card">
				      <div class="te-card__head"><?php echo $te_settings_card_routes; ?></div>
				      <div class="te-card__body">
				        <div class="te-form">
				          <div class="te-field">
				            <label class="te-field__label" for="input-client_translate_expert_excluded_routes"><?php echo $entry_excluded_routes; ?></label>
				            <div class="te-field__control"><textarea name="client_translate_expert_excluded_routes" rows="5" id="input-client_translate_expert_excluded_routes" class="form-control"><?php echo $client_translate_expert_excluded_routes; ?></textarea></div>
				            <div class="te-field__help"><?php echo $help_excluded_routes; ?></div>
				          </div>
				        </div>
				      </div>
				    </div>
				    <div class="te-card">
				      <div class="te-card__head">
				        <button type="button" class="te-collapse-toggle te-js-collapse" data-te-target="#te-try-panel" aria-expanded="false" aria-controls="te-try-panel"><?php echo $te_settings_card_try; ?></button>
				      </div>
				      <div class="te-card__body te-hidden" id="te-try-panel">
				        <?php foreach ($languages as $language) { ?>
				        <div class="input-group" style="margin-bottom: 8px;">
				          <span class="input-group-addon"><img src="<?php echo $language['image_url']; ?>" title="<?php echo $language['name']; ?>" /></span>
				          <textarea name="test[<?php echo $language['language_id']; ?>][translation]" rows="7" placeholder="<?php echo $entry_try_translate_text; ?>" class="form-control"></textarea>
				        </div>
				        <?php } ?>
				      </div>
				    </div>
				  </div>
				</div>
				<div class="tab-pane" id="tab-translate-site">
				  <?php $te_mode_value = in_array($te_mode, array('only_empty', 'same_value'), true) ? $te_mode : 'both'; ?>
				  <div class="te-app" id="te-site-app">
					<div class="te-alerts" id="te-site-alerts"></div>
					<div class="te-card">
					  <div class="te-card__head"><?php echo $te_site_card_what; ?></div>
					  <div class="te-card__body">
						<div class="te-form">
						  <div class="te-field">
							<span class="te-field__label"><?php echo $entry_analization_mode; ?></span>
							<div class="te-field__control te-radio-group">
							  <label class="radio-inline"><input type="radio" name="te-analization-mode" form="te-no-form" value="both" <?php if ($te_mode_value == 'both') { echo 'checked="checked"'; } ?>> <?php echo $value_analization_mode_both; ?></label>
							  <label class="radio-inline"><input type="radio" name="te-analization-mode" form="te-no-form" value="only_empty" <?php if ($te_mode_value == 'only_empty') { echo 'checked="checked"'; } ?>> <?php echo $value_analization_mode_only_empty; ?></label>
							  <label class="radio-inline"><input type="radio" name="te-analization-mode" form="te-no-form" value="same_value" <?php if ($te_mode_value == 'same_value') { echo 'checked="checked"'; } ?>> <?php echo $value_analization_mode_same_value; ?></label>
							</div>
						  </div>
						  <div class="te-field">
							<label class="te-field__label" for="te-analization-table"><?php echo $entry_table; ?></label>
							<div class="te-field__control">
							  <div style="margin-bottom: 5px;"><button type="button" class="btn btn-default btn-xs te-multi-select-all" data-te-target="#te-analization-table" data-te-caption-first="<?php echo htmlspecialchars($button_select_first, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $button_select_all; ?></button> <button type="button" class="btn btn-default btn-xs te-multi-select-clear" data-te-target="#te-analization-table"><?php echo $button_clear_selection; ?></button></div>
							  <select id="te-analization-table" data-te-max="<?php echo (int)$te_multi_select_max; ?>" multiple="multiple" size="6" class="form-control">
								<?php foreach ($tables as $table) { ?>
								<option value="<?php echo $table; ?>" <?php if (in_array((string)$table, $te_table)) { echo 'selected="selected"'; } ?>><?php echo $table; ?></option>
								<?php } ?>
							  </select>
							  <span class="help-block"><?php echo $help_multi_select; ?></span><div class="text-muted te-multi-select-limit" style="display:none; margin: 5px 0 10px;"><?php echo $help_multi_select_limit; ?></div>
							</div>
						  </div>
						  <div class="te-field">
							<label class="te-field__label" for="te-analization-table-deep"><?php echo $entry_table_deep; ?></label>
							<div class="te-field__control">
							  <div class="checkbox" style="margin-top: 0;"><label><input type="checkbox" id="te-analization-table-deep" value="1" checked="checked"> <?php echo $entry_table_deep_description; ?></label></div>
							</div>
						  </div>
						  <div class="te-field">
							<span class="te-field__label"></span>
							<div class="te-field__control">
							  <button type="button" class="te-collapse-toggle te-js-collapse" data-te-target="#te-site-more-filters" aria-expanded="false" aria-controls="te-site-more-filters"><?php echo $te_site_more_filters; ?></button>
							</div>
						  </div>
						  <div class="te-form te-hidden" id="te-site-more-filters">
							<div class="te-field">
							  <span class="te-field__label"><?php echo $entry_table_product_filter; ?></span>
							  <div class="te-field__control">
								<div class="checkbox" style="margin-top: 0;"><label><input type="checkbox" id="te-analization-product-status-filter" value="1" <?php if ($te_product_status) { echo 'checked="checked"'; } ?>> <?php echo $entry_table_product_status_filter; ?></label></div>
								<div class="checkbox"><label><input type="checkbox" id="te-analization-product-quantity-filter" value="1" <?php if ($te_product_quantity) { echo 'checked="checked"'; } ?>> <?php echo $entry_table_product_quantity_filter; ?></label></div>
							  </div>
							</div>
							<div class="te-field">
							  <label class="te-field__label" for="te-analization-product-category-id-filter"><?php echo $entry_table_product_category_id_filter; ?></label>
							  <div class="te-field__control">
								<div style="margin-bottom: 5px;"><button type="button" class="btn btn-default btn-xs te-multi-select-all" data-te-target="#te-analization-product-category-id-filter" data-te-caption-first="<?php echo htmlspecialchars($button_select_first, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $button_select_all; ?></button> <button type="button" class="btn btn-default btn-xs te-multi-select-clear" data-te-target="#te-analization-product-category-id-filter"><?php echo $button_clear_selection; ?></button></div>
								<select id="te-analization-product-category-id-filter" data-te-max="<?php echo (int)$te_multi_select_max; ?>" multiple="multiple" size="6" class="form-control">
								  <?php foreach($categories as $category) { ?>
								  <?php if(in_array((string)$category['category_id'], $te_product_category_id)) { ?>
								  <option value="<?php echo $category['category_id']; ?>" selected="selected"><?php echo $category['name']; ?></option>
								  <?php } else { ?>
								  <option value="<?php echo $category['category_id']; ?>"><?php echo $category['name']; ?></option>
								  <?php } ?>
								  <?php } ?>
								</select>
								<span class="help-block"><?php echo $help_multi_select; ?></span><div class="text-muted te-multi-select-limit" style="display:none; margin: 5px 0 10px;"><?php echo $help_multi_select_limit; ?></div>
							  </div>
							</div>
							<div class="te-field">
							  <label class="te-field__label" for="te-analization-product-stock-status-filter"><?php echo $entry_table_product_stock_status_filter; ?></label>
							  <div class="te-field__control">
								<div style="margin-bottom: 5px;"><button type="button" class="btn btn-default btn-xs te-multi-select-all" data-te-target="#te-analization-product-stock-status-filter" data-te-caption-first="<?php echo htmlspecialchars($button_select_first, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $button_select_all; ?></button> <button type="button" class="btn btn-default btn-xs te-multi-select-clear" data-te-target="#te-analization-product-stock-status-filter"><?php echo $button_clear_selection; ?></button></div>
								<select id="te-analization-product-stock-status-filter" data-te-max="<?php echo (int)$te_multi_select_max; ?>" multiple="multiple" size="6" class="form-control">
								  <?php foreach ($stock_statuses as $stock_status) { ?>
								  <option value="<?php echo $stock_status['stock_status_id']; ?>" <?php if (in_array((string)$stock_status['stock_status_id'], $te_stock_status)) { echo 'selected="selected"'; } ?>><?php echo $stock_status['name']; ?></option>
								  <?php } ?>
								</select>
								<span class="help-block"><?php echo $help_multi_select; ?></span><div class="text-muted te-multi-select-limit" style="display:none; margin: 5px 0 10px;"><?php echo $help_multi_select_limit; ?></div>
							  </div>
							</div>
							<div class="te-field">
							  <label class="te-field__label" for="te-analization-product-manufacturer-filter"><?php echo $entry_table_product_manufacturer_filter; ?></label>
							  <div class="te-field__control">
								<div style="margin-bottom: 5px;"><button type="button" class="btn btn-default btn-xs te-multi-select-all" data-te-target="#te-analization-product-manufacturer-filter" data-te-caption-first="<?php echo htmlspecialchars($button_select_first, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $button_select_all; ?></button> <button type="button" class="btn btn-default btn-xs te-multi-select-clear" data-te-target="#te-analization-product-manufacturer-filter"><?php echo $button_clear_selection; ?></button></div>
								<select id="te-analization-product-manufacturer-filter" data-te-max="<?php echo (int)$te_multi_select_max; ?>" multiple="multiple" size="6" class="form-control">
								  <?php foreach ($manufacturers as $manufacturer) { ?>
								  <option value="<?php echo $manufacturer['manufacturer_id']; ?>" <?php if (in_array((string)$manufacturer['manufacturer_id'], $te_manufacturer_id)) { echo 'selected="selected"'; } ?>><?php echo $manufacturer['name']; ?></option>
								  <?php } ?>
								</select>
								<span class="help-block"><?php echo $help_multi_select; ?></span><div class="text-muted te-multi-select-limit" style="display:none; margin: 5px 0 10px;"><?php echo $help_multi_select_limit; ?></div>
							  </div>
							</div>
						  </div>
						</div>
						<div class="te-toolbar">
						  <button type="button" id="te-begin-analization" class="btn btn-primary" title="<?php echo htmlspecialchars($entry_analization, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $entity_begin_analization; ?></button>
						  <span class="te-toolbar__spacer"></span>
						  <button type="button" id="te-begin-removing_data_from_db" class="btn btn-danger" data-te-label="<?php echo htmlspecialchars($entity_begin_removing_data_from_db, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($entry_remove_translation_records_from_db, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $entity_begin_removing_data_from_db; ?></button>
						</div>
					  </div>
					</div>
					<div class="te-card">
					  <div class="te-card__head">
						<button type="button" class="te-collapse-toggle te-js-collapse" data-te-target="#te-cron-link-panel" aria-expanded="false" aria-controls="te-cron-link-panel"><?php echo $entry_cron_link; ?></button>
					  </div>
					  <div class="te-card__body te-hidden" id="te-cron-link-panel" data-cron-url="<?php echo htmlspecialchars($te_cron_url, ENT_QUOTES, 'UTF-8'); ?>" data-te-max-length="<?php echo (int)$te_cron_link_max_length; ?>">
						<input type="text" readonly="readonly" id="te-cron-link" class="form-control" value="" <?php if (!$te_cron_secret_valid) { echo 'style="display:none"'; } ?> />
						<span class="help-block" <?php if (!$te_cron_secret_valid) { echo 'style="display:none"'; } ?> id="te-cron-link-help"><?php echo $help_cron_link; ?></span>
						<div class="text-danger" id="te-cron-link-error" <?php if ($te_cron_secret_valid) { echo 'style="display:none"'; } ?>><?php echo $error_cron_secret; ?></div>
						<div class="text-warning" id="te-cron-link-long" style="display:none"><?php echo $warning_cron_link_long; ?></div>
					  </div>
					</div>
					<div class="te-progress te-hidden" id="te-site-progress" role="status" aria-live="polite"></div>
					<div id="te-analization-result"></div>
				  </div>
				</div>


				<div class="tab-pane" id="tab-localization">
				  <div class="te-app te-loc-app" id="te-loc-app-catalog" data-scope="catalog">
				    <div class="te-alerts" id="te-loc-alerts-catalog"></div>
				    <div class="te-card">
				      <div class="te-card__head"><?php echo $te_loc_card_files; ?></div>
				      <div class="te-card__body">
				        <div class="te-loc-totals te-hidden" id="te-loc-totals-catalog"></div>
				        <div class="te-toolbar">
				          <button type="button" id="te-begin-localization-analization" class="btn btn-primary te-js-loc-analyze"><?php echo $te_loc_analyze; ?></button>
				        </div>
				      </div>
				    </div>
				    <div class="te-progress te-hidden" id="te-loc-progress-catalog" role="status" aria-live="polite"></div>
				    <div class="te-loc-result" id="te-loc-result-catalog"></div>
				  </div>
				</div>


				<div class="tab-pane" id="tab-admin-localization">
				  <div class="te-app te-loc-app" id="te-loc-app-admin" data-scope="admin">
				    <div class="te-alerts" id="te-loc-alerts-admin"></div>
				    <div class="te-card">
				      <div class="te-card__head"><?php echo $te_loc_card_files; ?></div>
				      <div class="te-card__body">
				        <div class="te-loc-totals te-hidden" id="te-loc-totals-admin"></div>
				        <div class="te-toolbar">
				          <button type="button" id="te-begin-admin-localization-analization" class="btn btn-primary te-js-loc-analyze"><?php echo $te_loc_analyze; ?></button>
				        </div>
				      </div>
				    </div>
				    <div class="te-progress te-hidden" id="te-loc-progress-admin" role="status" aria-live="polite"></div>
				    <div class="te-loc-result" id="te-loc-result-admin"></div>
				  </div>
				</div>


				<div class="tab-pane <?php echo $tab_debug_class; ?>" id="tab-debug">
				  <div class="te-app" id="te-debug-app">
				    <div class="te-card">
				      <div class="te-card__head"><?php echo $te_debug_card_versions; ?></div>
				      <div class="te-card__body">
				        <div class="te-form">
				          <div class="te-field">
				            <span class="te-field__label"><?php echo $entry_translate_expert_version; ?></span>
				            <div class="te-field__control te-plain-value"><?php echo $translate_expert_version; ?></div>
				          </div>
				          <div class="te-field">
				            <span class="te-field__label"><?php echo $entry_opencart_version; ?></span>
				            <div class="te-field__control te-plain-value"><?php echo $opencart_version; ?></div>
				          </div>
				          <div class="te-field">
				            <span class="te-field__label"><?php echo $entry_php_version; ?></span>
				            <div class="te-field__control te-plain-value"><?php echo $php_version; ?></div>
				          </div>
				        </div>
				      </div>
				    </div>
				    <div class="te-card">
				      <div class="te-card__head"><?php echo $te_debug_card_cache; ?></div>
				      <div class="te-card__body">
				        <div class="te-alerts" id="te-debug-alerts"></div>
				        <div class="te-form">
				          <div class="te-field">
				            <span class="te-field__label"><?php echo $entry_reset_translation_cache; ?></span>
				            <div class="te-field__control"><button type="button" class="btn btn-primary" id="reset_translation_cache" title="<?php echo $entry_reset_translation_cache; ?>" onclick="resetTranslationCache()"><?php echo $entry_reset_translation_cache; ?></button></div>
				          </div>
				        </div>
				      </div>
				    </div>
				    <div class="te-card">
				      <div class="te-card__head"><span><i class="fa fa-exclamation-triangle"></i> <?php echo $text_debug_log; ?></span></div>
				      <div class="te-card__body">
				        <div class="te-form">
				          <div class="te-field">
				            <span class="te-field__label"><?php echo $entry_debug_status; ?></span>
				            <div class="te-field__control te-radio-group"><label class="radio-inline"><input type="radio" name="client_translate_expert_debug_status" value="1" id="debug-status-enabled" autocomplete="off" <?php if ($client_translate_expert_debug_status) { ?>checked="checked"<?php } ?>> <?php echo $text_enabled; ?></label>
				          <label class="radio-inline"><input type="radio" name="client_translate_expert_debug_status" value="0" id="debug-status-disabled" autocomplete="off" <?php if (!$client_translate_expert_debug_status) { ?>checked="checked"<?php } ?>> <?php echo $text_disabled; ?></label></div>
				          </div>
				        </div>
				        <div class="te-toolbar">
				          <?php if (isset($debug_log)) { ?>
				            <a href="<?php echo $download_href; ?>" data-toggle="tooltip" title="<?php echo $button_download; ?>" class="btn btn-primary"><i class="fa fa-download"></i></a>
				            <a onclick="confirm('<?php echo $text_clear_confirm; ?>') ? location.href='<?php echo $clear_href; ?>' : false;" data-toggle="tooltip" title="<?php echo $button_clear; ?>" class="btn btn-danger"><i class="fa fa-eraser"></i></a>
				          <?php } else { ?>
				            <a class="btn btn-info" href="<?php echo $view_href; ?>" data-toggle="tooltip" title="<?php echo $button_view; ?>"><i class="fa fa-eye"></i> <?php echo $button_view; ?></a>
				          <?php } ?>
				        </div>
				        <?php if (isset($debug_log)) { ?>
				          <?php if (isset($error_debug_log_warning_frm) && $error_debug_log_warning_frm) { ?>
				            <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_debug_log_warning_frm; ?>
				              <button type="button" class="close" data-dismiss="alert">&times;</button>
				            </div>
				          <?php } ?>
				          <textarea wrap="off" rows="30" readonly class="form-control"><?php echo $debug_log; ?></textarea>
				        <?php } ?>
				      </div>
				    </div>
				  </div>
				</div>
				<div class="tab-pane" id="tab-about">
				  <div class="te-app" id="te-about-app">
				    <div class="te-card">
				      <div class="te-card__head"><?php echo $te_about_card; ?></div>
				      <div class="te-card__body">
				        <?php echo $text_about_page; ?>
				      </div>
				    </div>
				  </div>
				</div>
			</div>
        </form>
      </div>
    </div>
  </div>  
</div>
<!-- Modal -->
<div class="modal fade" id="translateExpertModal" tabindex="-1" role="dialog" aria-labelledby="translateExpertModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false" >
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="translateExpertModalLabel"><?php echo $heading_title_text; ?> - <?php echo $tab_translate_site; ?></h5>
        <button type="button" class="btn btn-secondary stop_modal_process" onclick="return confirmStopTranslationModelProcess('<?php echo $entry_stop_modal_process_confirmation; ?>')"><?php echo $entry_stop_modal_process; ?></button>
      </div>
      <div class="modal-body">
        ...
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary close_modal" data-dismiss="modal" data-bs-dismiss="modal" onclick="hideTranslateExpertModal();"><?php echo $entry_close; ?></button>
      </div>
    </div>
  </div>
</div>
<!-- js constants -->
<script>
	// removing
	document.js_const_entry_remove_translation_records_from_db_confirmation = "<?php echo $entry_remove_translation_records_from_db_confirmation; ?>";
	document.js_const_entry_removing_data_from_db_process_is_started = "<?php echo $entry_removing_data_from_db_process_is_started; ?>";
	// Front-end strings of the new admin design (read with teT() in the JS). The controller makes the JSON.
	document.te_i18n = <?php echo $te_i18n_json; ?>;
	
	// The key input is a text input masked with CSS, so password managers do not suggest or save a password for it.
	// If the browser does not support -webkit-text-security, fall back to a password input.
	(function() {
		var input = document.getElementById('input-client_translate_expert_key');
		var cssMask = !!(window.CSS && CSS.supports && CSS.supports('-webkit-text-security', 'disc'));
		if (!cssMask) {
			input.type = 'password';
		}
		$('#button-client_translate_expert_key_toggle').on('click', function() {
			var show = cssMask ? $(input).hasClass('te-key-masked') : input.type === 'password';
			if (cssMask) {
				$(input).toggleClass('te-key-masked', !show);
			} else {
				input.type = show ? 'text' : 'password';
			}
			$(this).text($(this).data(show ? 'text-hide' : 'text-show'));
		});
	})();

</script>
<?php echo $footer; ?>