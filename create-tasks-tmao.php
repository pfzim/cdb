<?php
	// Create new and close resolved tasks (TMAO)

	/**
		\file
		\brief Создание заявок по проблеме неисправности антивируса на ПК.
		
		Проверка происходит по версии антивирусной базы. Версия базы не должна отставать
		от самой свежей на количество хранимое в параметре TMAO_PATTERN_VERSION_LAG.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-tmao:\n";

	$limit_gup = TASKS_LIMIT_TMAO_GUP;
	$limit_goo = TASKS_LIMIT_TMAO_GOO;

	global $g_comp_flags;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT t.`id`, t.`operid`, t.`opernum`, c.`name`
		FROM @tasks AS t
		LEFT JOIN @computers AS c
			ON c.`id` = t.`pid`
		WHERE
			t.`tid` = 1
			AND t.`type` = {%TT_TMAO}
			AND (t.`flags` & {%TF_CLOSED}) = 0
			AND (
				c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})
				OR c.`ao_script_ptn` >= ((SELECT MAX(`ao_script_ptn`) FROM @computers) - ".TMAO_PATTERN_VERSION_LAG.")
			)
	")))
	{
		foreach($result as &$row)
		{
			$answer = @file_get_contents(
				HELPDESK_URL.'/ExtAlert.aspx/'
				.'?Source=cdb'
				.'&Action=resolved'
				.'&Id='.urlencode($row['operid'])
				.'&Num='.urlencode($row['opernum'])
				.'&Message='.urlencode("Заявка более не актуальна. Закрыта автоматически")
			);

			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE)
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$row['opernum']."\r\n";
					$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | {%TF_CLOSED}) WHERE `id` = # LIMIT 1", $row['id']));
					$i++;
				}
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
			AND t.`type` = {%TT_TMAO}
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
			AND t.`type` = {%TT_TMAO}
			AND (t.`flags` & {%TF_CLOSED}) = 0
			AND c.`dn` NOT LIKE '%".LDAP_OU_SHOPS."'
	")))
	{
		$count_goo = intval($result[0][0]);
	}
	
	//if($db->select_assoc_ex($result, rpv("SELECT * FROM @computers WHERE (`flags` & (0x0001 | 0x0004 | 0x0200)) = 0 AND `name` regexp '^(([[:digit:]]{4}-[nNwW])|(OFF[Pp][Cc]-))[[:digit:]]+$' AND `ao_script_ptn` < ((SELECT MAX(`ao_script_ptn`) FROM @computers) - 2900)")))
	if($db->select_assoc_ex($result, rpv("
			SELECT c.`id`, c.`name`, c.`dn`, c.`ao_script_ptn`, c.`flags`
			FROM @computers AS c
			LEFT JOIN @tasks AS t
				ON
					t.`tid` = {%TID_COMPUTERS}
					AND t.pid = c.id
					AND t.`type` = {%TT_TMAO}
					AND (t.`flags` & {%TF_CLOSED}) = 0
			WHERE
				(c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
				AND c.`ao_script_ptn` < ((SELECT MAX(`ao_script_ptn`) FROM @computers) - {%TMAO_PATTERN_VERSION_LAG})
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

			//$answer = '<?xml version="1.0" encoding="utf-8"? ><root><extAlert><event ref="c7db7df4-e063-11e9-8115-00155d420f11" date="2019-09-26T16:44:46" number="001437825" rule="" person=""/><query ref="" date="" number=""/><comment/></extAlert></root>';

			$answer = @file_get_contents(
				HELPDESK_URL.'/ExtAlert.aspx/'
				.'?Source=cdb'
				.'&Action=new'
				.'&Type=tmao'
				.'&To=byname'
				.'&Host='.urlencode($row['name'])
				.'&Message='.urlencode(
					'Выявлена проблема с антивирусом Trend Micro Apex One.'
					."\n\nПК: ".$row['name']
					."\nВерсия антивирусной базы: "
					.$row['ao_script_ptn']
					."\nИсточник информации о ПК: ".flags_to_string(intval($row['flags']) & CF_MASK_EXIST, $g_comp_flags, ', ')
					."\nКод работ: AVCTRL\n\n".WIKI_URL."/Отдел%20ИТ%20Инфраструктуры.Restore_AO_agent.ashx"
				)
			);

			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_COMPUTERS}, #, {%TT_TMAO}, {%TF_TMAO}, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
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
	}

	echo 'Total opened GOO: '.$count_goo."\r\n";
	echo 'Total opened GUP: '.$count_gup."\r\n";
