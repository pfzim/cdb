<?php
	// Report MaxPatrol SMB

	/**
		\file
		\brief Формирование списка обнаруженных MaxPatrol сетевых файловых
		ресурсов (SMB)
	*/


	if(!defined('Z_PROTECTED')) exit;

	echo PHP_EOL.'report-maxpatrol-smb:'.PHP_EOL;

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
	<h1>Список обнаруженных сетевых файловых ресурсов (MaxPatrol SMB)</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Hostname &#9650;</th><th>Share</th></tr>';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			ms.`hostname`,
			ms.`share`
		FROM @maxpatrol_smb AS ms
		ORDER BY
			ms.`hostname`,
			ms.`share`
	")))
	{
		foreach($result as &$row)
		{
			$table .= '<tr>';
			$table .= '<td>'.$row['hostname'].'</td>';
			$table .= '<td>'.$row['share'].'</td>';
			$table .= '</tr>';

			$i++;
		}
	}

	$table .= '</table>';

	$html .= $table;
	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-maxpatrol-smb">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if(php_mailer(REPORT_MAXPATROL_SMB_MAIL_TO, CDB_TITLE.': Report MaxPatrol SMB', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK'.PHP_EOL;
	}
	else
	{
		echo 'Send mail: FAILED'.PHP_EOL;
	}
