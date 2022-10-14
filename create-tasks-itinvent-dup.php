<?php
	// Create new and close resolved tasks (IT Invent Duplicates)

	/**
		\file
		\brief Создание заявок на устранение дубликатов в IT Invent.
		
		Критерии создания заявок при выполненни обоих условий:
		  - В разных карточках оборудования ИТ Инвент вненсены одинаковые MAC адреса
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-itinvent-dup:\n";

	$limit = TASKS_LIMIT_ITINVENT_DUP;

	global $g_mac_flags;
	global $g_inv_flags;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT * FROM (
			SELECT t.`id`, t.`operid`, t.`opernum`, m.`mac`, m.`flags` AS `m_flags`, i.`flags` AS `i_flags`, ((COUNT(i.`id`) OVER (PARTITION BY m.`id`)) > 1) AS `duplicates`
			FROM @tasks AS t
			LEFT JOIN @mac AS m
				ON m.`id` = t.`pid`
			LEFT JOIN c_mac_inv AS mi
				ON mi.`mac_id` = m.`id`
			LEFT JOIN c_inv AS i
				ON i.`id` = mi.`inv_id`
			WHERE
				t.`tid` = {%TID_MAC}
				AND t.`type` = {%TT_INV_DUP}
				AND (t.`flags` & {%TF_CLOSED}) = 0                                                                             -- Task status is Opened
		) AS `sq`
		WHERE
			NOT `sq`.`duplicates`
			AND (
				sq.`m_flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED})                                                    -- Temprary excluded or Premanently excluded
				OR (sq.`i_flags` & ({%IF_EXIST_IN_ITINV} | {%IF_INV_ACTIVE})) = ({%IF_EXIST_IN_ITINV} | {%IF_INV_ACTIVE})     -- Exist AND active in IT Invent AND not have duplicates
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

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_INV_DUP}")))
	{
		$i = intval($result[0][0]);
	}

	if($db->select_assoc_ex($result, rpv("
		SELECT
			m.`id`,
			m.`mac`,
			m.`name`,
			m.`ip`,
			m.`flags`,
			COUNT(mi.`inv_id`) AS `dups`
		FROM c_mac_inv AS mi
		LEFT JOIN @mac AS m
			ON m.`id` = mi.`mac_id`
		LEFT JOIN @tasks AS t
			ON
				t.`tid` = {%TID_MAC}
				AND t.pid = m.`id`
				AND t.`type` = {%TT_INV_DUP}
				AND (t.flags & {%TF_CLOSED}) = 0
		GROUP BY mi.`mac_id`
		HAVING
			COUNT(t.`id`) = 0
			AND `dups` > 1
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

			$message = '';
			if($db->select_assoc_ex($invs, rpv("
					SELECT
						i.`id`
						,i.`inv_no`
						,m_status.`name` AS `m_status_name`
						,m_type.`name` AS `m_type_name`
						,m_model.`name` AS `m_model_name`
						,m_branch.`name` AS `m_branch_name`
						,m_location.`name` AS `m_loc_name`
						,i.`flags`
					FROM @mac_inv AS mi
					LEFT JOIN c_inv AS i
						ON i.`id` = mi.`inv_id`
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
					WHERE
						mi.`mac_id` = {d0}
					LIMIT 5
				",
				$row['id']
			)))
			{
				foreach($invs as &$inv)
				{
					$message .= 
						"\n\nИнвентарный номер: ".$inv['inv_no']
						."\nСтатус: ".$inv['m_status_name']
						."\nТип: ".$inv['m_type_name']
						."\nМодель: ".$inv['m_model_name']
						."\nОфис: ".$inv['m_branch_name']
						."\nМестоположение: ".$inv['m_loc_name']
						."\nFlags: ".flags_to_string(intval($inv['flags']), $g_inv_flags, ', ')
					;
				}
			}

			$xml = helpdesk_api_request(
				'Source=cdb'
				.'&Action=new'
				.'&Type=itinvent'
				.'&To=itinvent'
				.'&Host='.urlencode($row['mac'])
				.'&Message='.helpdesk_message(
					TT_INV_DUP,
					array(
						'data_type'		=> ((intval($row['flags']) & MF_SERIAL_NUM) ? 'Серийный номер' : 'MAC адрес'),
						'mac'			=> $row['mac'],
						'dups'			=> $row['dups'],
						'dns_name'		=> $row['name'],
						'ip'			=> $row['ip'],
						'flags'			=> flags_to_string(intval($row['flags']), $g_mac_flags, ', '),
						'data'			=> $message
					)
				)
			);

			if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
			{
				echo $row['mac'].' '.$xml->extAlert->query['number']."\r\n";
				$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_MAC}, #, {%TT_INV_DUP}, 0, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
				$i++;
			}
		}
	}

	echo 'Created: '.$i."\r\n";

