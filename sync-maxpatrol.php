<?php
	// Retrieve information from MaxPatrol

	/**
		\file
		\brief Получение информации из MaxPatrol
		
		Получает список активов и информацию по ним.
	*/

	if(!defined('Z_PROTECTED')) exit;
	
	echo PHP_EOL.'sync-maxpatrol:'.PHP_EOL;
	
	//$db->put(rpv("UPDATE @vm SET `flags` = ((`flags` & ~{%VMF_EXIST_VK})) WHERE (`flags` & {%VMF_EXIST_VK}) = {%VMF_EXIST_VK}"));

	$ch = curl_init(MAXPATROL_AUTH_URL.'/connect/token');

	$post_data =
		'client_id=mpx'
		.'&client_secret='.urlencode(MAXPATROL_CLIENT_SECRET)
		.'&grant_type=password&username='.urlencode(MAXPATROL_LOGIN)
		.'&password='.urlencode(MAXPATROL_PASSWD)
		.'&response_type=code id_token'
		.'&scope=authorization offline_access mpx.api ptkb.api'
	;

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

	$response = curl_exec($ch);
	curl_close($ch);

	if($response === FALSE)
	{
		echo 'ERROR: Login failed.'.PHP_EOL;
		return;
	}

	$result_json = @json_decode($response, true);
	
	if(!isset($result_json['access_token']))
	{
		echo 'ERROR: Invalid answer from server: '.$response.PHP_EOL;
		return;
	}
	
	$token = $result_json['access_token'];

	//echo 'Token: '.$token.PHP_EOL;

	$ch = curl_init(MAXPATROL_URL.'/api/assets_temporal_readmodel/v1/assets_grid');

	$post_data = json_encode(array(
		'pdql' => 'select(Host.Hostname, Host.IpAddress, Host.@AuditTime)',
		'selectedGroupIds' => array(),
		'additionalFilterParameters' => array(
			'groupIds' => array(),
			'assetIds' => array()
		),
		'includeNestedGroups' => TRUE,
		'utcOffset' => '+03:00'
	));

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$token, 'Content-Type: application/json;charset=UTF-8'));
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

	$response = curl_exec($ch);
	curl_close($ch);

	$result_json = @json_decode($response, true);
	
	if(!isset($result_json['token']))
	{
		echo 'ERROR: Invalid answer from server: '.$response.PHP_EOL;
		return;
	}
	
	$pdql_token = $result_json['token'];

	//echo 'Loading VM list...'.PHP_EOL;
	
	$ch = curl_init(MAXPATROL_URL.'/api/assets_temporal_readmodel/v1/assets_grid/row_count?pdqlToken='.urlencode($pdql_token));

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$token));
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$response = curl_exec($ch);
	curl_close($ch);

	$result_json = @json_decode($response, true);
	
	if(!isset($result_json['rowCount']))
	{
		echo 'ERROR: Invalid answer from server: '.$response.PHP_EOL;
		return;
	}
	
	$row_count = intval($result_json['rowCount']);
	$offset = 0;
	$i = 0;
	
	echo 'Row count: '.$row_count.PHP_EOL;

	while($offset < $row_count)
	{
		$ch = curl_init(MAXPATROL_URL.'/api/assets_temporal_readmodel/v1/assets_grid/data?limit=50&offset='.$offset.'&pdqlToken='.urlencode($pdql_token));

		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$token));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);
		curl_close($ch);

		$result_json = @json_decode($response, true);
		
		if(!isset($result_json['records']))
		{
			echo 'ERROR: Invalid answer from server: '.$response.PHP_EOL;
			return;
		}
		
		foreach($result_json['records'] as &$record)
		{
			echo 'Hostname: '.$record['Host.Hostname'].', IP: '.$record['Host.IpAddress'].', AuditTime: '.$record['Host.@AuditTime'].PHP_EOL;

			/*
			$db->put(rpv("
					INSERT INTO @vm (`name`, `cpu`, `ram_size`, `hdd_size`, `os`, `flags`)
					VALUES ({s0}, {d1}, {d2}, {d3}, {s4}, {%VMF_EXIST_VK})
					ON DUPLICATE KEY UPDATE `cpu` = {d1}, `ram_size` = {d2}, `hdd_size` = {d3}, `os` = {s4}, `flags` = (`flags` | {%VMF_EXIST_VK})
				",
				$server['name'],
				$vm['vcpus'],
				$vm['ram'],
				$vm['disk'],
				''
			));
			*/

			$i++;
		}

		$offset += 50;
	}

	echo 'Count: '.$i.PHP_EOL;
