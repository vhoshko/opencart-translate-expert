<?php
/**
 * Static test for the translate_expert library folder.
 *
 * Usage:
 *   php tests/test_translate_expert_library_paths.php
 *
 * Checks that each library path in sources/ (DIR_SYSTEM and DIR_EXTENSION) resolves to an existing file,
 * and that no old library file name or class name remains in sources/, build.sh and tests/.
 */

$root = realpath(__DIR__ . '/..');
$libDir = $root . '/sources/upload/system/library/translate_expert';
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

function listFiles($dir, $extensions, &$out)
{
	foreach (scandir($dir) as $entry) {
		if ($entry === '.' || $entry === '..' || $entry === 'node_modules' || $entry === 'vendor' || $entry === 'google-cloud-translate') {
			continue;
		}
		$path = $dir . '/' . $entry;
		if (is_dir($path)) {
			listFiles($path, $extensions, $out);
		} elseif (in_array(pathinfo($path, PATHINFO_EXTENSION), $extensions)) {
			$out[] = $path;
		}
	}
}

$expected = array(
	'translate_expert.php',
	'translate_expert_core.php',
	'translate_expert_localization.php',
	'translate_expert_cron_helper.php',
	'translate_expert_i18n.php',
	'translate_expert_route_filter.php',
	'translate_expert_google_client.php',
);
foreach ($expected as $file) {
	check(is_file($libDir . '/' . $file), "library file exists: translate_expert/$file");
}

function extractLibraryPaths($content)
{
	$found = array();
	if (preg_match_all('#(DIR_SYSTEM|DIR_EXTENSION)\s*\.\s*([\'"])([^\'"]*\.php)\2#', $content, $m, PREG_SET_ORDER)) {
		foreach ($m as $hit) {
			$found[] = array($hit[1], $hit[3]);
		}
	}
	return $found;
}

function resolveLibraryPath($kind, $relative, $systemDir)
{
	$prefix = $kind === 'DIR_SYSTEM' ? 'library/translate_expert/' : 'client_translate_expert/system/library/translate_expert/';
	if (strpos($relative, $prefix) !== 0) {
		return null;
	}
	return $systemDir . '/' . substr($relative, strlen('client_translate_expert/system/') * ($kind === 'DIR_EXTENSION' ? 1 : 0));
}

$sourcesDir = isset($argv[1]) ? realpath($argv[1]) : $root . '/sources';
$systemDir = $sourcesDir . '/upload/system';

$bad = array(
	"DIR_SYSTEM . 'library/translate_expert_i18n.php'" => 'no folder',
	"DIR_SYSTEM . 'translate_expert/translate_expert_i18n.php'" => 'no library/',
	"DIR_EXTENSION . 'client_translate_expert/system/translate_expert/translate_expert_i18n.php'" => 'wrong OC4 root',
	"DIR_EXTENSION . 'client_translate_expert/library/translate_expert/translate_expert_i18n.php'" => 'wrong OC4 root 2',
);
foreach ($bad as $sample => $why) {
	$paths = extractLibraryPaths($sample);
	$resolved = count($paths) === 1 ? resolveLibraryPath($paths[0][0], $paths[0][1], $systemDir) : null;
	check($resolved === null || !is_file($resolved), "self-check: bad path is reported ($why)");
}

$expectedCounts = array(
	'upload/admin/model/extension/module/client_translate_expert.php' => 4,
	'upload/admin/controller/extension/module/client_translate_expert.php' => 2,
	'upload/catalog/controller/extension/module/client_translate_expert_cron.php' => 2,
	'upload/system/library/translate_expert/translate_expert.php' => 4,
	'upload/system/library/translate_expert/translate_expert_core.php' => 2,
	'upload/system/library/translate_expert/translate_expert_localization.php' => 2,
	'upload/system/OpenCartTranslateExpertClient.ocmod.xml' => 2,
);
$sourceFiles = array();
listFiles($sourcesDir, array('php', 'xml'), $sourceFiles);
$counts = array();
foreach ($sourceFiles as $file) {
	$rel = substr($file, strlen($sourcesDir) + 1);
	$content = file_get_contents($file);
	if (preg_match('#library/translate_expert[a-z_0-9]*\.php#', $content)) {
		check(false, "path without the translate_expert/ folder in $rel");
	}
	foreach (extractLibraryPaths($content) as $path) {
		if (strpos($path[1], 'translate_expert') === false) {
			continue;
		}
		$counts[$rel] = isset($counts[$rel]) ? $counts[$rel] + 1 : 1;
		$resolved = resolveLibraryPath($path[0], $path[1], $systemDir);
		check($resolved !== null && is_file($resolved), 'path resolves to a file: ' . $path[1] . ' in ' . $rel);
	}
}
foreach ($expectedCounts as $rel => $count) {
	$got = isset($counts[$rel]) ? $counts[$rel] : 0;
	check($got === $count, "$rel has $count library paths (found $got)");
}
check(count($counts) === count($expectedCounts), 'no other source file has a library path (found ' . count($counts) . ' files, expected ' . count($expectedCounts) . ')');

$old = '#vh_google_translator|VHGoogleTranslator|LibraryClientTranslateExpert|ClientTranslateExpertCronHelper|ClientTranslateExpertI18n|ClientTranslateExpertRouteFilter|client_translate_expert_(core|cron_helper|i18n|route_filter)\.php|client_localization_translate_expert|library/client_translate_expert\.php#';
$scan = array();
listFiles($sourcesDir, array('php', 'xml', 'js', 'tpl', 'twig'), $scan);
listFiles($root . '/tests', array('php', 'js'), $scan);
$scan[] = $root . '/build.sh';
$self = realpath(__FILE__);
$hits = array();
foreach ($scan as $file) {
	if (realpath($file) === $self) {
		continue;
	}
	foreach (file($file) as $n => $line) {
		if (strpos($line, 'OLD_LIB_NAMES') !== false) {
			continue;
		}
		if (preg_match($old, $line)) {
			$hits[] = str_replace($root . '/', '', $file) . ':' . ($n + 1);
		}
	}
}
check(count($hits) === 0, 'no old library file or class name in sources, build.sh and tests' . (count($hits) ? ': ' . implode(', ', $hits) : ''));

foreach (array('system/library/client_translate_expert.php', 'system/library/client_translate_expert_core.php', 'system/library/vh_google_translator.php') as $flat) {
	check(!file_exists($root . '/sources/upload/' . $flat), "old flat file is gone: $flat");
}

echo "\n=== Results: $passed passed, $failed failed ===\n";
exit($failed === 0 ? 0 : 1);
