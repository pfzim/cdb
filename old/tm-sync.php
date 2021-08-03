<?php

	if(!defined('ROOTDIR'))
	{
		define('ROOTDIR', dirname(__FILE__));
	}

	if(!file_exists(ROOTDIR.DIRECTORY_SEPARATOR.'inc.config.php'))
	{
		header('Location: install.php');
		exit;
	}

	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.config.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.utils.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.db.php');

	error_reporting(E_ALL);
	define('Z_PROTECTED', 'YES');

	$db = new MySQLDB(DB_RW_HOST, NULL, DB_USER, DB_PASSWD, DB_NAME, DB_CPAGE, TRUE);

	$params = array(
		'Database' =>				'BRC-AO-01-ApexOne',
		'UID' =>					'svc_collector',
		'PWD' =>					'Wm2WFieY',
		'ReturnDatesAsStrings' =>	true
	);

	$conn = sqlsrv_connect("brc-ao-01", $params);
	if($conn === false)
	{
		print_r(sqlsrv_errors());
		exit;
	}

	$result = sqlsrv_query($conn, "SELECT [COMP_NAME]
      ,[PTNUPDTIME]
      ,[SCRIPT_PTN]
      ,[AS_PSTIME]
  FROM [BRC-AO-01-ApexOne].[dbo].[TBL_CLIENT_INFO] WHERE CLIENTTYPE = 0");


while($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC))
{
	echo $row['COMP_NAME'].", ".$row['PTNUPDTIME'].", ".$row['SCRIPT_PTN'].", ".$row['AS_PSTIME']."<br />";
	$db->put(rpv("INSERT INTO @computers (`name`, `ao_ptnupdtime`, `ao_script_ptn`, `ao_as_pstime`) VALUES (!, !, #, !) ON DUPLICATE KEY UPDATE `ao_ptnupdtime` = !, `ao_script_ptn` = #, `ao_as_pstime` = !", $row['COMP_NAME'], $row['PTNUPDTIME'], $row['SCRIPT_PTN'], $row['AS_PSTIME'], $row['PTNUPDTIME'], $row['SCRIPT_PTN'], $row['AS_PSTIME']));
}

sqlsrv_free_stmt($result);
sqlsrv_close($conn);
