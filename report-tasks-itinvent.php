<?php
	// Report for opened HelpDesk tasks IT Invent

	if(!defined('Z_PROTECTED')) exit;

	global $g_mac_short_flags;

	echo "\nreport-tasks-itinvent:\n";

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
	<h1>Отчёт по ранее выставленным не закрытым заявкам в HelpDesk (IT Invent)</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>netdev</th><th>Name</th><th>MAC/SN</th><th>IP</th><th>Last seen</th><th>HD Task</th><th>Reason</th><th>Source</th></tr>';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			m.`id`,
			m.`name` AS `m_name`,
			d.`name` AS `d_name`,
			m.`ip`,
			DATE_FORMAT(m.`date`, '%d.%m.%Y %H:%i:%s') AS `last_seen`,
			m.`mac`,
			t.`opernum`,
			t.`operid`,
			t.`flags` AS `t_flags`,
			m.`flags` AS `m_flags`
		FROM @tasks AS t
		LEFT JOIN @mac AS m ON m.`id` = t.`pid`
		LEFT JOIN @devices AS d ON d.`id` = m.`pid`
		WHERE
			t.`tid` = 3
			AND (t.`flags` & 0x0001) = 0
		ORDER BY d.`name`, m.`name`
	")))
	{

		foreach($result as &$row)
		{
			$table .= '<tr>';
			$table .= '<td>'.$row['d_name'].'</td>';
			$table .= '<td>'.$row['m_name'].'</td>';
			$table .= '<td>'.$row['mac'].'</td>';
			$table .= '<td>'.$row['ip'].'</td>';
			$table .= '<td>'.$row['last_seen'].'</td>';
			$table .= '<td><a href="'.HELPDESK_URL.'/QueryView.aspx?KeyValue='.$row['operid'].'">'.$row['opernum'].'</a></td>';
			$table .= '<td>'.tasks_flags_to_string(intval($row['t_flags'])).'</td>';
			$table .= '<td>'.flags_to_string(intval($row['m_flags']) & 0x00F0, $g_mac_short_flags, '', '-').'</td>';
			$table .= '</tr>';

			$i++;
		}
	}

	$table .= '</table>';


	if($db->select_assoc_ex($result, rpv("
		SELECT
		(SELECT COUNT(*) FROM @mac AS m WHERE (m.`flags` & (0x0002 | 0x0010 | 0x0020)) = 0x0020) AS `inv_problems`,
		(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & (0x0001 | 0x8000)) = 0x8000) AS `inv_opened`
	")))
	{
		$html .= '<p>';
		$html .= 'Открытых заявок: '.$result[0]['inv_opened'].', MAC адресов не добавленных в IT Invent: '.$result[0]['inv_problems'];
		$html .= '</p>';
	}
	
	$html .= '<p>Обозначения: R - удалён, I - from IT Invent, N - from netdev, A - active in IT Invent, S - серийный номер</p>';
	$html .= $table;
	$html .= '<br /><small>Для перезапуска отчёта:<br />1. <a href="'.CDB_URL.'/cdb.php?action=check-tasks-status">Обновить статус заявок из системы HelpDesk</a><br />2. <a href="'.CDB_URL.'/cdb.php?action=report-tasks-itinvent">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	echo 'Opened: '.$i."\r\n";

	if(php_mailer(array(MAIL_TO_ADMIN, MAIL_TO_INVENT), CDB_TITLE.': Opened tasks IT Invent', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
