<?php

if (defined('DIR_EXTENSION') && version_compare(VERSION, '4.0', '>=')) {
	include_once(DIR_EXTENSION . 'client_translate_expert/system/library/client_translate_expert_core.php');
} else {
	include_once(DIR_SYSTEM . 'library/client_translate_expert_core.php');
}

class LibraryClientTranslateExpert extends LibraryClientTranslateExpertCore
{
	public function translate($text, $source, $target, $format)
	{
		$this->gebugLog('translate: ============START============', $startTime);

		$text = $this->trimValue($text);
		$this->gebugLog('$text: ' . var_export($text, true));
		$source = explode('-', $source)[0];
		$this->gebugLog('$source: ' . var_export($source, true));
		$target = explode('-', $target)[0];
		$this->gebugLog('$target: ' . var_export($target, true));
		$this->gebugLog('$format: ' . var_export($format, true));

		$res = $this->translateInternal($this->_model->config->get($this->_keyParamName), $text, $source, $target, $format);
		$this->gebugLog(var_export($res, true));
		if (isset($res->text))
		{
			$this->gebugLog('$res->text: ' . var_export($res->text, true));
		}
		if (isset($res->texts))
		{
			if (count($res->texts) > 0)
			{
				$this->gebugLog('$res->texts[0]: ' . var_export($res->texts[0], true));
			}
		}

		$this->gebugLog(var_export($res, true));
		$this->gebugLog('translate: ============FINISH============', $startTime);
		return $res;
	}

	public function translateTable($languageIdFrom, $mode, $tableName, $textColumnNameParam, $langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status)
	{
		$this->gebugLog('translateTable: ============START============', $startTime);

		if (is_null($mode))
			$mode = 'both';

		$languages = $this->getLanguages();

		$tableResult = $this->getTableToTranslateAnalizationInfo($languageIdFrom, $tableName, $languages, $mode, $langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status, false);

		$translatedCharCount = 0;
		$done = true;

		$start = microtime(true);
		if ($tableResult->tableTranslationIsSuppported)
		{
			foreach($tableResult->textColumnNames as $textColumnName)
			{
				if ($textColumnNameParam && ($textColumnNameParam != $textColumnName))
					continue;

				foreach($languages as $code => $language)
				{
					if ($langIdToParam && ($langIdToParam != $language['language_id']))
						continue;

					$textsToTranslateLenght = 0;
					$textRowsToTranslate = array();

					$limit = self::TRANSLATION_ANALITIC_SQL_LIMIT;
					$offset = 0;
					while (true)
					{
						$textToTranslateRows = $this->getTextToTranslateRows($languageIdFrom, $tableResult->table, $tableResult->pkColumnNames, $textColumnName, $mode, $limit, $offset, $langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status);
						if (count($textToTranslateRows) == 0)
							break;
						$offset += count($textToTranslateRows);

						foreach($textToTranslateRows as $textToTranslateRow)
						{
							if ($textToTranslateRow['language_id_to'] != $language['language_id'])
								continue;


							$textToTranslate = $textToTranslateRow['text_from'];

							if (($textsToTranslateLenght > 0)
								&& ($textsToTranslateLenght + mb_strlen($textToTranslate) > 5000))
							{
								$translateRes = $this->translateTableTexts($tableResult, $mode, $textColumnName, $product_status, $product_quantity, $product_category_id, $stock_status, $textRowsToTranslate);
								if (!$translateRes->success)
									return (object)array('success' => 0, 'done' => 0, 'message' => $translateRes->message, 'additional_message' => isset($translateRes->additional_message) ? $translateRes->additional_message : '');

								$offset = 0;
								$translatedCharCount += $textsToTranslateLenght;
								$textsToTranslateLenght = 0;
								$textRowsToTranslate = array();
							}

							$textsToTranslateLenght += mb_strlen($textToTranslate);
							$textRowsToTranslate[] = $textToTranslateRow;
						}

						if ($translatedCharCount > 0)
						{
							$time_elapsed_secs = microtime(true) - $start;
							if ($time_elapsed_secs > 10)
							{
								$done = false;
								break;
							}
						}
					}

					if ($textsToTranslateLenght > 0)
					{
						$translateRes = $this->translateTableTexts($tableResult, $mode, $textColumnName, $product_status, $product_quantity, $product_category_id, $stock_status, $textRowsToTranslate);
						if (!$translateRes->success)
							return (object)array('success' => 0, 'done' => 0, 'message' => $translateRes->message, 'additional_message' => isset($translateRes->additional_message) ? $translateRes->additional_message : '');
						$translatedCharCount += $textsToTranslateLenght;

						$time_elapsed_secs = microtime(true) - $start;
						if ($time_elapsed_secs > 10)
						{
							$done = false;
							break;
						}
					}

					if (!$done)
					{
						break;
					}
				}

				if (!$done)
				{
					break;
				}
			}
		}

		$this->gebugLog('translateTable: ============FINISH============', $startTime);
		return (object)array(
			'success' => 1,
			'done' => $done,
			'translatedCharCount' => $translatedCharCount,
		);
	}
















