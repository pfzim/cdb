<?php
	// Import MAC addresses

	/**
		\file
		\brief API для загрузки ошибок с сетевых устройств.
		
		Входящие параметры:
		- netdev - имя сетевого устройства передающего данные
		- list   - список данных, описание колонок:
		  - sw_id                  - имя коммутатора
		  - port                   - порт коммутатора в который подключено устройство
		  - SingleCollisionFrames  - коллизии, их появление говорит о том, что линк работает в полудуплексе, чего быть не должно
		  - CarrierSenseErrors     - потери несущей, частая их регистрация (флаппинг) говорит о проблемах с физикой. Порогом для предупреждения принимаем более 10 событий в час
		  - InErrors               - сводный показатель количества ошибок приема. Их быть не должно
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

	$pid = 0;
	$last_sw_id = '';
	
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
	
	error_log("\n".date('c').'  Start import from device: '.$net_dev." List:\n".$_POST['list']."\n", 3, '/var/log/cdb/import-errors.log');
	$line = strtok($_POST['list'], "\n");
	while($line !== FALSE)
	{
		$line_no++;
		
		$line = trim($line);
		
		if(!empty($line))
		{
			// Парсим сторку
			
			$row = explode(',', $line);  // format: sw_id,port,SingleCollisionFrames,CarrierSenseErrors,InErrors

			if(count($row) != 5)
			{
				$code = 1;
				$error_msg .= 'Warning: Incorrect line format. Line '.$line_no.';';
				
				error_log(date('c').'  Warning: Incorrect line format ('.$line_no.'): '.$line."\n", 3, '/var/log/cdb/import-errors.log');

				$line = strtok("\n");
				continue;
			}
			
			if($row[0] === $net_dev)
			{
				$last_sw_id = $net_dev;
				$pid = $dev_id;
			}
			else if($last_sw_id !== $row[0])
			{
				$pid = 0;
				$last_sw_id = $row[0];
				if($db->select_ex($result, rpv("SELECT m.`id`, m.`pid` FROM @devices AS m WHERE m.`type` = {%DT_NETDEV} AND m.`name` = ! LIMIT 1", $row[0])))
				{
					$pid = intval($result[0][0]);
					if(intval($result[0][1]) != $dev_id)    // && $pid != $dev_id  - лишнее, подразумевается в первом if
					{
						if($db->put(rpv("UPDATE @devices SET `pid` = # WHERE `id` = # LIMIT 1", $dev_id, $pid)))
						{
							error_log(date('c').'  Error: Update device info (id = '.$pid.', set pid = '.$dev_id.")\n", 3, '/var/log/cdb/import-errors.log');
						}
						else
						{
							error_log(date('c').'  Info: Updated device info (id = '.$pid.', set pid = '.$dev_id.")\n", 3, '/var/log/cdb/import-errors.log');
						}
					}
				}
				else
				{
					if($db->put(rpv("INSERT INTO @devices (`type`, `pid`, `name`, `flags`) VALUES ({%DT_NETDEV}, #, !, 0)", $dev_id, $row[0])))
					{
						$pid = $db->last_id();
					}
					else
					{
						error_log(date('c').'  Error: Insert new device ('.$line_no.'): '.$line."\n", 3, '/var/log/cdb/import-errors.log');
					}
				}
			}
			
			$row_id = 0;
			if(!$db->select_ex($result, rpv("SELECT m.`id` FROM @net_errors AS m WHERE m.`pid` = # LIMIT 1", $pid)))
			{
				if($db->put(rpv("INSERT INTO @net_errors (`pid`, `port`, `date`, `scf`, `cse`, `ine`, `flags`) VALUES (#, !, NOW(), #, #, #, 0x0000)",
					$pid,
					$row[1],  // port
					$row[2],  // SingleCollisionFrames
					$row[3],  // CarrierSenseErrors
					$row[4]   // InErrors
				)))
				{
					$row_id = $db->last_id();
				}
			}
			else
			{
				$row_id = $result[0][0];
				$db->put(rpv("UPDATE @net_errors SET `pid` = #, `port` = !, `date` = NOW(), `scf` = #, `cse` = #, `ine` = #, `flags` = (`flags` & ~{%NEF_FIXED}) WHERE `id` = # LIMIT 1",
					$pid,
					$row[1],  // port
					$row[2],  // SingleCollisionFrames
					$row[3],  // CarrierSenseErrors
					$row[4],  // InErrors
					$row_id
				));
			}
		}

		$line = strtok("\n");
	}

	echo '{"code": '.$code.', "message": "'.$error_msg.'"}';
