<?php
/**
 * Static test for the height of the multiple selects in the Site content tab (issue #100).
 *
 * Usage:
 *   php tests/test_client_translate_expert_select_height.php
 *
 * Reads the .tpl, the .oc4.twig and the CSS file. No OpenCart, no DB, no API key.
 * The table list #te-analization-table shows 15 rows. The CSS rule lets the size attribute set the height.
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

foreach ($files as $name => $path) {
	echo "== {$name} ==\n";
	$html = file_get_contents($path);
	$found = preg_match('/<select\b[^\n]*\bid="te-analization-table"[^\n]*/', $html, $m) === 1;
	check($found, 'the table list select exists');
	$tag = $found ? $m[0] : '';
	check(strpos($tag, 'multiple="multiple"') !== false, 'the table list is a multiple select');
	check(strpos($tag, 'size="15"') !== false, 'the table list shows 15 rows (size="15")');
}

echo "== css ==\n";
check(preg_match('/\.te-app select\[multiple\]\.form-control\s*(?:,[^{]*)?\{[^}]*height:\s*auto/s', $css) === 1, 'rule: .te-app select[multiple].form-control has height auto');
check(preg_match('/\.te-app select\[multiple\]\.form-select\s*(?:,[^{]*)?\{[^}]*height:\s*auto/s', $css) === 1, 'rule: .te-app select[multiple].form-select has height auto');

check(preg_match('/\.te-app select\[multiple\]\s*,\s*\.te-app select\[size\]\s*\{[^}]*height:\s*auto/s', $css) === 1, 'rule: .te-app select[multiple], .te-app select[size] has height auto');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
