<?php
	// Set computer flags

	/**
		\file
		\brief Управление объектом Computer.
		Модуль для манипуляции с объектом Computer:
		- Исключение из проверок

		GET method parameters:
		  - id - идентификатор ПК в БД
		  - do - операция:
		    - show    - сбрасывает флаг 0x0004,
		    - hide    - устанавливает флаг 0x0004.
	*/

	if(!defined('Z_PROTECTED')) exit;

	if(!empty($_GET['do']) && !empty($_GET['id']))
	{
		if($_GET['do'] === 'show')
		{
			$db->put(rpv("UPDATE @computers SET `flags` = (`flags` & ~0x0004) WHERE `id` = # LIMIT 1", $_GET['id']));
			echo 'OK';
		}
		else if($_GET['do'] === 'hide')
		{
			$db->put(rpv("UPDATE @computers SET `flags` = (`flags` | 0x0004) WHERE `id` = # LIMIT 1", $_GET['id']));
			echo 'OK';
		}
	}
