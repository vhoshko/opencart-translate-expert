<?php

// add missed functions
if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        if (!is_string($known_string) || !is_string($user_string)) {
            return false;
        }

        $known_len = strlen($known_string);
        $user_len = strlen($user_string);

        if ($user_len !== $known_len) {
            return false;
        }

        $result = 0;
        for ($i = 0; $i < $known_len; $i++) {
            $result |= (ord($known_string[$i]) ^ ord($user_string[$i]));
        }

        return $result === 0;
    }
}
if (!function_exists('boolval')) {
    function boolval($var) {
        return (bool) $var;
    }
}

class ControllerExtensionModuleClientTranslateExpert extends Controller {
	private $error = array();

	public function getCurrentVersion()
	{
		return '{client_version}';
	}

	private static function tokenName($version)
	{
		return version_compare($version, '3.0', '>=') ? 'user_token' : 'token';
	}

	private static function settingKey($name, $version)
	{
		return (version_compare($version, '3.0', '>=') ? 'module_' : '') . $name;
	}

	private static function prefixSettingKeys(array $post, $version)
	{
		$result = array();
		foreach ($post as $key => $value) {
			$result[strpos($key, 'client_translate_expert_') === 0 ? self::settingKey($key, $version) : $key] = $value;
		}
		return $result;
	}

	private static function extensionListRoute($version)
	{
		if (version_compare($version, '2.3', '<'))
			return 'extension/module';
		if (version_compare($version, '3.0', '<'))
			return 'extension/extension';
		return 'marketplace/extension';
	}

	private static function routeMethodSeparator($version)
	{
		return version_compare($version, '4.0', '>=') ? '.' : '/';
	}

	private static function needsPhpTemplateEngine($version)
	{
		return version_compare($version, '3.0', '>=') && version_compare($version, '4.0', '<');
	}

	private static function languageImageUrl($code, array $language, $version)
	{
		if (version_compare($version, '4.0', '>='))
			return isset($language['image']) ? $language['image'] : '';
		if (version_compare($version, '2.2', '<'))
			return 'view/image/flags/' . $language['image'];
		return "language/{$code}/{$code}.png";
	}

	private function tokenQuery($version)
	{
		$name = self::tokenName($version);
		return $name . '=' . $this->session->data[$name];
	}

	public function index() {
		$this->load->language('extension/module/client_translate_expert');

		// heading_title holds HTML (icon + colored part) for the module list and the H1.
		// Use the plain text for the browser title, breadcrumbs and the modal title.
		$heading_title_text = strip_tags($this->language->get('heading_title'));

		$this->document->setTitle($heading_title_text);

		// $this->load->model('extension/module');
		$this->load->model('setting/setting');
		$this->load->model('extension/module/client_translate_expert');
		$this->model_extension_module_client_translate_expert->ensureLanguageCodeMapDefault();

		// OpenCart escapes the POST values with htmlspecialchars(). Decode the JSON one time here.
		if (isset($this->request->post['client_translate_expert_language_code_map']) && is_string($this->request->post['client_translate_expert_language_code_map']))
		{
			$this->request->post['client_translate_expert_language_code_map'] = html_entity_decode($this->request->post['client_translate_expert_language_code_map'], ENT_QUOTES, 'UTF-8');
		}

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate() && $this->validateLanguageCodeMap()) {
			// The character counter and its reset month live in their own setting group.
			// Move them there before editSetting(), so that a settings save does not reset the count.
			unset($this->request->post['client_translate_expert_char_count']);
			unset($this->request->post['client_translate_expert_last_reset_char_month']);
			$this->model_extension_module_client_translate_expert->prepareCounterSettings();
			if (isset($this->request->post['client_translate_expert_cron_secret']))
			{
				$this->request->post['client_translate_expert_cron_secret'] = trim($this->request->post['client_translate_expert_cron_secret']);
			}
			if (isset($this->request->post['client_translate_expert_cron_bulk_amount']))
			{
				$this->request->post['client_translate_expert_cron_bulk_amount'] = $this->model_extension_module_client_translate_expert->clampCronBulkAmount($this->request->post['client_translate_expert_cron_bulk_amount']);
			}
			if (!isset($this->request->post['client_translate_expert_reset_char_count_on_start_month']))
			{
				$this->request->post['client_translate_expert_reset_char_count_on_start_month'] = 0;
			}
			if (!isset($this->request->post['client_translate_expert_show_char_count_globally']))
			{
				$this->request->post['client_translate_expert_show_char_count_globally'] = 0;
			}
			if (isset($this->request->post['client_translate_expert_language_code_map']))
			{
				$language_code_map = $this->model_extension_module_client_translate_expert->normalizeLanguageCodeMap($this->request->post['client_translate_expert_language_code_map']);
				$this->request->post['client_translate_expert_language_code_map'] = $language_code_map['json'];
			}
			else
			{
				$this->request->post['client_translate_expert_language_code_map'] = (string)$this->config->get(self::settingKey('client_translate_expert_language_code_map', VERSION));
			}
			$this->model_setting_setting->editSetting(self::settingKey('client_translate_expert', VERSION), self::prefixSettingKeys($this->request->post, VERSION));

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link(self::extensionListRoute(VERSION), $this->tokenQuery(VERSION) . '&type=module', true));
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['heading_title_text'] = $heading_title_text;

		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_confirm'] = $this->language->get('text_confirm');

		$data['entry_server_key'] = $this->language->get('entry_server_key');
		$data['entry_limit'] = $this->language->get('entry_limit');
		$data['entry_width'] = $this->language->get('entry_width');
		$data['entry_height'] = $this->language->get('entry_height');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_analization'] = $this->language->get('entry_analization');
		$data['entity_begin_analization'] = $this->language->get('entity_begin_analization');

		// remove translation
		$data['entry_remove_translation_records_from_db'] = $this->language->get('entry_remove_translation_records_from_db');
		$data['entity_begin_removing_data_from_db'] = $this->language->get('entity_begin_removing_data_from_db');
		$data['entry_remove_translation_records_from_db_confirmation'] = $this->language->get('entry_remove_translation_records_from_db_confirmation');
		$data['entry_removing_data_from_db_process_is_started'] = $this->language->get('entry_removing_data_from_db_process_is_started');

		// char count
		$data['entry_server_show_translation_char_count_globally'] = $this->language->get('entry_server_show_translation_char_count_globally');
		$data['entry_server_reset_translation_char_count_on_start_month'] = $this->language->get('entry_server_reset_translation_char_count_on_start_month');
		$data['entry_server_translation_char_count'] = $this->language->get('entry_server_translation_char_count');

