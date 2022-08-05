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

	$bcc_count = 0;
	if($db->select_ex($result, rpv("
		SELECT
			COUNT(DISTINCT m.`inv_no`) AS bcc_count
		FROM @mac AS m
		WHERE
			(m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_INV_BCCDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})) = ({%MF_INV_BCCDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})
	")))
	{
		$bcc_count = intval($result[0][0]);
	}
	
	$table = '<table>';
	$table .= '<tr><th>Филиал ID</th><th>Местоположение ID</th><th>Инв. № ДКС</th><th>Маршрутизатор</th><th>SN</th><th>IP</th><th>Inv.NO</th><th>Updated</th><th>Exist at Zabbix</th></tr>';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			m.`branch_no`,
			m.`loc_no`,
			m.`inv_no` AS m_inv_no,
			dm.`id`,
			dm.`name`,
			dm.`mac`,
			dm.`ip`,
			dm.`inv_no` AS dm_inv_no,
			DATE_FORMAT(dm.`date`, '%d.%m.%Y %H:%i:%s') AS `last_update`,
			zh.`flags` AS zh_flags
		FROM @mac AS m
		LEFT JOIN @mac AS dm
			ON (dm.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})) = ({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})
			AND dm.`port` = 'self'
			AND dm.`branch_no` = m.`branch_no`
			AND dm.`loc_no` = m.`loc_no`
		LEFT JOIN @zabbix_hosts AS zh
			ON zh.`pid` = dm.`id`
		WHERE
			(m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_INV_BCCDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})) = ({%MF_INV_BCCDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})
		GROUP BY m.`branch_no`, m.`loc_no`, m.`inv_no`
		ORDER BY m.`branch_no`, m.`loc_no`, m.`inv_no`
	")))
	{
		foreach($result as &$row)
		{
			if((intval($row['zh_flags']) & ZHF_EXIST_IN_ZABBIX) == 0)
			{
				$table .= '<tr>';
				$table .= '<td>'.$row['branch_no'].'</td>';
				$table .= '<td>'.$row['loc_no'].'</td>';
				$table .= '<td><a href="'.CDB_URL.'-ui/cdb_ui.php?path=invents/0/'.$row['m_inv_no'].'">'.$row['m_inv_no'].'</a></td>';
				$table .= '<td>'.$row['name'].'</td>';
				$table .= '<td><a href="'.CDB_URL.'-ui/cdb_ui.php?path=mac_info/'.$row['id'].'">'.$row['mac'].'</a></td>';
				$table .= '<td>'.$row['ip'].'</td>';
				$table .= '<td>'.$row['dm_inv_no'].'</td>';
				$table .= '<td>'.$row['last_update'].'</td>';
				$table .= (intval($row['zh_flags']) & ZHF_EXIST_IN_ZABBIX) ? '<td class="pass">TRUE</td>' : '<td class="error">FALSE</td>';
				$table .= '</tr>';
			}

			$i++;
		}
	}

	$table .= '</table>';

	$html .= '<p>';
	$html .= 'Всего ДКС по данным из ИТ Инвент: '.$bcc_count.'<br />';
	$html .= 'Выборка в таблице по местоположению и инвентарным номерам: '.$i;
	$html .= '<p />';
	$html .= $table;
	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-itinvent-bcc">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if(php_mailer(REPORT_ITINVENT_BCC_MAIL_TO, CDB_TITLE.': List BCCs', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
