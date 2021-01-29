<?php
	// Get computer info
	/**
		\file
		\brief Информационная страница об объекте Computer.
	*/


	if(!defined('Z_PROTECTED')) exit;

	header("Content-Type: text/html; charset=utf-8");

	global $g_comp_flags;
	global $g_tasks_flags;
	global $g_ac_flags;
	
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
	<h1>Информация о ПК</h1>
EOT;

	if(!empty($_GET['id']))
	{
		if(!$db->select_assoc_ex($computer, rpv("SELECT m.`id`, m.`name`, m.`dn`, m.`ao_ptnupdtime`, m.`ao_script_ptn`, m.`ao_as_pstime`, m.`ee_lastsync`, m.`ee_encryptionstatus`, m.`laps_exp`, m.`flags` FROM @computers AS m WHERE m.`id` = #", $_GET['id'])))
		{
			exit;
		}
	}
	else
	{
		if(!$db->select_assoc_ex($computer, rpv("SELECT m.`id`, m.`name`, m.`dn`, m.`ao_ptnupdtime`, m.`ao_script_ptn`, m.`ao_as_pstime`, m.`ee_lastsync`, m.`ee_encryptionstatus`, m.`laps_exp`, m.`flags` FROM @computers AS m WHERE m.`name` = ! LIMIT 1", $_GET['name'])))
		{
			exit;
		}
	}

	
	$html .= '<p>Name: '.$computer[0]['name'].'</p>';
	$html .= '<p>DN: '.$computer[0]['dn'].'</p>';
	$html .= '<p>Apex One last pattern update: '.$computer[0]['ao_ptnupdtime'].'</p>';
	$html .= '<p>Apex One pattern version: '.$computer[0]['ao_script_ptn'].'</p>';
	$html .= '<p>Apex One last full scan: '.$computer[0]['ao_as_pstime'].'</p>';
	$html .= '<p>Encryption Endpoint last sync: '.$computer[0]['ee_lastsync'].'</p>';
	$html .= '<p>Encryption Endpoint status: '.tmee_status(intval($computer[0]['ee_encryptionstatus'])).'</p>';
	$html .= '<p>LAPS expire time: '.$computer[0]['laps_exp'].'</p>';
	$html .= '<p>Flags: '.flags_to_string(intval($computer[0]['flags']), $g_comp_flags, ', ').'</p>';
	$html .= '<p>Action: '.((intval($computer[0]['flags']) & 0x0004) ? '<a href="'.CDB_URL.'/cdb.php?action=computer&do=show&id='.$computer[0]['id'].'">Show</a>':'<a href="'.CDB_URL.'/cdb.php?action=computer&do=hide&id='.$computer[0]['id'].'">Hide</a>').'</p>';

	$db->select_assoc_ex($tasks, rpv("SELECT m.`id`, m.`pid`, m.`flags`, m.`date`, m.`operid`, m.`opernum` FROM @tasks AS m WHERE m.`pid` = # ORDER BY m.`date` DESC", $computer[0]['id']));

	$html .= '<h1>История заявок</h1>';
	
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

	unset($tasks);

	$html .= $table;

	$db->select_assoc_ex($ac_log, rpv("SELECT m.`id`, m.`app_path`, m.`cmdln`, m.`last`, m.`flags` FROM @ac_log AS m WHERE m.`pid` = # ORDER BY m.`last` DESC", $computer[0]['id']));
	
	$html .= '<h1>Срабатываения блокировки ПО Application Control</h1>';

	$table = '<table>';
	$table .= '<tr><th>Date</th><th>Blocked app path</th><th>Command line</th><th>Flags</th></tr>';

	foreach($ac_log as &$row)
	{
		$table .= '<tr>';
		$table .= '<td>'.$row['last'].'</td>';
		$table .= '<td>'.$row['app_path'].'</td>';
		$table .= '<td>'.$row['cmdln'].'</td>';
		$table .= '<td>'.flags_to_string(intval($row['flags']), $g_ac_flags, ', ').'</td>';
		$table .= '</tr>';
	}

	$table .= '</table>';
	
	unset($ac_log);
	
	$html .= $table;

	$db->select_assoc_ex($files, rpv("
		SELECT
			f.`path`,
			f.`filename`
		FROM @files_inventory AS fi
		LEFT JOIN @files AS f
			ON fi.`fid` = f.`id`
		WHERE
			(fi.`flags` & 0x0020) = 0                         -- File not Deleted
			AND (f.`flags` & 0x0010) = 0                      -- Not exist in IT Invent
			AND fi.`pid` = #
		ORDER
			BY f.`path`, f.`filename`
	", $computer[0]['id']));
	
	$html .= '<h1>Обнаруженное незарегистрированное ПО</h1>';

	$html .= '<table>';
	$html .= '<tr><th>Path</th><th>File name</th></tr>';

	foreach($files as &$row)
	{
		$html .= '<tr>';
		$html .= '<td>'.$row['path'].'</td>';
		$html .= '<td>'.$row['filename'].'</td>';
		$html .= '</tr>';
	}

	$html .= '</table>';
	
	unset($files);
	
	$html .= '</body>';

	echo $html;