		// excluded routes
		$data['entry_excluded_routes'] = $this->language->get('entry_excluded_routes');
		$data['help_excluded_routes'] = $this->language->get('help_excluded_routes');

		// reset cache
		$data['entry_reset_translation_cache'] = $this->language->get('entry_reset_translation_cache');

		$data['entry_info'] = $this->language->get('entry_info');
		$data['entry_char_quantity'] = $this->language->get('entry_char_quantity');
		$data['entry_login'] = $this->language->get('entry_login');
		$data['entry_analization_mode'] = $this->language->get('entry_analization_mode');

		$data['entry_stop_modal_process'] = $this->language->get('entry_stop_modal_process');
		$data['entry_close'] = $this->language->get('entry_close');
		$data['entry_stop_modal_process_confirmation'] = $this->language->get('entry_stop_modal_process_confirmation');
		$data['entry_try_translate_text'] = $this->language->get('entry_try_translate_text');
		$data['entry_translate_expert_version'] = $this->language->get('entry_translate_expert_version');
		$data['entry_php_version'] = $this->language->get('entry_php_version');
		$data['entry_opencart_version'] = $this->language->get('entry_opencart_version');
		$data['entry_debug_status'] = $this->language->get('entry_debug_status');
		$data['entry_table'] = $this->language->get('entry_table');
		$data['entry_table_deep'] = $this->language->get('entry_table_deep');
		$data['entry_table_deep_description'] = $this->language->get('entry_table_deep_description');

		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_login_is_correct'] = $this->language->get('text_login_is_correct');
		$data['text_need_to_login'] = $this->language->get('text_need_to_login');
		$data['text_no_need_to_pay'] = $this->language->get('text_no_need_to_pay');
		$data['text_need_to_pay'] = $this->language->get('text_need_to_pay');
		$data['text_about_page'] = $this->language->get('text_about_page');
		$data['text_current_module_version'] = $this->language->get('text_current_module_version');
		$data['text_newest_module_version'] = $this->language->get('text_newest_module_version');
		$data['text_you_need_to_update_module'] = $this->language->get('text_you_need_to_update_module');
		$data['text_you_have_the_newest_version_of_module'] = $this->language->get('text_you_have_the_newest_version_of_module');
		$data['text_debug_log'] = $this->language->get('text_debug_log');
		$data['text_clear_confirm'] = $this->language->get('text_clear_confirm');

		$data['value_analization_mode_only_empty'] = $this->language->get('value_analization_mode_only_empty');
		$data['value_analization_mode_same_value'] = $this->language->get('value_analization_mode_same_value');
		$data['value_analization_mode_both'] = $this->language->get('value_analization_mode_both');

		$data['tab_general'] = $this->language->get('tab_general');
		$data['tab_translate_site'] = $this->language->get('tab_translate_site');
		$data['tab_localization'] = $this->language->get('tab_localization');
		$data['tab_admin_localization'] = $this->language->get('tab_admin_localization');
		$data['tab_debug'] = $this->language->get('tab_debug');
		$data['tab_about'] = $this->language->get('tab_about');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_download'] = $this->language->get('button_download');
		$data['button_clear'] = $this->language->get('button_clear');
		$data['button_view'] = $this->language->get('button_view');
		$data['button_show_key'] = $this->language->get('button_show_key');
		$data['button_hide_key'] = $this->language->get('button_hide_key');
		$data['button_cron_secret_generate'] = $this->language->get('button_cron_secret_generate');

		$data['entry_cron_bulk_amount'] = $this->language->get('entry_cron_bulk_amount');
		$data['help_cron_bulk_amount'] = $this->language->get('help_cron_bulk_amount');
		$data['entry_cron_secret'] = $this->language->get('entry_cron_secret');
		$data['help_cron_secret'] = $this->language->get('help_cron_secret');
		$data['entry_cron_link'] = $this->language->get('entry_cron_link');
		$data['help_cron_link'] = $this->language->get('help_cron_link');
		$data['help_multi_select'] = $this->language->get('help_multi_select');
		$this->load->model('extension/module/client_translate_expert');
		$data['te_multi_select_max'] = (int)$this->model_extension_module_client_translate_expert->getListMaxItems();
		$data['te_cron_link_max_length'] = 4000;
		$data['help_multi_select_limit'] = sprintf($this->language->get('help_multi_select_limit'), $data['te_multi_select_max']);
		$data['button_select_first'] = sprintf($this->language->get('button_select_first'), $data['te_multi_select_max']);
		$data['warning_cron_link_long'] = sprintf($this->language->get('warning_cron_link_long'), $data['te_cron_link_max_length']);
		$data['button_select_all'] = $this->language->get('button_select_all');
		$data['button_clear_selection'] = $this->language->get('button_clear_selection');
		$data['error_cron_secret'] = $this->language->get('error_cron_secret');
		$data['text_cron_secret_unsaved'] = $this->language->get('text_cron_secret_unsaved');

		$data['entry_table_product_filter'] = $this->language->get('entry_table_product_filter');
		$data['entry_table_product_status_filter'] = $this->language->get('entry_table_product_status_filter');
		$data['entry_table_product_quantity_filter'] = $this->language->get('entry_table_product_quantity_filter');
		$data['entry_table_product_category_id_filter'] = $this->language->get('entry_table_product_category_id_filter');
		$data['entry_table_product_stock_status_filter'] = $this->language->get('entry_table_product_stock_status_filter');
		$data['entry_table_product_manufacturer_filter'] = $this->language->get('entry_table_product_manufacturer_filter');

