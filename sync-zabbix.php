<?php
	// Retrieve information from Zabbix

	/**
		\file
		\brief Получение информации из Zabbix о хостах мониторинга
		
		1. Скрипт формирует список устройств, которые требуется поставить на 
		   мониторинг в Zabbix, в таблицу zabbix_hosts.
		   Устройства выбирабтся по "типу" указанному в ИТ Инвент.
		   Далее по физическому расположению определяется маршрутизатор
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

	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.zabbix.php');

	echo "\nsync-zabbix:\n";

	function cb_template_cmp($el, $payload)
	{
		return ($el['templateid'] === $payload);
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

	if($db->select_assoc_ex($result, rpv("
		SELECT 
			m.`id`,
			m.`name`,
			m.`mac`,
			m.`ip`,
			m.`inv_no`,
			DATE_FORMAT(m.`date`, '%d.%m.%Y %H:%i:%s') AS `last_update`
		FROM @mac AS m
		WHERE
			(m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_INV_ACTIVE})) = ({%MF_INV_ACTIVE})
			AND m.`port` = 'self'
			AND (
				m.`loc_no` IN (
					SELECT DISTINCT m2.`loc_no`
					FROM @mac AS m2
					WHERE
						(m2.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_INV_BCCDEV} | {%MF_INV_ACTIVE})) = ({%MF_INV_BCCDEV} | {%MF_INV_ACTIVE})
						AND m2.`loc_no` <> 0
				)
			)
		ORDER BY m.`name`
	")))
	{
		$db->put(rpv("UPDATE @zabbix_hosts SET `flags` = (`flags` & ~{%ZHF_MUST_BE_MONITORED}) WHERE (`flags` & {%ZHF_MUST_BE_MONITORED})"));

		foreach($result as &$row)
		{
			$db->put(rpv("INSERT INTO @zabbix_hosts (`pid`, `host_id`, `flags`) VALUES (#, 0, {%ZHF_MUST_BE_MONITORED}) ON DUPLICATE KEY UPDATE `flags` = (`flags` | {%ZHF_MUST_BE_MONITORED})", $row['id']));
		}
	}

	// Получаем список устройств, которые уже присутствуют в Zabbix, и помечаем их в таблице

	// start authentification
	$zabbix_result = zabbix_api_request('user.login', null, array('user' => ZABBIX_LOGIN, 'password' => ZABBIX_PASS));
	if($zabbix_result)
	{
		$auth_key = $zabbix_result;

		// get BCC list
		$zabbix_result = zabbix_api_request(
			'host.get',
			$auth_key, 
			array(
				'output' => ['hostid', 'host', 'status', 'proxy_hostid'],
				'selectInterfaces' => ['interfaceid', 'ip'],
				'selectGroups' => 'extend',
				'selectTriggers' => ['templateid', 'triggerid', 'description', 'status', 'priority']
			)
		);

		$db->put(rpv("UPDATE @zabbix_hosts SET `flags` = (`flags` & ~{%ZHF_EXIST_IN_ZABBIX}) WHERE (`flags` & {%ZHF_EXIST_IN_ZABBIX})"));

		foreach($zabbix_result as &$host)
		{
			$founded = FALSE;

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

			if(!$founded)
			{
				echo 'Host exist at Zabbix, but not founded in monitoring list: '.$host['host']."\n";
			}
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

			$template_id = ZABBIX_Host_Template;
			$group_id = isset($zabbix_groups[$region_num]) ? $zabbix_groups[$region_num] : 0;

			switch(intval($row['flags']) & (ZHF_MUST_BE_MONITORED | ZHF_EXIST_IN_ZABBIX))
			{
				case (ZHF_MUST_BE_MONITORED | ZHF_EXIST_IN_ZABBIX):  // update at Zabbix
				{
					echo 'Check before update at Zabbix: '.$row['name']."\n";
					
					$added_and_updated++;

					$zabbix_result = zabbix_api_request(
						'host.get',
						$auth_key, 
						array(
							'hostids'                   => $row['host_id'],
							'output'                    => ['hostid', 'host', 'status', 'proxy_hostid'],
							'selectGroups'              => ['groupid'],
							'selectParentTemplates'     => ['templateid'],
							//'selectInterfaces' => ['interfaceid', 'ip', 'main' => 1, 'type' => 2],
							//'selectTriggers'   => ['templateid', 'triggerid', 'description', 'status', 'priority']
						)
					);

					foreach($zabbix_result as &$host)
					{
						/*
						Нет смысла проверять IP, т.к. он является ключём для связки Snezhinka - Zabbix
						
						$zabbix_result = zabbix_api_request(
							'hostinterface.get',
							$auth_key,
							array(
								'hostids'       => $row['host_id'],
								'output'        => ['interfaceid', 'ip'],
								'filter'        => ['main' => 1, 'type' => 2]
							)
						);
						
						if($zabbix_result && $zabbix_result['ip'] !== $row['ip'])
						{
							zabbix_api_request(
								'hostinterface.update',
								$auth_key,
								array(
									'interfaceid'   => $zabbix_result['interfaceid'],
									'ip'            => $row['ip']
								)
							);
						}
						*/

						if($host['proxy_hostid'] !== ZABBIX_Host_Proxy
							|| !array_find($host['parentTemplates'], 'cb_template_cmp', $template_id)
							|| ($group_id && !array_find($host['groups'], 'cb_group_cmp', $group_id))
							|| $host['host'] !== $host_name
						)
						{
							$templates_to_clear = array_filter(
								$host['parentTemplates'],
								function($value) use($template_id) {
									return !cb_template_cmp($value, $template_id);
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
									'templates'       => array('templateid'   => $template_id),
									'templates_clear' => &$templates_to_clear,
									'proxy_hostid'    => ZABBIX_Host_Proxy
								)
							);
						}
					}
				}
				break;

				case ZHF_MUST_BE_MONITORED:                          // add to Zabbix
				{
					echo 'Add to Zabbix: '.$row['name']."\n";

					$added_and_updated++;

					$zabbix_result = zabbix_api_request(
						'host.create',
						$auth_key,
						array(
							'host'         => $host_name,
							'groups'       => array('groupid'      => $group_id),
							'templates'    => array('templateid'   => $template_id),
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
					
					$db->put(rpv("UPDATE @zabbix_hosts SET `flags` = (`flags` | {%ZHF_EXIST_IN_ZABBIX}) WHERE `host_id` = # LIMIT 1", $zabbix_result['hostids'][0]));
				}
				break;
				
				default:                                             // remove from Zabbix
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