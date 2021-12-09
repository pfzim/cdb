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

	function call_json_zabbix(string $in_method, string $in_auth, array $in_params) {
		$message = json_encode( array(
			'jsonrpc' => '2.0', 
			'id' => new_guid(), 
			'method' => $in_method, 
			'params' => $in_params,
			'auth' => $in_auth
		));
		echo "Initial RPC:\r\n";
		var_dump($message);
		echo "\r\n";
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


	// start authentification
	$retval = call_json_zabbix('user.login', null, array('user' => ZABBIX_LOGIN, 'password' => ZABBIX_PASS));
	if(!is_null ($retval)) {
		$auth_key = $retval;
		
		//get BBC list
		$retval = call_json_zabbix('host.get', $auth_key, 
			array(
				'output=' => ['hostid','host','status','proxy_hostid'],
				'selectInterfaces' => ['hostid','host','status','proxy_hostid'],
				'selectGroups' => 'extend'
				)
			);
		echo "BBC list:\r\n";
		var_dump($retval);
	} else { 
		echo "Authentification error.\r\n";
	}

	/** TODO:
	if($result !== FALSE) {
		$scans_list = @json_decode($result, true);
		
		foreach($scans_list['scans'] as &$scan) {
			if(in_array($scan['folder_id'], NESSUS_FOLDERS_IDS)) {
				echo "\r\n".$scan['name']."\r\n\r\n";
		
				$ch = curl_init(NESSUS_URL.'/scans/'.$scan['id']);

				curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-ApiKeys: accessKey='.NESSUS_ACCESS_KEY.'; secretKey='.NESSUS_SECRET_KEY.';'));
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

				$result = curl_exec($ch);
				curl_close($ch);
				
				if($result !== FALSE) {
					$scans = @json_decode($result, true);

					if($scans['info']['status'] == 'completed') {
						$scan_date = date('Y-m-d H:i:s', $scans['info']['scan_end']);
						
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
					}
				}
				else
				{
					echo 'ERROR'."\r\n\r\n"	;
				}
			}
		}
	}
	else
	{
		echo 'ERROR'."\r\n\r\n"	;
	}

	echo 'Count: '.$i."\n";
*/
