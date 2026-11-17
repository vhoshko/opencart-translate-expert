<?php
/**
 * Test script for the language code map in the admin controller index(): default write, save guard, template data.
 *
 * Usage:
 *   php tests/test_client_translate_expert_language_code_map_save.php
 *
 * Stubs only (no OpenCart, no DB, no API key). Any PHP warning or notice fails the run.
 */

define('VERSION', '3.0.3.8');
define('HTTP_CATALOG', 'http://shop.test/');
define('DIR_LOGS', '/nonexistent/');

class Controller {
	public static $registry = array();

	public function __get($key)
	{
		return isset(self::$registry[$key]) ? self::$registry[$key] : null;
	}
}

class Redirected extends Exception {}

class Anything {
	public function __call($name, $args)
	{
		return array();
	}
}

class StubLoader {
	public function model($route) {}

	public function language($route) {}

	public function controller($route)
	{
		return '';
	}

	public function view($route, $data)
	{
		Controller::$registry['response']->viewData = $data;
		return '';
	}
}

class StubLanguage {
	public function get($key)
	{
		return 'L:' . $key;
	}
}

class StubConfig {
	public $data = array();

	public function get($key)
	{
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}

	public function has($key)
	{
		return isset($this->data[$key]);
	}

	public function set($key, $value)
	{
		$this->data[$key] = $value;
	}
}

class StubResponse {
	public $viewData = null;

	public function setOutput($output) {}

	public function redirect($url)
	{
		throw new Redirected($url);
	}
}

class StubUser {
	public function hasPermission($type, $route)
	{
		return true;
	}
}

class StubUrl {
	public function link($route, $args = '', $secure = true)
	{
		return $route . '?' . $args;
	}
}

class StubDocument {
	public function setTitle($title) {}
}

class StubSettingModel {
	public $saved = array();

	public function editSetting($code, $data)
	{
		$this->saved[] = array($code, $data);
	}
}

class StubModuleModel {
	public $defaultCalls = 0;

	public function ensureLanguageCodeMapDefault()
	{
		$this->defaultCalls++;
	}

	public function normalizeLanguageCodeMap($json)
	{
		return TranslateExpertLanguageCodeMap::normalize($json);
	}

	public function getLanguageCodeMapDefault()
	{
		return TranslateExpertLanguageCodeMap::DEFAULT_JSON;
	}

	public function prepareCounterSettings() {}

	public function clampCronBulkAmount($value)
	{
		return 15;
	}

	public function isValidCronSecret($value)
	{
		return false;
	}

	public function getTables()
	{
		return array();
	}

	public function __call($name, $args)
	{
		return 0;
	}
}

define('DIR_SYSTEM', __DIR__ . '/../sources/upload/system/');
require_once DIR_SYSTEM . 'library/translate_expert/translate_expert_language_code_map.php';
require_once __DIR__ . '/../sources/upload/admin/controller/extension/module/client_translate_expert.php';

class LanguageCodeMapSaveTest {
	private $passed = 0;
	private $failed = 0;

	public function run()
	{
		echo "=== Language code map controller Tests ===\n\n";

		set_error_handler(function ($errno, $errstr, $errfile, $errline) {
			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		});

		$this->testGoodMap();
		$this->testEmptyMap();
		$this->testBadMap();
		$this->testMissingMap();
		$this->testGetPage();

		restore_error_handler();

		echo "\n=== Results: {$this->passed} passed, {$this->failed} failed ===\n";
		return $this->failed === 0;
	}

	private function enc($json)
	{
		return htmlspecialchars($json, ENT_COMPAT, 'UTF-8');
	}

	private function call($method, $post, $config)
	{
		$request = new stdClass();
		$request->server = array('REQUEST_METHOD' => $method);
		$request->post = $post;
		$request->get = array();
		$session = new stdClass();
		$session->data = array('user_token' => 'tok');
		$configStub = new StubConfig();
		$configStub->data = $config;
		$setting = new StubSettingModel();
		$module = new StubModuleModel();

		Controller::$registry = array(
			'load' => new StubLoader(),
			'language' => new StubLanguage(),
			'config' => $configStub,
			'session' => $session,
			'request' => $request,
			'response' => new StubResponse(),
			'user' => new StubUser(),
			'url' => new StubUrl(),
			'document' => new StubDocument(),
			'model_setting_setting' => $setting,
			'model_extension_module_client_translate_expert' => $module,
			'model_localisation_language' => new Anything(),
			'model_localisation_stock_status' => new Anything(),
			'model_catalog_manufacturer' => new Anything(),
			'model_catalog_category' => new Anything(),
		);

		$controller = new ControllerExtensionModuleClientTranslateExpert();
		$redirected = false;
		try {
			$controller->index();
		} catch (Redirected $e) {
			$redirected = true;
		}

		return array('setting' => $setting, 'module' => $module, 'redirected' => $redirected, 'data' => Controller::$registry['response']->viewData);
	}

	private function savedMap($result)
	{
		$saved = $result['setting']->saved;
		if (!$saved) {
			return null;
		}
		$post = $saved[0][1];
		return isset($post['module_client_translate_expert_language_code_map']) ? $post['module_client_translate_expert_language_code_map'] : 'KEY_MISSING';
	}

