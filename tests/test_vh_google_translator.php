<?php
/**
 * Test script for VHGoogleTranslator
 *
 * Usage:
 *   php tests/test_vh_google_translator.php YOUR_GOOGLE_API_KEY
 *
 * Runs unit tests (no API key needed) and integration tests (require a valid key).
 */

require_once __DIR__ . '/../sources/upload/system/library/vh_google_translator.php';

class VHGoogleTranslatorTest {
	private $passed = 0;
	private $failed = 0;
	private $skipped = 0;
	private $apiKey;

	function __construct($apiKey = '')
	{
		$this->apiKey = $apiKey;
	}

	public function run()
	{
		echo "=== VHGoogleTranslator Tests ===\n\n";

		echo "--- Unit Tests (no API call) ---\n";
		$this->testConstructor();
		$this->testLanguageCodeMapping();
		$this->testFixFormatParametersSingle();
		$this->testFixFormatParametersArray();
		$this->testBatchSkipsEmptyStrings();

		if ($this->apiKey) {
			echo "\n--- Integration Tests (live API) ---\n";
			$this->testTranslateSingle();
			$this->testTranslateBatch();
			$this->testTranslateWithLanguageAlias();
			$this->testTranslateEmptyInBatch();
			$this->testTranslateHtmlFormat();
			$this->testApiErrorHandling();
		} else {
			echo "\n--- Integration Tests SKIPPED (no API key provided) ---\n";
			$this->skipped += 6;
		}

		echo "\n=== Results: {$this->passed} passed, {$this->failed} failed, {$this->skipped} skipped ===\n";
		return $this->failed === 0;
	}

	// --- Unit Tests ---

	private function testConstructor()
	{
		$translator = new VHGoogleTranslator('test-key');
		$this->assert($translator instanceof VHGoogleTranslator, 'Constructor creates instance');
	}

	private function testLanguageCodeMapping()
	{
		$translator = new TestableVHGoogleTranslator('test-key');

		$translator->translate('hello', 'ua', 'en');
		$lastOpts = $translator->getLastOptions();
		$this->assert($lastOpts['source'] === 'uk', 'Language code "ua" mapped to "uk"');

		$translator->translate('hello', 'en', 'ua');
		$lastOpts = $translator->getLastOptions();
		$this->assert($lastOpts['target'] === 'uk', 'Target "ua" mapped to "uk"');

		$translator->translate('hello', 'rs', 'en');
		$lastOpts = $translator->getLastOptions();
		$this->assert($lastOpts['source'] === 'sr', 'Language code "rs" mapped to "sr"');

		$translator->translate('hello', 'ge', 'en');
		$lastOpts = $translator->getLastOptions();
		$this->assert($lastOpts['source'] === 'ka', 'Language code "ge" mapped to "ka"');
	}

	private function testFixFormatParametersSingle()
	{
		$translator = new TestableVHGoogleTranslator('test-key');

		$method = new ReflectionMethod($translator, 'fixFormatParameters');
		$method->setAccessible(true);

		$result = $method->invoke($translator, 'Hello %s world', 'Привіт %с світ');
		$this->assert(strpos($result, '%s') !== false, 'fixFormatParameters restores %s from %с');

		$result2 = $method->invoke($translator, 'Count: %d items', 'Кількість: %д предметів');
		$this->assert(strpos($result2, '%d') !== false, 'fixFormatParameters restores %d from %д');
	}

	private function testFixFormatParametersArray()
	{
		$translator = new TestableVHGoogleTranslator('test-key');

		$method = new ReflectionMethod($translator, 'fixFormatParameters');
		$method->setAccessible(true);

		$originals = ['Hello %s!', 'Count %d.'];
		$translated = ['Привіт %с!', 'Кількість %д.'];
		$result = $method->invoke($translator, $originals, $translated);

		$this->assert(is_array($result), 'fixFormatParameters returns array for array input');
		$this->assert(count($result) === 2, 'fixFormatParameters returns same count');
	}

	private function testBatchSkipsEmptyStrings()
	{
		$translator = new TestableVHGoogleTranslator('test-key');

		$result = $translator->translateBatch(['', '  ', 'hello'], 'en', 'uk');
		$this->assert($result->success === 1, 'Batch returns success');
		$this->assert($result->texts[0] === '', 'Empty string passed through');
		$this->assert($result->texts[1] === '  ', 'Whitespace-only string passed through');
		$this->assert($result->texts[2] === 'hello_translated', 'Non-empty string was translated');
	}

