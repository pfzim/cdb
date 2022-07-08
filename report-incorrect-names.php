<?php
	// Create report TMAO

	/**
		\file
		\brief Отчёт по именам ПК не соответствующим установленному шаблону.
	*/

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

	if(!defined('Z_PROTECTED')) exit;

	echo "\nreport-incorrect-names:\n";

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
	<h1>Список ПК с некорректным именованием</h1>
	<p>Правильное наименование:<br />[brc|dln|nn|rc1]-[имя]-[цифры]<br />[2 цифры]-[4 цифры]-[VM?][цифры]<br />[4 цифры]-[NW][4 цифры]<br />HD-EGAIS-[цифры]<br />В отчёте присутствуют ПК отключенные в AD</p>
EOT;

	$html .= '<p>Регулярное выражение: '.CDB_REGEXP_VALID_NAMES.'</p>';

	$table = '<table>';
	$table .= '<tr><th>Name</th><th>HD Task</th></tr>';

	$i = 0;
	$opened = 0;

	if($db->select_assoc_ex($result, rpv("
			SELECT c.`id`, c.`name`, c.`dn`, c.`laps_exp`, t.`type`, t.`flags`, t.`operid`, t.`opernum`
			FROM @computers AS c
			LEFT JOIN @tasks AS t
				ON t.`pid` = c.`id`
				AND t.`type` = {%TT_PC_RENAME}
				AND (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0
			WHERE
				(c.`flags` & ({%CF_DELETED} | {%CF_HIDED})) = 0
				AND c.`name` NOT REGEXP {s0}
			ORDER BY c.`name`
		",
		CDB_REGEXP_VALID_NAMES
	)))
	{
		foreach($result as &$row)
		{
			$table .= '<tr><td>'.$row['name'].'</td><td>';
			if(intval($row['type']) == TT_PC_RENAME)
			{
				$table .= '<a href="'.HELPDESK_URL.'/QueryView.aspx?KeyValue='.$row['operid'].'">'.$row['opernum'].'</a>';
				$opened++;
			}
			$table .= '</td></tr>';
			$i++;
		}
	}

	echo 'Count: '.$i."\r\n";

	$table .= '</table>';
	$html .= '<p>Открытых заявок: '.$opened.', всего проблемных ПК : '.$i.'</p>';
	$html .= $table;

	$html .= '<br /><small>Для перезапуска отчёта:<br /><br />1. <a href="'.CDB_URL.'/cdb.php?action=sync-ad">Выполнить синхронизацию с AD</a><br />2. <a href="'.CDB_URL.'/cdb.php?action=report-incorrect-names">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if($i > 0)
	{
		if(php_mailer(array(MAIL_TO_ADMIN), CDB_TITLE.': Computers with incorrect names', $html, 'You client does not support HTML'))
		{
			echo 'Send mail: OK';
		}
		else
		{
			echo 'Send mail: FAILED';
		}
	}
