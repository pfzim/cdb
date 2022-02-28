<?php
	// Import MAC addresses

	/**
		\file
		\brief API для загрузки MAC адресов с сетевых устройств и соответствующих им Серийных номеров.
		
		Входящие параметры:
		- netdev - имя сетевого устройства передающего данные
		- list   - список данных, описание колонок:
		  - mac    - MAC адрес
		  - sn     - соответствующий Серийный номер
	*/

	if(!defined('Z_PROTECTED')) exit;

	if(empty($_POST['netdev']))
	{
		echo '{"code": 1, "message": "Error: netdev undefined"}';
		exit;
	}

	if(empty($_POST['list']))
	{
		echo '{"code": 1, "message": "Error: list undefined"}';
		exit;
	}

	$code = 0;
	$error_msg = '';

	$net_dev = $_POST['netdev'];
	$dev_id = 0;
	if($db->select_ex($result, rpv("SELECT m.`id` FROM @devices AS m WHERE m.`type` = {%DT_NETDEV} AND m.`name` = ! LIMIT 1", $net_dev)))
	{
		$dev_id = intval($result[0][0]);
	}
	else
	{
		if($db->put(rpv("INSERT INTO @devices (`type`, `name`, `flags`) VALUES ({%DT_NETDEV}, !, 0)", $net_dev)))
		{
			$dev_id = $db->last_id();
		}
	}

	$line_no = 0;
	
	error_log("\n".date('c').'  Start import from device: '.$net_dev." List:\n".$_POST['list']."\n", 3, '/var/log/cdb/import-sn.log');
	$line = strtok($_POST['list'], "\n");
	while($line !== FALSE)
	{
		$line_no++;
		
		$line = trim($line);
		
		if(!empty($line))
		{
			// Парсим сторку
			
			$row = explode(',', $line);  // format: mac,name,ip,sw_id,port
			if(count($row) != 2)
			{
				$code = 1;
				$error_msg .= 'Warning: Incorrect line format. Line '.$line_no.';';
				
				error_log(date('c').'  Warning: Incorrect line format ('.$line_no.'): '.$line."\n", 3, '/var/log/cdb/import-sn.log');

				$line = strtok("\n");
				continue;
			}
			
			// Убираем лишние символы из MAC и SN

			$mac = strtolower(preg_replace('/[^0-9a-f]/i', '', $row[0]));
			$sn = strtoupper(preg_replace('/[-:;., ]/i', '', $row[1]));
			
			// Проверяем корректность данных

			if(empty($row[1]) || empty($mac) || (strlen($mac) != 12))
			{
				$code = 1;
				$error_msg .= 'Warning: Invalid data. Line '.$line_no.';';

				error_log(date('c').'  Warning: Invalid data ('.$line_no.'): '.$line."\n", 3, '/var/log/cdb/import-sn.log');

				$line = strtok("\n");
				continue;
			}
			
			// Записываем в БД

			if(!$db->put(rpv("INSERT INTO @mac_sn (`mac`, `sn`, `pid`) VALUES ({s1}, {s2}, {d0}) ON DUPLICATE KEY UPDATE `sn` = {s2}, `pid` = {d0}",
				$dev_id,
				$mac,
				$sn
			)))
			{
				$code = 1;
				$error_msg .= 'Error: When INSERT to DB. Line '.$line_no.';';

				error_log(date('c').'  Error: When INSERT to DB ('.$line_no.'): '.$line."\n", 3, '/var/log/cdb/import-sn.log');
			}

			// Обновляем данные в таблице mac
			
			// Проверяем существование SN в таблице mac
			if($db->select_ex($result, rpv("SELECT m.`id` FROM @mac AS m WHERE m.`mac` = ! AND m.`flags` & {%MF_SERIAL_NUM} LIMIT 1", $sn)))
			{
				// Если запись с таким SN уже существует, то помечаем дубликат с таким MAC как временно исключенный.
				$db->put(rpv("UPDATE @mac SET `flags` = (`flags` | {%MF_TEMP_EXCLUDED}) WHERE `mac` = ! AND (`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED})) = 0 LIMIT 1", $mac));
			}
			else
			{
				// Если запись с таким SN не существует, то у записи с таким MAC меняем MAC на SN.
				$db->put(rpv("UPDATE @mac SET `mac` = {s1}, `flags` = (`flags` | {%MF_SERIAL_NUM}) WHERE `mac` = {s0} AND (`flags` & {%MF_SERIAL_NUM}) = 0 LIMIT 1", $mac, $sn));
			}
		}

		$line = strtok("\n");
	}

	echo '{"code": '.$code.', "message": "'.$error_msg.'"}';
