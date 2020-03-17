<?php
	// Create new and close resolved tasks (SCCM)

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-sccm:\n";

	global $g_comp_flags;

	// Open new tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`name`, m.`dn`, m.`sccm_lastsync`, m.`flags`
		FROM @computers AS m
		LEFT JOIN @tasks AS j1 ON j1.pid = m.id AND (j1.flags & (0x0001 | 0x1000)) = 0x1000
		WHERE
			(m.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
			AND m.`sccm_lastsync` < DATE_SUB(NOW(), INTERVAL 1 MONTH)
			AND `name` NOT REGEXP '".CDB_REGEXP_SERVERS."'
		GROUP BY m.`id`
		HAVING (BIT_OR(j1.`flags`) & 0x1000) = 0
	")))
	{
		foreach($result as &$row)
		{
			$answer = @file_get_contents(
				HELPDESK_URL.'/ExtAlert.aspx/'
				.'?Source=cdb'
				.'&Action=new'
				.'&Type=sccm'
				.'&To=byname'
				.'&Host='.urlencode($row['name'])
				.'&Message='.urlencode(
					'Выявлена проблема с агентом SCCM'
					."\nПК: ".$row['name']
					."\nПоследняя синхронизация с сервером: ".$row['sccm_lastsync']
					."\nИсточник информации о ПК: ".flags_to_string(intval($row['flags']) & 0x00F0, $g_comp_flags, ', ')
					."\nКод работ: SC001\n\n".WIKI_URL.'/Группа%20удалённой%20поддержки.SC001-отсутствует-клиент-SCCM.ashx'
				)
			);

			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`pid`, `flags`, `date`, `operid`, `opernum`) VALUES (#, 0x1000, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
			if($i >= 10) break;
		}
	}

	echo 'Created: '.$i."\r\n";

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`operid`, m.`opernum`, j1.`name`
		FROM @tasks AS m
		LEFT JOIN @computers AS j1 ON j1.`id` = m.`pid`
		WHERE
			(m.`flags` & (0x0001 | 0x1000)) = 0x1000
			AND (j1.`flags` & (0x0001 | 0x0002 | 0x0004) OR m.`sccm_lastsync` >= DATE_SUB(NOW(), INTERVAL 1 MONTH))
	")))
	{
		foreach($result as &$row)
		{
			$answer = @file_get_contents(
				HELPDESK_URL.'/ExtAlert.aspx/'
				.'?Source=cdb'
				.'&Action=resolved'
				.'&Type=tmee'
				.'&Id='.urlencode($row['operid'])
				.'&Num='.urlencode($row['opernum'])
				.'&Host='.urlencode($row['name'])
				.'&Message='.urlencode("Заявка более не актуальна. Закрыта автоматически")
			);

			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE)
				{
					echo $row['name'].' '.$row['opernum']."\r\n";
					$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | 0x0001) WHERE `id` = # LIMIT 1", $row['id']));
					$i++;
				}
			}
		}
	}

	echo 'Closed: '.$i."\r\n";