	private function testGoodMap()
	{
		echo "\nGood map:\n";
		$r = $this->call('POST', array('client_translate_expert_language_code_map' => $this->enc('{" UA ":" uk ","x":"y"}')), array());
		$this->check(count($r['setting']->saved), 1, 'editSetting runs once');
		$this->check($this->savedMap($r), '{"ua":"uk","x":"y"}', 'the normalized JSON is saved with the module_ key');
		$this->check($r['redirected'], true, 'the save redirects');
		$this->check($r['module']->defaultCalls, 1, 'the default write runs once at the start of index()');
		$r = $this->call('POST', array('client_translate_expert_language_code_map' => $this->enc(TranslateExpertLanguageCodeMap::DEFAULT_JSON)), array());
		$this->check($this->savedMap($r), TranslateExpertLanguageCodeMap::DEFAULT_JSON, 'the escaped default JSON is saved as raw JSON, with no entity');
		$r = $this->call('POST', array('client_translate_expert_language_code_map' => $this->enc('{"0":"a","1":"b"}')), array());
		$this->check($this->savedMap($r), '{"0":"a","1":"b"}', 'numeric keys are saved as an object');
	}

	private function testEmptyMap()
	{
		echo "\nEmpty map:\n";
		$r = $this->call('POST', array('client_translate_expert_language_code_map' => ''), array('module_client_translate_expert_language_code_map' => '{"a":"b"}'));
		$this->check($this->savedMap($r), '', 'an empty map is saved as an empty string');
	}

	private function testBadMap()
	{
		echo "\nBad map:\n";
		$bad = '{"ok":"a","ua":"u k"}';
		$r = $this->call('POST', array('client_translate_expert_language_code_map' => $this->enc($bad)), array('module_client_translate_expert_language_code_map' => '{"a":"b"}'));
		$this->check(count($r['setting']->saved), 0, 'editSetting does not run');
		$this->check($r['redirected'], false, 'no redirect');
		$this->check(isset($r['data']['error_language_code_map']) ? $r['data']['error_language_code_map'] : null, '2: L:te_lcm_err_chars', 'the error text has the pair number and the language string');
		$this->check(isset($r['data']['error_warning']) ? $r['data']['error_warning'] : null, 'L:error_language_code_map 2: L:te_lcm_err_chars', 'the warning says that nothing was saved and shows the error');
		$this->check(isset($r['data']['client_translate_expert_language_code_map']) ? $r['data']['client_translate_expert_language_code_map'] : null, $bad, 'the form gets the decoded raw JSON (the template escapes it one time)');
	}

	private function testMissingMap()
	{
		echo "\nMissing map in the POST:\n";
		$r = $this->call('POST', array('client_translate_expert_key' => 'k'), array('module_client_translate_expert_language_code_map' => '{"a":"b"}'));
		$this->check($this->savedMap($r), '{"a":"b"}', 'the stored value is kept');
		$r = $this->call('POST', array('client_translate_expert_key' => 'k'), array('module_client_translate_expert_language_code_map' => ''));
		$this->check($this->savedMap($r), '', 'a stored empty map is kept');
	}

	private function testGetPage()
	{
		echo "\nGET page:\n";
		$r = $this->call('GET', array(), array('module_client_translate_expert_language_code_map' => '{"a":"b"}'));
		$d = $r['data'];
		$this->check(count($r['setting']->saved), 0, 'no save');
		$this->check($d['client_translate_expert_language_code_map'], '{"a":"b"}', 'the config value goes to the template');
		$this->check($d['te_language_code_map_default'], TranslateExpertLanguageCodeMap::DEFAULT_JSON, 'the default JSON goes to the template');
		$this->check($d['error_language_code_map'], '', 'no error text');
		$names = array('entry_language_code_map', 'help_language_code_map', 'te_settings_card_language_codes', 'te_lcm_add', 'te_lcm_reset', 'te_lcm_col_key', 'te_lcm_col_value', 'te_lcm_empty', 'te_lcm_delete');
		$ok = true;
		foreach ($names as $name) {
			if (!isset($d[$name]) || $d[$name] !== 'L:' . $name) {
				$ok = false;
				echo "    missing $name\n";
			}
		}
		$this->check($ok, true, 'the language strings go to the template');
		$i18n = json_decode($d['te_i18n_json'], true);
		$ok = true;
		foreach (array('te_lcm_reset_confirm', 'te_lcm_err_empty_key', 'te_lcm_err_empty_value', 'te_lcm_err_duplicate', 'te_lcm_err_chars', 'te_lcm_err_length', 'te_lcm_err_count', 'te_lcm_row_label') as $name) {
			if (!isset($i18n[$name])) {
				$ok = false;
				echo "    missing JS string $name\n";
			}
		}
		$this->check($ok, true, 'the JS strings are in te_i18n');
	}

	private function check($actual, $expected, $label)
	{
		if ($actual === $expected) {
			echo "  PASS: {$label}\n";
			$this->passed++;
		} else {
			echo "  FAIL: {$label} (expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . ")\n";
			$this->failed++;
		}
	}
}

$test = new LanguageCodeMapSaveTest();
exit($test->run() ? 0 : 1);
