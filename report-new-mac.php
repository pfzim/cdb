<?php
	// Report detected MAC addresses

	/**
		\file
		\brief Формирование отчёта по обнаруженным MAC адресам за сутки.
	*/


	if(!defined('Z_PROTECTED')) exit;

	global $g_mac_short_flags;

	echo "\nreport-new-mac:\n";

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
	<h1>Отчёт по обнаруженным за прошедшие сутки MAC адресам</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>MAC/SN</th><th>Name</th><th>IP</th><th>netdev</th><th>Port</th><th>VLAN</th><th>First seen</th><th>Flags</th></tr>';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			m.`id`,
			m.`mac`,
			m.`name` AS `m_name`,
			d.`name` AS `d_name`,
			m.`ip`,
			m.`port`,
			m.`vlan`,
			DATE_FORMAT(m.`first`, '%d.%m.%Y %H:%i:%s') AS `first_seen`,
			m.`flags` AS `m_flags`
		FROM @mac AS m
		LEFT JOIN @devices AS d ON d.`id` = m.`pid` AND d.`type` = 3
		WHERE
			(m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_EXIST_IN_ITINV} | {%MF_FROM_NETDEV})) = {%MF_FROM_NETDEV}
			AND
			m.`first` >= DATE_SUB(NOW(), INTERVAL 1 DAY)
		ORDER BY d.`name`, m.`first`
	")))
	{

		foreach($result as &$row)
		{
			$table .= '<tr>';
			$table .= '<td><a href="'.CDB_URL.'/cdb.php?action=get-mac-info&id='.$row['id'].'">'.$row['mac'].'</a></td>';
			$table .= '<td>'.$row['m_name'].'</td>';
			$table .= '<td>'.$row['ip'].'</td>';
			$table .= '<td>'.$row['d_name'].'</td>';
			$table .= '<td>'.$row['port'].'</td>';
			$table .= '<td>'.$row['vlan'].'</td>';
			$table .= '<td>'.$row['first_seen'].'</td>';
			$table .= '<td>'.flags_to_string(intval($row['m_flags']), $g_mac_short_flags, '', '-').'</td>';
			$table .= '</tr>';

			$i++;
		}
	}

	$table .= '</table>';

	$html .= '<p>Обозначения: R - исключен из проверок навсегда, T - временно исключен, I - from IT Invent, N - from netdev, A - active in IT Invent, S - серийный номер</p>';
	$html .= $table;
	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-new-mac">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	echo 'Opened: '.$i."\r\n";

	if(php_mailer(array(MAIL_TO_ADMIN, MAIL_TO_NET, MAIL_TO_INVENT, MAIL_TO_RITM), CDB_TITLE.': New MAC addresses', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
