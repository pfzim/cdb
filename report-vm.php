<?php
	// Report about Hyper-V Virtual Machines resource usage

	if(!defined('Z_PROTECTED')) exit;

	echo "\nreport-vm:\n";

	$table = '';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`address`, m.`name`
		FROM @devices AS m
		WHERE m.`type` = 2
	")))
	{
		foreach($result as &$row)
		{
			$table .= '<h3>'.$row['name'].'</h3>';
			$table .= '<table>';
			$table .= '<tr><th>Name</th><th>CPU</th><th>Memory, GB</th><th>HDD, GB</th><th>Last sync</th></tr>';

			if($db->select_assoc_ex($vms, rpv("
				SELECT `name`, DATE_FORMAT(`date`, '%d.%m.%Y %H:%i:%s') AS `last_update`, `cpu`, `ram_size`, `hdd_size`
					FROM (
						SELECT
							`name`,
							`date`,
							`cpu`,
							`ram_size`,
							`hdd_size`,
							row_number() OVER(PARTITION BY `name` ORDER BY `date` desc) AS `rn`
						FROM
							@vm_history
						WHERE `pid` = # AND `date` > DATE_SUB((SELECT MAX(`date`) FROM @vm_history), INTERVAL 1 HOUR)
					) AS t
					WHERE t.`rn` = 1
					ORDER BY t.`name`
			", $row['id'])))
			{
				$total_cpu = 0;
				$total_ram = 0;
				$total_hdd = 0;
				foreach($vms as &$vm)
				{
					$table .= '<tr><td>'.$vm['name'].'</td><td align="right">'.$vm['cpu'].'</td><td align="right">'.$vm['ram_size'].'</td><td align="right">'.$vm['hdd_size'].'</td><td>'.$vm['last_update'].'</td></tr>';
					$total_cpu += intval($vm['cpu']);
					$total_ram += intval($vm['ram_size']);
					$total_hdd += intval($vm['hdd_size']);
					$i++;
				}
			}

			$table .= '<tr><td><b>Total</b></td><td align="right"><b>'.$total_cpu.'</b></td><td align="right"><b>'.formatBytes($total_ram * 1073741824, 0).'</b></td><td align="right"><b>'.formatBytes($total_hdd * 1073741824, 0).'</b></td><td>&nbsp;</td></tr>';
			$table .= '</table>';
		}
	}

	echo 'Total VMs: '.$i."\r\n";

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
	<h1>Отчёт по используемым ресурсам виртуальными машинами</h1>
EOT;

	$html .= $table;

	$html .= '<br /><small>Для перезапуска отчёта:<br /><br />1. Выполнить скрипт sync-vm.ps1<br />2. <a href="'.CDB_URL.'/cdb.php?action=report-vm">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if($i > 0)
	{
		if(php_mailer(array(MAIL_TO_ADMIN), CDB_TITLE.': Hyper-V Virtual Machines resource usage', $html, 'You client does not support HTML'))
		{
			echo 'Send mail: OK';
		}
		else
		{
			echo 'Send mail: FAILED';
		}
	}
