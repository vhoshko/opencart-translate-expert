<?php

class LibraryClientTranslateExpertCore
{
	const TRANSLATE_EXPERT_SAME_TRANSLATIONS = "translate_expert_same_translations";

	const TRANSLATION_ANALITIC_SQL_LIMIT = 100;

	protected $_debug = true;
	protected $_debugLog = null;
	protected $_model = null;
	protected $_modulePrefix = null;
	protected $_languages = null;
	protected $_keyParamName = null;
	protected $_charCountKey = 'client_translate_expert_char_count';
	protected $_resetMonthKey = 'client_translate_expert_last_reset_char_month';
	protected $_counterGroup = 'client_translate_expert_usage';
	protected $_settingGroupColumn = null;

	public function __construct($model, $debug = true)
	{
		$this->_model = $model;
		$this->_modulePrefix = 'extension/module';
		if (version_compare(VERSION, '2.3', '<'))
		{
			$this->_modulePrefix = 'module';
		}
		if (version_compare(VERSION, '4.0', '>='))
		{
			$this->_modulePrefix = 'extension/client_translate_expert/module';
		}
		$this->_keyParamName = 'client_translate_expert_key';
		if (version_compare(VERSION, '3.0', '>='))
		{
			$this->_keyParamName = 'module_client_translate_expert_key';
			$this->_charCountKey = 'module_client_translate_expert_char_count';
			$this->_resetMonthKey = 'module_client_translate_expert_last_reset_char_month';
		}

		$this->_debug = $debug;
	}

	// translation


	public function analizeTableDetail($languageIdFrom, $mode, $tableName, $textColumnNameParam, $langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id)
	{
		$this->gebugLog('analizeTableDetail: ============START============', $startTime);

		if (is_null($mode))
			$mode = 'both';

		$this->loadLanguage($this->_modulePrefix . '/client_translate_expert');

		$languageFrom = $this->buildLanguageObject((int)$languageIdFrom);
		$languageTo = $this->buildLanguageObject((int)$langIdToParam);

		$tableResult = null;
		if (!is_null($languageFrom) && !is_null($languageTo) && in_array($tableName, $this->getTables(), true))
			$tableResult = $this->getTableToTranslateAnalizationInfo($languageIdFrom, $tableName, $this->getLanguages(), $mode, $langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id, false);

		if (is_null($tableResult)
			|| !$tableResult->tableTranslationIsSuppported
			|| !in_array($textColumnNameParam, $tableResult->textColumnNames, true))
		{
			return (object)array(
				'success' => 0,
				'message' => $this->_model->language->get('te_site_error_bad_detail_request'),
			);
		}

		$limit = self::TRANSLATION_ANALITIC_SQL_LIMIT;
		$textToTranslateRows = $this->getTextToTranslateRows($languageIdFrom, $tableResult->table, $tableResult->pkColumnNames, $textColumnNameParam,
			$mode, $limit + 1, 0, $langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id);

		$hasMore = 0;
		if (count($textToTranslateRows) > $limit)
		{
			$hasMore = 1;
			$textToTranslateRows = array_slice($textToTranslateRows, 0, $limit);
		}

		$idColumns = array_values($tableResult->pkColumnNames);
		$rows = array();
		foreach($textToTranslateRows as $textToTranslateRow)
		{
			$ids = array();
			foreach($idColumns as $idColumn)
			{
				$value = isset($textToTranslateRow[$idColumn]) ? $textToTranslateRow[$idColumn] : '';
				$ids[$idColumn] = (is_string($value) && preg_match('/^[0-9]+$/', $value)) ? (int)$value : $value;
			}
			$rows[] = array(
				'ids' => $ids,
				'textFrom' => html_entity_decode((string)$textToTranslateRow['text_from'], ENT_QUOTES, 'UTF-8'),
				'textTo' => html_entity_decode((string)(isset($textToTranslateRow['text_to']) ? $textToTranslateRow['text_to'] : ''), ENT_QUOTES, 'UTF-8'),
				'count' => (int)$textToTranslateRow['value_count'],
			);
		}

		$this->gebugLog('analizeTableDetail: ============FINISH============', $startTime);
		return (object)array(
			'success' => 1,
			'table' => $tableName,
			'column' => $textColumnNameParam,
			'mode' => $mode,
			'languageFrom' => $languageFrom,
			'languageTo' => $languageTo,
			'idColumns' => $idColumns,
			'rows' => $rows,
			'hasMore' => $hasMore,
		);
	}

