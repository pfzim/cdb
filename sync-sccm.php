<?php
	// Retrieve information from SCCM database

	/**
		\file
		\brief Синхронизация с БД SCCM.
		
		Загрузка данных о состоянии агента и информации по соответствию базовому уровню установки обновлений на ПК
	*/

	/*
		Определение идентификатора SCCM_CI_ID
		
		SELECT
			TOP 1000
			v_ConfigurationItems.CI_ID,
			v_LocalizedCIProperties.DisplayName
		FROM v_ConfigurationItems 
		INNER JOIN v_LocalizedCIProperties
		ON v_ConfigurationItems.CI_ID = v_LocalizedCIProperties.CI_ID 
		--AND  (v_LocalizedCIProperties.TopicType = 401) 
		WHERE
			CIType_ID = 3
			AND v_LocalizedCIProperties.DisplayName = 'CI - Check - PS - InstallHotFix'


		Инвентаризация файлов

		SELECT
			m.ItemKey AS ResourceID
			,m.Netbios_Name0 AS DeviceName
			,sf.FileName
			,fp.FilePath
			,si.ModifiedDate
		FROM [dbo].[System_DISC] AS m
		LEFT JOIN SoftwareInventory as si on si.ClientId = m.ItemKey
		LEFT JOIN SoftwareFile as sf on sf.FileId = si.FileId
		LEFT JOIN SoftwareFilePath as fp on si.FilePathId = fp.FilePathId
		WHERE
			ISNULL(m.Obsolete0, 0) <> 1
			AND ISNULL(m.Decommissioned0, 0) <> 1
			AND m.Client0 = 1
			AND m.Netbios_Name0 = '0001-W0125'
			AND sf.FileName LIKE '%.exe'
		
	*/

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
			j1.LastHW,
			j2.ComplianceState
		FROM [dbo].[System_DISC] AS m  
		LEFT JOIN [dbo].[CH_ClientSummary] AS j1
			ON m.ItemKey = j1.MachineID
		LEFT JOIN [dbo].[vCICurrentComplianceStatus] AS j2
			ON m.ItemKey = j2.ItemKey
			AND CI_ID = '".SCCM_CI_ID."'
			AND CIVersion = ".SCCM_CI_VERSION."
		WHERE ISNULL(m.Obsolete0, 0) <> 1 AND ISNULL(m.Decommissioned0, 0) <> 1 AND m.Client0 = 1
	");

