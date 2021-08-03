<?php
	// Create new and close resolved tasks (empty password allowed)

	/**
		\file
		\brief Создание заявок по проблемам устаревшей ОС на ПК.
		
		Проверятеся атрибут Операционная система у объекта компьютер в AD
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
		LEFT JOIN @properties_str AS j_os
			ON j_os.`tid` = 1
			AND j_os.`pid` = t.`pid`
			AND j_os.`oid` = #
		WHERE
			t.`tid` = 1
			AND (t.`flags` & (0x0001 | 0x4000)) = 0x4000
			AND (
				c.`flags` & (0x0001 | 0x0002 | 0x0004)
				OR j_os.`value` IN ('Windows 10 Корпоративная 2016 с долгосрочным обслуживанием', 'Windows 10 Корпоративная', 'Windows 10 Корпоративная LTSC')
			)
	", CDB_PROP_OPERATINGSYSTEM)))
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

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS m WHERE (m.`flags` & (0x0001 | 0x4000)) = 0x4000")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
			SELECT
				m.`id`,
				m.`name`,
				m.`dn`,
				m.`flags`,
				j_os.`value` AS `os`
			FROM @computers AS m
			LEFT JOIN @tasks AS t
				ON
				t.`tid` = 1
				AND t.`pid` = m.`id`
				AND (t.`flags` & (0x0001 | 0x4000)) = 0x4000
			LEFT JOIN @properties_str AS j_os
				ON j_os.`tid` = 1
				AND j_os.`pid` = m.`id`
				AND j_os.`oid` = #
			WHERE
				(m.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
				AND j_os.`value` NOT IN ('Windows 10 Корпоративная 2016 с долгосрочным обслуживанием', 'Windows 10 Корпоративная', 'Windows 10 Корпоративная LTSC')
				AND m.`name` NOT REGEXP {s1}
			GROUP BY m.`id`
			HAVING (BIT_OR(t.`flags`) & 0x4000) = 0
		",
		CDB_PROP_OPERATINGSYSTEM,
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
			
			$answer = @file_get_contents(
				HELPDESK_URL.'/ExtAlert.aspx/'
				.'?Source=cdb'
				.'&Action=new'
				.'&Type=update'
				.'&To=byname'
				.'&Host='.urlencode($row['name'])
				.'&Message='.urlencode(
					'Версия операционной системы не соответсвует стандартам компании.'
					."\nПК: ".$row['name']
					."\nТекущая версия ОС: ".$row['os']
					."\nИсточник информации о ПК: ".flags_to_string(intval($row['flags']) & 0x00F0, $g_comp_flags, ', ')
					."\nКод работ: OSUP\n\n".WIKI_URL.'/Департамент%20ИТ%20Отдел%20ИТ%20поддержки%20Регионов%20(ТСА).Установка-ОС-с-использованием-SCCM.ashx'
				)
			);
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `flags`, `date`, `operid`, `opernum`) VALUES (1, #, 0x4000, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
		}
	}

	echo 'Created: '.$i."\r\n";
