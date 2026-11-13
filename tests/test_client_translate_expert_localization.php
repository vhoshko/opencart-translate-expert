<?php
/**
 * Test script for the localization scopes of LocalizationLibraryClientTranslateExpert
 * (catalog and admin language files, OC 4.x extension roots, main file rename, include folder, save guard).
 *
 * Usage:
 *   php tests/test_client_translate_expert_localization.php
 *
 * Unit tests only (no OpenCart, no DB, no API key). The parent process runs one child process
 * for each OpenCart VERSION, because VERSION and the DIR_* paths are constants.
 * Each child makes a temp folder tree. Any PHP warning or notice fails the run.
 */

$isChild = isset($argv[1]) && $argv[1] === '--child';
if ($isChild)
{
	$childTest = setupChild($argv[2], $argv[3]);

	// PHP declares a class with a parent from another file only when execution reaches the declaration.
	// For this reason, the declaration comes after setupChild() loads the library.
	class TestableLocalizationLibrary extends LocalizationLibraryClientTranslateExpert
	{
		public function roots($scope, $from, $to) { return $this->getLocalizationRoots($scope, $from, $to); }
		public function mainName($language) { return $this->getMainLanguageFileName($language); }
	}

	$childTest->run();
	exit($childTest->failed === 0 ? 0 : 1);
}

