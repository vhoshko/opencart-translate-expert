<?php
/**
 * Test script for TranslateExpertGoogleClient
 *
 * Usage:
 *   php tests/test_translate_expert_google_client.php YOUR_GOOGLE_API_KEY
 *
 * Runs unit tests (no API key needed) and integration tests (require a valid key).
 */

require_once __DIR__ . '/../sources/upload/system/library/translate_expert/translate_expert_google_client.php';

class TranslateExpertGoogleClientTest {
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
		echo "=== TranslateExpertGoogleClient Tests ===\n\n";

		echo "--- Unit Tests (no API call) ---\n";
		$this->testConstructor();
		$this->testBatchSkipsEmptyStrings();
		$this->testBatchSendsSingleRequest();
		$this->testBatchKeepsZeroNullAndBlankPositions();

		if ($this->apiKey) {
			echo "\n--- Integration Tests (live API) ---\n";
			$this->testTranslateSingle();
			$this->testTranslateBatch();
			$this->testTranslateWithLanguageCode();
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
		$translator = new TranslateExpertGoogleClient('test-key');
		$this->assert($translator instanceof TranslateExpertGoogleClient, 'Constructor creates instance');
	}

	private function testBatchSkipsEmptyStrings()
	{
		$translator = new TestableTranslateExpertGoogleClient('test-key');

		$result = $translator->translateBatch(['', '  ', 'hello'], 'en', 'uk');
		$this->assert($result->success === 1, 'Batch returns success');
		$this->assert($result->texts[0] === '', 'Empty string passed through');
		$this->assert($result->texts[1] === '  ', 'Whitespace-only string passed through');
		$this->assert($result->texts[2] === 'hello_translated', 'Non-empty string was translated');
	}

	private function testBatchSendsSingleRequest()
	{
		$translator = new TestableTranslateExpertGoogleClient('test-key');

		$result = $translator->translateBatch(['one', 'two', '', null, '   ', 'three'], 'en', 'uk');

		$this->assert($translator->getBatchCallCount() === 1, 'Batch sends exactly one Google API request for multiple non-empty values');
		$this->assert($translator->getLastBatchTexts() === ['one', 'two', 'three'], 'Only non-empty values are sent to Google');

		$this->assert($result->texts[0] === 'one_translated', 'First non-empty value translated');
		$this->assert($result->texts[1] === 'two_translated', 'Second non-empty value translated');
		$this->assert($result->texts[2] === '', 'Empty string returned unchanged at its original position');
		$this->assert($result->texts[3] === null, 'Null value returned unchanged at its original position');
		$this->assert($result->texts[4] === '   ', 'Whitespace-only value returned unchanged at its original position');
		$this->assert($result->texts[5] === 'three_translated', 'Trailing non-empty value translated');
		$this->assert(count($result->texts) === 6, 'Result has same length as input');
	}

	private function testBatchKeepsZeroNullAndBlankPositions()
	{
		$translator = new TestableTranslateExpertGoogleClient('test-key');

		$result = $translator->translateBatch(array('0', null, '  ', 'one'), 'en', 'uk');
		$this->assert($translator->getLastBatchTexts() === array('one'), 'Zero, null and blank texts are not sent to Google');
		$this->assert($result->texts === array('0', null, '  ', 'one_translated'), 'Zero, null and blank texts keep their positions');

		$translator = new TestableTranslateExpertGoogleClient('test-key');
		$result = $translator->translateBatch(array('0', null, '  '), 'en', 'uk');
		$this->assert($translator->getBatchCallCount() === 0, 'No request is sent when no text can be translated');
		$this->assert($result->texts === array('0', null, '  '), 'Skipped texts come back unchanged');
		$this->assert(TranslateExpertGoogleClient::isTranslatableText('0') ? false : true, 'isTranslatableText rejects "0"');
		$this->assert(TranslateExpertGoogleClient::isTranslatableText('a') ? true : false, 'isTranslatableText accepts text');
	}

	// --- Integration Tests ---

	private function testTranslateSingle()
	{
		$translator = new TranslateExpertGoogleClient($this->apiKey);

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
		$translator = new TranslateExpertGoogleClient($this->apiKey);

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

	private function testTranslateWithLanguageCode()
	{
		$translator = new TranslateExpertGoogleClient($this->apiKey);

		try {
			$result = $translator->translate('Good morning', 'en', 'uk');
			$this->assert($result->success === 1, 'Translate with language code "uk" returns success');
			$this->assert(!empty($result->text), 'Translate with language code "uk" returns text');
			echo "   Translated 'Good morning' (code uk) -> '{$result->text}'\n";
		} catch (Exception $e) {
			$this->fail('Translate with language code threw exception: ' . $e->getMessage());
		}
	}

	private function testTranslateEmptyInBatch()
	{
		$translator = new TranslateExpertGoogleClient($this->apiKey);

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
		$translator = new TranslateExpertGoogleClient($this->apiKey);

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
		$translator = new TranslateExpertGoogleClient('invalid-key-12345');

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
class TestableTranslateExpertGoogleClient extends TranslateExpertGoogleClient {
	private $lastOptions = [];
	private $batchCallCount = 0;
	private $lastBatchTexts = [];

	public function getLastOptions()
	{
		return $this->lastOptions;
	}

	public function getBatchCallCount()
	{
		return $this->batchCallCount;
	}

	public function getLastBatchTexts()
	{
		return $this->lastBatchTexts;
	}

	protected function callGoogleTranslateApi($text, $options)
	{
		$this->lastOptions = $options;
		return $text . '_translated';
	}

	protected function callGoogleTranslateApiBatch($texts, $options)
	{
		$this->lastOptions = $options;
		$this->batchCallCount++;
		$this->lastBatchTexts = $texts;
		return array_map(function($text) { return $text . '_translated'; }, $texts);
	}
}

// --- Run ---
$apiKey = isset($argv[1]) ? $argv[1] : '';

if (!$apiKey) {
	echo "Tip: pass your Google API key as argument to run integration tests:\n";
	echo "  php tests/test_translate_expert_google_client.php YOUR_API_KEY\n\n";
}

$test = new TranslateExpertGoogleClientTest($apiKey);
$success = $test->run();
exit($success ? 0 : 1);
