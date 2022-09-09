<?php
	// Create new and close resolved tasks (IT Invent)

	/**
		\file
		\brief Создание заявок на корректировку местоположения оборудования в IT Invent.
		
		Локация оборудования (значения branch_no и loc_no) должна совпадать с локацией
		коммутатора, в который оно подключено.
		
		Коммутатор выбирается из таблицы по имени и здесь может быть небольшая проблема,
		т.к. имя устройства не является уникальным значением в БД. Если появится устройство
		с аналогичным именем, то возникнет путаница.
		
		Оборудование имеющее флаг Mobile (Ноутбуки) не проверяется на корректность
		местоположения.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-itinvent-move:\n";

	$limit = TASKS_LIMIT_ITINVENT_MOVE;

	global $g_mac_flags;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT * FROM (
			SELECT
				t.`id`,
				t.`operid`,
				t.`opernum`,
				m.`inv_no`,
				((COUNT(i.id) OVER (PARTITION BY m.`id`)) > 1) AS `m_duplicates`,
				((COUNT(di.id) OVER (PARTITION BY dm.`id`)) > 1) AS `dm_duplicates`
			FROM @tasks AS t
			LEFT JOIN @mac AS m
				ON m.`id` = t.`pid`
			LEFT JOIN c_mac_inv AS mi
				ON mi.`mac_id` = m.`id`
			LEFT JOIN c_inv AS i
				ON i.`id` = mi.`inv_id`
			LEFT JOIN @devices AS d
				ON
					d.`type` = {%DT_NETDEV}
					AND d.`id` = m.`pid`
			LEFT JOIN @mac AS dm
				ON
					dm.`name` = d.`name`
					AND (dm.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_FROM_NETDEV})) = ({%MF_FROM_NETDEV})    -- Not Temprary excluded, Not Premanently excluded, From netdev
			LEFT JOIN c_mac_inv AS dmi
				ON dmi.`mac_id` = dm.`id`
			LEFT JOIN c_inv AS di
				ON di.`id` = dmi.`inv_id`

			WHERE
				t.`tid` = {%TID_MAC}
				AND t.`type` = {%TT_INV_MOVE}
				AND (t.`flags` & {%TF_CLOSED}) = 0                                         -- Task status is Opened
				AND (
					(m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_FROM_NETDEV})) <> ({%MF_FROM_NETDEV})   -- Temprary excluded or Premanently excluded
					OR (
						di.`branch_no` IS NOT NULL
						AND di.`loc_no` IS NOT NULL
						AND i.`branch_no` = di.`branch_no`
						AND i.`loc_no` = di.`loc_no`
					)
				)
		) AS `sub_query`
		WHERE
			NOT `sub_query`.`m_duplicates`
			AND NOT  `sub_query`.`dm_duplicates`
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
				echo $row['inv_no'].' '.$row['opernum']."\r\n";
				$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | {%TF_CLOSED}) WHERE `id` = # LIMIT 1", $row['id']));
				$i++;
			}
		}
	}

	echo 'Closed: '.$i."\r\n";

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_INV_MOVE}")))
	{
		$i = intval($result[0][0]);
	}

	if($db->select_assoc_ex($result, rpv("
		SELECT
			m.id
			,m.`inv_no` AS m_inv_no
			,m.`name` AS m_name
			,m.`mac`
			,DATE_FORMAT(m.`date`, '%d.%m.%Y %H:%i:%s') AS `regtime`
			,m.`port`
			,m.`port_desc`
			,m.`vlan`
			,m.`flags`
			,m_status.`name` AS `m_status_name`
			,m_type.`name` AS `m_type_name`
			,m_model.`name` AS `m_model_name`
			,m_branch.`name` AS `m_branch_name`
			,m_location.`name` AS `m_loc_name`
			,d.`name` AS netdev
			,dm.`inv_no` AS d_inv_no
			,dm.`mac` AS d_mac
			,dm.`flags` AS d_flags
			,d_status.`name` AS `d_status_name`
			,d_type.`name` AS `d_type_name`
			,d_model.`name` AS `d_model_name`
			,d_branch.`name` AS `d_branch_name`
			,d_location.`name` AS `d_loc_name`
			,COUNT(i.id) OVER (PARTITION BY m.`id`) AS `duplicates`
		FROM @mac AS m
		LEFT JOIN c_mac_inv AS mi
			ON mi.`mac_id` = m.`id`
		LEFT JOIN c_inv AS i
			ON i.`id` = mi.`inv_id`
		LEFT JOIN @devices AS d
			ON
				d.`type` = {%DT_NETDEV}
				AND d.`id` = m.`pid`
		LEFT JOIN @mac AS dm
			ON
				dm.`name` = d.`name`
				AND (dm.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_FROM_NETDEV})) = ({%MF_FROM_NETDEV})    -- Not Temprary excluded, Not Premanently excluded, From netdev
		LEFT JOIN c_mac_inv AS dmi
			ON dmi.`mac_id` = dm.`id`
		LEFT JOIN c_inv AS di
			ON di.`id` = dmi.`inv_id`
		LEFT JOIN @names AS m_status
			ON
				m_status.`type` = {%NT_STATUSES}
				AND m_status.`pid` = 0
				AND m_status.`id` = i.`status`
		LEFT JOIN @names AS m_type
			ON
				m_type.`type` = {%NT_CI_TYPES}
				AND m_type.`pid` = 1				-- From IT Invent CI_TYPE
				AND m_type.`id` = i.`type_no`
		LEFT JOIN @names AS m_model
			ON
				m_model.`type` = {%NT_CI_MODELS}
				AND m_model.`pid` = 1				-- From IT Invent CI_TYPE
				AND m_model.`id` = i.`model_no`
		LEFT JOIN @names AS m_branch
			ON
				m_branch.`type` = {%NT_BRANCHES}
				AND m_branch.`pid` = 0
				AND m_branch.`id` = i.`branch_no`
		LEFT JOIN @names AS m_location
			ON
				m_location.`type` = {%NT_LOCATIONS}
				AND m_location.`pid` = 0
				AND m_location.`id` = i.`loc_no`
		LEFT JOIN @names AS d_status
			ON
				d_status.`type` = {%NT_STATUSES}
				AND d_status.`pid` = 0
				AND d_status.`id` = di.`status`
		LEFT JOIN @names AS d_type
			ON
				d_type.`type` = {%NT_CI_TYPES}
				AND d_type.`pid` = 1				-- From IT Invent CI_TYPE
				AND d_type.`id` = di.`type_no`
		LEFT JOIN @names AS d_model
			ON
				d_model.`type` = {%NT_CI_MODELS}
				AND d_model.`pid` = 1				-- From IT Invent CI_TYPE
				AND d_model.`id` = di.`model_no`
		LEFT JOIN @names AS d_branch
			ON
				d_branch.`type` = {%NT_BRANCHES}
				AND d_branch.`pid` = 0
				AND d_branch.`id` = di.`branch_no`
		LEFT JOIN @names AS d_location
			ON
				d_location.`type` = {%NT_LOCATIONS}
				AND d_location.`pid` = 0
				AND d_location.`id` = di.`loc_no`
		LEFT JOIN @tasks AS t
			ON
				t.`tid` = {%TID_MAC}
				AND t.`pid` = m.`id`
				AND t.`type` = {%TT_INV_MOVE}
				AND (t.`flags` & {%TF_CLOSED}) = 0
		WHERE
			(m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_FROM_NETDEV})) = ({%MF_FROM_NETDEV})                              -- Not Temprary excluded, Not Premanently excluded, From netdev
			AND (i.`flags` & ({%IF_EXIST_IN_ITINV} | {%IF_INV_ACTIVE} | {%IF_INV_MOBILEDEV})) = ({%IF_EXIST_IN_ITINV} | {%IF_INV_ACTIVE})    -- Exist in IT Invent, Active in IT Invent, Not Mobile
			AND (
				di.`id` IS NULL
				OR (di.`flags` & ({%IF_EXIST_IN_ITINV} | {%IF_INV_ACTIVE})) = ({%IF_EXIST_IN_ITINV} | {%IF_INV_ACTIVE})    -- Exist in IT Invent, Active in IT Invent
			)
			AND (
				di.`branch_no` IS NULL
				OR di.`loc_no` IS NULL
				OR (
					m.`branch_no` <> di.`branch_no`
					AND m.`loc_no` <> di.`loc_no`
				)
			)
		GROUP BY m.`id`, i.`id`, d.`id`, dm.`id`, di.`id`
		HAVING
			COUNT(t.`id`) = 0
	")))
	{
		foreach($result as &$row)
		{
			if($i >= $limit)
			{
				echo 'Limit reached: '.$limit."\r\n";
				break;
			}

			$xml = helpdesk_api_request(
				'Source=cdb'
				.'&Action=new'
				.'&Type=itinvmove'
				.'&To=bynetdev'
				.'&Host='.urlencode($row['netdev'])
				.'&Vlan='.urlencode($row['vlan'])
				.'&Message='.helpdesk_message(
					TT_INV_MOVE,
					array(
						'host'			=> $row['netdev'],
						'm_inv_no'		=> $row['m_inv_no'],
						'vlan'			=> $row['vlan'],
						'port'			=> $row['port'],
						'port_desc'		=> $row['port_desc'],
						'regtime'		=> $row['regtime'],
						'data_type'		=> ((intval($row['flags']) & MF_SERIAL_NUM) ? 'Серийный номер' : 'MAC адрес'),
						'mac_or_sn'		=> ((intval($row['flags']) & MF_SERIAL_NUM) ? 'Серийный номер коммутатора: '.$row['mac'] : 'MAC: '.implode(':', str_split($row['mac'], 2))),					
						'm_name'		=> $row['m_name'],
						'm_status_name'	=> $row['m_status_name'],
						'm_type_name'	=> $row['m_type_name'],
						'm_model_name'	=> $row['m_model_name'],
						'm_branch_name'	=> $row['m_branch_name'],
						'm_loc_name'	=> $row['m_loc_name'],
						'duplicates'	=> (intval($row['duplicates']) > 1) ? "Обнаружены дубликаты в ИТ Инвент!!!\n" : ''),
						'd_inv_no'		=> (empty($row['d_inv_no']) ? 'Отсутствует, проведите инвентаризацию коммутатора/маршрутизатора' : $row['d_inv_no']),
						'd_mac'			=> $row['d_mac'],
						'd_status_name'	=> $row['d_status_name'],
						'd_type_name'	=> $row['d_type_name'],
						'd_model_name'	=> $row['d_model_name'],
						'd_branch_name'	=> $row['d_branch_name'],
						'd_loc_name'	=> $row['d_loc_name'],
						'flags'			=> flags_to_string(intval($row['flags']), $g_mac_flags, ', '),
						'd_flags'		=> flags_to_string(intval($row['d_flags']), $g_mac_flags, ', ')
					)
				)
			);

			if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
			{
				echo $row['m_name'].' '.$xml->extAlert->query['number']."\r\n";
				$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_MAC}, #, {%TT_INV_MOVE}, 0, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
				$i++;
			}
		}
	}

	echo 'Created: '.$i."\r\n";

