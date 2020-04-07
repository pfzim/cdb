<?php
	// Create new and close resolved tasks (TMAO)

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-tmao:\n";

	global $g_comp_flags;

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS m WHERE (m.`flags` & (0x0001 | 0x0200)) = 0x0200")))
	{
		$i = intval($result[0][0]);
	}
	
	//if($db->select_assoc_ex($result, rpv("SELECT * FROM @computers WHERE (`flags` & (0x0001 | 0x0004 | 0x0200)) = 0 AND `name` regexp '^(([[:digit:]]{4}-[nNwW])|(OFF[Pp][Cc]-))[[:digit:]]+$' AND `ao_script_ptn` < ((SELECT MAX(`ao_script_ptn`) FROM @computers) - 2900)")))
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`name`, m.`dn`, m.`ao_script_ptn`, m.`flags`
		FROM @computers AS m
		LEFT JOIN @tasks AS j1 ON j1.pid = m.id AND (j1.flags & (0x0001 | 0x0200)) = 0x0200
		WHERE
			(m.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
			AND `ao_script_ptn` < ((SELECT MAX(`ao_script_ptn`) FROM @computers) - 2900)
			AND `name` NOT REGEXP '".CDB_REGEXP_SERVERS."'
		GROUP BY m.`id`
		HAVING (BIT_OR(j1.`flags`) & 0x0200) = 0
	")))
	{
		foreach($result as &$row)
		{
			if($i >= 100)
			{
				echo "Limit reached: 100\r\n";
				break;
			}

			//$answer = '<?xml version="1.0" encoding="utf-8"? ><root><extAlert><event ref="c7db7df4-e063-11e9-8115-00155d420f11" date="2019-09-26T16:44:46" number="001437825" rule="" person=""/><query ref="" date="" number=""/><comment/></extAlert></root>';

			$answer = @file_get_contents(
				HELPDESK_URL.'/ExtAlert.aspx/'
				.'?Source=cdb'
				.'&Action=new'
				.'&Type=tmao'
				.'&To=byname'
				.'&Host='.urlencode($row['name'])
				.'&Message='.urlencode(
					'Выявлена проблема с TMAO'
					."\nПК: ".$row['name']
					."\nВерсия антивирусной базы: "
					.$row['ao_script_ptn']
					."\nИсточник информации о ПК: ".flags_to_string(intval($row['flags']) & 0x00F0, $g_comp_flags, ', ')
					."\nКод работ: AVCTRL\n\n".WIKI_URL."/Отдел%20ИТ%20Инфраструктуры.Restore_AO_agent.ashx"
				)
			);

			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`pid`, `flags`, `date`, `operid`, `opernum`) VALUES (#, 0x0200, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
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
			(m.`flags` & (0x0001 | 0x0200)) = 0x0200
			AND (j1.`flags` & (0x0001 | 0x0002 | 0x0004) OR j1.`ao_script_ptn` >= ((SELECT MAX(`ao_script_ptn`) FROM @computers) - 2900))
	")))
	{
		foreach($result as &$row)
		{
			$answer = @file_get_contents(
				HELPDESK_URL.'/ExtAlert.aspx/'
				.'?Source=cdb'
				.'&Action=resolved'
				.'&Type=tmao'
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
