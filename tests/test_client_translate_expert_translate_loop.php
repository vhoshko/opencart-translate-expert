<?php
/**
 * Test script for the translate loop of LibraryClientTranslateExpert
 * (translateTable for the admin page, translateNextBatch for the cron link).
 *
 * Usage:
 *   php tests/test_client_translate_expert_translate_loop.php
 *
 * No database and no network: a stub subclass replaces the row query, the Google call and the DB write.
 * Any PHP warning or notice fails the run.
 */

define('VERSION', '3.0.3.8');
define('DIR_SYSTEM', __DIR__ . '/../sources/upload/system/');
define('DB_PREFIX', 'oc_');
define('DB_DATABASE', 'test');

class Log
{
	public static $lines = array();

	public function __construct($file) {}

	public function write($message)
	{
		self::$lines[] = $message;
	}
}

require_once DIR_SYSTEM . 'library/client_translate_expert.php';

class FakeDb
{
	public $queries = array();
	public $throwOnRelease = false;

	public function query($sql)
	{
		$this->queries[] = $sql;
		if ($this->throwOnRelease && strpos($sql, 'RELEASE_LOCK') !== false)
			throw new Exception('MySQL server has gone away');
		if (strpos($sql, 'GET_LOCK') !== false)
			return (object)array('row' => array('lock_result' => 1), 'rows' => array(), 'num_rows' => 1);
		return (object)array('row' => array(), 'rows' => array(), 'num_rows' => 0);
	}

	public function escape($value)
	{
		return $value;
	}

	public function released()
	{
		$n = 0;
		foreach ($this->queries as $q)
			if (strpos($q, 'RELEASE_LOCK') !== false)
				$n++;
		return $n;
	}
}

class FakeConfig
{
	public $data = array();

	public function get($key)
	{
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}
}

class FakeModel
{
	public $db;
	public $config;
}

class StubTranslateLibrary extends LibraryClientTranslateExpert
{
	public $groups = array();
	public $tablesList = array('oc_a');
	public $apiCalls = 0;
	public $sent = array();
	public $sentChars = 0;
	public $writes = array();
	public $clock = 1000.0;
	public $callCost = 0;
	public $failAtCall = 0;
	public $throwAtCall = 0;
	public $throwKind = 'error';
	public $addThrows = false;
	public $failMessage = 'Internal error.';
	public $throwAtWrite = 0;
	public $writeCount = 0;
	public $resetThrows = false;
	public $counted = array();
	public $sameCalls = array();
	public $pairs = array();
	public $independentWrites = false;

	public function __construct($model = null)
	{
		$this->_debug = false;
		if ($model !== null)
			$this->_model = $model;
	}

	protected function now()
	{
		return $this->clock;
	}

	public function enableDebug()
	{
		$this->_debug = true;
	}

	protected function loadAllLanguages()
	{
		return $this->getLanguages();
	}

	public function addCharCount($count)
	{
		$this->counted[] = $count;
		if ($this->addThrows)
			throw new Exception('db down');
		return $count;
	}

	public function resetCharCountOnNewMonth($month)
	{
		if ($this->resetThrows)
			throw new Exception('reset failed');
	}

	public function getTables()
	{
		return $this->tablesList;
	}

	protected function getLanguages()
	{
		return array(
			'en' => array('language_id' => 1, 'code' => 'en'),
			'uk' => array('language_id' => 2, 'code' => 'uk'),
		);
	}

	protected function getTableToTranslateAnalizationInfo($languageIdFrom, $table, $languages, $mode = null, $langIdToParam = null, $product_status = false, $product_quantity = false, $product_category_id = null, $stock_status = null, $calcCounts = true, $continueAfterColumn = null, $offset = 0)
	{
		return (object)array('tableTranslationIsSuppported' => true, 'textColumnNames' => array('name'), 'table' => $table, 'pkColumnNames' => array('id'));
	}

	protected function getTextToTranslateRows($languageIdFrom, $tableName, $pkColumnNames, $textColumnName, $mode, $limit, $offset, $langIdToParam = null, $product_status = false, $product_quantity = false, $product_category_id = null, $stock_status = null, $text_from_filter = null)
	{
		$rows = array();
		foreach ($this->groups as $g)
		{
			if ($g['table'] == $tableName && !$g['done'])
				$rows[] = array('id' => $g['id'], 'language_id_from' => 1, 'language_code_from' => 'en', 'text_from' => $g['text'],
					'language_id_to' => 2, 'language_code_to' => 'uk', 'text_to' => '', 'value_count' => $g['vc']);
		}
		return array_slice($rows, $offset, $limit);
	}

