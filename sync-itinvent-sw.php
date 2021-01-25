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
	$db->put(rpv("UPDATE @files SET `flags` = (`flags` & ~0x0010) WHERE `flags` & 0x0010"));

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
		while($row = sqlsrv_fetch_array($invent_result, SQLSRV_FETCH_ASSOC))
		{
			//echo 'NAME: '.$row['sw_name'].' VERSION: '.$row['sw_ver']."\r\n";

			$files = array();
			if(!empty($row['exe1'])) $files = array_merge($files, explode("\r\n", $row['exe1']));
			if(!empty($row['exe2'])) $files = array_merge($files, explode("\r\n", $row['exe2']));
			if(!empty($row['exe3'])) $files = array_merge($files, explode("\r\n", $row['exe3']));
			if(!empty($row['exe4'])) $files = array_merge($files, explode("\r\n", $row['exe4']));
			if(!empty($row['exe5'])) $files = array_merge($files, explode("\r\n", $row['exe5']));

			$paths = array();
			if(!empty($row['path1'])) $paths = array_merge($paths, explode("\r\n", $row['path1']));
			if(!empty($row['path2'])) $paths = array_merge($paths, explode("\r\n", $row['path2']));
			if(!empty($row['path3'])) $paths = array_merge($paths, explode("\r\n", $row['path3']));
			if(!empty($row['path4'])) $paths = array_merge($paths, explode("\r\n", $row['path4']));
			if(!empty($row['path5'])) $paths = array_merge($paths, explode("\r\n", $row['path5']));
			
			print_r($files);
			print_r($paths);
			if($db->select_assoc_ex($res, rpv("SELECT f.`id`, f.`filename`, f.`path` FROM @files AS f")))
			{
				foreach($res as &$row)
				{
					foreach($paths as &$path)
					{
						$i++;
						if(fnmatch($path, $row['path'], FNM_NOESCAPE | FNM_CASEFOLD))
						{
							//echo 'Pattern: '.$path."\n".'Match  : '.$row['path']."\n";
								 
							foreach($files as &$file)
							{
								$i++;
								if(fnmatch($file, $row['filename'], FNM_NOESCAPE | FNM_CASEFOLD))
								{
									//echo 'Pattern  : '.$path."\n".'Match    : '.$row['path']."\n".'  Pattern: '.$file."\n".'  Match  : '.$row['filename']."\n";
										
									$db->put(rpv("UPDATE @files SET `flags` = (`flags` | 0x0010) WHERE `id` = # LIMIT 1", $row['id']));
									
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
