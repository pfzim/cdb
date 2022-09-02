<?php
	// Report for opened HelpDesk tasks IT Invent

	/**
		\file
		\brief Формирование отчёта по открытым заявкам на устранение ошибок на портах коммутаторов (neterrors).
	*/


	if(!defined('Z_PROTECTED')) exit;

	global $g_mac_short_flags;
	global $g_tasks_types;

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
	<h1>Отчёт по ранее выставленным не закрытым заявкам в HelpDesk (NetErrors)</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>HD Task</th><th>HD Date</th><th>netdev</th><th>Port</th><th>SingleCollisionFrames</th><th>CarrierSenseErrors</th><th>InErrors</th><th>Last update</th><th>Reason</th><th>Issues</th></tr>';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			d.`id`,
			d.`name` AS `d_name`,
			t.`opernum`,
			t.`operid`,
			DATE_FORMAT(t.`date`, '%d.%m.%Y') AS `t_date`,
			t.`flags` AS `t_flags`,
			t.`type`,
			(
				SELECT COUNT(*)
				FROM @tasks AS t2
				WHERE t2.`pid` = t.`pid`
					AND t2.`type` = t.`type`
					AND (t2.`flags` & {%TF_CLOSED}) = {%TF_CLOSED}
					AND t2.`date` > DATE_SUB(NOW(), INTERVAL 1 MONTH)
			) AS `issues`
		FROM @tasks AS t
		LEFT JOIN @devices AS d
			ON
				d.`id` = t.`pid`
				AND d.`type` = {%DT_NETDEV}
		WHERE
			t.`tid` = {%TID_DEVICES}
			AND t.`type` = {%TT_NET_ERRORS}
			AND (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0
		ORDER BY t.`type`, d.`name`
	")))
	{

		foreach($result as &$row)
		{
			$rows_count = 0;
			$ports_errors_first = '';
			$ports_errors = '';

			if($db->select_assoc_ex($net_errors, rpv("
					SELECT
						e.`port`,
						DATE_FORMAT(e.`date`, '%d.%m.%Y %H:%i:%s') AS `last_update`,
						e.`scf`,
						e.`cse`,
						e.`ine`
					FROM @net_errors AS e
					WHERE
						e.`pid` = #
						-- AND (e.`flags` & {%NEF_FIXED}) = 0
					ORDER BY e.`port`
				",
				$row['id']
			)))
			{
				foreach($net_errors as &$ne_row)
				{
					if($ports_errors_first == '')
					{
						$ports_errors_first .= '<td>'.$ne_row['port'].'</td>';
						$ports_errors_first .= '<td>'.$ne_row['scf'].'</td>';
						$ports_errors_first .= '<td>'.$ne_row['cse'].'</td>';
						$ports_errors_first .= '<td>'.$ne_row['ine'].'</td>';
						$ports_errors_first .= '<td>'.$ne_row['last_update'].'</td>';
					}
					else
					{
						$ports_errors .= '<tr>';
						$ports_errors .= '<td>'.$ne_row['port'].'</td>';
						$ports_errors .= '<td>'.$ne_row['scf'].'</td>';
						$ports_errors .= '<td>'.$ne_row['cse'].'</td>';
						$ports_errors .= '<td>'.$ne_row['ine'].'</td>';
						$ports_errors .= '<td>'.$ne_row['last_update'].'</td>';
						$ports_errors .= '</tr>';
					}

					$rows_count++;
				}
			}
			
			$table .= '<tr>';
			$table .= '<td rowspan="'.$rows_count.'"><a href="'.HELPDESK_URL.'/QueryView.aspx?KeyValue='.$row['operid'].'">'.$row['opernum'].'</a></td>';
			$table .= '<td rowspan="'.$rows_count.'">'.$row['t_date'].'</td>';
			$table .= '<td rowspan="'.$rows_count.'">'.$row['d_name'].'</td>';
			$table .= $ports_errors_first;
			$table .= '<td rowspan="'.$rows_count.'">'.code_to_string($g_tasks_types, intval($row['type'])).'</td>';
			$table .= '<td rowspan="'.$rows_count.'"'.((intval($row['issues']) > 1)?' class="error"':'').'>'.$row['issues'].'</td>';
			$table .= '</tr>';
			$table .= $ports_errors;

			$i++;
		}
	}

	$table .= '</table>';


	if($db->select_assoc_ex($result, rpv("
		SELECT
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_NET_ERRORS}) AS `o_net_errors`,

			(
				SELECT 
					COUNT(*)
				FROM @devices AS d
				LEFT JOIN @net_errors AS e ON
					e.`pid` = d.`id`
				WHERE
					d.`type` = {%DT_NETDEV}
					AND (d.`flags` & ({%DF_DELETED} | {%DF_HIDED})) = 0    -- Not deleted, not hide
					AND (e.`flags` & {%NEF_FIXED}) = 0
					AND e.`port` <> 'FastEthernet4'
					AND (
					  -- e.`scf` > 10
					  -- OR 
					  e.`cse` > 10
					  -- OR e.`ine` > 10
					)
			) AS `p_net_errors`
	")))
	{
		$html .= '<table>';
		$html .= '<tr><th>Описание</th>                                                   <th>Несоответствий</th>                       <th>Открыто заявок</th>                        <th>Лимит заявок</th></tr>';
		$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_NET_ERRORS).'</td>          <td>'.$result[0]['p_net_errors'].'</td>       <td>'.$result[0]['o_net_errors'].'</td>       <td>'.TASKS_LIMIT_NET_ERRORS.'</td></tr>';
		$html .= '</table>';
		$html .= '<br />';
	}
	
	//$html .= '<p>Обозначения: R - исключен из проверок навсегда, T - временно исключен, I - from IT Invent, N - from netdev, A - active in IT Invent, S - серийный номер, M - перемещаемое устройство, D - обнаружены дубликаты в ИТ Инвент</p>';
	$html .= $table;
	$html .= '<br /><small>Для перезапуска отчёта:<br />1. <a href="'.CDB_URL.'/cdb.php?action=check-tasks-status">Обновить статус заявок из системы HelpDesk</a><br />2. <a href="'.CDB_URL.'/cdb.php?action=report-tasks-neterrors">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	echo 'Opened: '.$i."\r\n";

	if(php_mailer(REPORT_NET_ERRORS_MAIL_TO, CDB_TITLE.': Opened tasks NetErrors', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
