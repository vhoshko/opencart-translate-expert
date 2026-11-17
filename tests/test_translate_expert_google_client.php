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
		$this->testBatchSplitsByMaxSegments();
		$this->testBatchSegmentCapWithLongTexts();

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

	private function testBatchSplitsByMaxSegments()
	{
		$translator = new TestableTranslateExpertGoogleClient('test-key');

		$texts = array();
		$translatable = 0;
		for ($i = 0; $i < 300; $i++)
		{
			if ($i === 5)
				$texts[] = '';
			elseif ($i === 150)
				$texts[] = null;
			elseif ($i === 250)
				$texts[] = '   ';
			else
			{
				$texts[] = 't' . $i;
				$translatable++;
			}
		}

		$result = $translator->translateBatch($texts, 'en', 'uk');
		$sizes = $translator->getBatchSizes();

		$this->assert(max($sizes) <= TranslateExpertGoogleClient::GOOGLE_TRANSLATE_MAX_SEGMENTS, 'No Google request has more texts than GOOGLE_TRANSLATE_MAX_SEGMENTS');
		$this->assert(count($sizes) === 3, 'Texts are split into 3 Google requests');
		$this->assert(array_sum($sizes) === $translatable, 'The batch sizes add up to the number of translatable texts');
		$this->assert(count($result->texts) === 300, 'Result has same length as input');

		$ok = true;
		foreach ($texts as $i => $text)
		{
			if (!isset($result->texts[$i]) && $text !== null)
				$ok = false;
			elseif ($i === 5 || $i === 150 || $i === 250)
				$ok = $ok && $result->texts[$i] === $text;
			else
				$ok = $ok && isset($result->texts[$i]) && $result->texts[$i] === $text . '_translated';
		}
		$this->assert($ok, 'Each text keeps its position, and empty, null and blank texts come back unchanged');
	}

	private function testBatchSegmentCapWithLongTexts()
	{
		$translator = new TestableTranslateExpertGoogleClient('test-key');

		$long = str_repeat('a', TranslateExpertGoogleClient::GOOGLE_TRANSLATE_MAX_LENGTH + 10);
		$texts = array();
		for ($i = 0; $i < 250; $i++)
			$texts[] = 'x' . $i;
		array_splice($texts, 100, 0, array($long));
		array_splice($texts, 150, 0, array($long));
		$texts[] = $long;

		$result = $translator->translateBatch($texts, 'en', 'uk');
		$sizes = $translator->getBatchSizes();

		$this->assert(count($sizes) > 0 && max($sizes) <= TranslateExpertGoogleClient::GOOGLE_TRANSLATE_MAX_SEGMENTS, 'Long texts do not break the segment cap of the batches');
		$this->assert(count($result->texts) === count($texts), 'Result has same length as input with long texts');

		$ok = true;
		foreach ($texts as $i => $text)
		{
			// The stub adds the suffix to each chunk of a long text, so remove it before the compare.
			$ok = $ok && isset($result->texts[$i]) && str_replace('_translated', '', $result->texts[$i]) === $text;
			if (strlen($text) <= TranslateExpertGoogleClient::GOOGLE_TRANSLATE_MAX_LENGTH)
				$ok = $ok && $result->texts[$i] === $text . '_translated';
		}
		$this->assert($ok, 'Short and long texts keep their positions around the segment cap');
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
	private $batchSizes = [];

	public function getLastOptions()
	{
		return $this->lastOptions;
	}

	public function getBatchCallCount()
	{
		return $this->batchCallCount;
	}

	public function getBatchSizes()
	{
		return $this->batchSizes;
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
		$this->batchSizes[] = count($texts);
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