	public function analize($languageIdFrom, $languageIdTo, $mode = null, $filterTable = '', $product_status = false, $product_quantity = false, $product_category_id = null, $stock_status = null, $manufacturer_id = null,
		$continueAfterTable = null, $continueAfterColumn = null, $continueOffset = 0)
	{
		$this->gebugLog('analize: ============START============', $startTime);

		$analizeInfo = $this->getTablesToTranslateAnalizationInfo($languageIdFrom, $languageIdTo, $mode, $filterTable, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id, $continueAfterTable, $continueAfterColumn, $continueOffset);

		if (empty($analizeInfo->success))
		{
			return (object)array(
				'success' => 0,
				'message' => isset($analizeInfo->message) ? $analizeInfo->message : '',
			);
		}

		$tables = array();
		foreach($analizeInfo->tablesResult as $tableResult)
		{
			$tableCharCount = (int)$tableResult->tableCharCount;
			foreach($tableResult->tableLanguageCharCounts as $langIdTo => $value)
			{
				if ($languageIdTo == $langIdTo)
					$tableCharCount = (int)$value['char_count'];
			}

			$columns = array();
			foreach($tableResult->textColumnNames as $textColumnName)
			{
				if (isset($tableResult->tableFieldCharCounts[$textColumnName])
					&& $tableResult->tableFieldCharCounts[$textColumnName] > 0)
				{
					$columnCharCount = (int)$tableResult->tableFieldCharCounts[$textColumnName];
					foreach($tableResult->tableFieldLanguageCharCounts[$textColumnName] as $langIdTo => $value)
					{
						if ($languageIdTo == $langIdTo)
							$columnCharCount = (int)$value['char_count'];
					}
					$columns[] = array('column' => $textColumnName, 'charCount' => $columnCharCount);
				}
			}

			$tables[] = array(
				'table' => $tableResult->table,
				'supported' => $tableResult->tableTranslationIsSuppported ? 1 : 0,
				'charCount' => $tableCharCount,
				'columns' => $columns,
			);
		}

		$this->gebugLog('analize: ============FINISH============', $startTime);

		return (object)array(
			'success' => 1,
			'done' => $analizeInfo->done ? 1 : 0,
			'target' => $this->buildLanguageObject((int)$languageIdTo),
			'globalCharCount' => (int)$analizeInfo->globalCharCount,
			'timeInSec' => (float)$analizeInfo->timeInSec,
			'tables' => $tables,
			'lastProcessedTable' => $analizeInfo->lastProcessedTable,
			'lastProcessedColumn' => $analizeInfo->lastProcessedColumn,
			'lastProcessedOffset' => (int)$analizeInfo->lastProcessedOffset,
		);
	}

	public function removeDataFromDb($languageIdTo, $filterTable = '', $product_status = false, $product_quantity = false, $product_category_id = null, $stock_status = null, $manufacturer_id = null)
	{
		$this->loadLanguage($this->_modulePrefix . '/client_translate_expert');
		$languages = $this->getLanguages();

		$targetLanguage = null;
		if (is_int($languageIdTo) && $languageIdTo > 0)
		{
			foreach ($languages as $language)
			{
				if ((int)$language['language_id'] === $languageIdTo)
				{
					$targetLanguage = $language;
					break;
				}
			}
		}

		if (is_null($targetLanguage))
		{
			return (object)array(
				'success' => 0,
				'message' => $this->_model->language->get('error_language_to_required'),
			);
		}

		$this->gebugLog('removeDataFromDb: ============START============', $startTime);

		$start = microtime(true);

		$languageIdFrom = null;
		$mode = 'both';

		$tables = $this->selectTables($this->normalizeList($filterTable));

		$steps = array(array('type' => 'start'));

		foreach($tables as $table)
		{
			$tableResult = $this->getTableToTranslateAnalizationInfo($languageIdFrom, $table, $languages, $mode, $languageIdTo, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id, false);

			if ($tableResult->tableTranslationIsSuppported)
			{
				$tableStepIndex = count($steps);
				$steps[] = array('type' => 'table', 'table' => $tableResult->table, 'clearedRows' => 0);
				$tableClearedRows = 0;
				foreach($tableResult->textColumnNames as $textColumnName)
				{
					$clearedRows = (int)$this->removeDataFromTable($languageIdTo, $tableResult->table, $textColumnName, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id);
					$tableClearedRows += $clearedRows;
					$steps[] = array('type' => 'column', 'table' => $tableResult->table, 'column' => $textColumnName, 'clearedRows' => $clearedRows);
				}
				$steps[$tableStepIndex]['clearedRows'] = $tableClearedRows;
			}
		}
		$steps[] = array('type' => 'finish');

		$timeInSec = round(microtime(true) - $start, 4);

		$this->gebugLog('removeDataFromDb: ============FINISH============', $startTime);

		return (object)array(
			'success' => 1,
			'target' => $this->buildLanguageObject($languageIdTo),
			'steps' => $steps,
			'timeInSec' => $timeInSec,
		);
	}

