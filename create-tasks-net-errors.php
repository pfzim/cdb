<?php
	// Create new and close resolved tasks (Net errors)

	/**
		\file
		\brief Создание заявок на устранение обнаруженных ошибок коммутаторов.
		
		Наряды на устранение выставляются, если количество ошибок
		CarrierSenseErrors превышает 10. Остальные типы ошибок не
		обрабатываются.
		
		Все типы обшибок можно посмотреть в модуле import-errors.php
		
		Ошибки на порту FastEthernet4 не отслеживаются.
	
		Для удобства обслуживания ошибки группируются по устройствам и
		выставляется одна заявка на все проблемные порты одного коммутатора.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-net-errors:\n";

	$limit = TASKS_LIMIT_NET_ERRORS;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT t.`id`, t.`operid`, t.`opernum`, d.`name`
		FROM @tasks AS t
		LEFT JOIN @devices AS d
			ON d.`id` = t.`pid` AND t.`tid` = {%TID_DEVICES} AND d.`type` = {%DT_NETDEV}
		WHERE
			t.`tid` = {%TID_DEVICES}
			AND t.`type` = {%TT_NET_ERRORS}
			AND (t.`flags` & {%TF_CLOSED}) = 0     -- Task status is Opened
			AND d.`flags` & ({%DF_DELETED} | {%DF_HIDED})                              -- Deleted, Manual hide
	")))
	{
		foreach($result as &$row)
		{
			$xml = helpdesk_api_request(
				'Source=cdb'
				.'&Action=resolved'
				.'&Id='.urlencode($row['operid'])
				.'&Num='.urlencode($row['opernum'])
				.'&Message='.helpdesk_message(
					TT_CLOSE,
					array(
						'operid'	=> $row['operid'],
						'opernum'	=> $row['opernum']
					)
				)
			);

			if($xml !== FALSE)
			{
				echo $row['mac'].' '.$row['opernum']."\r\n";
				$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | {%TF_CLOSED}) WHERE `id` = # LIMIT 1", $row['id']));
				$i++;
			}
		}
	}

	echo 'Closed: '.$i."\r\n";

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_NET_ERRORS}")))
	{
		$i = intval($result[0][0]);
	}

	if($db->select_assoc_ex($result, rpv("
		SELECT 
			d.`id`,
			d.`name`,
			d.`flags`
		FROM @devices AS d
		LEFT JOIN @tasks AS t
			ON
				t.`tid` = {%TID_DEVICES}
				AND t.pid = d.id
				AND t.`type` = {%TT_NET_ERRORS}
				AND (t.`flags` & {%TF_CLOSED}) = 0
		LEFT JOIN @net_errors AS e ON
			e.`pid` = d.`id`
		WHERE
			d.`type` = {%DT_NETDEV}
			AND (d.`flags` & ({%DF_DELETED} | {%DF_HIDED})) = 0    -- Not deleted, not hide
			AND (e.`flags` & {%NEF_FIXED}) = 0
			AND e.`port` <> 'FastEthernet4'
			AND (
			  -- e.`scf` > 10
			  -- OR 
			  e.`cse` > 10
			  -- OR e.`ine` > 10
			)
		GROUP BY d.`id`
		HAVING
			COUNT(t.`id`) = 0
			AND COUNT(e.`id`) > 0
	")))
	{
		foreach($result as &$row)
		{
			if($i >= $limit)
			{
				echo 'Limit reached: '.$limit."\r\n";
				break;
			}

			if($db->select_assoc_ex($net_errors, rpv("
					SELECT
						e.`id`,
						e.`port`,
						e.`date`,
						e.`scf`,
						e.`cse`,
						e.`ine`
					FROM @net_errors AS e
					WHERE
						e.`pid` = #
						AND (e.`flags` & {%NEF_FIXED}) = 0
				",
				$row['id']
			)))
			{
				$message = '';

				foreach($net_errors as &$ne_row)
				{
					$message .= 
						"\n\nПорт: ".$ne_row['port']
						."\nВремя регистрации ошибок: ".$ne_row['date']
						."\nSingleCollisionFrames: ".$ne_row['scf']
						.", CarrierSenseErrors: ".$ne_row['cse']
						.", InErrors: ".$ne_row['ine']
					;
				}

				$xml = helpdesk_api_request(
					'Source=cdb'
					.'&Action=new'
					.'&Type=neterrors'
					.'&To=bynetdev'
					.'&Host='.urlencode($row['name'])
					.'&Message='.helpdesk_message(
						TT_NET_ERRORS,
						array(
							'host'			=> $row['name'],
							'data'			=> $message
						)
					)
				);

				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_DEVICES}, #, {%TT_NET_ERRORS}, 0, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}

			}
		}
	}

	echo 'Created: '.$i."\r\n";