	public function translate($text, $source, $target, $format)
	{
		$this->apiCalls++;
		$this->clock += $this->callCost;
		if ($this->failAtCall == $this->apiCalls)
			return (object)array('success' => 0, 'message' => $this->failMessage, 'additional_message' => 'Google says no: SECRETSECRETSECRET1 key');
		if ($this->throwAtCall == $this->apiCalls)
		{
			if ($this->throwKind == 'error')
			{
				throw new \Error('boom SECRETSECRETSECRET1');
			}
			throw new Exception('boom SECRETSECRETSECRET1');
		}
		$out = array();
		foreach ($text as $t)
		{
			$this->sent[$t] = isset($this->sent[$t]) ? $this->sent[$t] + 1 : 1;
			$this->sentChars += mb_strlen($t);
			$out[] = strpos($t, 'EMPTY') === 0 ? '' : 'T:' . $t;
		}
		return (object)array('success' => 1, 'texts' => $out);
	}

	protected function insertSameTranslatedText($languageIdFrom, $languageIdTo, $text)
	{
		$this->sameCalls[] = array($languageIdFrom, $languageIdTo, $text);
	}

	protected function insertTranslatedTableTextValue($tableResult, $mode, $textColumnName, $product_status, $product_quantity, $product_category_id, $stock_status, $row, $translatedText)
	{
		$this->writeCount++;
		if ($this->throwAtWrite == $this->writeCount)
			throw new Exception('db write failed');
		foreach ($this->groups as $k => $g)
		{
			if ($g['table'] == $tableResult->table && $g['text'] === $row['text_from'] && !($this->independentWrites && $g['done']))
			{
				$this->groups[$k]['done'] = true;
				$this->writes[] = $translatedText;
				$this->pairs[] = array($row['text_from'], $translatedText);
				if ($this->independentWrites)
					break;
			}
		}
	}

	public function add($table, $count, $length, $valueCount = 1, $prefix = 'x')
	{
		for ($i = 0; $i < $count; $i++)
		{
			$id = count($this->groups);
			$this->groups[] = array('id' => $id, 'table' => $table, 'text' => str_pad($prefix . $table . $id . '_', $length, 'a'), 'vc' => $valueCount, 'done' => false);
		}
	}

	public function addTwins($table, $text)
	{
		for ($i = 0; $i < 2; $i++)
			$this->groups[] = array('id' => count($this->groups), 'table' => $table, 'text' => $text, 'vc' => 1, 'done' => false);
	}

	public function model()
	{
		return $this->_model;
	}

	public function left()
	{
		$n = 0;
		foreach ($this->groups as $g)
			if (!$g['done'])
				$n++;
		return $n;
	}

	public function duplicates()
	{
		$n = 0;
		foreach ($this->sent as $c)
			if ($c > 1)
				$n++;
		return $n;
	}

	public function emptyWrites()
	{
		$n = 0;
		foreach ($this->writes as $w)
			if (trim($w) === '')
				$n++;
		return $n;
	}

	public function sourceWrites()
	{
		$n = 0;
		foreach ($this->pairs as $p)
			if (strpos($p[0], 'EMPTY') === 0 && $p[1] === $p[0])
				$n++;
		return $n;
	}

	public function sameCallsForEmpty()
	{
		$n = 0;
		foreach ($this->sameCalls as $c)
			if (strpos($c[2], 'EMPTY') === 0 && $c[0] == 1 && $c[1] == 2)
				$n++;
		return $n;
	}
}

class TranslateLoopTest
{
	private $passed = 0;
	private $failed = 0;