	protected function translateTableTexts($tableResult, $mode, $textColumnName, $product_status, $product_quantity, $product_category_id, $stock_status, $textRowsToTranslate)
	{
		//var_dump($textRowsToTranslate);

		$textsToTranslate = array();
		foreach($textRowsToTranslate as $textToTranslateRow)
		{
			$textsToTranslate[] = $textToTranslateRow['text_from'];
		}
		$textsToTranslateOriginal = $textsToTranslate;

		$shouldBeDecoded = $this->arrayContainsHtmlCode($textsToTranslate);
		if ($shouldBeDecoded)
		{
			$textsToTranslate = $this->decodeHtml($textsToTranslate);
		}
		$isCode = $this->arrayContainsSubstr('</', $textsToTranslate);

		$format = $isCode ?  'html' : 'text';
		$source = $textRowsToTranslate[0]['language_code_from'];
		$target = $textRowsToTranslate[0]['language_code_to'];

		$translationResults = $this->translate($textsToTranslate, $source, $target, $format);

		if (!$translationResults->success)
			return $translationResults;

		$c1 = count($translationResults->texts);
		$c2 = count($textRowsToTranslate);
		if ($c1 != $c2)
		{
			return (object)array('success' => 0, 'error_code' => 1000, 'message' => 'Internal error.', 'additional_message' => "count($translationResults->texts) != count($textRowsToTranslate), ${c1} != {$c2}");
		}

		if ($shouldBeDecoded)
		{
			$translationResults->texts = $this->encodeHtml($translationResults->texts, $textsToTranslateOriginal);
		}


		$c1 = count($translationResults->texts);
		$c2 = count($textRowsToTranslate);
		if ($c1 != $c2)
		{
			return (object)array('success' => 0, 'error_code' => 1000, 'message' => 'Internal error.', 'additional_message' => "count($translationResults->texts) != count($textRowsToTranslate), ${c1} != {$c2}");
		}

		for($i = 0; $i < count($textRowsToTranslate); $i++)
		{
			$textToTranslateRow = $textRowsToTranslate[$i];
			$textToTranslate = $textToTranslateRow['text_from'];
			$translatedText = $translationResults->texts[$i];
			if (trim(strtolower($textToTranslate)) == trim(strtolower($translatedText))
				|| isset($textToTranslateRow['text_to']) && trim(strtolower($textToTranslateRow['text_to'])) == trim(strtolower($translatedText))
			)
			{
				$this->insertSameTranslatedText($textToTranslateRow['language_id_from'], $textToTranslateRow['language_id_to'], $translatedText);
			}
			$this->insertTranslatedTableTextValue($tableResult, $mode, $textColumnName, $product_status, $product_quantity, $product_category_id, $stock_status, $textToTranslateRow, $translatedText);
		}

		return (object)array('success' => '1');
	}

