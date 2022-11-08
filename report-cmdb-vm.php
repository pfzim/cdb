<?php
	// Report CMDB - VM

	/**
		\file
		\brief Формирование отчёта по отсутствующим в CMDB или VM
		виртуальных серверах или различии в конфигурации
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
	<h1>Отчёт-сверка CMDB-VM</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Name</th><th>CPU</th><th>RAM</th><th>HDD</th><th>Check result</th></tr>';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			vm.`name`,
			vm.`os`,
			vm.`cpu`,
			vm.`ram_size`,
			vm.`hdd_size`,
			vm.`cmdb_type`,
			vm.`cmdb_os`,
			vm.`cmdb_cpu`,
			vm.`cmdb_ram_size`,
			vm.`cmdb_hdd_size`,
			vm.`flags`
		FROM @vm AS vm
		WHERE
			(vm.`flags` & ({%VMF_EXIST_CMDB} | {%VMF_EXIST_VMM}))
			AND (
				(vm.`flags` & {%VMF_EXIST_CMDB}) = 0
				OR (vm.`flags` & ({%VMF_EXIST_VMM})) = 0
				OR vm.`cpu` <> vm.`cmdb_cpu`
				OR vm.`ram_size` <> vm.`cmdb_ram_size`
				OR vm.`os` <> vm.`cmdb_os`
				-- OR vm.`hdd_size` <> vm.`cmdb_hdd_size`
			)
		ORDER BY vm.`name`
	")))
	{
		foreach($result as &$row)
		{
			$check_result = '';

			if((intval($row['flags']) & VMF_EXIST_CMDB) == 0)
			{
				$check_result .= 'Отсутствует в CMDB;';
			}
			else if((intval($row['flags']) & VMF_EXIST_VMM) == 0)
			{
				$check_result .= 'Виртуальная машина не существует ('.$row['cmdb_type'].');';
			}
			else
			{
				if(intval($row['cpu']) != intval($row['cmdb_cpu']))
				{
					$check_result .= 'CPU VMM '.$row['cpu'].' != CMDB '.$row['cmdb_cpu'].';';
				}

				if(intval($row['ram_size']) != intval($row['cmdb_ram_size']))
				{
					$check_result .= 'RAM VMM '.$row['ram_size'].' != CMDB '.$row['cmdb_ram_size'].';';
				}

				if(strcasecmp($row['os'], $row['cmdb_os']) !== 0)
				{
					$check_result .= 'OS VMM '.$row['os'].' != CMDB '.$row['cmdb_os'].';';
				}

				// if(intval($row['hdd_size']) != intval($row['cmdb_hdd_size']))
				// {
					// $check_result .= 'CPU VMM '.$row['hdd_size'].' != CMDB '.$row['cmdb_hdd_size'].';';
				// }
			}

			$table .= '<tr>';
			$table .= '<td>'.$row['name'].'</td>';
			$table .= '<td>'.$row['cpu'].'</td>';
			$table .= '<td>'.$row['ram_size'].'</td>';
			$table .= '<td>'.$row['hdd_size'].'</td>';
			//$table .= '<td>'.flags_to_string(intval($row['flags']), $g_cmdb_vmm_short_flags, '', '-').'</td>';
			$table .= '<td>'.$check_result.'</td>';
			$table .= '</tr>';

			$i++;
		}
	}

	$table .= '</table>';

	$html .= $table;
	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-cmdb-vm">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if(php_mailer(REPORT_CMDB_VM_MAIL_TO, CDB_TITLE.': Report CMDB VM', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
