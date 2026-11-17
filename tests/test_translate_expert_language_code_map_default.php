<?php
/**
 * Test script for the default map write of TranslateExpertCore::ensureLanguageCodeMapDefault (stub DB).
 *
 * Usage:
 *   php tests/test_translate_expert_language_code_map_default.php
 *
 * The script starts itself once for each OpenCart version, because VERSION is a constant.
 * No database and no network. Any PHP warning or notice fails the run.
 */

$versions = array('1.5.6.4', '2.2.0.0', '2.3.0.2', '3.0.3.8', '4.0.2.3', '4.1.0.0');

if (!isset($argv[1])) {
	$failed = 0;
	foreach ($versions as $version) {
		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($version) . ' 2>&1', $output, $code);
		echo implode("\n", $output) . "\n";
		if ($code !== 0) {
			$failed++;
		}
	}
	exit($failed === 0 ? 0 : 1);
}

define('VERSION', $argv[1]);
define('DIR_SYSTEM', __DIR__ . '/../sources/upload/system/');
$extensionDir = sys_get_temp_dir() . '/te_ext_' . getmypid() . '/';
mkdir($extensionDir);
symlink(realpath(__DIR__ . '/../sources/upload'), $extensionDir . 'client_translate_expert');
register_shutdown_function(function () use ($extensionDir) {
	unlink($extensionDir . 'client_translate_expert');
	rmdir($extensionDir);
});
define('DIR_EXTENSION', $extensionDir);
$order = array();
define('DB_PREFIX', 'oc_');
define('DB_DATABASE', 'test');

class Log
{
	public function __construct($file) {}

	public function write($message) {}
}

$warnings = array();
set_error_handler(function ($no, $str, $file, $line) use (&$warnings) {
	$warnings[] = "$str in $file:$line";
	return true;
});

require_once DIR_SYSTEM . 'library/translate_expert/translate_expert_core.php';

class StubDb
{
	public $queries = array();
	public $hasCode = true;

	public function query($sql)
	{
		$this->queries[] = $sql;
		if (strpos($sql, 'SHOW COLUMNS') !== false) {
			return (object)array('row' => array(), 'rows' => array(), 'num_rows' => $this->hasCode ? 1 : 0);
		}
		if (strpos($sql, 'INSERT') !== false) {
			$GLOBALS['order'][] = 'insert';
		}
		return (object)array('row' => array(), 'rows' => array(), 'num_rows' => 0);
	}

	public function escape($value)
	{
		return $value;
	}
}

class StubConfig
{
	public $data = array();
	public $log = array();

	public function has($key)
	{
		return isset($this->data[$key]);
	}

	public function get($key)
	{
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}

	public function set($key, $value)
	{
		$this->log[] = 'set';
		$GLOBALS['order'][] = 'set';
		$this->data[$key] = $value;
	}
}

class StubModel
{
	public $db;
	public $config;
}

class StubCore extends TranslateExpertCore
{
	public function __construct($model)
	{
		parent::__construct($model, false);
	}
}

$passed = 0;
$failed = 0;

function check($actual, $expected, $name)
{
	global $passed, $failed;
	if ($actual === $expected) {
		$passed++;
		echo " [PASS] " . VERSION . " $name\n";
	} else {
		$failed++;
		echo " [FAIL] " . VERSION . " $name (expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . ")\n";
	}
}

function build($data, $hasCode = true)
{
	$model = new StubModel();
	$model->db = new StubDb();
	$model->db->hasCode = $hasCode;
	$model->config = new StubConfig();
	$model->config->data = $data;
	return $model;
}

function inserts($model)
{
	$out = array();
	foreach ($model->db->queries as $sql) {
		if (strpos($sql, 'INSERT INTO') !== false) {
			$out[] = $sql;
		}
	}
	return $out;
}

$prefixed = version_compare(VERSION, '3.0', '>=');
$key = ($prefixed ? 'module_' : '') . 'client_translate_expert_language_code_map';
$group = ($prefixed ? 'module_' : '') . 'client_translate_expert';

$model = build(array());
$core = new StubCore($model);
$core->ensureLanguageCodeMapDefault();
$ins = inserts($model);
check(count($ins), 1, 'missing key: one INSERT');
$expected = "INSERT INTO `oc_setting` SET `store_id` = '0', `code` = '" . $group . "', `key` = '" . $key . "', `value` = '" . TranslateExpertLanguageCodeMap::DEFAULT_JSON . "', `serialized` = '0'";
check(isset($ins[0]) ? $ins[0] : null, $expected, 'missing key: INSERT text, group and key');
$expectedDelete = "DELETE `s` FROM `oc_setting` `s` JOIN (SELECT MIN(`setting_id`) AS `keep_id` FROM `oc_setting` WHERE `store_id` = '0' AND `key` = '" . $key . "') `k` WHERE `s`.`store_id` = '0' AND `s`.`key` = '" . $key . "' AND `s`.`setting_id` > `k`.`keep_id`";
$hasDelete = in_array($expectedDelete, $model->db->queries, true);
check($hasDelete, true, 'missing key: duplicate cleanup DELETE after the INSERT');
check($model->config->log, array('set'), 'missing key: config->set runs once');
check($order, array('insert', 'set'), 'missing key: config->set runs after the INSERT');
check($model->config->get($key), TranslateExpertLanguageCodeMap::DEFAULT_JSON, 'missing key: config holds the default');

$model = build(array());
$core = new StubCore($model);
$model->db->hasCode = false;
$core->ensureLanguageCodeMapDefault();
$ins = inserts($model);
check(isset($ins[0]) && strpos($ins[0], "`group` = '" . $group . "'") !== false, true, 'old group column name `group` is used when `code` is missing');

foreach (array('empty string' => '', 'custom value' => '{"x":"y"}') as $label => $value) {
	$model = build(array($key => $value));
	$core = new StubCore($model);
	$core->ensureLanguageCodeMapDefault();
	check(count(inserts($model)), 0, "$label: no INSERT");
	check($model->config->data[$key], $value, "$label: value stays");
	check($model->config->log, array(), "$label: config->set does not run");
}

$model = build(array($key => null));
$core = new StubCore($model);
$core->ensureLanguageCodeMapDefault();
check(count(inserts($model)), 1, 'null value: one INSERT');

$model = build(array());
$core = new StubCore($model);
$core->install();
check(count(inserts($model)), 1, 'install() writes the default');
$core->install();
check(count(inserts($model)), 1, 'install() twice: the second run writes nothing');

check(count($warnings) === 0, true, 'no PHP warnings' . (count($warnings) ? ': ' . implode('; ', $warnings) : ''));

echo VERSION . " Results: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
