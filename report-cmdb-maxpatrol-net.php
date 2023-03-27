<?php
	// Report CMDB - MaxPatrol

	/**
		\file
		\brief Формирование отчёта по списку сетевого оборудования из CMDB и
		дате их сканировния из MaxPatrol
	*/


	if(!defined('Z_PROTECTED')) exit;

	echo PHP_EOL.'report-cmdb-maxpatrol-net:'.PHP_EOL;

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
	<h1>Время последнего сканирования MaxPatrol сетевого оборудования по списку из CMDB</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Name</th><th>IP</th><th>Audit time &#9650;</th></tr>';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			ca.`name`,
			ca.`value1`,
			DATE_FORMAT(mp.`audit_time`, '%d.%m.%Y %H:%i:%s') AS `audit_time`,
			ca.`flags`
		FROM @cmdb_assets AS ca
		LEFT JOIN @maxpatrol AS mp
			ON mp.`name` = ca.`name`
			AND mp.`flags` & {%MPF_EXIST}
		WHERE
			ca.`flags` & {%CAF_NETWORK_ENTITY}
		ORDER BY
			mp.`audit_time`,
			ca.`name`
	")))
	{
		foreach($result as &$row)
		{
			$table .= '<tr>';
			$table .= '<td>'.$row['name'].'</td>';
			$table .= '<td>'.$row['value1'].'</td>';
			$table .= '<td>'.$row['audit_time'].'</td>';
			$table .= '</tr>';

			$i++;
		}
	}

	$table .= '</table>';

	$html .= '<p>';
	$html .= 'Всего: '.$i.'<br />';
	$html .= '</p>';

	$html .= $table;
	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-cmdb-maxpatrol-net">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if(php_mailer(REPORT_CMDB_MAXPATROL_NET_MAIL_TO, CDB_TITLE.': Report CMDB MaxPatrol (net devices)', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK'.PHP_EOL;
	}
	else
	{
		echo 'Send mail: FAILED'.PHP_EOL;
	}