	protected function insertSameTranslatedText($languageIdFrom, $languageIdTo, $text)
	{
		$this->gebugLog('insertSameTranslatedText: ============START============', $startTime);

		$tableName = DB_PREFIX . self::TRANSLATE_EXPERT_SAME_TRANSLATIONS;
		$sql = "
INSERT IGNORE INTO {$tableName} (language_id_from, language_id_to, text_hash_sum)
SELECT l.language_id_from, l.language_id_to, t.text_hash_sum
FROM (SELECT unhex(MD5(REPLACE(REPLACE(REPLACE(REPLACE(LOWER('{$this->_model->db->escape($text)}'), ' ', ''), '\t', ''), '\n', ''), '\r', ''))) text_hash_sum) t
INNER JOIN (
	SELECT 	'{$languageIdFrom}' language_id_from, '{$languageIdTo}' language_id_to
	UNION ALL
	SELECT 	'{$languageIdTo}' language_id_from, '{$languageIdFrom}' language_id_to
) l ON 1 = 1
		";

		$this->gebugLog($sql);

		$this->_model->db->query($sql);

		$this->gebugLog('insertSameTranslatedText: ============FINISH============', $startTime);
	}

	protected function insertTranslatedTableTextValue($tableResult, $mode, $textColumnName, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id, $textToTranslateRow, $translatedText)
	{
		$this->gebugLog('insertTranslatedTableTextValue: ============START============', $startTime);

		$translatedText = $this->_model->db->escape($translatedText);

		if ($textToTranslateRow['value_count'] == 1)
		{
			$rowsToTranslate = array($textToTranslateRow);
		}
		else
		{
			$rowsToTranslate = $this->getTextToTranslateRows($textToTranslateRow['language_id_from'], $tableResult->table, $tableResult->pkColumnNames, $textColumnName, $mode, 100500, 0,
				$textToTranslateRow['language_id_to'], $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id, $this->_model->db->escape($textToTranslateRow['text_from']));
		}

		foreach ($rowsToTranslate as $textToTranslateRow)
		{
			$insertFields = array('language_id', $textColumnName);
			$selectFields = array("'" . $textToTranslateRow['language_id_to'] . "'", "'" . $translatedText . "'");
			$conditions = array("language_id = '{$textToTranslateRow['language_id_from']}'");
			$conditionsForUpdate = array("language_id = '{$textToTranslateRow['language_id_to']}'");

			foreach($tableResult->columnNames as $column)
			{
				if ($column != 'language_id'
					&& $column != $textColumnName
					&& array_search($column, $tableResult->pkRealToIgnoreColumnNames) === false
				){
					$insertFields[] = '`' . $column . '`';
					$selectFields[] = '`' . $column . '`';
				}
			}

			$pks = (count($tableResult->pkRealToIgnoreColumnNames) > 0) ? $tableResult->pkRealToIgnoreColumnNames : $tableResult->pkColumnNames;
			foreach($pks as $column)
			{
				if ($column != 'language_id' && $column != $textColumnName)
				{
					$conditions[] = "{$column} = '{$textToTranslateRow[$column]}'";
					$conditionsForUpdate[] = "{$column} = '{$textToTranslateRow[$column]}'";
				}
			}

			$insertFieldsStr = implode(", ", $insertFields);
			$selectFieldsStr = implode(", ", $selectFields);
			$conditionsStr = implode(" AND ", $conditions);
			$conditionsForUpdateStr = implode(" AND ", $conditionsForUpdate);

			$sql = "
	SELECT 'exists'
	FROM `{$tableResult->table}`
	WHERE {$conditionsForUpdateStr}
			";
			$this->gebugLog($sql);
			$rows = $this->_model->db->query($sql)->rows;
			$exists = count($rows) > 0;
			if ($exists)
			{
				$sql = "
	UPDATE {$tableResult->table}
	SET {$textColumnName} = '{$translatedText}'
	WHERE {$conditionsForUpdateStr}
				";
				$this->gebugLog($sql);
				$this->_model->db->query($sql);
			}
			else
			{
				$sql = "
	INSERT INTO {$tableResult->table} ({$insertFieldsStr})
	SELECT {$selectFieldsStr} FROM `{$tableResult->table}` WHERE {$conditionsStr} LIMIT 1
				";
				$this->gebugLog($sql);
				$this->_model->db->query($sql);
			}
		}

		$this->gebugLog('insertTranslatedTableTextValue: ============FINISH============', $startTime);
	}

