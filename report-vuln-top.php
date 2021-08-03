<?php
	// Report detected vulnerabilities (Workstations)

	/**
		\file
		\brief Формирование отчёта по обнаруженным уязвимостям (рабочие станции) TOP 100.
		
		Отчёт формируется по уязвимостям загруженным из папки Nessus с идентификатором равным NESSUS_WORKSTATIONS_FOLDER_ID
	*/


	if(!defined('Z_PROTECTED')) exit;

	echo "\nreport-vuln-top:\n";

	$html = <<<'EOT'
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<style>
		body{font-family: Courier New; font-size: 8pt;}
		h1{font-size: 16px;}
		h2{font-size: 14px;}
		h3{font-size: 12px;}
		table{border: 1px solid black; border-collapse: collapse; font-size: 8pt;}
		th{border: 1px solid black; background: #dddddd; padding: 5px; color: #000000;}
		td{border: 1px solid black; padding: 5px; }
		.pass {background: #7FFF00;}
		.warn {background: #FFE600;}
		.error {background: #FF0000; color: #ffffff;}
		.severity0 {background: rgb(0, 113, 185); color: #ffffff;}
		.severity1 {background: rgb(63, 174, 73); color: #ffffff;}
		.severity2 {background: rgb(253, 196, 49); color: #000000;}
		.severity3 {background: rgb(238, 147, 54); color: #000000;}
		.severity4 {background: rgb(212, 63, 58); color: #ffffff;}
		</style>
	</head>
	<body>
	<h1>Отчёт по обнаруженным уязвимостям (рабочие станции)</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Vulnerability</th><th>Severity</th><th>Detections</th></tr>';

	$severities = array();

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			v.`plugin_name`,
			v.`severity`,
			COUNT(vs.`id`) AS cnt
		FROM @vulnerabilities AS v
		LEFT JOIN @vuln_scans AS vs ON vs.`plugin_id` = v.`plugin_id`
		WHERE vs.`folder_id` = #
		AND v.`severity` > 2
		GROUP BY v.`plugin_id`
		ORDER BY cnt DESC, v.`severity` DESC
		-- LIMIT 100
	", NESSUS_WORKSTATIONS_FOLDER_ID)))
	{

		foreach($result as &$row)
		{
			if($i < 100)
			{
				$table .= '<tr>';
				$table .= '<td>'.$row['plugin_name'].'</td>';
				$table .= '<td class="severity'.$row['severity'].'">'.$row['severity'].'</td>';
				$table .= '<td>'.$row['cnt'].'</td>';
				$table .= '</tr>';
			}

			if(!isset($severities[intval($row['severity'])]))
			{
				$severities[intval($row['severity'])] = 0;
			}

			$severities[intval($row['severity'])] += (intval($row['severity']) * intval($row['cnt']));

			$i++;
		}
	}

	$table .= '</table>';

	$html .= '<table>';
	$html .= '<tr><th>Severity</th><th>Общий балл</th></tr>';

	krsort($severities);

	foreach($severities as $key => $value)
	{
		$html .= '<tr><td class="severity'.$key.'">'.$key.'</td><td>'.$value.'</td></tr>';
	}
	$html .= '</table><br />';
	
	$html .= $table;
	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-vuln-top">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	echo 'Opened: '.$i."\r\n";

	if(php_mailer(array(MAIL_TO_ADMIN), CDB_TITLE.': Detected vulnerabilities - Workstations', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
