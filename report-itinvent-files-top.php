<?php
	// Report detected unregistered software

	/**
		\file
		\brief Формирование отчёта по незарегистрированному программному обеспечению. TOP 10.
	*/


	if(!defined('Z_PROTECTED')) exit;

	echo "\nreport-invent-files-top:\n";

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
		.severity0 {background: rgb(0, 113, 185); color: #ffffff;}
		.severity1 {background: rgb(63, 174, 73); color: #ffffff;}
		.severity2 {background: rgb(253, 196, 49); color: #000000;}
		.severity3 {background: rgb(238, 147, 54); color: #000000;}
		.severity4 {background: rgb(212, 63, 58); color: #ffffff;}
		</style>
	</head>
	<body>
	<h1>Отчёт по незарегистрированному программному обеспечению</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Путь</th><th>Количество</th></tr>';

	$severities = array();

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
			SELECT f.`path`, COUNT(*) AS cnt
			FROM @files_inventory AS fi
			LEFT JOIN @files AS f
				ON f.`id` = fi.`fid`
				-- AND (fi.`flags` & 0x0010) = 0
			WHERE (f.`flags` & 0x0010) = 0
			GROUP BY fi.`fid`, f.`path`
			ORDER BY `cnt` DESC
			LIMIT 100
		",
		NESSUS_SERVERS_FOLDER_ID
	)))
	{
		foreach($result as &$row)
		{
			$table .= '<tr>';
			$table .= '<td>'.$row['path'].'</td>';
			$table .= '<td>'.$row['cnt'].'</td>';
			$table .= '</tr>';

			$i++;
		}
	}

	$table .= '</table>';

	$html .= $table;
	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-itinvent-files-top">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	echo 'Opened: '.$i."\r\n";

	if(php_mailer(array(MAIL_TO_ADMIN, MAIL_TO_INVENT), CDB_TITLE.': IT Invent - Unregistered software', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