	protected function getTableToTranslateAnalizationInfo($languageIdFrom, $table, $languages, $mode = null, $langIdToParam = null,
		$product_status = false, $product_quantity = false, $product_category_id = null, $stock_status = null,
		$calcCounts = true, $continueAfterColumn = null, $offset = 0)
	{
		$columns = $this->getColumns($table);

		$langColumn = false;
		foreach($columns as $column)
		{
			if ($column['column_name'] == 'language_id')
				$langColumn = $column;
		}

		$columnNames = array();
		$pkColumnNames = array();
		$pkRealColumnNames = array();
		$pkRealToIgnoreColumnNames = array();
		$textColumnNames = array();
		$columnInfos = array();
		foreach($columns as $column)
		{
			$columnIsText = ($column['data_type'] == 'varchar') || ($column['data_type'] == 'text') || (strpos($column['data_type'], 'text') !== false);
			$columnKey = is_null($column['column_key']) ? '' : $column['column_key'];
			if ($columnKey == 'PRI')
			{
				if (($column['extra'] == 'auto_increment') && false)
				{
					$pkRealToIgnoreColumnNames[] = $column['column_name'];
					continue;
				}
				else
					$pkRealColumnNames[] = $column['column_name'];
			}

			if (($columnKey != 'PRI') || (!is_null($langColumn) && ($columnKey == 'PRI')))
			{
				$columnNames[] = $column['column_name'];
				if (($columnKey == 'PRI') && ($column['column_name'] != 'language_id'))
					$pkColumnNames[] = $column['column_name'];
				if ($columnIsText)
					$textColumnNames[] = $column['column_name'];
				$priStr = $columnKey == 'PRI' ? '-PRI' : '';
				$aiStr = $column['extra'] == 'auto_increment' ? '-AI' : '';
				$columnInfos[] = $column['column_name'] . '(' . $column['data_type'] . $priStr . $aiStr . ')';
			}
		}

		if (count($pkColumnNames) == 0) // it is BUG! need to show warning about it! do not process it!
			$pkColumnNames = array_slice($columnNames, 0, array_search('language_id', $columnNames));

		$start = microtime(true);

		$tableCharCount = 0;
		$tableLanguageCharCounts = $this->initLanguageCharCountsArray($languages);
		$tableFieldCharCounts = array();
		$tableFieldLanguageCharCounts = array();
		$tableTranslationIsSuppported = (count($pkRealColumnNames) != 1) && (count($pkRealToIgnoreColumnNames) == 0);
		$lastProcessedColumn = $continueAfterColumn;
		$lastProcessedOffset = $offset;
		$isBreaked = false;

		if ((array_search('language_id', $columnNames) !== false) && (count($pkColumnNames) != 0)
			&& $tableTranslationIsSuppported)
		{
			foreach($textColumnNames as $textColumnName)
			{
				$tableFieldCharCounts[$textColumnName] = 0;
				$tableFieldLanguageCharCounts[$textColumnName] = $this->initLanguageCharCountsArray($languages);

				if (!$calcCounts)
					continue;

				if (!is_null($continueAfterColumn))
				{
					if ($continueAfterColumn == $textColumnName)
						$continueAfterColumn = null;
					continue;
				}

				$limit = self::TRANSLATION_ANALITIC_SQL_LIMIT;
				$nextExists = true;
				while ($nextExists)
				{
					$textToTranslateRows = $this->getTextToTranslateRows($languageIdFrom, $table, $pkColumnNames, $textColumnName, $mode, $limit + 1, $offset,
						$langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status);
					if (count($textToTranslateRows) == 0)
						break;

					$nextExists = count($textToTranslateRows) == $limit + 1;
					$textToTranslateRows = array_slice($textToTranslateRows, 0, $limit);

					$offset += count($textToTranslateRows);
					$lastProcessedOffset = $offset;

					foreach($textToTranslateRows as $textToTranslateRow)
					{
						//print '<br><br><br>'; var_dump($textToTranslateRow); print '<br><br><br>';

						$langIdTo = $textToTranslateRow['language_id_to'];
						$charCount = mb_strlen($textToTranslateRow['text_from']);

						$tableCharCount += $charCount;
						$tableLanguageCharCounts[$langIdTo]['char_count'] += $charCount;

						$tableFieldCharCounts[$textColumnName] += $charCount;
						$tableFieldLanguageCharCounts[$textColumnName][$langIdTo]['char_count'] += $charCount;
					}

					$time_elapsed_secs = microtime(true) - $start;
					if ($time_elapsed_secs > 1 && ($tableCharCount > 0))
					{
						$isBreaked = true;
						break;
					}

				}

				if ($isBreaked)
				{
					break;
				}

				$lastProcessedColumn = $textColumnName;
				$lastProcessedOffset = 0;
				$offset = 0;
			}
		}

		if (!$isBreaked)
		{
			$lastProcessedColumn = null;
			$lastProcessedOffset = 0;
		}

		return (object)array(
			'table' => $table,
			'columns' => $columns,
			'columnNames' => $columnNames,
			'pkColumnNames' => $pkColumnNames,
			'pkRealColumnNames' => $pkRealColumnNames,
			'pkRealToIgnoreColumnNames' => $pkRealToIgnoreColumnNames,
			'textColumnNames' => $textColumnNames,
			'columnInfos' => $columnInfos,
			'tableTranslationIsSuppported' => $tableTranslationIsSuppported,
			'done' => !$isBreaked,

			'tableCharCount' => $tableCharCount,
			'tableLanguageCharCounts' => $tableLanguageCharCounts,
			'tableFieldCharCounts' => $tableFieldCharCounts,
			'tableFieldLanguageCharCounts' => $tableFieldLanguageCharCounts,

			'lastProcessedColumn' => $lastProcessedColumn,
			'lastProcessedOffset' => $lastProcessedOffset,
		);
	}

