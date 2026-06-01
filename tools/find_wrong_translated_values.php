	private function GetFixInfo()
	{
		$result = '';

		$tableRows = $this->db->query("SELECT table_name as 'table_name' FROM information_schema.tables WHERE table_schema = '" . DB_DATABASE . "' order by table_name")->rows;
		$tables = array();
		$ignore = array(DB_PREFIX.'language', DB_PREFIX.'customer', DB_PREFIX.'customer_search', DB_PREFIX.'order', DB_PREFIX.'translation');
        foreach($tableRows as $tableRow)
		{
			$tableRow = array_change_key_case($tableRow);
			if (array_search($tableRow['table_name'], $ignore) === false)
				$tables[] = $tableRow['table_name'];	
		}
		
		foreach($tables as $table)
		{
			$columns = $this->db->query("
				SELECT column_name as 'column_name', data_type as 'data_type', column_key as 'column_key', extra as 'extra'
				from INFORMATION_SCHEMA.COLUMNS 
				where table_schema = '" . DB_DATABASE . "' 
					and table_name = '" . $table . "'
					and column_name <> 'image'
					and column_name <> 'link'
			")->rows;
			$langExists = false;
			foreach($columns as &$column)
			{
				$column = array_change_key_case($column);
				if ($column['column_name'] == 'language_id')
					$langExists = true;
			}			
			
			if ($langExists)
			{
				foreach($columns as $column)
				{
					$isText = ($column['data_type'] == 'varchar') || ($column['data_type'] == 'text');
					if ($isText)
					{
						$columnName = $column['column_name'];
						$sql = "
							select 1
							from {$table} _
							where _.{$columnName} like '% # %'
							LIMIT 1";
						$rows = $this->db->query($sql)->rows;
						if (count($rows) == 1)
						{
							$result .= "Table {$table} - {$columnName}<br>";							
						}
					}
				}		
			}		
		}
		return $result;
	}

