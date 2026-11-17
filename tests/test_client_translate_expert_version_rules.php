<?php
/**
 * Test script for the OpenCart version rules of the admin controller, the model and the XML header
 *
 * Usage:
 *   php tests/test_client_translate_expert_version_rules.php
 *
 * Unit tests only (no OpenCart, no DB, no API key). Any PHP warning or notice fails the run.
 * The helpers take the version as an argument, so the test defines no VERSION constant.
 */

class Controller {
	public $registry = array();

	public function __get($key)
	{
		return isset($this->registry[$key]) ? $this->registry[$key] : null;
	}

	public function __set($key, $value)
	{
		$this->registry[$key] = $value;
	}
}

class Model {
}

define('DIR_SYSTEM', __DIR__ . '/../sources/upload/system/');

require_once __DIR__ . '/../sources/upload/system/library/client_translate_expert_cron_helper.php';
require_once __DIR__ . '/../sources/upload/admin/controller/extension/module/client_translate_expert.php';
require_once __DIR__ . '/../sources/upload/admin/model/extension/module/client_translate_expert.php';

class ClientTranslateExpertVersionRulesTest {
	private $passed = 0;
	private $failed = 0;

	private $versions = array('1.5.6.4', '2.0.3.1', '2.1.0.2', '2.2.0.0', '2.3.0.2', '3.0.0.0', '3.0.3.8', '4.0.2.3', '4.1.0.0');

	public function run()
	{
		echo "=== Version rules Tests ===\n\n";

		set_error_handler(function ($errno, $errstr, $errfile, $errline) {
			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		});

		$this->testTokenName();
		$this->testTokenQuery();
		$this->testSettingKey();
		$this->testPrefixSettingKeys();
		$this->testExtensionListRoute();
		$this->testRouteMethodSeparator();
		$this->testNeedsPhpTemplateEngine();
		$this->testLanguageImageUrl();
		$this->testXmlHeader();
		$this->testSourceRules();

		restore_error_handler();

		echo "\n=== Results: {$this->passed} passed, {$this->failed} failed, 0 skipped ===\n";
		return $this->failed === 0;
	}

	private function call($class, $method, $args, $object = null)
	{
		$reflection = new ReflectionMethod($class, $method);
		$reflection->setAccessible(true);
		return $reflection->invokeArgs($object, $args);
	}

	private function testTokenName()
	{
		echo "\ntokenName:\n";
		$expected = array('1.5.6.4' => 'token', '2.3.0.2' => 'token', '3.0.0.0' => 'user_token', '3.0.3.8' => 'user_token', '4.0.2.3' => 'user_token');
		foreach ($expected as $version => $name) {
			$this->check($this->call('ControllerExtensionModuleClientTranslateExpert', 'tokenName', array($version)), $name, "$version uses $name");
		}
	}

	private function testTokenQuery()
	{
		echo "\ntokenQuery:\n";
		$controller = new ControllerExtensionModuleClientTranslateExpert();
		$session = new stdClass();
		$session->data = array('token' => 'a', 'user_token' => 'b');
		$controller->session = $session;
		$this->check($this->call('ControllerExtensionModuleClientTranslateExpert', 'tokenQuery', array('2.3.0.2'), $controller), 'token=a', '2.3.0.2 gives token=a');
		$this->check($this->call('ControllerExtensionModuleClientTranslateExpert', 'tokenQuery', array('3.0.3.8'), $controller), 'user_token=b', '3.0.3.8 gives user_token=b');
	}

	private function testSettingKey()
	{
		echo "\nsettingKey:\n";
		foreach ($this->versions as $version) {
			$expected = ClientTranslateExpertCronHelper::settingKey('client_translate_expert_status', $version);
			$this->check($this->call('ControllerExtensionModuleClientTranslateExpert', 'settingKey', array('client_translate_expert_status', $version)), $expected, "controller $version matches the cron helper");
			$this->check($this->call('ModelExtensionModuleClientTranslateExpert', 'settingKey', array('client_translate_expert_status', $version)), $expected, "model $version matches the cron helper");
		}
		$this->check($this->call('ControllerExtensionModuleClientTranslateExpert', 'settingKey', array('client_translate_expert', '2.3.0.2')), 'client_translate_expert', 'group code is bare below 3.0');
		$this->check($this->call('ControllerExtensionModuleClientTranslateExpert', 'settingKey', array('client_translate_expert', '3.0.3.8')), 'module_client_translate_expert', 'group code has the module_ prefix from 3.0');
	}

	private function testPrefixSettingKeys()
	{
		echo "\nprefixSettingKeys:\n";
		$post = array('client_translate_expert_status' => '1', 'client_translate_expert_key' => 'k', 'other' => 'x');
		$this->check($this->call('ControllerExtensionModuleClientTranslateExpert', 'prefixSettingKeys', array($post, '2.3.0.2')), $post, '2.3.0.2 keeps the array');
		$this->check(
			$this->call('ControllerExtensionModuleClientTranslateExpert', 'prefixSettingKeys', array($post, '3.0.3.8')),
			array('module_client_translate_expert_status' => '1', 'module_client_translate_expert_key' => 'k', 'other' => 'x'),
			'3.0.3.8 prefixes only the client_translate_expert_ keys'
		);
	}

