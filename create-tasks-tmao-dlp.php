<?php
	// Create new and close resolved tasks (TMAO-DLP)

	/**
		\file
		\brief Создание заявок по проблеме с модулем ВДЗ антивируса на ПК.
		
		Проверка статуса DLP. Заявки создаются, если DLP статус не равен Running.
		
		0 - Not installed
		1 - Running
		2 - Stopped
		3 - Cannot install (Data Loss Prevention already exists)
		4 - Requires restart
		
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-tmao-dlp:\n";

	$limit_gup = TASKS_LIMIT_TMAO_DLP_GUP;
	$limit_goo = TASKS_LIMIT_TMAO_DLP_GOO;

	global $g_comp_flags;
	global $g_dlp_statuses;

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
		LEFT JOIN @properties_int AS dlp_status
			ON dlp_status.`tid` = {%TID_COMPUTERS}
			AND dlp_status.`pid` = t.`pid`
			AND dlp_status.`oid` = {%CDB_PROP_TMAO_DLP_STATUS}
		WHERE
			t.`tid` = {%TID_COMPUTERS}
			AND t.`type` = {%TT_TMAO_DLP}
			AND (t.`flags` & {%TF_CLOSED}) = 0
			AND (
				c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})
				OR dlp_status.`value` = 1
			)
	")))
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
				//echo $answer."\r\n";
				echo $row['name'].' '.$row['opernum']."\r\n";
				$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | {%TF_CLOSED}) WHERE `id` = # LIMIT 1", $row['id']));
				$i++;
			}
			//break;
		}
	}

	echo 'Closed that auto resolved: '.$i."\r\n";

	// Open new tasks

	$count_gup = 0;
	$count_goo = 0;

	/*
	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & (0x0001 | 0x0200)) = 0x0200")))
	{
		$i = intval($result[0][0]);
	}
	*/

	if($db->select_ex($result, rpv("
		SELECT COUNT(*)
		FROM @tasks AS t
		LEFT JOIN @computers AS c ON c.id = t.pid
		WHERE
			t.`tid` = {%TID_COMPUTERS}
			AND t.`type` = {%TT_TMAO_DLP}
			AND (t.`flags` & {%TF_CLOSED}) = 0
			AND c.`dn` LIKE '%".LDAP_OU_SHOPS."'
	")))
	{
		$count_gup = intval($result[0][0]);
	}
	
	if($db->select_ex($result, rpv("
		SELECT COUNT(*)
		FROM @tasks AS t
		LEFT JOIN @computers AS c ON c.id = t.pid
		WHERE
			t.`tid` = {%TID_COMPUTERS}
			AND t.`type` = {%TT_TMAO_DLP}
			AND (t.`flags` & {%TF_CLOSED}) = 0
			AND c.`dn` NOT LIKE '%".LDAP_OU_SHOPS."'
	")))
	{
		$count_goo = intval($result[0][0]);
	}
	
	//if($db->select_assoc_ex($result, rpv("SELECT * FROM @computers WHERE (`flags` & (0x0001 | 0x0004 | 0x0200)) = 0 AND `name` regexp '^(([[:digit:]]{4}-[nNwW])|(OFF[Pp][Cc]-))[[:digit:]]+$' AND `ao_script_ptn` < ((SELECT MAX(`ao_script_ptn`) FROM @computers) - 2900)")))
	if($db->select_assoc_ex($result, rpv("
			SELECT
				c.`id`,
				c.`name`,
				c.`dn`,
				c.`flags`,
				dlp_status.`value` AS `dlp_status`
			FROM @computers AS c
			LEFT JOIN @tasks AS t
				ON
				t.`tid` = {%TID_COMPUTERS}
				AND t.`pid` = c.`id`
				AND t.`type` = {%TT_OS_REINSTALL}
				AND (t.`flags` & {%TF_CLOSED}) = 0
			LEFT JOIN @properties_int AS dlp_status
				ON dlp_status.`tid` = {%TID_COMPUTERS}
				AND dlp_status.`pid` = c.`id`
				AND dlp_status.`oid` = {%CDB_PROP_TMAO_DLP_STATUS}
			WHERE
				(c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
				AND c.`delay_checks` < CURDATE()
				AND dlp_status.`value` <> 1
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
			if(substr($row['dn'], -strlen(LDAP_OU_SHOPS)) === LDAP_OU_SHOPS)
			{
				if($count_gup >= $limit_gup)
				{
					continue;
				}
			}
			else
			{
				if($count_goo >= $limit_goo)
				{
					continue;
				}
			}

			$xml = helpdesk_api_request(helpdesk_build_request(
				TT_TMAO_DLP,
				array(
					'host'			=> $row['name'],
					'dlp_status'	=> code_to_string($g_dlp_statuses, intval($row['dlp_status'])),
					'flags'			=> flags_to_string(intval($row['flags']) & CF_MASK_EXIST, $g_comp_flags, ', ')
				)
			));

			if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
			{
				//echo $answer."\r\n";
				echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
				$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_COMPUTERS}, #, {%TT_TMAO_DLP}, 0, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
				if(substr($row['dn'], -strlen(LDAP_OU_SHOPS)) === LDAP_OU_SHOPS)
				{
					$count_gup++;
				}
				else
				{
					$count_goo++;
				}
			}
		}
	}

	echo 'Total opened GOO: '.$count_goo."\r\n";
	echo 'Total opened GUP: '.$count_gup."\r\n";
