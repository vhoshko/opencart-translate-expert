<?php
/**
 * Test script for ClientTranslateExpertRouteFilter
 *
 * Usage:
 *   php tests/test_client_translate_expert_route_filter.php
 *
 * Unit tests only (no OpenCart, no API key). Any PHP warning or notice fails the run.
 */

require_once __DIR__ . '/../sources/upload/system/library/client_translate_expert_route_filter.php';

class ClientTranslateExpertRouteFilterTest {
	private $passed = 0;
	private $failed = 0;

	public function run()
	{
		echo "=== ClientTranslateExpertRouteFilter Tests ===\n\n";

		// Warnings such as "strpos(): Empty needle" (PHP < 8) must fail the test.
		set_error_handler(function ($errno, $errstr, $errfile, $errline) {
			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		});

		$this->testEmptyRules();
		$this->testExactMatch();
		$this->testPrefixMatch();
		$this->testStarMatchesAll();
		$this->testRuleCleanUp();
		$this->testFullUrlRules();
		$this->testPipeSeparator();
		$this->testMissingRoute();
		$this->testModulePageNeverExcluded();
		$this->testDefaultRoute();

		restore_error_handler();

		echo "\n=== Results: {$this->passed} passed, {$this->failed} failed, 0 skipped ===\n";
		return $this->failed === 0;
	}

	private function testEmptyRules()
	{
		echo "\nEmpty rules:\n";
		$this->check('catalog/product', '', false, 'empty setting excludes nothing');
		$this->check('catalog/product', "\n\r\n  \n", false, 'blank lines exclude nothing');
	}

	private function testExactMatch()
	{
		echo "\nExact match:\n";
		$rules = "extension/module/my_module1\nextension/module/my_module2";
		$this->check('extension/module/my_module1', $rules, true, 'first rule matches');
		$this->check('extension/module/my_module2', $rules, true, 'second rule matches');
		$this->check('extension/module/my_module3', $rules, false, 'other route does not match');
		$this->check('extension/module/my_module1/edit', $rules, false, 'exact rule does not match a method route');
	}

	private function testPrefixMatch()
	{
		echo "\nPrefix match:\n";
		$this->check('catalog/product', 'catalog/product*', true, 'catalog/product* matches the list page');
		$this->check('catalog/product/edit', 'catalog/product*', true, 'catalog/product* matches catalog/product/edit');
		$this->check('catalog/product.form', 'catalog/product*', true, 'catalog/product* matches catalog/product.form');
		$this->check('catalog/product', 'catalog/product/*', false, 'catalog/product/* does not match the list page');
		$this->check('catalog/category', 'catalog/product*', false, 'catalog/product* does not match catalog/category');
		$this->check('catalog/product/edit', 'catalog/*/edit', false, '* that is not the last character is a literal character');
		$this->check('catalog/*/edit', 'catalog/*/edit', true, '* that is not the last character matches only itself');
	}

	private function testStarMatchesAll()
	{
		echo "\nRule of only *:\n";
		foreach (array('*', '/*', '*/', 'route=*', 'index.php?route=*&user_token=x') as $rule) {
			$this->check('catalog/category', $rule, true, "'{$rule}' matches all routes");
		}
	}

	private function testRuleCleanUp()
	{
		echo "\nRule clean-up:\n";
		$this->check('extension/module/my_module', "  extension/module/my_module \r", true, 'spaces and \\r are trimmed');
		$this->check('extension/module/my_module', '/extension/module/my_module/', true, 'leading and trailing / are trimmed');
		$this->check('extension/module/my_module', 'route=extension/module/my_module', true, 'route= prefix is removed');
	}

	private function testFullUrlRules()
	{
		echo "\nFull admin URL rules:\n";
		$this->check('sale/order', 'http://shop.test/admin/index.php?route=sale/order&user_token=abc', true, 'URL with & is cut at &');
		$this->check('sale/order', 'index.php?route=sale/order&amp;user_token=abc', true, 'URL with &amp; (stored form) is cut at &');
		$this->check('sale/order/info', 'index.php?route=sale/order&user_token=abc', false, 'URL rule is still an exact rule');
		$this->check(null, 'http://shop.test/admin/index.php?user_token=abc', true, 'URL without route= means the dashboard');
		$this->check('catalog/product', 'http://shop.test/admin/index.php?user_token=abc', false, 'URL without route= does not match other pages');
		$this->check(null, 'http://shop.test/admin/index.php?token=abc', true, 'OC 1.5: URL without route= means common/home', 'common/home');
	}

	private function testPipeSeparator()
	{
		echo "\nMethod separator:\n";
		$this->check('catalog/category.form', 'catalog/category|*', true, '| in a rule matches . in the route');
		$this->check('catalog/category|form', 'catalog/category.form', true, '| in the route matches . in a rule');
	}

	private function testMissingRoute()
	{
		echo "\nMissing route:\n";
		$this->check(null, 'common/dashboard', true, 'null route means the default route', 'common/dashboard');
		$this->check('', 'common/dashboard', true, 'empty route means the default route', 'common/dashboard');
		$this->check(null, 'common/home', true, 'OC 1.5 default route common/home', 'common/home');
		$this->check(null, 'common/dashboard', false, 'OC 1.5: common/dashboard rule does not match', 'common/home');
		$this->check(null, 'common/*', true, 'prefix rule matches the default route');
		$this->check(null, 'catalog/*', false, 'other prefix rule does not match the default route');
	}

	private function testModulePageNeverExcluded()
	{
		echo "\nModule page:\n";
		$rules = "*\nextension/*\nextension/module/*";
		$this->check('module/client_translate_expert', $rules, false, 'OC 1.5–2.2 module page');
		$this->check('extension/module/client_translate_expert', $rules, false, 'OC 2.3–3.0 module page');
		$this->check('extension/client_translate_expert/module/client_translate_expert', $rules, false, 'OC4 module page');
		$this->check('extension/client_translate_expert/module/client_translate_expert.save', $rules, false, 'OC4 module page method');
	}

	private function testDefaultRoute()
	{
		echo "\nDefault route:\n";
		// The test runs without OpenCart, so VERSION is not defined.
		$actual = ClientTranslateExpertRouteFilter::defaultRoute();
		$this->assert(!defined('VERSION') && $actual === 'common/dashboard', "defaultRoute() without VERSION gives common/dashboard (got {$actual})");
	}

	// --- Helpers ---

	private function assert($condition, $message)
	{
		if ($condition) {
			echo " [PASS] {$message}\n";
			$this->passed++;
		} else {
			echo " [FAIL] {$message}\n";
			$this->failed++;
		}
	}

	private function check($route, $rules, $expected, $message, $defaultRoute = 'common/dashboard')
	{
		try {
			$actual = ClientTranslateExpertRouteFilter::isExcluded($route, $rules, $defaultRoute);
		} catch (ErrorException $e) {
			echo " [FAIL] {$message} (PHP error: {$e->getMessage()})\n";
			$this->failed++;
			return;
		}

		if ($actual === $expected) {
			echo " [PASS] {$message}\n";
			$this->passed++;
		} else {
			echo " [FAIL] {$message} (expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . ")\n";
			$this->failed++;
		}
	}
}

$test = new ClientTranslateExpertRouteFilterTest();
$success = $test->run();
exit($success ? 0 : 1);
