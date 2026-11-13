<?php

if (defined('DIR_EXTENSION') && version_compare(VERSION, '4.0', '>=')) {
	include_once(DIR_EXTENSION . 'client_translate_expert/system/library/client_translate_expert_core.php');
} else {
	include_once(DIR_SYSTEM . 'library/client_translate_expert_core.php');
}

class LocalizationLibraryClientTranslateExpert extends LibraryClientTranslateExpertCore
{
	const ANALIZE_BULK_SIZE = 10;

	const SCOPE_CATALOG = 'catalog';
	const SCOPE_ADMIN = 'admin';
	const ROOT_CORE = 'core';

	public function analizeLocalization($languageIdFrom, $languageIdTo, $nextFileIndex = 0, $scope = self::SCOPE_CATALOG)
	{
		$this->gebugLog('analizeLocalization: ============START============', $startTime);

		$this->loadLanguage($this->_modulePrefix . '/client_translate_expert');
		$this->_model->load->model('localisation/language');
		if (!$this->isLocalizationScope($scope))
			return (object)array('success' => 0, 'error_code' => 3004, 'message' => 'Internal error.',
				'additional_message' => 'analizeLocalization: unknown scope');
		if ($languageIdFrom == $languageIdTo)
			return (object)array('success' => 0, 'error_code' => 3003, 'message' => 'Internal error.',
				'additional_message' => $this->_model->language->get('error_lang_from_and_lang_to_are_equal'));
		$languageFrom = $this->_model->model_localisation_language->getLanguage($languageIdFrom);
		$languageTo = $this->_model->model_localisation_language->getLanguage($languageIdTo);
		// array ( 'language_id' => '1', 'name' => 'Russian', 'code' => 'ru-ru', 'locale' => 'ru_RU.UTF-8,ru_RU,russian', 'image' => 'ru.png', 'directory' => 'russian', 'sort_order' => '3', 'status' => '1', );

		$roots = $this->getLocalizationRoots($scope, $languageFrom, $languageTo);

		if (!file_exists($roots[self::ROOT_CORE]['from']))
			return (object)array('success' => 0, 'error_code' => 3001, 'message' => 'Internal error.',
				'additional_message' => $this->_model->language->get('error_lang_from_folder_is_not_exists') . ' ' . $roots[self::ROOT_CORE]['from']);

		$includeFolder = $this->getLocalizationIncludeFolder($scope);
		$mainFileFrom = '/' . $this->getMainLanguageFileName($languageFrom) . '.php';
		$mainFileTo = '/' . $this->getMainLanguageFileName($languageTo) . '.php';

		//$res = (object)array('success' => 0, 'error_code' => 2001, 'message' => 'Internal error.', 'additional_message' => $this->_model->language->get('error_unlicensed'))
		$analize = array();
		foreach ($roots as $rootId => $root)
		{
			$languageFromFiles = $this->getDirContents($root['from'], '.php');
			$languageToFiles = file_exists($root['to']) ? $this->getDirContents($root['to'], '.php') : array();

			foreach($languageFromFiles as $path => $contentFrom)
			{
				$pathTo = $path;
				// The main language file has the name of the language (for example en-gb/en-gb.php). It exists in the core root only.
				if ($rootId == self::ROOT_CORE && $pathTo == $mainFileFrom)
					$pathTo = $mainFileTo;

				$contentTo = isset($languageToFiles[$pathTo]) ? $languageToFiles[$pathTo] : '';
				$analizeLocalizationFileResult = $this->analizeLocalizationFile($languageFrom, $languageTo, $contentFrom, $contentTo, $path, $pathTo, $includeFolder);
				$analizeLocalizationFileResult['root'] = $rootId;
				$analizeLocalizationFileResult['rootName'] = $root['name'];
				$analize[] = $analizeLocalizationFileResult;
			}
		}

		$analizeHtml = $this->buildAnalizeHtml($analize);
		$analizeDetails = array_slice($analize, $nextFileIndex, self::ANALIZE_BULK_SIZE);
		$analizeDetailsHtml = $this->buildAnalizeDetailedHtml($analizeDetails);

		//$analizeHtml .= var_export(array_keys($languageFromFiles), true);
		//$analizeHtml .= '<br><br>';
		//$analizeHtml .= var_export(array_keys($languageToFiles), true);

		$newNextFileIndex = $nextFileIndex + self::ANALIZE_BULK_SIZE;
		$done = $newNextFileIndex >= count($analize);
		$res = (object)array('success' => 1, 'done' => $done, 'analize' => $analizeDetails, 'html' => $analizeHtml, 'detailsHtml' => $analizeDetailsHtml, 'nextFileIndex' => $newNextFileIndex);

		$this->gebugLog(var_export($res, true));
		$this->gebugLog('analizeLocalization: ============FINISH============', $startTime);
		return $res;
	}

