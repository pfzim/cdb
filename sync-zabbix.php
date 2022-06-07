<?php
	// Retrieve information from Zabbix

	/**
		\file
		\brief Синхронизация с Zabbix
		
		Получение информации из Zabbix о хостах мониторинга

		1. Скрипт формирует таблицу zabbix_hosts со списоком устройств, которые
		   требуется поставить на мониторинг в Zabbix.
		   Маршрутизаторы и устройства ДКС выбираются по "типу" указанному в
		   ИТ Инвент. Далее по физическому местоположению определяется
		   маршрутизатор, к которому подключено это устройство.

		2. Получает с сервера Zabbix список хостов, которые уже поставлены
		   на мониторинг. Обновляет статус в таблице zabbix_hosts в
		   соответствии с именем хоста.

		4. Получат с сервера Zabbix список групп начинающихся с TT и формирует
		   массив для дальнейшей привязки хостов к регионам по номеру региона
		   из названия.

		4. В соответствии со значениями из таблицы zabbix_hosts добавляет,
		   удаляет или обновляет параметры хостов в Zabbix.
		   
		Добавлено создание у удаление пользователей в соответстии с составом
		группы AD G_Zabbix_Access. Пользователям назначается роль User role и
		добавляются в группу LDAP Users. Операция производится только с
		пользователями входящими в состав группы LDAP Users. Локальные
		пользователи не удаляются.

	*/

	if(!defined('Z_PROTECTED')) exit;

	// Список шаблонов соответствующих типу оборудования можно
	// вынести в конфиг БД или забирать из Zabbix по заданному
	// наименованию

	$zabbix_templates = array(
		131 => 12923
	);

	define('ZABBIX_TEMPLATE_FALLBACK',  12923);    // Этот шаблон будет подключен, если типу оборудования не найден соответствующий шаблон
	define('ZABBIX_TEMPLATE_FOR_BCC',   12924);    // Этот шаблон будет добавлен к основному, если к маршрутизатору подключен резервный комплект
	define('ZABBIX_USER_ROLE_ID',       '1');      // Роль присваеваемая пользователю
	define('ZABBIX_USER_GROUP_ID',      '14');     // Группа, в котору добавляется пользователь
	define('ZABBIX_ACCESS_AD_GROUP_DN', 'CN=G_Zabbix_Access,OU=Zabbix,OU=AccessGroups,OU=Service Accounts,DC=bristolcapital,DC=ru');     // Группа в AD с пользователями, которым будет предоставлен доступ к Zabbix

	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.zabbix.php');

	echo "\nsync-zabbix:\n";

	function cb_template_cmp($el, $payload)
	{
		return in_array($el['templateid'], $payload);
	}

	function cb_group_cmp($el, $payload)
	{
		return ($el['groupid'] === $payload);
	}

	function cb_user_cmp($el, $payload)
	{
		return (strcasecmp($el['username'], $payload) == 0);
	}

	function array_find($arr, $func, $payload)
	{
		foreach($arr as &$el)
		{
			if(call_user_func($func, $el, $payload))
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	// Формируем список устройств, которые должны мониторится в Zabbix

	$i = 0;

/*
		Old:
		SELECT
			DISTINCT dm.`id`
		FROM c_mac AS m
		LEFT JOIN c_mac AS dm
			ON (dm.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})) = ({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})
			AND dm.`port` = 'self'
			AND dm.`branch_no` = m.`branch_no`
			AND dm.`loc_no` = m.`loc_no`
		WHERE
			(m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_INV_BCCDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})) = ({%MF_INV_BCCDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})
		GROUP BY m.`branch_no`, m.`loc_no`, m.`inv_no`
		HAVING dm.`id` IS NOT NULL



		SELECT
			m.`id`,
			m.`name`,
			m.`mac`,
			m.`ip`,
			m.`port`,
			m.`vlan`,
			m.`inv_no`,
			m.`status`,
			COUNT(bc.`id`) AS bcc_count
		FROM c_mac AS m
		LEFT JOIN c_mac AS bc
			ON (bc.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_INV_BCCDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})) = ({%MF_INV_BCCDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})
			AND bc.`branch_no` = m.`branch_no`
			AND bc.`loc_no` = m.`loc_no`
		WHERE
			(m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_FROM_NETDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE} | {%MF_SERIAL_NUM})) = ({%MF_FROM_NETDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE} | {%MF_SERIAL_NUM})
			AND m.`type_no` = 63
		GROUP BY m.`id`

		SELECT
			m.`id`,
			m.`name`,
			m.`mac`,
			m.`ip`,
			m.`port`,
			m.`vlan`,
			m.`inv_no`,
			m.`status`,
			(SELECT COUNT(bc.`id`) FROM c_mac AS bc
			WHERE (bc.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_INV_BCCDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})) = ({%MF_INV_BCCDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})
			AND bc.`branch_no` = m.`branch_no`
			AND bc.`loc_no` = m.`loc_no`)
		FROM c_mac AS m
		WHERE
			(m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})) = ({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})
			AND m.`type_no` = 63

		SELECT
			DISTINCT m.`inv_no`
		FROM c_mac AS m
		WHERE
			(m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})) = ({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})
			AND m.`type_no` = 63
*/

	if($db->select_assoc_ex($result, rpv("
		SELECT
			m.`id`,
			-- m.`name`,
			-- m.`mac`,
			-- m.`ip`,
			-- m.`port`,
			-- m.`vlan`,
			-- m.`inv_no`,
			-- m.`status`,
			COUNT(bc.`id`) AS bcc_count
		FROM @mac AS m
		LEFT JOIN @mac AS bc
			ON (bc.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_INV_BCCDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})) = ({%MF_INV_BCCDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})
			AND bc.`branch_no` = m.`branch_no`
			AND bc.`loc_no` = m.`loc_no`
		WHERE
			(m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_FROM_NETDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE} | {%MF_SERIAL_NUM})) = ({%MF_FROM_NETDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE} | {%MF_SERIAL_NUM})
			AND m.`type_no` = 63
		GROUP BY m.`id`
	")))
	{
		$db->put(rpv("UPDATE @zabbix_hosts SET `flags` = (`flags` & ~{%ZHF_MUST_BE_MONITORED}) WHERE (`flags` & {%ZHF_MUST_BE_MONITORED})"));

		foreach($result as &$row)
		{
			$db->put(rpv("
					INSERT INTO @zabbix_hosts (`pid`, `host_id`, `flags`)
					VALUES ({d0}, 0, ({%ZHF_MUST_BE_MONITORED} | {d1}))
					ON DUPLICATE KEY
					UPDATE `flags` = (`flags` | {%ZHF_MUST_BE_MONITORED} | {d1})
				",
				$row['id'],
				intval($row['bcc_count']) ? ZHF_TEMPLATE_WITH_BCC : 0
			));
		}
	}

	// Получаем список устройств, которые уже присутствуют в Zabbix, и помечаем
	// их в таблице. Ключ: hostname

	// start authentification
	$zabbix_result = zabbix_api_request('user.login', null, array('user' => ZABBIX_LOGIN, 'password' => ZABBIX_PASS));
	if(!$zabbix_result)
	{
		echo "Failed login.\n";
	}
	else
	{
		$auth_key = $zabbix_result;

		// get BCC list
		$zabbix_result = zabbix_api_request(
			'host.get',
			$auth_key,
			array(
				'output' => ['hostid', 'host'] // , 'status', 'proxy_hostid'
				//'selectInterfaces' => ['interfaceid', 'ip'],
				//'selectGroups' => 'extend',
				//'selectTriggers' => ['templateid', 'triggerid', 'description', 'status', 'priority']
			)
		);

		$db->put(rpv("UPDATE @zabbix_hosts SET `flags` = (`flags` & ~{%ZHF_EXIST_IN_ZABBIX}) WHERE (`flags` & {%ZHF_EXIST_IN_ZABBIX})"));

		foreach($zabbix_result as &$host)
		{
			$founded = FALSE;

			// by host id - возможно исправит ситуацию, когда хост сменил имя
			if($db->select_assoc_ex($result, rpv("
					SELECT
						zh.`pid`,
						zh.`host_id`,
						m.`name`
					FROM @zabbix_hosts AS zh
					LEFT JOIN @mac AS m ON m.`id` = zh.`pid`
					WHERE zh.`host_id` = {d0}
				",
				$host['hostid']
			)))
			{
				$founded = TRUE;
				foreach($result as &$row)
				{
					if(strcasecmp($host['host'], $row['name']) !== 0)
					{
						echo 'Different host Name: '.$host['host'].' ('.$host['hostid'].')'.' DB: '.$row['name'].' ('.$row['host_id'].')'.PHP_EOL;
					}
				}
				$db->put(rpv("UPDATE @zabbix_hosts SET `flags` = (`flags` | {%ZHF_EXIST_IN_ZABBIX}) WHERE `host_id` = #", $host['hostid']));
			}
			// by host name - вторичный поиск по имени отрабатывает ситуацию, когда хост ранее добавлен руками
			else if($db->select_assoc_ex($result, rpv("
					SELECT
						zh.`pid`,
						zh.`host_id`,
						m.`name`
					FROM @zabbix_hosts AS zh
					LEFT JOIN @mac AS m ON m.`id` = zh.`pid`
					WHERE m.`name` = {s0}
				",
				$host['host']
			)))
			{
				$founded = TRUE;
				foreach($result as &$row)
				{
					if($host['hostid'] !== $row['host_id'])
					{
						echo 'Different host ID: '.$host['host'].' ('.$host['hostid'].')'.' DB: '.$row['name'].' ('.$row['host_id'].')'.PHP_EOL;
					}
					$db->put(rpv("UPDATE @zabbix_hosts SET `flags` = (`flags` | {%ZHF_EXIST_IN_ZABBIX}), `host_id` = # WHERE `pid` = #", $host['hostid'], $row['pid']));
				}
			}

			/* by IP
			foreach($host['interfaces'] as &$interface)
			{
				if($db->select_assoc_ex($result, rpv("
						SELECT
							zh.`pid`
						FROM @zabbix_hosts AS zh
						LEFT JOIN @mac AS m ON m.`id` = zh.`pid`
						WHERE m.`ip` = {s0}
					",
					$interface['ip']
				)))
				{
					$founded = TRUE;
					foreach($result as &$row)
					{
						$db->put(rpv("UPDATE @zabbix_hosts SET `flags` = (`flags` | {%ZHF_EXIST_IN_ZABBIX}), `host_id` = # WHERE `pid` = #", $host['hostid'], $row['pid']));
					}
				}
			}
			*/

			if(!$founded)
			{
				echo 'Host exist at Zabbix, but not founded in monitoring list: '.$host['host'].' ('.$host['hostid'].')'.PHP_EOL;
			}
		}

		// Удаляем из таблицы хосты, которые не должны мониторится и уже отсутствуют в Zabbix

		$db->put(rpv("DELETE FROM @zabbix_hosts WHERE (`flags` & ({%ZHF_MUST_BE_MONITORED} | {%ZHF_EXIST_IN_ZABBIX})) = 0"));

		echo "Starting export to Zabbix...\n";

		// Получаем список доступных групп регионов

		$zabbix_result = zabbix_api_request(
			'hostgroup.get',
			$auth_key,
			array(
				'startSearch'               => TRUE,
				'search'                    => array(
					'name' => 				ZABBIX_REGION_GROUP_PREFIX
				),
				'output'                    => ['groupid', 'name']
			)
		);

		$zabbix_groups = array();

		foreach($zabbix_result as &$group)
		{
			if($group['name'] === ZABBIX_REGION_GROUP_PREFIX)
			{
				$zabbix_groups[0] = $group['groupid'];
			}
			else if(preg_match('/^'.ZABBIX_REGION_GROUP_PREFIX.'(\d+)/i', $group['name'], $matches))
			{
				$zabbix_groups[intval($matches[1])] = $group['groupid'];
			}
		}

		// Добавляем, обновляем и удаляем с мониторинга в Zabbix хосты

		$added_and_updated = 0;
		$removed = 0;

		if($db->select_assoc_ex($result, rpv("
			SELECT
				m.`id`,
				m.`name`,
				m.`mac`,
				m.`ip`,
				m.`inv_no`,
				m.`type_no`,
				m.`model_no`,
				DATE_FORMAT(m.`date`, '%d.%m.%Y %H:%i:%s') AS `last_update`,
				zh.`host_id`,
				zh.`flags`
			FROM @zabbix_hosts AS zh
			LEFT JOIN @mac AS m
				ON m.`id` = zh.`pid`
			WHERE
				(zh.`flags` & ({%ZHF_MUST_BE_MONITORED} | {%ZHF_EXIST_IN_ZABBIX}))
		")))
		{
			foreach($result as &$row)
			{
				$host_name = strtoupper($row['name']);

				$region_num = 0;
				if(preg_match('/^\w+-(\d+)-/', $host_name, $matches))
				{
					$region_num = intval($matches[1]);
				}

				$group_id = isset($zabbix_groups[$region_num]) ? $zabbix_groups[$region_num] : 0;

				// Выбираем шаблом по номеру модели оборудования
				
				$template_ids = array();
				$template_ids[] = isset($zabbix_templates[intval($row['model_no'])]) ? $zabbix_templates[intval($row['model_no'])] : ZABBIX_TEMPLATE_FALLBACK;

				if(intval($row['flags']) & ZHF_TEMPLATE_WITH_BCC)
				{
					$template_ids[] = ZABBIX_TEMPLATE_FOR_BCC;
				}

				switch(intval($row['flags']) & (ZHF_MUST_BE_MONITORED | ZHF_EXIST_IN_ZABBIX))
				{
					// Add to Zabbix

					case ZHF_MUST_BE_MONITORED:
					{
						echo 'Add to Zabbix: '.$row['name']."\n";
						
						if(empty($row['name']) || empty($row['ip']))
						{
							echo 'Error: Empty IP or name'."\n";
							break;
						}

						$added_and_updated++;

						$zabbix_result = zabbix_api_request(
							'host.create',
							$auth_key,
							array(
								'host'         => $host_name,
								'groups'       => array('groupid'      => $group_id),
								'templates'    => array_reduce($template_ids, function($result, $value) { $result[] = array('templateid'   => $value); return $result; }, array()),
								'proxy_hostid' => ZABBIX_Host_Proxy,
								'interfaces'   => array(
									array(
										'type'    => '2',
										'main'    => '1',
										'useip'   => '1',
										'dns'     => '',
										'port'    => '161',
										'ip'      => $row['ip'],
										'details' => array(
											'version'        => '3',
											'bulk'           => '1',
											'securityname'   => ZABBIX_Host_SecName,
											'securitylevel'  => '2',
											'authpassphrase' => ZABBIX_Host_SecAuth,
											'privpassphrase' => ZABBIX_Host_SecPass
										)
									)
								)
							)
						);

						echo '  Host ID: '.$zabbix_result['hostids'][0]."\n";
						
						$db->put(rpv("UPDATE @zabbix_hosts SET `flags` = (`flags` | {%ZHF_EXIST_IN_ZABBIX}), `host_id` = # WHERE `pid` = # LIMIT 1", $zabbix_result['hostids'][0], $row['id']));
					}
					break;

					// Update at Zabbix

					case (ZHF_MUST_BE_MONITORED | ZHF_EXIST_IN_ZABBIX):
					{
						echo 'Check before update at Zabbix: '.$row['name'].' ('.$row['host_id'].")\n";

						if(empty($row['name']) || empty($row['ip']))
						{
							echo 'Error: Empty IP or name'."\n";
							break;
						}

						$added_and_updated++;

						$zabbix_result = zabbix_api_request(
							'host.get',
							$auth_key,
							array(
								'hostids'                   => $row['host_id'],
								'output'                    => ['hostid', 'host', 'status', 'proxy_hostid'],
								'selectGroups'              => ['groupid'],
								'selectParentTemplates'     => ['templateid'],
								'selectInterfaces' => ['interfaceid', 'ip', 'main', 'type'],
								//'selectTriggers'   => ['templateid', 'triggerid', 'description', 'status', 'priority']
							)
						);

						foreach($zabbix_result as &$host)
						{
							/*
							IP адрес перестал быть ключевым значением, поэтому
							проверяем его у SNMP интерфейсов. Чтобы
							сократить количество запросов к Zabbix, список
							адресов получаем в общем запросе host.get

							$zabbix_result = zabbix_api_request(
								'hostinterface.get',
								$auth_key,
								array(
									'hostids'       => $row['host_id'],
									'output'        => ['interfaceid', 'ip'],
									'filter'        => ['main' => 1, 'type' => 2]
								)
							);
							*/

							foreach($host['interfaces'] as &$interface)
							{
								if(
									intval($interface['main']) == 1
									&& intval($interface['type']) == 2
									&& $interface['ip'] !== $row['ip']
								)
								{
									echo '  Updating interface: '.$interface['interfaceid']."\n";
									zabbix_api_request(
										'hostinterface.update',
										$auth_key,
										array(
											'interfaceid'   => $interface['interfaceid'],
											'ip'            => $row['ip']
										)
									);
								}
							}

							if($host['proxy_hostid'] !== ZABBIX_Host_Proxy
								|| !array_find($host['parentTemplates'], 'cb_template_cmp', $template_ids)
								|| ($group_id && !array_find($host['groups'], 'cb_group_cmp', $group_id))
								|| $host['host'] !== $host_name  // hostname проверяем, т.к. он теперь не является ключом
							)
							{
								$templates_to_clear = array_filter(
									$host['parentTemplates'],
									function($value) use($template_ids) {
										return !cb_template_cmp($value, $template_ids);
									}
								);

								echo '  Update at Zabbix: '.$row['name'].' ('.$row['host_id'].")\n";
								//echo json_encode($templates_to_clear, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

								zabbix_api_request(
									'host.update',
									$auth_key,
									array(
										'hostid'          => $row['host_id'],
										'host'            => $host_name,
										'groups'          => array('groupid'      => $group_id),
										'templates'       => array_reduce($template_ids, function($result, $value) { $result[] = array('templateid'   => $value); return $result; }, array()),
										'templates_clear' => &$templates_to_clear,
										'proxy_hostid'    => ZABBIX_Host_Proxy
									)
								);
							}
						}
					}
					break;

					// Remove from Zabbix

					default:
					{
						echo 'Remove from Zabbix: '.$row['name'].' ('.$row['host_id'].")\n";

						$removed++;

						$zabbix_result = zabbix_api_request(
							'host.delete',
							$auth_key,
							array($row['host_id'])
						);
					}
				}
			}
		}

		echo 'Added and updated: '.$added_and_updated."\n";
		echo 'Deleted: '.$removed."\n";
		
		# Register new users from AD group
		
		$users_in_ad_group = array();

		$entries_count = 0;

		$ldap = ldap_connect(LDAP_URI);
		if($ldap)
		{
			ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
			ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
			if(ldap_bind($ldap, LDAP_USER, LDAP_PASSWD))
			{
				$cookie = '';
				do
				{
					$sr = ldap_search(
						$ldap,
						LDAP_BASE_DN,
						'(&(objectCategory=person)(objectClass=user)(memberof:1.2.840.113556.1.4.1941:='.ZABBIX_ACCESS_AD_GROUP_DN.'))',
						array('samaccountname'),
						0,
						0,
						0,
						LDAP_DEREF_NEVER,
						[['oid' => LDAP_CONTROL_PAGEDRESULTS, 'value' => ['size' => 200, 'cookie' => $cookie]]]
					);

					if($sr === FALSE)
					{
						throw new Exception('ldap_search return error: '.ldap_error($ldap));
					}
					
					$matcheddn = NULL;
					$referrals = NULL;
					$errcode = NULL;
					$errmsg = NULL;
					
					if(!ldap_parse_result($ldap, $sr, $errcode , $matcheddn , $errmsg , $referrals, $controls))
					{
						throw new Exception('ldap_parse_result return error code: '.$errcode.', message: '.$errmsg.', ldap_error: '.ldap_error($ldap));
					}

					$entries = ldap_get_entries($ldap, $sr);
					if($entries === FALSE)
					{
						throw new Exception('ldap_get_entries return error: '.ldap_error($ldap));
					}

					$i = $entries['count'];

					while($i > 0)
					{
						$i--;
						$account = &$entries[$i];
						
						if(!empty($account['samaccountname'][0]))
						{
							array_push($users_in_ad_group, $account['samaccountname'][0]);

							$entries_count++;
						}
					}

					if(isset($controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie']))
					{
						$cookie = $controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'];
					}
					else
					{
						$cookie = '';
					}
						ldap_free_result($sr);
				}
				while(!empty($cookie));

				ldap_unbind($ldap);
			}
		}

		$exist_users = zabbix_api_request(
			'user.get',
			$auth_key,
			array(
				'usrgrpids' => ZABBIX_USER_GROUP_ID,
				'output' => array('userid', 'username')
			)
		);

		foreach($exist_users as &$user)
		{
			if(!in_array($user['username'], $users_in_ad_group))
			{
				echo 'Delete user from Zabbix: '.$user['username']."\n";
				
				$zabbix_result = zabbix_api_request(
					'user.delete',
					$auth_key,
					array(
						$user['userid']
					)
				);
			}
		}

		foreach($users_in_ad_group as &$username)
		{
			if(!array_find($exist_users, 'cb_user_cmp', $username))
			{
				echo 'Add user to Zabbix: '.$username."\n";
				
				$zabbix_result = zabbix_api_request(
					'user.create',
					$auth_key,
					array(
						'roleid' => ZABBIX_USER_ROLE_ID,
						'alias' => $username,
						'usrgrps' => array(
							array(
								'usrgrpid' => ZABBIX_USER_GROUP_ID
							)
						)
					)
				);
			}
		}

		$zabbix_result = zabbix_api_request('user.logout', $auth_key, []);
	}
