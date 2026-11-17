<?php

// Language code map: store language codes to Google language codes (for example ua to uk).
// Keys are lowercase and trimmed. Values are trimmed and keep their case (for example zh-CN).
// This class has no OpenCart dependency.
class TranslateExpertLanguageCodeMap
{
	const DEFAULT_JSON = '{"ua":"uk","rs":"sr","ge":"ka","cz":"cs"}';
	const MAX_LENGTH = 20;
	const MAX_PAIRS = 100;

	// Returns an array of key => value. Any bad input gives an empty array.
	public static function parse($value)
	{
		$map = array();
		if (!is_string($value) || trim($value) === '')
		{
			return $map;
		}
		$decoded = json_decode($value, true);
		if (!is_array($decoded) || !self::isObject($value))
		{
			return $map;
		}
		foreach ($decoded as $key => $item)
		{
			if (!is_string($item))
			{
				continue;
			}
			$key = strtolower(trim((string)$key));
			$item = trim($item);
			if ($key === '' || $item === '')
			{
				continue;
			}
			$map[$key] = $item;
		}
		return $map;
	}

	public static function lookup(array $map, $code)
	{
		$key = strtolower(trim((string)$code));
		if ($key !== '' && isset($map[$key]))
		{
			return $map[$key];
		}
		return $code;
	}

	// Returns array('json' => normalized JSON or '', 'errors' => list of array('code' => ..., 'index' => ...)).
	// The index is the 1-based pair number. It is 0 for an error that is not about one pair.
	// Error codes: format, empty_key, empty_value, duplicate, chars, length, count.
	public static function normalize($json)
	{
		$result = array('json' => '', 'errors' => array());
		if (is_null($json) || (is_string($json) && trim($json) === ''))
		{
			return $result;
		}
		$decoded = is_string($json) ? json_decode($json, true) : null;
		if (!is_array($decoded) || !self::isObject($json))
		{
			$result['errors'][] = array('code' => 'format', 'index' => 0);
			return $result;
		}
		if (count($decoded) > self::MAX_PAIRS)
		{
			$result['errors'][] = array('code' => 'count', 'index' => 0);
			return $result;
		}
		$map = array();
		$index = 0;
		foreach ($decoded as $key => $item)
		{
			$index++;
			$key = strtolower(trim((string)$key));
			$item = is_string($item) ? trim($item) : null;
			$code = self::pairError($key, $item);
			if (is_null($code) && isset($map[$key]))
			{
				$code = 'duplicate';
			}
			if (!is_null($code))
			{
				$result['errors'][] = array('code' => $code, 'index' => $index);
				continue;
			}
			$map[$key] = $item;
		}
		if (!$result['errors'] && $map)
		{
			$result['json'] = json_encode($map, JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT);
		}
		return $result;
	}

	private static function pairError($key, $item)
	{
		if ($key === '')
		{
			return 'empty_key';
		}
		if (is_null($item))
		{
			return 'chars';
		}
		if ($item === '')
		{
			return 'empty_value';
		}
		if (!preg_match('/^[A-Za-z0-9_-]+$/', $key) || !preg_match('/^[A-Za-z0-9_-]+$/', $item))
		{
			return 'chars';
		}
		if (strlen($key) > self::MAX_LENGTH || strlen($item) > self::MAX_LENGTH)
		{
			return 'length';
		}
		return null;
	}

	private static function isObject($json)
	{
		return substr(ltrim($json), 0, 1) === '{';
	}
}
