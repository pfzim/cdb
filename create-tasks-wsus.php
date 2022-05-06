<?php
	// Create new and close resolved tasks (updates have not been installed for too long)
	/**
		\file
		\brief Создание нарядов на исправление несответствию базовому уровню установки обновлений на ПК.
		
		Выполняется проверка информации загруженной из SCCM на соответствие базовому уровню установки обновлений.
		Если ПК не соответствует базовому уровню, выставляется заявка в HelpDesk на устаранение проблемы.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-wsus:\n";

	$limit_gup = TASKS_LIMIT_WSUS_GUP;
	$limit_goo = TASKS_LIMIT_WSUS_GOO;

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
			AND j_up.`oid` = {%CDB_PROP_BASELINE_COMPLIANCE_HOTFIX}
		WHERE
			t.`tid` = {%TID_COMPUTERS}
			AND t.`type` = {%TT_WIN_UPDATE}
			AND (t.`flags` & {%TF_CLOSED}) = 0
			AND (
				c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})
				OR j_up.`value` = 1
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
			AND t.`type` = {%TT_WIN_UPDATE}
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
			AND t.`type` = {%TT_WIN_UPDATE}
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
				AND t.`type` = {%TT_WIN_UPDATE}
				AND (t.`flags` & {%TF_CLOSED}) = 0
			LEFT JOIN @properties_int AS j_up
				ON j_up.`tid` = {%TID_COMPUTERS}
				AND j_up.`pid` = c.`id`
				AND j_up.`oid` = {%CDB_PROP_BASELINE_COMPLIANCE_HOTFIX}
			LEFT JOIN @properties_str AS j_os
				ON j_os.`tid` = {%TID_COMPUTERS}
				AND j_os.`pid` = c.`id`
				AND j_os.`oid` = {%CDB_PROP_OPERATINGSYSTEM}
			LEFT JOIN @properties_str AS j_ver
				ON j_ver.`tid` = {%TID_COMPUTERS}
				AND j_ver.`pid` = c.`id`
				AND j_ver.`oid` = {%CDB_PROP_OPERATINGSYSTEMVERSION}
			WHERE
				(c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
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

			$answer = @file_get_contents(
				HELPDESK_URL.'/ExtAlert.aspx/'
				.'?Source=cdb'
				.'&Action=new'
				.'&Type=update'
				.'&To='.$to
				.'&Host='.urlencode($row['name'])
				.'&Message='.urlencode(
					'Необходимо устранить проблему установки обновлений ОС.'
					."\nПК: ".$row['name']
					."\nОперационная система: ".$row['os'].' ('.$row['ver'].')'
					."\nИсточник информации о ПК: ".flags_to_string(intval($row['flags']) & CF_MASK_EXIST, $g_comp_flags, ', ')
					."\nКод работ: OSUP\n\n".WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Инструкция-Устранение-ошибок-установки-обновлений.ashx'
				)
			);

			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_COMPUTERS}, #, {%TT_WIN_UPDATE}, {%TF_WIN_UPDATE}, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
		}
	}

	echo 'Total opened GOO: '.$count_goo."\r\n";
	echo 'Total opened GUP: '.$count_gup."\r\n";
