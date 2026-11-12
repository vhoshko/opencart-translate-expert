<?php
/**
 * Test script for ClientTranslateExpertCronHelper
 *
 * Usage:
 *   php tests/test_client_translate_expert_cron_helper.php
 *
 * Unit tests only (no OpenCart, no API key). Any PHP warning or notice fails the run.
 */

require_once __DIR__ . '/../sources/upload/system/library/client_translate_expert_cron_helper.php';

class ClientTranslateExpertCronHelperTest {
	private $passed = 0;
	private $failed = 0;

	public function run()
	{
		echo "=== ClientTranslateExpertCronHelper Tests ===\n\n";

		set_error_handler(function ($errno, $errstr, $errfile, $errline) {
			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		});

		$this->testSecretRules();
		$this->testSecretsMatch();
		$this->testParseParams();
		$this->testSettingKey();
		$this->testClampBulkAmount();

		restore_error_handler();

		echo "\n=== Results: {$this->passed} passed, {$this->failed} failed, 0 skipped ===\n";
		return $this->failed === 0;
	}

	private function testSecretRules()
	{
		echo "\nSecret rules:\n";
		$this->check(ClientTranslateExpertCronHelper::isValidSecret(''), false, 'empty secret is not valid');
		$this->check(ClientTranslateExpertCronHelper::isValidSecret(str_repeat('a', 15)), false, '15 characters are not valid');
		$this->check(ClientTranslateExpertCronHelper::isValidSecret(str_repeat('a', 16)), true, '16 characters are valid');
		$this->check(ClientTranslateExpertCronHelper::isValidSecret(str_repeat('a', 128)), true, '128 characters are valid');
		$this->check(ClientTranslateExpertCronHelper::isValidSecret(str_repeat('a', 129)), false, '129 characters are not valid');
		$this->check(ClientTranslateExpertCronHelper::isValidSecret('Ab1-_Ab1-_Ab1-_Z'), true, 'letters, digits, dash and underscore are valid');
		$this->check(ClientTranslateExpertCronHelper::isValidSecret(str_repeat('a', 15) . ' '), false, 'space is not valid');
		$this->check(ClientTranslateExpertCronHelper::isValidSecret(str_repeat('a', 15) . '&'), false, 'ampersand is not valid');
		$this->check(ClientTranslateExpertCronHelper::isValidSecret(str_repeat('a', 15) . '"'), false, 'quote is not valid');
		$this->check(ClientTranslateExpertCronHelper::isValidSecret(str_repeat('a', 15) . "\xD1\x96"), false, 'multibyte character is not valid');
		$this->check(ClientTranslateExpertCronHelper::isValidSecret(str_repeat('a', 16) . "\n"), false, 'trailing newline is not valid');
		$this->check(ClientTranslateExpertCronHelper::isValidSecret(null), false, 'null is not valid');
		$this->check(ClientTranslateExpertCronHelper::isValidSecret(array('a')), false, 'array is not valid');
	}

	private function testSecretsMatch()
	{
		echo "\nSecret compare:\n";
		$secret = 'abcdefghijklmnop';
		$this->check(ClientTranslateExpertCronHelper::secretsMatch($secret, $secret), true, 'same secret matches');
		$this->check(ClientTranslateExpertCronHelper::secretsMatch($secret, 'abcdefghijklmnoq'), false, 'one different character does not match');
		$this->check(ClientTranslateExpertCronHelper::secretsMatch($secret, 'abcdefghijklmno'), false, 'shorter secret does not match');
		$this->check(ClientTranslateExpertCronHelper::secretsMatch($secret, ''), false, 'empty secret does not match');
		$this->check(ClientTranslateExpertCronHelper::secretsMatch($secret, null), false, 'null does not match');
		$this->check(ClientTranslateExpertCronHelper::secretsMatch($secret, array($secret)), false, 'array does not match');
	}

