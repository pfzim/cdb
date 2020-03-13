<?php
	// Create new and close resolved tasks (RENAME)

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-rename:\n";

	global $g_comp_flags;

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS m WHERE (m.`flags` & (0x0001 | 0x0400)) = 0x0400")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`name`, m.`dn`, m.`flags`
		FROM @computers AS m
		LEFT JOIN @tasks AS j1 ON j1.pid = m.id AND (j1.flags & (0x0001 | 0x0400)) = 0x0400
		WHERE
			(m.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
			AND m.`name` NOT REGEXP '".CDB_REGEXP_VALID_NAMES."'
		GROUP BY m.`id`
		HAVING (BIT_OR(j1.`flags`) & 0x0400) = 0
	")))
	{
		foreach($result as &$row)
		{
			if($i >= 10)
			{
				echo "Limit reached: 10\r\n";
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
					$db->put(rpv("INSERT INTO @tasks (`pid`, `flags`, `date`, `operid`, `opernum`) VALUES (#, 0x0400, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
		}
	}

	echo 'Created: '.$i."\r\n";

	// Close auto resolved tasks if PC was deleted from AD

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`operid`, m.`opernum`, j1.`name`
		FROM @tasks AS m
		LEFT JOIN @computers AS j1 ON j1.`id` = m.`pid`
		WHERE
			(m.`flags` & 0x0001) = 0
			AND (m.`flags` & 0x0400)
			AND j1.`flags` & (0x0001 | 0x0002 | 0x0004)
	")))
	{
		foreach($result as &$row)
		{
			$answer = @file_get_contents(HELPDESK_URL.'/ExtAlert.aspx/?Source=cdb&Action=resolved&Type=rename&Id='.urlencode($row['operid']).'&Num='.urlencode($row['opernum']).'&Host='.urlencode($row['name']).'&Message='.urlencode("Заявка более не актуальна"));
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

