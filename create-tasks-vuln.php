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

	$limit = TASKS_LIMIT_VULN;

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
			t.`tid` = {%TID_VULN_SCANS}
			AND t.`type` = {%TT_VULN_FIX}
			AND (t.`flags` & {%TF_CLOSED}) = 0
			AND (
				d.`flags` & {%DF_HIDED}                              -- Manual hide
				OR vs.`flags` & ({%VSF_FIXED} | {%VSF_HIDED})        -- Marked as Fixed or Hided
				OR v.`flags` & {%VF_HIDED}                           -- Manual hide
			)
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
				echo $row['name'].' '.$row['opernum']."\r\n";
				$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | {%TF_CLOSED}) WHERE `id` = # LIMIT 1", $row['id']));
				$i++;
			}
			//break;
		}
	}

	echo 'Closed: '.$i."\r\n";

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_VULN_FIX}")))
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
			t.`tid` = {%TID_VULN_SCANS}
			AND t.`pid` = vs.`id`
			AND t.`type` = {%TT_VULN_FIX}
			AND (t.`flags` & {%TF_CLOSED}) = 0
		LEFT JOIN @vulnerabilities AS v
			ON v.`plugin_id` = vs.`plugin_id`
		LEFT JOIN @devices AS d
			ON d.`id` = vs.`pid`
		WHERE
			(d.`flags` & {%DF_HIDED}) = 0                                                                      -- Device not excluded (Manual hide)
			AND (vs.`flags` & ({%VSF_FIXED} | {%VSF_HIDED})) = 0                                               -- Not marked as Fixed OR Manual hide
			AND v.`severity` >= 3                                                                              -- Severity >= 3
			AND (v.`flags` & {%VF_HIDED}) = 0                                                                  -- Vulnerability not excluded (Manual hide)
			AND (SELECT COUNT(*) FROM @vuln_scans AS ivs WHERE ivs.`plugin_id` = vs.`plugin_id`) < 100         -- Not mass vulnerability (affected < 100)
		GROUP BY vs.`id`
		HAVING
			COUNT(t.`id`) = 0
	")))
	{
		foreach($result as &$row)
		{
			if($i >= $limit)
			{
				echo 'Limit reached: '.$limit."\r\n";
				break;
			}
			
			$xml = helpdesk_api_request(
				'?Source=cdb'
				.'&Action=new'
				.'&Type=vuln'
				.'&To=byname'
				.'&Host='.urlencode($row['name'])
				.'&Message='.helpdesk_message(
					TT_VULN_FIX,
					array(
						'host'			=> $row['name'],
						'id'			=> $row['id'],
						'plugin_name'	=> $row['plugin_name'],
						'severity'		=> $row['severity'],
						'scan_date'		=> $row['scan_date']
					)
				)
			);

			if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
			{
				echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
				$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_VULN_SCANS}, #, {%TT_VULN_FIX}, 0, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
				$i++;
			}
		}
	}

	echo 'Created: '.$i."\r\n";
