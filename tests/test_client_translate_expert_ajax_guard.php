<?php
/**
 * Test script for the JSON error guard of the admin controller (runJsonAction)
 *
 * Usage:
 *   php tests/test_client_translate_expert_ajax_guard.php
 *
 * Unit tests only (no OpenCart, no DB, no API key). Any PHP warning or notice fails the run.
 */

class Controller {
	public static $registry = array();

	public function __get($key)
	{
		return isset(self::$registry[$key]) ? self::$registry[$key] : null;
	}
}

class StubLoader {
	public static $throw = null;

	public function model($route)
	{
		if (self::$throw) {
			throw self::$throw;
		}
	}

	public function language($route)
	{
		if (self::$throw) {
			throw self::$throw;
		}
	}
}

class StubLog {
	public $lines = array();

	public function write($message)
	{
		$this->lines[] = $message;
	}
}

class StubLanguage {
	public static $texts = array('error_internal' => 'Interner Fehler.', 'error_wrong_method' => 'Falsche Methode.');

	public function get($key)
	{
		return isset(self::$texts[$key]) ? self::$texts[$key] : $key;
	}
}

class StubConfig {
	public function get($key)
	{
		return null;
	}
}

class StubResponse {
	public $headers = array();
	public $output = null;

	public function addHeader($header)
	{
		$this->headers[] = $header;
	}

	public function setOutput($output)
	{
		$this->output = $output;
	}
}

class StubModel {
	public $throw = null;
	public $calls = array();

	public function parseTableList($raw)
	{
		return $raw === 'bad' ? false : array();
	}

	public function parseIdList($raw)
	{
		return array();
	}

	public function removeDataFromDb()
	{
		$this->calls[] = 'removeDataFromDb';
		if ($this->throw) {
			throw $this->throw;
		}
		return (object)array('success' => 1);
	}

	public function uninstall()
	{
		$this->calls[] = 'uninstall';
		if ($this->throw) {
			throw $this->throw;
		}
	}

	public function install()
	{
		$this->calls[] = 'install';
	}

	public function resetCharCount()
	{
		$this->calls[] = 'resetCharCount';
		if ($this->throw) {
			throw $this->throw;
		}
	}
}

require_once __DIR__ . '/../sources/upload/admin/controller/extension/module/client_translate_expert.php';

class ClientTranslateExpertAjaxGuardTest {
	private $passed = 0;
	private $failed = 0;
	private $model;
	private $response;
	private $log;
	private $request;

	public function run()
	{
		echo "=== Admin controller JSON guard Tests ===\n\n";

		set_error_handler(function ($errno, $errstr, $errfile, $errline) {
			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		});

		$this->testNormalPath();
		$this->testException();
		$this->testError();
		$this->testBadFilter();
		$this->testGetRejected();
		$this->testPostWorks();
		$this->testAllMethodsGuarded();
		$this->testInvalidUtf8Message();

		restore_error_handler();

		echo "\n=== Results: {$this->passed} passed, {$this->failed} failed, 0 skipped ===\n";
		return $this->failed === 0;
	}

	private function setUpController($method, $post, $get)
	{
		$this->model = new StubModel();
		$this->response = new StubResponse();
		$this->log = new StubLog();
		$this->request = new stdClass();
		$this->request->server = array('REQUEST_METHOD' => $method);
		$this->request->post = $post;
		$this->request->get = $get;

		$session = new stdClass();
		$session->data = array();

		Controller::$registry = array(
			'load' => new StubLoader(),
			'language' => new StubLanguage(),
			'config' => new StubConfig(),
			'session' => $session,
			'request' => $this->request,
			'response' => $this->response,
			'log' => $this->log,
			'model_extension_module_client_translate_expert' => $this->model,
		);

		return new ControllerExtensionModuleClientTranslateExpert();
	}

	private function decoded()
	{
		return json_decode($this->response->output, true);
	}

	private function testNormalPath()
	{
		echo "\nNormal path:\n";
		$controller = $this->setUpController('POST', array(), array());
		$controller->resetCharCount();
		$this->check($this->decoded(), array('success' => 1), 'body is the JSON result');
		$this->check($this->response->headers, array('Content-Type: application/json; Charset=UTF-8'), 'JSON header is set once');
		$this->check(count($this->log->lines), 0, 'no log line');
	}

	private function testException()
	{
		echo "\nException:\n";
		$controller = $this->setUpController('POST', array(), array());
		$this->model->throw = new RuntimeException('db down');
		$controller->resetCharCount();
		$body = $this->decoded();
		$this->check($body['success'], 0, 'success is 0');
		$this->check($body['error_code'], 2000, 'error_code is 2000');
		$this->check($body['message'], 'Interner Fehler.', 'message is the translated generic text');
		$this->check($body['additional_message'], 'RuntimeException: db down', 'additional_message has class and text');
		$this->check(count($this->log->lines), 1, 'one log line');
		$this->check(strpos($this->log->lines[0], 'db down') !== false, true, 'log line has the exception text');
		$this->check($this->response->headers, array('Content-Type: application/json; Charset=UTF-8'), 'JSON header is set');
	}

