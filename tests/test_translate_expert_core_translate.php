<?php
/**
 * Test script for the language code mapping and the format parameter fix of TranslateExpertCore::translateInternal.
 *
 * Usage:
 *   php tests/test_translate_expert_core_translate.php
 *
 * No database and no network: a stub Google client replaces the API calls.
 * Any PHP warning or notice fails the run.
 */

define('VERSION', '3.0.3.8');
define('DIR_SYSTEM', __DIR__ . '/../sources/upload/system/');
define('DB_PREFIX', 'oc_');
define('DB_DATABASE', 'test');

class Log
{
	public function __construct($file) {}

	public function write($message) {}
}

require_once DIR_SYSTEM . 'library/translate_expert/translate_expert_core.php';
require_once DIR_SYSTEM . 'library/translate_expert/translate_expert_google_client.php';

$warnings = array();
set_error_handler(function ($no, $str, $file, $line) use (&$warnings) {
	$warnings[] = "$str in $file:$line";
	return true;
});

class StubGoogleClient extends TranslateExpertGoogleClient
{
	public $options = array();
	public $singleCalls = 0;
	public $batchCalls = 0;
	public $batchTexts = array();
	public $answers = array();

	protected function callGoogleTranslateApi($text, $options)
	{
		$this->options = $options;
		$this->singleCalls++;
		return isset($this->answers[$text]) ? $this->answers[$text] : $text;
	}

	protected function callGoogleTranslateApiBatch($texts, $options)
	{
		$this->options = $options;
		$this->batchCalls++;
		$this->batchTexts = $texts;
		$out = array();
		foreach ($texts as $text)
			$out[] = isset($this->answers[$text]) ? $this->answers[$text] : $text;
		return $out;
	}
}

class StubCore extends TranslateExpertCore
{
	public $client;

	public function __construct()
	{
		$this->_debug = false;
		$this->client = new StubGoogleClient('key');
	}

	protected function createGoogleClient($googleApiKey)
	{
		return $this->client;
	}

	public function run($key, $text, $source, $target)
	{
		return $this->translateInternal($key, $text, $source, $target, 'text');
	}
}

$passed = 0;
$failed = 0;

function check($ok, $name)
{
	global $passed, $failed;
	if ($ok) {
		$passed++;
		echo " [PASS] $name\n";
	} else {
		$failed++;
		echo " [FAIL] $name\n";
	}
}

$maps = array('ua' => 'uk', 'rs' => 'sr', 'ge' => 'ka', 'cz' => 'cs');

foreach ($maps as $from => $to) {
	$core = new StubCore();
	$core->run('key', 'hello', $from, 'en');
	check($core->client->options['source'] === $to, "string source $from maps to $to");
	$core->run('key', 'hello', 'en', $from);
	check($core->client->options['target'] === $to, "string target $from maps to $to");
	$core->run('key', array('hello'), $from, 'en');
	check($core->client->options['source'] === $to, "array source $from maps to $to");
	$core->run('key', array('hello'), 'en', $from);
	check($core->client->options['target'] === $to, "array target $from maps to $to");
}

$core = new StubCore();
$core->run('key', 'hello', 'en', 'de');
check($core->client->options['source'] === 'en' && $core->client->options['target'] === 'de', 'other codes stay unchanged');

$core = new StubCore();
$core->client->answers = array('Hello %s world' => 'Привіт %с світ', 'Count: %d items' => 'Кількість: %д предметів');
$res = $core->run('key', 'Hello %s world', 'en', 'uk');
check($res->success === 1 && strpos($res->text, '%s') !== false && strpos($res->text, '%с') === false, 'string: %с is fixed to %s');
$res = $core->run('key', 'Count: %d items', 'en', 'uk');
check(strpos($res->text, '%d') !== false && strpos($res->text, '%д') === false, 'string: %д is fixed to %d');

$core = new StubCore();
$core->client->answers = array('A %s.' => 'А %с.', 'B %d.' => 'Б %д.');
$input = array('', 'A %s.', '   ', null, '0', 'B %d.', '');
$res = $core->run('key', $input, 'en', 'uk');
check($core->client->batchCalls === 1 && $core->client->batchTexts === array('A %s.', 'B %d.'), 'batch sends only translatable texts');
check($res->texts[0] === '' && $res->texts[2] === '   ' && $res->texts[3] === null && $res->texts[4] === '0' && $res->texts[6] === '', 'batch: skipped texts come back unchanged at their positions');
check(count($res->texts) === 7, 'batch keeps the result length');
check(strpos($res->texts[1], '%s') !== false && strpos($res->texts[1], '%с') === false, 'batch: %с is fixed at position 1');
check(strpos($res->texts[5], '%d') !== false && strpos($res->texts[5], '%д') === false, 'batch: %д is fixed at position 5');

$core = new StubCore();
$core->client->answers = array('A %s.' => 'А %с.');
$res = $core->run('key', array('0', 'A %s.'), 'en', 'uk');
check($res->texts[0] === '0' && strpos($res->texts[1], '%s') !== false, 'batch: a "0" text is skipped and the next text is fixed');
$core = new StubCore();
$core->client->answers = array('A %d.' => 'А %д.');
$res = $core->run('key', array('', 'A %d.', null, '   ', '0'), 'en', 'uk');
check($res->texts[0] === '' && $res->texts[2] === null && $res->texts[3] === '   ' && $res->texts[4] === '0' && strpos($res->texts[1], '%d') !== false && strpos($res->texts[1], '%д') === false, 'batch: skipped texts stay unchanged while the text with a format mark between them is fixed');
$core = new StubCore();
$res = $core->run('key', array('%s', '   ', '%d'), 'en', 'uk');
check($res->texts[1] === '   ', 'batch: blank text between real texts is not changed');

$core = new StubCore();
$core->client->answers = array('hello' => 'привіт', 'world' => 'світ');
$res = $core->run('key', array('a' => 'hello', 'b' => '', 'c' => 'world'), 'en', 'uk');
check(array_keys($res->texts) === array(0, 1, 2) && $res->texts[0] === 'привіт' && $res->texts[1] === '' && $res->texts[2] === 'світ', 'batch: string keys are re-indexed 0..n-1 and the skipped text keeps its position');

$core = new StubCore();
$res = $core->run('', 'hello', 'en', 'uk');
check($res->success === 0 && $res->error_code === 1004, 'empty key gives error 1004');

check(count($warnings) === 0, 'no PHP warnings' . (count($warnings) ? ': ' . implode('; ', $warnings) : ''));

echo "\n=== Results: $passed passed, $failed failed ===\n";
exit($failed === 0 ? 0 : 1);
