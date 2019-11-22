<?php
	// Mark all items before sync

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

	// Set temporary flag for remove not existing PC after all syncs
	
	if($db->put(rpv("UPDATE @computers SET `flags` = (`flags` | 0x0010) WHERE (`flags` & (0x0020 | 0x0004)) = 0")))
	{
		echo 'DONE';
	}
	else
	{
		echo 'FAILED';
	}

