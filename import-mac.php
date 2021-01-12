<?php
	// Import MAC addresses

	/**
		\file
		\brief API для загрузки MAC адресов с сетевых устройств.
		
		Входящие параметры:
		- netdev - имя сетевого устройства передающего данные
		- list   - список данных, описание колонок:
		  - mac    - MAC адрес подключенного оборудования
		  - name   - имя подключенного оборудования (hostname)
		  - ip     - ip адрес подключенного оборудования
		  - sw_id  - имя коммутатора
		  - port   - порт коммутатора в который подключено оборудование
		  
		\todo Вместо удаления адреса из БД помечать его как удаленный. Для этого добавить новый флаг.
		Флаг обнулять при обновлении записи.
		\todo Ручное исключение из проверок навсегда производить через флаг 0x0002.
		Временное исключение до первого обнаружения и исключения по фильтрам производить через флаг 0x0004.
		Флаг 0x0004 обнулять при импорте.
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
	$last_sw_name = '';
	
	$net_dev = $_POST['netdev'];
	$dev_id = 0;
	if($db->select_ex($result, rpv("SELECT m.`id` FROM @devices AS m WHERE m.`type` = 3 AND m.`name` = ! LIMIT 1", $net_dev)))
	{
		$dev_id = intval($result[0][0]);
	}
	else
	{
		if($db->put(rpv("INSERT INTO @devices (`type`, `name`, `flags`) VALUES (3, !, 0)", $net_dev)))
		{
			$dev_id = $db->last_id();
		}
	}

	$line_no = 0;
	
	error_log("\n".date('c').'  Start import from device: '.$net_dev." List:\n".$_POST['list']."\n", 3, '/var/log/cdb/import-mac.log');
	$line = strtok($_POST['list'], "\n");
	while($line !== FALSE)
	{
		$line_no++;
		
		$line = trim($line);
		
		if(!empty($line))
		{
			// Парсим сторку
			
			$row = explode(',', $line);  // format: mac,name,ip,sw_id,port
			if(count($row) != 5)
			{
				$code = 1;
				$error_msg .= 'Warning: Incorrect line format. Line '.$line_no.';';
				
				error_log(date('c').'  Warning: Incorrect line format ('.$line_no.'): '.$line."\n", 3, '/var/log/cdb/import-mac.log');

				$line = strtok("\n");
				continue;
			}
			
			// Определяем это серийный номер или MAC. Убираем лишние символы

			$is_sn = false;
			if(preg_match('/^[0-9a-f]{4}\\.[0-9a-f]{4}\\.[0-9a-f]{4}$/i', $row[0]))
			{
				$mac = strtolower(preg_replace('/[^0-9a-f]/i', '', $row[0]));
			}
			else
			{
				$is_sn = true;
				$mac = strtoupper(preg_replace('/[-:;., ]/i', '', $row[0]));
			}
			
			// Проверяем корректность данных

			if(empty($mac) || (!$is_sn && strlen($mac) != 12))
			{
				$code = 1;
				$error_msg .= 'Warning: Invalid MAC. Line '.$line_no.';';

				error_log(date('c').'  Warning: Invalid MAC ('.$line_no.'): '.$line."\n", 3, '/var/log/cdb/import-mac.log');

				$line = strtok("\n");
				continue;
			}
			
			if($row[3] === $net_dev)
			{
				$last_sw_name = $net_dev;
				$pid = $dev_id;
			}
			else if($last_sw_name !== $row[3])
			{
				$pid = 0;
				$last_sw_name = $row[3];
				if($db->select_ex($result, rpv("SELECT m.`id`, m.`pid` FROM @devices AS m WHERE m.`type` = 3 AND m.`name` = ! LIMIT 1", $row[3])))
				{
					$pid = intval($result[0][0]);
					if(intval($result[0][1]) != $dev_id)    // && $pid != $dev_id  - лишнее, подразумевается в первом if
					{
						if($db->put(rpv("UPDATE @devices SET `pid` = # WHERE `id` = # LIMIT 1", $dev_id, $pid)))
						{
							error_log(date('c').'  Error: Update device info (id = '.$pid.', set pid = '.$dev_id.")\n", 3, '/var/log/cdb/import-mac.log');
						}
						else
						{
							error_log(date('c').'  Info: Updated device info (id = '.$pid.', set pid = '.$dev_id.")\n", 3, '/var/log/cdb/import-mac.log');
						}
					}
				}
				else
				{
					if($db->put(rpv("INSERT INTO @devices (`type`, `pid`, `name`, `flags`) VALUES (3, #, !, 0)", $dev_id, $row[3])))
					{
						$pid = $db->last_id();
					}
					else
					{
						error_log(date('c').'  Error: Insert new device ('.$line_no.'): '.$line."\n", 3, '/var/log/cdb/import-mac.log');
					}
				}
			}
			
			// Исключения по MAC адресу
			
			$excluded = 0x0000;
			
			if(
				!$is_sn
				&& (
					(
						(preg_match('/'.MAC_NOT_EXCLUDE_REGEX.'/i', $mac) === 0)
						&& (
							(
								preg_match('/'.NETDEV_SHOPS_REGEX.'/i', $last_sw_name)
								&& preg_match('#'.NETDEV_EXCLUDE_SHOPS_PORT.'#i', $row[4])
							) || (
								preg_match('/'.NETDEV_TOF_REGEX.'/i', $last_sw_name)
								&& preg_match('#'.NETDEV_EXCLUDE_TOF_PORT.'#i', $row[4])
							)
						)
					) || (
						preg_match('/'.NETDEV_SHOPS_FA_REGEX.'/i', $last_sw_name)
						&& preg_match('#'.NETDEV_EXCLUDE_SHOPS_FA_PORT.'#i', $row[4])
					) || (
						preg_match('/'.NETDEV_SHOPS_REGEX.'/i', $last_sw_name)
						&& preg_match('/'.MAC_EXCLUDE_SHOPS_REGEX.'/i', $mac)
					) || (
						preg_match('/'.NETDEV_WIFI_REGEX.'/i', $last_sw_name)
						&& preg_match('#'.NETDEV_EXCLUDE_WIFI_PORT.'#i', $row[4])
					)
				)
			)
			{
				$excluded = 0x0002;
				error_log(date('c').'  MAC excluded: '.$mac."\n", 3, '/var/log/cdb/import-mac.log');
			}
			
			// Исключение по IP адресу

			if(!empty($row[2]) && ($excluded & 0x0002) == 0)
			{
				$masks = explode(';', IP_MASK_EXCLUDE_LIST);
				foreach($masks as &$mask)
				{
					if(cidr_match($row[2], $mask))
					{
						$excluded = 0x0002;
						error_log(date('c').'  MAC excluded: '.$mac.' by IP: '.$row[2].' CIDR: '.$mask."\n", 3, '/var/log/cdb/import-mac.log');
						break;
					}
				}
			}

			$row_id = 0;
			if(!$db->select_ex($result, rpv("SELECT m.`id` FROM @mac AS m WHERE m.`mac` = ! AND ((`flags` & 0x0080) = #) LIMIT 1", $mac, $is_sn ? 0x0080 : 0x0000 )))
			{
				if($db->put(rpv("INSERT INTO @mac (`pid`, `name`, `mac`, `ip`, `port`, `first`, `date`, `flags`) VALUES (#, !, !, !, !, NOW(), NOW(), #)",
					$pid,
					$row[1],  // name
					$mac,
					$row[2],  // ip
					$row[4],  // port
					0x0020 | $excluded | ($is_sn ? 0x0080 : 0x0000)
				)))
				{
					$row_id = $db->last_id();
				}
			}
			else
			{
				$row_id = $result[0][0];
				$db->put(rpv("UPDATE @mac SET `pid` = #,`name` = !, `ip` = !, `port` = !, `first` = IFNULL(`first`, NOW()), `date` = NOW(), `flags` = ((`flags` & ~0x0002) | #) WHERE `id` = # LIMIT 1",
					$pid,
					$row[1],  // name
					$row[2],  // ip
					$row[4],  // port
					0x0020 | $excluded,
					$row_id
				));
			}
		}

		$line = strtok("\n");
	}

	echo '{"code": '.$code.', "message": "'.$error_msg.'"}';
