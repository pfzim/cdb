<?php
	// Create new and close resolved tasks (empty password allowed)

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-epwd-persons:\n";

	global $g_comp_flags;

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS m WHERE (m.`flags` & (0x0001 | 0x2000)) = 0x2000")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`login`, m.`dn`, m.`flags`
		FROM @persons AS m
		LEFT JOIN @tasks AS j1
			ON j1.`pid` = m.`id`
			AND (j1.`flags` & (0x0001 | 0x2000)) = 0x2000
		LEFT JOIN @properties_int AS j2
			ON j2.`tid` = 2
			AND j2.`pid` = m.`id`
			AND j2.`oid` = #
		WHERE
			(m.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
			AND j2.`value` & 0x020
		GROUP BY m.`id`
		HAVING (BIT_OR(j1.`flags`) & 0x2000) = 0
	", CDB_PROP_USERACCOUNTCONTROL)))
	{
		foreach($result as &$row)
		{
			if($i >= 10)
			{
				echo "Limit reached: 1\r\n";
				break;
			}
			
			$answer = @file_get_contents(
				HELPDESK_URL.'/ExtAlert.aspx/'
				.'?Source=cdb'
				.'&Action=new'
				.'&Type=epwd'
				.'&To=sas'
				.'&Host='.urlencode($row['login'])
				.'&Message='.urlencode(
					'Требуется запретить установку пустого пароля у учётной записи.'
					."\nУЗ: ".$row['login']
					."\nКод работ: EPWD\n\n".WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Сброс-флага-разрещающего-установить-пустой-пароль.ashx'
				)
			);
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`pid`, `flags`, `date`, `operid`, `opernum`) VALUES (#, 0x2000, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
		}
	}

	echo 'Created: '.$i."\r\n";


	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`operid`, m.`opernum`, j1.`login`
		FROM @tasks AS m
		LEFT JOIN @persons AS j1
			ON j1.`id` = m.`pid`
		LEFT JOIN @properties_int AS j2
			ON `tid` = 2
			AND j2.`pid` = m.`pid`
			AND j2.`oid` = #
		WHERE
			(m.`flags` & (0x0001 | 0x2000)) = 0x2000
			AND (j1.`flags` & (0x0001 | 0x0002 | 0x0004) OR (j2.`value` & 0x020) = 0)
	", CDB_PROP_USERACCOUNTCONTROL)))
	{
		foreach($result as &$row)
		{
			$answer = @file_get_contents(
				HELPDESK_URL.'/ExtAlert.aspx/'
				.'?Source=cdb'
				.'&Action=resolved'
				.'&Type=epwd'
				.'&Id='.urlencode($row['operid'])
				.'&Num='.urlencode($row['opernum'])
				.'&Host='.urlencode($row['login'])
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

