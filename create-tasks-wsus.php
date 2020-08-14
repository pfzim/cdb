<?php
	// Create new and close resolved tasks (updates have not been installed for too long)

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-wsus:\n";

	$limit = 1;

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

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS m WHERE (m.`flags` & (0x0001 | 0x0040)) = 0x0040")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
		SELECT
			m.`id`,
			m.`name`,
			m.`dn`,
			m.`flags`
		FROM @computers AS m
		LEFT JOIN @tasks AS t
			ON
			t.`tid` = 1
			AND t.`pid` = m.`id`
			AND (t.`flags` & (0x0001 | 0x0040)) = 0x0040
		LEFT JOIN @properties_int AS j_up
			ON j_up.`tid` = 1
			AND j_up.`pid` = m.`id`
			AND j_up.`oid` = #
		WHERE
			(m.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
			AND j_up.`value` <> 1
			AND m.`name` NOT REGEXP '".CDB_REGEXP_SERVERS."'
		GROUP BY m.`id`
		HAVING (BIT_OR(t.`flags`) & 0x0040) = 0
	", CDB_PROP_BASELINE_COMPLIANCE_HOTFIX)))
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
				.'&Type=test'
				.'&To=byname'
				.'&Host='.urlencode($row['name'])
				.'&Message='.urlencode(
					'Необходимо устранить проблему установки обновлений ОС.'
					."\nПК: ".$row['name']
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

	echo 'Created: '.$i."\r\n";
