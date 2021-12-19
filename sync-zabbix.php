<?php
	// Retrieve information from Zabbix

	/**
		\file
		\brief Получение информации из Zabbix о хостах мониторинга
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-zabbix:\n";
	$i = 0;
	define('ZABBIX_Template_Array', ['20332','20535','20435']); // TODO: перенести в конфиг

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
		return in_array($var['templateid'], ZABBIX_Template_Array);
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
			array('output' => ['hostid','host','status','proxy_hostid']
				, 'selectInterfaces' => ['interfaceid','ip']
				, 'selectGroups' => 'extend'
				, 'selectTriggers' => ['templateid','triggerid','description','status','priority']
				)
			);
		// echo "BBC list:\r\n"; var_dump($retval);
		
		//connect to CtulhuDB
		$conn_ctulhu = sqlsrv_connect(CTULHU_DB_HOST, $params);
		if($conn_ctulhu === false) {
			print_r(sqlsrv_errors());
			exit(1);
		}

		// checking hosts 1 by 1
		foreach($retval as &$host) {
			$sGroups = null; $i++;

			$jsonTriggers = json_encode(array_values(array_filter($host['triggers'] ,"zabbix_trigger_id")), JSON_UNESCAPED_UNICODE);
			if(isset($host['groups'])) {
				foreach($host['groups'] as &$group) {
					if(isset($group['groupid'])){
						$sGroups .= $group['groupid'].';';
			}}}
			if( !is_null($sGroups) ) { $sGroups = ';'.$sGroups; }
			$host_fixed = preg_replace('/^(bcc_)/i','',$host['host']);
			// each IP = unique record in DB
			foreach($host['interfaces'] as &$sIP) {
				$bState = ($host['status']==0?'True':'False');
				//echo  $sIP['ip'].'=> hostname:'.$host_fixed .' id:'.$host['hostid'].' proxy:'.$host['proxy_hostid'].' status:'.$bState.' groups '.$sGroups."\r\n";
				//echo 'Triggers: '.$jsonTriggers;
				$proc_params = array(
					array(&$sIP['ip'], SQLSRV_PARAM_IN)
					,array(&$host_fixed, SQLSRV_PARAM_IN)
					,array(&$host['hostid'], SQLSRV_PARAM_IN)
					,array(&$host['proxy_hostid'], SQLSRV_PARAM_IN)
					,array(&$bState, SQLSRV_PARAM_IN)
					,array(&$sGroups, SQLSRV_PARAM_IN)
					,array(&$jsonTriggers, SQLSRV_PARAM_IN)
				);
				$sql = "EXEC [dbo].[spZabbix_update_bcc] @ipstring = ?, @hostname = ?, @hostid = ?, @proxyid = ?, @statzabbix = ?, @groups = ?, @triggers = ?;";
				$proc_exec = sqlsrv_prepare($conn_ctulhu, $sql, $proc_params);
				if (!sqlsrv_execute($proc_exec)) {
					echo "Procedure spZabbix_update_bcc fail!\r\n";
					print_r(sqlsrv_errors());
					//die;
				}
				//echo "\r\n---------------------------\r\n";
			}
		}
		echo "Synced {$i} hosts\r\n";

		//Add new hosts to Zabbix
		echo "\r\n\r\nCreating new hosts in Zabbix:\r\n";
		$itinv_ret = sqlsrv_query($conn_ctulhu, "SELECT * FROM [dbo].[fList_Bcc_Itinvent] () where [statzabbix] is null;");
		while($itinv_row = sqlsrv_fetch_array($itinv_ret, SQLSRV_FETCH_ASSOC)) {
			$zbx_hostname = strtoupper($itinv_row['hostname']);
			echo "Host {$zbx_hostname} with ip {$itinv_row['ip']}\r\n";
			
			$retval = call_json_zabbix('host.create', $auth_key,
				array('host' => ZABBIX_Host_Prefix.$zbx_hostname
					, 'groups' => array('groupid'=> ZABBIX_Host_Group)
					, 'templates ' => array('templateid'=> ZABBIX_Host_Template)
					, 'proxy_hostid' => ZABBIX_Host_Proxy
					, 'interfaces' => [
						array('type' => '2'
							, 'main' => '1'
							, 'useip' => '1'
							, 'dns' => ''
							, 'port' => '161'
							, 'ip' => $itinv_row['ip']
							, 'details' => 
							array('version' => '3'
								, 'bulk' => '1'
								, 'securityname' => ZABBIX_Host_SecName
								, 'securitylevel' => '2'
								, 'authpassphrase' => ZABBIX_Host_SecAuth
								, 'privpassphrase' => ZABBIX_Host_SecPass
							)
						)
					]
				)
			);
			//var_dump($retval); break;
			if(! is_null($retval)) {
				$proc_params = array(
					array(&$itinv_row['ip'], SQLSRV_PARAM_IN)
					, array(&$zbx_hostname, SQLSRV_PARAM_IN)
				);
				$sql = "EXEC [dbo].[spZabbix_update_bcc] @ipstring = ?, @hostname = ?, @statzabbix = 'True';";
				$proc_exec = sqlsrv_prepare($conn_ctulhu, $sql, $proc_params);
			}
			break;
		}


		// CLOSE CONNECTION
		sqlsrv_close($conn_ctulhu);
	} else {
		echo "Authentification error.\r\n";
	}

