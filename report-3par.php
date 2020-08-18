<?php
	// Report about 3PAR virtual volumes

	/**
		\file
		\brief Отчёт по используемому месту дисками 3Par
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nreport-3par:\n";

	$table = '';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`address`, m.`name`
		FROM @devices AS m
		WHERE m.`type` = 1
	")))
	{
		foreach($result as &$row)
		{
			$table .= '<h3>'.$row['name'].'</h3>';
			$table .= '<table>';
			$table .= '<tr><th>Name</th><th>Usr_RawRsvd_MB</th><th>Last sync</th></tr>';

			if($db->select_assoc_ex($disks, rpv("
				SELECT `name`, DATE_FORMAT(`date`, '%d.%m.%Y %H:%i:%s') AS `last_update`, `usr_rawrsvd_mb`
					FROM (
						SELECT
							`name`,
							`date`,
							`usr_rawrsvd_mb`,
							row_number() OVER(PARTITION BY `name` ORDER BY `date` desc) AS `rn`
						FROM
							@vv_history
						WHERE `pid` = # AND `date` > DATE_SUB((SELECT MAX(`date`) FROM @vv_history), INTERVAL 1 HOUR)
					) AS t
					WHERE t.`rn` = 1
					ORDER BY t.`name`
			", $row['id'])))
			{
				$total = 0;
				foreach($disks as &$disk)
				{
					$table .= '<tr><td>'.$disk['name'].'</td><td align="right">'.formatBytes(intval($disk['usr_rawrsvd_mb'])*1048576, 0).'</td><td>'.$disk['last_update'].'</td></tr>';
					$total += intval($disk['usr_rawrsvd_mb']);
					$i++;
				}
			}

			$table .= '<tr><td><b>Total</b></td><td colspan="2" align="right"><b>'.formatBytes(intval($total)*1048576, 0).'</b></td></tr>';
			$table .= '</table>';
		}
	}

	echo 'Total disks: '.$i."\r\n";

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
	<h1>Отчёт по используемому месту дисками</h1>
EOT;

	$html .= $table;

	$html .= '<br /><small>Для перезапуска отчёта:<br /><br />1. <a href="'.CDB_URL.'/cdb.php?action=sync-3par">Выполнить синхронизацию с 3PAR</a><br />2. <a href="'.CDB_URL.'/cdb.php?action=report-3par">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if($i > 0)
	{
		if(php_mailer(array(MAIL_TO_ADMIN), CDB_TITLE.': 3PAR Virtual Volumes used space', $html, 'You client does not support HTML'))
		{
			echo 'Send mail: OK';
		}
		else
		{
			echo 'Send mail: FAILED';
		}
	}