/*
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


		v_R_System.Name0,
		v_R_System.Netbios_Name0,
		[vCICurrentComplianceStatus].ComplianceState
	FROM [CM_M01].[dbo].[vCICurrentComplianceStatus]
	LEFT JOIN v_R_System ON v_R_System.ResourceID = [vCICurrentComplianceStatus].ItemKey
	LEFT JOIN v_StateNames ON [vCICurrentComplianceStatus].ComplianceState = v_StateNames.StateID AND (v_StateNames.TopicType = 401) 
	WHERE CI_ID = '' AND CIVersion = 6



		SELECT
			m.ItemKey AS ResourceID, 
			m.Netbios_Name0 AS DeviceName, 
			j1.LastDDR, 
			j1.LastPolicyRequest,
			j1.LastOnline,
			j1.LastSW,
			j1.LastHealthEvaluation,
			j1.LastStatusMessage,
			j1.LastHW,
			j2.ComplianceState
		FROM [dbo].[System_DISC] AS m  
		LEFT JOIN [dbo].[CH_ClientSummary] AS j1
			ON m.ItemKey = j1.MachineID
		LEFT JOIN [dbo].[vCICurrentComplianceStatus] AS j2
			ON m.ItemKey = j2.ItemKey
			AND CI_ID = ''
			AND CIVersion = 6
		WHERE ISNULL(m.Obsolete0, 0) <> 1 AND ISNULL(m.Decommissioned0, 0) <> 1 AND m.Client0 = 1

*/

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

		if($row_id)
		{
			$state = 0;
			
			if(intval($row['ComplianceState']) == 1)
			{
				$state = 1;
			}

			$db->put(rpv("INSERT INTO @properties_int (`tid`, `pid`, `oid`, `value`) VALUES (1, #, #, #) ON DUPLICATE KEY UPDATE `value` = #",
				$row_id,
				CDB_PROP_BASELINE_COMPLIANCE_HOTFIX,
				$state,
				$state
			));
		}
		$i++;
	}

	echo 'Count: '.$i."\r\n";

	sqlsrv_free_stmt($result);
	
	// Load files inventory data
	
	$result = sqlsrv_query($conn, "
		SELECT
			m.ItemKey AS ResourceID
			,m.Netbios_Name0 AS DeviceName
			,sf.FileName
			,fp.FilePath
			,si.ModifiedDate
		FROM [dbo].[System_DISC] AS m
		LEFT JOIN SoftwareInventory as si on si.ClientId = m.ItemKey
		LEFT JOIN SoftwareFile as sf on sf.FileId = si.FileId
		LEFT JOIN SoftwareFilePath as fp on si.FilePathId = fp.FilePathId
		WHERE
			ISNULL(m.Obsolete0, 0) <> 1
			AND ISNULL(m.Decommissioned0, 0) <> 1
			AND m.Client0 = 1
			AND m.Netbios_Name0 = '7701-W0034'
			AND sf.FileName LIKE '%.exe'
	");

	$i = 0;
	$last_resource_id = 0;
	$last_file_name = NULL;
	$last_path = NULL;
	$device_id = 0;
	$file_id = 0;
	
	while($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC))
	{
		//echo $row['DeviceName'].", ".$row['LastSync'].", ".$row['EncryptionStatus']."\r\n";

		if(intval($row['ResourceID']) != $last_resource_id)
		{
			$device_id = 0;
			$last_resource_id = 0;

			if(!$db->select_ex($res, rpv("SELECT m.`id` FROM @computers AS m WHERE m.`name` = ! LIMIT 1", $row['DeviceName'])))
			{
				if($db->put(rpv("INSERT INTO @computers (`name`, `flags`) VALUES (!, 0x0080)",
					$row['DeviceName']
				)))
				{
					$device_id = $db->last_id();
					$last_resource_id = intval($row['ResourceID']);
				}
			}
			else
			{
				$device_id = $res[0][0];
				$last_resource_id = intval($row['ResourceID']);
			}
		}

		if(($row['FileName'] !== $last_file_name) || ($row['FilePath'] !== $last_path))
		{
			$file_id = 0;
			$last_file_name = NULL;
			$last_path = NULL;

			if(!$db->select_ex($res, rpv("SELECT f.`id` FROM @files AS f WHERE f.`filename` = ! AND f.`path` = ! LIMIT 1", $row['FileName'], $row['FilePath'])))
			{
				if($db->put(rpv(
					"INSERT INTO @files (`filename`, `path`, `flags`) VALUES (!, !, 0x0000)",
					$row['FileName'],
					$row['FilePath']
				)))
				{
					$file_id = $db->last_id();
					$last_file_name = $row['FileName'];
					$last_path = $row['FilePath'];
				}
			}
			else
			{
				$file_id = $res[0][0];
				$last_file_name = $row['FileName'];
				$last_path = $row['FilePath'];
			}
		}

		if($device_id && $file_id)
		{
			$db->put(rpv("INSERT INTO @files_inventory (`pid`, `fid`, `scan_date`, `flags`) VALUES (#, #, {s3}, 0x0000) ON DUPLICATE KEY UPDATE `scan_date` = {s3}",
				$device_id,
				$file_id,
				$row['ModifiedDate']
			));
		}
		$i++;
	}

	echo 'Count: '.$i."\r\n";

	sqlsrv_free_stmt($result);

	sqlsrv_close($conn);
