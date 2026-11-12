<?php
/**
 * Static check of the Twig names in the OC4 templates.
 *
 * Usage:
 *   php tests/test_oc4_twig_filters.php
 *
 * The oc4.x package covers OpenCart 4.0.2.x (Twig v3.4.2) and 4.1.0.x (Twig v3.18.0).
 * A filter, test, function or tag that Twig does not define (for example |string) is a
 * Twig SyntaxError, and OpenCart 4 shows it only as "Could not load template".
 * This test reads every .twig file under sources/ and fails on a name that is not in the
 * lists below. The lists hold only the names that both Twig versions define in the
 * extensions that OpenCart 4 loads (Core, Escaper, and Debug on OC 4.1 only). It does not need Twig.
 *
 * The scanner works like the Twig lexer: it skips {# comments #} and {% verbatim %} blocks,
 * skips string literals, counts brackets (so "}}" in a string or a nested hash does not end a
 * block) and also checks the code in "#{...}" string interpolation.
 *
 * Limits: a call of an imported macro without a dot (after {% from ... import name %}) is
 * reported as an unknown function. Such a false positive is safe: the test fails and shows the name.
 *
 * Any PHP warning or notice fails the run.
 */

class Oc4TwigFiltersTest {
	// Filters of v3.4.2 and v3.18.0 (src/Extension/CoreExtension.php and EscaperExtension.php).
	// v3.18.0 also has 'find' and 'shuffle', but v3.4.2 (OpenCart 4.0.2.x) does not.
	private static $knownFilters = array(
		'abs', 'batch', 'capitalize', 'column', 'convert_encoding', 'date', 'date_modify', 'default',
		'e', 'escape', 'filter', 'first', 'format', 'join', 'json_encode', 'keys', 'last',
		'length', 'lower', 'map', 'merge', 'nl2br', 'number_format', 'raw', 'reduce', 'replace',
		'reverse', 'round', 'slice', 'sort', 'spaceless', 'split', 'striptags', 'title',
		'trim', 'upper', 'url_encode',
	);

	// Tests of v3.4.2 and v3.18.0 (first word). v3.18.0 also has 'mapping' and 'sequence'.
	private static $knownTests = array(
		'constant', 'defined', 'divisible', 'empty', 'even', 'iterable', 'none', 'null', 'odd', 'same',
	);

	// Functions of v3.4.2 and v3.18.0. 'parent', 'block' and 'attribute' are parser built-ins in v3.4.2.
	// Not in the list: 'enum' and 'enum_cases' (v3.18.0 only), 'dump' (DebugExtension, which
	// OpenCart 4.0.2.x does not load) and 'template_from_string' (StringLoaderExtension, not loaded).
	private static $knownFunctions = array(
		'attribute', 'block', 'constant', 'cycle', 'date', 'include', 'max', 'min', 'parent', 'random',
		'range', 'source',
	);

	// Tags of v3.4.2 and v3.18.0 with their end and branch words. Not in the list: 'guard' and
	// 'types' (v3.18.0 only) and 'sandbox' (SandboxExtension, not loaded).
	private static $knownTags = array(
		'apply', 'autoescape', 'block', 'deprecated', 'do', 'embed', 'extends', 'flush', 'for', 'from',
		'if', 'import', 'include', 'macro', 'set', 'use', 'verbatim', 'with',
		'else', 'elseif', 'endapply', 'endautoescape', 'endblock', 'endembed', 'endfor', 'endif',
		'endmacro', 'endset', 'endverbatim', 'endwith',
	);

	// Expression operators that a "(" can follow.
	private static $operators = array('not', 'and', 'or', 'in', 'is', 'matches', 'with', 'starts', 'ends');

	private $passed = 0;
	private $failed = 0;

