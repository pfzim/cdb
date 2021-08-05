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
		  - vlan   - VLAN ID (integer) for device
		  
		Исключения не применяются к коммутаторам и маршрутизаторам, которые идентифицируются по наличию
		серийного номера вместо MAC адреса.
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
	
	error_log("\n".date('c').'  Start import from device: '.$net_dev." List:\n".$_POST['list']."\n", 3, '/var/log/cdb/import-mac-test.log');
	$line = strtok($_POST['list'], "\n");
	while($line !== FALSE)
	{
		$line_no++;
		
		$line = trim($line);
		
		if(!empty($line))
		{
			// Парсим сторку
			
			$row = explode(',', $line);  // format: mac,name,ip,sw_id,port,vlan
			if(!(count($row) == 5 || count($row) == 6))
			{
				$code = 1;
				$error_msg .= 'Warning: Incorrect line format (count:'.count($row).'). Line '.$line_no.';';
				
				error_log(date('c').'  Warning: Incorrect line format ('.$line_no.'; count:'.count($row).'): '.$line."\n", 3, '/var/log/cdb/import-mac-test.log');

				$line = strtok("\n");
				continue;
			}
			
			// Определяем это серийный номер или MAC. Убираем лишние символы
			$is_sn = false;
			if(preg_match('/^[0-9a-f]{4}\\.[0-9a-f]{4}\\.[0-9a-f]{4}$/i', $row[0]))
			{
				$mac = strtolower(preg_replace('/[^0-9a-f]/i', '', $row[0]));

				if($db->select_ex($result, rpv("SELECT ms.`sn` FROM @mac_sn AS ms WHERE ms.`mac` = ! LIMIT 1", $mac)))
				{
					$is_sn = true;
					$mac = $result[0][0];
				}
			}
			else
			{
				$is_sn = true;
				$mac = strtoupper(preg_replace('/[-:;., ]/i', '', $row[0]));
			}

			if(empty($mac))
			{
				$code = 1;
				$error_msg .= 'Warning: Empty MAC or SN. Line '.$line_no.';';

				error_log(date('c').'  Warning: Empty MAC or SN ('.$line_no.'): '.$line."\n", 3, '/var/log/cdb/import-mac-test.log');

				$line = strtok("\n");
				continue;
			}

			// Получаем идентификатор родительского устройства

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
							error_log(date('c').'  Error: Update device info (id = '.$pid.', set pid = '.$dev_id.")\n", 3, '/var/log/cdb/import-mac-test.log');
						}
						else
						{
							error_log(date('c').'  Info: Updated device info (id = '.$pid.', set pid = '.$dev_id.")\n", 3, '/var/log/cdb/import-mac-test.log');
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
						error_log(date('c').'  Error: Insert new device ('.$line_no.'): '.$line."\n", 3, '/var/log/cdb/import-mac-test.log');
					}
				}
			}
			
			$excluded = 0x0000;
			
			// Сами коммутаторы и маршрутизаторы не исключаем, только оборудование подключенное в них
			
			if(!$is_sn)
			{
				// Исключение по MAC адресу, имени коммутатора, порту
			
				foreach(MAC_EXCLUDE_ARRAY as &$excl)
				{
					if(   (($excl['mac_regex'] === NULL) || preg_match('/'.$excl['mac_regex'].'/i', $mac))
					   && (($excl['name_regex'] === NULL) || preg_match('/'.$excl['name_regex'].'/i', $last_sw_name))
					   && (($excl['port_regex'] === NULL) || preg_match('#'.$excl['port_regex'].'#i', $row[4]))
					)
					{
						$excluded = 0x0002;
						error_log(date('c').'  MAC excluded: '.$mac."\n", 3, '/var/log/cdb/import-mac-test.log');
						break;
					}
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
							error_log(date('c').'  MAC excluded: '.$mac.' by IP: '.$row[2].' CIDR: '.$mask."\n", 3, '/var/log/cdb/import-mac-test.log');
							break;
						}
					}
				}
			}
			
			$row_id = 0; $vlan = $row[5] ?? "DEFAULT";

			if(!$db->select_ex($result, rpv("SELECT m.`id` FROM @mac AS m WHERE m.`mac` = ! AND ((`flags` & 0x0080) = #) LIMIT 1", $mac, $is_sn ? 0x0080 : 0x0000 )))
			{
				if($db->put(rpv("INSERT INTO @mac (`pid`, `name`, `mac`, `ip`, `port`, `vlan`, `first`, `date`, `flags`) VALUES (#, !, !, !, !, !, NOW(), NOW(), #)",
					$pid,
					$row[1],  // name
					$mac,
					$row[2],  // ip
					$row[4],  // port
					$vlan, // vlan id (int)
					0x0020 | $excluded | ($is_sn ? 0x0080 : 0x0000)
				)))
				{
					$row_id = $db->last_id();
				}
			}
			else
			{
				$row_id = $result[0][0];
				$db->put(rpv("UPDATE @mac SET `pid` = #,`name` = !, `ip` = !, `port` = !, `vlan` = !, `first` = IFNULL(`first`, NOW()), `date` = NOW(), `flags` = ((`flags` & ~0x0002) | #) WHERE `id` = # LIMIT 1",
					$pid,
					$row[1],  // name
					$row[2],  // ip
					$row[4],  // port
					$vlan, // vlan id (int)
					0x0020 | $excluded,
					$row_id
				));
			}
		}

		$line = strtok("\n");
	}

	echo '{"code": '.$code.', "message": "'.$error_msg.'"}';
