<?php
	// Create new and close resolved tasks (updates have not been installed for too long)
	/**
		\file
		\brief Создание нарядов на исправление несответствия CI - Check - Regkey: Edge Version.
		
		Выполняется проверка информации загруженной из SCCM.
		Если ПК не соответствует базовому уровню, выставляется заявка в HelpDesk на устаранение проблемы.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-edge:\n";

	$limit_gup = TASKS_LIMIT_EDGE_GUP;
	$limit_goo = TASKS_LIMIT_EDGE_GOO;

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
		LEFT JOIN @properties_int AS j_up
			ON j_up.`tid` = {%TID_COMPUTERS}
			AND j_up.`pid` = t.`pid`
			AND j_up.`oid` = {%CDB_PROP_BASELINE_COMPLIANCE_EDGE}
		WHERE
			t.`tid` = {%TID_COMPUTERS}
			AND t.`type` = {%TT_EDGE_INSTALL}
			AND (t.`flags` & {%TF_CLOSED}) = 0
			AND (
				c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})
				OR j_up.`value` = 1
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

	$count_gup = 0;
	$count_goo = 0;

	if($db->select_ex($result, rpv("
		SELECT COUNT(*)
		FROM @tasks AS t
		LEFT JOIN @computers AS c ON c.id = t.pid
		WHERE
			t.`tid` = {%TID_COMPUTERS}
			AND t.`type` = {%TT_EDGE_INSTALL}
			AND (t.`flags` & {%TF_CLOSED}) = 0
			AND c.`dn` LIKE '%{%LDAP_OU_SHOPS}'
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
			AND t.`type` = {%TT_EDGE_INSTALL}
			AND (t.`flags` & {%TF_CLOSED}) = 0
			AND c.`dn` NOT LIKE '%{%LDAP_OU_SHOPS}'
	")))
	{
		$count_goo = intval($result[0][0]);
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
				AND t.`type` = {%TT_EDGE_INSTALL}
				AND (t.`flags` & {%TF_CLOSED}) = 0
			LEFT JOIN @properties_int AS j_up
				ON j_up.`tid` = {%TID_COMPUTERS}
				AND j_up.`pid` = c.`id`
				AND j_up.`oid` = {%CDB_PROP_BASELINE_COMPLIANCE_HOTFIX}
			WHERE
				(c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
				AND c.`delay_checks` < CURDATE()
				AND j_up.`value` <> 1
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

				$count_gup++;
				$to = 'gup';
			}
			else
			{
				if($count_goo >= $limit_goo)
				{
					continue;
				}

				$count_goo++;
				$to = 'goo';
			}

			$xml = helpdesk_api_request(
				'Source=cdb'
				.'&Action=new'
				.'&Type=edge'
				.'&To='.urlencode($to)
				.'&Host='.urlencode($row['name'])
				.'&Message='.helpdesk_message(
					TT_WIN_UPDATE,
					array(
						'host'			=> $row['name'],
						'flags'			=> flags_to_string(intval($row['flags']) & CF_MASK_EXIST, $g_comp_flags, ', ')
					)
				)
			);

			if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
			{
				echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
				$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_COMPUTERS}, #, {%TT_EDGE_INSTALL}, 0, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
				$i++;
			}
		}
	}

	echo 'Total opened GOO: '.$count_goo."\r\n";
	echo 'Total opened GUP: '.$count_gup."\r\n";