		// Front-end strings of the new admin design: the template writes them as document.te_i18n (read with teT() in the JS).
		$te_i18n = array();
		foreach (array('te_alert_details', 'te_alert_close', 'te_alert_not_json', 'te_summary_month', 'te_summary_cost', 'te_remove_completed', 'te_remove_start', 'te_remove_table', 'te_remove_column', 'te_remove_finish', 'te_time_in_sec',
			'te_site_stop', 'te_site_log', 'te_site_analyzing', 'te_site_translating', 'te_site_col_table', 'te_site_col_chars', 'te_site_col_chars_lang', 'te_site_col_actions', 'te_site_total', 'te_site_translate', 'te_site_translate_all', 'te_site_details', 'te_site_not_supported', 'te_site_done', 'te_site_toggle', 'te_site_cnt_current', 'te_site_cnt_table', 'te_site_cnt_request', 'te_site_cnt_chars', 'te_site_cnt_time', 'te_site_cnt_translated', 'te_site_translate_confirm', 'te_site_translate_all_confirm', 'te_site_log_table_done', 'te_site_detail_loading', 'te_site_detail_source', 'te_site_detail_target', 'te_site_empty', 'te_site_identical', 'te_site_detail_none', 'te_site_detail_more', 'te_site_state_done', 'te_site_state_stopped', 'te_site_state_error', 'te_loc_analyzing', 'te_loc_translating', 'te_loc_cnt_files', 'te_loc_totals_files', 'te_loc_totals_to_translate', 'te_loc_totals_values', 'te_loc_totals_chars', 'te_loc_col_file', 'te_loc_col_keys', 'te_loc_col_chars', 'te_loc_col_state', 'te_loc_state_missing', 'te_loc_state_partial', 'te_loc_state_saved', 'te_loc_toggle', 'te_loc_root', 'te_loc_expression', 'te_loc_source_file', 'te_loc_translate_file', 'te_loc_translate_save_file', 'te_loc_save_file', 'te_loc_saved', 'te_loc_nothing_to_save', 'te_loc_no_files', 'te_loc_translate_all_confirm', 'te_loc_translate_all', 'te_cache_reset_done', 'te_cache_reset_confirm', 'te_lcm_reset_confirm', 'te_lcm_err_empty_key', 'te_lcm_err_empty_value', 'te_lcm_err_duplicate', 'te_lcm_err_chars', 'te_lcm_err_length', 'te_lcm_err_count', 'te_lcm_row_label', 'te_lcm_col_key', 'te_lcm_col_value', 'value_analization_mode_both', 'value_analization_mode_only_empty', 'value_analization_mode_same_value') as $te_i18n_key) {
			$te_i18n[$te_i18n_key] = $this->language->get($te_i18n_key);
		}
		foreach (array('te_site_lang_from', 'te_site_lang_to', 'te_site_lang_swap', 'te_site_card_what', 'te_site_more_filters', 'te_loc_card_files', 'te_loc_analyze', 'te_summary_status', 'te_summary_enabled', 'te_summary_disabled', 'te_summary_api_key', 'te_summary_key_set', 'te_summary_key_not_set', 'te_settings_card_connection', 'te_settings_card_usage', 'te_settings_card_cron', 'te_settings_card_routes', 'te_settings_card_language_codes', 'te_lcm_add', 'te_lcm_reset', 'te_lcm_col_key', 'te_lcm_col_value', 'te_lcm_empty', 'te_lcm_delete', 'entry_language_code_map', 'help_language_code_map', 'te_settings_card_try', 'te_debug_card_versions', 'te_debug_card_cache', 'te_about_card') as $te_text_key) {
			$data[$te_text_key] = $this->language->get($te_text_key);
		}
		$data['te_summary_status_on'] = $this->config->get(self::settingKey('client_translate_expert_status', VERSION)) ? 1 : 0;
		$data['te_summary_key_set_on'] = trim((string)$this->config->get(self::settingKey('client_translate_expert_key', VERSION))) !== '' ? 1 : 0;
		// The JSON_HEX_* flags escape <, >, &, ' and ", so a value cannot close the <script> block.
		$te_i18n_json = json_encode($te_i18n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		$data['te_i18n_json'] = is_string($te_i18n_json) && $te_i18n_json !== '' ? $te_i18n_json : '{}';

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['error_language_code_map'] = isset($this->error['language_code_map']) ? $this->error['language_code_map'] : '';

		if (isset($this->error['width'])) {
			$data['error_width'] = $this->error['width'];
		} else {
			$data['error_width'] = '';
		}

		if (isset($this->error['height'])) {
			$data['error_height'] = $this->error['height'];
		} else {
			$data['error_height'] = '';
		}

		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();
		foreach($data['languages'] as $code => $language)
		{
			$data['languages'][$code]['image_url'] = self::languageImageUrl($code, $language, VERSION);
		}


		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', $this->tokenQuery(VERSION), true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link(self::extensionListRoute(VERSION), $this->tokenQuery(VERSION) . '&type=module', true)
		);

		if (!isset($this->request->get['module_id'])) {
			$data['breadcrumbs'][] = array(
				'text' => $heading_title_text,
				'href' => $this->url->link('extension/module/client_translate_expert', $this->tokenQuery(VERSION), true)
			);
		} else {
			$data['breadcrumbs'][] = array(
				'text' => $heading_title_text,
				'href' => $this->url->link('extension/module/client_translate_expert', $this->tokenQuery(VERSION) . '&module_id=' . $this->request->get['module_id'], true)
			);
		}

		$data['action'] = $this->url->link('extension/module/client_translate_expert', $this->tokenQuery(VERSION), true);

		$data['cancel'] = $this->url->link(self::extensionListRoute(VERSION), $this->tokenQuery(VERSION) . '&type=module', true);

		if (isset($this->request->post['client_translate_expert_key'])) {
			$data['client_translate_expert_key'] = $this->request->post['client_translate_expert_key'];
		} else {
			$data['client_translate_expert_key'] = $this->config->get(self::settingKey('client_translate_expert_key', VERSION));
		}

		if (isset($this->request->post['client_translate_expert_cron_secret'])) {
			$data['client_translate_expert_cron_secret'] = $this->request->post['client_translate_expert_cron_secret'];
		} else {
			$data['client_translate_expert_cron_secret'] = (string)$this->config->get(self::settingKey('client_translate_expert_cron_secret', VERSION));
		}

		if (isset($this->request->post['client_translate_expert_cron_bulk_amount'])) {
			$data['client_translate_expert_cron_bulk_amount'] = $this->request->post['client_translate_expert_cron_bulk_amount'];
		} else {
			$this->load->model('extension/module/client_translate_expert');
			$data['client_translate_expert_cron_bulk_amount'] = $this->model_extension_module_client_translate_expert->clampCronBulkAmount($this->config->get(self::settingKey('client_translate_expert_cron_bulk_amount', VERSION)));
		}

		if (isset($this->request->post['client_translate_expert_show_char_count_globally'])) {
			$data['client_translate_expert_show_char_count_globally'] = $this->request->post['client_translate_expert_show_char_count_globally'];
		} else {
			$data['client_translate_expert_show_char_count_globally'] = $this->config->get(self::settingKey('client_translate_expert_show_char_count_globally', VERSION));
		}

		if (isset($this->request->post['client_translate_expert_reset_char_count_on_start_month'])) {
			$data['client_translate_expert_reset_char_count_on_start_month'] = $this->request->post['client_translate_expert_reset_char_count_on_start_month'];
		} else {
			$data['client_translate_expert_reset_char_count_on_start_month'] = $this->config->get(self::settingKey('client_translate_expert_reset_char_count_on_start_month', VERSION));
		}

