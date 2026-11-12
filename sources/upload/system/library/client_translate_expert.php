<?php

if (defined('DIR_EXTENSION') && version_compare(VERSION, '4.0', '>=')) {
	include_once(DIR_EXTENSION . 'client_translate_expert/system/library/client_translate_expert_core.php');
	include_once(DIR_EXTENSION . 'client_translate_expert/system/library/client_translate_expert_cron_helper.php');
} else {
	include_once(DIR_SYSTEM . 'library/client_translate_expert_core.php');
	include_once(DIR_SYSTEM . 'library/client_translate_expert_cron_helper.php');
}

class LibraryClientTranslateExpert extends LibraryClientTranslateExpertCore
{
	const CRON_TIME_LIMIT = 900;

	protected $_lastBudget = null;

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

	public function translateTable($languageIdFrom, $mode, $tableName, $textColumnNameParam, $langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id)
	{
		$this->gebugLog('translateTable: ============START============', $startTime);

		$res = $this->translateTableWithBudget($languageIdFrom, $mode, $tableName, $textColumnNameParam, $langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id, $this->createBatchBudget(0, 10, false));

		$this->gebugLog('translateTable: ============FINISH============', $startTime);
		return $res;
	}

	// Translates one batch for the cron link: up to $rowCap DB rows in the selected tables,
	// or in every table in order when $filters['table'] is an empty list. The WHERE of getTextToTranslateRows
	// selects only untranslated rows, so the next call continues where this one stopped.
	public function translateNextBatch($filters, $rowCap)
	{
		$this->gebugLog('translateNextBatch: ============START============', $startTime);

		$budget = $this->createBatchBudget($rowCap, self::CRON_TIME_LIMIT, true);
		$tables = $this->selectTables($filters['table']);

		$res = (object)array('success' => 1, 'done' => true, 'table' => '');
		foreach($tables as $table)
		{
			$budget->table = $table;
			$tableRes = $this->translateTableWithBudget($filters['langIdFrom'], $filters['mode'], $table, null, $filters['langIdTo'],
				$filters['product_status'], $filters['product_quantity'], $filters['product_category_id'], $filters['stock_status'], $filters['manufacturer_id'], $budget);
			$res->table = $table;
			if (!$tableRes->success)
			{
				$res->success = 0;
				$res->message = $tableRes->message;
				$res->details = isset($tableRes->additional_message) ? (string)$tableRes->additional_message : '';
				$res->done = false;
				break;
			}
			if (!$tableRes->done)
			{
				$res->done = false;
				break;
			}
		}

		$res->rows = $budget->rowsDone;
		$res->chars = $budget->charsDone;

		$this->gebugLog('translateNextBatch: ============FINISH============', $startTime);
		return $res;
	}

