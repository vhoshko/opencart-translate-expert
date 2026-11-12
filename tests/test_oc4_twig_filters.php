<?php
/**
 * Static check of the Twig filters in the OC4 templates.
 *
 * Usage:
 *   php tests/test_oc4_twig_filters.php
 *
 * OpenCart 4.1.0.0 ships Twig v3.18.0. A filter that Twig does not define (for example |string)
 * is a Twig SyntaxError, and OpenCart 4 shows it only as "Could not load template".
 * This test reads every .twig file under sources/ and fails on a filter that is not in the
 * Twig 3.18 core list. It does not need Twig. Any PHP warning or notice fails the run.
 */

class Oc4TwigFiltersTest {
	// Filters of Twig v3.18.0 (src/Extension/CoreExtension.php and EscaperExtension.php).
	private static $knownFilters = array(
		'abs', 'batch', 'capitalize', 'column', 'convert_encoding', 'date', 'date_modify', 'default',
		'e', 'escape', 'filter', 'find', 'first', 'format', 'join', 'json_encode', 'keys', 'last',
		'length', 'lower', 'map', 'merge', 'nl2br', 'number_format', 'raw', 'reduce', 'replace',
		'reverse', 'round', 'shuffle', 'slice', 'sort', 'spaceless', 'split', 'striptags', 'title',
		'trim', 'upper', 'url_encode',
	);

	private $passed = 0;
	private $failed = 0;

	public function run()
	{
		echo "=== OC4 Twig Filter Tests ===\n\n";

		set_error_handler(function ($errno, $errstr, $errfile, $errline) {
			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		});

		$this->testChecker();
		$this->testTemplates();

		restore_error_handler();

		echo "\n=== Results: {$this->passed} passed, {$this->failed} failed, 0 skipped ===\n";
		return $this->failed === 0;
	}

	/**
	 * @return string[] Names of the filters in the Twig blocks of $source that Twig 3.18 does not define
	 */
	public static function unknownFilters($source)
	{
		$unknown = array();
		preg_match_all('/\{\{.*?\}\}|\{%.*?%\}/s', $source, $blocks);
		foreach ($blocks[0] as $block) {
			// String literals can contain a "|" that is not a filter.
			$code = preg_replace('/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"/s', "''", $block);
			preg_match_all('/\|\s*([A-Za-z_][A-Za-z0-9_]*)/', $code, $names);
			foreach ($names[1] as $name) {
				if (!in_array($name, self::$knownFilters, true)) {
					$unknown[] = $name;
				}
			}
		}
		return array_values(array_unique($unknown));
	}

	private function testChecker()
	{
		echo "Checker:\n";
		$this->check(self::unknownFilters('{{ name|e }} {{ html|raw }} {{ a|replace({"x": "y"}) }}'), array(), 'known filters pass');
		$this->check(self::unknownFilters('{% if table|string in te_table %}x{% endif %}'), array('string'), 'an unknown filter in a tag fails');
		$this->check(self::unknownFilters("{{ value\n  | string }}"), array('string'), 'an unknown filter with spaces and a new line fails');
		$this->check(self::unknownFilters('{{ "a|string" }} <script>if (a || b) {}</script>'), array(), 'a pipe in a string or outside Twig is not a filter');
	}

	private function testTemplates()
	{
		echo "\nTemplates:\n";
		$root = realpath(__DIR__ . '/../sources');
		$files = array();
		$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
		foreach ($it as $file) {
			if (substr($file->getFilename(), -5) === '.twig') {
				$files[] = $file->getPathname();
			}
		}
		sort($files);
		$this->check(count($files) > 0, true, 'at least one .twig file is found');
		foreach ($files as $file) {
			$this->check(self::unknownFilters(file_get_contents($file)), array(), substr($file, strlen($root) + 1) . ' uses only Twig 3.18 filters');
		}
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

$test = new Oc4TwigFiltersTest();
exit($test->run() ? 0 : 1);