	public function saveLocalizationFile($fileContent, $langId, $path, $scope = self::SCOPE_CATALOG, $root = self::ROOT_CORE)
	{
		$this->gebugLog('analizeLocalization: ============START============', $startTime);

		if (is_null($fileContent) || is_null($langId) || is_null($path) || !$fileContent || !$langId || !$path)
		{
			return (object)array('success' => 0, 'error_code' => 3003, 'message' => 'Internal error.',
				'additional_message' => 'saveLocalizationFile: emty parameters is not allowed');
		}

		$this->loadLanguage($this->_modulePrefix . '/client_translate_expert');
		$this->_model->load->model('localisation/language');

		if (!$this->isValidLocalizationPath($path) || !$this->isLocalizationScope($scope))
			return $this->getSaveTargetInvalidResult();

		$language = $this->_model->model_localisation_language->getLanguage($langId);
		if (empty($language) || empty($language['code']))
			return $this->getSaveTargetInvalidResult();

		$languageFolder = $this->getLocalizationTargetFolder($scope, $root, $language);
		if (!$languageFolder)
			return $this->getSaveTargetInvalidResult();

		$fullPath = rtrim($languageFolder, '/') . $path;
		$fullFolder = dirname($fullPath);
		// The OC 4 admin error handler ignores "@": it redirects or prints on a warning. A local handler keeps the JSON answer.
		set_error_handler(function () { return true; });
		$saved = (is_dir($fullFolder) || mkdir($fullFolder, 0777, true) || is_dir($fullFolder))
			&& file_put_contents($fullPath, $fileContent) !== false;
		restore_error_handler();
		if (!$saved)
			return $this->getSaveFailedResult($fullFolder);

		$res = (object)array('success' => 1);

		$this->gebugLog(var_export($res, true));
		$this->gebugLog('analizeLocalization: ============FINISH============', $startTime);
		return $res;
	}

	protected function isLocalizationScope($scope)
	{
		return $scope === self::SCOPE_CATALOG || $scope === self::SCOPE_ADMIN;
	}

	// A file path relative to a language folder, as the analysis gives it (for example /common/header.php).
	protected function isValidLocalizationPath($path)
	{
		if (!is_string($path) || strlen($path) < 5)
			return false;
		if (substr($path, 0, 1) !== '/' || substr($path, -4) !== '.php')
			return false;
		if (strpos($path, '..') !== false || strpos($path, '\\') !== false || strpos($path, "\0") !== false)
			return false;
		return true;
	}

	private function getSaveTargetInvalidResult()
	{
		return (object)array('success' => 0, 'error_code' => 3005,
			'message' => $this->_model->language->get('error_localization_save_target_invalid'));
	}

	private function getSaveFailedResult($folder)
	{
		return (object)array('success' => 0, 'error_code' => 3007,
			'message' => $this->_model->language->get('error_localization_save_failed') . ' ' . $folder);
	}

	// The name (without .php) of the main language file: OC 1.5 "filename", OC 2.0-2.1 "directory", OC 2.2+ "code".
	// A store upgraded from OC 1.5 can keep the "filename" column, so use it on OC 1.5 only.
	protected function getMainLanguageFileName($language)
	{
		if (version_compare(VERSION, '2.0', '<') && !empty($language['filename']))
			return $language['filename'];
		if (version_compare(VERSION, '2.2', '<') && !empty($language['directory']))
			return $language['directory'];
		return $language['code'];
	}

	protected function getLocalizationIncludeFolder($scope)
	{
		return $scope === self::SCOPE_ADMIN ? DIR_APPLICATION . 'language/' : DIR_CATALOG . 'language/';
	}

	protected function isOc4ExtensionName($name)
	{
		return is_string($name) && preg_match('/^[A-Za-z0-9_\-]+$/', $name)
			&& version_compare(VERSION, '4.0', '>=') && defined('DIR_EXTENSION')
			&& is_dir(DIR_EXTENSION . $name . '/admin/language');
	}

