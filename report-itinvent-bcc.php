<?php
	// Report Backup communication channel (ДКС - Дублирующие Каналы Связи)

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
	<h1>Отчёт по Дублирующим Каналам Связи (Backup Communication Channels)</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Name</th><th>SN</th><th>IP</th><th>Inv.NO</th><th>Updated</th><th>Exist at Zabbix</th></tr>';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT 
			m.`id`,
			m.`name`,
			m.`mac`,
			m.`ip`,
			m.`inv_no`,
			DATE_FORMAT(m.`date`, '%d.%m.%Y %H:%i:%s') AS `last_update`,
			BIT_OR(zh.`flags`) AS zh_flags
		FROM @mac AS m
		LEFT JOIN @zabbix_hosts AS zh
			ON zh.`pid` = m.`id`
		WHERE
			m.`loc_no` IN (
				SELECT DISTINCT m2.`loc_no`
				FROM @mac AS m2
				WHERE
					(m2.`flags` & ({%MF_INV_BCCDEV} | {%MF_INV_ACTIVE})) = ({%MF_INV_BCCDEV} | {%MF_INV_ACTIVE})
					AND m2.`loc_no` <> 0
			)
			AND m.`port` = 'self'
		GROUP BY m.`id`
		ORDER BY m.`name`
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
			$table .= (intval($row['zh_flags']) & ZHF_EXIST_IN_ZABBIX) ? '<td class="pass">TRUE</td>' : '<td class="error">FALSE</td>';
			$table .= '</tr>';

			$i++;
		}
	}

	$table .= '</table>';

	$html .= '<p>Всего активных ДКС: '.$i.'</p><p />';
	$html .= $table;
	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-itinvent-bcc">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if(php_mailer(array(MAIL_TO_ADMIN, MAIL_TO_NET), CDB_TITLE.': List BCCs', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
