<?php
/**
 * Test script for TranslateExpertI18n
 *
 * Usage:
 *   php tests/test_translate_expert_i18n.php
 *
 * Unit tests only (no OpenCart, no DB, no API key). The tests make temp language folders.
 * Any PHP warning or notice fails the run.
 */

require_once __DIR__ . '/../sources/upload/system/library/translate_expert/translate_expert_i18n.php';

class TranslateExpertI18nTest {
	private $passed = 0;
	private $failed = 0;
	private $tmpDir = '';

	public function run()
	{
		echo "=== TranslateExpertI18n Tests ===\n\n";

		set_error_handler(function ($errno, $errstr, $errfile, $errline) {
			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		});

		$this->tmpDir = sys_get_temp_dir() . '/te_i18n_test_' . getmypid() . '_' . mt_rand(1000, 9999) . '/';
		mkdir($this->tmpDir, 0777, true);

		try {
			$this->testGlobalKeys();
			$this->testCandidateFiles();
			$this->testReadFile();
			$this->testPick();
			$this->testLoadGlobal();
			$this->testModuleLanguageFiles();
			$this->testToJson();
		} catch (Exception $e) {
			echo " [FAIL] unexpected exception: " . $e->getMessage() . "\n";
			$this->failed++;
		}

		$this->removeDir($this->tmpDir);
		restore_error_handler();

		echo "\n=== Results: {$this->passed} passed, {$this->failed} failed, 0 skipped ===\n";
		return $this->failed === 0;
	}

	private function testGlobalKeys()
	{
		echo "\nGlobal keys:\n";
		$keys = TranslateExpertI18n::globalKeys();
		$this->assertSame(true, is_array($keys) && count($keys) > 0, 'globalKeys() is a non-empty list');
		$allPrefixed = true;
		foreach ($keys as $key) {
			if (strpos($key, 'te_global_') !== 0) {
				$allPrefixed = false;
			}
		}
		$this->assertSame(true, $allPrefixed, 'every global key starts with te_global_');
		$this->assertSame(count($keys), count(array_unique($keys)), 'no duplicate global keys');
	}

	private function testCandidateFiles()
	{
		echo "\nCandidate files:\n";
		$sub = array('/extension/module/x.php', '/module/x.php');
		$this->assertSame(
			array('/l/en-gb/extension/module/x.php', '/l/en-gb/module/x.php', '/l/english/extension/module/x.php', '/l/english/module/x.php'),
			TranslateExpertI18n::candidateFiles('/l/', array('en-gb', 'english'), $sub),
			'each folder with each sub-path, in the given order'
		);
		$this->assertSame(
			array('/l/en/module/x.php', '/l/english/module/x.php'),
			TranslateExpertI18n::candidateFiles('/l/', array('en', 'english', 'en'), array('/module/x.php')),
			'duplicates are skipped'
		);
		$this->assertSame(
			array('/l/uk-ua/module/x.php'),
			TranslateExpertI18n::candidateFiles('/l/', array('', '..', '../admin', 'a/b', 'a.b', null, 5, array('x'), 'uk-ua'), array('/module/x.php')),
			'empty, path-like and non-string folder names are skipped'
		);
		$this->assertSame(array(), TranslateExpertI18n::candidateFiles('/l/', array(), $sub), 'no folders give no candidates');
	}

	private function testReadFile()
	{
		echo "\nRead file:\n";
		$file = $this->writeLang('read/ok.php', "\$_['a'] = 'A';\n\$_['b'] = 'B';\n");
		$this->assertSame(array('a' => 'A', 'b' => 'B'), TranslateExpertI18n::readFile($file), 'reads the $_ array');
		$this->assertSame(array(), TranslateExpertI18n::readFile($this->tmpDir . 'read/missing.php'), 'missing file gives an empty array');
		$this->assertSame(array(), TranslateExpertI18n::readFile(''), 'empty path gives an empty array');
		$this->assertSame(array(), TranslateExpertI18n::readFile($this->tmpDir . 'read'), 'a folder gives an empty array');
		$noArray = $this->writeLang('read/no_array.php', "\$other = 1;\n");
		$this->assertSame(array(), TranslateExpertI18n::readFile($noArray), 'file without $_ entries gives an empty array');
		$scalar = $this->writeLang('read/scalar.php', "\$_ = 'text';\n");
		$this->assertSame(array(), TranslateExpertI18n::readFile($scalar), 'file that sets $_ to a string gives an empty array');
	}

	private function testPick()
	{
		echo "\nPick:\n";
		$this->assertSame(
			array('a' => 'A', 'c' => ''),
			TranslateExpertI18n::pick(array('a' => 'A', 'b' => array('x'), 'c' => '', 'd' => 'D', 'e' => 5), array('a', 'b', 'c', 'e', 'z')),
			'keeps only requested keys with string values (empty string included)'
		);
	}