	private function testParseParams()
	{
		echo "\nParameters:\n";
		$base = array('langIdFrom' => '1', 'langIdTo' => '2');

		$this->check(ClientTranslateExpertCronHelper::parseParams($base), array(
			'langIdFrom' => 1, 'langIdTo' => 2, 'mode' => 'both', 'table' => array(),
			'product_status' => false, 'product_quantity' => false, 'product_category_id' => array(), 'stock_status' => array(), 'manufacturer_id' => array(), 'bulk_amount' => '',
		), 'minimal parameters get the defaults');

		$full = array_merge($base, array(
			'mode' => 'only_empty', 'table' => 'oc_product_description',
			'product_status' => '1', 'product_quantity' => '1', 'product_category_id' => '15', 'stock_status' => '7', 'manufacturer_id' => '9', 'bulk_amount' => '500',
		));
		$this->check(ClientTranslateExpertCronHelper::parseParams($full), array(
			'langIdFrom' => 1, 'langIdTo' => 2, 'mode' => 'only_empty', 'table' => array('oc_product_description'),
			'product_status' => true, 'product_quantity' => true, 'product_category_id' => array(15), 'stock_status' => array(7), 'manufacturer_id' => array(9), 'bulk_amount' => 500,
		), 'all parameters are normalized');

		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('mode' => 'same_value'))) ['mode'], 'same_value', 'same_value mode is valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('mode' => 'bad'))), false, 'unknown mode is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('mode' => array('both')))), false, 'array mode is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array()), false, 'no language ids are not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array('langIdFrom' => '1')), false, 'missing langIdTo is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array('langIdFrom' => 'a', 'langIdTo' => '2')), false, 'non-numeric langIdFrom is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array('langIdFrom' => '1', 'langIdTo' => '2x')), false, 'langIdTo with letters is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array('langIdFrom' => '0', 'langIdTo' => '2')), false, 'zero language id is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array('langIdFrom' => '-1', 'langIdTo' => '2')), false, 'negative language id is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array('langIdFrom' => '2', 'langIdTo' => '2')), false, 'same from and to are not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array('langIdFrom' => '2', 'langIdTo' => '02')), false, 'same from and to with a leading zero are not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('product_category_id' => 'x'))), false, 'non-numeric category is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('stock_status' => '1 or 1=1'))), false, 'stock status with SQL text is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('manufacturer_id' => '1 or 1=1'))), false, 'manufacturer with SQL text is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('manufacturer_id' => '3,0'))), false, 'a manufacturer list with a zero item is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('table' => array('x')))), false, 'array table is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('bulk_amount' => '')))['bulk_amount'], '', 'empty bulk amount uses the saved setting');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('bulk_amount' => '999999')))['bulk_amount'], 100000, 'a big bulk amount is limited');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('bulk_amount' => '0'))), false, 'zero bulk amount is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('bulk_amount' => '10x'))), false, 'bulk amount with letters is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('bulk_amount' => array('10')))), false, 'array bulk amount is not valid');

		$flags = ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('product_status' => 'true', 'product_quantity' => '0')));
		$this->check($flags['product_status'] === false && $flags['product_quantity'] === false, true, 'a checkbox flag is set only by 1');

		$multi = ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('product_category_id' => '15,16', 'stock_status' => '15, 16 ,15', 'table' => ' a , b ,a,')));
		$this->check($multi['product_category_id'], array(15, 16), 'a list of categories is parsed');
		$this->check($multi['stock_status'], array(15, 16), 'a list of stock statuses is trimmed and unique');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('manufacturer_id' => '3, 4 ,3')))['manufacturer_id'], array(3, 4), 'a list of manufacturers is trimmed and unique');
		$this->check($multi['table'], array('a', 'b'), 'a list of tables is trimmed and unique');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('product_category_id' => '15,x'))), false, 'a list with a text item is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('stock_status' => '15,0'))), false, 'a list with a zero item is not valid');

		$this->check(ClientTranslateExpertCronHelper::parseIdList(null), array(), 'parseIdList: null is empty');
		$this->check(ClientTranslateExpertCronHelper::parseIdList(''), array(), 'parseIdList: empty text is empty');
		$this->check(ClientTranslateExpertCronHelper::parseIdList(5), array(5), 'parseIdList: integer is one item');
		$this->check(ClientTranslateExpertCronHelper::parseIdList(array(5)), false, 'parseIdList: array is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseIdList('1 or 1=1'), false, 'parseIdList: SQL text is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseIdList('3,,2,3'), array(3, 2), 'parseIdList: empty items are skipped, order is kept');
		$this->check(ClientTranslateExpertCronHelper::parseTableList(null), array(), 'parseTableList: null is empty');
		$this->check(ClientTranslateExpertCronHelper::parseTableList(''), array(), 'parseTableList: empty text is empty');
		$this->check(ClientTranslateExpertCronHelper::parseTableList(5), false, 'parseTableList: integer is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseTableList(array('a')), false, 'parseTableList: array is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseTableList(','), false, 'parseTableList: only a comma is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseTableList(' '), false, 'parseTableList: only a space is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseIdList(','), false, 'parseIdList: only a comma is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseIdList(' , '), false, 'parseIdList: only separators are not valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('table' => ','))), false, 'parseParams: table with only a comma is not valid');
		$this->check(ClientTranslateExpertCronHelper::parseTableList(' , '), false, 'parseTableList: only separators are not valid');

		$countOrValue = function ($r) { return is_array($r) ? count($r) : $r; };
		$ids100 = implode(',', range(1, 100));
		$ids101 = implode(',', range(1, 101));
		$this->check($countOrValue(ClientTranslateExpertCronHelper::parseIdList($ids100)), 100, 'parseIdList: 100 unique items are valid');
		$this->check(ClientTranslateExpertCronHelper::parseIdList($ids101), false, 'parseIdList: 101 unique items are not valid');
		$this->check($countOrValue(ClientTranslateExpertCronHelper::parseIdList($ids100 . ',1,2,3')), 100, 'parseIdList: 103 items with 100 unique are valid');
		$tables100 = array();
		for ($i = 1; $i <= 101; $i++)
			$tables100[] = 'table' . $i;
		$this->check($countOrValue(ClientTranslateExpertCronHelper::parseTableList(implode(',', array_slice($tables100, 0, 100)))), 100, 'parseTableList: 100 unique items are valid');
		$this->check(ClientTranslateExpertCronHelper::parseTableList(implode(',', $tables100)), false, 'parseTableList: 101 unique items are not valid');
		$this->check($countOrValue(ClientTranslateExpertCronHelper::parseTableList(implode(',', array_slice($tables100, 0, 100)) . ',table1,table2')), 100, 'parseTableList: 102 items with 100 unique are valid');
		$this->check(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('manufacturer_id' => $ids101))), false, 'parseParams: 101 manufacturer IDs are not valid');
		$this->check(is_array(ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('manufacturer_id' => $ids100)))), true, 'parseParams: 100 manufacturer IDs are valid');

		$empty = ClientTranslateExpertCronHelper::parseParams(array_merge($base, array('table' => '', 'product_category_id' => '', 'stock_status' => '', 'manufacturer_id' => '')));
		$this->check($empty['table'] === array() && $empty['product_category_id'] === array() && $empty['stock_status'] === array() && $empty['manufacturer_id'] === array(), true, 'empty table, category, stock status and manufacturer are kept empty');
	}

	private function testSettingKey()
	{
		echo "\nSetting key:\n";
		$name = 'client_translate_expert_cron_secret';
		$this->check(ClientTranslateExpertCronHelper::settingKey($name, '1.5.6.4'), $name, 'OpenCart 1.5 has no prefix');
		$this->check(ClientTranslateExpertCronHelper::settingKey($name, '2.3.0.2'), $name, 'OpenCart 2.3 has no prefix');
		$this->check(ClientTranslateExpertCronHelper::settingKey($name, '3.0.3.8'), 'module_' . $name, 'OpenCart 3.0 has the module_ prefix');
		$this->check(ClientTranslateExpertCronHelper::settingKey($name, '4.1.0.0'), 'module_' . $name, 'OpenCart 4.1 has the module_ prefix');
	}

	private function testClampBulkAmount()
	{
		echo "\nBulk amount:\n";
		$this->check(ClientTranslateExpertCronHelper::clampBulkAmount(''), 1000, 'empty value gives the default');
		$this->check(ClientTranslateExpertCronHelper::clampBulkAmount('0'), 1000, 'zero gives the default');
		$this->check(ClientTranslateExpertCronHelper::clampBulkAmount('1'), 1, 'one is kept');
		$this->check(ClientTranslateExpertCronHelper::clampBulkAmount('250'), 250, 'a normal value is kept');
		$this->check(ClientTranslateExpertCronHelper::clampBulkAmount('999999'), 100000, 'a big value is limited');
	}

	private function check($actual, $expected, $message)
	{
		if ($actual === $expected) {
			echo " [PASS] {$message}\n";
			$this->passed++;
		} else {
			echo " [FAIL] {$message} (expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . ")\n";
			$this->failed++;
		}
	}
}

$test = new ClientTranslateExpertCronHelperTest();
$success = $test->run();
exit($success ? 0 : 1);
