<?php
	// Retrieve information from vSphere

	/**
		\file
		\brief Получение информации из vSphere
		
		Получает список VM
	*/

	if(!defined('Z_PROTECTED')) exit;
	
	echo PHP_EOL.'sync-vsphere:'.PHP_EOL;
	
	$db->put(rpv("UPDATE @vm SET `flags` = ((`flags` & ~{%VMF_EXIST_VSPHERE})) WHERE (`flags` & {%VMF_EXIST_VSPHERE}) = {%VMF_EXIST_VSPHERE}"));

	$ch = curl_init(VSPHERE_URL.'/api/session');

	$post_data = NULL;

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic '.base64_encode(VSPHERE_LOGIN.':'.VSPHERE_PASSWD)));
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
	
	if(empty($result_json))
	{
		echo 'ERROR: Invalid answer from server: '.$response.PHP_EOL;
		return;
	}
	
	$token = $result_json;

	echo 'Token: '.$token.PHP_EOL;

	echo 'Loading VM list...'.PHP_EOL;
	
	$ch = curl_init(VSPHERE_URL.'/api/vcenter/vm');

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('vmware-api-session-id: '.$token));
	curl_setopt($ch, CURLOPT_POST, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$response = curl_exec($ch);
	curl_close($ch);

	$result_json = @json_decode($response, true);
	
	$i = 0;
	foreach($result_json as &$vm)
	{
		echo 'Name: '.$vm['name'].', vcpus: '.$vm['cpu_count'].', ram: '.$vm['memory_size_MiB'].PHP_EOL;

		$db->put(rpv("
				INSERT INTO @vm (`name`, `cpu`, `ram_size`, `hdd_size`, `os`, `flags`)
				VALUES ({s0}, {d1}, {d2}, {d3}, {s4}, {%VMF_EXIST_VSPHERE})
				ON DUPLICATE KEY UPDATE `cpu` = {d1}, `ram_size` = {d2}, `hdd_size` = {d3}, `os` = {s4}, `flags` = (`flags` | {%VMF_EXIST_VSPHERE})
			",
			$vm['name'],
			$vm['cpu_count'],
			$vm['memory_size_MiB'],
			0,
			''
		));

		$i++;
	}

	echo 'Count: '.$i.PHP_EOL;
