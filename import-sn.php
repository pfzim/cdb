<?php
	// Import MAC addresses

	/**
		\file
		\brief API для загрузки MAC адресов с сетевых устройств и соответствующих им Серийных номеров.

		Одно устройство может иметь множество MAC адресов.
		Этот скрипт накапливает таблицу соответсвия серийных номеров MAC
		адресам.
		
		Данные записываются в таблицу mac_sn.
		Ищется MAC адрес в таблице mac и заменяется на серийный номер.
		
		Входящие параметры:
		- netdev - имя сетевого устройства передающего данные
		- list   - список данных, описание колонок:
		  - mac    - MAC адрес
		  - sn     - соответствующий Серийный номер

		Формат данных JSON:
		{
			"netdev": "ИМЯ_УСТРОЙСТВА",
			"list": [
				{
					"sn": "СЕРИЙНЫЙ_НОМЕР_1",
					"mac_addresses": [
						"MAC_1",
						"MAC_2",
						...
						"MAC_N"
					]
				},
				{
					"sn": "СЕРИЙНЫЙ_НОМЕР_2",
					"mac_addresses": [
						"MAC_1",
						"MAC_2",
						...
						"MAC_N"
					]
				},
				...
				{
					"sn": "СЕРИЙНЫЙ_НОМЕР_N",
					"mac_addresses": [
						"MAC_1",
						"MAC_2",
						...
						"MAC_N"
					]
				}
			]
		}
	*/

	if(!defined('Z_PROTECTED')) exit;

	$path_log = '/var/log/cdb/import-sn.log';

	/*
	if(isset($_POST['json']))
	{
		$data_raw = @$_POST['json'];
	}
	else
	{
		$data_raw = file_get_contents('php://input');
	}
	*/

	$data_raw = @$_POST['json'];
	if(empty($data_raw))
	{
		echo '{"code": 1, "message": "Error: Empty request"}';
		exit;
	}	

	$data = json_decode($data_raw, TRUE);

	if($data === NULL)
	{
		echo '{"code": 1, "message": "Error: JSON parse error: '.json_escape(json_last_error_msg()).'"}';
		error_log("\n".date('c').'  Error parse JSON: '.json_escape(json_last_error_msg())."\n".$data_raw."\n", 3, $path_log);
		exit;
	}

	if(empty($data['netdev']))
	{
		echo '{"code": 1, "message": "Error: netdev undefined"}';
		exit;
	}

	if(!$data['list'])
	{
		echo '{"code": 1, "message": "Error: list undefined"}';
		exit;
	}

	$mac_fake_address = NULL;

	if(defined('MAC_FAKE_ADDRESS'))
	{
		$mac_fake_address = MAC_FAKE_ADDRESS;
	}
	else if(isset($config['mac_fake_address']))
	{
		$mac_fake_address = $config['mac_fake_address'];
	}

	$code = 0;
	$error_msg = '';

	$net_dev = $data['netdev'];
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

	error_log("\n".date('c').'  Start import from device: '.$net_dev." List:\n".json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n", 3, $path_log);

	foreach($data['list'] as &$serial)
	{
		$sn = strtoupper(preg_replace('/[-:;., ]/i', '', $serial['sn']));

		if(empty($sn))
		{
			$code = 1;
			$error_msg .= 'Warning: Empty SN;';

			error_log(date('c').'  Warning: Empty SN'."\n", 3, $path_log);

			continue;
		}

		foreach($serial['mac_addresses'] as &$mac)
		{
			// Убираем лишние символы из MAC и SN

			$mac = strtolower(preg_replace('/[^0-9a-f]/i', '', $mac));

			// Проверяем корректность данных

			if(empty($mac) || (strlen($mac) != 12))
			{
				$code = 1;
				$error_msg .= 'Warning: Empty MAC address;';

				error_log(date('c').'  Warning: Empty MAC address'."\n", 3, $path_log);

				continue;
			}

			// Игнорируем фиктивный MAC адрес, который присутствует на большинстве устройств
			if($mac === $mac_fake_address)
			{
				error_log(date('c').'  Warning: Ignored fake MAC: '.$mac."\n", 3, $path_log);

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
				$error_msg .= 'Error: When INSERT to DB;';

				error_log(date('c').'  Error: When INSERT to DB ('.$sn.', '.$mac.')'."\n", 3, $path_log);
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
	}

	echo '{"code": '.$code.', "message": "'.json_escape($error_msg).'"}';
