<?php
/**
 * Test script for TranslateExpertLanguageCodeMap (default JSON, parse, lookup, normalize).
 *
 * Usage:
 *   php tests/test_translate_expert_language_code_map.php
 *
 * No OpenCart, no database, no network. Any PHP warning or notice fails the run.
 */

require_once __DIR__ . '/../sources/upload/system/library/translate_expert/translate_expert_language_code_map.php';

$warnings = array();
set_error_handler(function ($no, $str, $file, $line) use (&$warnings) {
	$warnings[] = "$str in $file:$line";
	return true;
});

$passed = 0;
$failed = 0;

function check($actual, $expected, $name)
{
	global $passed, $failed;
	if ($actual === $expected) {
		$passed++;
		echo " [PASS] $name\n";
	} else {
		$failed++;
		echo " [FAIL] $name (expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . ")\n";
	}
}

function codes($result)
{
	$out = array();
	foreach ($result['errors'] as $error) {
		$out[] = $error['code'] . ':' . $error['index'];
	}
	return $out;
}

$M = 'TranslateExpertLanguageCodeMap';

check($M::parse(TranslateExpertLanguageCodeMap::DEFAULT_JSON), array('ua' => 'uk', 'rs' => 'sr', 'ge' => 'ka', 'cz' => 'cs'), 'default JSON has the 4 old pairs');
check($M::parse(''), array(), 'parse: empty string');
check($M::parse(null), array(), 'parse: null');
check($M::parse('null'), array(), 'parse: "null"');
check($M::parse('{bad'), array(), 'parse: invalid JSON');
check($M::parse('["a","b"]'), array(), 'parse: JSON array');
check($M::parse('5'), array(), 'parse: JSON scalar');
check($M::parse('{"0":"a"}'), array('0' => 'a'), 'parse: key 0');
check($M::parse('{"0":"a","1":"b"}'), array('0' => 'a', '1' => 'b'), 'parse: keys 0 and 1');
check($M::parse(' {"1":"a"}'), array('1' => 'a'), 'parse: leading space and key 1');
check($M::parse('[1]'), array(), 'parse: real array [1]');
check($M::parse(array('ua' => 'uk')), array(), 'parse: non-string input');
check($M::parse('{"ua":"uk"}'), array('ua' => 'uk'), 'parse: valid object');
check($M::parse('{" UA ":" uk "}'), array('ua' => 'uk'), 'parse: key lowercase and trim, value trim');
check($M::parse('{"zh":"zh-CN"}'), array('zh' => 'zh-CN'), 'parse: value keeps its case');
check($M::parse('{"a":1,"b":"x","c":null,"":"y","d":""}'), array('b' => 'x'), 'parse: skips non-string and empty pairs');

$map = array('ua' => 'uk');
check($M::lookup($map, 'ua'), 'uk', 'lookup: match');
check($M::lookup($map, 'UA'), 'uk', 'lookup: code in upper case');
check($M::lookup($map, 'en'), 'en', 'lookup: no match returns the code');
check($M::lookup(array(), 'ua'), 'ua', 'lookup: empty map');
check($M::lookup($map, ''), '', 'lookup: empty code');

$r = $M::normalize('');
check(array($r['json'], $r['errors']), array('', array()), 'normalize: empty string gives empty map');
$r = $M::normalize(null);
check(array($r['json'], $r['errors']), array('', array()), 'normalize: null gives empty map');
$r = $M::normalize('{}');
check(array($r['json'], $r['errors']), array('', array()), 'normalize: {} gives empty string');
$r = $M::normalize(TranslateExpertLanguageCodeMap::DEFAULT_JSON);
check(array($r['json'], $r['errors']), array(TranslateExpertLanguageCodeMap::DEFAULT_JSON, array()), 'normalize: default JSON stays the same');
$r = $M::normalize('{" UA ":" uk ","Xx":"Yy_1-2"}');
check(array($r['json'], $r['errors']), array('{"ua":"uk","xx":"Yy_1-2"}', array()), 'normalize: lowercase and trim keys, trim values, keep value case');
$r = $M::normalize('{bad');
check(codes($r), array('format:0'), 'normalize: invalid JSON');
check($r['json'], '', 'normalize: invalid JSON has no JSON');
$r = $M::normalize('["a"]');
check(codes($r), array('format:0'), 'normalize: JSON array');
$r = $M::normalize('[1]');
check(codes($r), array('format:0'), 'normalize: real array [1]');
$r = $M::normalize('{"0":"a"}');
check(array($r['json'], $r['errors']), array('{"0":"a"}', array()), 'normalize: key 0 is valid and stays an object');
$r = $M::normalize('{"0":"a","1":"b"}');
check(array($r['json'], $r['errors']), array('{"0":"a","1":"b"}', array()), 'normalize: keys 0 and 1 are valid pairs');
$r = $M::normalize('"text"');
check(codes($r), array('format:0'), 'normalize: JSON string');
$r = $M::normalize('{"ok":"a","  ":"b"}');
check(codes($r), array('empty_key:2'), 'normalize: empty key (pair number 2)');
$r = $M::normalize('{"a":"  "}');
check(codes($r), array('empty_value:1'), 'normalize: empty value');
$r = $M::normalize('{"ua":"uk","UA":"x"}');
check(codes($r), array('duplicate:2'), 'normalize: duplicate key after lowercase');
$r = $M::normalize('{"u a":"uk","b":"u.k","c":"ok"}');
check(codes($r), array('chars:1', 'chars:2'), 'normalize: bad characters in key and value');
$r = $M::normalize('{"a":1}');
check(codes($r), array('chars:1'), 'normalize: non-string value');
$r = $M::normalize(json_encode(array(str_repeat('a', 20) => 'x')));
check(codes($r), array(), 'normalize: key of 20 characters is allowed');
$r = $M::normalize(json_encode(array(str_repeat('a', 21) => 'x')));
check(codes($r), array('length:1'), 'normalize: key of 21 characters');
$r = $M::normalize(json_encode(array('a' => str_repeat('b', 21))));
check(codes($r), array('length:1'), 'normalize: value of 21 characters');
$many = array();
for ($i = 1; $i <= 100; $i++) {
	$many['k' . $i] = 'v';
}
$r = $M::normalize(json_encode($many));
check(codes($r), array(), 'normalize: 100 pairs are allowed');
$many['k101'] = 'v';
$r = $M::normalize(json_encode($many));
check(codes($r), array('count:0'), 'normalize: 101 pairs');
check($r['json'], '', 'normalize: errors give no JSON');

check(count($warnings) === 0, true, 'no PHP warnings' . (count($warnings) ? ': ' . implode('; ', $warnings) : ''));

echo "\n=== Results: $passed passed, $failed failed ===\n";
exit($failed === 0 ? 0 : 1);