	protected function getTablesToTranslateAnalizationInfo($languageIdFrom, $languageIdTo, $mode = null, $filterTable = '', $product_status = false, $product_quantity = false, $product_category_id = null, $stock_status = null,
		$continueAfterTable = null, $continueAfterColumn = null, $continueOffset = 0
	) {
		$start = microtime(true);

		if (is_null($mode))
			$mode = 'both';

		$tables = $filterTable ? array($filterTable) : $this->getTables();

		$languages = $this->getLanguages();

		$globalCharCount = 0;
		$globalLanguageCharCounts = $this->initLanguageCharCountsArray($languages);

		$lastProcessedTable = $continueAfterTable;
		$lastProcessedColumn = $continueAfterColumn;
		$lastProcessedOffset = $continueOffset;
		$done = true;

		$tablesResult = array();
		foreach($tables as $table)
		{
			if (!is_null($continueAfterTable))
			{
				if ($continueAfterTable == $table)
					$continueAfterTable = null;
				continue;
			}

			if ($globalCharCount > 0)
			{
				$time_elapsed_secs = microtime(true) - $start;
				if ($time_elapsed_secs > 1)
				{
					$done = false;
					break;
				}
			}

			$tableResult = $this->getTableToTranslateAnalizationInfo($languageIdFrom, $table, $languages, $mode, $languageIdTo, $product_status, $product_quantity, $product_category_id, $stock_status,
				true, $continueAfterColumn, $continueOffset);

			$continueAfterColumn = null;
			$continueOffset = 0;

			//if($table == 'oc_product_description') var_export($tableResult);

			if ($tableResult->tableCharCount > 0 || $filterTable)
			{
				if ($tableResult->tableTranslationIsSuppported)
				{
					foreach($tableResult->textColumnNames as $textColumnName)
					{
						if (isset($tableResult->tableFieldLanguageCharCounts[$textColumnName]))
						{
							foreach($tableResult->tableFieldLanguageCharCounts[$textColumnName] as $langIdTo => $value)
							{
								$charCount = $value['char_count'];

								$globalCharCount += $charCount;
								//print '<br><br><br>'; var_dump($globalCharCount); print '<br><br><br>';
								$globalLanguageCharCounts[$langIdTo]['char_count'] += $charCount;
							}
						}
					}
				}

				$tablesResult[] = $tableResult;
			}

			$lastProcessedColumn = $tableResult->lastProcessedColumn;
			$lastProcessedOffset = $tableResult->lastProcessedOffset;
			$done = $tableResult->done;
			if (!$done || !is_null($lastProcessedColumn) || ((int)$lastProcessedOffset !== 0))
			{
				break;
			}

			$lastProcessedTable = $table;
		}
		if ($done && $lastProcessedTable == $tables[count($tables) - 1])
		{
			$lastProcessedTable = null;
			$lastProcessedColumn = null;
			$lastProcessedOffset = 0;
		}

		if (is_null($start))
			$start = microtime(true);
		$time_elapsed_secs = microtime(true) - $start;
		$time_elapsed_secs = number_format((float)$time_elapsed_secs, 4, '.', '');

		return (object)array(
			'success' => 1,
			'done' => $done,
			'globalCharCount' => $globalCharCount,
			'globalLanguageCharCounts' => $globalLanguageCharCounts,
			'tablesResult' => $tablesResult,
			'tables' => $tables,
			'timeInSec' => $time_elapsed_secs,
			'lastProcessedTable' => $lastProcessedTable,
			'lastProcessedColumn' => $lastProcessedColumn,
			'lastProcessedOffset' => $lastProcessedOffset
		);
	}