	public function run()
	{
		echo "=== Translate loop Tests ===\n\n";

		set_error_handler(function ($errno, $errstr, $errfile, $errline) {
			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		});

		$this->testRowCap();
		$this->testSmallCap();
		$this->testBudgetSharedAcrossTables();
		$this->testRepeatedRunsFinish();
		$this->testEmptyResultUsesSource();
		$this->testEmptyFirstDoesNotStall(40, 1, 'cap 100, 40 empty groups first');
		$this->testEmptyFirstDoesNotStall(120, 1, 'cap 100, 120 empty groups first');
		$this->testSingleBigEmptyGroup();
		$this->testAdminTranslateTable();
		if (class_exists('Error'))
			$this->testCronError();
		else
			echo "\nSKIP: testCronError needs the Error class (PHP 7+)\n";
		$this->testCronException();
		$this->testCronAddCharCountThrows();
		$this->testCronGoogleFailure();
		$this->testTimeLimits();
		$this->testCronPerBatchCounting();
		$this->testCronWriteException();
		$this->testCronLastBudgetReset();
		$this->testCronLogHidesSecret();
		$this->testCronMessageHidesSecret();
		$this->testSetTimeLimitDoesNotBreak();
		$this->testDuplicateTextGroups();
		$this->testTimeCheckAfterFinalFlush();
		$this->testCronReleaseLockThrows();

		restore_error_handler();

		echo "\n=== Results: {$this->passed} passed, {$this->failed} failed, 0 skipped ===\n";
		return $this->failed === 0;
	}

	private function filters($table)
	{
		return array('langIdFrom' => 1, 'langIdTo' => 2, 'mode' => 'both', 'table' => $table,
			'product_status' => false, 'product_quantity' => false, 'product_category_id' => '', 'stock_status' => '');
	}

	private function runAll($lib, $cap, $table, $maxRuns = 80)
	{
		$runs = array();
		do
		{
			$r = $lib->translateNextBatch($this->filters($table), $cap);
			$runs[] = $r;
		}
		while (!$r->done && count($runs) < $maxRuns);
		return $runs;
	}

	private function testRowCap()
	{
		echo "Row cap:\n";
		$lib = new StubTranslateLibrary();
		$lib->add('oc_a', 200, 20, 7);
		$r = $lib->translateNextBatch($this->filters('oc_a'), 100);
		$this->check($r->rows, 105, 'cap 100 with value_count 7 translates 105 rows');
		$this->check($r->done, false, 'run is not done while rows are left');
		$this->check($r->chars, $lib->sentChars, 'chars equal chars sent');
		$this->check($lib->left(), 185, '15 groups are written');
	}

	private function testSmallCap()
	{
		echo "\nCap 1:\n";
		$lib = new StubTranslateLibrary();
		$lib->add('oc_a', 3, 20, 4);
		$r = $lib->translateNextBatch($this->filters('oc_a'), 1);
		$this->check($r->rows, 4, 'cap 1 translates one group');
		$this->check($lib->left(), 2, 'two groups stay');
		$this->check($r->done, false, 'not done');
	}

	private function testBudgetSharedAcrossTables()
	{
		echo "\nBudget across tables:\n";
		$lib = new StubTranslateLibrary();
		$lib->tablesList = array('oc_a', 'oc_b', 'oc_c');
		$lib->add('oc_a', 3, 20);
		$lib->add('oc_b', 10, 20);
		$lib->add('oc_c', 10, 20);
		$r = $lib->translateNextBatch($this->filters(''), 5);
		$this->check($r->rows, 5, 'cap 5 is shared by all tables');
		$this->check($r->table, 'oc_b', 'run stops in the second table');
		$this->check($r->done, false, 'not done');
		$r = $lib->translateNextBatch($this->filters(''), 5);
		$this->check($r->rows, 5, 'next run translates 5 rows');
		$this->check($r->table, 'oc_b', 'run stops in the second table again');
	}

	private function testRepeatedRunsFinish()
	{
		echo "\nRepeated runs:\n";
		$lib = new StubTranslateLibrary();
		$lib->tablesList = array('oc_a', 'oc_b', 'oc_c');
		$lib->add('oc_a', 130, 40, 3);
		$lib->add('oc_c', 45, 40, 2);
		$runs = $this->runAll($lib, 100, '');
		$last = $runs[count($runs) - 1];
		$sumChars = 0;
		$sumRows = 0;
		foreach ($runs as $r)
		{
			$sumChars += $r->chars;
			$sumRows += $r->rows;
		}
		$this->check($last->done, true, 'the last run is done');
		$this->check($lib->left(), 0, 'all groups are translated');
		$this->check($lib->duplicates(), 0, 'each text is sent once');
		$this->check($sumChars, $lib->sentChars, 'char total equals chars sent');
		$this->check($sumRows, 130 * 3 + 45 * 2, 'row total equals all rows');
		$this->check(count($runs) > 1, true, 'more than one run is needed');
	}