		if (isset($this->request->post['client_translate_expert_excluded_routes'])) {
			$data['client_translate_expert_excluded_routes'] = $this->request->post['client_translate_expert_excluded_routes'];
		} else {
			$data['client_translate_expert_excluded_routes'] = (string)$this->config->get(self::settingKey('client_translate_expert_excluded_routes', VERSION));
		}

		if (isset($this->request->post['client_translate_expert_language_code_map'])) {
			$data['client_translate_expert_language_code_map'] = $this->request->post['client_translate_expert_language_code_map'];
		} else {
			$data['client_translate_expert_language_code_map'] = (string)$this->config->get(self::settingKey('client_translate_expert_language_code_map', VERSION));
		}
		$data['te_language_code_map_default'] = $this->model_extension_module_client_translate_expert->getLanguageCodeMapDefault();

		$data['client_translate_expert_char_count'] = number_format((float)$this->config->get(self::settingKey('client_translate_expert_char_count', VERSION)), 0, '.', '`');

		if (isset($this->request->post['client_translate_expert_status'])) {
			$data['client_translate_expert_status'] = $this->request->post['client_translate_expert_status'];
		} else {
			$data['client_translate_expert_status'] = $this->config->get(self::settingKey('client_translate_expert_status', VERSION));
		}

		if (isset($this->request->post['client_translate_expert_debug_status'])) {
			$data['client_translate_expert_debug_status'] = $this->request->post['client_translate_expert_debug_status'];
		} else {
			$data['client_translate_expert_debug_status'] = $this->config->get(self::settingKey('client_translate_expert_debug_status', VERSION));
		}

		$data['te_langIdFrom'] = isset($this->session->data['te_langIdFrom']) ? $this->session->data['te_langIdFrom'] : null;
		$data['te_langIdTo'] = isset($this->session->data['te_langIdTo']) ? $this->session->data['te_langIdTo'] : null;
		$data['te_mode'] = isset($this->session->data['te_mode']) ? $this->session->data['te_mode'] : null;
		$data['te_table'] = $this->readSessionList('te_table');
		$data['te_product_status'] = isset($this->session->data['te_product_status']) ? $this->session->data['te_product_status'] : null;
		$data['te_product_quantity'] = isset($this->session->data['te_product_quantity']) ? $this->session->data['te_product_quantity'] : null;
		$data['te_product_category_id'] = $this->readSessionList('te_product_category_id');
		$data['te_stock_status'] = $this->readSessionList('te_stock_status');
		$data['te_manufacturer_id'] = $this->readSessionList('te_manufacturer_id');

		$data['translate_expert_version'] = $this->getCurrentVersion();
		$data['php_version'] = phpversion();
		$data['opencart_version'] = VERSION;
		$data['tab_general_class'] = 'active';
		$data['tab_debug_class'] = '';

		$this->load->model('localisation/stock_status');
		$data['stock_statuses'] = $this->model_localisation_stock_status->getStockStatuses();
		$this->load->model('catalog/manufacturer');
		$data['manufacturers'] = $this->model_catalog_manufacturer->getManufacturers();

		$this->load->model('catalog/category');
		$filter_data = array(
			'sort'        => 'name',
			'order'       => 'ASC'
		);
		$data['categories'] = $this->model_catalog_category->getCategories($filter_data);

		$this->load->model('extension/module/client_translate_expert');