	protected function normalizeList($value)
	{
		if (is_array($value))
			return $value;
		return $value === '' || $value === null ? array() : array($value);
	}

	protected function normalizeIdList($value)
	{
		$ids = array();
		foreach($this->normalizeList($value) as $item)
		{
			$id = (int)$item;
			if ($id > 0)
				$ids[] = $id;
		}
		return array_values(array_unique($ids));
	}

	protected function selectTables($filterTables)
	{
		$filterTables = $this->normalizeList($filterTables);
		if (count($filterTables) == 0)
			return $this->getTables();
		return array_values(array_intersect($this->getTables(), $filterTables));
	}

	protected function initLanguageCharCountsArray($languages) {
		$res = array();
		foreach($languages as $code => $language)
			$res[$language['language_id']] = array('code' => $code, 'image' => isset($language['image']) ? $language['image'] : $code . '.png', 'char_count' => 0);
		return $res;
	}

	protected function getColumns($table) {
		$this->gebugLog('getColumns: ============START============', $startTime);

		//$row = $this->_model->db->query("SELECT * FROM " . $table . " LIMIT 1")->row;
		$columns = $this->_model->db->query("
			SELECT column_name as 'column_name', data_type as 'data_type', column_key as 'column_key', extra as 'extra'
			from INFORMATION_SCHEMA.COLUMNS
			where table_schema = '" . DB_DATABASE . "'
				and table_name = '" . $table . "'
				and column_name <> 'image'
				and column_name <> 'link'
				and column_name <> 'query'
				and column_name <> 'url'
		")->rows;
		$this->gebugLog(var_export($columns, true));
        foreach($columns as &$column)
		{
			$column = array_change_key_case($column);
		}

		$this->gebugLog('getColumns: ============FINISH============', $startTime);
		return $columns;
	}

	public function getTables() {
		$this->gebugLog('getTables: ============START============', $startTime);

		$tableRows = $this->_model->db->query("
			select c.table_name as 'table_name'
			from INFORMATION_SCHEMA.COLUMNS c
			where c.table_schema = '" . DB_DATABASE . "'
				and c.column_name = 'language_id'
				and c.table_name like '" . DB_PREFIX . "%'
				and not c.table_name like '%-%'
				and not c.table_name like '%+%'
			order by c.table_name
		")->rows;
		$this->gebugLog(var_export($tableRows, true));
		$tables = array();
		$ignore = array(DB_PREFIX.'language', DB_PREFIX.'customer', DB_PREFIX.'customer_search', DB_PREFIX.'order', DB_PREFIX.'translation', DB_PREFIX.'seo_url');
        foreach($tableRows as $tableRow)
		{
			$tableRow = array_change_key_case($tableRow);
			if (strpos($tableRow['table_name'], 'url') !== false)
				continue;
			if (array_search($tableRow['table_name'], $ignore) === false)
				$tables[] = $tableRow['table_name'];
		}

		$this->gebugLog('getTables: ============FINISH============', $startTime);
		return $tables;
	}

	public function tableHasFIeld($table, $field)
	{
		$this->gebugLog('tableHasFIeld: ============START============', $startTime);

		//$row = $this->_model->db->query("SELECT * FROM " . $table . " LIMIT 1")->row;
		$columns = $this->_model->db->query("
			SELECT 1
			from INFORMATION_SCHEMA.COLUMNS
			where table_schema = '" . DB_DATABASE . "'
				and table_name = '" . $table . "'
				and column_name = '" . $field . "'
		")->rows;

		$this->gebugLog('tableHasFIeld: ============FINISH============', $startTime);
		return count($columns) > 0;
	}
	// translation

	// REGION COMMON
	protected function translateInternal($googleApiKey, $text, $source, $target, $format) {

		$this->gebugLog('translateInternal: ============START============', $startTime);

		if (defined('DIR_EXTENSION') && version_compare(VERSION, '4.0', '>=')) {
			include_once(DIR_EXTENSION . 'client_translate_expert/system/library/vh_google_translator.php');
		} else {
			include_once(DIR_SYSTEM . 'library/vh_google_translator.php');
		}

		$this->gebugLog('translateInternal: vh_google_translator');

		if (!$googleApiKey)
		{
			$res = (object)array('success' => 0, 'error_code' => 1004, 'message' => 'Google Translate API key is empty.');
			$this->gebugLog('translateInternal googleApiKey is empty: ============END============', $startTime);
			return $res;
		}

		try {
			//var_dump($text); print '<br>';
			$this->gebugLog('translateInternal VHGoogleTranslator creation before');
			$translator = new VHGoogleTranslator( $googleApiKey );
			$this->gebugLog('translateInternal VHGoogleTranslator creation after');
			$res = is_array($text)
				? $translator->translateBatch($text, $source, $target, $format)
				: $translator->translate($text, $source, $target, $format);
			$this->gebugLog(var_export($res, true));
			//var_dump($res); print '<br>';
		}
		catch (\Google\Cloud\Core\Exception\BadRequestException $exception) {
			//var_dump($exception);
			$this->gebugLog('Google\Cloud\Core\Exception\BadRequestException', $startTime);
			$errorMessage = $exception->getMessage();
			$this->gebugLog(var_export($errorMessage, true));
			$error = json_decode($errorMessage)->error;
			$res = (object)array('success' => 0, 'error_code' => 1000, 'message' => 'Internal error.', 'additional_message' => '(' . $error->code . ') ' . $error->message);
			$this->gebugLog(var_export($res, true));
			$this->gebugLog('translateInternal: ============END============', $startTime);
			return $res;
		}
		catch(\Exception $exception)
		{
			$error = $exception->getMessage();
			$res = (object)array('success' => 0, 'error_code' => 1000, 'message' => 'Internal error.', 'additional_message' => $error);
			$this->gebugLog(var_export($res, true));
			$this->gebugLog('translateInternal: ============END============', $startTime);
			return $res;
		}
		catch(\Throwable $exception)
		{
			$error = $exception->getMessage();
			$res = (object)array('success' => 0, 'error_code' => 1000, 'message' => 'Internal error.', 'additional_message' => $error);
			$this->gebugLog(var_export($res, true));
			$this->gebugLog('translateInternal: ============END============', $startTime);
			return $res;
		}

		$this->gebugLog(var_export($res, true));
		$this->gebugLog('translateInternal: ============END============', $startTime);
		return $res;
	}
	// REGION COMMON


	// LOCALIZATION
	protected function getDirContents($dir, $pathSuffix, &$relativePath = '', &$results = array()) {
		$files = scandir($dir);

		foreach ($files as $key => $value)
		{
			$path = realpath($dir . '/' . $value);
			$relPath = $relativePath . '/' . $value;
			//var_dump($relPath);
			if (!is_dir($path))
			{
				if ($this->endsWith($path, $pathSuffix))
				{
					$results[$relPath] = file_get_contents($path);
				}
			}
			else
			if ($value != "." && $value != "..")
			{
				$this->getDirContents($path, $pathSuffix, $relPath, $results);
			}
		}

		return $results;
	}
	// LOCALIZATION









	// common functions
	protected function trimValue($text)
	{
		if (is_array($text))
		{
			$newArr = array();
			foreach($text as $t)
			{
				$t = trim($t);
				if (($t != '') && !is_null($t)) {
					$newArr[] = $t;
				}
			}
			$text = $newArr;
		}
		else
		{
			$text = trim($text);
		}

		return $text;
	}

	function startsWith( $haystack, $needle ) {
		 $length = strlen( $needle );
		 return substr( $haystack, 0, $length ) === $needle;
	}

	function endsWith( $haystack, $needle ) {
		$length = strlen( $needle );
		if( !$length ) {
			return true;
		}
		return substr( $haystack, -$length ) === $needle;
	}

	protected function getRandomString($length = 8) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$string = '';

		for ($i = 0; $i < $length; $i++) {
			$string .= $characters[mt_rand(0, strlen($characters) - 1)];
		}

		return $string;
	}

	protected function arrayContainsHtmlCode($array)
	{
		return $this->arrayContainsSubstr('</', $array)
			|| $this->arrayContainsSubstr('&lt;', $array)
			|| $this->arrayContainsSubstr('&amp;', $array)
			|| $this->arrayContainsSubstr('&quot;', $array)
			|| $this->arrayContainsSubstr('&#39;', $array);
	}

	protected function arrayContainsSubstr($substr, array $arr)
	{
		foreach($arr as $a) {
			if (mb_strpos($a, $substr, 0, "UTF-8") !== false)
				return true;
		}
		return false;
	}

	// Characters of a DB text as Google gets them: decodeHtml() and the trim() of trimValue() run before the send.
	// The analysis, the batch size and the character counter all use this length.
	protected function getBillableCharCount($text)
	{
		$decoded = $this->decodeHtml(array((string)$text));
		return mb_strlen(trim($decoded[0]), 'UTF-8');
	}

	// The PHP form of the SQL "same value" rule in getTextToTranslateRows(): LOWER() and no ' ', '\t', '\n', '\r'.
	protected function normalizeForSameCheck($text)
	{
		return mb_strtolower(str_replace(array(' ', "\t", "\n", "\r"), '', (string)$text), 'UTF-8');
	}

	protected function decodeHtml($texts)
	{
		foreach($texts as &$text)
		{
			if (mb_strpos($text, '&lt;', 0, "UTF-8") !== false
				|| mb_strpos($text, '&amp;', 0, "UTF-8") !== false
				|| mb_strpos($text, '&quot;', 0, "UTF-8") !== false
				|| mb_strpos($text, '&#39;', 0, "UTF-8") !== false)
			{
				$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
			}
		}
		return $texts;
	}

	protected function encodeHtml($texts, $originals)
	{
		$this->gebugLog('encodeHtml: ============START============', $startTime);
		$this->gebugLog(var_export($texts, true));
		foreach($originals as $key => &$original)
		{
			if (mb_strpos($original, '&lt;', 0, "UTF-8") !== false
				|| mb_strpos($original, '&amp;', 0, "UTF-8") !== false
				|| mb_strpos($original, '&quot;', 0, "UTF-8") !== false
				|| mb_strpos($original, '&#39;', 0, "UTF-8") !== false)
			{
				$texts[$key] = htmlentities($texts[$key], ENT_QUOTES, 'UTF-8');
			}
		}
		$this->gebugLog(var_export($texts, true));
		$this->gebugLog('encodeHtml: ============FINISH============', $startTime);
		return $texts;
	}

	protected function loadLanguage($module)
	{
		if (version_compare(VERSION, '2.1', '='))
			return $this->_model->language->load($module);
		else
			return $this->_model->load->language($module);
	}

	protected function getLangImgUrl($langCode)
	{
		if (version_compare(VERSION, '4.0', '>='))
		{
			foreach($this->getLanguages() as $language)
			{
				if ($language['code'] == $langCode && isset($language['image']))
					return $language['image'];
			}
			return '';
		}
		if (version_compare(VERSION, '2.2', '<'))
			return 'view/image/flags/' . $this->getLangImageName($langCode);
		else
			return "language/{$langCode}/{$langCode}.png";
	}

	public function buildLanguageObject($languageId)
	{
		foreach($this->getLanguages() as $language)
		{
			if ((int)$language['language_id'] === (int)$languageId)
			{
				return array(
					'language_id' => (int)$language['language_id'],
					'code' => $language['code'],
					'name' => html_entity_decode($language['name'], ENT_QUOTES, 'UTF-8'),
					'image' => $this->getLangImgUrl($language['code']),
				);
			}
		}
		return null;
	}

	protected function getLangImageName($langCode)
	{
		foreach($this->getLanguages() as $key => $language)
		{
			if ($language['code'] == $langCode)
			{
				if (isset($language['image']))
					return $language['image'];
			}
		}

		return $langCode;
	}

	protected function getLanguageFolder($language)
	{
		if (version_compare(VERSION, '2.2', '<'))
			return DIR_CATALOG . 'language/' . $language['directory'] . '/';

		if (version_compare(VERSION, '4.0', '>=') && !empty($language['extension']))
			return DIR_EXTENSION . $language['extension'] . '/catalog/language/' . $language['code'] . '/';

		return DIR_CATALOG . 'language/' . $language['code'] . '/';
	}

	// The admin runs this code, so DIR_APPLICATION is the admin folder.
	protected function getAdminLanguageFolder($language)
	{
		if (version_compare(VERSION, '2.2', '<'))
			return DIR_APPLICATION . 'language/' . $language['directory'] . '/';

		if (version_compare(VERSION, '4.0', '>=') && !empty($language['extension']))
			return DIR_EXTENSION . $language['extension'] . '/admin/language/' . $language['code'] . '/';

		return DIR_APPLICATION . 'language/' . $language['code'] . '/';
	}

	protected function getLanguages()
	{
		if (is_null($this->_languages))
		{
			$this->_model->load->model('localisation/language');
			$this->_languages = $this->_model->model_localisation_language->getLanguages();
		}
		return $this->_languages;
	}

	public function gebugLog($message, &$startTime = null)
	{
		if (!$this->_debug)
			return;

		if (is_null($this->_debugLog))
			$this->_debugLog = new Log('client_translate_expert.log');

		$executionTimeStr = '';
		$currentTime = microtime(true);
		if (is_null($startTime) || !isset($startTime))
			$startTime = $currentTime;
		else
		{
			$secs = $currentTime - $startTime;
			$executionTimeStr = ' (' . number_format($secs, 3, '.', "`") . ' sec)';
		}

		$currentDateTime = \DateTime::createFromFormat('U.u', $currentTime);
		if (is_bool($currentDateTime)) $currentDateTime = \DateTime::createFromFormat('U.u', $currentTime += 0.001);
		$this->_debugLog->write($currentDateTime->format("Y-m-d H:i:s.v") . $executionTimeStr . PHP_EOL
			. $message . PHP_EOL);
	}
	// common functions

	// Monthly character counter.
	// The counter rows live in their own setting group, so that editSetting() for the module
	// settings never deletes them. The queries find the rows by store_id and key only,
	// so rows that are still in the old group work too.
	// Each write is a single SQL statement, so concurrent requests do not lose increments.

	// Old OpenCart versions name the group column `group`, newer versions name it `code`.
	private function settingGroupColumn()
	{
		if (is_null($this->_settingGroupColumn))
		{
			$query = $this->_model->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "setting` LIKE 'code'");
			$this->_settingGroupColumn = $query->num_rows ? 'code' : 'group';
		}
		return $this->_settingGroupColumn;
	}

	private function getCounterValue($key)
	{
		$query = $this->_model->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `key` = '" . $this->_model->db->escape($key) . "' ORDER BY `setting_id` ASC LIMIT 1");
		return $query->num_rows ? $query->row['value'] : null;
	}

	private function ensureCounterRow($key, $default)
	{
		$db = $this->_model->db;
		if (is_null($this->getCounterValue($key)))
		{
			$db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = '0', `" . $this->settingGroupColumn() . "` = '" . $db->escape($this->_counterGroup) . "', `key` = '" . $db->escape($key) . "', `value` = '" . $db->escape($default) . "', `serialized` = '0'");
			// Two requests can both find no row and both insert one. Keep the oldest row only.
			// All updates change every row of the key, so the oldest row has all increments.
			$db->query("DELETE `s` FROM `" . DB_PREFIX . "setting` `s` JOIN (SELECT MIN(`setting_id`) AS `keep_id` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `key` = '" . $db->escape($key) . "') `k` WHERE `s`.`store_id` = '0' AND `s`.`key` = '" . $db->escape($key) . "' AND `s`.`setting_id` > `k`.`keep_id`");
		}
	}

	private function setCounterValue($key, $value)
	{
		$this->_model->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '" . $this->_model->db->escape($value) . "' WHERE `store_id` = '0' AND `key` = '" . $this->_model->db->escape($key) . "'");
		$this->_model->config->set($key, $value);
	}

	// Moves the counter rows out of the module settings group and creates missing rows.
	// Call it before editSetting() for the module settings.
	public function prepareCounterSettings()
	{
		$db = $this->_model->db;
		$db->query("UPDATE `" . DB_PREFIX . "setting` SET `" . $this->settingGroupColumn() . "` = '" . $db->escape($this->_counterGroup) . "' WHERE `store_id` = '0' AND `key` IN ('" . $db->escape($this->_charCountKey) . "', '" . $db->escape($this->_resetMonthKey) . "')");
		$this->ensureCounterRow($this->_charCountKey, '0');
		$this->ensureCounterRow($this->_resetMonthKey, date('m'));
	}

	public function getCharCount()
	{
		$value = $this->getCounterValue($this->_charCountKey);
		return is_numeric($value) ? (int)$value : 0;
	}

	// Adds $charCount to the stored count in one statement and returns the new count.
	public function addCharCount($charCount)
	{
		$charCount = (int)$charCount;
		$this->ensureCounterRow($this->_charCountKey, '0');
		if ($charCount > 0)
		{
			$this->_model->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = CASE WHEN `value` REGEXP '^[0-9]+$' THEN CAST(`value` AS UNSIGNED) + " . $charCount . " ELSE " . $charCount . " END WHERE `store_id` = '0' AND `key` = '" . $this->_model->db->escape($this->_charCountKey) . "'");
		}
		$newCharCount = $this->getCharCount();
		$this->_model->config->set($this->_charCountKey, $newCharCount);
		return $newCharCount;
	}

	public function resetCharCount($month)
	{
		$this->ensureCounterRow($this->_charCountKey, '0');
		$this->ensureCounterRow($this->_resetMonthKey, $month);
		$this->setCounterValue($this->_charCountKey, '0');
		$this->setCounterValue($this->_resetMonthKey, $month);
	}

	// Resets the count if the stored month is not $month.
	// One statement changes the month row and the count row, so only one request does the reset.
	// A missing month row is created with $month, so a first use does not reset the count.
	public function resetCharCountOnNewMonth($month)
	{
		$db = $this->_model->db;
		$this->ensureCounterRow($this->_charCountKey, '0');
		$this->ensureCounterRow($this->_resetMonthKey, $month);
		$db->query("UPDATE `" . DB_PREFIX . "setting` `m` JOIN `" . DB_PREFIX . "setting` `c` ON `c`.`store_id` = '0' AND `c`.`key` = '" . $db->escape($this->_charCountKey) . "' SET `m`.`value` = '" . $db->escape($month) . "', `c`.`value` = '0' WHERE `m`.`store_id` = '0' AND `m`.`key` = '" . $db->escape($this->_resetMonthKey) . "' AND `m`.`value` <> '" . $db->escape($month) . "'");
		if ($db->countAffected() > 0)
		{
			$this->_model->config->set($this->_resetMonthKey, $month);
			$this->_model->config->set($this->_charCountKey, 0);
		}
	}

	public function deleteCounterSettings()
	{
		$db = $this->_model->db;
		$db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `key` IN ('" . $db->escape($this->_charCountKey) . "', '" . $db->escape($this->_resetMonthKey) . "')");
	}

	// installation
	public function install()
	{
		$sql = "
		CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "translate_expert_same_translations` (
		  `language_id_from` int(11) NOT NULL,
		  `language_id_to` int(11) NOT NULL,
		  `text_hash_sum` binary(16) NOT NULL,
		  PRIMARY KEY(`language_id_from`,`language_id_to`,`text_hash_sum`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT
		";
		return $this->_model->db->query($sql);
	}

	public function uninstall()
	{
		$sql = "DROP TABLE IF EXISTS `" . DB_PREFIX . "translate_expert_same_translations`";
		return $this->_model->db->query($sql);
	}
	// installation
}