	private function testLoadGlobal()
	{
		echo "\nLoad global:\n";
		$keys = TranslateExpertI18n::globalKeys();
		$k0 = $keys[0];
		$k1 = $keys[1];

		$en = $this->writeLang('lg/en-gb/module/x.php', $this->langBody($keys, 'EN'));
		$uk = $this->writeLang('lg/uk-ua/module/x.php', "\$_['{$k0}'] = 'UK0';\n\$_['not_global'] = 'N';\n");
		$ukFull = $this->writeLang('lg/uk-full/module/x.php', $this->langBody($keys, 'UKF'));

		$result = TranslateExpertI18n::loadGlobal(array($uk), array($en));
		$this->assertSame('UK0', isset($result[$k0]) ? $result[$k0] : null, 'value from the admin language file wins');
		$this->assertSame('EN' . $k1, isset($result[$k1]) ? $result[$k1] : null, 'missing key comes from the English fallback');
		$this->assertSame(false, isset($result['not_global']), 'keys that are not global are not returned');
		$this->assertSame(count($keys), count($result), 'all global keys are filled');

		$result = TranslateExpertI18n::loadGlobal(array($this->tmpDir . 'lg/missing/x.php', $ukFull, $uk), array($en));
		$this->assertSame('UKF' . $k1, isset($result[$k1]) ? $result[$k1] : null, 'first existing candidate is used');
		$this->assertSame('UKF' . $k0, isset($result[$k0]) ? $result[$k0] : null, 'a later candidate does not overwrite the first');

		$result = TranslateExpertI18n::loadGlobal(array(), array($this->tmpDir . 'lg/missing/x.php', $en));
		$this->assertSame('EN' . $k0, isset($result[$k0]) ? $result[$k0] : null, 'no candidates: values from the first existing fallback');

		$this->assertSame(array(), TranslateExpertI18n::loadGlobal(array(), array()), 'no files give an empty array');
	}

	// The real module language files of the repo must define every global key with a string value.
	private function testModuleLanguageFiles()
	{
		echo "\nModule language files:\n";
		$base = __DIR__ . '/../sources/upload/admin/language/';
		foreach (array('en-gb', 'ru-ru', 'uk-ua') as $lang) {
			$file = $base . $lang . '/extension/module/client_translate_expert.php';
			$result = TranslateExpertI18n::loadGlobal(array($file), array());
			$missing = array_diff(TranslateExpertI18n::globalKeys(), array_keys($result));
			$this->assertSame(array(), array_values($missing), "{$lang} defines all global keys");
		}
		$sub = array('/extension/module/client_translate_expert.php', '/module/client_translate_expert.php');
		$result = TranslateExpertI18n::loadGlobal(
			TranslateExpertI18n::candidateFiles($base, array('en', 'english'), $sub),
			TranslateExpertI18n::candidateFiles($base, array('en-gb', 'english'), $sub)
		);
		$this->assertSame(count(TranslateExpertI18n::globalKeys()), count($result), 'OC 1.5-style folders that do not exist fall back to en-gb');
	}

	private function testToJson()
	{
		echo "\nTo JSON:\n";
		$this->assertSame('{}', TranslateExpertI18n::toJson(array()), 'empty array gives {} (an object, not [])');

		$json = TranslateExpertI18n::toJson(array('a' => '</script><script>alert("x")</script>', 'b' => "it's & \"q\""));
		$this->assertSame(false, strpos($json, '<') !== false || strpos($json, '>') !== false, 'no < or > in the JSON');
		$this->assertSame(false, strpos($json, "'") !== false, "no ' in the JSON");
		$this->assertSame(false, strpos($json, '&') !== false, 'no & in the JSON');
		$decoded = json_decode($json, true);
		$this->assertSame('</script><script>alert("x")</script>', isset($decoded['a']) ? $decoded['a'] : null, 'value with tags decodes to the same text');
		$this->assertSame("it's & \"q\"", isset($decoded['b']) ? $decoded['b'] : null, 'value with quotes decodes to the same text');

		$decoded = json_decode(TranslateExpertI18n::toJson(array('uk' => 'Перекласти')), true);
		$this->assertSame('Перекласти', isset($decoded['uk']) ? $decoded['uk'] : null, 'Cyrillic text decodes to the same text');

		$this->assertSame('{}', TranslateExpertI18n::toJson(array('bad' => "\xB1\x31")), 'invalid UTF-8 gives {}');
	}

	private function langBody(array $keys, $prefix)
	{
		$body = '';
		foreach ($keys as $key) {
			$body .= "\$_['{$key}'] = '{$prefix}{$key}';\n";
		}
		return $body;
	}

	private function writeLang($relPath, $body)
	{
		$file = $this->tmpDir . $relPath;
		if (!is_dir(dirname($file))) {
			mkdir(dirname($file), 0777, true);
		}
		file_put_contents($file, "<?php\n" . $body);
		return $file;
	}

	private function removeDir($dir)
	{
		if (!is_dir($dir)) {
			return;
		}
		foreach (scandir($dir) as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			$path = rtrim($dir, '/') . '/' . $item;
			if (is_dir($path)) {
				$this->removeDir($path);
			} else {
				unlink($path);
			}
		}
		rmdir($dir);
	}

	private function assertSame($expected, $actual, $message)
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

$test = new TranslateExpertI18nTest();
$success = $test->run();
exit($success ? 0 : 1);