	// --- Integration Tests ---

	private function testTranslateSingle()
	{
		$translator = new VHGoogleTranslator($this->apiKey);

		try {
			$result = $translator->translate('Hello', 'en', 'uk');
			$this->assert($result->success === 1, 'Single translate returns success');
			$this->assert(!empty($result->text), 'Single translate returns non-empty text');
			echo "   Translated 'Hello' -> '{$result->text}'\n";
		} catch (Exception $e) {
			$this->fail('Single translate threw exception: ' . $e->getMessage());
		}
	}

	private function testTranslateBatch()
	{
		$translator = new VHGoogleTranslator($this->apiKey);

		try {
			$result = $translator->translateBatch(['Hello', 'World'], 'en', 'uk');
			$this->assert($result->success === 1, 'Batch translate returns success');
			$this->assert(count($result->texts) === 2, 'Batch translate returns 2 results');
			$this->assert(!empty($result->texts[0]), 'Batch result[0] is non-empty');
			$this->assert(!empty($result->texts[1]), 'Batch result[1] is non-empty');
			echo "   Translated batch -> ['{$result->texts[0]}', '{$result->texts[1]}']\n";
		} catch (Exception $e) {
			$this->fail('Batch translate threw exception: ' . $e->getMessage());
		}
	}

	private function testTranslateWithLanguageAlias()
	{
		$translator = new VHGoogleTranslator($this->apiKey);

		try {
			$result = $translator->translate('Good morning', 'en', 'ua');
			$this->assert($result->success === 1, 'Translate with "ua" alias returns success');
			$this->assert(!empty($result->text), 'Translate with "ua" alias returns text');
			echo "   Translated 'Good morning' (ua alias) -> '{$result->text}'\n";
		} catch (Exception $e) {
			$this->fail('Translate with alias threw exception: ' . $e->getMessage());
		}
	}

	private function testTranslateEmptyInBatch()
	{
		$translator = new VHGoogleTranslator($this->apiKey);

		try {
			$result = $translator->translateBatch(['Hello', '', 'World'], 'en', 'uk');
			$this->assert($result->success === 1, 'Batch with empty returns success');
			$this->assert(count($result->texts) === 3, 'Batch with empty returns 3 results');
			$this->assert($result->texts[1] === '', 'Empty string preserved in batch');
			$this->assert(!empty($result->texts[0]), 'Non-empty items translated');
		} catch (Exception $e) {
			$this->fail('Batch with empty threw exception: ' . $e->getMessage());
		}
	}

	private function testTranslateHtmlFormat()
	{
		$translator = new VHGoogleTranslator($this->apiKey);

		try {
			$result = $translator->translate('<b>Hello</b> world', 'en', 'uk', 'html');
			$this->assert($result->success === 1, 'HTML translate returns success');
			$this->assert(strpos($result->text, '<b>') !== false, 'HTML tags preserved');
			echo "   Translated HTML -> '{$result->text}'\n";
		} catch (Exception $e) {
			$this->fail('HTML translate threw exception: ' . $e->getMessage());
		}
	}

	private function testApiErrorHandling()
	{
		$translator = new VHGoogleTranslator('invalid-key-12345');

		try {
			$translator->translate('Hello', 'en', 'uk');
			$this->fail('Expected exception for invalid API key');
		} catch (Exception $e) {
			$this->assert(
				strpos($e->getMessage(), 'Google Translate API error') !== false,
				'Invalid key throws descriptive error: ' . $e->getMessage()
			);
		}
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

	private function fail($message)
	{
		echo " [FAIL] {$message}\n";
		$this->failed++;
	}
}

/**
 * Testable subclass that stubs the API call for unit tests.
 */
class TestableVHGoogleTranslator extends VHGoogleTranslator {
	private $lastOptions = [];

	public function getLastOptions()
	{
		return $this->lastOptions;
	}

	protected function callGoogleTranslateApi($text, $options)
	{
		$this->lastOptions = $options;
		return $text . '_translated';
	}
}

// --- Run ---
$apiKey = isset($argv[1]) ? $argv[1] : '';

if (!$apiKey) {
	echo "Tip: pass your Google API key as argument to run integration tests:\n";
	echo "  php tests/test_vh_google_translator.php YOUR_API_KEY\n\n";
}

$test = new VHGoogleTranslatorTest($apiKey);
$success = $test->run();
exit($success ? 0 : 1);
