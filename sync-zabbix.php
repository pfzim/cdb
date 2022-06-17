<?php
	// Retrieve information from Zabbix

	/**
		\file
		\brief Синхронизация с Zabbix

		Синхронизация с Zabbix

		1. Скрипт формирует таблицу zabbix_hosts со списоком устройств, которые
		   требуется поставить на мониторинг в Zabbix.
		   Маршрутизаторы и устройства ДКС выбираются по "типу" указанному в
		   ИТ Инвент. Далее по физическому местоположению определяется
		   маршрутизатор, к которому подключено это устройство.

		2. Получает с сервера Zabbix список хостов, которые уже поставлены
		   на мониторинг. Обновляет статус в таблице zabbix_hosts в
		   соответствии с именем хоста.

		4. Получат с сервера Zabbix список групп и формирует массив для
		   дальнейшей привязки хостов к регионам по номеру региона из названия.

		4. В соответствии со значениями из таблицы zabbix_hosts добавляет,
		   удаляет или обновляет параметры хостов в Zabbix.

		5. Состав локальной группы Zabbix LDAP Users синхронизируются группой
		   AD G_Zabbix_Access.

		Маршрутизаторы распределяются по группам с учётом региональной
		принадлежности.
		Группы с суффиксом ALL содержат маршрутизаторы всех регионов этого же
		типа.
		Маршрутизаторы, принадлежность которых не удалось определить, заносятся
		во все группы с суффиксом UNKNOWN и ALL.

		Несуществующие группы создаются автоматически.

		Шаблоны наименования групп:
		  [TT|TOF|RC|CO][код_региона]_[любой_комментарий]
		  [TT|TOF|RC|CO]_ALL
		  [TT|TOF|RC|CO]_UNKNOWN

		Шаблоны именования маршрутизаторов для определения принадлежности:
		  RU-\d{2}-B[o\d]\d-\w{3} - ТОФ
		  RU-\d{2}-\d{4}-\w{3}    - ТТ
		  RU-\d{2}-RC\d{1,2}      - RC
		  RU-\d{2}-Ao\d           - ЦО
		
		Добавлено создание и удаление пользователей в соответстии с составом
		группы AD G_Zabbix_Access. Пользователям назначается роль User role и
		добавляются в группу LDAP Users. Операция производится только с
		пользователями входящими в состав группы LDAP Users. Локальные
		пользователи не удаляются.
	*/

	if(!defined('Z_PROTECTED')) exit;

	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.zabbix.php');

	echo "\nsync-zabbix:\n";

	function cb_template_cmp($el, $payload)
	{
		return in_array($el['templateid'], $payload);
	}

	function cb_group_cmp($el, $payload)
	{
		return in_array($el['groupid'], $payload);
	}

	function cb_group_cmp_flipped($el, $payload)
	{
		return in_array($el, $payload['groupid']);
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

	function zabbix_create_group($auth_key, $group_name)
	{
		echo 'Creating new group: '.$group_name.PHP_EOL;

		$zabbix_result = zabbix_api_request(
			'hostgroup.create',
			$auth_key,
			array(
				'name'         => $group_name
			)
		);
		
		if(!isset($zabbix_result['groupids'][0]))
		{
			throw new Exception('ERROR: Zabbix API: Unexpected answer');
		}

		return $zabbix_result['groupids'][0];
	}

	function zabbix_get_or_create_group_id($auth_key, &$zabbix_groups, $zabbix_groups_objects_templates, $zabbix_groups_types_templates, $obj_code, $reg_code, $type_code)
	{
		// global $zabbix_groups_objects_templates;
		// global $zabbix_groups_types_templates;

		// echo 'Obj: '.$obj_code.' Reg: '.$reg_code.' Type: '.$type_code.PHP_EOL;
		// print_r($zabbix_groups_objects_templates);
		// print_r($zabbix_groups_types_templates);
		if(!isset($zabbix_groups[$obj_code][$reg_code][$type_code]))
		{
			if($reg_code)
			{
				$group_name = $zabbix_groups_objects_templates[$obj_code].'/'.sprintf('%02d', $reg_code).'/'.$zabbix_groups_types_templates[$type_code];
			}
			else
			{
				$group_name = $zabbix_groups_objects_templates[$obj_code].'/'.$zabbix_groups_types_templates[$type_code];
			}

			$zabbix_groups[$obj_code][$reg_code][$type_code] = zabbix_create_group($auth_key, $group_name);
		}

		return $zabbix_groups[$obj_code][$reg_code][$type_code];
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
			m.`name`,
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
					INSERT INTO @zabbix_hosts (`name`, `pid`, `host_id`, `flags`)
					VALUES ({s0}, {d1}, 0, ({%ZHF_MUST_BE_MONITORED} | {d2}))
					ON DUPLICATE KEY
					UPDATE `pid` = {d1}, `flags` = (`flags` | {%ZHF_MUST_BE_MONITORED} | {d2})
				",
				$row['name'],
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
		throw new Exception('ERROR: Zabbix API: Failed login');
	}

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
					zh.`name`,
					zh.`pid`,
					zh.`host_id`
				FROM @zabbix_hosts AS zh
				WHERE zh.`name` = {s0}
			",
			$host['host']
		)))
		{
			$founded = TRUE;
			$db->put(rpv("UPDATE @zabbix_hosts SET `flags` = (`flags` | {%ZHF_EXIST_IN_ZABBIX}), `host_id` = # WHERE `name` = !", $host['hostid'], $host['host']));
		}

		// // by host id - возможно исправит ситуацию, когда хост сменил имя
		// if($db->select_assoc_ex($result, rpv("
				// SELECT
					// zh.`pid`,
					// zh.`host_id`,
					// zh.`name`
				// FROM @zabbix_hosts AS zh
				// LEFT JOIN @mac AS m ON m.`id` = zh.`pid`
				// WHERE zh.`host_id` = {d0}
			// ",
			// $host['hostid']
		// )))
		// {
			// $founded = TRUE;
			// foreach($result as &$row)
			// {
				// if(strcasecmp($host['host'], $row['name']) !== 0)
				// {
					// echo 'Different host Name: '.$host['host'].' ('.$host['hostid'].')'.' DB: '.$row['name'].' ('.$row['host_id'].')'.PHP_EOL;
				// }
			// }
			// $db->put(rpv("UPDATE @zabbix_hosts SET `flags` = (`flags` | {%ZHF_EXIST_IN_ZABBIX}) WHERE `host_id` = #", $host['hostid']));
		// }
		
		// // by IP
		// foreach($host['interfaces'] as &$interface)
		// {
			// if($db->select_assoc_ex($result, rpv("
					// SELECT
						// zh.`pid`
					// FROM @zabbix_hosts AS zh
					// LEFT JOIN @mac AS m ON m.`id` = zh.`pid`
					// WHERE m.`ip` = {s0}
				// ",
				// $interface['ip']
			// )))
			// {
				// $founded = TRUE;
				// foreach($result as &$row)
				// {
					// $db->put(rpv("UPDATE @zabbix_hosts SET `flags` = (`flags` | {%ZHF_EXIST_IN_ZABBIX}), `host_id` = # WHERE `pid` = #", $host['hostid'], $row['pid']));
				// }
			// }
		// }

		if(!$founded)
		{
			echo 'Host exist at Zabbix, but not founded in monitoring list: '.$host['host'].' ('.$host['hostid'].')'.PHP_EOL;
		}
	}

	// Удаляем из таблицы хосты, которые не должны мониторится и уже отсутствуют в Zabbix

	$db->put(rpv("DELETE FROM @zabbix_hosts WHERE (`flags` & ({%ZHF_MUST_BE_MONITORED} | {%ZHF_EXIST_IN_ZABBIX})) = 0"));
	echo "Starting export to Zabbix...\n";

