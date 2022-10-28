<?php
	// Report CMDB - VPN

	/**
		\file
		\brief Формирование отчёта по отсутствующим в CMDB или PaloAlto
		VPN группам доступа
	*/


	if(!defined('Z_PROTECTED')) exit;

	echo PHP_EOL.'report-cmdb-vmm:'.PHP_EOL;

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
	<h1>Отчёт-сверка CMDB-VPN-PaloAlto</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Name</th><th>Check result</th></tr>';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			ag.`name`,
			ag.`flags`
		FROM @ad_groups AS ag
		WHERE
			(ag.`flags` & ({%AGF_EXIST_CMDB} | {%AGF_EXIST_PALOALTO}))
			AND (
				(ag.`flags` & ({%AGF_EXIST_CMDB} | {%AGF_EXIST_PALOALTO})) <> ({%AGF_EXIST_CMDB} | {%AGF_EXIST_PALOALTO})
			)
		ORDER BY ag.`name`
	")))
	{
		foreach($result as &$row)
		{
			$check_result = '';

			if((intval($row['flags']) & AGF_EXIST_CMDB) == 0)
			{
				$check_result .= 'Отсутствует в CMDB;';
			}
			else if((intval($row['flags']) & AGF_EXIST_PALOALTO) == 0)
			{
				$check_result .= 'Отсутствует в PaloAlto;';
			}

			$table .= '<tr>';
			$table .= '<td>'.$row['name'].'</td>';
			$table .= '<td>'.$check_result.'</td>';
			$table .= '</tr>';

			$i++;
		}
	}

	$table .= '</table>';

	$html .= $table;
	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-cmdb-vpn">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if(php_mailer(REPORT_CMDB_VPN_MAIL_TO, CDB_TITLE.': Report CMDB VPN', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