	private function testExtensionListRoute()
	{
		echo "\nextensionListRoute:\n";
		$expected = array('1.5.6.4' => 'extension/module', '2.2.0.0' => 'extension/module', '2.3.0.2' => 'extension/extension', '3.0.3.8' => 'marketplace/extension', '4.0.2.3' => 'marketplace/extension');
		foreach ($expected as $version => $route) {
			$this->check($this->call('ControllerExtensionModuleClientTranslateExpert', 'extensionListRoute', array($version)), $route, "$version gives $route");
		}
	}

	private function testRouteMethodSeparator()
	{
		echo "\nrouteMethodSeparator:\n";
		$expected = array('3.0.3.8' => '/', '4.0.2.3' => '.', '4.1.0.0' => '.');
		foreach ($expected as $version => $separator) {
			$this->check($this->call('ControllerExtensionModuleClientTranslateExpert', 'routeMethodSeparator', array($version)), $separator, "$version gives '$separator'");
		}
	}

	private function testNeedsPhpTemplateEngine()
	{
		echo "\nneedsPhpTemplateEngine:\n";
		$expected = array('2.3.0.2' => false, '3.0.0.0' => true, '3.0.3.8' => true, '4.0.2.3' => false);
		foreach ($expected as $version => $flag) {
			$this->check($this->call('ControllerExtensionModuleClientTranslateExpert', 'needsPhpTemplateEngine', array($version)), $flag, "$version gives " . var_export($flag, true));
		}
	}

	private function testLanguageImageUrl()
	{
		echo "\nlanguageImageUrl:\n";
		$language = array('image' => 'gb.png');
		$this->check($this->call('ControllerExtensionModuleClientTranslateExpert', 'languageImageUrl', array('en-gb', $language, '1.5.6.4')), 'view/image/flags/gb.png', '1.5.6.4 uses the flags folder');
		$this->check($this->call('ControllerExtensionModuleClientTranslateExpert', 'languageImageUrl', array('en-gb', $language, '2.2.0.0')), 'language/en-gb/en-gb.png', '2.2.0.0 uses the language folder');
		$this->check($this->call('ControllerExtensionModuleClientTranslateExpert', 'languageImageUrl', array('en-gb', array('image' => 'http://x/gb.png'), '4.0.2.3')), 'http://x/gb.png', '4.0.2.3 uses the image field');
		$this->check($this->call('ControllerExtensionModuleClientTranslateExpert', 'languageImageUrl', array('en-gb', array(), '4.0.2.3')), '', '4.0.2.3 gives an empty string without the image field');
	}

	private function testXmlHeader()
	{
		echo "\nXML header:\n";
		$xml = file_get_contents(__DIR__ . '/../sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml');
		$this->check(strpos($xml, "\$teKeyPrefix = version_compare(VERSION, '3.0', '>=') ? 'module_' : '';") !== false, true, 'the header selects the setting prefix at runtime');
		$this->check(strpos($xml, "version_compare(VERSION, '2.3', '<') ? 'module' : 'extension/module'") !== false, true, 'the header emits the module prefix');
		$this->check(preg_match("/config->get\\('client_translate_expert_/", $xml), 0, 'the header has no bare setting read');
	}

	private function testSourceRules()
	{
		echo "\nSource rules:\n";
		$base = __DIR__ . '/../sources/upload/admin/';
		$ctrl = file_get_contents($base . 'controller/extension/module/client_translate_expert.php');
		$model = file_get_contents($base . 'model/extension/module/client_translate_expert.php');
		$this->check(strpos($ctrl, 'js_const_module_prefix = \\"extension/client_translate_expert/module\\"') !== false || strpos($ctrl, 'js_const_module_prefix = "extension/client_translate_expert/module"') !== false, true, 'the OC4 event emits the module prefix');
		$this->check(strpos($ctrl, 'js_const_module_method_separator') !== false, true, 'the OC4 event emits the method separator');
		// A bare setting read gives an empty value on OC 3.0 and 4.x. languages_json is not a setting.
		$bare = "/config->get\\(\\s*'client_translate_expert_(?!languages_json)/";
		$this->check(preg_match($bare, $ctrl), 0, 'the controller has no bare setting read');
		$this->check(preg_match($bare, $model), 0, 'the model has no bare setting read');
	}

	private function check($actual, $expected, $label)
	{
		if ($actual === $expected) {
			$this->passed++;
			echo "  PASS: $label\n";
		} else {
			$this->failed++;
			echo "  FAIL: $label (expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . ")\n";
		}
	}
}

$test = new ClientTranslateExpertVersionRulesTest();
exit($test->run() ? 0 : 1);
