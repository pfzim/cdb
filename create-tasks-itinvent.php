<?php
	// Create new and close resolved tasks (IT Invent)

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-itinvent:\n";

	$limit = 20;

	global $g_comp_flags;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT t.`id`, t.`operid`, t.`opernum`, m.`mac`
		FROM @tasks AS t
		LEFT JOIN @mac AS m
			ON m.`id` = t.`pid`
		WHERE
			t.`tid` = 3
			AND (t.`flags` & (0x0001 | 0x8000)) = 0x8000          -- Task status is Opened
			AND m.`flags` & (0x0002 | 0x0004 | 0x0040 | 0x0010)   -- Deleted, Manual hide, Inactive in IT Invent or Exist in IT Invent
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
					echo $row['mac'].' '.$row['opernum']."\r\n";
					$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | 0x0001) WHERE `id` = # LIMIT 1", $row['id']));
					$i++;
				}
			}
		}
	}

	echo 'Closed: '.$i."\r\n";

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS m WHERE (m.`flags` & (0x0001 | 0x8000)) = 0x8000")))
	{
		$i = intval($result[0][0]);
	}

	if($db->select_assoc_ex($result, rpv("
		SELECT 
			m.`id`,
			d.`name` AS `netdev`,
			m.`name`,
			m.`mac`,
			m.`ip`,
			m.`port`,
			DATE_FORMAT(m.`date`, '%d.%m.%Y %H:%i:%s') AS `regtime`,
			m.`flags`
		FROM @mac AS m
		LEFT JOIN @devices AS d
			ON d.`id` = m.`pid`
		LEFT JOIN @tasks AS t
			ON
				t.`tid` = 3
				AND t.pid = m.id
				AND (t.flags & (0x0001 | 0x8000)) = 0x8000
		WHERE
			(m.`flags` & (0x0002 | 0x0004 | 0x0010 | 0x0020 | 0x0040)) = 0x0020    -- Not deleted, not hide, imported from netdev, not exist in IT Invent
			AND d.`name` LIKE 'RU-44-%'                                            -- Temporary filter by region 44
		GROUP BY m.`id`
		HAVING (BIT_OR(t.`flags`) & 0x8000) = 0
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
				.'&Type=itinvent'
				.'&To=bynetdev'
				.'&Host='.urlencode($row['netdev'])
				.'&Message='.urlencode(
					'Обнаружено сетевое устройство MAC адрес которого не зафиксирован в IT Invent'
					."\n\nMAC: ".implode(':', str_split($row['mac'], 2))
					."\nIP: ".$row['ip']
					."\nDNS name: ".$row['name']
					."\nУстройство подключено к: ".$row['netdev']
					."\nПорт: ".$row['port']
					."\nВремя регистрации: ".$row['regtime']
					."\n\nКод работ: INV01\n\nСледует актуализировать данные по указанному устройству и заполнить атрибут MAC адрес в соответствии с инструкцией п. 2.7 ".WIKI_URL.'/Процессы%20и%20функции%20ИТ.Заполнение-карточки-учетнои-единицы-при-первичном-внесении-в-базу.ashx'
				)
			);

			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `flags`, `date`, `operid`, `opernum`) VALUES (3, #, 0x8000, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
		}
	}

	echo 'Created: '.$i."\r\n";

