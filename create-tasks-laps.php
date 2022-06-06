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
		SELECT t.`id`, t.`operid`, t.`opernum`, c.`name`
		FROM @tasks AS t
		LEFT JOIN @computers AS c
			ON c.`id` = t.`pid`
		WHERE
			t.`tid` = {%TID_COMPUTERS}
			AND t.`type` = {%TT_LAPS}
			AND (t.`flags` & {%TF_CLOSED}) = 0
			AND (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED}) OR c.`laps_exp` >= DATE_SUB(NOW(), INTERVAL {%LAPS_EXPIRE_DAYS} DAY))
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

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & {%TF_CLOSED}) = 0 AND t.`type` = {%TT_LAPS}")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
		SELECT c.`id`, c.`name`, c.`dn`, c.`laps_exp`, c.`flags`
		FROM @computers AS c
		LEFT JOIN @tasks AS t
			ON
				t.`tid` = {%TID_COMPUTERS}
				AND t.pid = c.id
				AND t.`type` = {%TT_LAPS}
				AND (t.flags & {%TF_CLOSED}) = 0
		WHERE
			(c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
			AND c.`delay_checks` < CURDATE()
			-- AND c.`dn` LIKE '%{%LDAP_OU_COMPANY}'
			AND c.`laps_exp` < DATE_SUB(NOW(), INTERVAL {%LAPS_EXPIRE_DAYS} DAY)
		GROUP BY c.`id`
		HAVING
			COUNT(t.`id`) = 0
	")))
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
					."\nИсточник информации о ПК: ".flags_to_string(intval($row['flags']) & CF_MASK_EXIST, $g_comp_flags, ', ')
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
					$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_COMPUTERS}, #, {%TT_LAPS}, 0, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
		}
	}

	echo 'Created: '.$i."\r\n";
