<?php
	if(!defined('ROOTDIR'))
	{
		define('ROOTDIR', dirname(__FILE__));
	}

	if($argc > 1 && !empty($argv[1]))
	{
		$_GET['action'] = $argv[1];
	}

	include(ROOTDIR.DIRECTORY_SEPARATOR.'cdb.php');
