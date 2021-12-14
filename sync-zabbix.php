<?php
	// Retrieve information from Zabbix

	/**
		\file
		\brief Получение информации из Zabbix о хостах мониторинга
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-zabbix:\n";
	$i = 0;

	function new_guid(): string {
    	return sprintf(
			'%04X%04X-%04X-%04X-%04X-%04X%04X%04X', 
			mt_rand(0, 65535), 
			mt_rand(0, 65535), 
			mt_rand(0, 65535), 
			mt_rand(16384, 20479), 
			mt_rand(32768, 49151), 
			mt_rand(0, 65535), 
			mt_rand(0, 65535), 
			mt_rand(0, 65535)
		);
	}

	function call_json_zabbix(string $in_method, $in_auth, array $in_params) {
		$message = json_encode( array(
			'jsonrpc' => '2.0', 
			'id' => new_guid(), 
			'method' => $in_method, 
			'params' => $in_params,
			'auth' => $in_auth
		));
		//DEBUG
		//echo "Initial RPC:\r\n";
		//var_dump($message); echo "\r\n";
		$ch = curl_init(ZABBIX_URL.'/api_jsonrpc.php');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json;'));
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $message);

		$result = curl_exec($ch);
		curl_close($ch);

		if($result !== FALSE) {
			$rdecoded = json_decode($result, TRUE);
			if(array_key_exists('error',$rdecoded)) {
				echo "ERROR:\r\n";
				var_dump($rdecoded['error']);
			} elseif(!array_key_exists('result',$rdecoded)) {
				echo "ERROR: RPC result format unexpected\r\n";
				var_dump($rdecoded);
			} else {
				return $rdecoded['result'];
			}
		}
		return null;
	}

	//CtulhuDB connection string
	$params = array(
		'Database' =>				CTULHU_DB_NAME,
		'UID' =>					CTULHU_DB_USER,
		'PWD' =>					CTULHU_DB_PASSWD,
		'ReturnDatesAsStrings' =>	true
	);

	// start authentification
	$retval = call_json_zabbix('user.login', null, array('user' => ZABBIX_LOGIN, 'password' => ZABBIX_PASS));
	if(!is_null ($retval)) {
		$auth_key = $retval;
		
		// get BBC list
		$retval = call_json_zabbix('host.get', $auth_key, 
			array(
				'output=' => ['hostid','host','status','proxy_hostid'],
				'selectInterfaces' => ['interfaceid','ip'],
				'selectGroups' => 'extend'
				)
			);
		// echo "BBC list:\r\n"; var_dump($retval);
		
		//connect to CtulhuDB
		$conn = sqlsrv_connect(CTULHU_DB_HOST, $params);
		if($conn === false) {
			print_r(sqlsrv_errors());
			exit(1);
		}

		// checking hosts 1 by 1
		foreach($retval as &$host) {
			$sGroups = null;
			if(isset($host['groups'])) {
				foreach($host['groups'] as &$group) {
					if(isset($group['groupid'])){
						$sGroups .= $group['groupid'].';';
			}}}
			$host_fixed = preg_replace('/^(bcc_)/i','',$host['host']);
			// each IP = unique record in DB
			foreach($host['interfaces'] as &$sIP) {
				$bState = ($host['status']==0?'True':'False');
				echo  $sIP['ip'].'=> hostname:'.$host_fixed .' id:'.$host['hostid'].' proxy:'.$host['proxy_hostid'].' status:'.$bState.' groups:'.$sGroups."\r\n";
				$proc_params = array(
					array(&$sIP['ip'], SQLSRV_PARAM_IN)
					,array(&$host_fixed, SQLSRV_PARAM_IN)
					,array(&$host['hostid'], SQLSRV_PARAM_IN)
					,array(&$host['proxy_hostid'], SQLSRV_PARAM_IN)
					,array(&$bState, SQLSRV_PARAM_IN)
				);
				$sql = "EXEC [dbo].[spZabbix_update_bcc] @ipstring = ?, @hostname = ?, @hostid = ?, @proxyid = ?, @statzabbix = ?;";
				$proc_exec = sqlsrv_prepare($conn, $sql, $proc_params);
				if (!sqlsrv_execute($proc_exec)) {
					echo "Procedure spZabbix_update_bcc fail!\r\n";
					print_r(sqlsrv_errors());
					//die;
				}
				echo "---------------------------\r\n";
			}
		}
	} else {
		echo "Authentification error.\r\n";
	}

	/** TODO:

	

	$result = sqlsrv_query($conn, "
		SELECT
			[DeviceName],
			[LastSync],
			[EncryptionStatus]
		FROM [".TMEE_DB_NAME."].[dbo].[Device]
		WHERE IsDeleted = 0
		ORDER BY [LastSync]
	");




	foreach($scans['hosts'] as &$host)
	{
		//echo '  '.$host['host_id'].': '.$host['hostname']."\r\n";

		$row_id = 0;
		if($db->select_ex($res, rpv("SELECT d.`id` FROM @devices AS d WHERE d.type = 4 AND d.`name` = ! LIMIT 1", $host['hostname'])))
		{
			$row_id = $res[0][0];
		}
		else
		{
			if($db->put(rpv("INSERT INTO @devices (`type`, `pid`, `name`, `flags`) VALUES (4, 0, !, 0)", $host['hostname'])))
			{
				$row_id = $db->last_id();
			}
		}
		
		if($row_id)
		{
			$ch = curl_init(NESSUS_URL.'/scans/'.$scan['id'].'/hosts/'.$host['host_id']);

			curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-ApiKeys: accessKey='.NESSUS_ACCESS_KEY.'; secretKey='.NESSUS_SECRET_KEY.';'));
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$result = curl_exec($ch);
			curl_close($ch);

			if($result !== FALSE)
			{
				$host_details = @json_decode($result, true);
				foreach($host_details['vulnerabilities'] as &$vuln)
				{
					if(intval($vuln['severity']) > 0)
					{
						//echo '    '.$vuln['severity_index'].' '.$vuln['severity'].' '.' '.$vuln['plugin_id'].' '.$vuln['plugin_name']."\r\n"; flush();

						if(!$db->select_ex($res, rpv("SELECT v.`plugin_id` FROM @vulnerabilities AS v WHERE v.`plugin_id` = # LIMIT 1", $vuln['plugin_id'])))
						{
							$db->put(rpv("
									INSERT INTO @vulnerabilities (`plugin_id`, `plugin_name`, `severity`, `flags`)
									VALUES ( #, !, #, #)
								",
								$vuln['plugin_id'],
								$vuln['plugin_name'],
								$vuln['severity'],
								0x0000
							));
						}
						// update if needed
						else
						{
							$db->put(rpv("
									UPDATE
										@vulnerabilities
									SET
										`plugin_name` = !,
										`severity` = #,
									WHERE
										`plugin_id` = #
									LIMIT 1
								",
								$vuln['plugin_name'],
								$vuln['severity'],
								$vuln['plugin_id']
							));
						}

						if(!$db->select_ex($res, rpv("SELECT s.`id`, s.`scan_date` FROM @vuln_scans AS s WHERE s.`pid` = # AND s.`plugin_id` = # LIMIT 1", $row_id, $vuln['plugin_id'])))
						{
							$db->put(rpv("
									INSERT INTO @vuln_scans (`pid`, `plugin_id`, `scan_date`, `folder_id`, `flags`)
									VALUES (#, #, !, #, #)
								",
								$row_id,
								$vuln['plugin_id'],
								$scan_date,
								$scan['folder_id'],
								0x0000
							));
						}
						else
						{
							if(sql_date_cmp($scan_date, $res[0][1]) > 0)
							{
								$db->put(rpv("
										UPDATE
											@vuln_scans
										SET
											`scan_date` = !,
											`folder_id` = #,
											`flags` = (`flags` & ~0x0002)     -- reset Fixed flag
										WHERE
											`id` = #
										LIMIT 1
									",
									$scan_date,
									$scan['folder_id'],
									$res[0][0]
								));
							}
						}
						
						$i++;
					}
				}
			}
			else
			{
				echo 'ERROR'."\r\n\r\n"	;
			}
		}
	}
*/
