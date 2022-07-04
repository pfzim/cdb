<?php
	// Create new and close resolved tasks (RENAME)

	/**
		\file
		\brief Создание заявок на переименование ПК.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-rename:\n";

	$limit = TASKS_LIMIT_RENAME;

	global $g_comp_flags;

	// Close auto resolved tasks if PC was deleted from AD

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
			SELECT t.`id`, t.`operid`, t.`opernum`, c.`name`
			FROM @tasks AS t
			LEFT JOIN @computers AS c
				ON c.`id` = t.`pid`
			WHERE
				t.`tid` = {%TID_COMPUTERS}
				AND t.`type` = {%TT_PC_RENAME}
				AND (t.`flags` & {%TF_CLOSED}) = 0
				AND (
					c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})
					OR c.`name` REGEXP !
				)
		",
		CDB_REGEXP_VALID_NAMES
	)))
	{
		foreach($result as &$row)
		{
			$xml = helpdesk_api_request(helpdesk_build_request(
				TT_CLOSE,
				array(
					'operid'	=> $row['operid'],
					'opernum'	=> $row['opernum']
				)
			));

			if($xml !== FALSE)
			{
				echo $row['name'].' '.$row['opernum']."\r\n";
				$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | {%TF_CLOSED}) WHERE `id` = # LIMIT 1", $row['id']));
				$i++;
			}
		}
	}

	echo 'Closed that auto resolved: '.$i."\r\n";

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & {%TF_CLOSED}) = 0 AND t.`type` = {%TT_PC_RENAME}")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
			SELECT c.`id`, c.`name`, c.`dn`, c.`flags`
			FROM @computers AS c
			LEFT JOIN @tasks AS t
				ON
					t.`tid` = {%TID_COMPUTERS}
					AND t.`pid` = c.`id`
					AND t.`type` = {%TT_PC_RENAME}
					AND (t.`flags` & {%TF_CLOSED}) = 0
			WHERE
				(c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
				AND c.`delay_checks` < CURDATE()
				AND c.`name` NOT REGEXP {s0}
			GROUP BY c.`id`
			HAVING
				COUNT(t.`id`) = 0
		",
		CDB_REGEXP_VALID_NAMES
	)))
	{
		foreach($result as &$row)
		{
			if($i >= $limit)
			{
				echo 'Limit reached: '.$limit."\r\n";
				break;
			}

			$xml = helpdesk_api_request(helpdesk_build_request(
				TT_PC_RENAME,
				array(
					'host'			=> $row['name'],
					'sccm_lastsync'	=> $row['sccm_lastsync'],
					'flags'			=> flags_to_string(intval($row['flags']) & CF_MASK_EXIST, $g_comp_flags, ', ')
				)
			));

			if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
			{
				echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
				$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_COMPUTERS}, #, {%TT_PC_RENAME}, 0, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
				$i++;
			}
		}
	}

	echo 'Created: '.$i."\r\n";