	protected function getTextToTranslateRows($languageIdFrom, $tableName, $pkColumnNames, $textColumnName, $mode, $limit, $offset,
		$langIdToParam = null, $product_status = false, $product_quantity = false, $product_category_id = null, $stock_status = null, $text_from_filter = null)
	{
		$this->gebugLog('getTextToTranslateRows: ============START============', $startTime);

		if (is_null($langIdToParam))
		{
			$res = array();
			$languages = $this->getLanguages();
			foreach($languages as $code => $language)
			{
				$newRows = $this->getTextToTranslateRows($languageIdFrom, $tableName, $pkColumnNames, $textColumnName, $mode, $limit, $offset,
					$language['language_id'], $product_status, $product_quantity, $product_category_id, $stock_status);
				$index = count($res);
				foreach($newRows as $number => $newRow)
				{
					$res[$index++] = $newRow;
				}
				$this->gebugLog('$res: ' . var_export($res, true));
			}
			$this->gebugLog('getTextToTranslateRows: ============FINISH============', $startTime);
			return $res;
		}

		$def_language_id = (int)$this->_model->config->get('config_language_id');

		$joinPkConditions = array();
		foreach($pkColumnNames as $pkColumnName)
			if ($pkColumnName != $textColumnName)
				$joinPkConditions[] = "t2.{$pkColumnName} = t.{$pkColumnName}";
		if (count($joinPkConditions) == 0)
			$joinPkConditions[] = 'false';

		$additionalInternalWhereCondition = count($joinPkConditions) == 0 ? 'false' : 'true';

		$tableSameTranslatedText = DB_PREFIX . self::TRANSLATE_EXPERT_SAME_TRANSLATIONS;
		$whereConditions = array();
		if ($mode == 'only_empty' || $mode == 'both')
			$whereConditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(t2.{$textColumnName}, ' ', ''), '\t', ''), '\n', ''), '\r', '') = '' or t2.{$textColumnName} is null";
		if ($mode == 'same_value' || $mode == 'both')
		{
			$whereConditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(LOWER(t2.{$textColumnName}), ' ', ''), '\t', ''), '\n', ''), '\r', '') = REPLACE(REPLACE(REPLACE(REPLACE(LOWER(t.{$textColumnName}), ' ', ''), '\t', ''), '\n', ''), '\r', '')
				and stt1.text_hash_sum is null
				and stt2.text_hash_sum is null";
		}

		$l1WhereCondition = $languageIdFrom ? "(l1.language_id = {$languageIdFrom})" : '1 = 1';
		$l2WhereCondition = $langIdToParam ? "l2.language_id = {$langIdToParam}" : '1 = 1';

		$this->_model->db->query('SET SESSION group_concat_max_len = 1000000;');

		$groupBy = is_null($text_from_filter) ? "t2.language_id, t.{$textColumnName}" : "t.".implode(', t.', $pkColumnNames).", t2.language_id";
		$filterByTextFrom = is_null($text_from_filter) ? "" : " and (t.{$textColumnName} = '{$text_from_filter}')";

		$tableHasProductId = $this->tableHasFIeld($tableName, 'product_id');

		$productJoin = $tableHasProductId && ($product_status || $product_quantity || $stock_status)
			? "join ".DB_PREFIX."product p on p.product_id = t.product_id"
				. ($product_status ? " and p.status" : "")
				. ($product_quantity ? " and p.quantity > 0" : "")
				. ($stock_status ? " and p.stock_status_id = {$stock_status}" : "")
			: "";
		$productCategoryJoin = $tableHasProductId && $product_category_id
			? "join ".DB_PREFIX."product_to_category ptc on ptc.product_id = t.product_id and ptc.category_id = {$product_category_id}"
			: "";

		$sql = "
