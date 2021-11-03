<?php
	// Report Backup communication channel (ДКС - Дежурные Каналы Связи)

	/**
		\file
		\brief Формирование отчёта по установленным ДКС.
	*/


	if(!defined('Z_PROTECTED')) exit;

	echo "\nreport-itinvent-bcc:\n";

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
	<h1>Отчёт по Дежурным Каналам Связи</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Name</th><th>SN</th><th>IP</th><th>Inv.NO</th><th>Updated</th></tr>';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
	SELECT 
		`name`,
		`mac`,
		`ip`,
		`inv_no`,
		DATE_FORMAT(`date`, '%d.%m.%Y %H:%i:%s') AS `last_update`
	FROM @mac
	WHERE `loc_no` IN 
		(SELECT DISTINCT `loc_no`
		FROM @mac
		WHERE (`flags` & 0x0400) > 0 AND (`flags` & 0x0040) > 0 AND `loc_no` <> 0)
	AND PORT LIKE 'self'
	")))
	{

		foreach($result as &$row)
		{
			$table .= '<tr>';
			$table .= '<td>'.$row['name'].'</td>';
			$table .= '<td><a href="'.CDB_URL.'/cdb.php?action=get-mac-info&id='.$row['id'].'">'.$row['mac'].'</a></td>';
			$table .= '<td>'.$row['ip'].'</td>';
			$table .= '<td>'.$row['inv_no'].'</td>';
			$table .= '<td>'.$row['last_update'].'</td>';
			$table .= '</tr>';

			$i++;
		}
	}

	$table .= '</table>';

	$html .= '<p>Всего активных ДКС: '.$i.'</p><p />';
	$html .= $table;
	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-itinvent-bcc">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	echo 'Total BCC: '.$i."\r\n";

	if(php_mailer(array(MAIL_TO_ADMIN, MAIL_TO_NET), CDB_TITLE.': List BCCs', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