	private function testEmptyResultUsesSource()
	{
		echo "\nEmpty result uses the source text:\n";
		$lib = new StubTranslateLibrary();
		$lib->add('oc_a', 5, 20, 2, 'EMPTY');
		$lib->add('oc_a', 5, 20, 2);
		$runs = $this->runAll($lib, 100, 'oc_a');
		$last = $runs[count($runs) - 1];
		$this->check($lib->emptyWrites(), 0, 'no empty text is written');
		$this->check($lib->sourceWrites(), 5, 'the source text is written for each empty group');
		$this->check($lib->sameCallsForEmpty(), 5, 'a same-translation entry is recorded for each empty group');
		$this->check(count($lib->sameCalls), 5, 'no same-translation entry for normal groups');
		$this->check($lib->left(), 0, 'no group stays untranslated');
		$this->check($runs[0]->rows, 20, 'empty groups count in rows');
		$this->check($last->done, true, 'the run is done');
		$this->check($lib->duplicates(), 0, 'each text is sent once');
		$this->check(count($runs), 1, 'one run is enough');
	}

	private function testEmptyFirstDoesNotStall($emptyCount, $valueCount, $label)
	{
		echo "\nNo stall ({$label}):\n";
		$lib = new StubTranslateLibrary();
		$lib->add('oc_a', $emptyCount, 20, $valueCount, 'EMPTY');
		$lib->add('oc_a', 150, 20, $valueCount);
		$first = $lib->translateNextBatch($this->filters('oc_a'), 100);
		$this->check($first->rows, 100, 'first run translates exactly the cap');
		$this->check($lib->duplicates(), 0, 'each text is sent once in a run');
		$this->check($lib->left(), $emptyCount + 150 - 100, 'first run writes exactly 100 groups');
		$this->check($lib->sourceWrites(), min($emptyCount, 100), 'empty groups are written with the source text');
		$runs = $this->runAll($lib, 100, 'oc_a');
		$last = $runs[count($runs) - 1];
		$this->check($last->done, true, 'runs finish');
		$this->check($lib->left(), 0, 'all groups are translated');
		$this->check($lib->sourceWrites(), $emptyCount, 'each empty group is written once');
		$this->check($lib->duplicates(), 0, 'each text is sent once in all runs');
		$rest = $emptyCount + 150 - 100;
		$expectedRuns = (int)ceil($rest / 100);
		$this->check(count($runs), $expectedRuns, 'the rest needs ' . $expectedRuns . ' more run(s)');
		$this->check($runs[0]->rows, min($rest, 100), 'next run writes the cap or the rest');
		$this->check($last->rows, $rest - 100 * ($expectedRuns - 1), 'last run writes the rest');
	}

	private function testSingleBigEmptyGroup()
	{
		echo "\nNo stall (one empty group with value_count 500):\n";
		$lib = new StubTranslateLibrary();
		$lib->add('oc_a', 1, 20, 500, 'EMPTY');
		$lib->add('oc_a', 150, 20, 1);
		$first = $lib->translateNextBatch($this->filters('oc_a'), 100);
		$this->check($first->rows, 500, 'first run counts the 500 values of the big group');
		$this->check($first->done, false, 'first run is not done');
		$this->check($lib->sourceWrites(), 1, 'the big group is written with the source text');
		$this->check($lib->duplicates(), 0, 'each text is sent once in a run');
		$runs = $this->runAll($lib, 100, 'oc_a');
		$last = $runs[count($runs) - 1];
		$sum = $first->rows;
		foreach ($runs as $r)
			$sum += $r->rows;
		$this->check($last->done, true, 'runs finish');
		$this->check($lib->left(), 0, 'all groups are translated');
		$this->check($sum, 650, 'row total equals all rows');
	}

