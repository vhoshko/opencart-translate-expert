<?php

require_once(DIR_SYSTEM . 'library/client_translate_expert.php');
require_once(DIR_SYSTEM . 'library/client_localization_translate_expert.php');

class ModelExtensionModuleClientTranslateExpert extends Model
{
	public function translate($text, $source, $target, $format)
	{
		try {
			$res = $this->getLibrary()->translate($text, $source, $target, $format);
			$this->getLibrary()->gebugLog("function translate \n" . var_export($res, true));
			return $res;
		} catch (\Exception $e) {
			$this->getLibrary()->gebugLog("EXCEPTION!!!!!! \n" . "function translate \n" . $e->getMessage());
			throw $e;
		}		
	}
	
	public function translateTable($languageIdFrom, $mode, $tableName, $textColumnNameParam, $langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status)
	{		
		try {
			$res = $this->getLibrary()->translateTable($languageIdFrom, $mode, $tableName, $textColumnNameParam, $langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status);
			$this->getLibrary()->gebugLog("function translateTable \n" . var_export($res, true));
			return $res;
		} catch (\Exception $e) {
			$this->getLibrary()->gebugLog("EXCEPTION!!!!!! \n" . "function translateTable \n" . $e->getMessage());
			throw $e;
		}		
	}
	
	public function analizeTableDetail($languageIdFrom, $mode, $tableName, $textColumnNameParam, $langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status)
	{		
		try {
			$res = $this->getLibrary()->analizeTableDetail($languageIdFrom, $mode, $tableName, $textColumnNameParam, $langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status);
			$this->getLibrary()->gebugLog("function analizeTableDetail \n" . var_export($res, true));
			return $res;
		} catch (\Exception $e) {
				$this->getLibrary()->gebugLog("EXCEPTION!!!!!! \n" . "function analizeTableDetail \n" . $e->getMessage());
				throw $e;
		}		
	}

	public function analize($languageIdFrom, $languageIdTo, $mode = null, $filterTable = '', $product_status = false, $product_quantity = false, $product_category_id = null, $stock_status = null, $continueAfterTable = null, $continueAfterColumn = null, $continueOffset = 0)
	{
		try {
			$res = $this->getLibrary()->analize($languageIdFrom, $languageIdTo, $mode, $filterTable, $product_status, $product_quantity, $product_category_id, $stock_status, $continueAfterTable, $continueAfterColumn, $continueOffset);
			$this->getLibrary()->gebugLog("function analize \n" . var_export($res, true));
			return $res;
		} catch (\Exception $e) {
				$this->getLibrary()->gebugLog("EXCEPTION!!!!!! \n" . "function analize \n" . $e->getMessage());
				throw $e;
		}		
	}

	public function removeDataFromDb($languageIdTo, $filterTable = '', $product_status = false, $product_quantity = false, $product_category_id = null, $stock_status = null)
	{
		try {
			$res = $this->getLibrary()->removeDataFromDb($languageIdTo, $filterTable, $product_status, $product_quantity, $product_category_id, $stock_status);
			$this->getLibrary()->gebugLog("function analize \n" . var_export($res, true));
			return $res;
		} catch (\Exception $e) {
				$this->getLibrary()->gebugLog("EXCEPTION!!!!!! \n" . "function removeDataFromDb \n" . $e->getMessage());
				throw $e;
		}		
	}

	public function analizeLocalization($languageIdFrom, $languageIdTo, $nextFileIndex = 0)
	{
		try {
			$res = $this->getLocaliationLibrary()->analizeLocalization($languageIdFrom, $languageIdTo, $nextFileIndex);
			$this->getLibrary()->gebugLog("function analizeLocaliation \n" . var_export($res, true));
			return $res;
		} catch (\Exception $e) {
				$this->getLibrary()->gebugLog("EXCEPTION!!!!!! \n" . "function analizeLocaliation \n" . $e->getMessage());
				throw $e;
		}		
	}
	
	public function saveLocalizationFile($fileContent, $langId, $path)
	{
		try {
			$res = $this->getLocaliationLibrary()->saveLocalizationFile($fileContent, $langId, $path);
			$this->getLibrary()->gebugLog("function saveLocalizationFile \n" . var_export($res, true));
			return $res;
		} catch (\Exception $e) {
				$this->getLibrary()->gebugLog("EXCEPTION!!!!!! \n" . "function saveLocalizationFile \n" . $e->getMessage());
				throw $e;
		}
	}
	
	public function getTables()
	{
		try {
			$res = $this->getLibrary()->getTables();
			$this->getLibrary()->gebugLog("function getTables \n" . var_export($res, true));
			return $res;
		} catch (\Exception $e) {
				$this->getLibrary()->gebugLog("EXCEPTION!!!!!! \n" . "function getTables \n" . $e->getMessage());
				throw $e;
		}		
	}
	
	public function install()
	{
		try {
			$res = $this->getLibrary()->install();
			$this->getLibrary()->gebugLog("function install \n" . var_export($res, true));
			return $res;
		} catch (\Exception $e) {
				$this->getLibrary()->gebugLog("EXCEPTION!!!!!! \n" . "function install \n" . $e->getMessage());
				throw $e;
		}		
	}
	
	public function uninstall()
	{
		try {
			$res = $this->getLibrary()->uninstall();
			$this->getLibrary()->gebugLog("function uninstall \n" . var_export($res, true));
			return $res;
		} catch (\Exception $e) {
				$this->getLibrary()->gebugLog("EXCEPTION!!!!!! \n" . "function uninstall \n" . $e->getMessage());
				throw $e;
		}		
	}
	
