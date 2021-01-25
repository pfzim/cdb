<?php
	// Create report LAPS

	/**
		\file
		\brief Отчёт по ПК с неработающим LAPS.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nreport-laps:\n";

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
	<h1>Список ПК с отсутствующем либо просроченным паролем локального администратора</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Name</th><th>HD Task</th></tr>';

	$i = 0;
	$opened = 0;

	//		AND m.`dn` LIKE '%".LDAP_OU_COMPANY."'
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`name`, m.`dn`, m.`laps_exp`, j1.`flags`, j1.`operid`, j1.`opernum`
		FROM @computers AS m
		LEFT JOIN @tasks AS j1 ON j1.pid = m.id AND (j1.flags & (0x0001 | 0x0800)) = 0x0800
		WHERE
			(m.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
			AND m.`laps_exp` < DATE_SUB(NOW(), INTERVAL # DAY)
		GROUP BY m.`id`
		ORDER BY m.`name`
	", LAPS_EXPIRE_DAYS)))
	{
		foreach($result as &$row)
		{
			$table .= '<tr><td>'.$row['name'].'</td><td>';
			if(intval($row['flags']) & 0x0800)
			{
				$table .= '<a href="'.HELPDESK_URL.'/QueryView.aspx?KeyValue='.$row['operid'].'">'.$row['opernum'].'</a>';
				$opened++;
			}
			$table .= '</td></tr>';
			$i++;
		}
	}

	echo 'Count: '.$i."\r\n";

	$table .= '</table>';
	$html .= '<p>Открытых заявок: '.$opened.', всего проблемных ПК : '.$i.'</p>';
	$html .= $table;

	$html .= '<br /><small>Для перезапуска отчёта:<br /><br />1. <a href="'.CDB_URL.'/cdb.php?action=sync-ad">Выполнить синхронизацию с AD</a><br />2. <a href="'.CDB_URL.'/cdb.php?action=report-laps">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if($i > 0)
	{
		if(php_mailer(array(MAIL_TO_ADMIN), CDB_TITLE.': Computers with expired LAPS password', $html, 'You client does not support HTML'))
		{
			echo 'Send mail: OK';
		}
		else
		{
			echo 'Send mail: FAILED';
		}
	}