	private function testAdminTranslateTable()
	{
		echo "\nAdmin translateTable (no cap):\n";
		$lib = new StubTranslateLibrary();
		$lib->add('oc_a', 250, 60);
		$lib->add('oc_a', 5, 60, 1, 'EMPTY');
		$sum = 0;
		$n = 0;
		do
		{
			$r = $lib->translateTable(1, 'both', 'oc_a', null, 2, false, false, null, null);
			$sum += $r->translatedCharCount;
			$n++;
		}
		while (!$r->done && $n < 20);
		$this->check($r->success, 1, 'result has success');
		$this->check($r->done, true, 'last result is done');
		$this->check($sum, $lib->sentChars, 'char total equals chars sent');
		$this->check($lib->left(), 0, 'all groups are translated');
		$this->check($lib->emptyWrites(), 0, 'empty translations are not written');
		$this->check($lib->sourceWrites(), 5, 'the source text is written for each empty group');
		$this->check($lib->duplicates(), 0, 'each text is sent once');
		$this->check($lib->apiCalls > 1, true, 'the 5000 char rule splits the Google calls');
	}

	private function cronLib($bulk = 100)
	{
		$model = new FakeModel();
		$model->db = new FakeDb();
		$model->config = new FakeConfig();
		$model->config->data = array(
			'module_client_translate_expert_cron_secret' => 'SECRETSECRETSECRET1',
			'module_client_translate_expert_status' => 1,
			'module_client_translate_expert_cron_bulk_amount' => $bulk,
		);
		return new StubTranslateLibrary($model);
	}

	private function cronGet()
	{
		return array('secret' => 'SECRETSECRETSECRET1', 'langIdFrom' => '1', 'langIdTo' => '2', 'table' => 'oc_a');
	}

	private function testCronError()
	{
		echo "\nrunCron with an Error thrown mid-run:\n";
		$lib = $this->cronLib();
		$lib->add('oc_a', 300, 3000);
		$lib->throwAtCall = 3;
		$r = $lib->runCron($this->cronGet());
		$this->check($r['body']['error'], 'translate_failed', 'Error gives translate_failed');
		$this->check($lib->counted, array(3000, 3000), 'each earlier batch is counted once');
		$this->check($r['body']['chars'], 6000, 'response has chars already done');
		$this->check($r['body']['rows'], 2, 'response has rows already done');
		$this->check($r['body']['table'], 'oc_a', 'response has the table');
		$this->check(array_key_exists('details', $r['body']), true, 'response has details');
		$this->check($lib->model()->db->released(), 1, 'lock is released once');
	}

	private function testCronException()
	{
		echo "\nrunCron with an Exception that holds the secret:\n";
		$lib = $this->cronLib();
		$lib->add('oc_a', 300, 3000);
		$lib->throwAtCall = 3;
		$lib->throwKind = 'exception';
		$r = $lib->runCron($this->cronGet());
		$this->check($r['body']['message'], 'boom ***', 'secret is hidden in the message');
		$this->check($r['body']['details'], '', 'details is an empty string');
		$this->check($lib->counted, array(3000, 3000), 'each earlier batch is counted once');
		$this->check($lib->model()->db->released(), 1, 'lock is released once');
	}

	private function testCronAddCharCountThrows()
	{
		echo "\nrunCron when addCharCount throws:\n";
		$lib = $this->cronLib();
		$lib->add('oc_a', 30, 20);
		$lib->addThrows = true;
		$r = $lib->runCron($this->cronGet());
		$this->check($r['body']['error'], 'translate_failed', 'gives translate_failed');
		$this->check(count($lib->counted), 1, 'chars are counted once');
		$this->check($lib->model()->db->released(), 1, 'lock is released once');
	}

	private function testCronGoogleFailure()
	{
		echo "\nrunCron when Google fails:\n";
		$lib = $this->cronLib();
		$lib->add('oc_a', 300, 3000);
		$lib->failAtCall = 3;
		$r = $lib->runCron($this->cronGet());
		$this->check($r['body']['error'], 'translate_failed', 'gives translate_failed');
		$this->check($r['body']['details'], 'Google says no: *** key', 'details present and secret hidden');
		$this->check($r['body']['chars'], 6000, 'chars of earlier batches are in the response');
		$this->check($lib->counted, array(3000, 3000), 'each earlier batch is counted once');
		$this->check($lib->model()->db->released(), 1, 'lock is released once');
	}

