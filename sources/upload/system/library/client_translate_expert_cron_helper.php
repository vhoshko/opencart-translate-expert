<?php
/**
 * Pure helpers for the Translate Expert cron link (secret rules and request parameters).
 *
 * Keep this file PHP 5.6 compatible and free of OpenCart dependencies.
 */
class ClientTranslateExpertCronHelper
{
	const SECRET_MIN_LENGTH = 16;
	const SECRET_MAX_LENGTH = 128;
	const BULK_AMOUNT_DEFAULT = 1000;
	const BULK_AMOUNT_MAX = 100000;
	const LIST_MAX_ITEMS = 100;

	public static function isValidSecret($secret)
	{
		return is_string($secret) && preg_match('/^[A-Za-z0-9_-]{' . self::SECRET_MIN_LENGTH . ',' . self::SECRET_MAX_LENGTH . '}$/D', $secret) === 1;
	}

	public static function secretsMatch($known, $given)
	{
		if (!is_string($known) || !is_string($given))
			return false;

		if (function_exists('hash_equals'))
			return hash_equals($known, $given);

		$knownLen = strlen($known);
		if (strlen($given) !== $knownLen)
			return false;

		$result = 0;
		for ($i = 0; $i < $knownLen; $i++)
			$result |= (ord($known[$i]) ^ ord($given[$i]));

		return $result === 0;
	}

	public static function clampBulkAmount($value)
	{
		$value = (int)$value;
		if ($value < 1)
			return self::BULK_AMOUNT_DEFAULT;
		if ($value > self::BULK_AMOUNT_MAX)
			return self::BULK_AMOUNT_MAX;
		return $value;
	}

	/**
	 * @param array $get Request GET values
	 * @return array|bool Normalized filters, or false if a parameter is not valid
	 */
	public static function parseParams(array $get)
	{
		$langIdFrom = self::positiveInt(isset($get['langIdFrom']) ? $get['langIdFrom'] : null);
		$langIdTo = self::positiveInt(isset($get['langIdTo']) ? $get['langIdTo'] : null);
		if ($langIdFrom === false || $langIdTo === false || $langIdFrom === $langIdTo)
			return false;

		$mode = isset($get['mode']) ? $get['mode'] : 'both';
		if (!is_string($mode) || !in_array($mode, array('both', 'only_empty', 'same_value'), true))
			return false;

		$table = self::parseTableList(isset($get['table']) ? $get['table'] : '');
		if ($table === false)
			return false;

		$categoryId = self::parseIdList(isset($get['product_category_id']) ? $get['product_category_id'] : '');
		$stockStatus = self::parseIdList(isset($get['stock_status']) ? $get['stock_status'] : '');
		$manufacturerId = self::parseIdList(isset($get['manufacturer_id']) ? $get['manufacturer_id'] : '');
		if ($categoryId === false || $stockStatus === false || $manufacturerId === false)
			return false;

		$bulkAmount = self::optionalInt(isset($get['bulk_amount']) ? $get['bulk_amount'] : '');
		if ($bulkAmount === false)
			return false;
		if ($bulkAmount !== '')
			$bulkAmount = self::clampBulkAmount($bulkAmount);

		return array(
			'langIdFrom' => $langIdFrom,
			'langIdTo' => $langIdTo,
			'mode' => $mode,
			'table' => $table,
			'product_status' => isset($get['product_status']) && $get['product_status'] === '1',
			'product_quantity' => isset($get['product_quantity']) && $get['product_quantity'] === '1',
			'product_category_id' => $categoryId,
			'stock_status' => $stockStatus,
			'manufacturer_id' => $manufacturerId,
			'bulk_amount' => $bulkAmount,
		);
	}

	/**
	 * @param mixed $value Comma-separated positive integers
	 * @return array|bool List of unique integers ('' or null = empty list = no filter), or false if an item is not valid or a non-empty value has no items or more than LIST_MAX_ITEMS unique items
	 */
	public static function parseIdList($value)
	{
		if ($value === '' || $value === null)
			return array();
		if (is_int($value))
			$value = (string)$value;
		if (!is_string($value))
			return false;

		$ids = array();
		foreach(explode(',', $value) as $item)
		{
			$item = trim($item);
			if ($item === '')
				continue;
			$id = self::positiveInt($item);
			if ($id === false)
				return false;
			$ids[] = $id;
		}
		$ids = array_values(array_unique($ids));
		return count($ids) > 0 && count($ids) <= self::LIST_MAX_ITEMS ? $ids : false;
	}

	/**
	 * @param mixed $value Comma-separated table names
	 * @return array|bool List of unique names ('' or null = empty list = no filter), or false if the value is not a string or a non-empty value has no items or more than LIST_MAX_ITEMS unique items
	 */
	public static function parseTableList($value)
	{
		if ($value === '' || $value === null)
			return array();
		if (!is_string($value))
			return false;

		$tables = array();
		foreach(explode(',', $value) as $item)
		{
			$item = trim($item);
			if ($item !== '')
				$tables[] = $item;
		}
		$tables = array_values(array_unique($tables));
		return count($tables) > 0 && count($tables) <= self::LIST_MAX_ITEMS ? $tables : false;
	}

	public static function settingKey($name, $version)
	{
		return (version_compare($version, '3.0', '>=') ? 'module_' : '') . $name;
	}

	private static function positiveInt($value)
	{
		if (!is_string($value) && !is_int($value))
			return false;
		$value = (string)$value;
		if (!preg_match('/^[0-9]{1,9}$/D', $value) || (int)$value < 1)
			return false;
		return (int)$value;
	}

	private static function optionalInt($value)
	{
		if ($value === '' || $value === null)
			return '';
		return self::positiveInt($value);
	}
}
