<?php
/**
 * OC 3.0 OCMOD XML rewrite test (issue #98).
 *
 * Usage:
 *   php tests/test_oc30_ocmod_xml.php
 *
 * Reads the file_replace function and each file_replace line for the OC 3.0 OCMOD XML from build.sh.
 * Runs them with bash on a copy of the XML in a temporary folder, as the OC 3.0 build does.
 * Checks that header.twig gets the two Twig lines and that no PHP tag remains.
 * The result depends on the sed of the host: GNU sed (Linux) and BSD sed (macOS) read \? in a different way.
 * No OpenCart, no DB, no API key.
 */

$root = realpath(__DIR__ . '/..');
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

$build = file_get_contents($root . '/build.sh');
$xmlSource = $root . '/sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml';

// 1. Read the function and the OC 3.0 replace lines from build.sh.
$function = '';
if (preg_match('/^file_replace\(\) \{\n.*?^\}\n/ms', $build, $m)) {
	$function = $m[0];
}
check($function !== '', 'build.sh: file_replace function found');

$lines = array();
if (preg_match_all('/^file_replace sources_release_30\/upload\/system\/OpenCartTranslateExpertClient\.ocmod\.xml (.+)$/m', $build, $m)) {
	$lines = $m[1];
}
check(count($lines) >= 3, 'build.sh: at least 3 OC 3.0 OCMOD XML replace lines found (' . count($lines) . ')');
check(preg_match('/^check_oc30_xml sources_release_30\/upload\/system\/OpenCartTranslateExpertClient\.ocmod\.xml$/m', $build) === 1, 'build.sh: check_oc30_xml runs on the OC 3.0 XML');

// 2. Run the real lines on a copy of the XML.
$tmp = sys_get_temp_dir() . '/te_oc30_xml_' . getmypid();
if (!mkdir($tmp)) {
	fwrite(STDERR, "Cannot make $tmp\n");
	exit(1);
}
$copy = $tmp . '/OpenCartTranslateExpertClient.ocmod.xml';
copy($xmlSource, $copy);
$script = $function . "\n";
foreach ($lines as $args) {
	$script .= 'file_replace ' . escapeshellarg($copy) . ' ' . $args . "\n";
}
file_put_contents($tmp . '/run.sh', "#!/bin/bash\nset -euo pipefail\n" . $script);
exec('bash ' . escapeshellarg($tmp . '/run.sh') . ' 2>&1', $out, $code);
check($code === 0, 'bash: the OC 3.0 replace lines run without error' . ($code === 0 ? '' : ' (' . implode(' | ', $out) . ')'));

$xml = file_get_contents($copy);
$body = substr($xml, strpos($xml, "\n") + 1);

// 3. Check the result.
check(strpos($xml, '{{ client_translate_expert_head_html }}') !== false, 'result: {{ client_translate_expert_head_html }} exists');
check(strpos($xml, '{{ client_translate_expert_header_html }}') !== false, 'result: {{ client_translate_expert_header_html }} exists');
check(strpos($body, '<?') === false, 'result: no "<?" after the XML declaration');
check(strpos($body, '?>') === false, 'result: no "?>" after the XML declaration');
check(strpos($xml, 'header.twig') !== false && strpos($xml, 'header.tpl') === false, 'result: the path is header.twig and not header.tpl');
check(preg_match('/\{\{[^}\n]*\}\}/', $xml) === 1 && substr_count($xml, '{{') === substr_count($xml, '}}'), 'result: each {{ has a closing }}');

// 4. The source XML stays in the PHP form for OC 2.x.
$source = file_get_contents($xmlSource);
check(strpos($source, '<?php echo $client_translate_expert_head_html; ?>') !== false, 'source: the PHP echo form is the same');

// 5. The check_oc30_xml function of build.sh: it passes the result and it fails on a PHP tag or a missing Twig line.
$checkFunction = '';
if (preg_match('/^check_oc30_xml\(\) \{\n.*?^\}\n/ms', $build, $m)) {
	$checkFunction = $m[0];
}
check($checkFunction !== '', 'build.sh: check_oc30_xml function found');
function runCheck($checkFunction, $tmp, $content)
{
	$file = $tmp . '/check.xml';
	file_put_contents($file, $content);
	file_put_contents($tmp . '/check.sh', "#!/bin/bash\nset -euo pipefail\n" . $checkFunction . "\ncheck_oc30_xml " . escapeshellarg($file) . "\n");
	exec('bash ' . escapeshellarg($tmp . '/check.sh') . ' 2>&1', $o, $c);
	return $c;
}
$twig = "{{ client_translate_expert_head_html }}\n{{ client_translate_expert_header_html }}\n";
check(runCheck($checkFunction, $tmp, $xml) === 0, 'check_oc30_xml: passes the rewritten XML');
check(runCheck($checkFunction, $tmp, "<?xml version=\"1.0\"?>\n" . $twig . "<?{{ x; ?>\n") !== 0, 'check_oc30_xml: fails on a PHP tag');
check(runCheck($checkFunction, $tmp, "<?xml version=\"1.0\"?>\n{{ client_translate_expert_head_html }}\n") !== 0, 'check_oc30_xml: fails on a missing Twig line');
check(runCheck($checkFunction, $tmp, "<?xml version=\"1.0\"?>\n" . $twig) === 0, 'check_oc30_xml: the XML declaration is not a PHP tag');

// Cleanup
foreach (glob($tmp . '/*') as $f) {
	unlink($f);
}
rmdir($tmp);

echo "\n$passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
