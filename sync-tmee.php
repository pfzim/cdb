<?php
	// Retrieve information from TMEE database

	/**
		\file
		\brief Синхронизация БД TMEE.
		Загрузка информации о статусе шифрования ноутбука
	*/

	/*
		TMEE status:

			1 - Not Encrypted
			2 - Encrypted
			3 - Encrypting
			4 - Decrypting
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-tmee:\n";

	$params = array(
		'Database' =>				TMEE_DB_NAME,
		'UID' =>					TMEE_DB_USER,
		'PWD' =>					TMEE_DB_PASSWD,
		'ReturnDatesAsStrings' =>	true
	);

	$conn = sqlsrv_connect(TMEE_DB_HOST, $params);
	if($conn === false)
	{
		print_r(sqlsrv_errors());
		exit;
	}

	$result = sqlsrv_query($conn, "
		SELECT
			[DeviceName],
			[LastSync],
			[EncryptionStatus]
		FROM [".TMEE_DB_NAME."].[dbo].[Device]
		WHERE IsDeleted = 0
		ORDER BY [LastSync]
	");


	$i = 0;
	while($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC))
	{
		//echo $row['DeviceName'].", ".$row['LastSync'].", ".$row['EncryptionStatus']."\r\n";

		if(!empty($row['LastSync']))
		{
			$lastsync = $row['LastSync'];
		}
		else
		{
			$lastsync = '0000-00-00 00:00:00';
		}

		$row_id = 0;
		if(!$db->select_ex($res, rpv("SELECT m.`id` FROM @computers AS m WHERE m.`name` = ! LIMIT 1", $row['DeviceName'])))
		{
			if($db->put(rpv("INSERT INTO @computers (`name`, `ee_lastsync`, `ee_encryptionstatus`, `flags`) VALUES (!, !, #, 0x0040)",
				$row['DeviceName'], 
				$lastsync, 
				$row['EncryptionStatus']
			)))
			{
				$row_id = $db->last_id();
			}
		}
		else
		{
			$row_id = $res[0][0];
			$db->put(rpv("UPDATE @computers SET `ee_lastsync` = !, `ee_encryptionstatus` = #, `flags` = ((`flags` & ~0x0008) | 0x0040) WHERE `id` = # LIMIT 1",
				$lastsync, 
				$row['EncryptionStatus'],
				$row_id
			));
		}
		$i++;
	}

	echo 'Count: '.$i."\r\n";

	sqlsrv_free_stmt($result);
	sqlsrv_close($conn);