	// Runs one cron batch. $get is the request GET array. The secret and the GET array never go to the debug log.
	// Returns array('http_status' => int, 'body' => array) for the store-front controller.
	public function runCron($get)
	{
		$this->_lastBudget = null;
		$version = VERSION;
		$secretKey = ClientTranslateExpertCronHelper::settingKey('client_translate_expert_cron_secret', $version);
		$config = $this->_model->config;

		$knownSecret = (string)$config->get($secretKey);
		$givenSecret = isset($get['secret']) && is_string($get['secret']) ? $get['secret'] : '';
		if (!ClientTranslateExpertCronHelper::isValidSecret($knownSecret)
			|| !ClientTranslateExpertCronHelper::secretsMatch($knownSecret, $givenSecret))
		{
			return $this->cronError('forbidden', 403);
		}

		if (!$config->get(ClientTranslateExpertCronHelper::settingKey('client_translate_expert_status', $version)))
			return $this->cronError('disabled');

		$filters = ClientTranslateExpertCronHelper::parseParams($get);
		if ($filters === false)
			return $this->cronError('bad_params');

		$this->_languages = $this->loadAllLanguages();
		$languageIds = array();
		foreach($this->_languages as $language)
			$languageIds[] = (int)$language['language_id'];
		if (!in_array($filters['langIdFrom'], $languageIds, true) || !in_array($filters['langIdTo'], $languageIds, true))
			return $this->cronError('bad_params');

		if (count($filters['table']) > 0 && count(array_diff($filters['table'], $this->getTables())) > 0)
			return $this->cronError('bad_params');

		if ($filters['bulk_amount'] !== '')
			$rowCap = $filters['bulk_amount'];
		else
			$rowCap = ClientTranslateExpertCronHelper::clampBulkAmount($config->get(ClientTranslateExpertCronHelper::settingKey('client_translate_expert_cron_bulk_amount', $version)));

		$db = $this->_model->db;
		$lockName = 'te_cron_' . md5(DB_DATABASE . DB_PREFIX);
		$lockRow = $db->query("SELECT GET_LOCK('" . $db->escape($lockName) . "', 0) AS `lock_result`")->row;
		if (!isset($lockRow['lock_result']) || (int)$lockRow['lock_result'] !== 1)
			return $this->cronError('busy');

		$this->gebugLog('runCron: ' . var_export(array('filters' => $filters, 'rowCap' => $rowCap), true));

		if (function_exists('set_time_limit'))
			@set_time_limit(0);
		if (function_exists('ignore_user_abort'))
			@ignore_user_abort(true);

		try
		{
			if ($config->get(ClientTranslateExpertCronHelper::settingKey('client_translate_expert_reset_char_count_on_start_month', $version)))
				$this->resetCharCountOnNewMonth(date('m'));

			$res = $this->translateNextBatch($filters, $rowCap);
			$charsDone = (int)$res->chars;

			if (!$res->success)
			{
				$error = $this->cronError('translate_failed');
				$error['body']['message'] = $this->hideSecret(isset($res->message) ? $res->message : '', $knownSecret);
				$error['body']['details'] = $this->hideSecret(isset($res->details) ? $res->details : '', $knownSecret);
				$error['body']['rows'] = (int)$res->rows;
				$error['body']['chars'] = $charsDone;
				$error['body']['table'] = (string)$res->table;
				return $error;
			}

			return array('http_status' => 200, 'body' => array(
				'done' => (bool)$res->done,
				'rows' => (int)$res->rows,
				'chars' => $charsDone,
				'table' => (string)$res->table,
				'error' => '',
			));
		}
		catch (\Exception $e)
		{
			return $this->cronFailure($e, $knownSecret);
		}
		catch (\Throwable $e)
		{
			return $this->cronFailure($e, $knownSecret);
		}
		finally
		{
			try
			{
				$db->query("SELECT RELEASE_LOCK('" . $db->escape($lockName) . "')");
			}
			catch (\Exception $e)
			{
				// The lock is released anyway when the DB connection closes.
			}
		}
	}

	public function getListMaxItems()
	{
		return ClientTranslateExpertCronHelper::LIST_MAX_ITEMS;
	}

	public function parseIdList($value)
	{
		return ClientTranslateExpertCronHelper::parseIdList($value);
	}

	public function parseTableList($value)
	{
		return ClientTranslateExpertCronHelper::parseTableList($value);
	}

	public function isValidCronSecret($secret)
	{
		return ClientTranslateExpertCronHelper::isValidSecret($secret);
	}

	protected function cronFailure($e, $knownSecret)
	{
		$message = $this->hideSecret($e->getMessage(), $knownSecret);
		$this->gebugLog('runCron EXCEPTION: ' . $message);
		$budget = $this->_lastBudget;
		$error = $this->cronError('translate_failed');
		$error['body']['message'] = $message;
		$error['body']['details'] = '';
		if (!is_null($budget))
		{
			$error['body']['rows'] = (int)$budget->rowsDone;
			$error['body']['chars'] = (int)$budget->charsDone;
			$error['body']['table'] = (string)$budget->table;
		}
		return $error;
	}

	protected function hideSecret($text, $secret)
	{
		$text = (string)$text;
		return $secret !== '' ? str_replace($secret, '***', $text) : $text;
	}

	public function clampCronBulkAmount($value)
	{
		return ClientTranslateExpertCronHelper::clampBulkAmount($value);
	}

	protected function cronError($code, $httpStatus = 200)
	{
		return array('http_status' => $httpStatus, 'body' => array(
			'done' => false,
			'rows' => 0,
			'chars' => 0,
			'table' => '',
			'error' => $code,
		));
	}

	// The store-front language model returns enabled languages only. The cron must see all of them.
	protected function loadAllLanguages()
	{
		$languages = array();
		$rows = $this->_model->db->query("SELECT * FROM `" . DB_PREFIX . "language` ORDER BY `sort_order`, `name`")->rows;
		foreach($rows as $row)
			$languages[$row['code']] = $row;
		return $languages;
	}

