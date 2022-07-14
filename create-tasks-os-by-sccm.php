<?php
	// Create new and close resolved tasks (empty password allowed)

	/**
		\file
		\brief Создание заявок по проблемам устаревшей ОС на ПК по данных из SCCM.
		
		Проверятеся версия Операционной системы у объекта компьютер по данным
		из SCCM
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-os:\n";

	$limit = TASKS_LIMIT_OS;

	global $g_comp_flags;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			t.`id`,
			t.`operid`,
			t.`opernum`,
			c.`name`
		FROM @tasks AS t
		LEFT JOIN @computers AS c
			ON c.`id` = t.`pid`
		LEFT JOIN @properties_str AS j_ver
			ON j_ver.`tid` = {%TID_COMPUTERS}
			AND j_ver.`pid` = c.`id`
			AND j_ver.`oid` = {%CDB_PROP_OPERATINGSYSTEMVERSION_SCCM}
		WHERE
			t.`tid` = {%TID_COMPUTERS}
			AND t.`type` = {%TT_OS_REINSTALL}
			AND (t.`flags` & {%TF_CLOSED}) = 0
			AND (
				c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})
				OR j_ver.`value` = '{%CHECK_OPERATION_SYSTEM_VERSION_SCCM}'
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
				echo $row['name'].' '.$row['opernum']."\r\n";
				$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | {%TF_CLOSED}) WHERE `id` = # LIMIT 1", $row['id']));
				$i++;
			}
		}
	}

	echo 'Closed: '.$i."\r\n";

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_OS_REINSTALL}")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
			SELECT
				c.`id`,
				c.`name`,
				c.`dn`,
				c.`flags`,
				j_os.`value` AS `os`,
				j_ver.`value` AS `ver`
			FROM @computers AS c
			LEFT JOIN @tasks AS t
				ON
				t.`tid` = {%TID_COMPUTERS}
				AND t.`pid` = c.`id`
				AND t.`type` = {%TT_OS_REINSTALL}
				AND (t.`flags` & {%TF_CLOSED}) = 0
			LEFT JOIN @properties_str AS j_os
				ON j_os.`tid` = {%TID_COMPUTERS}
				AND j_os.`pid` = c.`id`
				AND j_os.`oid` = {%CDB_PROP_OPERATINGSYSTEM}
			LEFT JOIN @properties_str AS j_ver
				ON j_ver.`tid` = {%TID_COMPUTERS}
				AND j_ver.`pid` = c.`id`
				AND j_ver.`oid` = {%CDB_PROP_OPERATINGSYSTEMVERSION_SCCM}
			WHERE
				(c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
				AND c.`delay_checks` < CURDATE()
				AND j_ver.`value` <> '{%CHECK_OPERATION_SYSTEM_VERSION_SCCM}'
				AND c.`name` NOT REGEXP {s0}
			GROUP BY c.`id`
			HAVING
				COUNT(t.`id`) = 0
		",
		CDB_REGEXP_SERVERS
	)))
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
				.'&Type=update'
				.'&To=byname'
				.'&Host='.urlencode($row['name'])
				.'&Message='.helpdesk_message(
					TT_OS_REINSTALL,
					array(
						'host'			=> $row['name'],
						'os'			=> $row['os'],
						'os_version'	=> $row['ver'],
						'flags'			=> flags_to_string(intval($row['flags']) & CF_MASK_EXIST, $g_comp_flags, ', ')
					)
				)
			);

			if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
			{
				echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
				$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_COMPUTERS}, #, {%TT_OS_REINSTALL}, 0, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
				$i++;
			}
		}
	}

	echo 'Created: '.$i."\r\n";
