<?php
/**
 * Static test for the donation info (README, store descriptions, language files, QR images, templates, controller).
 *
 * Usage:
 *   php tests/test_client_translate_expert_donation_info.php
 *
 * No OpenCart, no DB, no API key. Any PHP warning fails the test.
 */

set_error_handler(function ($no, $str, $file, $line) {
	echo "  FAIL: PHP warning: {$str} in {$file}:{$line}\n";
	exit(1);
});

$root = realpath(__DIR__ . '/..');
$wallets = array(
	'tron' => 'TKB4TNF9GRCcJQHavDCaMbFQVsEp322BhU',
	'bitcoin' => 'bc1qr38nutpnjeqygs8tnsypw32hqtgpe0wnuf3jnv',
	'base' => '0x5706F582478CE6C15Ef6489B103AC018280B0540',
);
$link = 'https://github.com/vhoshko/opencart-translate-expert#donate';

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

$readme = file_get_contents($root . '/README.md');

echo "== docs ==\n";
check(strpos($readme, "\n## Donate\n") !== false, 'README has the Donate section');
$addrPattern = '/T[1-9A-HJ-NP-Za-km-z]{33}|bc1[a-z0-9]{20,}|0x[0-9a-fA-F]{40}/';
foreach (array('README.md', 'docs/store/description.en.md', 'docs/store/description.uk.md') as $rel) {
	$src = file_get_contents($root . '/' . $rel);
	foreach ($wallets as $net => $addr) {
		check(substr_count($src, $addr) === 1, "{$rel}: {$net} address exactly once");
	}
	preg_match_all($addrPattern, $src, $found);
	check(!array_diff($found[0], $wallets), "{$rel}: no other address-like token");
}
foreach (array('tron', 'bitcoin', 'base') as $net) {
	$rel = 'docs/donate/qr-' . $net . '.png';
	$path = $root . '/' . $rel;
	$sig = is_file($path) ? file_get_contents($path, false, null, 0, 8) : '';
	check($sig === "\x89PNG\r\n\x1a\n", "{$rel} exists and has the PNG signature");
	check(strpos($readme, $rel) !== false, "{$rel} is used in README.md");
}

echo "== language files ==\n";
$keys = array();
foreach (array('en-gb', 'ru-ru', 'uk-ua') as $lang) {
	$_ = array();
	include $root . '/sources/upload/admin/language/' . $lang . '/extension/module/client_translate_expert.php';
	$donate = array();
	foreach ($_ as $k => $v) {
		if (strpos($k, 'te_donate_') === 0) {
			$donate[$k] = $v;
		}
	}
	if ($lang === 'en-gb') {
		$keys = array_keys($donate);
	}
	check(array_keys($donate) === $keys && count($keys) > 0, "{$lang}: same te_donate_* keys in the same order");
	foreach ($wallets as $net => $addr) {
		check(isset($_['te_donate_address_' . $net]) && $_['te_donate_address_' . $net] === $addr, "{$lang}: te_donate_address_{$net}");
	}
	$langSrc = file_get_contents($root . '/sources/upload/admin/language/' . $lang . '/extension/module/client_translate_expert.php');
	foreach ($wallets as $net => $addr) {
		check(substr_count($langSrc, $addr) === 1, "{$lang}: {$net} address exactly once in the file");
	}
	preg_match_all($addrPattern, $langSrc, $found);
	check(!array_diff($found[0], $wallets), "{$lang}: no other address-like token");
	$html = false;
	foreach ($donate as $v) {
		if ($v !== strip_tags($v)) {
			$html = true;
		}
	}
	check(!$html, "{$lang}: no HTML in te_donate_* values");
}

echo "== templates and controller ==\n";
$base = $root . '/sources/upload/admin/view/template/extension/module/client_translate_expert.';
foreach (array('tpl' => $base . 'tpl', 'oc4.twig' => $base . 'oc4.twig') as $name => $path) {
	$src = file_get_contents($path);
	$missing = array();
	foreach ($keys as $k) {
		$pattern = $name === 'tpl'
			? '/<\?php\s+echo\s+\$' . $k . '\s*;?\s*\?>/'
			: '/\{\{\s*' . $k . '\s*\}\}/';
		if (!preg_match($pattern, $src)) {
			$missing[] = $k;
		}
	}
	check(!$missing, "{$name}: uses every te_donate_* key" . ($missing ? ' (missing: ' . implode(', ', $missing) . ')' : ''));
	check(strpos($src, 'href="' . $link . '"') !== false, "{$name}: has the GitHub #donate link");
}
$ctl = file_get_contents($root . '/sources/upload/admin/controller/extension/module/client_translate_expert.php');
foreach ($keys as $k) {
	check(strpos($ctl, "'" . $k . "'") !== false, "controller whitelist: {$k}");
}

echo "== sources ==\n";
$bad = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/sources', FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
	if (!$file->isFile() || preg_match('/\.(png|jpe?g|gif|ico|zip|woff2?|ttf|eot)$/i', $file->getFilename())) {
		continue;
	}
	$c = file_get_contents($file->getPathname());
	if (strpos($c, 'docs/donate/') !== false || preg_match('/qr-[a-z]+\.png/', $c)) {
		$bad[] = $file->getPathname();
	}
}
check(!$bad, 'no file under sources/ uses docs/donate/ or qr-*.png' . ($bad ? ' (' . implode(', ', $bad) . ')' : ''));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
