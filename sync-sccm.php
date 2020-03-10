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
			j1.LastHealthEvaluation
		FROM [".SCCM_DB_NAME."].[dbo].[System_DISC] AS m  
		LEFT JOIN [".SCCM_DB_NAME."].[dbo].[CH_ClientSummary] AS j1 ON m.ItemKey = j1.MachineID
	");


	$i = 0;
	while($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC))
	{
		//echo $row['DeviceName'].", ".$row['LastSync'].", ".$row['EncryptionStatus']."\r\n";

		$lastsync = '0000-00-00 00:00:00';
		$max_date = strtotime($lastsync);

		if(!empty($row['LastDDR']))
		{
			$tmp_date = strtotime($row['LastDDR']);
			if($tmp_date > $max_date)
			{
				$max_date = $tmp_date;
				$lastsync = $row['LastDDR'];
			}
		}

		if(!empty($row['LastPolicyRequest']))
		{
			$tmp_date = strtotime($row['LastPolicyRequest']);
			if($tmp_date > $max_date)
			{
				$max_date = $tmp_date;
				$lastsync = $row['LastPolicyRequest'];
			}
		}

		if(!empty($row['LastOnline']))
		{
			$tmp_date = strtotime($row['LastOnline']);
			if($tmp_date > $max_date)
			{
				$max_date = $tmp_date;
				$lastsync = $row['LastOnline'];
			}
		}

		if(!empty($row['LastSW']))
		{
			$tmp_date = strtotime($row['LastSW']);
			if($tmp_date > $max_date)
			{
				$max_date = $tmp_date;
				$lastsync = $row['LastSW'];
			}
		}

		if(!empty($row['LastHealthEvaluation']))
		{
			$tmp_date = strtotime($row['LastHealthEvaluation']);
			if($tmp_date > $max_date)
			{
				$max_date = $tmp_date;
				$lastsync = $row['LastHealthEvaluation'];
			}
		}

		$db->put(rpv("
			INSERT INTO @computers (`name`, `sccm_lastsync`, `flags`)
			VALUES (!, !, 0x0080)
			ON DUPLICATE KEY UPDATE `sccm_lastsync` = !, `flags` = ((`flags` & ~0x0008) | 0x0080)
			",
			$row['DeviceName'],
			$lastsync,
			$lastsync)
		);
		$i++;
	}

	echo 'Count: '.$i."\r\n";

	sqlsrv_free_stmt($result);
	sqlsrv_close($conn);
