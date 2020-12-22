<?php
	// Create new and close resolved tasks (RENAME)

	/**
		\file
		\brief Создание заявок на переименование ПК.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-rename:\n";

	$limit = 20;

	global $g_comp_flags;

	// Close auto resolved tasks if PC was deleted from AD

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
			SELECT t.`id`, t.`operid`, t.`opernum`, c.`name`
			FROM @tasks AS t
			LEFT JOIN @computers AS c
				ON c.`id` = t.`pid`
			WHERE
				t.`tid` = 1
				AND (t.`flags` & (0x0400 | 0x0001)) = 0x0400
				AND (
					c.`flags` & (0x0001 | 0x0002 | 0x0004)
					OR c.`name` REGEXP !
				)
		",
		CDB_REGEXP_VALID_NAMES
	)))
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

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & (0x0001 | 0x0400)) = 0x0400")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
			SELECT c.`id`, c.`name`, c.`dn`, c.`flags`
			FROM @computers AS c
			LEFT JOIN @tasks AS t
				ON
					t.`tid` = 1
					AND t.pid = c.id
					AND (t.flags & (0x0001 | 0x0400)) = 0x0400
			WHERE
				(c.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
				AND c.`name` NOT REGEXP {s0}
			GROUP BY c.`id`
			HAVING (BIT_OR(t.`flags`) & 0x0400) = 0
		",
		CDB_REGEXP_VALID_NAMES
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
				HELPDESK_URL.'/ExtAlert.aspx/'.
				'?Source=cdb'.
				'&Action=new'.
				'&Type=rename'.
				'&To=hd'.
				'&Host='.urlencode($row['name']).
				'&Message='.urlencode(
					"Имя ПК не соответствует шаблону. Переименуйте ПК ".$row['name'].
					"\nDN: ".$row['dn'].
					"\nИсточник информации о ПК: ".flags_to_string(intval($row['flags']) & 0x00F0, $g_comp_flags, ', ').
					"\nКод работ: RNM01\n\n".
					WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Регламент-именования-ресурсов-в-каталоге-Active-Directory.ashx'
				)
			);

			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `flags`, `date`, `operid`, `opernum`) VALUES (1, #, 0x0400, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
		}
	}

	echo 'Created: '.$i."\r\n";
