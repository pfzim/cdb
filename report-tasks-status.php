<?php
	// Report for opened HelpDesk tasks

	if(!defined('Z_PROTECTED')) exit;

	echo "\nreport-tasks-status:\n";

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
	<h1>Отчёт по ранее выставленным не закрытым заявкам в HelpDesk</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Name</th><th>AV Pattern version</th><th>Last update</th><th>TMEE Status</th><th>TMEE Last sync</th><th>HD Task</th><th>Reason</th></tr>';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT j1.`id`, j1.`name`, j1.`ao_script_ptn`, DATE_FORMAT(j1.`ao_ptnupdtime`, '%d.%m.%Y %H:%i:%s') AS `last_update`, j1.`ee_encryptionstatus`, DATE_FORMAT(j1.`ee_lastsync`, '%d.%m.%Y %H:%i:%s') AS `last_sync`, m.`operid`, m.`opernum`, m.`flags`
		FROM @tasks AS m
		LEFT JOIN @computers AS j1 ON j1.`id` = m.`pid`
		WHERE (m.`flags` & 0x0001) = 0
		ORDER BY j1.`name`
	")))
	{
		foreach($result as &$row)
		{
			$table .= '<tr><td>'.$row['name'].'</td><td>'.$row['ao_script_ptn'].'</td><td>'.$row['last_update'].'</td>';
			$table .= '<td>'.tmee_status(intval($row['ee_encryptionstatus'])).'</td><td>'.$row['last_sync'].'</td>';
			$table .= '<td><a href="'.HELPDESK_URL.'/QueryView.aspx?KeyValue='.$row['operid'].'">'.$row['opernum'].'</a></td>';
			$table .= '<td>'.tasks_flags_to_string(intval($row['flags'])).'</td>';
			$table .= '</tr>';

			$i++;
		}
	}

	$table .= '</table>';

	$problems_tmao = 0;
	$problems_tmee = 0;
	$opened_tmao = 0;
	$opened_tmee = 0;

	if($db->select_ex($result, rpv("
		SELECT
		(SELECT COUNT(*) FROM @computers WHERE (`flags` & (0x0001 | 0x0002 | 0x0004)) = 0 AND `name` regexp '^(([[:digit:]]{4}-[nNwW])|([Pp][Cc]-))[[:digit:]]+$' AND `ao_script_ptn` < ((SELECT MAX(`ao_script_ptn`) FROM @computers) - 2900)) AS `c1`,
		(SELECT COUNT(*) FROM @computers WHERE `name` regexp '^[[:digit:]]{4}-[nN][[:digit:]]+' AND (`flags` & (0x0001 | 0x0002 | 0x0004)) = 0 AND `ee_encryptionstatus` <> 2) AS `c2`,
		(SELECT COUNT(*) FROM @tasks WHERE (`flags` & (0x0001 | 0x0200)) = 0x0200) AS `c3`,
		(SELECT COUNT(*) FROM @tasks WHERE (`flags` & (0x0001 | 0x0100)) = 0x0100) AS `c4`
	")))
	{
		$problems_tmao = $result[0][0];
		$problems_tmee = $result[0][1];
		$opened_tmao = $result[0][2];
		$opened_tmee = $result[0][3];
	}

	$html .= '<p>TMAO открытых заявок: '.$opened_tmao.', всего проблемных ПК : '.$problems_tmao.'<br />TMEE открытых заявок: '.$opened_tmee.', всего проблемных ПК : '.$problems_tmee.'</p>';
	$html .= $table;
	$html .= '<br /><small>Для перезапуска отчёта:<br />1. <a href="'.CDB_URL.'/cdb.php?action=check-tasks-status">Обновить статус заявок из системы HelpDesk</a><br />2. <a href="'.CDB_URL.'/cdb.php?action=report-tasks-status">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	echo 'Opened: '.$i."\r\n";

	if(php_mailer(array(MAIL_TO_ADMIN), CDB_TITLE.': Opened tasks', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
