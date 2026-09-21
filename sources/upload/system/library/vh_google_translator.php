<?php

class VHGoogleTranslator  {
	private $googleApiKey;

	const GOOGLE_TRANSLATE_MAX_LENGTH = 50 * 1000;

	function __construct($googleApiKey)
	{
		$this->googleApiKey = $googleApiKey;
	}

	public function translate($text, $source = '', $target = 'en', $format = 'html')
	{

		if ($source == 'ua') $source = 'uk';
		if ($target == 'ua') $target = 'uk';

		if ($source == 'rs') $source = 'sr';
		if ($target == 'rs') $target = 'sr';

		if ($source == 'ge') $source = 'ka';
		if ($target == 'ge') $target = 'ka';

		if ($source == 'cz') $source = 'cs';
		if ($target == 'cz') $target = 'cs';

		$res = $this->translateInternal($text, [
			'source' => $source,
			'target' => $target,
			'format' => $format
		]);

		$res = $this->fixFormatParameters($text, $res);

		$data = (object)array('success' => 1, 'text' => $res);
		return $data;
	}

	public function translateBatch($texts, $source = '', $target = 'en', $format = 'html')
	{

		if ($source == 'ua') $source = 'uk';
		if ($target == 'ua') $target = 'uk';

		if ($source == 'rs') $source = 'sr';
		if ($target == 'rs') $target = 'sr';

		if ($source == 'ge') $source = 'ka';
		if ($target == 'ge') $target = 'ka';

		if ($source == 'cz') $source = 'cs';
		if ($target == 'cz') $target = 'cs';

		// Empty/null/whitespace-only values are not sent to Google - they are
		// returned unchanged, in their original position.
		$resultTexts = array_values($texts);

		$toTranslateIndexes = array();
		$toTranslateTexts = array();
		foreach ($resultTexts as $index => $text)
		{
			if ($text && trim($text))
			{
				$toTranslateIndexes[] = $index;
				$toTranslateTexts[] = $text;
			}
		}

		if (!empty($toTranslateTexts))
		{
			$translatedTexts = $this->translateInternalBatch($toTranslateTexts, [
				'source' => $source,
				'target' => $target,
				'format' => $format
			]);

			foreach ($toTranslateIndexes as $pos => $originalIndex)
			{
				$resultTexts[$originalIndex] = $this->fixFormatParameters($toTranslateTexts[$pos], $translatedTexts[$pos]);
			}
		}

		$data = (object)array('success' => 1, 'texts' => $resultTexts);
		return $data;
	}

	private function fixFormatParameters($text, $translatedText)
	{
		if (is_array($text))
		{
			if (count($text) != count($translatedText))
				return $translatedText;

			$res = array();
			$i = 0;
			foreach ($text as $t)
			{
				$res[] = $this->fixFormatParameters($t, $translatedText[$i]);
				$i++;
			}
			return $res;
		}

		$res = $translatedText;
		$formatParameters = array('d' => 'д', 's' => 'с');
		foreach ($formatParameters as $paramKey => $paramValue)
			$res = $this->fixFormatParameter($text, $res, $paramKey, $paramValue);
		return $res;
	}

	private function fixFormatParameter($text, $translatedText, $paramKey, $paramValue)
	{
		$regExp1 = '/(%'.$paramKey.')[^a-zA-Z]/ms';
		if (preg_match_all($regExp1, $text, $matches, PREG_SET_ORDER, 0) !== false)
		{
			$regExp2 = '/(%\s*'.$paramValue.')[^a-zA-Z]/ms';
			foreach ($matches as $match)
			{
				$translatedText = preg_replace($regExp2, $match[1], $translatedText, 1);
			}
		}

		return $translatedText;
	}

