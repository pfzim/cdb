<?php
	// Create new and close resolved tasks (vulnerabilities)

	/**
		\file
		\brief Создание заявок на устранение обнаруженных уязвимостей (массовые проблемы >= 100 ПК).
		Автоматическое закрытие при ручной установке отметки Manual hide (Скрыть из проверок)
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-vuln-mass:\n";

	$limit = 1;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			t.`id`,
			t.`operid`,
			t.`opernum`,
			v.`plugin_name`
		FROM @tasks AS t
		LEFT JOIN @vulnerabilities AS v
			ON v.`plugin_id` = t.`pid`
		WHERE
			t.`tid` = 6
			AND (t.`flags` & (0x0001 | 0x020000)) = 0x020000
			AND v.`flags` & 0x0004                              -- Manual hide
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
					//echo $answer."\r\n";
					echo $row['plugin_name'].' '.$row['opernum']."\r\n";
					$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | 0x0001) WHERE `id` = # LIMIT 1", $row['id']));
					$i++;
				}
			}
			//break;
		}
	}

	echo 'Closed: '.$i."\r\n";

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS m WHERE (m.`flags` & (0x0001 | 0x020000)) = 0x020000")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
		SELECT
			v.`plugin_id`,
			v.`plugin_name`,
			v.`severity`,
			v.`flags`,
			COUNT(vs.`id`) AS v_count
		FROM @vulnerabilities AS v
		LEFT JOIN @tasks AS t
			ON
				t.`tid` = 6
				AND t.`pid` = v.`plugin_id`
				AND (t.`flags` & (0x0001 | 0x020000)) = 0x020000
		LEFT JOIN @vuln_scans AS vs
			ON
				vs.`plugin_id` = v.`plugin_id`
				AND (vs.`flags` & 0x0002) = 0x0000                         -- Not fixed
		WHERE
			(v.`flags` & 0x0004) = 0                                       -- Not excluded (Manual hide)
			AND v.`severity` >= 3                                          -- Severity >= 3
		GROUP BY v.`plugin_id`		
		HAVING
			(BIT_OR(t.`flags`) & 0x020000) = 0                             -- Not yet task created
			AND v_count >= 100                                             -- Affected devices >= 100
		ORDER BY
			severity DESC,
			v_count DESC
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
				.'&Type=vuln'
				.'&To=sas'
				.'&Host='.urlencode($row['name'])
				.'&Message='.urlencode(
					'Nessus: Обнаружена массовая уязвимость требующая устранения. #'.$row['plugin_id']
					."\n\nУязвимость: ".$row['plugin_name']
					."\nУровень опасности: ".$row['severity']
					."\nКоличество уязвимых устройств: ".$row['v_count']
				)
			);
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['plugin_name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `flags`, `date`, `operid`, `opernum`) VALUES (6, #, 0x020000, NOW(), !, !)", $row['plugin_id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
		}
	}

	echo 'Created: '.$i."\r\n";
