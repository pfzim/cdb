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
	
	function zabbix_trigger_id(array $var) {
		$testarray = ['20332','20535','20435']; // TODO: перенести в конфиг
		return in_array($var['templateid'],$testarray);
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
			array('output=' => ['hostid','host','status','proxy_hostid']
				, 'selectInterfaces' => ['interfaceid','ip']
				, 'selectGroups' => 'extend'
				, 'selectTriggers' => ['templateid','triggerid','description','status','priority']
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
			$jsonTriggers = json_encode(array_filter($host['triggers'] ,"zabbix_trigger_id"), JSON_UNESCAPED_UNICODE);
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
				echo 'Triggers: '.$jsonTriggers;
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
				echo "\r\n---------------------------\r\n";
			}
		}
		
		// CLOSE CONNECTION
		if($result !== FALSE) {
			sqlsrv_free_stmt($result);
		}
		sqlsrv_close($conn);
	} else {
		echo "Authentification error.\r\n";
	}

