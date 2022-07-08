<?php
	// Create new and close resolved tasks (IT Invent)

	/**
		\file
		\brief Создание заявок на занесение оборудования в IT Invent.
		
		Критерии создания заявок при выполненни обоих условий:
		  - Оборудование обнаружено активным в сети
		  - Оборудование не занесено в ИТ Инвент, либо занесено, но числится не в работе

		Если это коммутатор или маршрутизатор, то в заявке должен фигурировать Серийный номер.
		1.	Если в заявке видите MAC адрес вместо Серийного номера, значит он передаёт некорректные данные и с ним что-то не в порядке.
		2.	Серийный номер должен соответствовать номеру в карточке поля: Серийный номер.
		3.	Статус в карточке должен быть «Работает» или «Выдан пользователю для удаленной работы».

		Для другого оборудования:
		1.	MAC адрес должен соответствовать одному из номеров в карточке полей: MAC Адрес, MAC Адрес (1, 2, 3), MAC Адрес ТСД, Усилитель 3G: mac-адрес (2).
		2.	Статус в карточке должен быть «Работает» или «Выдан пользователю для удаленной работы».

		Если оборудование «засветилось» в сети и находится в статусе отличном от «Работает» и «Выдан пользователю для удаленной работы», то выясняете причину и принимаете соответствующие меры.
		
		Временно отключена проверка статуса в из карточки ИТ Инвент. Все статусы считаются валидными, главное присутствие оборудования в ИТ Инвент
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-itinvent:\n";

	$limit = TASKS_LIMIT_ITINVENT;

	global $g_mac_flags;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT t.`id`, t.`operid`, t.`opernum`, m.`mac`
		FROM @tasks AS t
		LEFT JOIN @mac AS m
			ON m.`id` = t.`pid`
		WHERE
			t.`tid` = {%TID_MAC}
			AND (
				t.`type` = {%TT_INV_ADD}
				OR t.`type` = {%TT_INV_ADD_DECOMIS}
			)
			AND (t.`flags` & {%TF_CLOSED}) = 0              -- Task status is Opened
			AND (
				m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED})                   -- Temprary excluded or Premanently excluded
				OR (m.`flags` & ({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE} | {%MF_DUPLICATE})) = ({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})     -- Exist AND active in IT Invent AND not have duplicates
				-- OR (m.`flags` & {%MF_EXIST_IN_ITINV})     					              -- Exist in IT Invent        -- Temporary do not check status
			)
	")))
	{
		foreach($result as &$row)
		{
			$xml = helpdesk_api_request(
				'Source=cdb'
				.'&Action=resolved'
				.'&Id='.urlencode($row['operid'])
				.'&Num='.urlencode($row['opernum'])
				.'&Message='.helpdesk_message(
					TT_CLOSE,
					array(
						'operid'	=> $row['operid'],
						'opernum'	=> $row['opernum']
					)
				)
			);

			if($xml !== FALSE)
			{
				echo $row['mac'].' '.$row['opernum']."\r\n";
				$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | {%TF_CLOSED}) WHERE `id` = # LIMIT 1", $row['id']));
				$i++;
			}
		}
	}

	echo 'Closed: '.$i."\r\n";

	// Open new tasks

/*
			AND (
				d.`name` LIKE 'RU-44-%'                                            -- Temporary filter by region 44
				OR
				d.`name` LIKE 'RU-33-%'                                            -- Temporary filter by region 33
				OR
				d.`name` LIKE 'RU-77-%'                                            -- Temporary filter by region 77
				OR
				d.`name` LIKE 'RU-13-%'                                            -- Temporary filter by region 13
				OR
				d.`name` LIKE 'RU-11-%'                                            -- Temporary filter by region 11
			)
*/

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND (t.`type` = {%TT_INV_ADD} OR t.`type` = {%TT_INV_ADD_DECOMIS})")))
	{
		$i = intval($result[0][0]);
	}

	if($db->select_assoc_ex($result, rpv("
		SELECT 
			m.`id`,
			d.`name` AS `netdev`,
			m.`name`,
			m.`mac`,
			m.`ip`,
			m.`port`,
			m.`vlan`,
			m.`inv_no`,
			m.`status`,
			status.`name` AS `status_name`,
			type.`name` AS `type_name`,
			DATE_FORMAT(m.`date`, '%d.%m.%Y %H:%i:%s') AS `regtime`,
			m.`flags`
		FROM @mac AS m
		LEFT JOIN @devices AS d
			ON d.`id` = m.`pid`
		LEFT JOIN @names AS status
			ON
				status.`type` = {%NT_STATUSES}
				AND status.`pid` = 0
				AND status.`id` = m.`status`
		LEFT JOIN @names AS type
			ON
				type.`type` = {%NT_CI_TYPES}
				AND type.`pid` = 1				-- From IT Invent CI_TYPE
				AND type.`id` = m.`type_no`
		LEFT JOIN @tasks AS t
			ON
				t.`tid` = {%TID_MAC}
				AND t.pid = m.id
				AND (
					t.`type` = {%TT_INV_ADD}
					OR t.`type` = {%TT_INV_ADD_DECOMIS}
				)
				AND (t.flags & {%TF_CLOSED}) = 0
		WHERE
			(m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_FROM_NETDEV})) = {%MF_FROM_NETDEV}
			AND (
				(m.`flags` & {%MF_EXIST_IN_ITINV}) = 0
				OR m.`status` = 7                          -- Decommissioned
			)
			-- AND (m.`flags` & ({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})) <> ({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})
			-- (m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_EXIST_IN_ITINV} | {%MF_FROM_NETDEV})) = {%MF_FROM_NETDEV}    -- Not Temprary excluded, Not Premanently excluded, imported from netdev, not exist in IT Invent or not Active
		GROUP BY m.`id`
		HAVING COUNT(t.`id`) = 0
		ORDER BY RAND()
	")))
	{
		foreach($result as &$row)
		{
			if($i >= $limit)
			{
				echo 'Limit reached: '.$limit."\r\n";
				break;
			}

			//echo 'MAC: '.$row['mac']."\n";

			$task_type = TT_INV_ADD;
			$task_code = 'itinvent';
			$task_to = 'bynetdev';

			// Если оборудование находится в статусе Списано, то создаётся отдельный тип заявки
			if((intval($row['flags']) & MF_EXIST_IN_ITINV) && (intval($row['status']) == 7))
			{
				$task_type = TT_INV_ADD_DECOMIS;
				$task_code = 'itinvstatus';
				$task_to = 'itinvent';
			}

			$xml = helpdesk_api_request(
				'Source=cdb'
				.'&Action=new'
				.'&Type='.urlencode($task_code)
				.'&To='.urlencode($task_to)
				.'&Host='.urlencode($row['netdev'])
				.'&Vlan='.urlencode($row['vlan'])
				.'&Message='.helpdesk_message(
					$task_type,
					array(
						'host'			=> $row['netdev'],
						'inv_no'		=> $row['inv_no'],
						'type_name'		=> $row['type_name'],
						'status_name'	=> $row['status_name'],
						'vlan'			=> $row['vlan'],
						'port'			=> $row['port'],
						'regtime'		=> $row['regtime'],
						'data_type'		=> ((intval($row['flags']) & MF_SERIAL_NUM) ? 'Серийный номер' : 'MAC адрес'),
						'mac_or_sn'		=> ((intval($row['flags']) & MF_SERIAL_NUM) ? 'Серийный номер коммутатора: '.$row['mac'] : 'MAC: '.implode(':', str_split($row['mac'], 2))),					
						'dns_name'		=> $row['name'],
						'ip'			=> $row['ip'],
						'flags'			=> flags_to_string(intval($row['flags']), $g_mac_flags, ', ')
					)
				)
			);

			if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
			{
				echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
				$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_MAC}, #, #, 0, NOW(), !, !)", $row['id'], $task_type, $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
				$i++;
			}
		}
	}

	echo 'Created: '.$i."\r\n";

