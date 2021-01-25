<?php
	// Create new and close resolved tasks (mailbox is unlimited)

	/**
		\file
		\brief Создание заявок по проблеме безлимитного почтового ящика.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-mbx-unlim:\n";

	$limit = TASKS_LIMIT_MBX;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT t.`id`, t.`operid`, t.`opernum`, p.`login`
		FROM @tasks AS t
		LEFT JOIN @persons AS p
			ON p.`id` = t.`pid`
		LEFT JOIN @properties_int AS j_quota
			ON j_quota.`tid` = 2
			AND j_quota.`pid` = t.`pid`
			AND j_quota.`oid` = #
		WHERE
			t.`tid` = 2
			AND(t.`flags` & (0x0001 | 0x0008)) = 0x0008
			AND (p.`flags` & (0x0001 | 0x0002 | 0x0004) OR (j_quota.`value` <> 0))
	", CDB_PROP_MAILBOX_QUOTA)))
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
					echo $row['login'].' '.$row['opernum']."\r\n";
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

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS m WHERE (m.`flags` & (0x0001 | 0x2000)) = 0x2000")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
		SELECT p.`id`, p.`login`, p.`dn`, p.`flags`
		FROM @persons AS p
		LEFT JOIN @tasks AS t
			ON t.`tid` = 2
			AND t.`pid` = p.`id`
			AND (t.`flags` & (0x0001 | 0x0008)) = 0x0008
		LEFT JOIN @properties_int AS j_quota
			ON j_quota.`tid` = 2
			AND j_quota.`pid` = p.`id`
			AND j_quota.`oid` = #
		WHERE
			(p.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
			AND j_quota.`value` = 0
		GROUP BY p.`id`
		HAVING (BIT_OR(t.`flags`) & 0x0008) = 0
	", CDB_PROP_MAILBOX_QUOTA)))
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
				.'&To=goo'
				.'&Host='.urlencode($row['login'])
				.'&Message='.urlencode(
					'Не настроена квота на почтовом ящике пользователя. Установите квоту.'
					."\nУЗ: ".$row['login']
					."\nКод работ: MBXQ"
					//."\n\n".WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Сброс-флага-разрещающего-установить-пустой-пароль.ashx'
				)
			);
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['login'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `flags`, `date`, `operid`, `opernum`) VALUES (2, #, 0x0008, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
		}
	}

	echo 'Created: '.$i."\r\n";
