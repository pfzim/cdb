<?php
	// Retrieve information from IT Invent database

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
			,[MAC_ADDRESS] AS mac0
			,m1.[FIELD_VALUE] AS mac1
			,m2.[FIELD_VALUE] AS mac2
			,m3.[FIELD_VALUE] AS mac3
		FROM [ITEMS] AS item
		LEFT JOIN [FIELDS_VALUES] AS m1 ON m1.[ITEM_ID] = item.[ID] AND m1.[FIELD_NO] = 106 AND m1.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m2 ON m2.[ITEM_ID] = item.[ID] AND m2.[FIELD_NO] = 107 AND m2.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m3 ON m3.[ITEM_ID] = item.[ID] AND m3.[FIELD_NO] = 133 AND m3.[ITEM_NO] = 1
		WHERE 
			m1.[FIELD_VALUE] IS NOT NULL
			OR m2.[FIELD_VALUE] IS NOT NULL
			OR m3.[FIELD_VALUE] IS NOT NULL
	");

	// before sync remove mark: 0x0010 - Exist in IT Invent, 0x0040 - Active
	$db->put(rpv("UPDATE @mac SET `flags` = (`flags` & ~(0x0010 | 0x0040)) WHERE `flags` & (0x0010 | 0x0040)"));

	$i = 0;
	while($row = sqlsrv_fetch_array($invent_result, SQLSRV_FETCH_ASSOC))
	{
		for($k = 1; $k <= 3; $k++)
		{
			$mac = strtolower(str_replace(array(':', '.', ' '), '', $row['mac'.$k]));
			if(!empty($mac))
			{
				$row_id = 0;
				if(!$db->select_ex($result, rpv("SELECT m.`id` FROM @mac AS m WHERE m.`mac` = ! LIMIT 1", $mac)))
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
						0x0010 | ((in_array(intval($row['STATUS_NO']), $active_statuses)) ? 0x0040 : 0x0000)
					));
				}
				$i++;
			}
		}
	}

	echo 'Count: '.$i."\r\n";

	sqlsrv_free_stmt($invent_result);
	sqlsrv_close($conn);
