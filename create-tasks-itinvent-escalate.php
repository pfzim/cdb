<?php
	// Create new and close resolved tasks (IT Invent) tasks

	/**
		\file
		\brief Создание заявок на заявки в IT Invent.
		
		Рекурсия. Заявки которые открывались повторно 2 и более раз перевыствляются на РИТМ для проведения анализа.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-itinvent-escalate:\n";

	$limit = TASKS_LIMIT_ITINVENT_ESCALATE;

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
			AND t.`type` = {%TT_INV_TASKFIX}
			AND (t.`flags` & {%TF_CLOSED}) = 0          -- Task status is Opened
			AND (
				m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED})                   -- Temprary excluded or Premanently excluded
				OR (m.`flags` & ({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})) = ({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})     -- Exist AND Active in IT Invent
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

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & {%TF_CLOSED}) = 0 AND t.`type` = {%TT_INV_TASKFIX}")))
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
			m.`flags`,
			COUNT(*) AS `issues`
		FROM c_mac AS m
		LEFT JOIN c_devices AS d
			ON d.`id` = m.`pid`
		LEFT JOIN c_tasks AS t
			ON
				t.`tid` = {%TID_MAC}
				AND t.pid = m.id
				AND t.`type` = {%TT_INV_TASKFIX}
				AND (t.flags & {%TF_CLOSED}) = 0
		LEFT JOIN c_tasks AS t2
			ON
				t2.`tid` = {%TID_MAC}
				AND t2.pid = m.id
				AND t2.`type` = {%TT_INV_ADD}
				-- AND (t2.flags & {%TF_INV_ADD})
		WHERE
			(m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE} | {%MF_FROM_NETDEV})) = {%MF_FROM_NETDEV}    -- Not Temprary excluded, Not Premanently excluded, Imported from netdev, Not exist in IT Invent
		GROUP BY m.`id`
		HAVING
			COUNT(t.`id`) = 0
			AND `issues` > 1
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
				.'&To=ritm'
				.'&Host='.urlencode($row['netdev'])
				.'&Message='.urlencode(
					'Необходимо проанализировать заявки по данному сетевому устройству. Принять меры: добавить в ИТ Инвент, удалить из базы Снежинки или добавить в исключение.'
					."\n\n".((intval($row['flags']) & MF_SERIAL_NUM) ? 'Серийный номер коммутатора: '.$row['mac'] : 'MAC: '.implode(':', str_split($row['mac'], 2)))
					."\nIP: ".$row['ip']
					."\nDNS name: ".$row['name']
					."\nУстройство подключено к: ".$row['netdev']
					."\nПорт: ".$row['port']
					."\nVLAN ID: ".$row['vlan']
					."\nВремя регистрации: ".$row['regtime']
					."\nКоличество повторных заявок: ".$row['issues']
					."\n\nКод работ: IIV09"
					."\n\nИстория выставленных нарядов: ".CDB_URL.'/cdb.php?action=get-mac-info&id='.$row['id']
					."\n\nВ решении указать причину и принятые меры по недопущению открытия повторных заявок."
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
				$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_MAC}, #, {%TT_INV_TASKFIX}, {%TF_INV_TASKFIX}, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
				$i++;
			}

			curl_close($ch);
			//break;
		}
	}

	echo 'Created: '.$i."\r\n";

