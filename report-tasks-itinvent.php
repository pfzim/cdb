<?php
	// Report for opened HelpDesk tasks IT Invent

	/**
		\file
		\brief Формирование отчёта по открытым заявкам на добавление оборудования в IT Invent.
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
	<h1>Отчёт по ранее выставленным не закрытым заявкам в HelpDesk (IT Invent)</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>netdev</th><th>Port</th><th>VLAN</th><th>Name</th><th>MAC/SN</th><th>IP</th><th>Last seen</th><th>HD Task</th><th>HD Date</th><th>Reason</th><th>Source</th><th>Issues</th></tr>';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			m.`id`,
			m.`name` AS `m_name`,
			d.`name` AS `d_name`,
			m.`port`,
			m.`vlan`,
			m.`ip`,
			DATE_FORMAT(m.`date`, '%d.%m.%Y %H:%i:%s') AS `last_seen`,
			m.`mac`,
			t.`opernum`,
			t.`operid`,
			DATE_FORMAT(t.`date`, '%d.%m.%Y') AS `t_date`,
			t.`flags` AS `t_flags`,
			t.`type`,
			m.`flags` AS `m_flags`,
			(
				SELECT COUNT(*)
				FROM @tasks AS t2
				WHERE t2.`pid` = t.`pid`
					AND t2.`tid` = {%TID_MAC}
					AND t2.`type` = t.`type`
					AND (t2.`flags` & {%TF_CLOSED}) = {%TF_CLOSED}
					AND t2.`date` > DATE_SUB(NOW(), INTERVAL 1 MONTH)
			) AS `issues`
		FROM @tasks AS t
		LEFT JOIN @mac AS m ON m.`id` = t.`pid`
		LEFT JOIN @devices AS d ON d.`id` = m.`pid`
		WHERE
			t.`tid` = {%TID_MAC}
			AND (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0
		ORDER BY t.`flags`, d.`name`, m.`port`, m.`name`
	")))
	{

		foreach($result as &$row)
		{
			$table .= '<tr>';
			$table .= '<td>'.$row['d_name'].'</td>';
			$table .= '<td>'.$row['port'].'</td>';
			$table .= '<td>'.$row['vlan'].'</td>';
			$table .= '<td>'.$row['m_name'].'</td>';
			$table .= '<td><a href="'.CDB_URL.'-ui/cdb_ui.php?path=mac_info/'.$row['id'].'">'.$row['mac'].'</a></td>';
			$table .= '<td>'.$row['ip'].'</td>';
			$table .= '<td>'.$row['last_seen'].'</td>';
			$table .= '<td><a href="'.HELPDESK_URL.'/QueryView.aspx?KeyValue='.$row['operid'].'">'.$row['opernum'].'</a></td>';
			$table .= '<td>'.$row['t_date'].'</td>';
			$table .= '<td>'.code_to_string($g_tasks_types, intval($row['type'])).'</td>';
			$table .= '<td>'.flags_to_string(intval($row['m_flags']), $g_mac_short_flags, '', '-').'</td>';
			$table .= '<td'.((intval($row['issues']) > 1)?' class="error"':'').'>'.$row['issues'].'</td>';
			$table .= '</tr>';

			$i++;
		}
	}

	$table .= '</table>';


	if($db->select_assoc_ex($result, rpv("
		SELECT
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_INV_ADD}) AS `o_inv_add`,
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_INV_ADD_DECOMIS}) AS `o_inv_add_decomis`,
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_INV_MOVE}) AS `o_iimv`,
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_INV_DUP}) AS `o_dup`,

			(SELECT COUNT(*) FROM @mac AS m WHERE (m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_INV_ACTIVE} | {%MF_FROM_NETDEV})) = {%MF_FROM_NETDEV}) AS `p_inv_add`,
			(SELECT COUNT(*) FROM @mac AS m WHERE (m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_EXIST_IN_ITINV} | {%MF_FROM_NETDEV})) = ({%MF_EXIST_IN_ITINV} | {%MF_FROM_NETDEV}) AND m.`status` = 7) AS `p_inv_add_decomis`,
			(
				SELECT COUNT(*)
				FROM @mac AS m
				LEFT JOIN @devices AS d
					ON d.`id` = m.`pid` AND d.`type` = {%DT_NETDEV}
				LEFT JOIN @mac AS dm
					ON
						dm.`name` = d.`name`
						AND (dm.`flags` & ({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE} | {%MF_SERIAL_NUM})) = ({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE} | {%MF_SERIAL_NUM})                    -- Only exist and active in IT Invent
				WHERE
					(m.`flags` & ({%MF_TEMP_EXCLUDED} | {%MF_PERM_EXCLUDED} | {%MF_FROM_NETDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE} | {%MF_INV_MOBILEDEV})) = ({%MF_FROM_NETDEV} | {%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE})    -- Not Temprary excluded, Not Premanently excluded, Exist in IT Invent, Active in IT Invent, Not Mobile device
					AND (
						dm.`branch_no` IS NULL
						OR dm.`loc_no` IS NULL
						OR (
							m.`branch_no` <> dm.`branch_no`
							AND m.`loc_no` <> dm.`loc_no`
						)
					)
			) AS `p_iimv`,

			(
				SELECT
					COUNT(*)
				FROM (
					SELECT
						sq.`id`,
						sq.`type_no`,
						sq.`model_no`,
						COUNT(*) AS `dups`
					FROM (

							SELECT
								m.`id`,
								i.`type_no`,
								i.`model_no`
							FROM c_mac_inv AS mi

							LEFT JOIN c_mac AS m
								ON m.`id` = mi.`mac_id`

							LEFT JOIN c_inv AS i
								ON i.`id` = mi.`inv_id`
							
							WHERE
								(m.`flags` & {%MF_SERIAL_NUM})

						) AS `sq`

					GROUP BY
						sq.`id`,
						sq.`type_no`,
						sq.`model_no`

					HAVING
						`dups` > 1
						
				) AS `sq2`
			) AS `p_dup_sn`,

			(
				SELECT
					COUNT(*)
				FROM (
					SELECT
						sq.`id`,
						sq.`type_no`,
						COUNT(*) AS `dups`
					FROM (

							SELECT
								m.`id`,
								i.`type_no`
							FROM c_mac_inv AS mi

							LEFT JOIN c_mac AS m
								ON m.`id` = mi.`mac_id`

							LEFT JOIN c_inv AS i
								ON i.`id` = mi.`inv_id`
							
							WHERE
								(m.`flags` & {%MF_SERIAL_NUM}) = 0

						) AS `sq`

					GROUP BY
						sq.`id`

					HAVING
						`dups` > 1
				) AS `sq2`
			) AS `p_dup_mac`
	")))
	{
		$html .= '<table>';
		$html .= '<tr><th>Описание</th>                                                   <th>Несоответствий</th>                       <th>Открыто заявок</th>                        <th>Лимит заявок</th></tr>';
		$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_INV_ADD).'</td>             <td>'.$result[0]['p_inv_add'].'</td>          <td>'.$result[0]['o_inv_add'].'</td>           <td>'.TASKS_LIMIT_ITINVENT.'</td></tr>';
		$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_INV_ADD_DECOMIS).'</td>     <td>'.$result[0]['p_inv_add_decomis'].'</td>  <td>'.$result[0]['o_inv_add_decomis'].'</td>   <td>&infin;</td></tr>';
		$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_INV_MOVE).'</td>            <td>'.$result[0]['p_iimv'].'</td>             <td>'.$result[0]['o_iimv'].'</td>              <td>'.TASKS_LIMIT_ITINVENT_MOVE.'</td></tr>';
		$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_INV_DUP).' (SN)</td>        <td>'.$result[0]['p_dup_sn'].'</td>           <td rowspan="2">'.$result[0]['o_dup'].'</td>   <td rowspan="2">'.TASKS_LIMIT_ITINVENT_DUP.'</td></tr>';
		$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_INV_DUP).' (MAC)</td>       <td>'.$result[0]['p_dup_mac'].'</td>                                                         </tr>';
		$html .= '</table>';
	}
	
	$html .= '<p>Обозначения: R - исключен из проверок навсегда, T - временно исключен, I - from IT Invent, N - from netdev, A - active in IT Invent, S - серийный номер, M - перемещаемое устройство, D - обнаружены дубликаты в ИТ Инвент</p>';
	$html .= $table;
	$html .= '<br /><small>Для перезапуска отчёта:<br />1. <a href="'.CDB_URL.'/cdb.php?action=check-tasks-status">Обновить статус заявок из системы HelpDesk</a><br />2. <a href="'.CDB_URL.'/cdb.php?action=report-tasks-itinvent">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	echo 'Opened: '.$i."\r\n";

	if(php_mailer(REPORT_ITINVENT_MAIL_TO, CDB_TITLE.': Opened tasks IT Invent', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
