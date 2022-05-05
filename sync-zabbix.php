<?php
	// Retrieve information from Zabbix

	/**
		\file
		\brief Получение информации из Zabbix о хостах мониторинга

		1. Скрипт формирует список устройств, которые требуется поставить на
		   мониторинг в Zabbix, в таблицу zabbix_hosts.
		   Устройства ДКС выбираются по "типу" указанному в ИТ Инвент.
		   Далее по физическому местоположению определяется маршрутизатор,
		   к которому подключено это устройство.

		2. Получает с сервера Zabbix список хостов, которые уже поставлены
		   на мониторинг.
		   Обновляем статус в таблице zabbix_hosts в соответствии с IP адресом.

		4. Получат с сервера Zabbix список групп начинающихся с TT и формирует
		   массив для дальнейшей привязки хостов к регионам по номеру региона
		   из названия.

		4. В соответствии со значениями из таблицы zabbix_hosts добавляет,
		   удаляет или обновляет параметры хостов в Zabbix.

	*/

	if(!defined('Z_PROTECTED')) exit;

	// Список шаблонов соответствующих типу оборудования можно
	// вынести в конфиг БД или забирать из Zabbix по заданному
	// наименованию

	$zabbix_templates = array(
		131 => 12923
	);

	define('ZABBIX_TEMPLATE_FALLBACK', 12923);    // Этот шаблон будет подключен, если типу оборудования не найден соответствующий шаблон
	define('ZABBIX_TEMPLATE_FOR_BCC',  12924);    // Этот шаблон будет добавлен к основному, если к маршрутизатору подключен резервный комплект

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
		FROM c_mac AS m
		LEFT JOIN c_mac AS bc
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

			// by host name
			if($db->select_assoc_ex($result, rpv("
					SELECT
						zh.`pid`
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
				echo 'Host exist at Zabbix, but not founded in monitoring list: '.$host['host']."\n";
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
			else if(preg_match('/^'.ZABBIX_REGION_GROUP_PREFIX.'(\d+)$/i', $group['name'], $matches))
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

						$db->put(rpv("UPDATE @zabbix_hosts SET `flags` = (`flags` | {%ZHF_EXIST_IN_ZABBIX}), `host_id` = # WHERE `pid` = # LIMIT 1", $zabbix_result['hostids'][0], $row['id']));
					}
					break;

					// Update at Zabbix

					case (ZHF_MUST_BE_MONITORED | ZHF_EXIST_IN_ZABBIX):
					{
						echo 'Check before update at Zabbix: '.$row['name']."\n";

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
									echo 'Updating interface: '.$interface['interfaceid']."\n";
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
								// || $host['host'] !== $host_name  // hostname не проверяем, т.к. он является ключём
							)
							{
								$templates_to_clear = array_filter(
									$host['parentTemplates'],
									function($value) use($template_ids) {
										return !cb_template_cmp($value, $template_ids);
									}
								);

								echo 'Update at Zabbix: '.$row['name']."\n";
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
						echo 'Remove from Zabbix: '.$row['name']."\n";

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

		$zabbix_result = zabbix_api_request('user.logout', $auth_key, []);

		echo 'Added and updated: '.$added_and_updated."\n";
		echo 'Deleted: '.$removed."\n";
	}
