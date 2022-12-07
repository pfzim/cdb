<?php
	// Retrieve information from Dataline

	/**
		\file
		\brief Получение информации из Dataline
	*/

	if(!defined('Z_PROTECTED')) exit;
	
	echo PHP_EOL.'sync-dataline:'.PHP_EOL;
	
	$db->put(rpv("UPDATE @vm SET `flags` = ((`flags` & ~{%VMF_EXIST_DTLN})) WHERE (`flags` & {%VMF_EXIST_DTLN}) = {%VMF_EXIST_DTLN}"));

	foreach(DTLN_AUTH as $auth)
	{
		$ch = curl_init(DTLN_URL.'/api/sessions');

		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/*+json;version=36.0', 'Authorization: Basic '.base64_encode($auth)));
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, NULL);

		$result = curl_exec($ch);
		curl_close($ch);

		if($result === FALSE)
		{
			echo 'ERROR: Login failed.'.PHP_EOL;
			return;
		}

		if(!preg_match('/^X-VMWARE-VCLOUD-ACCESS-TOKEN:\s+([^;\r\n]+)/mi', $result, $matches))
		{
			echo 'ERROR: Token not found.';
			return;
		}
		
		$token = $matches[1];

		//echo 'Token: '.$token.PHP_EOL;
		echo 'Loading VM list...'.PHP_EOL;
		
		$i = 0;
		$page = 1;

		do
		{
			$ch = curl_init(DTLN_URL.'/api/query?type=vm&page='.$page.'&pageSize=100&fields=name,numberOfCpus,memoryMB,totalStorageAllocatedMb,guestOs&filter=isVAppTemplate==false');

			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/*+json;version=36.0', 'Authorization: Bearer '.$token));
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$result = curl_exec($ch);
			curl_close($ch);

			$result_json = @json_decode($result, true);
			
			if(!isset($result_json['record']) || !isset($result_json['total']))
			{
				echo 'ERROR: Invalid answer from server!'.PHP_EOL;
				return;
			}
			
			foreach($result_json['record'] as &$vm)
			{
				echo 'Name: '.$vm['name'].', numberOfCpus: '.$vm['numberOfCpus'].', memoryMB: '.$vm['memoryMB'].', totalStorageAllocatedMb: '.$vm['totalStorageAllocatedMb'].', guestOs: '.$vm['guestOs'].PHP_EOL;

				$db->put(rpv("
						INSERT INTO @vm (`name`, `cpu`, `ram_size`, `hdd_size`, `os`, `flags`)
						VALUES ({s0}, {d1}, {d2}, {d3}, {s4}, {%VMF_EXIST_DTLN})
						ON DUPLICATE KEY UPDATE `cpu` = {d1}, `ram_size` = {d2}, `hdd_size` = {d3}, `os` = {s4}, `flags` = (`flags` | {%VMF_EXIST_DTLN})
					",
					$vm['name'],
					$vm['numberOfCpus'],
					$vm['memoryMB'],
					$vm['totalStorageAllocatedMb']/1024,
					$vm['guestOs']
				));
				$i++;
			}

			$page++;
		} while($i < intval($result_json['total']));
		
		echo 'Count: '.$i.PHP_EOL;
	}
