<?php
/**
 * Static test for the collapsed cards of the Settings tab in both admin templates.
 *
 * Usage:
 *   php tests/test_client_translate_expert_settings_cards.php
 *
 * Reads the .tpl and the .oc4.twig file. No OpenCart, no DB, no API key.
 * Each card needs a toggle button and a card body with the same id.
 * The cron, routes and "Try a translation" cards start closed.
 * The language codes card starts closed and opens when $error_language_code_map is set.
 */

$base = __DIR__ . '/../sources/upload/admin/view/template/extension/module/client_translate_expert.';
$files = array('tpl' => $base . 'tpl', 'oc4.twig' => $base . 'oc4.twig');
// panel id => a field that must be inside the card body
$cards = array(
	'te-cron-panel' => 'id="te-cron-secret"',
	'te-routes-panel' => 'name="client_translate_expert_excluded_routes"',
	'te-lcm-panel' => 'id="te-lcm-rows"',
	'te-try-panel' => 'name="test[',
);

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
	$src = file_get_contents($path);
	foreach ($cards as $id => $field) {
		$hasButton = preg_match('/<button[^\n]*te-js-collapse[^\n]*data-te-target="#' . $id . '"[^\n]*aria-controls="' . $id . '"/', $src) === 1;
		check($hasButton, "{$id}: toggle button points to the body id");
		$hasBody = preg_match('/<div class="te-card__body([^"]*)" id="' . $id . '">/', $src, $m, PREG_OFFSET_CAPTURE) === 1;
		check($hasBody, "{$id}: card body has the id");
		if (!$hasBody) {
			continue;
		}
		$pos = $m[0][1];
		$end = strpos($src, 'class="te-card"', $pos);
		$chunk = substr($src, $pos, $end === false ? 4000 : $end - $pos);
		check(strpos($chunk, $field) !== false, "{$id}: the field is inside the card body");
		if ($id === 'te-lcm-panel') {
			$cls = $m[1][0];
			$hiddenIfNoError = $name === 'tpl'
				? strpos($src, "<?php if (empty(\$error_language_code_map)) { echo ' te-hidden'; } ?>\" id=\"te-lcm-panel\"") !== false
				: strpos($src, '{% if not error_language_code_map %} te-hidden{% endif %}" id="te-lcm-panel"') !== false;
			check($hiddenIfNoError, "{$id}: te-hidden is set only when no save error exists");
			$expandedIfError = $name === 'tpl'
				? strpos($src, "aria-expanded=\"<?php echo !empty(\$error_language_code_map) ? 'true' : 'false'; ?>\"") !== false
				: strpos($src, 'aria-expanded="{% if error_language_code_map %}true{% else %}false{% endif %}"') !== false;
			check($expandedIfError, "{$id}: aria-expanded is true only when a save error exists");
			check(preg_match('/aria-expanded="[^"]*error_language_code_map[^"]*"[^\n]*aria-controls="te-lcm-panel"/', $src) === 1, "{$id}: aria-expanded follows the save error");
		} else {
			check(strpos($m[1][0], 'te-hidden') !== false, "{$id}: starts closed");
			check(preg_match('/data-te-target="#' . $id . '" aria-expanded="false"/', $src) === 1, "{$id}: aria-expanded is false");
		}
	}
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