	private function limitRun($lib, $cost, $cron)
	{
		$lib->add('oc_a', 1000, 60);
		$lib->callCost = $cost;
		if ($cron)
			return $lib->translateNextBatch($this->filters('oc_a'), 100000);
		return $lib->translateTable(1, 'both', 'oc_a', null, 2, false, false, null, null);
	}

	private function testTimeLimits()
	{
		echo "\nTime limits (fake clock):\n";

		$admin = new StubTranslateLibrary();
		$r = $this->limitRun($admin, 400, false);
		$this->check($admin->apiCalls, 2, 'admin run with 400 s calls stops at the end of the first page (2 calls with the final flush)');
		$this->check($r->done, false, 'admin run is not done');

		$adminFast = new StubTranslateLibrary();
		$r = $this->limitRun($adminFast, 4, false);
		$this->check($adminFast->apiCalls, 4, 'admin run with 4 s calls stops after 12 s (3 calls and the final flush)');
		$this->check($r->done, false, 'admin run with 4 s calls is not done');

		$cron = new StubTranslateLibrary();
		$r = $this->limitRun($cron, 400, true);
		$this->check($cron->apiCalls, 4, 'cron run with 400 s calls stops after 1200 s (3 calls and the final flush)');
		$this->check($r->done, false, 'cron run is not done');
		$this->check($r->chars, $cron->sentChars, 'chars equal chars sent');

		$cron = new StubTranslateLibrary();
		$r = $this->limitRun($cron, 100, true);
		$this->check($cron->apiCalls, 11, 'cron run with 100 s calls runs past 10 s and stops after 1000 s (10 calls and the final flush)');
		$this->check($r->done, false, 'cron run with 100 s calls is not done');

		$cron = new StubTranslateLibrary();
		$r = $this->limitRun($cron, 0, true);
		$this->check($r->done, true, 'cron run without delay finishes the table');
		$this->check($cron->left(), 0, 'all groups are translated');

		$capped = new StubTranslateLibrary();
		$capped->add('oc_a', 100, 20, 1, 'EMPTY');
		$capped->add('oc_a', 5, 20);
		$capped->callCost = 3.0;
		$r = $capped->translateNextBatch($this->filters('oc_a'), 1);
		$this->check($capped->apiCalls, 1, 'cap 1 with empty groups first: one call, the empty group counts as a row (3 s calls)');
		$this->check($r->rows, 1, 'cap path writes one row (the empty group, source text)');
		$this->check($r->done, false, 'cap path run is not done');

		$capped = new StubTranslateLibrary();
		$capped->add('oc_a', 1000, 20, 1, 'EMPTY');
		$capped->add('oc_a', 5, 20);
		$capped->callCost = 100.0;
		$r = $capped->translateNextBatch($this->filters('oc_a'), 1);
		$this->check($capped->apiCalls, 1, 'cap 1 with 100 s calls: one call, the cap ends the run before the 900 s limit');
		$this->check($r->rows, 1, 'cap path run writes one row');
		$this->check($capped->sourceWrites(), 1, 'the empty group is written with the source text');
		$this->check($r->done, false, 'cap path run is not done');
	}

	private function testCronPerBatchCounting()
	{
		echo "\nrunCron counts after each batch:\n";
		$lib = $this->cronLib(5);
		$lib->add('oc_a', 300, 3000);
		$r = $lib->runCron($this->cronGet());
		$this->check($r['body']['error'], '', 'run has no error');
		$this->check($lib->apiCalls >= 2, true, 'run sends more than one batch');
		$this->check(count($lib->counted), $lib->apiCalls, 'one counter call for each batch');
		$this->check(array_sum($lib->counted), $lib->sentChars, 'counter total equals chars sent');
		$this->check($r['body']['chars'], $lib->sentChars, 'response chars equal chars sent');

		$lib = $this->cronLib();
		$lib->add('oc_a', 300, 3000);
		$lib->throwAtCall = 3;
		$lib->throwKind = 'exception';
		$lib->runCron($this->cronGet());
		$this->check($lib->counted, array(3000, 3000), 'exception after batch 2: batches 1 and 2 counted once');

		$lib = $this->cronLib();
		$lib->add('oc_a', 300, 3000);
		$lib->failAtCall = 3;
		$lib->runCron($this->cronGet());
		$this->check($lib->counted, array(3000, 3000), 'translate failure: earlier batches counted once');

		$lib = $this->cronLib();
		$lib->add('oc_a', 300, 3000);
		$lib->failAtCall = 1;
		$lib->runCron($this->cronGet());
		$this->check($lib->counted, array(), 'translate failure on the first batch: nothing counted');

		$lib = $this->cronLib(5);
		$lib->add('oc_a', 300, 3000);
		$lib->model()->config->data['module_client_translate_expert_reset_char_count_on_start_month'] = 1;
		$lib->runCron($this->cronGet());
		$this->check(count($lib->counted), $lib->apiCalls, 'with month reset: one counter call for each batch');

		$lib = new StubTranslateLibrary();
		$lib->add('oc_a', 250, 60);
		$lib->translateTable(1, 'both', 'oc_a', null, 2, false, false, null, null);
		$this->check($lib->counted, array(), 'admin translateTable does not count by itself');
	}

