<?php
	// Retrieve information from MaxPatrol

	/**
		\file
		\brief Получение информации из MaxPatrol
		
		Получает список активов и информацию по ним.
		
		Запрос: POST <Корневой URL API>:3334/connect/token
		Как определить какой у нас тип Идентификатор приложения (client_id)?
		Где посмотреть Ключ доступа к приложению (client_secret)?
		Используйте, команду corecfg get, в выводе которой будут указаны нужные Вам параметры.
	*/

	if(!defined('Z_PROTECTED')) exit;
	
	echo PHP_EOL.'sync-maxpatrol:'.PHP_EOL;
	
	$db->put(rpv('UPDATE @maxpatrol SET `flags` = ((`flags` & ~{%MPF_EXIST})) WHERE (`flags` & {%MPF_EXIST}) = {%MPF_EXIST}'));
	$db->put(rpv('TRUNCATE @maxpatrol_smb'));

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

//goto lb_vuln;

	echo 'Loading assets list...'.PHP_EOL;

	$ch = curl_init(MAXPATROL_URL.'/api/assets_temporal_readmodel/v1/assets_grid');

	$post_data = json_encode(array(
		'pdql' => 'select(Host.@Id, Host.Hostname, Host.Fqdn, Host.IpAddress, Host.@AuditTime)',
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
			$date = 'NULL';
			if(!empty($record['Host.@AuditTime']))
			{
				$date = '\''.DateTime::createFromFormat(DateTime::ISO8601, $record['Host.@AuditTime'])->format('Y-m-d H:i:s').'\'';
			}

			$hostname = '';
			if(!empty($record['Host.Hostname']))
			{
				$hostname = strtoupper($record['Host.Hostname']);
			}
			elseif(!empty($record['Host.Fqdn']))
			{
				$hostname = strtoupper(preg_replace('/\..*$/', '', $record['Host.Fqdn']));
			}
			
			echo 'Id: '.$record['Host.@Id'].', Hostname: '.$hostname.', IP: '.$record['Host.IpAddress'].', AuditTime: '.$date.PHP_EOL;

			$db->put(rpv("
					INSERT INTO @maxpatrol (`guid`, `name`, `ip`, `audit_time`, `flags`)
					VALUES ({s0}, {s1}, {s2}, {r3}, {%MPF_EXIST})
					ON DUPLICATE KEY UPDATE `name` = {s1}, `ip` = {s2}, `audit_time` = {r3}, `flags` = (`flags` | {%MPF_EXIST})
				",
				$record['Host.@Id'],
				$hostname,
				$record['Host.IpAddress'],
				$date
			));

			$i++;
		}

		$offset += 50;
	}

	echo 'Count: '.$i.PHP_EOL;

	echo 'Loading SMB list...'.PHP_EOL;
	
	$ch = curl_init(MAXPATROL_URL.'/api/assets_temporal_readmodel/v1/assets_grid');

	$post_data = json_encode(array(
		'pdql' => 'select(Host.IpAddress, Host.Hostname, Host.Fqdn, Host.Endpoints) | filter('
			.'Host.Endpoints NOT LIKE "%/%" and '
			.'Host.Endpoints NOT LIKE "%:%" and '
			.'Host.Endpoints NOT LIKE "%.%.%.%" and '
			.'Host.Endpoints NOT LIKE "_$" and '
			.'Host.Hostname NOT LIKE "NN-PRINT-__" and '
			.'Host.Hostname NOT LIKE "RC_-PRINT-__" and '
			.'Host.Endpoints NOT LIKE "RC_-PRN-__" and '
			.'Host.Endpoints NOT LIKE "7701-PRN-__" and '
			.'Host.Endpoints NOT LIKE "\\\\\\\\%\\\\ClusterStorage$" and '
			.'Host.Endpoints NOT LIKE "\\\\\\\\%\\\\_$" and '
			.'Host.Endpoints NOT IN ["Admin$","IPC$","print$","NETLOGON","SYSVOL"]'
		.')',
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
			echo 'Hostname: '.$record['Host.Hostname'].', Endpoints: '.$record['Host.Endpoints'].PHP_EOL;

			$db->put(rpv("
					INSERT INTO @maxpatrol_smb (`hostname`, `share`, `flags`)
					VALUES ({s0}, {s1}, {%MSF_EXIST_MAXPATROL})
				",
				empty($record['Host.Hostname']) ? $record['Host.IpAddress'] : strtoupper($record['Host.Hostname']),
				$record['Host.Endpoints']
			));

			$i++;
		}

		$offset += 50;
	}

	echo 'Count: '.$i.PHP_EOL;

