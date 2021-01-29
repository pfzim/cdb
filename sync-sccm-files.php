<?php
	// Retrieve information from SCCM database

	/**
		\file
		\brief Синхронизация с БД SCCM (файлы).
		
		Загрузка данных из БД SCCM о присутсутвующих на ПК .exe файлах
		
		Флаг Deleted сбрасывается, если дата сканирования новее присутствующей в БД.
		
		Флаг Deleted устанавливается у всех файлов просканированных более 30 дней назад.
	*/

	/*
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

	echo "\nsync-sccm-files:\n";

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

	// Load files inventory data
	
	$result = sqlsrv_query($conn, "
		SELECT
			m.ItemKey AS ResourceID
			,m.Netbios_Name0 AS DeviceName
			,sf.FileName
			,fp.FilePath
			,si.ModifiedDate
			-- ,sp.ProductName
			-- ,sp.CompanyName
			-- COUNT(*)
		FROM [dbo].[System_DISC] AS m
		LEFT JOIN SoftwareInventory as si on si.ClientId = m.ItemKey
		LEFT JOIN SoftwareFile as sf on sf.FileId = si.FileId
		LEFT JOIN SoftwareFilePath as fp on si.FilePathId = fp.FilePathId
		WHERE
			ISNULL(m.Obsolete0, 0) <> 1
			AND ISNULL(m.Decommissioned0, 0) <> 1
			AND m.Client0 = 1
			-- AND m.Netbios_Name0 = '7701-W0034'
			AND sf.FileName LIKE '%.exe'
		ORDER BY sf.FileName, fp.FilePath, m.Netbios_Name0
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

			if(!$db->select_ex($res, rpv("SELECT c.`id` FROM @computers AS c WHERE c.`name` = ! LIMIT 1", $row['DeviceName'])))
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
			$db->put(rpv("
					INSERT
						INTO @files_inventory (`pid`, `fid`, `scan_date`, `flags`)
						VALUES (#, #, {s2}, 0x0000)
					ON DUPLICATE KEY
						UPDATE
							`scan_date` = IF(`scan_date` < {s2}, {s2}, `scan_date`),          -- Update `scan_date` if newer
							`flags` = IF(`scan_date` < {s2}, (`flags` & ~0x0002), `flags`)    -- Remove flag Deleted if `scan_date` newer
				",
				$device_id,
				$file_id,
				$row['ModifiedDate']
			));
		}
		$i++;
	}

	// Mark as Deleted all files scanned more 30 days ago
	$db->put(rpv("UPDATE @files_inventory SET `flags` = (`flags` | 0x0002) WHERE `scan_date` < DATE_SUB(NOW(), INTERVAL 30 DAY)"));
	
	echo 'Count: '.$i."\r\n";

	sqlsrv_free_stmt($result);

	sqlsrv_close($conn);
