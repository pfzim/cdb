<?php
	// Create new and close resolved tasks (vulnerabilities)

	/**
		\file
		\brief Создание заявок на устранение обнаруженных уязвимостей.
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
		LEFT JOIN @vuln_scans AS s
			ON s.`id` = t.`pid`
		LEFT JOIN @devices AS d
			ON d.`id` = s.`pid`
		WHERE
			t.`tid` = 5
			AND (t.`flags` & (0x0001 | 0x010000)) = 0x010000
			AND d.`flags` & 0x0004
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
			d.`id`,
			d.`name`,
			v.`plugin_name`,
			v.`severity`,
			s.`scan_date`,
			d.`flags`
		FROM @vuln_scans AS s
		LEFT JOIN @tasks AS t
			ON
			t.`tid` = 5
			AND t.`pid` = v.`id`
			AND (t.`flags` & (0x0001 | 0x010000)) = 0x010000
		LEFT JOIN @vulnerabilities AS v
			ON v.`plugin_id` = s.`plugin_id`
		LEFT JOIN @devices AS d
			ON d.`id` = s.`pid`
		WHERE
			(d.`flags` & 0x0004) = 0
			AND (s.`flags` 0x0002) = 0
			AND v.`severity` >= 4
		GROUP BY s.`id`
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
				.'&To=sas'
				.'&Host='.urlencode($row['name'])
				.'&Message='.urlencode(
					'Обнаружена уязвимость требующая устранения.'
					."\nПК: ".$row['name']
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
