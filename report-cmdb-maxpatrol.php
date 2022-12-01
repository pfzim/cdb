<?php
	// Report CMDB - MaxPatrol

	/**
		\file
		\brief Формирование отчёта по серверам из CMDB и дате их
		сканировния из MaxPatrol
	*/


	if(!defined('Z_PROTECTED')) exit;

	echo "\nreport-cmdb-vm:\n";

	global $g_cmdb_vmm_short_flags;

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
	<h1>Отчёт о времени последнего сканированию MaxPatrol серверов из CMDB</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Name</th><th>Audit time</th></tr>';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			vm.`name`,
			DATE_FORMAT(mp.`audit_time`, '%d.%m.%Y %H:%i:%s') AS `audit_time`
		FROM @vm AS vm
		LEFT JOIN @maxpatrol AS mp
			ON mp.`name` = vm.`name`
		WHERE
			vm.`flags` & {%VMF_EXIST_CMDB}
			AND mp.`flags` & {%MPF_EXIST}
		ORDER BY
			mp.`audit_time`
	")))
	{
		foreach($result as &$row)
		{
			$table .= '<tr>';
			$table .= '<td>'.$row['name'].'</td>';
			$table .= '<td>'.$row['audit_time'].'</td>';
			$table .= '</tr>';

			$i++;
		}
	}

	$table .= '</table>';

	$html .= $table;
	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-cmdb-maxpatrol">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if(php_mailer(REPORT_CMDB_MAXPATROL_MAIL_TO, CDB_TITLE.': Report CMDB MaxPatrol', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
