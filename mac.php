<?php
	//Set MAC flags and other operaions
	
	/**
		\file
		\brief Управление объектом MAC адресами.
		Модуль для манипуляции с MAC адресами:
		- отключение/включение создания заявок
		- удаление MAC адреса из БД
		
		GET method parameters:
		  do - reset, exclude, delete
		  mac - MAC address or SN
		  type - 1 - mac is MAC address, 2 - mac is SN
	*/

	if(!defined('Z_PROTECTED')) exit;

	if(!empty($_GET['do']) && !empty($_GET['mac']))
	{
		$type = 0x0000;
		if(!empty($_GET['type']) && intval($_GET['type']) == 2)
		{
			$type = 0x0080;
		}
		
		if($db->select_ex($result, rpv("SELECT m.`id` FROM @mac AS m WHERE m.`mac` = ! AND ((`flags` & 0x0080) = #) LIMIT 1", $_GET['mac'], $type)))
		{
			if($_GET['do'] === 'reset')
			{
				if($db->put(rpv("UPDATE @mac SET `flags` = (`flags` & ~0x0002) WHERE `id` = # LIMIT 1", $result[0][0])))
				{
					echo 'OK';
					return;
				}
			}
			else if($_GET['do'] === 'exclude')
			{
				if($db->put(rpv("UPDATE @mac SET `flags` = (`flags` | 0x0002), comment = ! WHERE `id` = # LIMIT 1", @$_GET['comment'], $result[0][0])))
				{
					echo 'OK';
					return;
				}
			}
			else if($_GET['do'] === 'delete')
			{
				$db->put(rpv("DELETE FROM @tasks WHERE `pid` = # AND tid = 3", $result[0][0]));
				if($db->put(rpv("DELETE FROM @mac WHERE `id` = # LIMIT 1", $result[0][0])))
				{
					echo 'OK';
					return;
				}
			}
		}
	}

	echo 'ERROR: MAC address or SN not found!';