	// The folder to save a translated file into. Returns false for an unknown scope or root.
	protected function getLocalizationTargetFolder($scope, $root, $languageTo)
	{
		if ($root === self::ROOT_CORE)
		{
			if ($scope === self::SCOPE_ADMIN)
				return $this->getAdminLanguageFolder($languageTo);
			if ($scope === self::SCOPE_CATALOG)
				return $this->getLanguageFolder($languageTo);
			return false;
		}

		// OC 4.x: the admin language folder of an installed extension (root "ext:<extension folder>").
		if ($scope === self::SCOPE_ADMIN && is_string($root) && substr($root, 0, 4) === 'ext:')
		{
			$name = substr($root, 4);
			if ($this->isOc4ExtensionName($name) && (empty($languageTo['extension']) || $name !== $languageTo['extension']))
				return DIR_EXTENSION . $name . '/admin/language/' . $languageTo['code'] . '/';
		}

		return false;
	}

	// The language folders to analyze: array(rootId => array('from' => folder, 'to' => folder, 'name' => extension name)).
	protected function getLocalizationRoots($scope, $languageFrom, $languageTo)
	{
		$roots = array();
		$roots[self::ROOT_CORE] = array(
			'from' => $scope === self::SCOPE_ADMIN ? $this->getAdminLanguageFolder($languageFrom) : $this->getLanguageFolder($languageFrom),
			'to' => $this->getLocalizationTargetFolder($scope, self::ROOT_CORE, $languageTo),
			'name' => '');

		if ($scope !== self::SCOPE_ADMIN || version_compare(VERSION, '4.0', '<') || !defined('DIR_EXTENSION') || !is_dir(DIR_EXTENSION))
			return $roots;

		// The language extensions of the two languages hold the core admin files of these languages: the core root covers them.
		$skip = array();
		if (!empty($languageFrom['extension']))
			$skip[] = $languageFrom['extension'];
		if (!empty($languageTo['extension']))
			$skip[] = $languageTo['extension'];

		$names = scandir(DIR_EXTENSION);
		sort($names);
		foreach ($names as $name)
		{
			if ($name == '.' || $name == '..' || in_array($name, $skip, true) || !$this->isOc4ExtensionName($name))
				continue;
			$from = DIR_EXTENSION . $name . '/admin/language/' . $languageFrom['code'] . '/';
			if (!is_dir($from))
				continue;
			$rootId = 'ext:' . $name;
			$roots[$rootId] = array(
				'from' => $from,
				'to' => $this->getLocalizationTargetFolder($scope, $rootId, $languageTo),
				'name' => $name);
		}

		return $roots;
	}

	private function buildAnalizeHtml($analize)
	{
		$analizeTotals = $this->calcAnalizeTotals($analize);

		$entry_fileCount = $this->_model->language->get('entry_fileCount');
		$entry_needToTranslateFileCount = $this->_model->language->get('entry_needToTranslateFileCount');
		$entry_needToTranslateValueCount = $this->_model->language->get('entry_needToTranslateValueCount');
		$entry_needToTranslateCharCount = $this->_model->language->get('entry_needToTranslateCharCount');
		$entry_TranslateAll = $this->_model->language->get('entry_TranslateAll');

		$html = '';
		$html .= '<div class="container localization-analize-totals">';
		$html .= 	"<div class='row localization-analize-total-row'>";
		$html .= 		"<div class='col-md-3'>{$entry_fileCount}</div>";
		$html .= 		"<div class='col-md-3'>{$analizeTotals['fileCount']}</div>";
		$html .= 	'</div>';
		$html .= 	"<div class='row localization-analize-total-row'>";
		$html .= 		"<div class='col-md-3'>{$entry_needToTranslateFileCount}</div>";
		$html .= 		"<div class='col-md-3'>{$analizeTotals['needToTranslateFileCount']}</div>";
		$html .= 	'</div>';
		$html .= 	"<div class='row localization-analize-total-row'>";
		$html .= 		"<div class='col-md-3'>{$entry_needToTranslateValueCount}</div>";
		$html .= 		"<div class='col-md-3'>{$analizeTotals['needToTranslateValueCount']}</div>";
		$html .= 	'</div>';
		$html .= 	"<div class='row localization-analize-total-row'>";
		$html .= 		"<div class='col-md-3'>{$entry_needToTranslateCharCount}</div>";
		$html .= 		"<div class='col-md-3'>{$analizeTotals['needToTranslateCharCount']}</div>";
		$html .= 	'</div>';
		if ($analizeTotals['needToTranslateFileCount'] > 0)
		{
			$html .= 	"<div class='row localization-analize-total-row'>";
			$html .= 		"<div class='col-md-3'><button type='button' class='translate_localization_all_button' title='{$entry_TranslateAll}' onclick='doTranslateLocalizationAllExpert(0, this)'><i class='fa fa-language'></i>{$entry_TranslateAll}</button></div>";
			$html .= 	'</div>';
			$html .= "";
		}
		$html .= '</div>';
		$html .= '<br><br>';

		$html .= '<div class="container localization-analize-table">';
		$html .= '</div>';
//		$html .= '<script>initTranslateExpertLocalization();</script>';

		return $html;
	}

