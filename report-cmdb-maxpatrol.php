<?php
	// Report CMDB - MaxPatrol

	/**
		\file
		\brief Формирование отчёта по списку серверов из CMDB и дате их
		сканировния из MaxPatrol
	*/


	if(!defined('Z_PROTECTED')) exit;

	echo PHP_EOL.'report-cmdb-maxpatrol:'.PHP_EOL;

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
	<h1>Время последнего сканирования MaxPatrol серверов по списку из CMDB</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Name</th><th>Audit time &#9650;</th><th>OS</th></tr>';

	$scan_period = time() - 9*24*60*60;
	$recently_scanned = 0;
	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			vm.`name`,
			DATE_FORMAT(mp.`audit_time`, '%d.%m.%Y %H:%i:%s') AS `audit_time`,
			vm.`cmdb_os`
		FROM @vm AS vm
		LEFT JOIN @maxpatrol AS mp
			ON mp.`name` = vm.`name`
			AND mp.`flags` & {%MPF_EXIST}
		WHERE
			vm.`flags` & {%VMF_EXIST_CMDB}
		ORDER BY
			mp.`audit_time`,
			vm.`name`
	")))
	{
		foreach($result as &$row)
		{
			if(!empty($row['audit_time']))
			{
				$audit_time = strtotime($row['audit_time']);
				if($audit_time > $scan_period)
				{
					$recently_scanned++;
				}
			}

			$table .= '<tr>';
			$table .= '<td>'.$row['name'].'</td>';
			$table .= '<td>'.$row['audit_time'].'</td>';
			$table .= '<td>'.$row['cmdb_os'].'</td>';
			$table .= '</tr>';

			$i++;
		}
	}

	$table .= '</table>';

	$html .= '<p>';
	$html .= 'Всего: '.$i.'<br />';
	$html .= 'Cканировалось за последние 9 дней: '.$recently_scanned;
	$html .= '</p>';

	$html .= $table;
	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-cmdb-maxpatrol">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if(php_mailer(REPORT_CMDB_MAXPATROL_MAIL_TO, CDB_TITLE.': Report CMDB MaxPatrol (servers)', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK'.PHP_EOL;
	}
	else
	{
		echo 'Send mail: FAILED'.PHP_EOL;
	}