select
	t.".implode(', t.', $pkColumnNames).",
	t.language_id language_id_from,
	l1.code language_code_from,
	t.{$textColumnName} text_from,
	l2.language_id language_id_to,
	l2.code language_code_to,
	l2.image language_image_to,
	t2.{$textColumnName} text_to,
	count(*) value_count
from ".DB_PREFIX."language l1
join `{$tableName}` t on l1.language_id = t.language_id
	and {$additionalInternalWhereCondition}
	and REPLACE(REPLACE(REPLACE(REPLACE(t.{$textColumnName}, ' ', ''), '\t', ''), '\n', ''), '\r', '') <> ''
	and t.{$textColumnName} is not null
join ".DB_PREFIX."language l2 on l2.language_id <> t.language_id and ({$l2WhereCondition})
left join {$tableName} t2 on ".implode(' and ', $joinPkConditions)."
	and t2.language_id = l2.language_id
left join {$tableSameTranslatedText} stt1 on stt1.language_id_from = l1.language_id
	and stt1.language_id_to = l2.language_id
	and stt1.text_hash_sum = unhex(MD5(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(t.{$textColumnName}), ' ', ''), '\t', ''), '\n', ''), '\r', '')))
left join {$tableSameTranslatedText} stt2 on stt2.language_id_from = l1.language_id
	and stt2.language_id_to = l2.language_id
	and stt2.text_hash_sum = unhex(MD5(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(t2.{$textColumnName}), ' ', ''), '\t', ''), '\n', ''), '\r', '')))
{$productJoin}
{$productCategoryJoin}
where {$l1WhereCondition} {$filterByTextFrom}
	and (".implode(' or ', $whereConditions).")
group by {$groupBy}
order by t.".implode(', t.', $pkColumnNames).", t.language_id, t2.language_id
limit {$limit} offset {$offset}
		";

		$this->gebugLog($sql);
		$columns = $this->_model->db->query($sql)->rows;
		$this->gebugLog(var_export($columns, true));

		$this->gebugLog('getTextToTranslateRows: ============FINISH============', $startTime);
		return $columns;
	}

	protected function removeDataFromTable($langIdToParam, $tableName, $textColumnName, $product_status = false, $product_quantity = false, $product_category_id = null, $stock_status = null)
	{
		$this->gebugLog('removeDataFromTable: ============START============', $startTime);

		$tableHasProductId = $this->tableHasFIeld($tableName, 'product_id');

		$productJoin = $tableHasProductId && ($product_status || $product_quantity || $stock_status)
			? "join ".DB_PREFIX."product p on p.product_id = t.product_id"
				. ($product_status ? " and p.status" : "")
				. ($product_quantity ? " and p.quantity > 0" : "")
				. ($stock_status ? " and p.stock_status_id = {$stock_status}" : "")
			: "";
		$productCategoryJoin = $tableHasProductId && $product_category_id
			? "join ".DB_PREFIX."product_to_category ptc on ptc.product_id = t.product_id and ptc.category_id = {$product_category_id}"
			: "";


		$sql = "
update `{$tableName}` t
{$productJoin}
{$productCategoryJoin}
set t.{$textColumnName} = ''
where t.language_id = {$langIdToParam}
  and t.{$textColumnName} <> ''
  and t.{$textColumnName} is not null
		";

		$this->gebugLog($sql);
		$this->_model->db->query($sql);

		$this->gebugLog('removeDataFromTable: ============FINISH============', $startTime);
	}

}
