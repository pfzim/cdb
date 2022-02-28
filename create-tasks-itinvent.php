<?php
	// Create new and close resolved tasks (IT Invent)

	/**
		\file
		\brief Создание заявок на занесение оборудования в IT Invent.
		
		Критерии создания заявок при выполненни обоих условий:
		  - Оборудование обнаружено активным в сети
		  - Оборудование не занесено в ИТ Инвент, либо занесено, но числится не в работе

		Если это коммутатор или маршрутизатор, то в заявке должен фигурировать Серийный номер.
		1.	Если в заявке видите MAC адрес вместо Серийного номера, значит он передаёт некорректные данные и с ним что-то не в порядке.
		2.	Серийный номер должен соответствовать номеру в карточке поля: Серийный номер.
		3.	Статус в карточке должен быть «Работает» или «Выдан пользователю для удаленной работы».

		Для другого оборудования:
		1.	MAC адрес должен соответствовать одному из номеров в карточке полей: MAC Адрес, MAC Адрес (1, 2, 3), MAC Адрес ТСД, Усилитель 3G: mac-адрес (2).
		2.	Статус в карточке должен быть «Работает» или «Выдан пользователю для удаленной работы».

		Если оборудование «засветилось» в сети и находится в статусе отличном от «Работает» и «Выдан пользователю для удаленной работы», то выясняете причину и принимаете соответствующие меры.
		
		Временно отключена проверка статуса в из карточки ИТ Инвент. Все статусы считаются валидными, главное присутствие оборудования в ИТ Инвент
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-itinvent:\n";

	$limit = TASKS_LIMIT_ITINVENT;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT t.`id`, t.`operid`, t.`opernum`, m.`mac`
		FROM @tasks AS t
		LEFT JOIN @mac AS m
			ON m.`id` = t.`pid`
		WHERE
			t.`tid` = {%TID_MAC}
			AND (t.`flags` & ({%TF_CLOSED} | {%TF_INV_ADD})) = {%TF_INV_ADD}              -- Task status is Opened
			AND (
				m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED})                   -- Temprary excluded or Premanently excluded
				-- OR (m.`flags` & ({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})) = ({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})     -- Exist AND Active in IT Invent      -- Temporary do not check status
				OR (m.`flags` & {%MF_EXIST_IN_ITINV})     					              -- Exist in IT Invent
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
					echo $row['mac'].' '.$row['opernum']."\r\n";
					$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | {%TF_CLOSED}) WHERE `id` = # LIMIT 1", $row['id']));
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

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS m WHERE (m.`flags` & ({%TF_CLOSED} | {%TF_INV_ADD})) = {%TF_INV_ADD}")))
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
			m.`vlan`,
			DATE_FORMAT(m.`date`, '%d.%m.%Y %H:%i:%s') AS `regtime`,
			m.`flags`
		FROM @mac AS m
		LEFT JOIN @devices AS d
			ON d.`id` = m.`pid`
		LEFT JOIN @tasks AS t
			ON
				t.`tid` = {%TID_MAC}
				AND t.pid = m.id
				AND (t.flags & ({%TF_CLOSED} | {%TF_INV_ADD})) = {%TF_INV_ADD}
		WHERE
			-- (m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_EXIST_IN_ITINV} | {%MF_FROM_NETDEV} | {%MF_INV_ACTIVE})) = {%MF_FROM_NETDEV}    -- Not Temprary excluded, Not Premanently excluded, imported from netdev, not exist in IT Invent or not Active    -- Temporary do not check status
			(m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_EXIST_IN_ITINV} | {%MF_FROM_NETDEV})) = {%MF_FROM_NETDEV}    -- Not Temprary excluded, Not Premanently excluded, imported from netdev, not exist in IT Invent or not Active
		GROUP BY m.`id`
		HAVING (BIT_OR(t.`flags`) & {%TF_INV_ADD}) = 0
		ORDER BY RAND()
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
					'Обнаружено сетевое устройство '.((intval($row['flags']) & MF_SERIAL_NUM) ? 'Серийный номер' : 'MAC адрес').' которого не зафиксирован в IT Invent'
					."\n\n".((intval($row['flags']) & MF_SERIAL_NUM) ? 'Серийный номер коммутатора: '.$row['mac'] : 'MAC: '.implode(':', str_split($row['mac'], 2)))
					."\nDNS name: ".$row['name']
					."\nIP: ".$row['ip']
					."\n\nУстройство подключено к: ".$row['netdev']
					."\nПорт: ".$row['port']
					."\nVLAN ID: ".$row['vlan']
					."\nВремя регистрации: ".$row['regtime']
					."\n\nКод работ: IIV09"
					."\n\nСледует актуализировать данные по указанному устройству и заполнить соответствующий атрибут. Подробнее: ".WIKI_URL.'/Процессы%20и%20функции%20ИТ.Обнаружено-сетевое-устроиство-MAC-адрес-которого-не-зафиксирован-в-IT-Invent.ashx'
					."\nВ решении укажите Инвентарный номер оборудования!"
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
				$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_MAC}, #, {%TF_INV_ADD}, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
				$i++;
			}

			curl_close($ch);
			//break;
		}
	}

	echo 'Created: '.$i."\r\n";

