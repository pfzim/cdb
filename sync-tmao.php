<?php
	// Retrieve information from TMAO database

	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-tmao:\n";

	$servers = array(
		TMAO_01_DB_HOST => array(
			'Database' =>				TMAO_01_DB_NAME,
			'UID' =>					TMAO_01_DB_USER,
			'PWD' =>					TMAO_01_DB_PASSWD,
			'ReturnDatesAsStrings' =>	true
		),
		TMAO_03_DB_HOST => array(
			'Database' =>				TMAO_03_DB_NAME,
			'UID' =>					TMAO_03_DB_USER,
			'PWD' =>					TMAO_03_DB_PASSWD,
			'ReturnDatesAsStrings' =>	true
		)
	);

	foreach($servers as $server => $params)
	{
		$conn = sqlsrv_connect($server, $params);
		if($conn === false)
		{
			print_r(sqlsrv_errors());
			exit;
		}

		$result = sqlsrv_query($conn, "
			SELECT [COMP_NAME]
				,[PTNUPDTIME]
				,[SCRIPT_PTN]
				,[AS_PSTIME]
			FROM [".$params['Database']."].[dbo].[TBL_CLIENT_INFO] WHERE [CLIENTTYPE] = 0
			ORDER BY [SCRIPT_PTN]
		");

		$i = 0;
		while($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC))
		{
			//echo $row['COMP_NAME'].", ".$row['PTNUPDTIME'].", ".$row['SCRIPT_PTN'].", ".$row['AS_PSTIME']."\r\n";
			
			if(!empty($row['PTNUPDTIME']))
			{
				$ptnupdtime = $row['PTNUPDTIME'];
			}
			else
			{
				$ptnupdtime = '0000-00-00 00:00:00';
			}
			
			if(!empty($row['AS_PSTIME']))
			{
				$as_pstime = $row['AS_PSTIME'];
			}
			else
			{
				$as_pstime = '0000-00-00 00:00:00';
			}
			
			$db->put(rpv("INSERT INTO @computers (`name`, `ao_ptnupdtime`, `ao_script_ptn`, `ao_as_pstime`, `flags`) VALUES (!, !, #, !, 0x0020) ON DUPLICATE KEY UPDATE `ao_ptnupdtime` = !, `ao_script_ptn` = #, `ao_as_pstime` = !, `flags` = ((`flags` & ~0x0008) | 0x0020)", $row['COMP_NAME'], $ptnupdtime, $row['SCRIPT_PTN'], $as_pstime, $ptnupdtime, $row['SCRIPT_PTN'], $as_pstime));
			$i++;
		}

		echo 'Count ['.$server.']: '.$i."\n";
		
		sqlsrv_free_stmt($result);
		sqlsrv_close($conn);
	}