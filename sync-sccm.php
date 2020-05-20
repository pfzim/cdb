<?php
	// Retrieve information from SCCM database

	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-sccm:\n";

	$params = array(
		'Database' =>				SCCM_DB_NAME,
		'UID' =>					SCCM_DB_USER,
		'PWD' =>					SCCM_DB_PASSWD,
		'ReturnDatesAsStrings' =>	true
	);

	$conn = sqlsrv_connect(SCCM_DB_HOST, $params);
	if($conn === false)
	{
		print_r(sqlsrv_errors());
		exit;
	}

	$result = sqlsrv_query($conn, "
		SELECT
			m.ItemKey AS ResourceID, 
			m.Netbios_Name0 AS DeviceName, 
			j1.LastDDR, 
			j1.LastPolicyRequest,
			j1.LastOnline,
			j1.LastSW,
			j1.LastHealthEvaluation,
			j1.LastStatusMessage,
			j1.LastHW
		FROM [".SCCM_DB_NAME."].[dbo].[System_DISC] AS m  
		LEFT JOIN [".SCCM_DB_NAME."].[dbo].[CH_ClientSummary] AS j1 ON m.ItemKey = j1.MachineID
		WHERE ISNULL(m.Obsolete0, 0) <> 1 AND ISNULL(m.Decommissioned0, 0) <> 1 AND m.Client0 = 1
	");

	$columns = array('LastDDR', 'LastPolicyRequest', 'LastSW', 'LastHealthEvaluation', 'LastStatusMessage', 'LastHW');

	$i = 0;
	while($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC))
	{
		//echo $row['DeviceName'].", ".$row['LastSync'].", ".$row['EncryptionStatus']."\r\n";

		$lastsync = '0000-00-00 00:00:00';
		$max_date = strtotime($lastsync);

		foreach($columns as &$col)
		{
			if(!empty($row[$col]))
			{
				$tmp_date = strtotime($row[$col]);
				if($tmp_date > $max_date)
				{
					$max_date = $tmp_date;
					$lastsync = $row[$col];
				}
			}
		}

		$row_id = 0;
		if(!$db->select_ex($res, rpv("SELECT m.`id` FROM @computers AS m WHERE m.`name` = ! LIMIT 1", $row['DeviceName'])))
		{
			if($db->put(rpv("INSERT INTO @computers (`name`, `sccm_lastsync`, `flags`) VALUES (!, !, 0x0080)",
				$row['DeviceName'], 
				$lastsync
			)))
			{
				$row_id = $db->last_id();
			}
		}
		else
		{
			$row_id = $res[0][0];
			$db->put(rpv("UPDATE @computers SET `sccm_lastsync` = !, `flags` = ((`flags` & ~0x0008) | 0x0080) WHERE `id` = # LIMIT 1",
				$lastsync, 
				$row_id
			));
		}
		$i++;
	}

	echo 'Count: '.$i."\r\n";

	sqlsrv_free_stmt($result);
	sqlsrv_close($conn);