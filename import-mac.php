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
		
		Исключения настраиваются:
		  - по VLAN ID
		  - по подсети (формат 0.0.0.0/0)
		  - по MAC адресу и имени маршрутизатора и имени порта (все значения
			являются регулярными выражениями, если значение NULL, то оно
			игнорируется)
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
	$path_log = '/var/log/cdb/import-mac.log';
	
	// load config parameters from DB if it is not defined in inc.config.php
	
	if($db->select_ex($cfg, rpv('
		SELECT
			m.`name`,
			m.`value`
		FROM @config AS m
		WHERE
			m.`uid` = 0
			AND m.`name` IN (\'mac_exclude_vlan_regex\', \'mac_exclude_json\', \'mac_exclude_by_ip_list\')
	')))
	{
		$config = array();

		foreach($cfg as &$row)
		{
			$config[$row[0]] = $row[1];
		}
	}

	$mac_exclude_vlan_regex = '';

	if(defined('MAC_EXCLUDE_VLAN'))
	{
		$mac_exclude_vlan_regex = MAC_EXCLUDE_VLAN;
	}
	else if(isset($config['mac_exclude_vlan_regex']))
	{
		$mac_exclude_vlan_regex = $config['mac_exclude_vlan_regex'];
	}	

	$mac_exclude_json = NULL;

	if(defined('MAC_EXCLUDE_ARRAY'))
	{
		$mac_exclude_json = MAC_EXCLUDE_ARRAY;
	}
	else if(isset($config['mac_exclude_json']))
	{
		$mac_exclude_json = json_decode($config['mac_exclude_json'], TRUE);
	}	

	$mac_exclude_by_ip_list = NULL;

	if(defined('IP_MASK_EXCLUDE_LIST'))
	{
		$mac_exclude_by_ip_list = IP_MASK_EXCLUDE_LIST;
	}
	else if(isset($config['mac_exclude_by_ip_list']))
	{
		$mac_exclude_by_ip_list = $config['mac_exclude_by_ip_list'];
	}	

	$pid = 0;
	$last_sw_name = '';
	
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
	
	error_log("\n".date('c').'  Start import from device: '.$net_dev." List:\n".$_POST['list']."\n", 3, $path_log);
	$line = strtok($_POST['list'], "\n");
	while($line !== FALSE)
	{
		$line_no++;
		
		$line = trim($line);
		
		if(!empty($line))
		{
			// Парсим сторку
			
			$row = explode(',', $line);  // format: mac,name,ip,sw_id,port,vlan
			if(count($row) != 6 && count($row) != 5)
			{
				$code = 1;
				$error_msg .= 'Warning: Incorrect line format (count:'.count($row).'). Line '.$line_no.';';
				
				error_log(date('c').'  Warning: Incorrect line format ('.$line_no.'; count:'.count($row).'): '.$line."\n", 3, $path_log);

				$line = strtok("\n");
				continue;
			}
			
			// Определяем это серийный номер или MAC. Убираем лишние символы
			$is_sn = false;
			if(preg_match('/^[0-9a-f]{4}\\.[0-9a-f]{4}\\.[0-9a-f]{4}$/i', $row[0]))
			{
				$mac = strtolower(preg_replace('/[^0-9a-f]/i', '', $row[0]));

				if($mac === MAC_FAKE_ADDRESS)
				{
					error_log(date('c').'  Warning: Ignored fake MAC ('.$line_no.'): '.$line."\n", 3, $path_log);

					$line = strtok("\n");
					continue;
				}

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

				error_log(date('c').'  Warning: Empty MAC or SN ('.$line_no.'): '.$line."\n", 3, $path_log);

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
				if($db->select_ex($result, rpv("SELECT m.`id`, m.`pid` FROM @devices AS m WHERE m.`type` = {%DT_NETDEV} AND m.`name` = ! LIMIT 1", $row[3])))
				{
					$pid = intval($result[0][0]);
					if(intval($result[0][1]) != $dev_id)    // && $pid != $dev_id  - лишнее, подразумевается в первом if
					{
						if($db->put(rpv("UPDATE @devices SET `pid` = # WHERE `id` = # LIMIT 1", $dev_id, $pid)))
						{
							error_log(date('c').'  Error: Update device info (id = '.$pid.', set pid = '.$dev_id.")\n", 3, $path_log);
						}
						else
						{
							error_log(date('c').'  Info: Updated device info (id = '.$pid.', set pid = '.$dev_id.")\n", 3, $path_log);
						}
					}
				}
				else
				{
					if($db->put(rpv("INSERT INTO @devices (`type`, `pid`, `name`, `flags`) VALUES ({%DT_NETDEV}, #, !, 0)", $dev_id, $row[3])))
					{
						$pid = $db->last_id();
					}
					else
					{
						error_log(date('c').'  Error: Insert new device ('.$line_no.'): '.$line."\n", 3, $path_log);
					}
				}
			}
			
			$excluded = 0x0000;
			$vlan = (intval($row[5]) == 0) ? 'NULL' : intval($row[5]);
			
			// Сами коммутаторы и маршрутизаторы не исключаем, только оборудование подключенное в них
			
			if(!$is_sn)
			{
				// Исключение по VLAN, MAC адресу, имени коммутатора, порту
			
				if( $vlan !== 'NULL' && preg_match('/'.$mac_exclude_vlan_regex.'/i', $vlan) ) {
					$excluded = MF_TEMP_EXCLUDED;
					error_log(date('c').'  MAC excluded: '.$mac.' by VLAN ID: '.$vlan."\n", 3, $path_log);
				}
				else
				{
					if($mac_exclude_json !== NULL)
					{
						foreach($mac_exclude_json as &$excl)
						{
							if(   (($excl['mac_regex'] === NULL) || preg_match('/'.$excl['mac_regex'].'/i', $mac))
							&& (($excl['name_regex'] === NULL) || preg_match('/'.$excl['name_regex'].'/i', $last_sw_name))
							&& (($excl['port_regex'] === NULL) || preg_match('#'.$excl['port_regex'].'#i', $row[4]))
							)
							{
								$excluded = MF_TEMP_EXCLUDED;
								error_log(date('c').'  MAC excluded: '.$mac."\n", 3, $path_log);
								break;
							}
						}
					}
			
					// Исключение по IP адресу
					if(!empty($mac_exclude_by_ip_list))
					{
						if(!empty($row[2]) && ($excluded & MF_TEMP_EXCLUDED) == 0)
						{
							$masks = explode(';', $mac_exclude_by_ip_list);
							foreach($masks as &$mask)
							{
								if(cidr_match($row[2], $mask))
								{
									$excluded = MF_TEMP_EXCLUDED;
									error_log(date('c').'  MAC excluded: '.$mac.' by IP: '.$row[2].' CIDR: '.$mask."\n", 3, $path_log);
									break;
								}
							}
						}
					}
				}
			}
			
			$row_id = 0;
			if(!$db->select_ex($result, rpv("SELECT m.`id` FROM @mac AS m WHERE m.`mac` = ! AND ((`flags` & {%MF_SERIAL_NUM}) = #) LIMIT 1", $mac, $is_sn ? MF_SERIAL_NUM : 0x0000 )))
			{
				if($db->put(rpv("
						INSERT INTO @mac (`pid`, `name`, `mac`, `ip`, `port`, `vlan`, `first`, `date`, `flags`)
						VALUES ({d0}, {s1}, {s2}, {s3}, {s4}, {r5}, NOW(), NOW(), {d6})
					",
					$pid,
					$row[1],  // name
					$mac,
					$row[2],  // ip
					$row[4],  // port
					$vlan,
					MF_FROM_NETDEV | $excluded | ($is_sn ? MF_SERIAL_NUM : 0x0000)
				)))
				{
					$row_id = $db->last_id();
				}
			}
			else
			{
				$row_id = $result[0][0];
				$db->put(rpv("
						UPDATE @mac
						SET
							`pid` = {d0},
							`name` = {s1},
							`ip` = {s2},
							`port` = {s3},
							`vlan` = {r4},
							`first` = IFNULL(`first`, NOW()), `date` = NOW(), `flags` = ((`flags` & ~{%MF_TEMP_EXCLUDED}) | {d5})
						WHERE
							`id` = {d6}
						LIMIT 1
					",
					$pid,
					$row[1],  // name
					$row[2],  // ip
					$row[4],  // port
					$vlan,
					MF_FROM_NETDEV | $excluded,
					$row_id
				));
			}
		}

		$line = strtok("\n");
	}

	echo '{"code": '.$code.', "message": "'.$error_msg.'"}';
