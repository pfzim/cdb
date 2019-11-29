<?php
	// Retrieve information from TMAO database

	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-tmao:\n";

	$params = array(
		'Database' =>				TMAO_DB_NAME,
		'UID' =>					TMAO_DB_USER,
		'PWD' =>					TMAO_DB_PASSWD,
		'ReturnDatesAsStrings' =>	true
	);

	$conn = sqlsrv_connect(TMAO_DB_HOST, $params);
	if($conn === false)
	{
		print_r(sqlsrv_errors());
		exit;
	}

	$result = sqlsrv_query($conn, "SELECT [COMP_NAME]
      ,[PTNUPDTIME]
      ,[SCRIPT_PTN]
      ,[AS_PSTIME]
  FROM [".TMAO_DB_NAME."].[dbo].[TBL_CLIENT_INFO] WHERE [CLIENTTYPE] = 0");

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
		
		$db->put(rpv("INSERT INTO @computers (`name`, `ao_ptnupdtime`, `ao_script_ptn`, `ao_as_pstime`) VALUES (!, !, #, !) ON DUPLICATE KEY UPDATE `ao_ptnupdtime` = !, `ao_script_ptn` = #, `ao_as_pstime` = !, `flags` = (`flags` & ~0x0008)", $row['COMP_NAME'], $ptnupdtime, $row['SCRIPT_PTN'], $as_pstime, $ptnupdtime, $row['SCRIPT_PTN'], $as_pstime));
		$i++;
	}

	echo 'Count: '.$i."\r\n";
	
	sqlsrv_free_stmt($result);
	sqlsrv_close($conn);