	private function testError()
	{
		echo "\nError (Throwable, not Exception):\n";
		if (!class_exists('Error')) {
			echo "  SKIP: PHP 5.x has no Error class\n";
			return;
		}
		$controller = $this->setUpController('POST', array(), array());
		$this->model->throw = new Error('boom');
		$controller->resetCharCount();
		$body = $this->decoded();
		$this->check($body['success'], 0, 'success is 0');
		$this->check($body['error_code'], 2000, 'error_code is 2000');
		$this->check($body['additional_message'], 'Error: boom', 'additional_message has class and text');
		$this->check(count($this->log->lines), 1, 'one log line');
	}

	private function testBadFilter()
	{
		echo "\nBad filter inside the closure:\n";
		$controller = $this->setUpController('POST', array('table' => 'bad'), array());
		$controller->removeDataFromDb();
		$this->check($this->decoded(), array('success' => 0, 'message' => 'error_bad_filter'), 'body is the bad filter error');
		$this->check(count($this->log->lines), 0, 'no log line');
		$this->check($this->model->calls, array(), 'model is not called');
	}

	private function testGetRejected()
	{
		echo "\nGET request:\n";
		$controller = $this->setUpController('GET', array(), array('table' => 'product'));
		$controller->removeDataFromDb();
		$body = $this->decoded();
		$this->check($body['success'], 0, 'removeDataFromDb: success is 0');
		$this->check($body['error_code'], 2001, 'removeDataFromDb: error_code is 2001');
		$this->check($body['message'], 'Falsche Methode.', 'removeDataFromDb: message is translated');
		$this->check($this->model->calls, array(), 'removeDataFromDb: model is not called');
		$this->check(count($this->log->lines), 0, 'removeDataFromDb: no log line');

		$controller = $this->setUpController('GET', array(), array());
		$controller->resetCache();
		$body = $this->decoded();
		$this->check($body['success'], 0, 'resetCache: success is 0');
		$this->check($body['error_code'], 2001, 'resetCache: error_code is 2001');
		$this->check($this->model->calls, array(), 'resetCache: model is not called');
	}

	private function testPostWorks()
	{
		echo "\nPOST request:\n";
		$controller = $this->setUpController('POST', array('table' => 'product', 'langIdTo' => '2'), array());
		$controller->removeDataFromDb();
		$this->check($this->decoded(), array('success' => 1), 'removeDataFromDb: body is the model result');
		$this->check($this->model->calls, array('removeDataFromDb'), 'removeDataFromDb: model is called');

		$controller = $this->setUpController('POST', array(), array());
		$controller->resetCache();
		$this->check($this->decoded(), array('success' => true), 'resetCache: success is true');
		$this->check($this->model->calls, array('uninstall', 'install'), 'resetCache: model uninstall and install are called');
	}

	private function testAllMethodsGuarded()
	{
		echo "\nEvery JSON method returns the JSON error on an exception:\n";
		$methods = array('translate', 'translateTable', 'analizeTableDetail', 'analize', 'removeDataFromDb', 'analizeLocalization', 'translatedCharCountInfo', 'resetCharCount', 'resetCache', 'saveLocalizationFile');
		foreach ($methods as $method) {
			$controller = $this->setUpController('POST', array('table' => 'product', 'langIdFrom' => '1', 'langIdTo' => '2'), array('langIdFrom' => '1', 'langIdTo' => '2'));
			StubLoader::$throw = new RuntimeException('guard');
			$controller->$method();
			StubLoader::$throw = null;
			$body = $this->decoded();
			$this->check(is_array($body) ? $body['error_code'] : null, 2000, "$method: error_code is 2000");
			$this->check(count($this->log->lines), 1, "$method: one log line");
			$this->check(is_array($body) ? $body['message'] : null, 'Internal error.', "$method: message falls back to English when the language load fails");
		}
	}

	private function testInvalidUtf8Message()
	{
		echo "\nException message with invalid UTF-8:\n";
		$controller = $this->setUpController('POST', array(), array());
		$this->model->throw = new RuntimeException("bad \xE9 byte");
		$controller->resetCharCount();
		$body = $this->decoded();
		$this->check(is_array($body) ? $body['error_code'] : null, 2000, 'the body is valid JSON with error_code 2000');
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

$test = new ClientTranslateExpertAjaxGuardTest();
exit($test->run() ? 0 : 1);
