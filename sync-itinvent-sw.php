<?php
	// Retrieve information from IT Invent database
	/**
		\file
		\brief Синхронизация БД IT Invent (Программное обеспечение).
		
		Из ИТ Инвент загружается информация о путях и файлах относящихся к ПО.
		
		Загруженная информация сравнивается с файлами присутствующими на ПК (данные из SCCM).
		
		Файлам соостветствующим маске загруженной из ИТ Инвент ставится отметка присутствия
		в ИТ Инвент.
	*/

	/*

		List all fields:

		SELECT [FIELD_NO]
			  ,[ITEM_NO]
			  ,[FIELD_NAME]
			  ,[FIELD_TYPE]
			  ,[FIELD_DESCR]
			  ,[SORT_NO]
			  ,[LIST_VALUES]
			  ,[REQUIRED]
			  ,[TAB_NO]
			  ,[SQL_QUERY]
			  ,[USE_ON_TABLE]
		FROM [ITINVENT].[dbo].[FIELDS]

		List all statuses:
		
		SELECT
			[STATUS_NO]
			,[DESCR]
			,[ADDINFO]
			,[IMAGE_DATA]
		FROM [ITINVENT].[dbo].[STATUS]
  
  */
	
	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-itinvent-sw:\n";

	$params = array(
		'Database' =>				ITINVENT_DB_NAME,
		'UID' =>					ITINVENT_DB_USER,
		'PWD' =>					ITINVENT_DB_PASSWD,
		'ReturnDatesAsStrings' =>	true
	);

	$conn = sqlsrv_connect(ITINVENT_DB_HOST, $params);
	if($conn === false)
	{
		print_r(sqlsrv_errors());
		exit;
	}

	// before sync remove marks: 0x0010 - Exist in IT Invent
	$db->put(rpv("UPDATE @files SET `flags` = (`flags` & ~{%FF_ALLOWED}) WHERE `flags` & {%FF_ALLOWED}"));

	$db->select_ex($res, rpv("SELECT f.`id`, f.`filename`, f.`path` FROM @files AS f"));
	
	$invent_result = sqlsrv_query($conn, "
		SELECT
			m.[MODEL_NO]
			,t.[TYPE_NAME] AS sw_name
			,m.[MODEL_NAME] AS sw_ver
			,m1.[FIELD_VALUE] AS exe1
			,m2.[FIELD_VALUE] AS exe2
			,m3.[FIELD_VALUE] AS exe3
			,m4.[FIELD_VALUE] AS exe4
			,m5.[FIELD_VALUE] AS exe5
			,m6.[FIELD_VALUE] AS path1
			,m7.[FIELD_VALUE] AS path2
			,m8.[FIELD_VALUE] AS path3
			,m9.[FIELD_VALUE] AS path4
			,m10.[FIELD_VALUE] AS path5
		INTO #tmptable
		FROM [CI_TYPES] AS t WITH (NOLOCK)
		LEFT JOIN [CI_MODELS] AS m WITH (NOLOCK) ON m.CI_TYPE = 2 AND m.TYPE_NO = t.TYPE_NO
		LEFT JOIN [FIELDS_VALUES] AS m1 WITH (NOLOCK) ON m1.[ITEM_ID] = m.[MODEL_NO] AND m1.[FIELD_NO] = 168 AND m1.[ITEM_NO] = 111
		LEFT JOIN [FIELDS_VALUES] AS m2 WITH (NOLOCK) ON m2.[ITEM_ID] = m.[MODEL_NO] AND m2.[FIELD_NO] = 178 AND m2.[ITEM_NO] = 111
		LEFT JOIN [FIELDS_VALUES] AS m3 WITH (NOLOCK) ON m3.[ITEM_ID] = m.[MODEL_NO] AND m3.[FIELD_NO] = 179 AND m3.[ITEM_NO] = 111
		LEFT JOIN [FIELDS_VALUES] AS m4 WITH (NOLOCK) ON m4.[ITEM_ID] = m.[MODEL_NO] AND m4.[FIELD_NO] = 180 AND m4.[ITEM_NO] = 111
		LEFT JOIN [FIELDS_VALUES] AS m5 WITH (NOLOCK) ON m5.[ITEM_ID] = m.[MODEL_NO] AND m5.[FIELD_NO] = 181 AND m5.[ITEM_NO] = 111
		LEFT JOIN [FIELDS_VALUES] AS m6 WITH (NOLOCK) ON m6.[ITEM_ID] = m.[MODEL_NO] AND m6.[FIELD_NO] = 182 AND m6.[ITEM_NO] = 111
		LEFT JOIN [FIELDS_VALUES] AS m7 WITH (NOLOCK) ON m7.[ITEM_ID] = m.[MODEL_NO] AND m7.[FIELD_NO] = 183 AND m7.[ITEM_NO] = 111
		LEFT JOIN [FIELDS_VALUES] AS m8 WITH (NOLOCK) ON m8.[ITEM_ID] = m.[MODEL_NO] AND m8.[FIELD_NO] = 184 AND m8.[ITEM_NO] = 111
		LEFT JOIN [FIELDS_VALUES] AS m9 WITH (NOLOCK) ON m9.[ITEM_ID] = m.[MODEL_NO] AND m9.[FIELD_NO] = 185 AND m9.[ITEM_NO] = 111
		LEFT JOIN [FIELDS_VALUES] AS m10 WITH (NOLOCK) ON m10.[ITEM_ID] = m.[MODEL_NO] AND m10.[FIELD_NO] = 186 AND m10.[ITEM_NO] = 111
		WHERE
			t.[CI_TYPE] = 2
			AND (
				m1.[FIELD_VALUE] IS NOT NULL
				OR m2.[FIELD_VALUE] IS NOT NULL
				OR m3.[FIELD_VALUE] IS NOT NULL
				OR m4.[FIELD_VALUE] IS NOT NULL
				OR m5.[FIELD_VALUE] IS NOT NULL
				OR m6.[FIELD_VALUE] IS NOT NULL
				OR m7.[FIELD_VALUE] IS NOT NULL
				OR m8.[FIELD_VALUE] IS NOT NULL
				OR m9.[FIELD_VALUE] IS NOT NULL
				OR m10.[FIELD_VALUE] IS NOT NULL
			)
	");
	
	if($invent_result !== FALSE)
	{
		sqlsrv_free_stmt($invent_result);

		$invent_result = sqlsrv_query($conn, 'SELECT * FROM #tmptable');

		$i = 0;
		while($row_patterns = sqlsrv_fetch_array($invent_result, SQLSRV_FETCH_ASSOC))
		{
			//echo 'NAME: '.$row_patterns['sw_name'].' VERSION: '.$row_patterns['sw_ver']."\r\n";

			$file_patterns = array();
			if(!empty($row_patterns['exe1'])) $file_patterns = array_merge($file_patterns, explode("\r\n", $row_patterns['exe1']));
			if(!empty($row_patterns['exe2'])) $file_patterns = array_merge($file_patterns, explode("\r\n", $row_patterns['exe2']));
			if(!empty($row_patterns['exe3'])) $file_patterns = array_merge($file_patterns, explode("\r\n", $row_patterns['exe3']));
			if(!empty($row_patterns['exe4'])) $file_patterns = array_merge($file_patterns, explode("\r\n", $row_patterns['exe4']));
			if(!empty($row_patterns['exe5'])) $file_patterns = array_merge($file_patterns, explode("\r\n", $row_patterns['exe5']));

			$path_patterns = array();
			if(!empty($row_patterns['path1'])) $path_patterns = array_merge($path_patterns, explode("\r\n", $row_patterns['path1']));
			if(!empty($row_patterns['path2'])) $path_patterns = array_merge($path_patterns, explode("\r\n", $row_patterns['path2']));
			if(!empty($row_patterns['path3'])) $path_patterns = array_merge($path_patterns, explode("\r\n", $row_patterns['path3']));
			if(!empty($row_patterns['path4'])) $path_patterns = array_merge($path_patterns, explode("\r\n", $row_patterns['path4']));
			if(!empty($row_patterns['path5'])) $path_patterns = array_merge($path_patterns, explode("\r\n", $row_patterns['path5']));
			
			//print_r($file_patterns);
			//print_r($path_patterns);
			if($res)
			{
				foreach($res as &$row)
				{
					foreach($path_patterns as &$path_pattern)
					{
						$i++;
						if(fnmatch($path_pattern, $row[2], FNM_NOESCAPE | FNM_CASEFOLD))
						{
							//echo 'Pattern: '.$path_pattern."\n".'Match  : '.$row[2]."\n";
								 
							foreach($file_patterns as &$file_pattern)
							{
								$i++;
								if(($file_pattern === '*') || fnmatch($file_pattern, $row[1], FNM_NOESCAPE | FNM_CASEFOLD))
								{
									//echo 'Pattern  : '.$path_pattern."\n".'Match    : '.$row[2]."\n".'  Pattern: '.$file_pattern."\n".'  Match  : '.$row[1]."\n";
									//log_file('Pattern: '.$path_pattern.'; Match: '.$row[2].'; Pattern: '.$file_pattern.'; Match: '.$row[1]);
										
									$db->put(rpv("UPDATE @files SET `flags` = (`flags` | {%FF_ALLOWED}) WHERE `id` = # LIMIT 1", $row[0]));
									
									break;
								}
							}
							break;
						}
					}
				}
			}
		}

		echo 'Count: '.$i."\r\n";

		sqlsrv_free_stmt($invent_result);
	}
	
	$invent_result = sqlsrv_query($conn, 'DROP TABLE #tmptable');

	if($invent_result !== FALSE)
	{
		sqlsrv_free_stmt($invent_result);
	}

	sqlsrv_close($conn);
