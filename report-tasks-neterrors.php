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
			d.`name` AS `d_name`,
			ne.`port`,
			ne.`scf`,
			ne.`cse`,
			ne.`ine`,
			DATE_FORMAT(ne.`date`, '%d.%m.%Y %H:%i:%s') AS `last_update`,
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
		LEFT JOIN @net_errors AS ne ON ne.`id` = t.`pid`
		LEFT JOIN @devices AS d ON d.`id` = ne.`pid`
		WHERE
			t.`tid` = {%TID_DEVICES}
			AND t.`type` = {%TT_NET_ERRORS}
			AND (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0
		ORDER BY t.`type`, d.`name`, ne.`port`
	")))
	{

		foreach($result as &$row)
		{
			$table .= '<tr>';
			$table .= '<td><a href="'.HELPDESK_URL.'/QueryView.aspx?KeyValue='.$row['operid'].'">'.$row['opernum'].'</a></td>';
			$table .= '<td>'.$row['t_date'].'</td>';
			$table .= '<td>'.$row['d_name'].'</td>';
			$table .= '<td>'.$row['port'].'</td>';
			$table .= '<td>'.$row['scf'].'</td>';
			$table .= '<td>'.$row['cse'].'</td>';
			$table .= '<td>'.$row['ine'].'</td>';
			$table .= '<td>'.$row['last_update'].'</td>';
			$table .= '<td>'.code_to_string($g_tasks_types, intval($row['type'])).'</td>';
			$table .= '<td'.((intval($row['issues']) > 1)?' class="error"':'').'>'.$row['issues'].'</td>';
			$table .= '</tr>';

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