		$cronBaseUrl = HTTP_CATALOG;
		if (defined('HTTPS_CATALOG') && !empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] != 'off')
		{
			$cronBaseUrl = HTTPS_CATALOG;
		}
		// The page JS adds the secret from the Cron secret field (saved or not) and the filters.
		$data['te_cron_url'] = $cronBaseUrl . 'index.php?route=extension/module/client_translate_expert_cron';
		$data['te_cron_secret_valid'] = $this->model_extension_module_client_translate_expert->isValidCronSecret((string)$data['client_translate_expert_cron_secret']);

		$data['download_href'] = $this->url->link('extension/module/client_translate_expert' . self::routeMethodSeparator(VERSION) . 'downloadDebugLog', $this->tokenQuery(VERSION), true);
		$data['clear_href'] = $this->url->link('extension/module/client_translate_expert' . self::routeMethodSeparator(VERSION) . 'clearDebugLog', $this->tokenQuery(VERSION), true);
		$data['view_href'] = $this->url->link('extension/module/client_translate_expert', 'view=1&' . $this->tokenQuery(VERSION), true);

		if (isset($this->request->get)
			&& isset($this->request->get['view'])
			&& $this->request->get['view'] == '1')
		{
			$data['tab_general_class'] = '';
			$data['tab_debug_class'] = 'active';
			$data['debug_log'] = '';
			$file = DIR_LOGS . 'client_translate_expert.log';
			if (file_exists($file))
			{
				$size = filesize($file);
				if ($size >= 5242880)
				{
					$suffix = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
					$i = 0;
					while (($size / 1024) > 1)
					{
						$size = $size / 1024;
						$i++;
					}
					$data['error_debug_log_warning_frm'] = sprintf($this->language->get('error_debug_log_warning'), basename($file), round(substr($size, 0, strpos($size, '.') + 4), 2) . $suffix[$i]);
				} else {
					$data['debug_log'] = file_get_contents($file, FILE_USE_INCLUDE_PATH, null);
				}
			}
			else
			{
				$data['debug_log'] = 'File is empty!';
			}
		}

		$data['tables'] = $this->getTables();

		if (version_compare(VERSION, '2.0', '<'))
		{
			$this->children = array(
				'common/header',
				'common/footer'
			);

			$this->template = 'module/client_translate_expert.tpl';
			$this->response->setOutput($this->render());
		}
		else
		{
			$data['header'] = $this->load->controller('common/header');
			$data['column_left'] = $this->load->controller('common/column_left');
			$data['footer'] = $this->load->controller('common/footer');

			if (self::needsPhpTemplateEngine(VERSION)) {
				$this->config->set('template_engine', 'template');
			}
			$this->response->setOutput($this->load->view('extension/module/client_translate_expert', $data));
		}
	}

	public function translate() {
		$this->runJsonAction(function () {
			if ($this->request->server['REQUEST_METHOD'] == 'POST') {
				$text = isset($this->request->post['text']) ? html_entity_decode($this->request->post['text']) : '';
				$textArr = isset($this->request->post['textArr']) ? html_entity_decode($this->request->post['textArr']) : '';
				$source = isset($this->request->post['source']) ? $this->request->post['source'] : '';
				$target = isset($this->request->post['target']) ? $this->request->post['target'] : 'en';
				$format = isset($this->request->post['format']) ? $this->request->post['format'] : 'html';
			}
			else {
				$text = isset($this->request->get['text']) ? html_entity_decode($this->request->get['text']) : '';
				$textArr = isset($this->request->get['textArr']) ? html_entity_decode($this->request->get['textArr']) : '';
				$source = isset($this->request->get['source']) ? $this->request->get['source'] : '';
				$target = isset($this->request->get['target']) ? $this->request->get['target'] : 'en';
				$format = isset($this->request->get['format']) ? $this->request->get['format'] : 'html';
			}

			if ($textArr)
			{
				$textArr = json_decode($textArr);
				foreach($textArr as &$textEl)
					$textEl = html_entity_decode($textEl);
				$text = $textArr;
			}

			$this->load->model('localisation/language');
			if (is_numeric($source))
			{
				$source = $this->model_localisation_language->getLanguage($source)['code'];
			}
			if (is_numeric($target))
			{
				$target = $this->model_localisation_language->getLanguage($target)['code'];
			}

			$this->load->model('extension/module/client_translate_expert');
			$result = $this->model_extension_module_client_translate_expert->translate($text, $source, $target, $format);

			// Count the characters as Google gets them (the library trims each text) and only for a successful call.
			$translatedCharCount = 0;
			if (is_array($text))
			{
				foreach($text as $textValue)
				{
					$translatedCharCount += mb_strlen(trim($textValue), 'UTF-8');
				}
			}
			else
			{
				$translatedCharCount = mb_strlen(trim($text), 'UTF-8');
			}
			if (is_object($result) && !empty($result->success))
			{
				$this->storeTranslatedCharCount($translatedCharCount);
			}

			return $result;
		});
	}

	private function readStrictListParam($source, $key, $isTable) {
		if (isset($source[$key]) && !is_string($source[$key])) {
			return false;
		}
		$raw = isset($source[$key]) && is_string($source[$key]) ? html_entity_decode($source[$key]) : '';
		$this->load->model('extension/module/client_translate_expert');
		return $isTable
			? $this->model_extension_module_client_translate_expert->parseTableList($raw)
			: $this->model_extension_module_client_translate_expert->parseIdList($raw);
	}

	private function badFilterResult() {
		$this->load->language('extension/module/client_translate_expert');
		return array('success' => 0, 'message' => $this->language->get('error_bad_filter'));
	}

	private function errorText($key, $default) {
		try {
			$this->load->language('extension/module/client_translate_expert');
			$text = $this->language->get($key);
		} catch (\Exception $e) {
			return $default;
		} catch (\Throwable $e) {
			return $default;
		}
		return ($text === $key || $text === '' || $text === null) ? $default : $text;
	}

	private function jsonErrorResult($e) {
		$this->log->write(get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
		return $this->convert_from_latin1_to_utf8_recursively(array('success' => 0, 'error_code' => 2000, 'message' => $this->errorText('error_internal', 'Internal error.'), 'additional_message' => get_class($e) . ': ' . $e->getMessage()));
	}

	private function runJsonAction($action) {
		try {
			$result = $action();
		} catch (\Exception $e) {
			$result = $this->jsonErrorResult($e);
		} catch (\Throwable $e) {
			$result = $this->jsonErrorResult($e);
		}

		$this->response->addHeader('Content-Type: application/json; Charset=UTF-8');
		$this->response->setOutput(json_encode($result));
	}

	private function readSessionList($key) {
		$list = array();
		if (isset($this->session->data[$key]) && !is_null($this->session->data[$key])) {
			foreach ((array)$this->session->data[$key] as $item) {
				if (is_scalar($item) && (string)$item !== '') {
					$list[] = (string)$item;
				}
			}
		}
		return $list;
	}

	public function translateTable() {
		$this->runJsonAction(function () {
			$langIdFrom = isset($this->request->post['langIdFrom']) ? (int)$this->request->post['langIdFrom'] : 0;
			$mode = isset($this->request->post['mode']) && is_string($this->request->post['mode']) ? $this->request->post['mode'] : '';
			$tableName = isset($this->request->post['tableName']) ? html_entity_decode($this->request->post['tableName']) : null;
			$textColumnName = isset($this->request->post['textColumnName']) ? html_entity_decode($this->request->post['textColumnName']) : null;
			$langIdTo = isset($this->request->post['langIdTo']) ? (int)$this->request->post['langIdTo'] : 0;
			$product_status = isset($this->request->post['product_status']) ? boolval(html_entity_decode($this->request->post['product_status'])) : false;
			$product_quantity = isset($this->request->post['product_quantity']) ? boolval(html_entity_decode($this->request->post['product_quantity'])) : false;
			$product_category_id = $this->readStrictListParam($this->request->post, 'product_category_id', false);
			$stock_status = $this->readStrictListParam($this->request->post, 'stock_status', false);
			$manufacturer_id = $this->readStrictListParam($this->request->post, 'manufacturer_id', false);

			if ($langIdFrom <= 0 || $langIdTo <= 0 || !in_array($mode, array('both', 'only_empty', 'same_value'), true) || $product_category_id === false || $stock_status === false || $manufacturer_id === false) {
				return $this->badFilterResult();
			}

			$this->load->model('extension/module/client_translate_expert');
			$result = $this->model_extension_module_client_translate_expert->translateTable($langIdFrom, $mode, $tableName, $textColumnName, $langIdTo, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id);

			if (isset($result->translatedCharCount))
			{
				$this->storeTranslatedCharCount($result->translatedCharCount);
			}

			return $result;
		});
	}

	public function analizeTableDetail() {
		$this->runJsonAction(function () {
			$langIdFrom = isset($this->request->post['langIdFrom']) ? (int)$this->request->post['langIdFrom'] : 0;
			$mode = isset($this->request->post['mode']) && is_string($this->request->post['mode']) ? $this->request->post['mode'] : '';
			$tableName = isset($this->request->post['tableName']) ? html_entity_decode($this->request->post['tableName']) : null;
			$textColumnName = isset($this->request->post['textColumnName']) ? html_entity_decode($this->request->post['textColumnName']) : null;
			$langIdTo = isset($this->request->post['langIdTo']) ? (int)$this->request->post['langIdTo'] : 0;
			$product_status = isset($this->request->post['product_status']) ? boolval(html_entity_decode($this->request->post['product_status'])) : false;
			$product_quantity = isset($this->request->post['product_quantity']) ? boolval(html_entity_decode($this->request->post['product_quantity'])) : false;
			$product_category_id = $this->readStrictListParam($this->request->post, 'product_category_id', false);
			$stock_status = $this->readStrictListParam($this->request->post, 'stock_status', false);
			$manufacturer_id = $this->readStrictListParam($this->request->post, 'manufacturer_id', false);

			if ($langIdFrom <= 0 || $langIdTo <= 0 || !in_array($mode, array('both', 'only_empty', 'same_value'), true) || $product_category_id === false || $stock_status === false || $manufacturer_id === false) {
				return $this->badFilterResult();
			}

			$this->load->model('extension/module/client_translate_expert');
			$result = $this->model_extension_module_client_translate_expert->analizeTableDetail($langIdFrom, $mode, $tableName, $textColumnName, $langIdTo, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id);

			return $result;
		});
	}

	public function analize() {
		$this->runJsonAction(function () {
			$langIdFrom = isset($this->request->get['langIdFrom']) ? (int)$this->request->get['langIdFrom'] : 0;
			$langIdTo = isset($this->request->get['langIdTo']) ? (int)$this->request->get['langIdTo'] : 0;
			$mode = isset($this->request->get['mode']) && is_string($this->request->get['mode']) ? $this->request->get['mode'] : '';
			$table = $this->readStrictListParam($this->request->get, 'table', true);
			$product_status = isset($this->request->get['product_status']) ? boolval(html_entity_decode($this->request->get['product_status'])) : false;
			$product_quantity = isset($this->request->get['product_quantity']) ? boolval(html_entity_decode($this->request->get['product_quantity'])) : false;
			$product_category_id = $this->readStrictListParam($this->request->get, 'product_category_id', false);
			$stock_status = $this->readStrictListParam($this->request->get, 'stock_status', false);
			$manufacturer_id = $this->readStrictListParam($this->request->get, 'manufacturer_id', false);

			if ($langIdFrom <= 0 || $langIdTo <= 0 || !in_array($mode, array('both', 'only_empty', 'same_value'), true) || $product_category_id === false || $stock_status === false || $manufacturer_id === false || $table === false) {
				return $this->badFilterResult();
			}

			$continueAfterTable = isset($this->request->get['continue_after_table']) ? html_entity_decode($this->request->get['continue_after_table']) : null;
			$continueAfterColumn = isset($this->request->get['continue_after_column']) ? html_entity_decode($this->request->get['continue_after_column']) : null;
			$continueOffset = isset($this->request->get['continue_offset']) ? intval(html_entity_decode($this->request->get['continue_offset'])) : 0;

			$this->session->data['te_langIdFrom'] = $langIdFrom;
			$this->session->data['te_langIdTo'] = $langIdTo;
			$this->session->data['te_mode'] = $mode;
			$this->session->data['te_table'] = $table;

			$this->session->data['te_product_status'] = $product_status;
			$this->session->data['te_product_quantity'] = $product_quantity;
			$this->session->data['te_product_category_id'] = $product_category_id;
			$this->session->data['te_stock_status'] = $stock_status;
			$this->session->data['te_manufacturer_id'] = $manufacturer_id;

			$this->load->model('extension/module/client_translate_expert');
			$result = $this->model_extension_module_client_translate_expert->analize($langIdFrom, $langIdTo, $mode, $table, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id,
				$continueAfterTable, $continueAfterColumn, $continueOffset);

			return $result;
		});
	}

	public function removeDataFromDb() {
		$this->runJsonAction(function () {
			if (!isset($this->request->server['REQUEST_METHOD']) || $this->request->server['REQUEST_METHOD'] != 'POST') {
				return array('success' => 0, 'error_code' => 2001, 'message' => $this->errorText('error_wrong_method', 'Wrong request method.'));
			}

			$langIdTo = isset($this->request->post['langIdTo']) ? (int)$this->request->post['langIdTo'] : 0;
			$table = $this->readStrictListParam($this->request->post, 'table', true);
			$product_status = isset($this->request->post['product_status']) ? boolval(html_entity_decode($this->request->post['product_status'])) : false;
			$product_quantity = isset($this->request->post['product_quantity']) ? boolval(html_entity_decode($this->request->post['product_quantity'])) : false;
			$product_category_id = $this->readStrictListParam($this->request->post, 'product_category_id', false);
			$stock_status = $this->readStrictListParam($this->request->post, 'stock_status', false);
			$manufacturer_id = $this->readStrictListParam($this->request->post, 'manufacturer_id', false);

			if ($table === false || $product_category_id === false || $stock_status === false || $manufacturer_id === false) {
				return $this->badFilterResult();
			}

			$this->session->data['te_langIdTo'] = $langIdTo;
			$this->session->data['te_table'] = $table;

			$this->session->data['te_product_status'] = $product_status;
			$this->session->data['te_product_quantity'] = $product_quantity;
			$this->session->data['te_product_category_id'] = $product_category_id;
			$this->session->data['te_stock_status'] = $stock_status;
			$this->session->data['te_manufacturer_id'] = $manufacturer_id;

			$this->load->model('extension/module/client_translate_expert');

			$result = $this->model_extension_module_client_translate_expert->removeDataFromDb($langIdTo, $table, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id);

			return $result;
		});
	}

	public function analizeLocalization() {
		$this->runJsonAction(function () {
			$langIdFrom = isset($this->request->get['langIdFrom']) ? (int)$this->request->get['langIdFrom'] : 0;
			$langIdTo = isset($this->request->get['langIdTo']) ? (int)$this->request->get['langIdTo'] : 0;
			$nextFileIndex = isset($this->request->get['nextFileIndex']) ? (int)$this->request->get['nextFileIndex'] : 0;
			$scope = (isset($this->request->get['scope']) && $this->request->get['scope'] === 'admin') ? 'admin' : 'catalog';

			if ($langIdFrom <= 0 || $langIdTo <= 0) {
				return $this->badFilterResult();
			}
			if ($nextFileIndex < 0) {
				$nextFileIndex = 0;
			}

			$this->session->data['te_langIdFrom'] = $langIdFrom;
			$this->session->data['te_langIdTo'] = $langIdTo;

			$this->load->model('extension/module/client_translate_expert');
			$result = $this->model_extension_module_client_translate_expert->analizeLocalization($langIdFrom, $langIdTo, $nextFileIndex, $scope);

			$result = $this->convert_from_latin1_to_utf8_recursively($result);

			return $result;
		});
	}

	public function translatedCharCountInfo()
	{
		$this->runJsonAction(function () {
			$this->resetCharCountOnStartMonth();

			$this->load->model('extension/module/client_translate_expert');
			$charCount = (int)$this->model_extension_module_client_translate_expert->getCharCount();
			$monthFreeLimit = 500*1000;
			$priceFor20MInUSD = 20;
			$costOverFreeLimit = $charCount <= $monthFreeLimit ? 0 : round(($charCount - $monthFreeLimit) / (1000 * 1000) * $priceFor20MInUSD, 2);

			$result = (object)array(
				'success' => 1,
				'charCount' => $charCount,
				'monthFreeLimit' => $monthFreeLimit,
				'priceFor20MInUSD' => $priceFor20MInUSD,
				'costOverFreeLimit' => $costOverFreeLimit
			);

			return $result;
		});
	}

	public function resetCharCount()
	{
		$this->runJsonAction(function () {
			$this->load->model('extension/module/client_translate_expert');
			$this->model_extension_module_client_translate_expert->resetCharCount(date('m'));

			$result = (object)array(
				'success' => 1,
			);

			return $result;
		});
	}

	public function resetCache()
	{
		$this->runJsonAction(function () {
			if (!isset($this->request->server['REQUEST_METHOD']) || $this->request->server['REQUEST_METHOD'] != 'POST') {
				return array('success' => 0, 'error_code' => 2001, 'message' => $this->errorText('error_wrong_method', 'Wrong request method.'));
			}

			$this->load->model('extension/module/client_translate_expert');
			$result = $this->model_extension_module_client_translate_expert->uninstall();
			$result = $this->model_extension_module_client_translate_expert->install();

			$result = (object)array(
				'success' => true,
			);

			return $result;
		});
	}

	private function storeTranslatedCharCount($charCount = 0)
	{
		if (is_null($charCount))
		{
			$charCount = 0;
		}

		$this->resetCharCountOnStartMonth();

		$this->load->model('extension/module/client_translate_expert');
		$this->model_extension_module_client_translate_expert->addCharCount($charCount);
	}

	private function resetCharCountOnStartMonth()
	{
		if ($this->config->get(self::settingKey("client_translate_expert_reset_char_count_on_start_month", VERSION)))
		{
			$this->load->model('extension/module/client_translate_expert');
			$this->model_extension_module_client_translate_expert->resetCharCountOnNewMonth(date('m'));
		}
	}

	private function is_utf8($str) {
		$strlen = strlen($str);
		for ($i = 0; $i < $strlen; $i++) {
			$ord = ord($str[$i]);
			if ($ord < 0x80) continue; // 0bbbbbbb
			elseif (($ord & 0xE0) === 0xC0 && $ord > 0xC1) $n = 1; // 110bbbbb (exkl C0-C1)
			elseif (($ord & 0xF0) === 0xE0) $n = 2; // 1110bbbb
			elseif (($ord & 0xF8) === 0xF0 && $ord < 0xF5) $n = 3; // 11110bbb (exkl F5-FF)
			else return false; // invalid UTF-8-Zeichen
			for ($c=0; $c<$n; $c++) // $n following bytes? // 10bbbbbb
				if (++$i === $strlen || (ord($str[$i]) & 0xC0) !== 0x80)
					return false; // invalid UTF-8 char
		}
		return true; // didn't find any invalid characters
	}

	private function convert_from_latin1_to_utf8_recursively($dat)
	{
		if (is_string($dat)) {
			return $this->is_utf8($dat) ? $dat : mb_convert_encoding($dat, 'UTF-8', 'ISO-8859-1');
		} elseif (is_array($dat)) {
			$ret = [];
			foreach ($dat as $i => $d)
				$ret[ $i ] = $this->convert_from_latin1_to_utf8_recursively($d);
			return $ret;
		} elseif (is_object($dat)) {
			foreach ($dat as $i => $d)
				$dat->$i = $this->convert_from_latin1_to_utf8_recursively($d);
			return $dat;
		} else {
			return $dat;
		}
	}

	public function saveLocalizationFile()
	{
		$this->runJsonAction(function () {
			$this->load->language('extension/module/client_translate_expert');

			if (!$this->user->hasPermission('modify', 'extension/module/client_translate_expert')) {
				return array('success' => 0, 'error_code' => 3006, 'message' => $this->language->get('error_permission'));
			}

			$fileContent = (isset($this->request->post['fileContent']) && is_string($this->request->post['fileContent'])) ? html_entity_decode($this->request->post['fileContent']) : null;
			$langId = (isset($this->request->post['langId']) && is_string($this->request->post['langId'])) ? html_entity_decode($this->request->post['langId']) : null;
			$path = (isset($this->request->post['path']) && is_string($this->request->post['path'])) ? html_entity_decode($this->request->post['path']) : null;
			$scope = (isset($this->request->post['scope']) && $this->request->post['scope'] === 'admin') ? 'admin' : 'catalog';
			$root = (isset($this->request->post['root']) && is_string($this->request->post['root'])) ? html_entity_decode($this->request->post['root']) : 'core';

			$this->load->model('extension/module/client_translate_expert');
			$result = $this->model_extension_module_client_translate_expert->saveLocalizationFile($fileContent, $langId, $path, $scope, $root);

			return $result;
		});
	}

	public function downloadDebugLog() {
		$this->load->language('extension/module/client_translate_expert');

		$file = DIR_LOGS . 'client_translate_expert.log';

		if (file_exists($file) && filesize($file) > 0) {
			$this->response->addheader('Pragma: public');
			$this->response->addheader('Expires: 0');
			$this->response->addheader('Content-Description: File Transfer');
			$this->response->addheader('Content-Type: application/octet-stream');
			$this->response->addheader('Content-Disposition: attachment; filename="' . $this->config->get('config_name') . '_' . date('Y-m-d_H-i-s', time()) . '_error.log"');
			$this->response->addheader('Content-Transfer-Encoding: binary');

			$this->response->setOutput(file_get_contents($file, FILE_USE_INCLUDE_PATH, null));
		} else {
			$this->session->data['error'] = sprintf($this->language->get('error_debug_log_warning'), basename($file), '0B');

			$this->response->redirect($this->url->link('extension/module/client_translate_expert', 'view=1&' . $this->tokenQuery(VERSION), true));
		}
	}

	public function clearDebugLog() {
		$this->load->language('extension/module/client_translate_expert');

		if (!$this->user->hasPermission('modify', 'extension/module/client_translate_expert')) {
			$this->session->data['error'] = $this->language->get('error_permission');
		} else {
			$file = DIR_LOGS . 'client_translate_expert.log';

			$handle = fopen($file, 'w+');

			fclose($handle);

			$this->session->data['success'] = $this->language->get('text_clear_debug_log_success');
		}

		$this->response->redirect($this->url->link('extension/module/client_translate_expert', 'view=1&' . $this->tokenQuery(VERSION), true));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/client_translate_expert')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	protected function validateLanguageCodeMap() {
		if (!isset($this->request->post['client_translate_expert_language_code_map'])) {
			return true;
		}

		$language_code_map = $this->model_extension_module_client_translate_expert->normalizeLanguageCodeMap($this->request->post['client_translate_expert_language_code_map']);
		$messages = array();
		foreach ($language_code_map['errors'] as $language_code_error) {
			$code = $language_code_error['code'] == 'format' ? 'chars' : $language_code_error['code'];
			$message = $this->language->get('te_lcm_err_' . $code);
			$messages[] = $language_code_error['index'] > 0 ? $language_code_error['index'] . ': ' . $message : $message;
		}
		if ($messages) {
			$this->error['language_code_map'] = implode(' ', $messages);
			$this->error['warning'] = $this->language->get('error_language_code_map') . ' ' . $this->error['language_code_map'];
		}

		return !$messages;
	}

	protected function getInfo() {
		$this->load->model('extension/module/client_translate_expert');
		$result = $this->model_extension_module_client_translate_expert->getInfo();

		return $result;
	}

	protected function getTables() {
		$this->load->model('extension/module/client_translate_expert');
		$result = $this->model_extension_module_client_translate_expert->getTables();

		return $result;
	}

	public function install()
	{
		$this->load->model('extension/module/client_translate_expert');
		$result = $this->model_extension_module_client_translate_expert->install();

		// OC4_EVENT_START
		if (version_compare(VERSION, '4.0', '>=')) {
			$this->load->model('setting/event');

			$this->model_setting_event->deleteEventByCode('client_translate_expert_header_controller');
			$this->model_setting_event->addEvent([
				'code'        => 'client_translate_expert_header_controller',
				'description' => 'Inject Translate Expert config data into admin header',
				'trigger'     => 'admin/controller/common/header/after',
				'action'      => 'extension/client_translate_expert/module/client_translate_expert.eventHeaderControllerAfter',
				'status'      => 1,
				'sort_order'  => 0
			]);

			$this->model_setting_event->deleteEventByCode('client_translate_expert_header_view');
			$this->model_setting_event->addEvent([
				'code'        => 'client_translate_expert_header_view',
				'description' => 'Inject Translate Expert JS/CSS into admin header view',
				'trigger'     => 'admin/view/common/header/after',
				'action'      => 'extension/client_translate_expert/module/client_translate_expert.eventHeaderViewAfter',
				'status'      => 1,
				'sort_order'  => 0
			]);

		}
		// OC4_EVENT_END
	}

	public function uninstall()
	{
		$this->load->model('extension/module/client_translate_expert');
		$result = $this->model_extension_module_client_translate_expert->uninstall();
		// OpenCart deletes the module settings group itself. The counter rows are in their own group.
		$this->model_extension_module_client_translate_expert->deleteCounterSettings();

		// OC4_EVENT_START
		if (version_compare(VERSION, '4.0', '>=')) {
			$this->load->model('setting/event');
			$this->model_setting_event->deleteEventByCode('client_translate_expert_header_controller');
			$this->model_setting_event->deleteEventByCode('client_translate_expert_header_view');
		}
		// OC4_EVENT_END
	}

	// OC4_EVENT_START
	public function eventHeaderControllerAfter(string &$route, array &$args, mixed &$output): void {
		if ($this->isCurrentRouteExcluded()) {
			return;
		}

		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();

		$this->config->set('client_translate_expert_languages_json', json_encode($languages));
	}

	public function eventHeaderViewAfter(string &$route, array &$args, string &$output): void {
		if ($this->isCurrentRouteExcluded()) {
			return;
		}

		$isEnabled = $this->config->get(self::settingKey('client_translate_expert_status', VERSION));
		$showCharCount = $this->config->get(self::settingKey('client_translate_expert_show_char_count_globally', VERSION));
		$languagesJson = $this->config->get('client_translate_expert_languages_json');

		$version = $this->getCurrentVersion();

		$extensionAssetBase = '../extension/client_translate_expert/admin/view/';
		$js = '<script type="text/javascript" src="' . $extensionAssetBase . 'javascript/client_translate_expert.js?v=' . $version . '"></script>';
		$css = '<link type="text/css" rel="stylesheet" href="' . $extensionAssetBase . 'stylesheet/client_translate_expert.css?v=' . $version . '" />';
		$config = '<script>'
			. 'document.js_const_client_translate_expert_is_enabled = ' . (int)$isEnabled . ';'
			. 'document.js_const_client_translate_expert_show_char_count_is_enabled = ' . (int)$showCharCount . ';'
			. 'document.js_const_client_translate_expert_languages = ' . ($languagesJson ? $languagesJson : '{}') . ';'
			. 'document.te_i18n_global = ' . $this->getGlobalI18nJson() . ';'
			. 'document.js_const_module_prefix = "extension/client_translate_expert/module";'
			. 'document.js_const_module_method_separator = ".";'
			. '</script>';

		$output = str_replace('</head>', $js . $css . '</head>', $output);
		$headerPos = strpos($output, '<header');
		if ($headerPos !== false) {
			$headerClosePos = strpos($output, '>', $headerPos);
			if ($headerClosePos !== false) {
				$output = substr_replace($output, '>' . $config, $headerClosePos, 1);
			}
		}
	}

	// Strings of the header items for all admin pages (document.te_i18n_global).
	// The library reads the module language file in a function scope, so the shared language object of the page does not change.
	private function getGlobalI18nJson(): string {
		require_once(DIR_EXTENSION . 'client_translate_expert/system/library/translate_expert/translate_expert_i18n.php');

		$langDir = DIR_EXTENSION . 'client_translate_expert/admin/language/';
		$subPaths = array('/module/client_translate_expert.php');

		return \Opencart\Extension\ClientTranslateExpert\System\Library\TranslateExpertI18n::toJson(
			\Opencart\Extension\ClientTranslateExpert\System\Library\TranslateExpertI18n::loadGlobal(
				\Opencart\Extension\ClientTranslateExpert\System\Library\TranslateExpertI18n::candidateFiles($langDir, array((string)$this->config->get('config_language_admin')), $subPaths),
				\Opencart\Extension\ClientTranslateExpert\System\Library\TranslateExpertI18n::candidateFiles($langDir, array('en-gb'), $subPaths)
			)
		);
	}

	// The rules are in system/library/translate_expert/translate_expert_route_filter.php (shared with the OCMOD header op for OC 1.5–3.0).
	// The event $route is common/header, so the page route comes from the request.
	private function isCurrentRouteExcluded(): bool {
		require_once(DIR_EXTENSION . 'client_translate_expert/system/library/translate_expert/translate_expert_route_filter.php');

		return \Opencart\Extension\ClientTranslateExpert\System\Library\TranslateExpertRouteFilter::isExcluded(
			isset($this->request->get['route']) ? $this->request->get['route'] : null,
			(string)$this->config->get(self::settingKey('client_translate_expert_excluded_routes', VERSION)),
			'common/dashboard'
		);
	}
	// OC4_EVENT_END
}
