<?php
/**
 * Front-end strings of Translate Expert for the admin header (document.te_i18n_global).
 *
 * Used by the header op in system/OpenCartTranslateExpertClient.ocmod.xml (OC 1.5–3.0)
 * and by eventHeaderViewAfter() in the admin controller (OC4).
 *
 * The header runs on every admin page. A load->language() call there merges all module keys
 * into the shared language object of the page. This class reads the module language file
 * in a function scope instead, and returns only the global keys.
 *
 * Keep this file PHP 5.6 compatible and free of OpenCart dependencies (it is loaded on every admin page).
 */
class TranslateExpertI18n
{
	/**
	 * @return string[] Language keys that document.te_i18n_global holds
	 */
	public static function globalKeys()
	{
		return array(
			'te_global_char_count',
			'te_global_reset',
			'te_global_reset_confirm',
			'te_global_translate',
			'te_global_error_internal',
		);
	}

	/**
	 * Makes the list of language file paths to try: each folder with each sub-path, in the given order.
	 * Skips empty folder names and folder names with characters other than letters, digits, "_" and "-"
	 * (the names come from the database), and skips duplicates.
	 *
	 * @param string   $baseDir  Language root folder with a trailing slash (DIR_LANGUAGE on OC 1.5–3.0)
	 * @param array    $folders  Language folder names, for example the language code and the directory
	 * @param string[] $subPaths Paths of the module file inside a language folder, with a leading slash
	 * @return string[]
	 */
	public static function candidateFiles($baseDir, array $folders, array $subPaths)
	{
		$result = array();
		foreach ($folders as $folder) {
			if (!is_string($folder) || !preg_match('/^[A-Za-z0-9_-]+$/', $folder)) {
				continue;
			}
			foreach ($subPaths as $subPath) {
				$file = (string)$baseDir . $folder . (string)$subPath;
				if (!in_array($file, $result, true)) {
					$result[] = $file;
				}
			}
		}

		return $result;
	}

	/**
	 * Reads an OpenCart language file ($_['key'] = 'value';) without the OpenCart Language class.
	 *
	 * @param string $file Full path of the language file
	 * @return array The $_ array of the file, or an empty array if the file is missing or has no $_ array
	 */
	public static function readFile($file)
	{
		if (!is_string($file) || $file === '' || !is_file($file)) {
			return array();
		}

		$_ = array();
		include $file;

		return is_array($_) ? $_ : array();
	}

	/**
	 * @param array    $strings Key => value array of a language file
	 * @param string[] $keys    Keys to keep
	 * @return array Key => string value, only for the keys that exist with a string value
	 */
	public static function pick(array $strings, array $keys)
	{
		$result = array();
		foreach ($keys as $key) {
			if (isset($strings[$key]) && is_string($strings[$key])) {
				$result[$key] = $strings[$key];
			}
		}

		return $result;
	}

	/**
	 * Reads the global keys from the first candidate file that exists.
	 * Fills the keys that it does not find from the first fallback file that exists.
	 *
	 * @param string[] $candidateFiles Language files of the admin language, in order of preference
	 * @param string[] $fallbackFiles  English language files, in order of preference
	 * @return array Key => string value
	 */
	public static function loadGlobal(array $candidateFiles, array $fallbackFiles)
	{
		$keys = self::globalKeys();

		$result = self::pick(self::readFile(self::firstFile($candidateFiles)), $keys);

		if (count($result) < count($keys)) {
			$fallback = self::pick(self::readFile(self::firstFile($fallbackFiles)), $keys);
			foreach ($fallback as $key => $value) {
				if (!isset($result[$key])) {
					$result[$key] = $value;
				}
			}
		}

		return $result;
	}

	/**
	 * JSON for a <script> block: <, >, &, ' and " are hex-escaped, so a value cannot close the script.
	 *
	 * @param array $strings Key => string value
	 * @return string A JS object literal. "{}" if the array is empty or json_encode() fails.
	 */
	public static function toJson(array $strings)
	{
		if (empty($strings)) {
			return '{}';
		}

		$json = json_encode($strings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

		return is_string($json) && $json !== '' ? $json : '{}';
	}

	/**
	 * @param string[] $files
	 * @return string The first path that is a file, or an empty string
	 */
	private static function firstFile(array $files)
	{
		foreach ($files as $file) {
			if (is_string($file) && $file !== '' && is_file($file)) {
				return $file;
			}
		}

		return '';
	}
}
