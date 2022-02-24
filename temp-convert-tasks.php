<?php
	// Create new and close resolved tasks (TMAO)

	if(!defined('ROOTDIR'))
	{
		define('ROOTDIR', dirname(__FILE__));
	}

	if(!file_exists(ROOTDIR.DIRECTORY_SEPARATOR.'inc.config.php'))
	{
		header('Location: install.php');
		exit;
	}

	error_reporting(E_ALL);
	define('Z_PROTECTED', 'YES');

	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.config.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.utils.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.db.php');

	$db = new MySQLDB(DB_RW_HOST, NULL, DB_USER, DB_PASSWD, DB_NAME, DB_CPAGE, TRUE);

	header("Content-Type: text/plain; charset=utf-8");

	// Move tasks to new table

	$i = 0;
	if($db->select_assoc_ex($result, rpv("SELECT * FROM @computers WHERE `ao_opernum` <> ''")))
	{
		foreach($result as &$row)
		{
			echo $row['name'].' '.$row['ao_opernum']."\r\n";
			$db->put(rpv("INSERT INTO @tasks (`pid`, `flags`, `date`, `operid`, `opernum`) VALUES (#, #, NOW(), !, !)", $row['id'], (intval($row['flags'])&0x0200)?0x0200:(0x0200|0x0001), $row['ao_operid'], $row['ao_opernum']));
			$i++;
		}
	}

	echo 'Moved: '.$i."\r\n";

	$i = 0;
	if($db->select_assoc_ex($result, rpv("SELECT * FROM @computers WHERE `ee_opernum` <> ''")))
	{
		foreach($result as &$row)
		{
			echo $row['name'].' '.$row['ee_opernum']."\r\n";
			$db->put(rpv("INSERT INTO @tasks (`pid`, `flags`, `date`, `operid`, `opernum`) VALUES (#, #, NOW(), !, !)", $row['id'], (intval($row['flags'])&0x0100)?0x0100:(0x0100|0x0001), $row['ee_operid'], $row['ee_opernum']));
			$i++;
		}
	}

	echo 'Moved: '.$i."\r\n";
