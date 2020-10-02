<?php
	// Retrieve information from IT Invent database
	/**
		\file
		\brief Синхронизация БД IT Invent.
		Загрузка информации о MAC адресах и серийных номера.
		Добавлена загрузка информации о местоположении оборудования.
		Местоположение состоит из двух значенией: Филиал и Местоположение.
		Местоположение может содержать как номер кабинете или этажа, так и
		адреса магазинов. Если поле LOC_NO_BUH равно NULL, то Местоположение
		не загружается.
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

	// before sync remove marks: 0x0010 - Exist in IT Invent, 0x0040 - Active
	$db->put(rpv("UPDATE @mac SET `flags` = (`flags` & ~(0x0010 | 0x0040)) WHERE `flags` & (0x0010 | 0x0040)"));

	$invent_result = sqlsrv_query($conn, "
		SELECT
			[ID]
			,[INV_NO]
			,item.[BRANCH_NO]
			,[LOC_NO] = 
				CASE
					WHEN loc.[LOC_NO_BUH] IS NULL THEN 0
					ELSE item.[LOC_NO]
				END
			-- ,brn.[BRANCH_NAME]
			-- ,loc.[DESCR]
			,[STATUS_NO]
			,[SERIAL_NO]
			,[MAC_ADDRESS] AS mac0
			,m1.[FIELD_VALUE] AS mac1
			,m2.[FIELD_VALUE] AS mac2
			,m3.[FIELD_VALUE] AS mac3
			,m4.[FIELD_VALUE] AS mac4
			,m5.[FIELD_VALUE] AS mac5
			,m6.[FIELD_VALUE] AS mac6
			,m7.[FIELD_VALUE] AS mac7
		INTO #tmptable
		FROM [ITEMS] AS item WITH (NOLOCK)
		LEFT JOIN [FIELDS_VALUES] AS m1 WITH (NOLOCK) ON m1.[ITEM_ID] = item.[ID] AND m1.[FIELD_NO] = 106 AND m1.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m2 WITH (NOLOCK) ON m2.[ITEM_ID] = item.[ID] AND m2.[FIELD_NO] = 107 AND m2.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m3 WITH (NOLOCK) ON m3.[ITEM_ID] = item.[ID] AND m3.[FIELD_NO] = 133 AND m3.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m4 WITH (NOLOCK) ON m4.[ITEM_ID] = item.[ID] AND m4.[FIELD_NO] = 149 AND m4.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m5 WITH (NOLOCK) ON m5.[ITEM_ID] = item.[ID] AND m5.[FIELD_NO] = 150 AND m5.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m6 WITH (NOLOCK) ON m6.[ITEM_ID] = item.[ID] AND m6.[FIELD_NO] = 94 AND m6.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m7 WITH (NOLOCK) ON m7.[ITEM_ID] = item.[ID] AND m7.[FIELD_NO] = 163 AND m7.[ITEM_NO] = 1
		-- LEFT JOIN [BRANCHES] AS brn WITH (NOLOCK) ON brn.[BRANCH_NO] = item.[BRANCH_NO]
		LEFT JOIN [LOCATIONS] AS loc WITH (NOLOCK) ON loc.[LOC_NO] = item.[LOC_NO]
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
				OR m7.[FIELD_VALUE] IS NOT NULL
			)
	");

	if($invent_result !== FALSE)
	{
		sqlsrv_free_stmt($invent_result);

		$invent_result = sqlsrv_query($conn, 'SELECT * FROM #tmptable');

		$i = 0;
		while($row = sqlsrv_fetch_array($invent_result, SQLSRV_FETCH_ASSOC))
		{
			// Load SN
			$mac = strtoupper(preg_replace('/[-:;., ]/i', '', $row['SERIAL_NO']));
			if(!empty($mac))
			{
				$row_id = 0;
				if(!$db->select_ex($result, rpv("SELECT m.`id`, m.`inv_no`, m.`flags` FROM @mac AS m WHERE m.`mac` = ! AND (`flags` & 0x0080) = 0x0080 LIMIT 1", $mac)))
				{
					if($db->put(rpv("INSERT INTO @mac (`mac`, `inv_no`, `branch_no`, `loc_no`, `flags`) VALUES (!, !, #, #, #)",
						$mac,
						$row['INV_NO'],
						$row['BRANCH_NO'],
						$row['LOC_NO'],
						0x0010 | 0x0080 | ((in_array(intval($row['STATUS_NO']), $active_statuses)) ? 0x0040 : 0x0000)
					)))
					{
						$row_id = $db->last_id();
					}
				}
				else
				{
					$row_id = $result[0][0];

					if(intval($result[0][2]) & 0x0010 && $mac !== 'N/A' && $mac !== 'N\A' && $mac !== 'NA')    // Exist in IT Invent?
					{
						echo 'Possible duplicate: '.$row_id.' INV_ON: '.$row['INV_NO'].' and '.$result[0][1].', SN: '.$mac.', STATUS_NO: '.intval($row['STATUS_NO'])."\r\n";
					}

					$db->put(rpv("UPDATE @mac SET `inv_no` = !, `branch_no` = #, `loc_no` = #, `flags` = (`flags` | #) WHERE `id` = # LIMIT 1",
						$row['INV_NO'],
						$row['BRANCH_NO'],
						$row['LOC_NO'],
						0x0010 | ((in_array(intval($row['STATUS_NO']), $active_statuses)) ? 0x0040 : 0x0000),
						$row_id
					));
				}
				$i++;
			}

			// Load MACs
			for($k = 1; $k <= 7; $k++)    // mac* fields count
			{
				$mac = strtolower(preg_replace('/[^0-9a-f]/i', '', $row['mac'.$k]));
				if(!empty($mac) && strlen($mac) == 12)
				{
					$row_id = 0;
					if(!$db->select_ex($result, rpv("SELECT m.`id`, m.`inv_no`, m.`flags` FROM @mac AS m WHERE m.`mac` = ! AND (`flags` & 0x0080) = 0 LIMIT 1", $mac)))
					{
						if($db->put(rpv("INSERT INTO @mac (`mac`, `inv_no`, `branch_no`, `loc_no`, `flags`) VALUES (!, !, #, #, #)",
							$mac,
							$row['INV_NO'],
							$row['BRANCH_NO'],
							$row['LOC_NO'],
							0x0010 | ((in_array(intval($row['STATUS_NO']), $active_statuses)) ? 0x0040 : 0x0000)
						)))
						{
							$row_id = $db->last_id();
						}
					}
					else
					{
						$row_id = $result[0][0];

						if(intval($result[0][2]) & 0x0010)    // Exist in IT Invent?
						{
							echo 'Possible duplicate: '.$row_id.' INV_ON: '.$row['INV_NO'].' and '.$result[0][1].', MAC: '.$mac.', STATUS_NO: '.intval($row['STATUS_NO'])."\r\n";
						}

						$db->put(rpv("UPDATE @mac SET `inv_no` = !, `branch_no` = #, `loc_no` = #, `flags` = (`flags` | #) WHERE `id` = # LIMIT 1",
							$row['INV_NO'],
							$row['BRANCH_NO'],
							$row['LOC_NO'],
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
	}
	
	$invent_result = sqlsrv_query($conn, 'DROP TABLE #tmptable');

	if($invent_result !== FALSE)
	{
		sqlsrv_free_stmt($invent_result);
	}

	sqlsrv_close($conn);