	protected function now()
	{
		return microtime(true);
	}

	protected function createBatchBudget($rowCap, $timeLimit, $countBatches)
	{
		$this->_lastBudget = (object)array(
			'rowCap' => (int)$rowCap,
			'timeLimit' => $timeLimit,
			'countBatches' => $countBatches,
			'rowsQueued' => 0,
			'rowsDone' => 0,
			'charsDone' => 0,
			'start' => $this->now(),
			'table' => '',
		);
		return $this->_lastBudget;
	}

	protected function addBatchChars($budget, $chars)
	{
		if ($budget->countBatches && $chars > 0)
			$this->addCharCount($chars);
		$budget->charsDone += $chars;
	}

	protected function translateTableWithBudget($languageIdFrom, $mode, $tableName, $textColumnNameParam, $langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id, $budget)
	{
		if (is_null($mode))
			$mode = 'both';

		$languages = $this->getLanguages();

		$tableResult = $this->getTableToTranslateAnalizationInfo($languageIdFrom, $tableName, $languages, $mode, $langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id, false);

		$done = true;

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
					$queuedKeys = array();
					$droppedDuplicate = false;

					$limit = self::TRANSLATION_ANALITIC_SQL_LIMIT;
					$offset = 0;
					while (true)
					{
						$textToTranslateRows = $this->getTextToTranslateRows($languageIdFrom, $tableResult->table, $tableResult->pkColumnNames, $textColumnName, $mode, $limit, $offset, $langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id);
						if (count($textToTranslateRows) == 0)
							break;
						$offset += count($textToTranslateRows);

						foreach($textToTranslateRows as $textToTranslateRow)
						{
							if ($textToTranslateRow['language_id_to'] != $language['language_id'])
								continue;

							$textToTranslate = $textToTranslateRow['text_from'];

							$queuedKey = md5($textToTranslate);
							if (isset($queuedKeys[$queuedKey]))
							{
								// Same text in another group (e.g. no target row vs empty target row): it is
								// written after its twin is flushed, so the run is not done until it is re-read.
								$droppedDuplicate = true;
								continue;
							}

							if ($budget->rowCap > 0 && $budget->rowsQueued > 0 && $budget->rowsQueued >= $budget->rowCap)
							{
								if (count($textRowsToTranslate) > 0)
								{
									$translateRes = $this->translateTableTexts($tableResult, $mode, $textColumnName, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id, $textRowsToTranslate, $budget);
									if (!$translateRes->success)
										return (object)array('success' => 0, 'done' => 0, 'message' => $translateRes->message, 'additional_message' => isset($translateRes->additional_message) ? $translateRes->additional_message : '', 'translatedCharCount' => $budget->charsDone);

									$offset = 0;
									$this->addBatchChars($budget, $textsToTranslateLenght);
									$textsToTranslateLenght = 0;
									$textRowsToTranslate = array();
									$queuedKeys = array();
									$droppedDuplicate = false;
								}
								if ($budget->rowsQueued > 0 && $budget->rowsQueued >= $budget->rowCap)
								{
									$done = false;
									break 2;
								}
							}

							if ((count($textRowsToTranslate) > 0)
								&& ($textsToTranslateLenght + $this->getBillableCharCount($textToTranslate) > 5000))
							{
								$translateRes = $this->translateTableTexts($tableResult, $mode, $textColumnName, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id, $textRowsToTranslate, $budget);
								if (!$translateRes->success)
									return (object)array('success' => 0, 'done' => 0, 'message' => $translateRes->message, 'additional_message' => isset($translateRes->additional_message) ? $translateRes->additional_message : '', 'translatedCharCount' => $budget->charsDone);

								$offset = 0;
								$this->addBatchChars($budget, $textsToTranslateLenght);
								$textsToTranslateLenght = 0;
								$textRowsToTranslate = array();
								$queuedKeys = array();
								$droppedDuplicate = false;
							}

							$textsToTranslateLenght += $this->getBillableCharCount($textToTranslate);
							$textRowsToTranslate[] = $textToTranslateRow;
							$queuedKeys[$queuedKey] = true;
							$budget->rowsQueued += (int)$textToTranslateRow['value_count'];
						}

						if ($budget->charsDone > 0)
						{
							$time_elapsed_secs = $this->now() - $budget->start;
							if ($time_elapsed_secs > $budget->timeLimit)
							{
								$done = false;
								break;
							}
						}
					}

					if (count($textRowsToTranslate) > 0)
					{
						$translateRes = $this->translateTableTexts($tableResult, $mode, $textColumnName, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id, $textRowsToTranslate, $budget);
						if (!$translateRes->success)
							return (object)array('success' => 0, 'done' => 0, 'message' => $translateRes->message, 'additional_message' => isset($translateRes->additional_message) ? $translateRes->additional_message : '', 'translatedCharCount' => $budget->charsDone);
						$this->addBatchChars($budget, $textsToTranslateLenght);

						$time_elapsed_secs = $this->now() - $budget->start;
						if ($time_elapsed_secs > $budget->timeLimit)
						{
							$done = false;
							break;
						}
					}

					if ($done && $droppedDuplicate)
					{
						$leftRows = $this->getTextToTranslateRows($languageIdFrom, $tableResult->table, $tableResult->pkColumnNames, $textColumnName, $mode, $limit, 0, $langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id);
						foreach($leftRows as $leftRow)
						{
							if ($leftRow['language_id_to'] == $language['language_id'])
							{
								$done = false;
								break;
							}
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

		return (object)array(
			'success' => 1,
			'done' => $done,
			'translatedCharCount' => $budget->charsDone,
		);
	}

	protected function translateTableTexts($tableResult, $mode, $textColumnName, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id, $textRowsToTranslate, $budget = null)
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
			return (object)array('success' => 0, 'error_code' => 1000, 'message' => 'Internal error.', 'additional_message' => 'count($translationResults->texts) != count($textRowsToTranslate), ' . $c1 . ' != ' . $c2);
		}

		if ($shouldBeDecoded)
		{
			$translationResults->texts = $this->encodeHtml($translationResults->texts, $textsToTranslateOriginal);
		}


		$c1 = count($translationResults->texts);
		$c2 = count($textRowsToTranslate);
		if ($c1 != $c2)
		{
			return (object)array('success' => 0, 'error_code' => 1000, 'message' => 'Internal error.', 'additional_message' => 'count($translationResults->texts) != count($textRowsToTranslate), ' . $c1 . ' != ' . $c2);
		}

		for($i = 0; $i < count($textRowsToTranslate); $i++)
		{
			$textToTranslateRow = $textRowsToTranslate[$i];
			$textToTranslate = $textToTranslateRow['text_from'];
			$translatedText = $translationResults->texts[$i];
			// Google returns '' for some texts (e.g. '.'): keep the source text, as if it came back unchanged.
			if (trim($translatedText) === '')
				$translatedText = $textToTranslate;
			$translatedNormalized = $this->normalizeForSameCheck($translatedText);
			if ($this->normalizeForSameCheck($textToTranslate) === $translatedNormalized
				|| isset($textToTranslateRow['text_to']) && $this->normalizeForSameCheck($textToTranslateRow['text_to']) === $translatedNormalized
			)
			{
				$this->insertSameTranslatedText($textToTranslateRow['language_id_from'], $textToTranslateRow['language_id_to'], $translatedText);
			}
			$this->insertTranslatedTableTextValue($tableResult, $mode, $textColumnName, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id, $textToTranslateRow, $translatedText);
			if (!is_null($budget))
				$budget->rowsDone += (int)$textToTranslateRow['value_count'];
		}

		return (object)array('success' => '1');
	}