	private function buildAnalizeDetailedHtml($analize)
	{
		$toTranslateInfo = $this->_model->language->get('entry_localization_file_to_translate_info');
		$toTranslateInfoFullTranslated = $this->_model->language->get('entry_localization_file_to_translate_info_full_translated');

		$html = '';
		foreach($analize as $analizeFile)
		{
			$contentFrom = $analizeFile['contentFrom'];
			$contentTo = $analizeFile['contentTo'];

			$langFrom = $analizeFile['languageFrom'];
			$langTo = $analizeFile['languageTo'];

			$langFromImgUrl = $this->getLangImgUrl($langFrom['code']);
			$langToImgUrl = $this->getLangImgUrl($langTo['code']);

			$toTranslate = $analizeFile['toTranslate'];
			$toTranslateCount = count($toTranslate);

			$contentFrom = $this->highlightToTranslate($contentFrom, $toTranslate);

			$displayPath = $analizeFile['pathFrom'];
			if ($analizeFile['pathFrom'] != $analizeFile['pathTo'])
				$displayPath = $analizeFile['pathFrom'] . ' => ' . $analizeFile['pathTo'];
			$rootName = isset($analizeFile['rootName']) ? $analizeFile['rootName'] : '';
			if ($rootName !== '')
				$displayPath = '[' . $rootName . '] ' . $displayPath;
			$displayPath = htmlspecialchars($displayPath, ENT_QUOTES, 'UTF-8');
			$pathToAttr = htmlspecialchars($analizeFile['pathTo'], ENT_QUOTES, 'UTF-8');
			$rootAttr = htmlspecialchars(isset($analizeFile['root']) ? $analizeFile['root'] : self::ROOT_CORE, ENT_QUOTES, 'UTF-8');

			$fileToTranslateInfo = ($toTranslateCount == 0)
				? str_replace('{path}', $displayPath, $toTranslateInfoFullTranslated)
				: $fileToTranslateInfo = str_replace(array('{path}', '{count}'), array($displayPath, $toTranslateCount), $toTranslateInfo);

			//$contentFrom = htmlentities($contentFrom); // already entited
			$contentTo = htmlentities($contentTo);

			$html .= "<div class='row localization-analize-row' data-path-to='{$pathToAttr}' data-root='{$rootAttr}'>";
			$html .= 	"<div class='col-md-12 localization-analize-file-header'><div class='info' onclick='clickTranslateExpertLocalizationHeader(this)'>{$fileToTranslateInfo}</div></div>";
			$html .= "</div>";
			$html .= "<div class='row localization-analize-row info' style='display:none;'>";
			$html .= 	"<div class='col-md-6 localization-analize-file-toolbar from'>";
			$html .= 		"<img src='{$langFromImgUrl}' alt='{$langFrom['name']}' title='{$langFrom['name']}'>";
			$html .= 	"</div>";
			$html .= 	"<div class='col-md-6 localization-analize-file-toolbar to'>";
			$html .= 		"<img src='{$langToImgUrl}' alt='{$langTo['name']}' title='{$langTo['name']}'>";
			if ($toTranslateCount > 0)
				$html .= 	"<button type='button' class='translate_localization_button' title='Translate' onclick='doTranslateLocalizationExpert(this); return false;'><i class='fa fa-language'></i> Translate</button>";
			$html .= 		"<button type='button' class='save_translation_button' title='Save' onclick='doSaveLocalizationExpert(this); return false;'><i class='fa fa-save'></i> Save</button>";
			$html .= 	"</div>";
			$html .= "</div>";
			$html .= "<div class='row localization-analize-row info' style='display:none;'>";
			$html .= 	"<div class='col-md-6 localization-analize-file from'><pre data-lang-from='{$langFrom['language_id']}'>{$contentFrom}</pre></div>";
			$html .= 	"<div class='col-md-6 localization-analize-file to'><textarea data-lang-to='{$langTo['language_id']}'>{$contentTo}</textarea></div>";
			$html .= "</div>";
			$html .= "<div class='row localization-analize-row info' style='display:none;'>";
			$html .= "</div>";
		}
		$html .= '</div>';

		return $html;
	}

