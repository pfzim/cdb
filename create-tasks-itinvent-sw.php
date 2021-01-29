<?php
	// Create new and close resolved tasks (IT Invent Software)

	/**
		\file
		\brief Создание заявок на инвентиризацию ПО в IT Invent или удаление с ПК пользователя (Код INV06).
		
		Из БД выбираются компьютеры, на которых присутствуют файлы обнаруженные SCCM и отсутствующие в ИТ Инвент.
		
		После закрытия наряда у файлов автоматические устанавливается флаг "Удален". При повтороном обнаружени
		файла во время следующего сканирования флаг снимается.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-itinvent-sw:\n";

	$limit = TASKS_LIMIT_ITINVENT_SW;

	global $g_comp_flags;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			t.`id`,
			t.`operid`,
			t.`opernum`,
			COUNT(fi.`fid`) AS files_count,
			c.`flags`
		FROM c_tasks AS t
		LEFT JOIN c_computers AS c
			ON t.`pid` = c.`id`
		LEFT JOIN c_files_inventory AS fi
			ON fi.`pid` = c.`id` AND fi.`flags` & 0x0020 = 0
		LEFT JOIN c_files AS f
			ON fi.`fid` = f.`id`
		WHERE
			t.`tid` = 1
			AND (t.`flags` & (0x0001 | 0x0080000)) = 0x0080000     -- Task status is Opened
			AND (f.`flags` & 0x0010) = 0                           -- Not exist in IT Invent
		GROUP BY t.`id`
		HAVING
			`files_count` = 0
			OR c.`flags` & (0x0001 | 0x0002 | 0x0004)
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
					echo $row['opernum']."\r\n";
					$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | 0x0001) WHERE `id` = # LIMIT 1", $row['id']));
					$i++;
				}
			}
		}
	}

	echo 'Closed: '.$i."\r\n";

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & (0x0001 | 0x080000)) = 0x080000")))
	{
		$i = intval($result[0][0]);
	}

	if($db->select_assoc_ex($result, rpv("
		SELECT
			c.`id`,
			c.`name`,
			c.`flags`,
			COUNT(fi.`fid`) AS files_count
		FROM @computers AS c
		LEFT JOIN @files_inventory AS fi
			ON
				fi.`pid` = c.`id`
				AND (fi.`flags` & 0x0020) = 0                     -- File not Deleted
		LEFT JOIN @files AS f
			ON fi.`fid` = f.`id`
		WHERE
			(f.`flags` & 0x0010) = 0                              -- Not exist in IT Invent
			AND c.`flags` & (0x0001 | 0x0002 | 0x0004) = 0        -- Not Disabled, Not Deleted, Not Hide
			AND c.`id` NOT IN (
				SELECT
					DISTINCT t.`id`
				FROM @tasks AS t
				WHERE
					t.`tid` = 1
					AND (t.flags & (0x0001 | 0x080000)) = 0x080000
			)
		GROUP BY c.`id`
		ORDER BY files_count
		LIMIT 100
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
				.'&Type=test'
				.'&To=byname'
				.'&Host='.urlencode($row['name'])
				.'&Message='.urlencode(
					'На компьютере обнаружено ПО не зарегистрированное в IT Invent'
					."\n\nИмя ПК: ".$row['name']
					."\nИсточник информации о ПК: ".flags_to_string(intval($row['flags']) & 0x00F0, $g_comp_flags, ', ')
					."\nСписок обнаруженного ПО доступен по ссылке: ".CDB_URL.'/cdb.php?action=get-computer-info&id='.$row['id']
					."\n\nКод работ: INV06"
					."\n\nСледует зарегистрировать ПО в ИТ Инвент или удалить с ПК пользователя. Подробнее: ".WIKI_URL.'/Процессы%20и%20функции%20ИТ.Обнаружено-сетевое-устроиство-MAC-адрес-которого-не-зафиксирован-в-IT-Invent.ashx'
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
				echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
				$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `flags`, `date`, `operid`, `opernum`) VALUES (1, #, 0x080000, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
				$i++;
			}

			curl_close($ch);
			//break;
		}
	}

	echo 'Created: '.$i."\r\n";

