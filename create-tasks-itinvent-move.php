<?php
	// Create new and close resolved tasks (IT Invent)

	/**
		\file
		\brief Создание заявок на корректировку местоположения оборудования в IT Invent.
		
		Локация оборудования (значения branch_no и loc_no) должна совпадать с локацией
		коммутатора, в который оно подключено.
		
		Коммутатор выбирается из таблицы по имени и здесь может быть небольшая проблема,
		т.к. имя устройства не является уникальным значением в БД. Если появится устройство
		с аналогичным именем, то возникнет путаница.
		
		Оборудование имеющее флаг Mobile (Ноутбуки) не проверяется на корректность
		местоположения.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-itinvent-move:\n";

	$limit = TASKS_LIMIT_ITINVENT_MOVE;

	global $g_comp_flags;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT t.`id`, t.`operid`, t.`opernum`, m.`inv_no`
		FROM @tasks AS t
		LEFT JOIN @mac AS m
			ON m.`id` = t.`pid`
		LEFT JOIN @devices AS d
			ON d.`id` = m.`pid` AND d.`type` = 3
		LEFT JOIN @mac AS dm
			ON
				dm.`name` = d.`name`
				AND (dm.`flags` & (0x0010 | 0x0040 | 0x0080)) = (0x0010 | 0x0040 | 0x0080)       -- Valid devices is only that exist and active in IT Invent and have SN
		WHERE
			t.`tid` = 3
			AND (t.`flags` & (0x0001 | 0x0010)) = 0x0010                                         -- Task status is Opened
			AND (
				(m.`flags` & (0x0002 | 0x0004 | 0x0010 | 0x0020 | 0x0040 | 0x0100)) <> 0x0070    -- Temprary excluded or Premanently excluded, Not Exist OR Inactive in IT Invent, Not Mobile device
				OR (
					dm.`branch_no` IS NOT NULL
					AND dm.`loc_no` IS NOT NULL
					AND m.`branch_no` = dm.`branch_no`
					AND m.`loc_no` = dm.`loc_no`
				)
			)
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
					echo $row['inv_no'].' '.$row['opernum']."\r\n";
					$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | 0x0001) WHERE `id` = # LIMIT 1", $row['id']));
					$i++;
				}
			}
		}
	}

	echo 'Closed: '.$i."\r\n";

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS m WHERE (m.`flags` & (0x0001 | 0x0010)) = 0x0010")))
	{
		$i = intval($result[0][0]);
	}

	if($db->select_assoc_ex($result, rpv("
		SELECT
			m.id
			,m.`inv_no` AS m_inv_no
			,m.`name` AS m_name
			,m.`mac`
			,DATE_FORMAT(m.`date`, '%d.%m.%Y %H:%i:%s') AS `regtime`
			,m.`port`
			,m.`flags`
			-- ,m.`branch_no`
			-- ,m.`loc_no`
			-- ,hex(m.`flags`)
			,d.`name` AS netdev
			,dm.`inv_no` AS d_inv_no
			,dm.`mac` AS d_mac
			-- ,dm.`branch_no`
			-- ,dm.`loc_no`
			-- ,dm.`date`
			-- ,hex(dm.`flags`)
			-- (SELECT BIT_OR(t.`flags`) FROM @tasks AS t WHERE t.`pid` = m.`id` AND t.`tid = 3 AND (t.flags & (0x0001 | 0x0010)) = 0x0010) AS t_flags
		FROM @mac AS m
		LEFT JOIN @devices AS d
			ON d.`id` = m.`pid` AND d.`type` = 3
		LEFT JOIN @mac AS dm
			ON
				dm.`name` = d.`name`
				AND (dm.`flags` & (0x0010 | 0x0040 | 0x0080)) = (0x0010 | 0x0040 | 0x0080)  -- Valid devices is only that exist and active in IT Invent and have SN
		LEFT JOIN @tasks AS t
			ON
				t.`tid` = 3
				AND t.pid = m.id
				AND (t.flags & (0x0001 | 0x0010)) = 0x0010
		WHERE
			(m.`flags` & (0x0002 | 0x0004 | 0x0010 | 0x0020 | 0x0040 | 0x0100)) = 0x0070    -- Not Temprary excluded, Not Premanently excluded, From netdev, Exist in IT Invent, Active in IT Invent, Not Mobile
			AND (
				dm.`branch_no` IS NULL
				OR dm.`loc_no` IS NULL
				OR (
					m.`branch_no` <> dm.`branch_no`
					AND m.`loc_no` <> dm.`loc_no`
				)
			)
		GROUP BY m.`id`, dm.`id`
		HAVING (BIT_OR(t.`flags`) & 0x0010) = 0
	")))
	{
		foreach($result as &$row)
		{
			if($i >= $limit)
			{
				echo 'Limit reached: '.$limit."\r\n";
				break;
			}

			//echo 'MAC: '.$row['mac']."\n";

			$ch = curl_init(HELPDESK_URL.'/ExtAlert.aspx');

			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			curl_setopt($ch, CURLOPT_POSTFIELDS,
				'Source=cdb'
				.'&Action=new'
				.'&Type=itinvent'
				.'&To=bynetdev'
				.'&Host='.urlencode($row['netdev'])
				.'&Message='.urlencode(
					'Обнаружено расхождение в IT Invent: местоположение оборудования отличается от местоположения коммутатора/маршрутизатора, в который оно подключено.'
					."\n\nИнвентарный номер оборудования: ".$row['m_inv_no']
					."\nDNS имя: ".$row['m_name']
					."\n".((intval($row['flags']) & 0x0080) ? 'Серийный номер: '.$row['mac'] : 'MAC: '.implode(':', str_split($row['mac'], 2)))
					."\nПорт: ".$row['port']
					."\nВремя регистрации: ".$row['regtime']
					."\n\nИнвентарный номер коммутатора/маршрутизатора: ".(empty($row['d_inv_no']) ? 'Отсутствует, проведите инвентаризацию коммутатора/маршрутизатора' : $row['d_inv_no'])
					."\nDNS имя: ".$row['netdev']
					."\nСерийный номер: ".$row['d_mac']
					."\n\nКод работ: IIV09"
					."\n\nПодробнее: ".WIKI_URL.'/Процессы%20и%20функции%20ИТ.Местоположение-оборудования-отличается-от-местоположения-коммутатора-в-которыи-оно-подключено.ashx'
				)
			);

			$answer = curl_exec($ch);

			if($answer === FALSE)
			{
				curl_close($ch);
				break;
			}

			$xml = @simplexml_load_string($answer);
			if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
			{
				echo $row['m_name'].' '.$xml->extAlert->query['number']."\r\n";
				$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `flags`, `date`, `operid`, `opernum`) VALUES (3, #, 0x0010, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
				$i++;
			}

			curl_close($ch);
			//break;
		}
	}

	echo 'Created: '.$i."\r\n";