	private function calcAnalizeTotals($analize)
	{
		$fileCount = count($analize);
		$needToTranslateFileCount = 0;
		$needToTranslateValueCount = 0;
		$needToTranslateCharCount = 0;

		foreach($analize as $analizeFile)
		{
			$toTranslate = $analizeFile['toTranslate'];
			$toTranslateCount = count($toTranslate);
			if ($toTranslateCount > 0)
				$needToTranslateFileCount++;
			$needToTranslateValueCount += $toTranslateCount;
			foreach($toTranslate as $key => $toTranslateLine)
			{
				// The admin controller html_entity_decode()s each text before the send and the count: use the same call.
				$needToTranslateCharCount += mb_strlen(trim(html_entity_decode($toTranslateLine['text'])), 'UTF-8');
			}
		}

		return array(
			'fileCount' => $fileCount,
			'needToTranslateFileCount' => $needToTranslateFileCount,
			'needToTranslateValueCount' => $needToTranslateValueCount,
			'needToTranslateCharCount' => $needToTranslateCharCount,
		);
	}

	private function highlightToTranslate($content, $toTranslate)
	{
		foreach($toTranslate as $key => $toTranslateLine)
			$content = str_replace($toTranslateLine['full_text'], '{begin_highlight_vh}' . $toTranslateLine['full_text'] . '{end_highlight_vh}', $content);
		$content = htmlentities($content);
		$content = str_replace('{begin_highlight_vh}', '<b>', $content);
		$content = str_replace('{end_highlight_vh}', '</b>', $content);
		return $content;
	}

	public function analizeLocalizationFile($languageFrom, $languageTo, $contentFrom, $contentTo, $pathFrom, $pathTo, $includeFolder = null)
	{
		if (is_null($includeFolder))
			$includeFolder = DIR_CATALOG . 'language/';
		$contentFrom = $this->parsePhpInclude($contentFrom, $includeFolder);
		$contentTo = $this->parsePhpInclude($contentTo, $includeFolder);

		$contentFromParsed = $this->parseLocalizationFile($contentFrom);
		$contentToParsed = $this->parseLocalizationFile($contentTo);
		$toTranslate = array();
		foreach ($contentFromParsed as $key => $valueFrom)
		{
			if (!isset($contentToParsed[$key]))
				$toTranslate[$key] = $valueFrom;
		}

		$res = array(
			'languageFrom' => $languageFrom,
			'languageTo' => $languageTo,
			'pathFrom' => $pathFrom,
			'pathTo' => $pathTo,
			'toTranslate' => $toTranslate,
			'contentFrom' => $contentFrom,
			'contentTo' => $contentTo);
		return $res;
	}

	private function parsePhpInclude($content, $includeFolder)
	{
		$regExp = '/(?:include|require_once)\s*\(\s*DIR_LANGUAGE\s*\.\s*[\'\"](.*?)[\'\"]\s*\)\s*;/ms';
		if (preg_match_all($regExp, $content, $matches, PREG_SET_ORDER, 0) !== false)
		{
			foreach ($matches as $match)
			{
				$includePath = $includeFolder . $match[1];
				$includedContent = file_exists($includePath) ? file_get_contents($includePath) : '';
				$includedContent = $this->parsePhpInclude($includedContent, $includeFolder);
				$localizationFile = $this->parseLocalizationFile($includedContent);
				$replaceTo = '';
				foreach ($localizationFile as $key => $value)
					$replaceTo .= $value['full_text'] . "\n";
				$content = str_replace($match[0], $replaceTo, $content);
			}
		}
		return $content;
	}

	public function parseLocalizationFile($content)
	{
		$res = array();

		$regExp1 = '/\$\_\[[\'\"]([\w\_\d]+)[\'\"]]\s*?=\s*?\'((\\\\.|[^\'])*)\'\s*?\;/ms';
		if (preg_match_all($regExp1, $content, $matches, PREG_SET_ORDER, 0) !== false)
		{
			foreach ($matches as $match)
			{
				$res[$match[1]] = array('text' => $match[2], 'full_text' => $match[0]);
			}
		}

		$regExp2 = '/\$\_\[[\'\"]([\w\_\d]+)[\'\"]]\s*?=\s*?\"((\\\\.|[^\"])*)\"\s*?\;/ms';
		if (preg_match_all($regExp2, $content, $matches, PREG_SET_ORDER, 0) !== false)
		{
			foreach ($matches as $match)
			{
				$res[$match[1]] = array('text' => $match[2], 'full_text' => $match[0]);
			}
		}

		return $res;
	}
}
