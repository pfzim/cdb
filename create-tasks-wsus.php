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

	$limit_gup = 1;
	$limit_goo = 10;

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
			ON j_up.`tid` = 1
			AND j_up.`pid` = t.`pid`
			AND j_up.`oid` = #
		WHERE
			t.`tid` = 1
			AND (t.`flags` & (0x0001 | 0x0040)) = 0x0040
			AND (
				c.`flags` & (0x0001 | 0x0002 | 0x0004)
				OR j_up.`value` = 1
			)
	", CDB_PROP_BASELINE_COMPLIANCE_HOTFIX)))
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
					$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | 0x0001) WHERE `id` = # LIMIT 1", $row['id']));
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
			t.`tid` = 1
			AND (t.`flags` & (0x0001 | 0x0040)) = 0x0040
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
			t.`tid` = 1
			AND (t.`flags` & (0x0001 | 0x0040)) = 0x0040
			AND c.`dn` NOT LIKE '%".LDAP_OU_SHOPS."'
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
				t.`tid` = 1
				AND t.`pid` = c.`id`
				AND (t.`flags` & (0x0001 | 0x0040)) = 0x0040
			LEFT JOIN @properties_int AS j_up
				ON j_up.`tid` = 1
				AND j_up.`pid` = c.`id`
				AND j_up.`oid` = #
			LEFT JOIN @properties_str AS j_os
				ON j_os.`tid` = 1
				AND j_os.`pid` = c.`id`
				AND j_os.`oid` = #
			LEFT JOIN @properties_str AS j_ver
				ON j_ver.`tid` = 1
				AND j_ver.`pid` = c.`id`
				AND j_ver.`oid` = #
			WHERE
				(c.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
				AND j_up.`value` <> 1
				AND c.`name` NOT REGEXP {s3}
			GROUP BY c.`id`
			HAVING (BIT_OR(t.`flags`) & 0x0040) = 0
		",
		CDB_PROP_BASELINE_COMPLIANCE_HOTFIX,
		CDB_PROP_OPERATINGSYSTEM,
		CDB_PROP_OPERATINGSYSTEMVERSION,
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
					."\nИсточник информации о ПК: ".flags_to_string(intval($row['flags']) & 0x00F0, $g_comp_flags, ', ')
					."\nКод работ: USYS\n\n".WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Инструкция-Устранение-ошибок-установки-обновлений.ashx'
				)
			);

			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `flags`, `date`, `operid`, `opernum`) VALUES (1, #, 0x0040, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
		}
	}

	echo 'Total opened GOO: '.$count_goo."\r\n";
	echo 'Total opened GUP: '.$count_gup."\r\n";