	protected function getTableToTranslateAnalizationInfo($languageIdFrom, $table, $languages, $mode = null, $langIdToParam = null,
		$product_status = false, $product_quantity = false, $product_category_id = null, $stock_status = null, $manufacturer_id = null,
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
						$langIdToParam, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id);
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
						$charCount = $this->getBillableCharCount($textToTranslateRow['text_from']);

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

	protected function getTablesToTranslateAnalizationInfo($languageIdFrom, $languageIdTo, $mode = null, $filterTable = '', $product_status = false, $product_quantity = false, $product_category_id = null, $stock_status = null, $manufacturer_id = null,
		$continueAfterTable = null, $continueAfterColumn = null, $continueOffset = 0
	) {
		$start = microtime(true);

		if (is_null($mode))
			$mode = 'both';

		$filterTable = $this->normalizeList($filterTable);
		$tables = $this->selectTables($filterTable);

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

			$tableResult = $this->getTableToTranslateAnalizationInfo($languageIdFrom, $table, $languages, $mode, $languageIdTo, $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id,
				true, $continueAfterColumn, $continueOffset);

			$continueAfterColumn = null;
			$continueOffset = 0;

			//if($table == 'oc_product_description') var_export($tableResult);

			if ($tableResult->tableCharCount > 0 || count($filterTable) > 0)
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
		if ($done && (count($tables) == 0 || $lastProcessedTable == $tables[count($tables) - 1]))
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
		$langIdToParam = null, $product_status = false, $product_quantity = false, $product_category_id = null, $stock_status = null, $manufacturer_id = null, $text_from_filter = null)
	{
		$this->gebugLog('getTextToTranslateRows: ============START============', $startTime);

		if (is_null($langIdToParam))
		{
			$res = array();
			$languages = $this->getLanguages();
			foreach($languages as $code => $language)
			{
				$newRows = $this->getTextToTranslateRows($languageIdFrom, $tableName, $pkColumnNames, $textColumnName, $mode, $limit, $offset,
					$language['language_id'], $product_status, $product_quantity, $product_category_id, $stock_status, $manufacturer_id);
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

		$stockIds = $this->normalizeIdList($stock_status);
		$manufacturerIds = $this->normalizeIdList($manufacturer_id);
		$categoryIds = $this->normalizeIdList($product_category_id);

		$productJoin = $tableHasProductId && ($product_status || $product_quantity || count($stockIds) > 0 || count($manufacturerIds) > 0)
			? "join ".DB_PREFIX."product p on p.product_id = t.product_id"
				. ($product_status ? " and p.status" : "")
				. ($product_quantity ? " and p.quantity > 0" : "")
				. (count($stockIds) > 0 ? " and p.stock_status_id in (" . implode(',', $stockIds) . ")" : "")
				. (count($manufacturerIds) > 0 ? " and p.manufacturer_id in (" . implode(',', $manufacturerIds) . ")" : "")
			: "";
		$categoryCondition = $tableHasProductId && count($categoryIds) > 0
			? " and exists (select 1 from ".DB_PREFIX."product_to_category ptc where ptc.product_id = t.product_id and ptc.category_id in (" . implode(',', $categoryIds) . "))"
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
where {$l1WhereCondition} {$filterByTextFrom} {$categoryCondition}
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

	protected function removeDataFromTable($langIdToParam, $tableName, $textColumnName, $product_status = false, $product_quantity = false, $product_category_id = null, $stock_status = null, $manufacturer_id = null)
	{
		$this->gebugLog('removeDataFromTable: ============START============', $startTime);

		$tableHasProductId = $this->tableHasFIeld($tableName, 'product_id');

		$stockIds = $this->normalizeIdList($stock_status);
		$manufacturerIds = $this->normalizeIdList($manufacturer_id);
		$categoryIds = $this->normalizeIdList($product_category_id);

		$productJoin = $tableHasProductId && ($product_status || $product_quantity || count($stockIds) > 0 || count($manufacturerIds) > 0)
			? "join ".DB_PREFIX."product p on p.product_id = t.product_id"
				. ($product_status ? " and p.status" : "")
				. ($product_quantity ? " and p.quantity > 0" : "")
				. (count($stockIds) > 0 ? " and p.stock_status_id in (" . implode(',', $stockIds) . ")" : "")
				. (count($manufacturerIds) > 0 ? " and p.manufacturer_id in (" . implode(',', $manufacturerIds) . ")" : "")
			: "";
		$categoryCondition = $tableHasProductId && count($categoryIds) > 0
			? " and exists (select 1 from ".DB_PREFIX."product_to_category ptc where ptc.product_id = t.product_id and ptc.category_id in (" . implode(',', $categoryIds) . "))"
			: "";


		$sql = "
update `{$tableName}` t
{$productJoin}
set t.{$textColumnName} = ''
where t.language_id = {$langIdToParam}
  {$categoryCondition}
  and t.{$textColumnName} <> ''
  and t.{$textColumnName} is not null
		";

		$this->gebugLog($sql);
		$this->_model->db->query($sql);

		$this->gebugLog('removeDataFromTable: ============FINISH============', $startTime);
	}

}
