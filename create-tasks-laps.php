<?php
	// Create new and close resolved tasks (RENAME)

	/**
		\file
		\brief Создание заявок по проблемам неисправности агента LAPS на ПК.
		
		Заявки выставляются если от даты хранящейся в атрибуте ms-mcs-admpwdexpirationtime прошло
		более LAPS_EXPIRE_DAYS дней
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-laps:\n";

	$limit = TASKS_LIMIT_LAPS;
	
	global $g_comp_flags;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`operid`, m.`opernum`, j1.`name`
		FROM @tasks AS m
		LEFT JOIN @computers AS j1
			ON j1.`id` = m.`pid`
		WHERE
			m.`tid` = 1
			AND (m.`flags` & (0x0001 | 0x0800)) = 0x0800
			AND (j1.`flags` & (0x0001 | 0x0002 | 0x0004) OR j1.`laps_exp` >= DATE_SUB(NOW(), INTERVAL # DAY))
	", LAPS_EXPIRE_DAYS)))
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

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS m WHERE (m.`flags` & (0x0001 | 0x0800)) = 0x0800")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`name`, m.`dn`, m.`laps_exp`, m.`flags`
		FROM @computers AS m
		LEFT JOIN @tasks AS j1
			ON
				j1.`tid` = 1
				AND j1.pid = m.id
				AND (j1.flags & (0x0001 | 0x0800)) = 0x0800
		WHERE
			(m.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
			-- AND m.`dn` LIKE '%".LDAP_OU_COMPANY."'
			AND m.`laps_exp` < DATE_SUB(NOW(), INTERVAL # DAY)
		GROUP BY m.`id`
		HAVING (BIT_OR(j1.`flags`) & 0x0800) = 0
	", LAPS_EXPIRE_DAYS)))
	{
		foreach($result as &$row)
		{
			if($i >= $limit)
			{
				echo 'Limit reached: '.$limit."\r\n";
				break;
			}
			
			/*
			if(preg_match('/'.LDAP_OU_SHOPS.'$/i', $row['dn']))
			{
				$direction = 'gup';
			}
			else
			{
				$direction = 'goo';
			}
			*/

			$answer = @file_get_contents(
				HELPDESK_URL.'/ExtAlert.aspx/'
				.'?Source=cdb'
				.'&Action=new'
				.'&Type=laps'
				.'&To=byname'
				.'&Host='.urlencode($row['name'])
				.'&Message='.urlencode(
					'Не установлен либо не работает LAPS.'
					."\nПК: ".$row['name']
					."\nПоследнее обновление LAPS: ".$row['laps_exp']
					."\nИсточник информации о ПК: ".flags_to_string(intval($row['flags']) & 0x00F0, $g_comp_flags, ', ')
					."\nКод работ: LPS01\n\n".WIKI_URL.'/Сервисы.laps%20troubleshooting.ashx'
				)
			);
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `flags`, `date`, `operid`, `opernum`) VALUES (1, #, 0x0800, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
		}
	}

	echo 'Created: '.$i."\r\n";
