
	
	private function GetFixInfo2()
	{
		$field = 'question';
		$table = 'oc_product_faq';
		$key = 'product_faq_id';
		$sourceLangIds = array(6, 7);
		$targetLangId = 9;
		$format = 'html';
		

		$sourceLangCodes = array();
		$this->load->model('localisation/language');
		foreach($sourceLangIds as $sourceLangId)
		{
			$sourceLangCodes[$sourceLangId] = $this->model_localisation_language->getLanguage($sourceLangId)['code'];
		}
		$target = $this->model_localisation_language->getLanguage($targetLangId)['code'];

		$result = '';
		$sql = "select * from {$table} order by product_id";
		$rows = $this->db->query($sql)->rows;
		$count = 0;
		$this->load->model('module/client_translate_expert');
		foreach($rows as $row)
		{
			$valueStr = $row[$field];
			$value = unserialize($valueStr);
			if (!isset($value[$targetLangId]) || !$value[$targetLangId])
			{
				foreach($sourceLangIds as $sourceLangId)
				{
					if (isset($value[$sourceLangId]) && $value[$sourceLangId])
					{
						$text = $value[$sourceLangId];
						$source = $sourceLangCodes[$sourceLangId];
						break;
					}	
				}
				
				if (isset($text) && $text)
				{
					$result .= $row['product_id'] . '<br>';
					$translateResult = $this->model_module_client_translate_expert->translate($text, $source, $target, $format);
					if ($translateResult->success)
					{
						$textNew = $translateResult->text;
						$charset = mb_detect_encoding($textNew);
						$textNew = iconv($charset, "UTF-8", $textNew);
						$valueNew = $value;
						$valueNew[$targetLangId] = $textNew;
						$valueNewStr = $this->db->escape(serialize($valueNew));
						$updateSql = "update {$table} set {$field} = '{$valueNewStr}' where {$key} = " . $row[$key];
						$this->db->query($updateSql);
						$result .= var_export($text, true) . '<br>' . var_export($textNew, true) . '<br>' . var_export($value, true) . '<br>' . var_export($valueNew, true);
					}
					else
						$result .= 'ERROR!! ' . var_export($translateResult, true);
					$result .= '<br><br>';
					$count++;
					if ($count >= 100)
						break;
				}
			}
		}
		/*
		*/
		$result .= "Count: {$count}<br>";
		return $result;
	}

	