//*/

	// Получаем список доступных групп регионов

	$zabbix_result = zabbix_api_request(
		'hostgroup.get',
		$auth_key,
		array(
			'startSearch'           => TRUE,
			'searchByAny'           => TRUE,
			'search'                => array(
				'name'                  => array(
											ZABBIX_TT_GROUP_PREFIX,
											ZABBIX_TOF_GROUP_PREFIX,
											ZABBIX_RC_GROUP_PREFIX,
											ZABBIX_CO_GROUP_PREFIX
				)
			),
			'output'                => ['groupid', 'name']
		)
	);
	
	define('ZABBIX_OBJECT_CO',    1);
	define('ZABBIX_OBJECT_RC',    2);
	define('ZABBIX_OBJECT_TOF',   3);
	define('ZABBIX_OBJECT_TT',    4);

	define('ZABBIX_TYPE_UNKNOWN',              0);
	define('ZABBIX_TYPE_ROUTER',               1);
	define('ZABBIX_TYPE_ROUTER_BCC',           2);
	define('ZABBIX_TYPE_WORKSTATION_ADMIN',    3);
	define('ZABBIX_TYPE_WORKSTATION_KASSA',    4);
	define('ZABBIX_TYPE_ZABBIX_TYPE_SWITCH',   5);

	$zabbix_groups_objects_templates = array(
		ZABBIX_OBJECT_CO  => ZABBIX_CO_GROUP_PREFIX,
		ZABBIX_OBJECT_RC  => ZABBIX_RC_GROUP_PREFIX,
		ZABBIX_OBJECT_TOF => ZABBIX_TOF_GROUP_PREFIX,
		ZABBIX_OBJECT_TT  => ZABBIX_TT_GROUP_PREFIX
	);
	
	$zabbix_groups_types_templates = array(
		ZABBIX_TYPE_UNKNOWN              => 'UNKNOWN',
		ZABBIX_TYPE_ROUTER               => 'ROUTER',
		ZABBIX_TYPE_ROUTER_BCC           => 'ROUTER/BCC',
		ZABBIX_TYPE_WORKSTATION_ADMIN    => 'WORKSTATION/ADMIN',
		ZABBIX_TYPE_WORKSTATION_KASSA    => 'WORKSTATION/KASSA',
		ZABBIX_TYPE_SWITCH               => 'SWITCH'
	);
	
	$zabbix_groups = array(
		/*
			obj_code => [
				reg_code => [
						type => group_id // 'OBJ_NAME/REG_NAME/TYPE/NAME'
					]
			]
		*/
	);

	foreach($zabbix_result as &$group)
	{
		$reg_code = 0;
		if(preg_match('/^([^\/]+)\/(\d+)[^\/]*\/(.*)$/i', $group['name'], $matches))
		{
			foreach($zabbix_groups_objects_templates as $key => $value)
			{
				if($matches[1] === $value)
				{
					$obj_code = $key;
					
					$reg_code = intval($matches[2]);
					
					foreach($zabbix_groups_types_templates as $key => $value)
					{
						if($matches[3] === $value)
						{
							$type_code = $key;
							$zabbix_groups[$obj_code][$reg_code][$type_code] = $group['groupid'];
						}
					}
				}
			}
		}
		else if(preg_match('/^([^\/]+)\/(.*)$/i', $group['name'], $matches))
		{
			foreach($zabbix_groups_objects_templates as $key => $value)
			{
				if($matches[1] === $value)
				{
					$obj_code = $key;
					
					$reg_code = 0;
					
					foreach($zabbix_groups_types_templates as $key => $value)
					{
						if($matches[2] === $value)
						{
							$type_code = $key;
							$zabbix_groups[$obj_code][$reg_code][$type_code] = $group['groupid'];
						}
					}
				}
			}
		}
	}

	// Добавляем, обновляем и удаляем с мониторинга в Zabbix хосты

	$added_and_updated = 0;
	$removed = 0;

	if($db->select_assoc_ex($result, rpv("
		SELECT
			m.`id`,
			zh.`name`,
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
			
			if(intval($row['flags']) & ZHF_TEMPLATE_WITH_BCC)
			{
				$type_code = ZABBIX_TYPE_ROUTER_BCC;
			}
			else
			{
				$type_code = ZABBIX_TYPE_ROUTER;
			}
			
			$group_ids = array();

			// TT
			if(preg_match('/^RU-(\d{2})-\d{4}-\w{3}$/i', $host_name, $matches))
			{
				$group_ids[] = zabbix_get_or_create_group_id($auth_key, $zabbix_groups, $zabbix_groups_objects_templates, $zabbix_groups_types_templates, ZABBIX_OBJECT_TT, intval($matches[1]), $type_code);
				$group_ids[] = zabbix_get_or_create_group_id($auth_key, $zabbix_groups, $zabbix_groups_objects_templates, $zabbix_groups_types_templates, ZABBIX_OBJECT_TT, 0, $type_code);
			}
			// TOF
			else if(preg_match('/^RU-(\d{2})-B[o\d]\d-\w{3}$/i', $host_name, $matches))
			{
				$group_ids[] = zabbix_get_or_create_group_id($auth_key, $zabbix_groups, $zabbix_groups_objects_templates, $zabbix_groups_types_templates, ZABBIX_OBJECT_TOF, intval($matches[1]), $type_code);
				$group_ids[] = zabbix_get_or_create_group_id($auth_key, $zabbix_groups, $zabbix_groups_objects_templates, $zabbix_groups_types_templates, ZABBIX_OBJECT_TOF, 0, $type_code);
			}
			// RC
			else if(preg_match('/^RU-(\d{2})-RC\d{1,2}-/i', $host_name, $matches))
			{
				$group_ids[] = zabbix_get_or_create_group_id($auth_key, $zabbix_groups, $zabbix_groups_objects_templates, $zabbix_groups_types_templates, ZABBIX_OBJECT_RC, intval($matches[1]), $type_code);
				$group_ids[] = zabbix_get_or_create_group_id($auth_key, $zabbix_groups, $zabbix_groups_objects_templates, $zabbix_groups_types_templates, ZABBIX_OBJECT_RC, 0, $type_code);
			}
			// CO
			else if(preg_match('/^RU-(\d{2})-Ao\d-/i', $host_name, $matches))
			{
				$group_ids[] = zabbix_get_or_create_group_id($auth_key, $zabbix_groups, $zabbix_groups_objects_templates, $zabbix_groups_types_templates, ZABBIX_OBJECT_CO, intval($matches[1]), $type_code);
				$group_ids[] = zabbix_get_or_create_group_id($auth_key, $zabbix_groups, $zabbix_groups_objects_templates, $zabbix_groups_types_templates, ZABBIX_OBJECT_CO, 0, $type_code);
			}
			// unknown mask add to all groups
			else
			{
				$group_ids[] = zabbix_get_or_create_group_id($auth_key, $zabbix_groups, $zabbix_groups_objects_templates, $zabbix_groups_types_templates, ZABBIX_OBJECT_TT, 0, ZABBIX_TYPE_UNKNOWN);
				$group_ids[] = zabbix_get_or_create_group_id($auth_key, $zabbix_groups, $zabbix_groups_objects_templates, $zabbix_groups_types_templates, ZABBIX_OBJECT_TOF, 0, ZABBIX_TYPE_UNKNOWN);
				$group_ids[] = zabbix_get_or_create_group_id($auth_key, $zabbix_groups, $zabbix_groups_objects_templates, $zabbix_groups_types_templates, ZABBIX_OBJECT_RC, 0, ZABBIX_TYPE_UNKNOWN);
				$group_ids[] = zabbix_get_or_create_group_id($auth_key, $zabbix_groups, $zabbix_groups_objects_templates, $zabbix_groups_types_templates, ZABBIX_OBJECT_CO, 0, ZABBIX_TYPE_UNKNOWN);
			}

			// Выбираем шаблон по номеру модели оборудования
			
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
						echo '  Error: Empty IP or name'."\n";
						break;
					}

					$added_and_updated++;

					$zabbix_result = zabbix_api_request(
						'host.create',
						$auth_key,
						array(
							'host'         => $host_name,
							'groups'       => array_reduce($group_ids, function($result, $value) { $result[] = array('groupid'   => $value); return $result; }, array()),
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
							|| count(array_diff($template_ids, array_reduce($host['parentTemplates'], function($result, $value) { $result[] = $value['templateid']; return $result; }, array())))
							|| count(array_diff($group_ids, array_reduce($host['groups'], function($result, $value) { $result[] = $value['groupid']; return $result; }, array())))
							|| $host['host'] !== $host_name
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
									'groups'          => array_reduce($group_ids, function($result, $value) { $result[] = array('groupid'   => $value); return $result; }, array()),
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
			//break;
		}
	}

	echo 'Added and updated: '.$added_and_updated."\n";
	echo 'Deleted: '.$removed."\n";
	
	//return;

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
