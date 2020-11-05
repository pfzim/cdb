<?php
	// Create new and close resolved tasks (vulnerabilities)

	/**
		\file
		\brief Создание заявок на устранение обнаруженных уязвимостей (не массовых < 100 ПК).
		Автоматическое закрытие срабатывает, если проблема помечена исправленой, устройство скрыли из проверок (Manual hide),
		или уязвимость скрыли из проверок (Manual hide).
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-vuln:\n";

	$limit = 1;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			t.`id`,
			t.`operid`,
			t.`opernum`,
			d.`name`
		FROM @tasks AS t
		LEFT JOIN @vuln_scans AS vs
			ON vs.`id` = t.`pid`
		LEFT JOIN @vulnerabilities AS v
			ON v.`plugin_id` = vs.`plugin_id`
		LEFT JOIN @devices AS d
			ON d.`id` = vs.`pid`
		WHERE
			t.`tid` = 5
			AND (t.`flags` & (0x0001 | 0x010000)) = 0x010000
			AND (
				d.`flags` & 0x0004                              -- Manual hide
				OR vs.`flags` & 0x0002                          -- Marked as Fixed
				OR v.`flags` & 0x0004                           -- Manual hide
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

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS m WHERE (m.`flags` & (0x0001 | 0x010000)) = 0x010000")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
		SELECT
			vs.`id`,
			d.`name`,
			v.`plugin_name`,
			v.`severity`,
			vs.`scan_date`,
			d.`flags`			
		FROM @vuln_scans AS vs
		LEFT JOIN @tasks AS t
			ON
			t.`tid` = 5
			AND t.`pid` = vs.`id`
			AND (t.`flags` & (0x0001 | 0x010000)) = 0x010000
		LEFT JOIN @vulnerabilities AS v
			ON v.`plugin_id` = vs.`plugin_id`
		LEFT JOIN @devices AS d
			ON d.`id` = vs.`pid`
		WHERE
			(d.`flags` & 0x0004) = 0                                                                           -- Device not excluded (Manual hide)
			AND (vs.`flags` & (0x0002 | 0x0004)) = 0                                                           -- Not marked as Fixed OR Manual hide
			AND v.`severity` >= 3                                                                              -- Severity >= 3
			AND (v.`flags` & 0x0004) = 0                                                                       -- Vulnerability not excluded (Manual hide)
			AND (SELECT COUNT(*) FROM @vuln_scans AS ivs WHERE ivs.`plugin_id` = vs.`plugin_id`) < 100         -- Not mass vulnerability (affected < 100)
		GROUP BY vs.`id`
		HAVING (BIT_OR(t.`flags`) & 0x010000) = 0
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
				.'&To=byname'
				.'&Host='.urlencode($row['name'])
				.'&Message='.urlencode(
					'Nessus: Обнаружена уязвимость требующая устранения. #'.$row['id']
					."\n\nПК: ".$row['name']
					."\nУязвимость: ".$row['plugin_name']
					."\nУровень опасности: ".$row['severity']
					."\nДата обнаружения: ".$row['scan_date']
				)
			);
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `flags`, `date`, `operid`, `opernum`) VALUES (5, #, 0x010000, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
		}
	}

	echo 'Created: '.$i."\r\n";