$versions = array('1.5.6.4', '2.0.3.1', '2.1.0.2', '2.2.0.0', '2.3.0.2', '3.0.3.8', '4.0.2.3', '4.1.0.0');
$passed = 0;
$failed = 0;
foreach ($versions as $version)
{
	$tmp = sys_get_temp_dir() . '/te_localization_test_' . getmypid() . '_' . str_replace('.', '_', $version);
	echo "\n=== OpenCart {$version} ===\n";
	removeTree($tmp);
	$output = array();
	$code = 0;
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --child ' . escapeshellarg($version) . ' ' . escapeshellarg($tmp) . ' 2>&1', $output, $code);
	echo implode("\n", $output) . "\n";
	foreach ($output as $line)
	{
		if (strpos($line, ' [PASS] ') === 0)
			$passed++;
		elseif (strpos($line, ' [FAIL] ') === 0)
			$failed++;
	}
	if ($code !== 0 && $code !== 1)
	{
		echo " [FAIL] child process for {$version} stopped with exit code {$code}\n";
		$failed++;
	}
	removeTree($tmp);
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "Results: {$passed} passed, {$failed} failed\n";
echo str_repeat('=', 50) . "\n";
exit($failed === 0 && $passed > 0 ? 0 : 1);

function removeTree($dir)
{
	if (!is_dir($dir))
		return;
	foreach (scandir($dir) as $name)
	{
		if ($name == '.' || $name == '..')
			continue;
		$path = $dir . '/' . $name;
		if (is_dir($path) && !is_link($path))
			removeTree($path);
		else
			unlink($path);
	}
	rmdir($dir);
}

function writeFile($path, $content)
{
	if (!is_dir(dirname($path)))
		mkdir(dirname($path), 0777, true);
	file_put_contents($path, $content);
}

function langFile($keys)
{
	$s = "<?php\n";
	foreach ($keys as $key => $value)
		$s .= "\$_['{$key}'] = '{$value}';\n";
	return $s;
}

// ---------------------------------------------------------------- child process

function setupChild($version, $tmp)
{
	set_error_handler(function ($severity, $message, $file, $line) {
		throw new ErrorException($message, 0, $severity, $file, $line);
	});

	$isOc4 = version_compare($version, '4.0', '>=');
	define('VERSION', $version);
	define('DIR_SYSTEM', __DIR__ . '/../sources/upload/system/');
	define('DIR_APPLICATION', $tmp . '/admin/');
	define('DIR_CATALOG', $tmp . '/catalog/');
	if ($isOc4)
		define('DIR_EXTENSION', $tmp . '/extension/');

	$t = new LocalizationChildTest($version, $tmp);
	$t->buildTree();

	// The OC4 library loads the core from DIR_EXTENSION. A stub there loads the source file.
	require_once __DIR__ . '/../sources/upload/system/library/client_localization_translate_expert.php';

	return $t;
}

class FakeLocalisationLanguageModel
{
	public $languages = array();

	public function getLanguage($id)
	{
		return isset($this->languages[$id]) ? $this->languages[$id] : array();
	}

	public function getLanguages()
	{
		return $this->languages;
	}
}

class FakeLoader
{
	public function model($route) {}
	public function language($route) {}
}

class FakeLanguage
{
	public function get($key) { return strpos($key, 'file_to_translate_info') !== false ? $key . ': {path}' : $key; }
	public function load($route) {}
}

class FakeLocalizationModel
{
	public $load;
	public $language;
	public $model_localisation_language;
}

class LocalizationChildTest
{
	public $passed = 0;
	public $failed = 0;
	private $version;
	private $tmp;
	private $isOc4;
	private $oldOc;   // OC < 2.2: folders by "directory"
	private $from;    // source language folder name
	private $to;      // target language folder name
	private $mainFrom;
	private $mainTo;

	public function __construct($version, $tmp)
	{
		$this->version = $version;
		$this->tmp = $tmp;
		$this->isOc4 = version_compare($version, '4.0', '>=');
		$this->oldOc = version_compare($version, '2.2', '<');
		$this->from = $this->oldOc ? 'english' : 'en-gb';
		$this->to = $this->oldOc ? 'ukrainian' : 'uk-ua';
		// OC 4.x has no main file with the name of the language (it loads default.php), so nothing is renamed.
		$this->mainFrom = $this->isOc4 ? 'default' : $this->from;
		$this->mainTo = $this->isOc4 ? 'default' : $this->to;
	}

	private function languages()
	{
		if ($this->oldOc)
		{
			$en = array('language_id' => '1', 'name' => 'English', 'code' => 'en', 'directory' => 'english');
			$uk = array('language_id' => '2', 'name' => 'Ukrainian', 'code' => 'uk', 'directory' => 'ukrainian');
			if (version_compare($this->version, '2.0', '<'))
			{
				$en['filename'] = 'english';
				$uk['filename'] = 'ukrainian';
			}
		}
		else
		{
			$en = array('language_id' => '1', 'name' => 'English', 'code' => 'en-gb', 'directory' => 'english');
			$uk = array('language_id' => '2', 'name' => 'Ukrainian', 'code' => 'uk-ua', 'directory' => 'ukrainian');
			if ($this->isOc4)
			{
				$en['extension'] = '';
				$uk['extension'] = 'ukrainian';
			}
		}
		return array('1' => $en, '2' => $uk);
	}

	public function buildTree()
	{
		$tmp = $this->tmp;
		$f = $this->from;

		// Admin core files
		writeFile("$tmp/admin/language/$f/{$this->mainFrom}.php", langFile(array('code' => 'en', 'text_yes' => 'Yes')));
		writeFile("$tmp/admin/language/$f/common/header.php",
			"<?php\n\$_['heading_title'] = 'Header';\ninclude(DIR_LANGUAGE . '$f/common/inc.php');\n");
		writeFile("$tmp/admin/language/$f/common/inc.php", langFile(array('text_admin_inc' => 'Admin include')));
		writeFile("$tmp/admin/language/$f/quote'name.php", langFile(array('text_q' => 'Quote')));

		// Catalog core files (the catalog include has another key, to find the include folder)
		writeFile("$tmp/catalog/language/$f/{$this->mainFrom}.php", langFile(array('code' => 'en')));
		writeFile("$tmp/catalog/language/$f/product/product.php",
			"<?php\n\$_['text_product'] = 'Product';\ninclude(DIR_LANGUAGE . '$f/common/inc.php');\n");
		writeFile("$tmp/catalog/language/$f/common/inc.php", langFile(array('text_catalog_inc' => 'Catalog include')));

		if ($this->isOc4)
		{
			writeFile("$tmp/extension/client_translate_expert/system/library/client_translate_expert_core.php",
				"<?php\nrequire_once " . var_export(realpath(__DIR__ . '/../sources/upload/system/library/client_translate_expert_core.php'), true) . ";\n");
			// An installed extension with admin files of the source language
			writeFile("$tmp/extension/opencart/admin/language/en-gb/module/account.php", langFile(array('heading_title' => 'Account')));
			// An extension without files of the source language
			writeFile("$tmp/extension/emptyext/admin/language/de-de/module/x.php", langFile(array('heading_title' => 'X')));
			// The language extension of the target language: the core root of uk-ua. Its en-gb folder must not be an extension root.
			writeFile("$tmp/extension/ukrainian/admin/language/uk-ua/common/header.php", langFile(array('heading_title' => 'Заголовок')));
			writeFile("$tmp/extension/ukrainian/admin/language/en-gb/stray.php", langFile(array('text_stray' => 'Stray')));
			// A folder name that is not a safe extension name
			writeFile("$tmp/extension/bad name/admin/language/en-gb/x.php", langFile(array('heading_title' => 'Bad')));
		}
	}

	private function newLibrary()
	{
		$model = new FakeLocalizationModel();
		$model->load = new FakeLoader();
		$model->language = new FakeLanguage();
		$model->model_localisation_language = new FakeLocalisationLanguageModel();
		$model->model_localisation_language->languages = $this->languages();
		return new TestableLocalizationLibrary($model, false);
	}

	private function adminTargetCore()
	{
		if ($this->isOc4)
			return $this->tmp . '/extension/ukrainian/admin/language/uk-ua/';
		return $this->tmp . '/admin/language/' . $this->to . '/';
	}

	public function run()
	{
		try {
			$this->testRoots();
			$this->testAdminAnalysis();
			$this->testCatalogAnalysis();
			// Before the save tests: these write target files that the reverse direction would read as source files.
			if ($this->isOc4)
				$this->testOc4SourceFromLanguageExtension();
			$this->testSaveGuard();
			$this->testSave();
			$this->testSaveFailure();
		} catch (Exception $e) {
			$this->fail('unexpected exception: ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
		}
	}

	private function testRoots()
	{
		$lib = $this->newLibrary();
		$langs = $this->languages();

		$roots = $lib->roots('catalog', $langs['1'], $langs['2']);
		$this->check(array_keys($roots), array('core'), 'catalog scope has the core root only');
		$this->check($roots['core']['from'], $this->tmp . '/catalog/language/' . $this->from . '/', 'catalog core source folder');

		$roots = $lib->roots('admin', $langs['1'], $langs['2']);
		$this->check($roots['core']['from'], $this->tmp . '/admin/language/' . $this->from . '/', 'admin core source folder');
		$this->check($roots['core']['to'], $this->adminTargetCore(), 'admin core target folder');
		if ($this->isOc4)
		{
			$this->check(array_keys($roots), array('core', 'ext:opencart'), 'admin roots: core and the extension with source files only');
			$this->check($roots['ext:opencart']['to'], $this->tmp . '/extension/opencart/admin/language/uk-ua/', 'extension target folder is in the same extension');
		}
		else
		{
			$this->check(array_keys($roots), array('core'), 'admin scope has the core root only before OC 4.0');
		}

		$expectedMain = $this->oldOc ? 'english' : 'en-gb';
		$this->check($lib->mainName($langs['1']), $expectedMain, 'main language file name of the source language');
		// A store upgraded from OC 1.5 can keep the "filename" column. Use it on OC 1.5 only.
		$upgraded = $langs['1'];
		$upgraded['filename'] = 'leftover';
		$this->check($lib->mainName($upgraded), version_compare($this->version, '2.0', '<') ? 'leftover' : $expectedMain,
			'leftover filename column is used on OC 1.5 only');
	}

	private function analysisRows($res)
	{
		$rows = array();
		foreach ($res->analize as $item)
			$rows[$item['root'] . '|' . $item['pathFrom']] = $item;
		ksort($rows);
		return $rows;
	}

	private function testAdminAnalysis()
	{
		$lib = $this->newLibrary();
		$res = $lib->analizeLocalization('1', '2', 0, 'admin');
		$this->check($res->success, 1, 'admin analysis succeeds');
		if (!$res->success)
			return;

		$rows = $this->analysisRows($res);
		$expected = array(
			"core|/{$this->mainFrom}.php",
			'core|/common/header.php',
			'core|/common/inc.php',
			"core|/quote'name.php",
		);
		if ($this->isOc4)
			$expected[] = 'ext:opencart|/module/account.php';
		sort($expected);
		$this->check(array_keys($rows), $expected, 'admin analysis files (stray files of the language extension are not included)');

		$main = $rows["core|/{$this->mainFrom}.php"];
		$this->check($main['pathTo'], "/{$this->mainTo}.php", $this->isOc4 ? 'default.php keeps its name on OC 4' : 'main file is renamed to the target language');

		$header = $rows['core|/common/header.php'];
		$this->check(strpos($header['contentFrom'], 'text_admin_inc') !== false && strpos($header['contentFrom'], 'text_catalog_inc') === false,
			true, 'admin include is read from the admin language folder');
		$this->check(isset($header['toTranslate']['heading_title']), !$this->isOc4,
			$this->isOc4 ? 'existing target file in the language extension is read (heading_title is translated)' : 'missing target file: heading_title needs translation');

		if ($this->isOc4)
			$this->check($rows['ext:opencart|/module/account.php']['rootName'], 'opencart', 'extension root name');

		$html = $res->detailsHtml;
		$this->check(strpos($html, "data-path-to='/quote&#039;name.php'") !== false, true, 'data-path-to is escaped');
		$this->check(strpos($html, "quote'name") === false, true, 'no unescaped quote in the HTML');
		$this->check(strpos($html, "data-root='core'") !== false, true, 'data-root is in the HTML');
		if ($this->isOc4)
			$this->check(strpos($html, '[opencart] /module/account.php') !== false, true, 'display path shows the extension name');
	}

	private function testCatalogAnalysis()
	{
		$lib = $this->newLibrary();
		$res = $lib->analizeLocalization('1', '2', 0);
		$this->check($res->success, 1, 'catalog analysis (default scope) succeeds');
		if (!$res->success)
			return;
		$rows = $this->analysisRows($res);
		$expected = array("core|/{$this->mainFrom}.php", 'core|/common/inc.php', 'core|/product/product.php');
		sort($expected);
		$this->check(array_keys($rows), $expected, 'catalog analysis files');
		$this->check($rows["core|/{$this->mainFrom}.php"]['pathTo'], "/{$this->mainTo}.php", $this->isOc4 ? 'catalog default.php keeps its name on OC 4' : 'catalog main file is renamed to the target language');
		$product = $rows['core|/product/product.php'];
		$this->check(strpos($product['contentFrom'], 'text_catalog_inc') !== false && strpos($product['contentFrom'], 'text_admin_inc') === false,
			true, 'catalog include is read from the catalog language folder');

		$res = $lib->analizeLocalization('1', '2', 0, 'other');
		$this->check($res->success, 0, 'unknown scope is rejected');
	}

	private function testSaveGuard()
	{
		$lib = $this->newLibrary();
		$content = langFile(array('heading_title' => 'X'));
		$bad = array(
			array('/../evil.php', 'admin', 'core', 'path with ..'),
			array('evil.php', 'admin', 'core', 'path without a leading slash'),
			array('/evil.txt', 'admin', 'core', 'path without .php'),
			array("/evil\0.php", 'admin', 'core', 'path with a NUL byte'),
			array('/a\\evil.php', 'admin', 'core', 'path with a backslash'),
			array('/evil.php', 'other', 'core', 'unknown scope'),
			array('/evil.php', 'admin', 'unknown', 'unknown root'),
			array('/evil.php', 'catalog', 'ext:opencart', 'extension root in the catalog scope'),
			array('/evil.php', 'admin', 'ext:../admin', 'extension root with ..'),
			array('/evil.php', 'admin', 'ext:ukrainian', 'language extension of the target as an extension root'),
			array('/evil.php', 'admin', 'ext:client_translate_expert', 'extension without an admin language folder'),
			array('/evil.php', 'admin', 'ext:bad name', 'extension root with an unsafe name'),
		);
		if (!$this->isOc4)
			$bad[] = array('/evil.php', 'admin', 'ext:opencart', 'extension root before OC 4.0');
		foreach ($bad as $case)
		{
			$res = $lib->saveLocalizationFile($content, '2', $case[0], $case[1], $case[2]);
			$this->check($res->success, 0, 'save rejects: ' . $case[3]);
		}
		$res = $lib->saveLocalizationFile($content, '99', '/evil.php', 'admin', 'core');
		$this->check($res->success, 0, 'save rejects: unknown language');
		$this->check(count($this->findFiles($this->tmp, 'evil')), 0, 'no rejected file is written');
	}

	private function findFiles($dir, $needle)
	{
		$found = array();
		foreach (scandir($dir) as $name)
		{
			if ($name == '.' || $name == '..')
				continue;
			$path = $dir . '/' . $name;
			if (is_dir($path))
				$found = array_merge($found, $this->findFiles($path, $needle));
			elseif (strpos($name, $needle) !== false)
				$found[] = $path;
		}
		return $found;
	}

	private function testSave()
	{
		$lib = $this->newLibrary();
		$content = langFile(array('heading_title' => 'Saved'));

		$res = $lib->saveLocalizationFile($content, '2', '/common/new.php', 'admin', 'core');
		$this->check($res->success, 1, 'admin core save succeeds');
		$this->check(is_file($this->adminTargetCore() . 'common/new.php'), true, 'admin core file is in the admin target folder');

		$res = $lib->saveLocalizationFile($content, '2', '/product/new.php');
		$this->check($res->success, 1, 'catalog save (default scope and root) succeeds');
		$catalogTarget = $this->isOc4
			? $this->tmp . '/extension/ukrainian/catalog/language/uk-ua/product/new.php'
			: $this->tmp . '/catalog/language/' . $this->to . '/product/new.php';
		$this->check(is_file($catalogTarget), true, 'catalog file is in the catalog target folder');

		if ($this->isOc4)
		{
			$res = $lib->saveLocalizationFile($content, '2', '/module/account.php', 'admin', 'ext:opencart');
			$this->check($res->success, 1, 'extension save succeeds');
			$this->check(is_file($this->tmp . '/extension/opencart/admin/language/uk-ua/module/account.php'), true,
				'extension file is in the admin language folder of the same extension');
		}
	}

	private function testSaveFailure()
	{
		$lib = $this->newLibrary();
		// A file where the save needs a folder: mkdir() fails also for root.
		writeFile($this->adminTargetCore() . 'blocked', 'not a folder');
		$res = $lib->saveLocalizationFile(langFile(array('heading_title' => 'X')), '2', '/blocked/x.php', 'admin', 'core');
		$this->check($res->success, 0, 'save reports a failure when the folder cannot be made');
		$this->check(isset($res->message) && strpos($res->message, 'error_localization_save_failed') === 0, true, 'save failure message');
		$this->check(isset($res->message) && substr($res->message, -8) === '/blocked', true, 'save failure message shows the folder');

		// A folder where the save needs a file: file_put_contents() fails also for root.
		mkdir($this->adminTargetCore() . 'folder.php', 0777, true);
		$res = $lib->saveLocalizationFile(langFile(array('heading_title' => 'X')), '2', '/folder.php', 'admin', 'core');
		$this->check($res->success, 0, 'save reports a failure when the file cannot be written');
	}

	// OC 4.x: the source language comes from a language extension, and the target language does not.
	private function testOc4SourceFromLanguageExtension()
	{
		$lib = $this->newLibrary();
		$langs = $this->languages();
		$roots = $lib->roots('admin', $langs['2'], $langs['1']);
		$this->check(array_keys($roots), array('core'), 'reverse direction: language extension of the source is not an extension root');
		$this->check($roots['core']['from'], $this->tmp . '/extension/ukrainian/admin/language/uk-ua/', 'reverse direction: core source folder is in the language extension');
		$this->check($roots['core']['to'], $this->tmp . '/admin/language/en-gb/', 'reverse direction: core target folder is the admin folder');

		$res = $lib->analizeLocalization('2', '1', 0, 'admin');
		$this->check($res->success, 1, 'reverse direction: admin analysis succeeds');
		if ($res->success)
		{
			$rows = $this->analysisRows($res);
			$this->check(array_keys($rows), array('core|/common/header.php'), 'reverse direction: analysis files');
			$this->check(isset($rows['core|/common/header.php']['toTranslate']['heading_title']), false,
				'reverse direction: existing target file in the admin folder is read');
		}
	}

	private function check($actual, $expected, $message)
	{
		if ($actual === $expected)
		{
			echo " [PASS] {$message}\n";
			$this->passed++;
		}
		else
		{
			$this->fail("{$message} (expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
		}
	}

	private function fail($message)
	{
		echo " [FAIL] {$message}\n";
		$this->failed++;
	}
}
