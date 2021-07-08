<?php
	/**
		\file
		\brief Отчёт по ПК, на которых давно не устанавливались обновления.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nreport-wsus:\n";
	
	global $g_comp_short_flags;
	global $g_comp_flags;

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
		</style>
	</head>
	<body>
	<h1>Список ПК, на которые давно не устанавливались обновления</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Name</th><th>OS</th><th>Version</th><th>Flags</th></tr>';

	$i = 0;

	if($db->select_assoc_ex($result, rpv("
			SELECT
				c.`id`,
				c.`name`,
				c.`flags`,
				j_os.`value` AS `os`,
				j_ver.`value` AS `ver`
			FROM @computers AS c
			LEFT JOIN @properties_int AS j_up
				ON j_up.`tid` = 1
				AND j_up.`pid` = c.`id`
				AND j_up.`oid` = #
			LEFT JOIN @properties_str AS j_os
				ON j_os.`tid` = 1
				AND j_os.`pid` = c.`id`
				AND j_os.`oid` = #
			LEFT JOIN @properties_str AS j_ver
				ON j_ver.`tid` = 1
				AND j_ver.`pid` = c.`id`
				AND j_ver.`oid` = #
			WHERE
				(c.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
				AND j_up.`value` <> 1
				AND c.`name` NOT REGEXP {s3}
		",
		CDB_PROP_BASELINE_COMPLIANCE_HOTFIX,
		CDB_PROP_OPERATINGSYSTEM,
		CDB_PROP_OPERATINGSYSTEMVERSION,
		CDB_REGEXP_SERVERS
	)))
	{
		foreach($result as &$row)
		{
			$table .= '<tr>';
			$table .= '<td>'.$row['name'].'</td>';
			$table .= '<td>'.$row['os'].'</td>';
			$table .= '<td>'.$row['ver'].'</td>';
			$table .= '<td>'.flags_to_string(intval($row['flags']), $g_comp_short_flags, '', '-').'</td>';
			$table .= '</tr>';
			$i++;
		}
	}

	echo 'Count: '.$i."\r\n";

	$table .= '</table>';
	$html .= '<p>Всего проблемных ПК : '.$i.'</p>';
	$html .= '<b>Описание флагов:</b>';
	$html .= '<pre>'.flags_to_legend($g_comp_short_flags, $g_comp_flags, "\n").'</pre>';
	
	$html .= $table;

	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-wsus">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if($i > 0)
	{
		if(php_mailer(REPORT_WSUS_MAIL_TO, CDB_TITLE.': Computers without updates', $html, 'You client does not support HTML'))
		{
			echo 'Send mail: OK';
		}
		else
		{
			echo 'Send mail: FAILED';
		}
	}
