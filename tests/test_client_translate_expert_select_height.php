<?php
/**
 * Static test for the height of the multiple selects in the Site content tab (issue #100).
 *
 * Usage:
 *   php tests/test_client_translate_expert_select_height.php
 *
 * Reads the .tpl, the .oc4.twig and the CSS file. No OpenCart, no DB, no API key.
 * The table list, the category filter and the manufacturer filter show 15 rows. The class te-select-multi and its CSS rule keep the height set by the size attribute.
 */

$base = __DIR__ . '/../sources/upload/admin/view/';
$files = array(
	'tpl' => $base . 'template/extension/module/client_translate_expert.tpl',
	'oc4.twig' => $base . 'template/extension/module/client_translate_expert.oc4.twig',
);
$cssFile = $base . 'stylesheet/client_translate_expert.css';
foreach (array_merge($files, array('css' => $cssFile)) as $name => $path) {
	if (!is_file($path)) {
		echo "  FAIL: file is missing: {$path}\n";
		exit(1);
	}
}
// Remove the comments, so that a rule in a comment does not count.
$css = preg_replace('#/\*.*?\*/#s', '', file_get_contents($cssFile));

$passed = 0;
$failed = 0;

function check($ok, $label)
{
	global $passed, $failed;
	if ($ok) {
		echo "  PASS: {$label}\n";
		$passed++;
	} else {
		echo "  FAIL: {$label}\n";
		$failed++;
	}
}

// select id => rows
$lists = array(
	'te-analization-table' => 15,
	'te-analization-product-category-id-filter' => 15,
	'te-analization-product-manufacturer-filter' => 15,
);
foreach ($files as $name => $path) {
	echo "== {$name} ==\n";
	$html = file_get_contents($path);
	foreach ($lists as $id => $rows) {
		$found = preg_match('/<select\b[^\n]*\bid="' . $id . '"[^\n]*/', $html, $m) === 1;
		check($found, "{$id}: the select exists");
		$tag = $found ? $m[0] : '';
		check(strpos($tag, 'multiple="multiple"') !== false, "{$id}: it is a multiple select");
		check(strpos($tag, 'size="' . $rows . '"') !== false, "{$id}: it shows {$rows} rows (size=\"{$rows}\")");
		check(preg_match('/\bclass="[^"]*\bte-select-multi\b[^"]*"/', $tag) === 1, "{$id}: it has the class te-select-multi");
	}
	// Each multiple select tag has the class. A new multiple select without the class fails here.
	preg_match_all('/<select\b[^\n]*\bmultiple\b[^\n]*/', $html, $all);
	$withClass = 0;
	foreach ($all[0] as $tag) {
		if (preg_match('/\bclass="[^"]*\bte-select-multi\b[^"]*"/', $tag) === 1) {
			$withClass++;
		}
	}
	check(count($all[0]) === 4 && $withClass === 4, 'each of the 4 multiple selects has the class te-select-multi (found ' . count($all[0]) . ' multiple, ' . $withClass . ' with the class)');
	check(preg_match('/\bclass="[^"]*\bte-select-multi\b[^"]*"[^\n]*\bid="te-analization-product-stock-status-filter"|\bid="te-analization-product-stock-status-filter"[^\n]*\bclass="[^"]*\bte-select-multi\b/', $html) === 1, 'the stock status filter has the class te-select-multi');
}

echo "== css ==\n";
// The rules of release 05.01.0100 are replaced by the class rule (issue #104).
check(strpos($css, 'select[multiple]') === false, 'no old rule for select[multiple]');
check(strpos($css, 'select[size]') === false, 'no old rule for select[size]');
check(preg_match('/\.te-app \.te-select-multi\s*\{[^}]*height:\s*auto\s*!important/s', $css) === 1, 'rule: .te-app .te-select-multi has height auto !important');
// No !important on a general select or input rule (issue #104). Read each selector and body pair. Split the selector at each comma.
// A part fails if its last compound is select or input with no class (also after a descendant selector).
$general = array();
preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
foreach ($rules as $rule) {
	if (strpos($rule[2], '!important') === false) {
		continue;
	}
	foreach (explode(',', $rule[1]) as $part) {
		if (preg_match('/(?:^|[\s>+~])(?:select|input)(?:\[[^\]]*\])*$/', trim($part)) === 1) {
			$general[] = trim($part);
		}
	}
}
check(count($general) === 0, 'no !important in a rule for a general select or input' . ($general ? ' (found: ' . implode('; ', $general) . ')' : ''));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
