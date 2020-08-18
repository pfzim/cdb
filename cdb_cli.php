<?php
/**
	\file
	\brief Стартовый модуль для CLI версии.
	Предназначен для запуска из cron.
	
	\todo Перенести функционал в cdb.php
*/

	if(!defined('ROOTDIR'))
	{
		define('ROOTDIR', dirname(__FILE__));
	}

	if($argc > 1 && !empty($argv[1]))
	{
		$_GET['action'] = $argv[1];
	}

	include(ROOTDIR.DIRECTORY_SEPARATOR.'cdb.php');