	private function testCronWriteException()
	{
		echo "\nrunCron when a DB write throws:\n";
		$lib = $this->cronLib();
		$lib->add('oc_a', 30, 20);
		$lib->throwAtWrite = 3;
		$r = $lib->runCron($this->cronGet());
		$this->check($r['body']['error'], 'translate_failed', 'gives translate_failed');
		$this->check($r['body']['rows'], 2, 'rows count only the groups that were written');
		$this->check($lib->model()->db->released(), 1, 'lock is released once');
	}

	private function testCronLastBudgetReset()
	{
		echo "\nrunCron on the same object twice:\n";
		$lib = $this->cronLib(5);
		$lib->add('oc_a', 30, 20);
		$first = $lib->runCron($this->cronGet());
		$this->check($first['body']['rows'] > 0, true, 'first run translates rows');
		$counted = count($lib->counted);
		$lib->model()->config->data['module_client_translate_expert_reset_char_count_on_start_month'] = 1;
		$lib->resetThrows = true;
		$r = $lib->runCron($this->cronGet());
		$this->check($r['body']['error'], 'translate_failed', 'second run fails before the budget exists');
		$this->check($r['body']['rows'], 0, 'response has no rows of the first run');
		$this->check($r['body']['chars'], 0, 'response has no chars of the first run');
		$this->check(count($lib->counted), $counted, 'chars of the first run are not counted again');
	}

	private function testCronLogHidesSecret()
	{
		echo "\nrunCron with the debug log on:\n";
		Log::$lines = array();
		$lib = $this->cronLib();
		$lib->enableDebug();
		$lib->add('oc_a', 300, 3000);
		$lib->throwAtCall = 3;
		$lib->throwKind = 'exception';
		$r = $lib->runCron($this->cronGet());
		$log = implode("\n", Log::$lines);
		$this->check(strpos($log, 'runCron EXCEPTION') !== false, true, 'the exception is in the log');
		$this->check(strpos($log, 'boom ***') !== false, true, 'the log has the redacted message');
		$this->check(strpos($log, 'SECRETSECRETSECRET1'), false, 'the secret is not in the log');
		$this->check(strpos(json_encode($r['body']), 'SECRETSECRETSECRET1'), false, 'the secret is not in the response');
	}

	private function testCronMessageHidesSecret()
	{
		echo "\nrunCron when Google message holds the secret:\n";
		$lib = $this->cronLib();
		$lib->add('oc_a', 300, 3000);
		$lib->failAtCall = 1;
		$lib->failMessage = 'bad SECRETSECRETSECRET1 key';
		$r = $lib->runCron($this->cronGet());
		$this->check($r['body']['message'], 'bad *** key', 'message is redacted');
		$this->check(strpos(json_encode($r['body']), 'SECRETSECRETSECRET1'), false, 'the secret is not in the response');
	}

	private function testSetTimeLimitDoesNotBreak()
	{
		echo "\nrunCron and set_time_limit:\n";
		$lib = $this->cronLib();
		$lib->add('oc_a', 5, 20);
		$r = $lib->runCron($this->cronGet());
		$this->check($r['body']['error'], '', 'run works after set_time_limit and ignore_user_abort');
		$this->check($r['body']['done'], true, 'run is done');
	}

