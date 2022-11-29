<?php
	// Retrieve information from VK Cloud (Mail.Ru)

	/**
		\file
		\brief Получение информации из VK Cloud (OpenStack API)
		
		Получает список проектов и из каждого проекта получает список
		виртуальных машин.
	*/

	if(!defined('Z_PROTECTED')) exit;
	
	echo PHP_EOL.'sync-vk:'.PHP_EOL;
	
	$db->put(rpv("UPDATE @vm SET `flags` = ((`flags` & ~{%VMF_EXIST_VK})) WHERE (`flags` & {%VMF_EXIST_VK}) = {%VMF_EXIST_VK}"));

	$ch = curl_init(VK_AUTH_URL.'/auth/tokens?nocatalog');

	$post_data = json_encode(array(
		'auth' => array(
			'identity' => array(
				'methods' => array(
					'password'
				),
				'password' => array(
					'user' => array(
						'domain' => array(
							'name' => 'users'
						),
						'name' => VK_LOGIN,
						'password' => VK_PASSWD
					)
				)
			)
		)
	));

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

	$result = curl_exec($ch);

	if($result === FALSE)
	{
		curl_close($ch);
		echo 'ERROR: Login failed.'.PHP_EOL;
		return;
	}

	$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	$header = substr($result, 0, $header_size);
	$body = substr($result, $header_size);

	curl_close($ch);

	if(!preg_match('/^X-Subject-Token:\\s+([^\\r\\n;]+)/mi', $header, $matches))
	{
		echo 'ERROR: Token not found.';
		return;
	}

	$token = $matches[1];
	//echo 'Token: '.$token.PHP_EOL;

	$result_json = @json_decode($body, true);
	
	if(!isset($result_json['token']['user']['id']))
	{
		echo 'ERROR: Invalid answer from server: '.$result.PHP_EOL;
		return;
	}

	$ch = curl_init(VK_AUTH_URL.'/users/'.$result_json['token']['user']['id'].'/projects');

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Auth-Token: '.$token));
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$result = curl_exec($ch);
	curl_close($ch);

	$result_json = @json_decode($result, true);
	
	if(!isset($result_json['projects']))
	{
		echo 'ERROR: Invalid answer from server: '.$result.PHP_EOL;
		return;
	}
	
	$projects = array();
	
	$i = 0;
	foreach($result_json['projects'] as &$project)
	{
		echo 'Project: '.$project['name'].PHP_EOL;
		$projects[] = $project['id'];
	}
	
	foreach($projects as &$project_id)
	{
		$ch = curl_init(VK_AUTH_URL.'/auth/tokens?nocatalog');

		$post_data = json_encode(array(
			'auth' => array(
				'identity' => array(
					'methods' => array(
						'password'
					),
					'password' => array(
						'user' => array(
							'domain' => array(
								'name' => 'users'
							),
							'name' => VK_LOGIN,
							'password' => VK_PASSWD
						)
					)
				),
				'scope' => array(
					'project' => array(
						'id' => $project_id
					)
				)
			)
		));

		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

		$result = curl_exec($ch);
		curl_close($ch);

		if($result === FALSE)
		{
			echo 'ERROR: Login failed.'.PHP_EOL;
			return;
		}

		if(!preg_match('/^X-Subject-Token:\\s+([^\\r\\n;]+)/mi', $result, $matches))
		{
			echo 'ERROR: Token not found.';
			return;
		}
		
		$token = $matches[1];

		echo 'Loading VM list...'.PHP_EOL;
		//echo 'Token: '.$token.PHP_EOL;
		
		$ch = curl_init(VK_NOVA_URL.'/servers/detail');

		curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Auth-Token: '.$token));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$result = curl_exec($ch);
		curl_close($ch);

		$result_json = @json_decode($result, true);
		
		if(!isset($result_json['servers']))
		{
			echo 'ERROR: Invalid answer from server: '.$result.PHP_EOL;
			return;
		}
		
		$i = 0;
		foreach($result_json['servers'] as &$server)
		{
			//echo 'Name: '.$server['name'].PHP_EOL;

			$ch = curl_init(VK_NOVA_URL.'/flavors/'.$server['flavor']['id']);

			curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Auth-Token: '.$token));
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$result = curl_exec($ch);
			curl_close($ch);

			$result_json = @json_decode($result, true);
			
			if(!isset($result_json['flavor']))
			{
				echo 'ERROR: Invalid answer from server: '.$result.PHP_EOL;
				return;
			}
			
			$vm = &$result_json['flavor'];

			echo 'Name: '.$server['name'].', vcpus: '.$vm['vcpus'].', ram: '.$vm['ram'].', disk: '.$vm['disk'].PHP_EOL;

			$db->put(rpv("
					INSERT INTO @vm (`name`, `cpu`, `ram_size`, `hdd_size`, `os`, `flags`)
					VALUES ({s0}, {d1}, {d2}, {d3}, {s4}, {%VMF_EXIST_VK})
					ON DUPLICATE KEY UPDATE `cpu` = {d1}, `ram_size` = {d2}, `hdd_size` = {d3}, `os` = {s4}, `flags` = (`flags` | {%VMF_EXIST_VK})
				",
				$server['name'],
				$vm['vcpus'],
				$vm['ram']/1024,
				$vm['disk'],
				''
			));

			$i++;
		}
	}
