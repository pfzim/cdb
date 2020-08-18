<?php
	// Retrieve information from IT Invent database
	/**
		\file
		\brief Синхронизация БД IT Invent.
		Загрузка информации о MAC адресах и серийных номерах
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
  
  */
	
	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-itinvent:\n";

	$active_statuses = array(
		1,    // Работает
		21    // Выдан пользователю для удаленной работы
	);

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

	$invent_result = sqlsrv_query($conn, "
		SELECT 
			[ID]
			,[INV_NO]
			,[STATUS_NO]
			,[SERIAL_NO]
			,[MAC_ADDRESS] AS mac0
			,m1.[FIELD_VALUE] AS mac1
			,m2.[FIELD_VALUE] AS mac2
			,m3.[FIELD_VALUE] AS mac3
			,m4.[FIELD_VALUE] AS mac4
			,m5.[FIELD_VALUE] AS mac5
			,m6.[FIELD_VALUE] AS mac6
		FROM [ITEMS] AS item WITH (NOLOCK)
		LEFT JOIN [FIELDS_VALUES] AS m1 WITH (NOLOCK) ON m1.[ITEM_ID] = item.[ID] AND m1.[FIELD_NO] = 106 AND m1.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m2 WITH (NOLOCK) ON m2.[ITEM_ID] = item.[ID] AND m2.[FIELD_NO] = 107 AND m2.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m3 WITH (NOLOCK) ON m3.[ITEM_ID] = item.[ID] AND m3.[FIELD_NO] = 133 AND m3.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m4 WITH (NOLOCK) ON m4.[ITEM_ID] = item.[ID] AND m4.[FIELD_NO] = 149 AND m4.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m5 WITH (NOLOCK) ON m5.[ITEM_ID] = item.[ID] AND m5.[FIELD_NO] = 150 AND m5.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m6 WITH (NOLOCK) ON m6.[ITEM_ID] = item.[ID] AND m6.[FIELD_NO] = 94 AND m6.[ITEM_NO] = 1
		WHERE
			[CI_TYPE] = 1
			AND (
				[SERIAL_NO] IS NOT NULL
				OR m1.[FIELD_VALUE] IS NOT NULL
				OR m2.[FIELD_VALUE] IS NOT NULL
				OR m3.[FIELD_VALUE] IS NOT NULL
				OR m4.[FIELD_VALUE] IS NOT NULL
				OR m5.[FIELD_VALUE] IS NOT NULL
				OR m6.[FIELD_VALUE] IS NOT NULL
			)
	");
/*
	$invent_result = sqlsrv_query($conn, "
		SELECT *
		FROM (
			SELECT 
				[ID]
				,[INV_NO]
				,[STATUS_NO]
				,[SERIAL_NO]
				,[MAC_ADDRESS] AS mac0
				,(SELECT [FIELD_VALUE] FROM [FIELDS_VALUES] AS m1 WITH (NOLOCK) WHERE m1.[ITEM_ID] = item.[ID] AND m1.[FIELD_NO] = 106 AND m1.[ITEM_NO] = 1) AS mac1
				,(SELECT [FIELD_VALUE] FROM [FIELDS_VALUES] AS m2 WITH (NOLOCK) WHERE m2.[ITEM_ID] = item.[ID] AND m2.[FIELD_NO] = 107 AND m2.[ITEM_NO] = 1) AS mac2
				,(SELECT [FIELD_VALUE] FROM [FIELDS_VALUES] AS m3 WITH (NOLOCK) WHERE m3.[ITEM_ID] = item.[ID] AND m3.[FIELD_NO] = 133 AND m3.[ITEM_NO] = 1) AS mac3
				,(SELECT [FIELD_VALUE] FROM [FIELDS_VALUES] AS m4 WITH (NOLOCK) WHERE m4.[ITEM_ID] = item.[ID] AND m4.[FIELD_NO] = 149 AND m4.[ITEM_NO] = 1) AS mac4
				,(SELECT [FIELD_VALUE] FROM [FIELDS_VALUES] AS m5 WITH (NOLOCK) WHERE m5.[ITEM_ID] = item.[ID] AND m5.[FIELD_NO] = 150 AND m5.[ITEM_NO] = 1) AS mac5
				,(SELECT [FIELD_VALUE] FROM [FIELDS_VALUES] AS m6 WITH (NOLOCK) WHERE m6.[ITEM_ID] = item.[ID] AND m6.[FIELD_NO] = 94 AND m6.[ITEM_NO] = 1) AS mac6
			FROM [ITEMS] AS item WITH (NOLOCK)
			WHERE
				[CI_TYPE] = 1
		) AS t
		WHERE
			[SERIAL_NO] IS NOT NULL
			OR mac1 IS NOT NULL
			OR mac2 IS NOT NULL
			OR mac3 IS NOT NULL
			OR mac4 IS NOT NULL
			OR mac5 IS NOT NULL
			OR mac6 IS NOT NULL
	");
*/
	// before sync remove mark: 0x0010 - Exist in IT Invent, 0x0040 - Active
	$db->put(rpv("UPDATE @mac SET `flags` = (`flags` & ~(0x0010 | 0x0040)) WHERE `flags` & (0x0010 | 0x0040)"));

	$i = 0;
	while($row = sqlsrv_fetch_array($invent_result, SQLSRV_FETCH_ASSOC))
	{
		// Load SN
		$mac = strtoupper(preg_replace('/[-:;., ]/i', '', $row['SERIAL_NO']));
		if(!empty($mac))
		{
			$row_id = 0;
			if(!$db->select_ex($result, rpv("SELECT m.`id` FROM @mac AS m WHERE m.`mac` = ! AND (`flags` & 0x0080) = 0x0080 LIMIT 1", $mac)))
			{
				if($db->put(rpv("INSERT INTO @mac (`mac`, `inv_no`, `flags`) VALUES (!, !, #)",
					$mac,
					$row['INV_NO'],
					0x0010 | 0x0080 | ((in_array(intval($row['STATUS_NO']), $active_statuses)) ? 0x0040 : 0x0000)
				)))
				{
					$row_id = $db->last_id();
				}
			}
			else
			{
				$row_id = $result[0][0];
				$db->put(rpv("UPDATE @mac SET `inv_no` = !, `flags` = (`flags` | #) WHERE `id` = # LIMIT 1",
					$row['INV_NO'],
					0x0010 | ((in_array(intval($row['STATUS_NO']), $active_statuses)) ? 0x0040 : 0x0000),
					$row_id
				));
			}
			$i++;
		}

		// Load MACs
		for($k = 1; $k <= 6; $k++)    // mac* fields count
		{
			$mac = strtolower(preg_replace('/[^0-9a-f]/i', '', $row['mac'.$k]));
			if(!empty($mac) && strlen($mac) == 12)
			{
				$row_id = 0;
				if(!$db->select_ex($result, rpv("SELECT m.`id` FROM @mac AS m WHERE m.`mac` = ! AND (`flags` & 0x0080) = 0 LIMIT 1", $mac)))
				{
					if($db->put(rpv("INSERT INTO @mac (`mac`, `inv_no`, `flags`) VALUES (!, !, #)",
						$mac,
						$row['INV_NO'],
						0x0010 | ((in_array(intval($row['STATUS_NO']), $active_statuses)) ? 0x0040 : 0x0000)
					)))
					{
						$row_id = $db->last_id();
					}
				}
				else
				{
					$row_id = $result[0][0];
					$db->put(rpv("UPDATE @mac SET `inv_no` = !, `flags` = (`flags` | #) WHERE `id` = # LIMIT 1",
						$row['INV_NO'],
						0x0010 | ((in_array(intval($row['STATUS_NO']), $active_statuses)) ? 0x0040 : 0x0000),
						$row_id
					));
				}
				$i++;
			}
		}
	}

	echo 'Count: '.$i."\r\n";

	sqlsrv_free_stmt($invent_result);
	sqlsrv_close($conn);