	private function testDuplicateTextGroups()
	{
		echo "\nTwo groups with the same text and independent writes:\n";
		$lib = $this->cronLib();
		$lib->independentWrites = true;
		$lib->addTwins('oc_a', 'twin text 1');
		$r = $lib->runCron($this->cronGet());
		$this->check($r['body']['done'], false, 'cron run 1 is not done while the second group is left');
		$this->check($lib->left(), 1, 'one group is left after run 1');
		$r = $lib->runCron($this->cronGet());
		$this->check($r['body']['done'], true, 'cron run 2 is done');
		$this->check($lib->left(), 0, 'both groups are written after run 2');
		$this->check(count($lib->writes), 2, 'two writes in total');
		$this->check($r['body']['rows'], 1, 'run 2 writes the left group');

		$lib = new StubTranslateLibrary();
		$lib->independentWrites = true;
		$lib->addTwins('oc_a', 'twin text 2');
		$r = $lib->translateTable(1, 'both', 'oc_a', null, 2, false, false, null, null);
		$this->check($r->done, false, 'admin first call is not done');
		$this->check($lib->left(), 1, 'one group is left after the first admin call');
		$r = $lib->translateTable(1, 'both', 'oc_a', null, 2, false, false, null, null);
		$this->check($r->done, true, 'admin second call is done');
		$this->check($lib->left(), 0, 'nothing is left after the second admin call');

		$lib = $this->cronLib();
		$lib->addTwins('oc_a', 'twin text 3');
		$r = $lib->runCron($this->cronGet());
		$this->check($r['body']['done'], true, 'one write fills both groups: done in one run');
		$this->check($lib->left(), 0, 'nothing is left');
		$this->check($r['body']['rows'], 1, 'only the first group is queued');
	}

	private function testTimeCheckAfterFinalFlush()
	{
		echo "\nTime check after the final flush:\n";
		$lib = new StubTranslateLibrary();
		$lib->add('oc_a', 1, 20);
		$lib->callCost = 20;
		$r = $lib->translateTable(1, 'both', 'oc_a', null, 2, false, false, null, null);
		$this->check($lib->apiCalls, 1, 'admin: one call');
		$this->check($lib->left(), 0, 'admin: the row is written');
		$this->check($r->done, false, 'admin: the final flush passes 10 s, so not done');

		$lib = new StubTranslateLibrary();
		$lib->add('oc_a', 1, 20);
		$lib->callCost = 20;
		$r = $lib->translateNextBatch($this->filters('oc_a'), 100000);
		$this->check($r->done, true, 'cron: 20 s is under 900 s, so done');

		$lib = new StubTranslateLibrary();
		$lib->add('oc_a', 1, 20);
		$lib->callCost = 1000;
		$r = $lib->translateNextBatch($this->filters('oc_a'), 100000);
		$this->check($lib->apiCalls, 1, 'cron: one call');
		$this->check($r->rows, 1, 'cron: one row written');
		$this->check($lib->left(), 0, 'cron: the row is written');
		$this->check($r->done, false, 'cron: the final flush passes 900 s, so not done');
	}

	private function testCronReleaseLockThrows()
	{
		echo "\nrunCron when RELEASE_LOCK throws:\n";
		$lib = $this->cronLib();
		$lib->model()->db->throwOnRelease = true;
		$lib->add('oc_a', 5, 20);
		$threw = false;
		$r = null;
		try
		{
			$r = $lib->runCron($this->cronGet());
		}
		catch (Exception $e)
		{
			$threw = true;
		}
		$this->check($threw, false, 'runCron does not throw');
		$body = is_array($r) ? $r['body'] : array();
		$this->check(is_array($r) ? $r['http_status'] : null, 200, 'status is 200');
		$this->check(isset($body['done']) ? $body['done'] : null, true, 'body has done true');
		$this->check(isset($body['rows']) ? $body['rows'] : null, 5, 'body has the rows');
		$this->check(isset($body['error']) ? $body['error'] : null, '', 'body has no error');
		$this->check(is_string(json_encode($body)), true, 'body is JSON-able');
		$this->check($lib->model()->db->released(), 1, 'RELEASE_LOCK was tried once');
	}

	private function check($actual, $expected, $label)
	{
		if ($actual === $expected)
		{
			echo "  PASS: {$label}\n";
			$this->passed++;
		}
		else
		{
			echo "  FAIL: {$label} (expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . ")\n";
			$this->failed++;
		}
	}
}

$test = new TranslateLoopTest();
exit($test->run() ? 0 : 1);
