<?php
	// Report VM backup

	/**
		\file
		\brief Формирует отчёт по резервным копиям виртуальных машин
	*/


	if(!defined('Z_PROTECTED')) exit;

	echo "\nreport-cmdb-vm-backup:\n";

	global $g_cmdb_vmm_flags;
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
	<h1>Отчёт по резервным копиям виртуальных машин (VK)</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Name</th><th>Backup date &#9650;</th></tr>';

	$i = 0;
	$cmdb = 0;
	$vm = 0;
	
	if($db->select_assoc_ex($result, rpv("
		SELECT
			vm.`name`,
			DATE_FORMAT(vm.`backup_date`, '%d.%m.%Y %H:%i:%s') AS `backup_date`,
			vm.`flags`
		FROM @vm AS vm
		WHERE
			vm.`flags` & ({%VMF_EXIST_VK})
		ORDER BY
			vm.`backup_date`,
			vm.`name`			
	")))
	{
		foreach($result as &$row)
		{
			$table .= '<tr>';
			$table .= '<td>'.$row['name'].'</td>';
			$table .= '<td>'.$row['backup_date'].'</td>';
			$table .= '</tr>';

			$i++;
		}
	}

	$table .= '</table>';

	$html .= '<p>';
	$html .= 'Всего записей: '.$i.'<br />';
	$html .= '</p>';

	$html .= $table;
	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-cmdb-vm-backup">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if(php_mailer(REPORT_CMDB_VM_BACKUPS_MAIL_TO, CDB_TITLE.': Report VM backups', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
