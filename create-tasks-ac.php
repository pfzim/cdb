<?php
	// Create new and close resolved tasks (TMAO AC)

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-ac\n";

	global $g_comp_flags;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT t.`id`, t.`operid`, t.`opernum`, c.`name`
		FROM @tasks AS t
		LEFT JOIN @ac_log AS al
			ON al.`id` = t.`pid`
		LEFT JOIN @computers AS c
			ON c.`id` = al.`pid`
		WHERE
			m.`tid` = 4
			AND (t.`flags` & (0x0001 | 0x0080)) = 0x0080
			AND c.`flags` & (0x0001 | 0x0002 | 0x0004)
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
					$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | 0x0001) WHERE `id` = # LIMIT 1", $row['id']));
					$i++;
				}
			}
			//break;
		}
	}

	echo 'Closed that auto resolved: '.$i."\r\n";

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS m WHERE (m.`flags` & (0x0001 | 0x0080)) = 0x0080")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
		SELECT
			al.`id`,
			c.`name`,
			c.`dn`,
			c.`flags`,
			al.`app_path`,
			al.`cmdln`
		FROM @ac_log AS al
		LEFT JOIN @tasks AS t ON
			t.`tid` = 4
			AND t.`pid` = al.`id`
			AND (t.`flags` & (0x0001 | 0x0080)) = 0x0080
		LEFT JOIN @computers AS c ON
			c.`id` = al.`pid`
		WHERE
			(c.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
			AND (al.`flags` & 0x0002) = 0
		GROUP BY al.`id`
		HAVING (BIT_OR(t.`flags`) & 0x0080) = 0
	")))
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
				.'&Type=ac'
				.'&To=byname'
				.'&Host='.urlencode($row['name'])
				.'&Message='.urlencode(
					'Обнаружена попытка запуска ПО из запрещённого расположения. Удалите или переустановите ПО в Program Files.'
					."\nПК: ".$row['name']
					."\nПуть к заблокированному файлу: ".$row['app_path']
					."\nПроцесс запускавший файл: ".$row['cmdln']
					."\nИсточник информации о ПК: ".flags_to_string(intval($row['flags']) & 0x00F0, $g_comp_flags, ', ')
					//."\nКод работ: AC001\n\n".WIKI_URL.'/Департамент%20ИТ.ashx'
				)
			);
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `flags`, `date`, `operid`, `opernum`) VALUES (4, #, 0x4000, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
		}
	}

	echo 'Created: '.$i."\r\n";
