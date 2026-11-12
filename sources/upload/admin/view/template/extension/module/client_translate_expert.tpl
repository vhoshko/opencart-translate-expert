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
            <li class="<?php echo $tab_debug_class; ?>"><a href="#tab-debug" data-toggle="tab"><?php echo $tab_debug; ?></a></li>
            <li><a href="#tab-about" data-toggle="tab"><?php echo $tab_about; ?></a></li>
          </ul>

          <div class="tab-content">
            <div class="tab-pane <?php echo $tab_general_class; ?>" id="tab-general">
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="input-client_translate_expert_status"><?php echo $entry_status; ?></label>
					<div class="col-sm-10">
					  <select name="client_translate_expert_status" id="input-client_translate_expert_status" class="form-control">
						<?php if ($client_translate_expert_status) { ?>
						<option value="1" selected="selected"><?php echo $text_enabled; ?></option>
						<option value="0"><?php echo $text_disabled; ?></option>
						<?php } else { ?>
						<option value="1"><?php echo $text_enabled; ?></option>
						<option value="0" selected="selected"><?php echo $text_disabled; ?></option>
						<?php } ?>
					  </select>
					</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="input-client_translate_expert_key"><?php echo $entry_server_key; ?></label>
					<div class="col-sm-10">
					  <style>.te-key-masked { -webkit-text-security: disc; }</style>
					  <div class="input-group">
						<input type="text" autocomplete="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other" name="client_translate_expert_key" value="<?php echo $client_translate_expert_key; ?>" placeholder="<?php echo $entry_server_key; ?>" id="input-client_translate_expert_key" class="form-control te-key-masked" />
						<span class="input-group-btn"><button type="button" id="button-client_translate_expert_key_toggle" class="btn btn-default" data-text-show="<?php echo $button_show_key; ?>" data-text-hide="<?php echo $button_hide_key; ?>"><?php echo $button_show_key; ?></button></span>
					  </div>
					  <?php if ($error_name) { ?>
					  <div class="text-danger"><?php echo $error_name; ?></div>
					  <?php } ?>
					</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="te-cron-bulk-amount"><?php echo $entry_cron_bulk_amount; ?></label>
					<div class="col-sm-10">
					  <input type="number" min="1" max="100000" step="1" name="client_translate_expert_cron_bulk_amount" value="<?php echo (int)$client_translate_expert_cron_bulk_amount; ?>" id="te-cron-bulk-amount" class="form-control" />
					  <span class="help-block"><?php echo $help_cron_bulk_amount; ?></span>
					</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="te-cron-secret"><?php echo $entry_cron_secret; ?></label>
					<div class="col-sm-10">
					  <div class="input-group">
						<input type="text" autocomplete="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other" maxlength="128" name="client_translate_expert_cron_secret" value="<?php echo htmlspecialchars($client_translate_expert_cron_secret, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo $entry_cron_secret; ?>" id="te-cron-secret" class="form-control" />
						<span class="input-group-btn"><button type="button" id="te-cron-secret-generate" class="btn btn-default"><?php echo $button_cron_secret_generate; ?></button></span>
					  </div>
					  <span class="help-block"><?php echo $help_cron_secret; ?></span>
					  <div class="text-warning" id="te-cron-secret-unsaved" style="display:none"><?php echo $text_cron_secret_unsaved; ?></div>
					</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="input-client_translate_expert_show_char_count_globally"><?php echo $entry_server_show_translation_char_count_globally; ?></label>
					<div class="col-sm-10">
					  <input type="checkbox" name="client_translate_expert_show_char_count_globally" value="1" id="input-client_translate_expert_show_char_count_globally" <?php echo $client_translate_expert_show_char_count_globally == 1 ? 'checked="1"' : ''; ?>/> <?php echo $entry_server_show_translation_char_count_globally; ?>
					</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="input-client_translate_expert_reset_char_count_on_start_month"><?php echo $entry_server_reset_translation_char_count_on_start_month; ?></label>
					<div class="col-sm-10">
					  <input type="checkbox" name="client_translate_expert_reset_char_count_on_start_month" value="1" id="input-client_translate_expert_reset_char_count_on_start_month" <?php echo $client_translate_expert_reset_char_count_on_start_month == 1 ? 'checked="1"' : ''; ?> /> <?php echo $entry_server_reset_translation_char_count_on_start_month; ?>
					</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="input-client_translate_expert_char_count"><?php echo $entry_server_translation_char_count; ?></label>
					<div class="col-sm-10">
					  <div id="input-client_translate_expert_char_count">
						<?php echo $client_translate_expert_char_count ? $client_translate_expert_char_count : 0; ?>
					  </div>
					</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="input-client_translate_expert_excluded_routes"><?php echo $entry_excluded_routes; ?></label>
					<div class="col-sm-10">
					  <textarea name="client_translate_expert_excluded_routes" rows="5" id="input-client_translate_expert_excluded_routes" class="form-control"><?php echo $client_translate_expert_excluded_routes; ?></textarea>
					  <span class="help-block"><?php echo $help_excluded_routes; ?></span>
					</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label"><?php echo $entry_try_translate; ?></label>
					<div class="col-sm-10">
						<?php foreach ($languages as $language) { ?>
                        <div class="input-group">
							<span class="input-group-addon"><img src="<?php echo $language['image_url']; ?>" title="<?php echo $language['name']; ?>" /></span>
							<textarea name="test[<?php echo $language['language_id']; ?>][translation]" rows="7" placeholder="<?php echo $entry_try_translate_text; ?>" class="form-control"></textarea>
                        </div>
                        <?php } ?>
						</div>
				  </div>
				</div>
				<div class="tab-pane" id="tab-translate-site">
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="te-analization-mode"><?php echo $entry_analization_mode; ?></label>
					<div class="col-sm-10">
						<select id="te-analization-mode" >
							<option value="both" <?php if ($te_mode == 'both') { echo 'selected="selected"'; } ?>><?php echo $value_analization_mode_both; ?></option>
							<option value="only_empty" <?php if ($te_mode == 'only_empty') { echo 'selected="selected"'; } ?>><?php echo $value_analization_mode_only_empty; ?></option>
							<option value="same_value" <?php if ($te_mode == 'same_value') { echo 'selected="selected"'; } ?>><?php echo $value_analization_mode_same_value; ?></option>
						</select>
					</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="te-analization-table"><?php echo $entry_table; ?></label>
					<div class="col-sm-10">
						<select id="te-analization-table" multiple="multiple" size="6" class="form-control">
							<?php foreach ($tables as $table) { ?>
							<option value="<?php echo $table; ?>" <?php if (in_array((string)$table, $te_table)) { echo 'selected="selected"'; } ?>><?php echo $table; ?></option>
							<?php } ?>
						</select>
						<div style="margin-top: 5px;"><button type="button" class="btn btn-default btn-xs te-multi-select-all" data-te-target="#te-analization-table"><?php echo $button_select_all; ?></button> <button type="button" class="btn btn-default btn-xs te-multi-select-clear" data-te-target="#te-analization-table"><?php echo $button_clear_selection; ?></button></div>
						<span class="help-block"><?php echo $help_multi_select; ?></span>
					</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="te-analization-language-from"><?php echo $entry_analization_language_from; ?></label>
					<div class="col-sm-10">
						<select id="te-analization-language-from">
							<?php foreach ($languages as $language) { ?>
							<option value="<?php echo $language['language_id']; ?>" <?php if ($te_langIdFrom == $language['language_id']) { echo 'selected="selected"'; } ?>><?php echo $language['name']; ?></option>
							<?php } ?>
						</select>
					</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="te-analization-language-to"><?php echo $entry_analization_language_to; ?></label>
					<div class="col-sm-10">
						<select id="te-analization-language-to" onchange="$('#te-begin-removing_data_from_db').val('<?php echo $entity_begin_removing_data_from_db; ?> - ' + this.options[this.selectedIndex].text)">
							<?php foreach ($languages as $language) { ?>
							<option value="<?php echo $language['language_id']; ?>" <?php if ($te_langIdTo == $language['language_id']) { echo 'selected="selected"'; } ?>><?php echo $language['name']; ?></option>
							<?php } ?>
						</select>
					</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="te-analization-table-deep"><?php echo $entry_table_deep; ?></label>
					<div class="col-sm-10">
						<input type="checkbox" id="te-analization-table-deep" value="1" checked="checked"><?php echo $entry_table_deep_description; ?>
					</div>
				  </div>

				  <div class="form-group">
					<label class="col-sm-2 control-label" for="te-analization-product-status-filter"><?php echo $entry_table_product_filter; ?></label>
					<div class="col-sm-10">
						<input type="checkbox" id="te-analization-product-status-filter" value="1" <?php if ($te_product_status) { echo 'checked="checked"'; } ?>> <?php echo $entry_table_product_status_filter; ?><br><br>
						<input type="checkbox" id="te-analization-product-quantity-filter" value="1" <?php if ($te_product_quantity) { echo 'checked="checked"'; } ?>> <?php echo $entry_table_product_quantity_filter; ?><br><br>
						<select id="te-analization-product-category-id-filter" multiple="multiple" size="6" class="form-control">
							<?php foreach($categories as $category) { ?>
							<?php if(in_array((string)$category['category_id'], $te_product_category_id)) { ?>
							<option value="<?php echo $category['category_id']; ?>" selected="selected"><?php echo $category['name']; ?></option>
							<?php } else { ?>
							<option value="<?php echo $category['category_id']; ?>"><?php echo $category['name']; ?></option>
							<?php } ?>
							<?php } ?>
						</select> <?php echo $entry_table_product_category_id_filter; ?>
						<div style="margin-top: 5px;"><button type="button" class="btn btn-default btn-xs te-multi-select-all" data-te-target="#te-analization-product-category-id-filter"><?php echo $button_select_all; ?></button> <button type="button" class="btn btn-default btn-xs te-multi-select-clear" data-te-target="#te-analization-product-category-id-filter"><?php echo $button_clear_selection; ?></button></div>
						<span class="help-block"><?php echo $help_multi_select; ?></span><br>
						<select id="te-analization-product-stock-status-filter" multiple="multiple" size="6" class="form-control">
							<?php foreach ($stock_statuses as $stock_status) { ?>
							<option value="<?php echo $stock_status['stock_status_id']; ?>" <?php if (in_array((string)$stock_status['stock_status_id'], $te_stock_status)) { echo 'selected="selected"'; } ?>><?php echo $stock_status['name']; ?></option>
							<?php } ?>
						</select> <?php echo $entry_table_product_stock_status_filter; ?>
						<div style="margin-top: 5px;"><button type="button" class="btn btn-default btn-xs te-multi-select-all" data-te-target="#te-analization-product-stock-status-filter"><?php echo $button_select_all; ?></button> <button type="button" class="btn btn-default btn-xs te-multi-select-clear" data-te-target="#te-analization-product-stock-status-filter"><?php echo $button_clear_selection; ?></button></div>
						<span class="help-block"><?php echo $help_multi_select; ?></span><br>
					</div>
				  </div>

				  <div class="form-group" id="te-cron-link-panel" data-cron-url="<?php echo htmlspecialchars($te_cron_url, ENT_QUOTES, 'UTF-8'); ?>">
					<label class="col-sm-2 control-label" for="te-cron-link"><?php echo $entry_cron_link; ?></label>
					<div class="col-sm-10">
					  <input type="text" readonly="readonly" id="te-cron-link" class="form-control" value="" <?php if (!$te_cron_secret_valid) { echo 'style="display:none"'; } ?> />
					  <span class="help-block" <?php if (!$te_cron_secret_valid) { echo 'style="display:none"'; } ?> id="te-cron-link-help"><?php echo $help_cron_link; ?></span>
					  <div class="text-danger" id="te-cron-link-error" <?php if ($te_cron_secret_valid) { echo 'style="display:none"'; } ?>><?php echo $error_cron_secret; ?></div>
					</div>
				  </div>

				  <div class="form-group">
					<label class="col-sm-2 control-label" for="te-begin-analization"><?php echo $entry_analization; ?></label>
					<div class="col-sm-10">
						<input type="button" id="te-begin-analization" value="<?php echo $entity_begin_analization; ?>">
					</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="te-remove-translation" style="color:red"><?php echo $entry_remove_translation_records_from_db; ?></label>
					<div class="col-sm-10">
						<input type="button" id="te-begin-removing_data_from_db" value="<?php echo $entity_begin_removing_data_from_db; ?>">
					</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="input-client_translate_expert_status"><?php echo $entry_analization_result; ?></label>
					<div class="col-sm-10">
						<div id="te-analization-result">
						</div>
					</div>
				  </div>
				</div>
				
				
				
				
				<div class="tab-pane" id="tab-localization">
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="te-localization-language-from"><?php echo $entry_localization_language_from; ?></label>
					<div class="col-sm-10">
						<select id="te-localization-language-from">
							<?php foreach ($languages as $language) { ?>
							<option value="<?php echo $language['language_id']; ?>" <?php if ($te_langIdFrom == $language['language_id']) { echo 'selected="selected"'; } ?>><?php echo $language['name']; ?></option>
							<?php } ?>
						</select>
					</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="te-localization-language-to"><?php echo $entry_localization_language_to; ?></label>
					<div class="col-sm-10">
						<select id="te-localization-language-to">
							<?php foreach ($languages as $language) { ?>
							<option value="<?php echo $language['language_id']; ?>" <?php if ($te_langIdTo == $language['language_id']) { echo 'selected="selected"'; } ?>><?php echo $language['name']; ?></option>
							<?php } ?>
						</select>
					</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="te-localization-analization"><?php echo $entry_localization_analization; ?></label>
					<div class="col-sm-10">
						<input type="button" id="te-begin-localization-analization" value="<?php echo $entity_begin_analization; ?>">
					</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label" for="input-client_translate_expert_status"><?php echo $entry_localization_analization_result; ?></label>
					<div class="col-sm-10">
						<div id="te-localization-analization-result">
						</div>
					</div>
				  </div>	  
				</div>
				
				
				
				
				<div class="tab-pane <?php echo $tab_debug_class; ?>" id="tab-debug">
				  <div class="form-group">
						<label class="col-sm-2 control-label" for="client_translate_expert_version"><?php echo $entry_translate_expert_version; ?></label>
						<div class="col-sm-10">
							<strong name="client_translate_expert_version" id="client_translate_expert_version" class="form-control"><?php echo $translate_expert_version; ?></strong>
						</div>
				  </div>
				  <div class="form-group">
						<label class="col-sm-2 control-label" for="php_version"><?php echo $entry_php_version; ?></label>
						<div class="col-sm-10">
							<strong name="php_version" id="php_version" class="form-control"><?php echo $php_version; ?></strong>
						</div>
				  </div>
				  <div class="form-group">
						<label class="col-sm-2 control-label" for="opencart_version"><?php echo $entry_opencart_version; ?></label>
						<div class="col-sm-10">
							<strong name="opencart_version" id="opencart_version" class="form-control"><?php echo $opencart_version; ?></strong>
						</div>
				  </div>
				  <div class="form-group">
						<label class="col-sm-2 control-label" for="reset_translation_cache"><?php echo $entry_reset_translation_cache; ?></label>
						<div class="col-sm-10">
							<div class="btn btn-primary" name="reset_translation_cache" id="reset_translation_cache" title="<?php echo $entry_reset_translation_cache; ?>" onclick="resetTranslationCache()"><?php echo $entry_reset_translation_cache; ?></div>
						</div>
				  </div>
				  <div class="form-group">
					<label class="col-sm-2 control-label" ><?php echo $entry_debug_status; ?></label>
					<div class="col-sm-10">
						<div class="btn-group" data-toggle="buttons">
							<label class="btn btn-primary <?php if ($client_translate_expert_debug_status) { ?>active<?php } ?>">
								<input type="radio" name="client_translate_expert_debug_status" value="1"
									<?php if ($client_translate_expert_debug_status) { ?>checked="checked"<?php } ?>> <?php echo $text_enabled; ?>
							</label>
							<label class="btn btn-primary <?php if (!$client_translate_expert_debug_status) { ?>active<?php } ?>">
								<input type="radio" name="client_translate_expert_debug_status" value="0"
									<?php if (!$client_translate_expert_debug_status) { ?>checked="checked"<?php } ?>> <?php echo $text_disabled; ?>
							</label>
                        </div>
					</div>
				  </div>
				  
					<div class="panel panel-default">

						<div class="container-fluid">
							<div class="pull-left">
								<?php if (isset($debug_log)) { ?>
								<a href="<?php echo $download_href; ?>" data-toggle="tooltip" title="<?php echo $button_download; ?>" class="btn btn-primary"><i class="fa fa-download"></i></a>
								<a onclick="confirm('<?php echo $text_clear_confirm; ?>') ? location.href='<?php echo $clear_href; ?>' : false;" data-toggle="tooltip" title="<?php echo $button_clear; ?>" class="btn btn-danger"><i class="fa fa-eraser"></i></a>
								<?php } else { ?>
								<a class="btn btn-info" href="<?php echo $view_href; ?>" data-toggle="tooltip" title="" data-original-title="<?php echo $button_view; ?>"><i class="fa fa-eye"><?php echo $button_view; ?></i></a>							
								<?php } ?>
							</div>
						</div>
						<div class="container-fluid">
							<div class="panel-heading">
								<h3 class="panel-title"><i class="fa fa-exclamation-triangle"></i> <?php echo $text_debug_log; ?></h3>
							</div>
						</div>
						<?php if (isset($debug_log)) { ?>
							<?php if (isset($error_debug_log_warning_frm) && $error_debug_log_warning_frm) { ?>
							<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_debug_log_warning_frm; ?>
								<button type="button" class="close" data-dismiss="alert">&times;</button>
							</div>
							<?php } ?>
							<div class="panel-body">
								<textarea wrap="off" rows="30" readonly class="form-control"><?php echo $debug_log; ?></textarea>
							</div>
						<?php } ?>
					</div>
				  
				</div>
				<div class="tab-pane" id="tab-about">
					<?php echo $text_about_page; ?>
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
	document.js_const_done_status = "<?php echo $js_const_done_status; ?>";
	document.js_const_in_progress_status = "<?php echo $js_const_in_progress_status; ?>";
	document.js_const_statistic_html = "<?php echo str_replace(array('\n', '\r', PHP_EOL), '', $js_const_statistic_html); ?>";
	document.js_const_analization_process_is_started = "<?php echo $js_const_analization_process_is_started; ?>";
	// removing
	document.js_const_entry_remove_translation_records_from_db_confirmation = "<?php echo $entry_remove_translation_records_from_db_confirmation; ?>";
	document.js_const_entry_removing_data_from_db_process_is_started = "<?php echo $entry_removing_data_from_db_process_is_started; ?>";
	
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

	$('#te-analization-language-to').change();
</script>
<?php echo $footer; ?>