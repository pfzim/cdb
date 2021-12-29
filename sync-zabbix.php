<?php
	// Retrieve information from Zabbix

	/**
		\file
		\brief Получение информации из Zabbix о хостах мониторинга
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-zabbix:\n";
	$i = 0;

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

		// Remove hosts from Zabbix using previous Sync state result
		$i = 0;
		echo "\r\n\r\nRemoving hosts from Zabbix:\r\n";
		$removed_ret = sqlsrv_query($conn_ctulhu, "SELECT * FROM [dbo].[fList_Bcc_Zabbix_ToRemove]('".(ZABBIX_Host_Groups['Default'])."');");
		while($removed_row = sqlsrv_fetch_array($removed_ret, SQLSRV_FETCH_ASSOC)) {
			// var_dump($removed_row);
			$zbx_hostname = strtoupper($removed_row['hostname']);
			echo "Host {$zbx_hostname} with ip {$removed_row['ip']}\r\n";
			$i++;
			// TODO: add actualy working code
			/*
			$retval = call_json_zabbix('host.delete', $auth_key,
				array($removed_row['hostid'])
			);
			var_dump($retval); break;
			if( is_null($retval) ) {
				echo "Error removing host {$zbx_hostname}\r\n";
			}
			//break;
			*/
		}
		echo "Removed {$i} hosts\r\n\r\n";

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
		$i = 0;  $tru = 'True';
		echo "\r\n\r\nCreating new hosts in Zabbix:\r\n";
		$itinv_ret = sqlsrv_query($conn_ctulhu, "SELECT * FROM [dbo].[fList_Bcc_Itinvent] () where [statzabbix] is null;");
		while($itinv_row = sqlsrv_fetch_array($itinv_ret, SQLSRV_FETCH_ASSOC)) {
			$zbx_hostname = strtoupper($itinv_row['hostname']);
			echo "Host {$zbx_hostname} with ip {$itinv_row['ip']}\r\n";
			
			$retval = call_json_zabbix('host.create', $auth_key,
				array('host' => ZABBIX_Host_Prefix.$zbx_hostname
					, 'groups' => array('groupid'=> ZABBIX_Host_Groups['Default']) // TODO: add associate group logic
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
			var_dump($retval); //break;
			if(! is_null($retval) and !is_null($retval['hostids'][0])) {
				$i++;
				$proc_params = array(
					array(&$itinv_row['ip'], SQLSRV_PARAM_IN)
					, array(&$zbx_hostname, SQLSRV_PARAM_IN)
					, array(&$retval['hostids'][0], SQLSRV_PARAM_IN)
					, array(&$tru, SQLSRV_PARAM_IN)
				);
				$sql = "EXEC [dbo].[spZabbix_update_bcc] @ipstring = ?, @hostname = ?, @hostid =? , @statzabbix = ?;";
				$proc_exec = sqlsrv_prepare($conn_ctulhu, $sql, $proc_params);
			}
			//break;
		}
		echo "Added {$i} hosts\r\n";

		// CLOSE CONNECTION
		sqlsrv_close($conn_ctulhu);
	} else {
		echo "Authentification error.\r\n";
	}

