<?php
	// List Zabbix problems

	/**
		\file
		\brief Экспорт списка проблем Zabbix
	*/


	if(!defined('Z_PROTECTED')) exit;

	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.zabbix.php');

	echo PHP_EOL.'report-zabbix-problems:'.PHP_EOL;

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
	<h1>Отчёт Zabbix Problems</h1>
EOT;

	$auth_key = ZABBIX_TOKEN;

	$zabbix_result = zabbix_api_request(
		'event.get',
		$auth_key,
		array(
			'acknowledged' => TRUE,
			'value' => 1,
			'selectHosts' =>  array('host'),
			'select_acknowledges' => array('clock', 'message'),
			'output' => array('objectid', 'r_eventid', 'severity', 'clock', 'acknowledged', 'name')
		)
	);

	$table = '<table>';
	$table .= '<tr><th>Host</th><th>Severity</th><th>Start</th><th>Duration</th><th>Problem</th><th>Comment</th></tr>';

	$i = 0;
	foreach($zabbix_result as &$problem)
	{
		$dt = new DateTime();
		$duration = $dt->diff(DateTime::createFromFormat('U', $problem['clock']));
		
		$table .= '<tr>';
		$table .= '<td>'.@$problem['hosts'][0]['host'].'</td>';
		$table .= '<td>'.$problem['severity'].'</td>';
		$table .= '<td>'.date('Y-m-d H:i:s', $problem['clock']).'</td>';
		$table .= '<td>'.$duration->format('%dd %Hh %Im %Ss').'</td>';
		$table .= '<td>'.$problem['name'].'</td>';
		$table .= '<td>'.@$problem['acknowledges'][0]['message'].'</td>';
		$table .= '</tr>';

		$i++;
	}

	$table .= '</table>';

	$html .= $table;
	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-zabbix-problems">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if(php_mailer(array('dvz@bristolcapital.ru'), CDB_TITLE.': Report Zabbix problems list', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