	// Monthly character counter.
	// The counter rows live in their own setting group, so that editSetting() for the module
	// settings never deletes them. The queries find the rows by store_id and key only,
	// so rows that are still in the old group work too.
	// Each write is a single SQL statement, so concurrent requests do not lose increments.

	private $counterGroup = 'client_translate_expert_usage';
	private $charCountKey = 'client_translate_expert_char_count';
	private $resetMonthKey = 'client_translate_expert_last_reset_char_month';

	private $_settingGroupColumn = null;

	// Old OpenCart versions name the group column `group`, newer versions name it `code`.
	private function settingGroupColumn()
	{
		if (is_null($this->_settingGroupColumn))
		{
			$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "setting` LIKE 'code'");
			$this->_settingGroupColumn = $query->num_rows ? 'code' : 'group';
		}
		return $this->_settingGroupColumn;
	}

	private function getCounterValue($key)
	{
		$query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `key` = '" . $this->db->escape($key) . "' ORDER BY `setting_id` ASC LIMIT 1");
		return $query->num_rows ? $query->row['value'] : null;
	}

	private function ensureCounterRow($key, $default)
	{
		if (is_null($this->getCounterValue($key)))
		{
			$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = '0', `" . $this->settingGroupColumn() . "` = '" . $this->db->escape($this->counterGroup) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($default) . "', `serialized` = '0'");
			// Two requests can both find no row and both insert one. Keep the oldest row only.
			// All updates change every row of the key, so the oldest row has all increments.
			$this->db->query("DELETE `s` FROM `" . DB_PREFIX . "setting` `s` JOIN (SELECT MIN(`setting_id`) AS `keep_id` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `key` = '" . $this->db->escape($key) . "') `k` WHERE `s`.`store_id` = '0' AND `s`.`key` = '" . $this->db->escape($key) . "' AND `s`.`setting_id` > `k`.`keep_id`");
		}
	}

	private function setCounterValue($key, $value)
	{
		$this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '" . $this->db->escape($value) . "' WHERE `store_id` = '0' AND `key` = '" . $this->db->escape($key) . "'");
		$this->config->set($key, $value);
	}

	// Moves the counter rows out of the module settings group and creates missing rows.
	// Call it before editSetting() for the module settings.
	public function prepareCounterSettings()
	{
		$this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `" . $this->settingGroupColumn() . "` = '" . $this->db->escape($this->counterGroup) . "' WHERE `store_id` = '0' AND `key` IN ('" . $this->db->escape($this->charCountKey) . "', '" . $this->db->escape($this->resetMonthKey) . "')");
		$this->ensureCounterRow($this->charCountKey, '0');
		$this->ensureCounterRow($this->resetMonthKey, date('m'));
	}

	public function getCharCount()
	{
		$value = $this->getCounterValue($this->charCountKey);
		return is_numeric($value) ? (int)$value : 0;
	}

	// Adds $charCount to the stored count in one statement and returns the new count.
	public function addCharCount($charCount)
	{
		$charCount = (int)$charCount;
		$this->ensureCounterRow($this->charCountKey, '0');
		if ($charCount > 0)
		{
			$this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = CASE WHEN `value` REGEXP '^[0-9]+$' THEN CAST(`value` AS UNSIGNED) + " . $charCount . " ELSE " . $charCount . " END WHERE `store_id` = '0' AND `key` = '" . $this->db->escape($this->charCountKey) . "'");
		}
		$newCharCount = $this->getCharCount();
		$this->config->set($this->charCountKey, $newCharCount);
		return $newCharCount;
	}

	public function resetCharCount($month)
	{
		$this->ensureCounterRow($this->charCountKey, '0');
		$this->ensureCounterRow($this->resetMonthKey, $month);
		$this->setCounterValue($this->charCountKey, '0');
		$this->setCounterValue($this->resetMonthKey, $month);
	}

	// Resets the count if the stored month is not $month.
	// One statement changes the month row and the count row, so only one request does the reset.
	// A missing month row is created with $month, so a first use does not reset the count.
	public function resetCharCountOnNewMonth($month)
	{
		$this->ensureCounterRow($this->charCountKey, '0');
		$this->ensureCounterRow($this->resetMonthKey, $month);
		$this->db->query("UPDATE `" . DB_PREFIX . "setting` `m` JOIN `" . DB_PREFIX . "setting` `c` ON `c`.`store_id` = '0' AND `c`.`key` = '" . $this->db->escape($this->charCountKey) . "' SET `m`.`value` = '" . $this->db->escape($month) . "', `c`.`value` = '0' WHERE `m`.`store_id` = '0' AND `m`.`key` = '" . $this->db->escape($this->resetMonthKey) . "' AND `m`.`value` <> '" . $this->db->escape($month) . "'");
		if ($this->db->countAffected() > 0)
		{
			$this->config->set($this->resetMonthKey, $month);
			$this->config->set($this->charCountKey, 0);
		}
	}

	public function deleteCounterSettings()
	{
		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `key` IN ('" . $this->db->escape($this->charCountKey) . "', '" . $this->db->escape($this->resetMonthKey) . "')");
	}

	private $_library = null;

	private function getLibrary()
	{
		if (is_null($this->_library))
			$this->_library = new LibraryClientTranslateExpert($this, $this->config->get('client_translate_expert_debug_status'));
		return $this->_library;
	}
	
	private $_localiationLibrary = null;
	
	private function getLocaliationLibrary()
	{
		if (is_null($this->_localiationLibrary))
			$this->_localiationLibrary = new LocalizationLibraryClientTranslateExpert($this, $this->config->get('client_translate_expert_debug_status'));
		return $this->_localiationLibrary;
	}
}
