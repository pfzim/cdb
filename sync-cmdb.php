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
	echo 'SessionID: '. $sess_id.PHP_EOL;

	echo 'Loading servers...'.PHP_EOL;
	
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
	
	$db->put(rpv("UPDATE @vm SET `flags` = ((`flags` & ~{%VMF_EXIST_CMDB})) WHERE (`flags` & {%VMF_EXIST_CMDB}) = {%VMF_EXIST_CMDB}"));

	$i = 0;
	foreach($result_json['data'] as &$card)
	{
		$db->put(rpv("
				INSERT INTO @vm (`name`, `cmdb_cpu`, `cmdb_ram_size`, `cmdb_hdd_size`, `cmdb_os`, `flags`)
				VALUES ({s0}, {d1}, {d2}, {d3}, {s4}, {%VMF_EXIST_CMDB})
				ON DUPLICATE KEY UPDATE `cmdb_cpu` = {d1}, `cmdb_ram_size` = {d2}, `cmdb_hdd_size` = {d3}, `cmdb_os` = {s4}, `flags` = (`flags` | {%VMF_EXIST_CMDB})
			",
			$card['brlVSrvHostname'],
			$card['brlVSrvCPU'],
			$card['brlVSrvRAM'],
			0,  // hdd_size
			$card['brlVSrvOSVersion'] // _brlSrvOS_description OR _brlSrvOS_description_translation
		));
		$i++;
	}

	echo 'Count: '.$i.PHP_EOL;

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
