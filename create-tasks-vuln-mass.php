<?php
	// Create new and close resolved tasks (vulnerabilities)

	/**
		\file
		\brief Создание заявок на устранение обнаруженных уязвимостей (массовые проблемы >= 100 ПК).
		Автоматическое закрытие при ручной установке отметки Manual hide (Скрыть из проверок)
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-vuln-mass:\n";

	$limit = TASKS_LIMIT_VULN_MASS;

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
			t.`tid` = {%TID_VULNS}
			AND t.`type` = {%TT_VULN_FIX_MASS}
			AND (t.`flags` & {%TF_CLOSED}) = 0
			AND v.`flags` & {%VF_HIDED}                              -- Manual hide
	")))
	{
		foreach($result as &$row)
		{
			$xml = helpdesk_api_request(helpdesk_build_request(
				TT_CLOSE,
				array(
					'operid'	=> $row['operid'],
					'opernum'	=> $row['opernum']
				)
			));

			if($xml !== FALSE)
			{
				//echo $answer."\r\n";
				echo $row['plugin_name'].' '.$row['opernum']."\r\n";
				$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | {%TF_CLOSED}) WHERE `id` = # LIMIT 1", $row['id']));
				$i++;
			}
			//break;
		}
	}

	echo 'Closed: '.$i."\r\n";

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & {%TF_CLOSED}) = 0 AND t.`type` = {%TT_VULN_FIX_MASS}")))
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
				t.`tid` = {%TID_VULNS}
				AND t.`pid` = v.`plugin_id`
				AND t.`type` = {%TT_VULN_FIX_MASS}
				AND (t.`flags` & {%TF_CLOSED}) = 0
		LEFT JOIN @vuln_scans AS vs
			ON
				vs.`plugin_id` = v.`plugin_id`
				AND (vs.`flags` & ({%VSF_FIXED} | {%VSF_HIDED})) = 0x0000        -- Not fixed
		WHERE
			(v.`flags` & {%VF_HIDED}) = 0                                        -- Not excluded (Manual hide)
			AND v.`severity` >= 3                                                -- Severity >= 3
		GROUP BY v.`plugin_id`		
		HAVING
			COUNT(t.`id`) = 0                                                    -- Not yet task created
			AND v_count >= 100                                                   -- Affected devices >= 100
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
			
			$xml = helpdesk_api_request(helpdesk_build_request(
				TT_VULN_FIX_MASS,
				array(
					'host'			=> $row['name'],
					'plugin_id'		=> $row['plugin_id'],
					'plugin_name'	=> $row['plugin_name'],
					'severity'		=> $row['severity'],
					'vuln_count'	=> $row['v_count']
				)
			));

			if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
			{
				//echo $answer."\r\n";
				echo $row['plugin_name'].' '.$xml->extAlert->query['number']."\r\n";
				$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_VULNS}, #, {%TT_VULN_FIX_MASS}, 0, NOW(), !, !)", $row['plugin_id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
				$i++;
			}
		}
	}

	echo 'Created: '.$i."\r\n";
