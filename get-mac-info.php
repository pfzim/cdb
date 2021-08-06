<?php
	// Get MAC (IT Invent) info

	/**
		\file
		\brief Информационная страница о MAC адресе.
	*/


	if(!defined('Z_PROTECTED')) exit;

	header("Content-Type: text/html; charset=utf-8");

	global $g_mac_flags;
	global $g_tasks_flags;
	
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
	<h1>Информация об обнаруженном сетевом устройстве</h1>
EOT;

	if(!empty($_GET['id']))
	{
		if(!$db->select_assoc_ex($mac, rpv("
			SELECT
				m.`id`,
				m.`mac`,
				m.`inv_no`,
				m.`ip`,
				m.`name`,
				d.`name` AS netdev,
				m.`port`,
				m.`vlan`,
				DATE_FORMAT(m.`date`, '%d.%m.%Y %H:%i:%s') AS last_seen,
				DATE_FORMAT(m.`first`, '%d.%m.%Y %H:%i:%s') AS first_seen,
				m.`flags`
			FROM
				@mac AS m
			LEFT JOIN
				@devices AS d
				ON
					d.`id` = m.`pid`
			WHERE
				m.`id` = #
			",
			$_GET['id']))
		)
		{
			exit;
		}
	}
	else if(!empty($_GET['name']))
	{
		if(!$db->select_assoc_ex($mac, rpv("
			SELECT
				m.`id`,
				m.`mac`,
				m.`inv_no`,
				m.`ip`,
				m.`name`,
				d.`name` AS netdev,
				m.`port`,
				m.`vlan`,
				DATE_FORMAT(m.`date`, '%d.%m.%Y %H:%i:%s') AS last_seen,
				DATE_FORMAT(m.`first`, '%d.%m.%Y %H:%i:%s') AS first_seen,
				m.`flags`
			FROM
				@mac AS m
			LEFT JOIN
				@devices AS d
				ON
					d.`id` = m.`pid`
			WHERE
				m.`name` = ! LIMIT 1
			",
			$_GET['name']))
		)
		{
			exit;
		}
	}
	else if(!empty($_GET['mac']))
	{
		if(!$db->select_assoc_ex($mac, rpv("
			SELECT
				m.`id`,
				m.`mac`,
				m.`inv_no`,
				m.`ip`,
				m.`name`,
				d.`name` AS netdev,
				m.`port`,
				m.`vlan`,
				DATE_FORMAT(m.`date`, '%d.%m.%Y %H:%i:%s') AS last_seen,
				DATE_FORMAT(m.`first`, '%d.%m.%Y %H:%i:%s') AS first_seen,
				m.`flags`
			FROM
				@mac AS m
			LEFT JOIN
				@devices AS d
				ON
					d.`id` = m.`pid`
			WHERE
				m.`mac` = !
			ORDER BY (m.`flags` & 0x0080) = 0x0080
			LIMIT 1
			",
			strtolower(preg_replace('/[^0-9a-f]/i', '', $_GET['mac']))))
		)
		{
			exit;
		}
	}
	else if(!empty($_GET['sn']))
	{
		if(!$db->select_assoc_ex($mac, rpv("
			SELECT
				m.`id`,
				m.`mac`,
				m.`inv_no`,
				m.`ip`,
				m.`name`,
				d.`name` AS netdev,
				m.`port`,
				m.`vlan`,
				DATE_FORMAT(m.`date`, '%d.%m.%Y %H:%i:%s') AS last_seen,
				DATE_FORMAT(m.`first`, '%d.%m.%Y %H:%i:%s') AS first_seen,
				m.`flags`
			FROM
				@mac AS m
			LEFT JOIN
				@devices AS d
				ON
					d.`id` = m.`pid`
			WHERE
				m.`mac` = !
				AND (m.`flags` & 0x0080)
			LIMIT 1
			",
			$_GET['sn']))
		)
		{
			exit;
		}
	}
	else
	{
		exit;
	}

	$db->select_assoc_ex($tasks, rpv("
			SELECT
				t.`id`,
				t.`pid`,
				t.`flags`,
				t.`date`,
				t.`operid`,
				t.`opernum`
			FROM
				@tasks AS t
			WHERE
				t.`tid` = 3
				AND t.`pid` = #
			ORDER BY t.`date`
		",
		$mac[0]['id'])
	);
	
	$html .= '<p>MAC/SN: '.$mac[0]['mac'].'</p>';
	$html .= '<p>Inv.No: '.$mac[0]['inv_no'].'</p>';
	$html .= '<p>Hostname: '.$mac[0]['name'].'</p>';
	$html .= '<p>IP: '.$mac[0]['ip'].'</p>';
	$html .= '<p>VLAN: '.$mac[0]['vlan'].'</p>';
	$html .= '<p>NetDev: '.$mac[0]['netdev'].'</p>';
	$html .= '<p>First seen: '.$mac[0]['first_seen'].'</p>';
	$html .= '<p>Last seen: '.$mac[0]['last_seen'].'</p>';
	$html .= '<p>Flags: '.flags_to_string(intval($mac[0]['flags']), $g_mac_flags, ', ').'</p>';
	
	$table = '<table>';
	$table .= '<tr><th>Date</th><th>HD Task</th><th>Reason</th></tr>';


	foreach($tasks as &$row)
	{
		$table .= '<tr>';
		$table .= '<td>'.$row['date'].'</td>';
		$table .= '<td><a target= "_blank" href="'.HELPDESK_URL.'/QueryView.aspx?KeyValue='.$row['operid'].'">'.$row['opernum'].'</a></td>';
		$table .= '<td>'.flags_to_string(intval($row['flags']), $g_tasks_flags, ', ').'</td>';
		$table .= '</tr>';
	}

	$table .= '</table>';
	$html .= $table;
	$html .= '</body>';

	echo $html;
