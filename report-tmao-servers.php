<?php
	// Create report TMAO
/*
	SET @current_pattern = (SELECT MAX(ao_script_ptn) FROM c_computers) - 200;
	SELECT @current_pattern;
	SELECT * FROM c_computers WHERE ao_script_ptn < @current_pattern AND ao_script_ptn <> 0 OR (name regexp '^[[:digit:]]{4}-[nN][[:digit:]]+' AND ee_encryptionstatus <> 2);

	SELECT name, ao_script_ptn, ee_encryptionstatus FROM c_computers WHERE ao_script_ptn < (SELECT MAX(ao_script_ptn) FROM c_computers) - 200 AND ao_script_ptn <> 0 OR (name regexp '[[:digit:]]{4}-[nN][[:digit:]]+' AND ee_encryptionstatus <> 2)

	TMAO - Workstations:
	SELECT
		`name`,
		`ao_script_ptn`,
		DATE_FORMAT(`ao_ptnupdtime`, '%d.%m.%Y %H:%i:%s') AS `last_update`,
		DATE_FORMAT(`ao_as_pstime`, '%d.%m.%Y %H:%i:%s') AS `last_scan`,
		`flags`
	FROM c_computers
	WHERE (`flags` & (0x0001 | 0x0004)) = 0
		AND `ao_script_ptn` < (SELECT MAX(`ao_script_ptn`) FROM c_computers) - 200
		AND `name` regexp '^([[:digit:]]{4}-[NnWw][[:digit:]]{4})|([Pp][Cc]-[[:digit:]]{3})$';

	TMME:
	SELECT * FROM c_computers WHERE name regexp '^[[:digit:]]{4}-[nN][[:digit:]]+' AND (ee_encryptionstatus <> 2 OR ee_lastsync < DATE_SUB(NOW(), INTERVAL 2 WEEK));
*/

	/**
		\file
		\brief Формиррование отчёта по проблемам антивируса на серверах.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nreport-tmao-servers:\n";

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
	<h1>Список серверов с устаревшей антивирусной базой</h1>
	<p>Маска для отбора серверов: [brc|dln|nn|rc1]-[имя]-[цифры]<br />В отчёте присутствуют сервера отключенные в AD</p>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Name</th><th>Pattern version</th><th>Last update</th><th>Last full scan</th></tr>';

	$i = 0;

	if($db->select_assoc_ex($result, rpv("
			SELECT
				`id`,
				`name`,
				`ao_script_ptn`,
				DATE_FORMAT(`ao_ptnupdtime`, '%d.%m.%Y %H:%i:%s') AS `last_update`,
				DATE_FORMAT(`ao_as_pstime`, '%d.%m.%Y %H:%i:%s') AS `last_scan`
			FROM
				@computers
			WHERE
				(`flags` & ({%CF_DELETED} | {%CF_HIDED})) = 0
				AND `ao_script_ptn` < (SELECT MAX(`ao_script_ptn`) FROM @computers) - ".TMAO_PATTERN_VERSION_LAG."
				AND `name` REGEXP {s0}
			ORDER BY `name`
		",
		CDB_REGEXP_SERVERS
	)))
	{
		foreach($result as &$row)
		{
			#echo $row['name']."\r\n";

			$td = getdate();
			$dd = &$td['mday'];
			$dm = &$td['mon'];
			$dy = &$td['year'];

			$class1 = '';
			$class2 = '';

			$d = explode('.', $row['last_update'], 3);
			$nd = intval(@$d[0]);
			$nm = intval(@$d[1]);
			$ny = intval(@$d[2]);
			dateadd($nd, $nm, $ny, 7);
			if(!datecheck($nd, $nm, $ny) || (datecmp($nd, $nm, $ny, $dd, $dm, $dy) < 0))
			{
				$class1 = ' class="error"';
			}

			$d = explode('.', $row['last_scan'], 3);
			$nd = intval(@$d[0]);
			$nm = intval(@$d[1]);
			$ny = intval(@$d[2]);
			dateadd($nd, $nm, $ny, 7);
			if(!datecheck($nd, $nm, $ny) || (datecmp($nd, $nm, $ny, $dd, $dm, $dy) < 0))
			{
				$class2 = ' class="error"';
			}

			$table .= '<tr>';
			$table .= '<td><a href="'.CDB_URL.'/cdb.php?action=get-computer-info&id='.$row['id'].'">'.$row['name'].'</a></td>';
			$table .= '<td>'.$row['ao_script_ptn'].'</td>';
			$table .= '<td'.$class1.'>'.$row['last_update'].'</td>';
			$table .= '<td'.$class2.'>'.$row['last_scan'].'</td>';
			$table .= '</tr>';
			$i++;
		}
	}

	echo 'Count: '.$i."\r\n";

	$table .= '</table>';
	$html .= '<p>Всего: '.$i.'</p>';
	$html .= $table;

	$table = '<table>';
	$table .= '<tr><th>Name</th></tr>';

	if($db->select_assoc_ex($result, rpv("SELECT `name` FROM @computers WHERE (`flags` & 0x0004)")))
	{
		foreach($result as &$row)
		{
			$table .= '<tr><td>'.$row['name'].'</td></tr>';
		}
	}

	$table .= '</table>';

	$html .= '<h2>Список исключений</h2>';
	$html .= $table;
	$html .= '<br /><small>Для перезапуска отчёта:<br />1. <a href="'.CDB_URL.'/cdb.php?action=sync-all">Выполнить синхронизацию</a><br />2. <a href="'.CDB_URL.'/cdb.php?action=report-tmao-servers">Сформировать отчёт</a></small>';
	$html .= '</body>';

	if(php_mailer(REPORT_TMAO_SERVERS_MAIL_TO, CDB_TITLE.': Audit antivirus protection', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
