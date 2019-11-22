<?php
	// Retrieve information from TMEE database

	/*
		TMEE status:

			1 - Not Encrypted
			2 - Encrypted
			3 - Encrypting
			4 - Decrypting
	*/

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

	header("Content-Type: text/plain; charset=utf-8");

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

	$result = sqlsrv_query($conn, "SELECT [DeviceName]
      ,[LastSync]
      ,[EncryptionStatus]
  FROM [".TMEE_DB_NAME."].[dbo].[Device] WHERE IsDeleted = 0");


	$i = 0;
	while($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC))
	{
		//echo $row['DeviceName'].", ".$row['LastSync'].", ".$row['EncryptionStatus']."\r\n";
		$db->put(rpv("INSERT INTO @computers (`name`, `ee_lastsync`, `ee_encryptionstatus`) VALUES (!, !, #) ON DUPLICATE KEY UPDATE `ee_lastsync` = !, `ee_encryptionstatus` = #, `flags` = (`flags` & ~0x10)", $row['DeviceName'], $row['LastSync'], $row['EncryptionStatus'], $row['LastSync'], $row['EncryptionStatus']));
		$i++;
	}

	echo 'Count: '.$i."\r\n";

	sqlsrv_free_stmt($result);
	sqlsrv_close($conn);