//lb_vuln:

	echo 'Loading vulnerabilities...'.PHP_EOL;
	
	$ch = curl_init(MAXPATROL_URL.'/api/assets_temporal_readmodel/v1/assets_grid');

	$post_data = json_encode(array(
		'pdql' => 'select(Host.IpAddress, '
			.'Host.Hostname, '
			.'Host.OsName, '
			.'Host.@AuditTime, '
			.'Host.@Vulners.CVEs as CVE, '
			.'Host.@Vulners.Status, '
			.'Host.@Vulners.IssueTime, '
			.'Host.@Vulners.DiscoveryTime, '
			.'Host.@Vulners.StatusUpdateTime, '
			.'Host.@Vulners.CVSS3Score, '
			.'Host.@Vulners.IsTrend, '
			.'Host.@Vulners.Metrics.HasNetworkAttackVector, '
			.'Host.@Vulners.Metrics.Exploitable, '
			.'Host.@Vulners.Metrics.HasFix) '
			.'| filter(CVE and (Host.@Vulners.DiscoveryTime > Now() - 7days or Host.@Vulners.StatusUpdateTime > Now() - 7days)) '
			//.'| filter(CVE and (Host.@Vulners.DiscoveryTime > Now() - 60days and Host.@Vulners.DiscoveryTime <= Now() - 30days))',
			.'| group(Host.IpAddress, '
				.'Host.Hostname, '
				.'Host.OsName, '
				.'Host.@AuditTime, '
				.'CVE, '
				.'Host.@Vulners.Status, '
				.'Host.@Vulners.IssueTime, '
				.'max(Host.@Vulners.DiscoveryTime) as MDT, '
				.'max(Host.@Vulners.StatusUpdateTime) as MSUT, '
				.'Host.@Vulners.CVSS3Score, '
				.'Host.@Vulners.IsTrend, '
				.'Host.@Vulners.Metrics.HasNetworkAttackVector, '
				.'Host.@Vulners.Metrics.Exploitable, '
				.'Host.@Vulners.Metrics.HasFix)',
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
			echo 'IpAddress: '.$record['Host.IpAddress'].', Hostname: '.$record['Host.Hostname'].', CVE: '.$record['CVE'].', AuditTime: '.$record['Host.@AuditTime'].PHP_EOL;
			//print_r($record);

			$date_AuditTime = 'NULL';
			if(!empty($record['Host.@AuditTime']))
			{
				$date_AuditTime = '\''.DateTime::createFromFormat(DateTime::ISO8601, $record['Host.@AuditTime'])->format('Y-m-d H:i:s').'\'';
			}

			$date_IssueTime = 'NULL';
			if(!empty($record['Host.@Vulners.IssueTime']))
			{
				$date_IssueTime = '\''.DateTime::createFromFormat(DateTime::ISO8601, $record['Host.@Vulners.IssueTime'])->format('Y-m-d H:i:s').'\'';
			}

			$date_DiscoveryTime = 'NULL';
			if(!empty($record['MDT']))
			{
				$date_DiscoveryTime = '\''.DateTime::createFromFormat(DateTime::ISO8601, $record['MDT'])->format('Y-m-d H:i:s').'\'';
			}

			$date_StatusUpdateTime = 'NULL';
			if(!empty($record['MSUT']))
			{
				$date_StatusUpdateTime = '\''.DateTime::createFromFormat(DateTime::ISO8601, $record['MSUT'])->format('Y-m-d H:i:s').'\'';
			}

			$db->put(rpv("
					INSERT INTO @maxpatrol_vulnerabilities (
						`IpAddress`,
						`CVE`,
						`Hostname`,
						`OsName`,
						`AuditTime`,
						`Status`,
						`IssueTime`,
						`DiscoveryTime`,
						`StatusUpdateTime`,
						`CVSS3Score`,
						`IsTrend`,
						`HasNetworkAttackVector`,
						`Exploitable`,
						`HasFix`,
						`flags`
					)
					VALUES ({s0}, {s1}, {s2}, {s3}, {r4}, {s5}, {r6}, {r7}, {r8}, {f9}, {d10}, {d11}, {d12}, {d13}, 0)
					ON DUPLICATE KEY UPDATE
						`Hostname` = {s2},
						`OsName` = {s3},
						`AuditTime` = {r4},
						`Status` = {s5},
						`IssueTime` = {r6},
						`DiscoveryTime` = {r7},
						`StatusUpdateTime` = {r8},
						`CVSS3Score` = {f9},
						`IsTrend` = {d10},
						`HasNetworkAttackVector` = {d11},
						`Exploitable` = {d12},
						`HasFix` = {d13},
						`flags` = 0
				",
					$record['Host.IpAddress'],
					$record['CVE'],
					$record['Host.Hostname'],
					$record['Host.OsName'],
					$date_AuditTime,
					$record['Host.@Vulners.Status']['id'],
					$date_IssueTime,
					$date_DiscoveryTime,
					$date_StatusUpdateTime,
					str_replace(',', '.', $record['Host.@Vulners.CVSS3Score']),
					($record['Host.@Vulners.IsTrend'] == 'True'),
					($record['Host.@Vulners.Metrics.HasNetworkAttackVector'] == 'True'),
					($record['Host.@Vulners.Metrics.Exploitable'] == 'True'),
					($record['Host.@Vulners.Metrics.HasFix'] == 'True'),
			));

			$i++;
		}

		$offset += 50;
	}

	echo 'Count: '.$i.PHP_EOL;
