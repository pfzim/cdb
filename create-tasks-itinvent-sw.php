<?php
	// Create new and close resolved tasks (IT Invent Software)

	/**
		\file
		\brief Создание заявок на инвентиризацию ПО в IT Invent или удаление с ПК пользователя (Код INV06).
		
		Из БД выбираются файлы, у которые во время синхронизации данных из ИТ Инвент и SCCM выявлено
		отстутствие их в ИТ Инвент.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-itinvent-sw:\n";

	$limit = 1;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT t.`id`, t.`operid`, t.`opernum`
		FROM @tasks AS t
		LEFT JOIN @files AS f
			ON f.`id` = t.`pid`
		WHERE
			t.`tid` = 8
			AND (t.`flags` & (0x0001 | 0x0080000)) = 0x8000        -- Task status is Opened
			AND (
				f.`flags` & 0x0010                                 -- Exist in IT Invent
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
					echo $row['opernum']."\r\n";
					$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | 0x0001) WHERE `id` = # LIMIT 1", $row['id']));
					$i++;
				}
			}
		}
	}

	echo 'Closed: '.$i."\r\n";

	// Open new tasks

/*
			AND (
				d.`name` LIKE 'RU-44-%'                                            -- Temporary filter by region 44
				OR
				d.`name` LIKE 'RU-33-%'                                            -- Temporary filter by region 33
				OR
				d.`name` LIKE 'RU-77-%'                                            -- Temporary filter by region 77
				OR
				d.`name` LIKE 'RU-13-%'                                            -- Temporary filter by region 13
				OR
				d.`name` LIKE 'RU-11-%'                                            -- Temporary filter by region 11
			)
*/

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & (0x0001 | 0x080000)) = 0x080000")))
	{
		$i = intval($result[0][0]);
	}

	if($db->select_assoc_ex($result, rpv("
		SELECT
			f.`id`,
			f.`path`,
			f.`filename`,
			f.`flags`
		FROM @files AS f
		WHERE
			(f.`flags` & 0x0010) = 0                              -- Not exist in IT Invent or not Active
			AND f.`id` NOT IN (
				SELECT
					DISTINCT t.`id`
				FROM @tasks AS t
				WHERE
					t.`tid` = 8
					AND (t.flags & (0x0001 | 0x080000)) = 0x080000
			)
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
				.'&Type=itinvent'
				.'&To=bynetdev'
				.'&Host='.urlencode($row['netdev'])
				.'&Message='.urlencode(
					'Обнаружено ПО не зарегистрированное в IT Invent'
					."\nPath: ".$row['path']
					."\nFile: ".$row['name']
					."\n\nКод работ: INV06"
					."\n\nСледует зарагистрировать ПО в ИТ Инвент или удалить с ПК пользователей. Подробнее: ".WIKI_URL.'/Процессы%20и%20функции%20ИТ.Обнаружено-сетевое-устроиство-MAC-адрес-которого-не-зафиксирован-в-IT-Invent.ashx'
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
				$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `flags`, `date`, `operid`, `opernum`) VALUES (8, #, 0x080000, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
				$i++;
			}

			curl_close($ch);
			//break;
		}
	}

	echo 'Created: '.$i."\r\n";

