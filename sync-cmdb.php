<?php
	// Retrieve information from CMDBuild

	/**
		\file
		\brief Получение информации из CMDBuild
	*/

	if(!defined('Z_PROTECTED')) exit;
	
	echo PHP_EOL.'sync-cmdb:'.PHP_EOL;

	$post_data = json_encode(array(
		'username'  => CMDB_LOGIN,
		'password'  => CMDB_PASS
	));

	$ch = curl_init(CMDB_URL.'/sessions?scope=service&returnId=true');

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json;'));
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

	$result = curl_exec($ch);
	curl_close($ch);

	if($result === FALSE)
	{
		echo 'ERROR: Login failed.'.PHP_EOL;
		return;
	}

	$result_json = @json_decode($result, true);
	
	if(!isset($result_json['success'])
		|| ($result_json['success'] != 1)
		|| !isset($result_json['data'])
	)
	{
		echo 'ERROR: Login failed.'.PHP_EOL;
		return;
	}

	//print_r($result_json);

	$sess_id = $result_json['data']['_id'];
	//echo 'SessionID: '. $sess_id.PHP_EOL;

	echo 'Loading virtual servers...'.PHP_EOL;
	
	//$ch = curl_init(CMDB_URL.'/classes/brlVirtualSrv?scope=service');
	//$ch = curl_init(CMDB_URL.'/classes/brlVirtualSrv?scope=service');
	//$ch = curl_init(CMDB_URL.'/classes/brlService?scope=service');
	//$ch = curl_init(CMDB_URL.'/classes/brlService/cards');
	$ch = curl_init(CMDB_URL.'/classes/brlVirtualSrv/cards');

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json;', 'Cmdbuild-authorization: '.$sess_id));
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$result = curl_exec($ch);
	curl_close($ch);

	$result_json = @json_decode($result, true);
	
	if(!isset($result_json['success'])
		|| ($result_json['success'] != 1)
		|| !isset($result_json['data'])
	)
	{
		echo 'ERROR: Invalid answer from server!'.PHP_EOL;
		return;
	}
	
	// Clear flags before sync
	$db->put(rpv("UPDATE @vm SET `flags` = (`flags` & ~({%VMF_EXIST_CMDB} | {%VMF_BAREMETAL} | {%VMF_EQUIPMENT} | {%VMF_HAVE_ROOT})) WHERE `flags` & ({%VMF_EXIST_CMDB} | {%VMF_BAREMETAL} | {%VMF_EQUIPMENT} | {%VMF_HAVE_ROOT})"));

	$i = 0;
	foreach($result_json['data'] as &$card)
	{
		$db->put(rpv("
				INSERT INTO @vm (`name`, `cmdb_type`, `cmdb_cpu`, `cmdb_ram_size`, `cmdb_hdd_size`, `cmdb_os`, `flags`)
				VALUES ({s0}, {s1}, {d2}, {d3}, {d4}, {s5}, {d6})
				ON DUPLICATE KEY UPDATE `cmdb_type` = {s1}, `cmdb_cpu` = {d2}, `cmdb_ram_size` = {d3}, `cmdb_hdd_size` = {d4}, `cmdb_os` = {s5}, `flags` = (`flags` | {d6})
			",
			$card['brlCIName'],
			$card['_brlVirtualization_description'],
			$card['brlVSrvCPU'],
			$card['brlVSrvRAM'],
			0,  // hdd_size
			$card['brlVSrvOSVersion'], // _brlSrvOS_description OR _brlSrvOS_description_translation
			((@$card['_brlVSrvRootAccess_description'] === 'true') ? VMF_HAVE_ROOT : 0) | VMF_EXIST_CMDB
		));
		$i++;
	}

	echo 'Count: '.$i.PHP_EOL;

	echo 'Loading baremetal servers...'.PHP_EOL;
	
	$ch = curl_init(CMDB_URL.'/classes/brlPhyServ/cards');

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json;', 'Cmdbuild-authorization: '.$sess_id));
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$result = curl_exec($ch);
	curl_close($ch);

	$result_json = @json_decode($result, true);
	
	if(!isset($result_json['success'])
		|| ($result_json['success'] != 1)
		|| !isset($result_json['data'])
	)
	{
		echo 'ERROR: Invalid answer from server!'.PHP_EOL;
		return;
	}
	
	$i = 0;
	foreach($result_json['data'] as &$card)
	{
		$db->put(rpv("
				INSERT INTO @vm (`name`, `cmdb_type`, `cmdb_cpu`, `cmdb_ram_size`, `cmdb_hdd_size`, `cmdb_os`, `flags`)
				VALUES ({s0}, {s1}, {d2}, {d3}, {d4}, {s5}, {%VMF_EXIST_CMDB} | {%VMF_BAREMETAL})
				ON DUPLICATE KEY UPDATE `cmdb_type` = {s1}, `cmdb_cpu` = {d2}, `cmdb_ram_size` = {d3}, `cmdb_hdd_size` = {d4}, `cmdb_os` = {s5}, `flags` = (`flags` | {%VMF_EXIST_CMDB} | {%VMF_BAREMETAL})
			",
			$card['brlCIName'],
			'Baremetal',
			$card['brlPhSrvvCPU'],
			$card['brlPhSrvRAM'],
			0,  // hdd_size
			$card['brlPhSrvOSversion'] // _brlSrvOS_description OR _brlSrvOS_description_translation
		));
		$i++;
	}

	echo 'Count: '.$i.PHP_EOL;

	/* Temporary disabled - Not required now

	echo 'Loading servers equipment...'.PHP_EOL;

	$ch = curl_init(CMDB_URL.'/classes/brlSrvHardware/cards');

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json;', 'Cmdbuild-authorization: '.$sess_id));
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$result = curl_exec($ch);
	curl_close($ch);

	$result_json = @json_decode($result, true);
	
	if(!isset($result_json['success'])
		|| ($result_json['success'] != 1)
		|| !isset($result_json['data'])
	)
	{
		echo 'ERROR: Invalid answer from server!'.PHP_EOL;
		return;
	}

	$i = 0;
	foreach($result_json['data'] as &$card)
	{
		$db->put(rpv("
				INSERT INTO @vm (`name`, `cmdb_type`, `cmdb_cpu`, `cmdb_ram_size`, `cmdb_hdd_size`, `cmdb_os`, `flags`)
				VALUES ({s0}, {s1}, {d2}, {d3}, {d4}, {s5}, {%VMF_EXIST_CMDB} | {%VMF_EQUIPMENT})
				ON DUPLICATE KEY UPDATE `cmdb_type` = {s1}, `cmdb_cpu` = {d2}, `cmdb_ram_size` = {d3}, `cmdb_hdd_size` = {d4}, `cmdb_os` = {s5}, `flags` = (`flags` | {%VMF_EXIST_CMDB} | {%VMF_EQUIPMENT})
			",
			$card['brlCIName'],
			'Baremetal',
			0,
			0,
			0,  // hdd_size
			'' // _brlSrvOS_description OR _brlSrvOS_description_translation
		));
		$i++;
	}

	echo 'Count: '.$i.PHP_EOL;
	*/

	echo 'Loading VPN groups...'.PHP_EOL;

	$ch = curl_init(CMDB_URL.'/classes/brlVPNService/cards');

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json;', 'Cmdbuild-authorization: '.$sess_id));
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$result = curl_exec($ch);
	curl_close($ch);

	$result_json = @json_decode($result, true);
	
	if(!isset($result_json['success'])
		|| ($result_json['success'] != 1)
		|| !isset($result_json['data'])
	)
	{
		echo 'ERROR: Invalid answer from server!'.PHP_EOL;
		return;
	}
	
	$db->put(rpv("UPDATE @ad_groups SET `flags` = ((`flags` & ~{%AGF_EXIST_CMDB})) WHERE (`flags` & {%AGF_EXIST_CMDB}) = {%AGF_EXIST_CMDB}"));

	$i = 0;
	foreach($result_json['data'] as &$card)
	{
		$db->put(rpv("
				INSERT INTO @ad_groups (`name`, `flags`)
				VALUES ({s0}, {%AGF_EXIST_CMDB})
				ON DUPLICATE KEY UPDATE `flags` = (`flags` | {%AGF_EXIST_CMDB})
			",
			strtolower($card['brlCIName'])
		));
		$i++;
	}

	echo 'Count: '.$i.PHP_EOL;

	echo 'Loading SMB resources...'.PHP_EOL;

	$ch = curl_init(CMDB_URL.'/classes/brlSBM/cards');

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json;', 'Cmdbuild-authorization: '.$sess_id));
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$result = curl_exec($ch);
	curl_close($ch);

	$result_json = @json_decode($result, true);
	
	if(!isset($result_json['success'])
		|| ($result_json['success'] != 1)
		|| !isset($result_json['data'])
	)
	{
		echo 'ERROR: Invalid answer from server!'.PHP_EOL;
		return;
	}
	
	$i = 0;
	foreach($result_json['data'] as &$card)
	{
		if(!empty($card['_brlVirSrvRef_description']))
		{
			$srv_name = strtoupper($card['_brlVirSrvRef_description']);
		}
		else
		{
			$srv_name = strtoupper($card['_brlSrvRef_description']);
		}
		
		echo 'Server: '.$srv_name.', Share: '.$card['brlSMBName'].PHP_EOL;

		$id = 0;
		if(!$db->select_ex($result, rpv("SELECT ms.`id` FROM @maxpatrol_smb AS ms WHERE ms.`hostname` = ! AND ms.`share` = ! LIMIT 1", $srv_name, $card['brlSMBName'])))
		{
			$db->put(rpv("
					INSERT INTO @maxpatrol_smb (`hostname`, `share`, `flags`)
					VALUES ({s0}, {s1}, {%MSF_EXIST_CMDB})
				",
				$srv_name,
				$card['brlSMBName']
			));
		}
		else
		{
			$db->put(rpv("UPDATE @maxpatrol_smb SET `flags` = (`flags` | {%MSF_EXIST_CMDB}) WHERE `id` = # LIMIT 1", $result[0][0]));
		}

		$i++;
	}

	echo 'Count: '.$i.PHP_EOL;