	private function translateInternal($text, $options)
	{
		$translatedResult = '';
		while ($text != '')
		{
			$countToTranslate = strlen($text);
			if ($countToTranslate > self::GOOGLE_TRANSLATE_MAX_LENGTH)
			{
				$countToTranslate = strrpos($text, '.', self::GOOGLE_TRANSLATE_MAX_LENGTH - strlen($text));
				if ($countToTranslate === false)
					$countToTranslate = strrpos($text, '>', self::GOOGLE_TRANSLATE_MAX_LENGTH - strlen($text));
				if ($countToTranslate === false)
					$countToTranslate = strrpos($text, ' ', self::GOOGLE_TRANSLATE_MAX_LENGTH - strlen($text));
				if ($countToTranslate === false)
					$countToTranslate = self::GOOGLE_TRANSLATE_MAX_LENGTH - 1;
			}

			$textToTranslate = substr($text, 0, $countToTranslate + 1);

			$res = $this->callGoogleTranslateApi($textToTranslate, $options);
			$translatedResult .= $res;

			$text = substr($text, $countToTranslate + 1);
		}

		return $translatedResult;
	}

	/**
	 * Translates a list of non-empty texts, grouping them into as few Google
	 * Translate API requests as possible (one request per group of texts
	 * whose combined length fits under GOOGLE_TRANSLATE_MAX_LENGTH). A text
	 * that alone exceeds the limit is translated on its own via translateInternal(),
	 * which chunks it into sequential requests. Returns translations in the
	 * same order as $texts.
	 */
	private function translateInternalBatch($texts, $options)
	{
		$results = array_fill(0, count($texts), '');

		$batchIndexes = array();
		$batchTexts = array();
		$batchLength = 0;

		$flushBatch = function() use (&$batchIndexes, &$batchTexts, &$batchLength, &$results, $options)
		{
			if (empty($batchTexts))
				return;

			$translated = $this->callGoogleTranslateApiBatch($batchTexts, $options);

			foreach ($batchIndexes as $pos => $originalIndex)
				$results[$originalIndex] = $translated[$pos];

			$batchIndexes = array();
			$batchTexts = array();
			$batchLength = 0;
		};

		foreach ($texts as $index => $text)
		{
			if (strlen($text) > self::GOOGLE_TRANSLATE_MAX_LENGTH)
			{
				$flushBatch();
				$results[$index] = $this->translateInternal($text, $options);
				continue;
			}

			if ($batchLength + strlen($text) > self::GOOGLE_TRANSLATE_MAX_LENGTH)
				$flushBatch();

			$batchIndexes[] = $index;
			$batchTexts[] = $text;
			$batchLength += strlen($text);
		}

		$flushBatch();

		return $results;
	}

	protected function callGoogleTranslateApi($text, $options)
	{
		$postData = array(
			'q'      => $text,
			'target' => $options['target'],
			'format' => isset($options['format']) ? $options['format'] : 'html'
		);

		if (!empty($options['source']))
			$postData['source'] = $options['source'];

		$translations = $this->sendGoogleTranslateRequest($postData);

		if (!isset($translations[0]['translatedText']))
			throw new Exception('Google Translate API: unexpected response format');

		return $translations[0]['translatedText'];
	}

	protected function callGoogleTranslateApiBatch($texts, $options)
	{
		$postData = array(
			'q'      => array_values($texts),
			'target' => $options['target'],
			'format' => isset($options['format']) ? $options['format'] : 'html'
		);

		if (!empty($options['source']))
			$postData['source'] = $options['source'];

		$translations = $this->sendGoogleTranslateRequest($postData);

		if (count($translations) !== count($texts))
			throw new Exception('Google Translate API: unexpected response format');

		$translatedTexts = array();
		foreach ($translations as $translation)
		{
			if (!isset($translation['translatedText']))
				throw new Exception('Google Translate API: unexpected response format');
			$translatedTexts[] = $translation['translatedText'];
		}

		return $translatedTexts;
	}

	private function sendGoogleTranslateRequest($postData)
	{
		$url = 'https://translation.googleapis.com/language/translate/v2?key=' . urlencode($this->googleApiKey);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		if ($curlError)
			throw new Exception('Google Translate API curl error: ' . $curlError);

		$responseData = json_decode($response, true);

		if ($httpCode !== 200 || !$responseData)
		{
			$errorMessage = 'Google Translate API error (HTTP ' . $httpCode . ')';
			if (isset($responseData['error']['message']))
				$errorMessage .= ': ' . $responseData['error']['message'];
			throw new Exception($errorMessage);
		}

		if (!isset($responseData['data']['translations']) || !is_array($responseData['data']['translations']))
			throw new Exception('Google Translate API: unexpected response format');

		return $responseData['data']['translations'];
	}
}