	public function run()
	{
		echo "=== OC4 Twig Name Tests ===\n\n";

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
	 * @return string[] "<kind> <name>" for each name in the Twig code of $source that is not in the lists
	 */
	public static function unknownNames($source)
	{
		$unknown = array();
		foreach (self::blocks($source) as $block) {
			$code = $block['code'];
			if ($block['type'] === 'tag') {
				if (!preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)(.*)$/s', $code, $m)) {
					continue;
				}
				$tag = $m[1];
				$code = $m[2];
				if (!in_array($tag, self::$knownTags, true)) {
					$unknown[] = 'tag ' . $tag;
				}
				if ($tag === 'apply') {
					// {% apply upper|trim %} names filters without a leading pipe.
					$code = '|' . $code;
				} elseif ($tag === 'macro' || $tag === 'block') {
					// The name after these tags is not a function call.
					$code = preg_replace('/^\s*[A-Za-z_][A-Za-z0-9_]*/', '', $code);
				}
			}
			$unknown = array_merge($unknown, self::unknownInExpression($code));
		}
		return array_values(array_unique($unknown));
	}

	private static function unknownInExpression($code)
	{
		$unknown = array();

		preg_match_all('/\|\s*([A-Za-z_][A-Za-z0-9_]*)/', $code, $m);
		foreach ($m[1] as $name) {
			if (!in_array($name, self::$knownFilters, true)) {
				$unknown[] = 'filter ' . $name;
			}
		}
		// ".x" keeps the arguments of the filter from looking like a call of the name before it.
		$code = preg_replace('/\|\s*[A-Za-z_][A-Za-z0-9_]*/', '.x', $code);

		preg_match_all('/\bis\s+(?:not\s+)?([A-Za-z_][A-Za-z0-9_]*)/', $code, $m);
		foreach ($m[1] as $name) {
			if (!in_array($name, self::$knownTests, true)) {
				$unknown[] = 'test ' . $name;
			}
		}
		$code = preg_replace('/\bis\s+(?:not\s+)?(?:divisible\s+by|same\s+as|[A-Za-z_][A-Za-z0-9_]*)/', '.x', $code);

		preg_match_all('/(?<![A-Za-z0-9_.$])([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $code, $m);
		foreach ($m[1] as $name) {
			if (!in_array($name, self::$operators, true) && !in_array($name, self::$knownFunctions, true)) {
				$unknown[] = 'function ' . $name;
			}
		}
		return $unknown;
	}

	/**
	 * Splits $source into Twig blocks. String literals in the code are replaced by '', and the
	 * code of each "#{...}" interpolation is added as a separate expression block.
	 *
	 * @return array[] array('type' => 'tag'|'expr', 'code' => string)
	 */
	private static function blocks($source)
	{
		$blocks = array();
		$len = strlen($source);
		$i = 0;
		while ($i < $len) {
			$start = strpos($source, '{', $i);
			if ($start === false || $start + 1 >= $len) {
				break;
			}
			$next = $source[$start + 1];
			if ($next === '#') {
				$end = strpos($source, '#}', $start + 2);
				$i = $end === false ? $len : $end + 2;
				continue;
			}
			if ($next !== '{' && $next !== '%') {
				$i = $start + 1;
				continue;
			}
			$close = $next === '{' ? '}}' : '%}';
			list($code, $extra, $i) = self::readCode($source, $start + 2, $close);
			$type = $next === '%' ? 'tag' : 'expr';
			$code = ltrim($code, '-~');
			$blocks[] = array('type' => $type, 'code' => rtrim($code, '-~'));
			foreach ($extra as $interpolation) {
				$blocks[] = array('type' => 'expr', 'code' => $interpolation);
			}
			if ($type === 'tag' && preg_match('/^\s*verbatim\b/', $code)) {
				// Twig does not parse anything up to {% endverbatim %}.
				if (preg_match('/\{%-?~?\s*endverbatim\s*[-~]?%\}/', $source, $m, PREG_OFFSET_CAPTURE, $i)) {
					$i = $m[0][1];
				} else {
					$i = $len;
				}
			}
		}
		return $blocks;
	}

	/**
	 * @return array (code with string literals replaced by '', interpolation codes, offset after $close)
	 */
	private static function readCode($source, $i, $close)
	{
		$len = strlen($source);
		$code = '';
		$extra = array();
		$depth = 0;
		while ($i < $len) {
			$c = $source[$i];
			if ($c === '"' || $c === "'") {
				$i++;
				while ($i < $len && $source[$i] !== $c) {
					if ($source[$i] === '\\') {
						$i += 2;
						continue;
					}
					if ($c === '"' && $source[$i] === '#' && $i + 1 < $len && $source[$i + 1] === '{') {
						list($inner, $innerExtra, $i) = self::readCode($source, $i + 2, '}');
						$extra[] = $inner;
						$extra = array_merge($extra, $innerExtra);
						continue;
					}
					$i++;
				}
				$i++;
				$code .= "''";
				continue;
			}
			if ($depth === 0 && substr($source, $i, strlen($close)) === $close) {
				return array($code, $extra, $i + strlen($close));
			}
			if ($c === '(' || $c === '[' || $c === '{') {
				$depth++;
			} elseif (($c === ')' || $c === ']' || $c === '}') && $depth > 0) {
				$depth--;
			}
			$code .= $c;
			$i++;
		}
		return array($code, $extra, $len);
	}

	private function testChecker()
	{
		echo "Checker:\n";
		$cases = array(
			array('{{ name|e }} {{ html|raw }} {{ a|replace({"x": "y"}) }}', array(), 'known filters pass'),
			array('{% if table|string in te_table %}x{% endif %}', array('filter string'), 'an unknown filter in a tag fails'),
			array("{{ value\n  | string }}", array('filter string'), 'an unknown filter with spaces and a new line fails'),
			array('{{ items|shuffle }} {{ items|find(i => i) }}', array('filter shuffle', 'filter find'), 'a Twig 3.18 filter that Twig 3.4.2 (OC 4.0.2.x) does not have fails'),
			array('{{ "a|string" }} <script>if (a || b) { x(); }</script>', array(), 'a pipe in a string or outside Twig is not a filter'),
			array('{% if a is defined and b is not empty and c is divisible by(3) and d is same as(e) %}{% endif %}', array(), 'known tests pass'),
			array('{% if a is sequence or b is not mapping %}{% endif %}', array('test sequence', 'test mapping'), 'a Twig 3.18 test that Twig 3.4.2 does not have fails'),
			array('{{ max(1, 2) }} {{ range(1, 3)|join(",") }} {{ product.getName() }} {% if not (a) and (b) in (c) %}{% endif %}', array(), 'known functions, methods and operators pass'),
			array('{{ enum("X") }} {{ dump(a) }} {{ template_from_string("x") }}', array('function enum', 'function dump', 'function template_from_string'), 'a function that OC 4.0.2.x does not have fails'),
			array('{% for k, v in items %}{% else %}{% endfor %}{% set x = 1 %}{% apply upper %}x{% endapply %}{% macro input(name) %}{% endmacro %}{% block body %}{% endblock %}', array(), 'known tags pass'),
			array('{% guard function enum %}{% endguard %}{% types {a: "int"} %}{% sandbox %}{% endsandbox %}', array('tag guard', 'tag endguard', 'tag types', 'tag sandbox', 'tag endsandbox'), 'a tag that OC 4.0.2.x does not have fails'),
			array('{% apply string %}x{% endapply %}', array('filter string'), 'an unknown filter in an apply tag fails'),
			array('{# old: {{ x|string }} {% guard %} #}', array(), 'a comment is not checked'),
			array('{% verbatim %}{{ x|string }}{% endverbatim %}', array(), 'a verbatim block is not checked'),
			array('{{ "}}"|string }}', array('filter string'), 'a "}}" in a string does not end the block'),
			array('{{ a|replace({"x": {"y": 1}})|string }}', array('filter string'), 'a nested hash does not end the block'),
			array('{{ "#{x|string}" }}', array('filter string'), 'the code in a string interpolation is checked'),
			array('{{- x|string -}}', array('filter string'), 'whitespace control is handled'),
		);
		foreach ($cases as $case) {
			$this->check(self::unknownNames($case[0]), $case[1], $case[2]);
		}
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
			$this->check(self::unknownNames(file_get_contents($file)), array(), substr($file, strlen($root) + 1) . ' uses only names of Twig 3.4.2 and 3.18.0');
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
