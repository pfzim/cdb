<?php
	// Report for opened HelpDesk tasks

	/**
		\file
		\brief Формирование отчёта по открытым заявкми в системе HelpDesk.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nreport-tasks-status:\n";

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
	<h1>Отчёт по ранее выставленным не закрытым заявкам в HelpDesk</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Name</th><th>AV Pattern version</th><th>Last update</th><th>TMEE Status</th><th>TMEE Last sync</th><th>HD Task</th><th>Reason</th><th>Source</th><th>Issues</th></tr>';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			j1.`id`,
			j1.`name`,
			j1.`ao_script_ptn`,
			DATE_FORMAT(j1.`ao_ptnupdtime`, '%d.%m.%Y %H:%i:%s') AS `last_update`,
			j1.`ee_encryptionstatus`,
			DATE_FORMAT(j1.`ee_lastsync`, '%d.%m.%Y %H:%i:%s') AS `last_sync`,
			m.`operid`,
			m.`opernum`,
			m.`flags`,
			j1.`flags` AS j1_flags,
			(
				SELECT COUNT(*)
				FROM @tasks AS i1
				WHERE i1.`pid` = m.`pid`
					AND i1.`tid` = 1
					AND (i1.`flags` & (m.`flags` | 0x0001)) = (m.`flags` | 0x0001)
					AND i1.`date` > DATE_SUB(NOW(), INTERVAL 1 MONTH)
			) AS `issues`
		FROM @tasks AS m
		LEFT JOIN @computers AS j1 ON j1.`id` = m.`pid`
		WHERE
			m.`tid` = 1
			AND (m.`flags` & 0x0001) = 0
		ORDER BY j1.`name`
	")))
	{
		global $g_comp_short_flags;

		foreach($result as &$row)
		{
			$table .= '<tr>';
			$table .= '<td><a href="'.CDB_URL.'/cdb.php?action=get-computer-info&id='.$row['id'].'">'.$row['name'].'</a></td>';
			$table .= '<td>'.$row['ao_script_ptn'].'</td><td>'.$row['last_update'].'</td>';
			$table .= '<td>'.tmee_status(intval($row['ee_encryptionstatus'])).'</td><td>'.$row['last_sync'].'</td>';
			$table .= '<td><a href="'.HELPDESK_URL.'/QueryView.aspx?KeyValue='.$row['operid'].'">'.$row['opernum'].'</a></td>';
			$table .= '<td>'.tasks_flags_to_string(intval($row['flags'])).'</td>';
			$table .= '<td>'.flags_to_string(intval($row['j1_flags']) & 0x00F0, $g_comp_short_flags, '', '-').'</td>';
			$table .= '<td'.((intval($row['issues']) > 3)?' class="error"':'').'>'.$row['issues'].'</td>';
			$table .= '</tr>';

			$i++;
		}
	}

	$table .= '</table>';

	$problems_tmao = 0;
	$problems_tmao_tt = 0;
	$problems_tmee = 0;
	$opened_tmao = 0;
	$opened_tmee = 0;
	$problems_laps = 0;
	$opened_laps = 0;
	$problems_sccm = 0;
	$opened_sccm = 0;
	$problems_name = 0;
	$opened_name = 0;
	$problems_osup = 0;
	$opened_osup = 0;

	if($db->select_assoc_ex($result, rpv("
		SELECT
		(SELECT COUNT(*) FROM @computers WHERE (`flags` & (0x0001 | 0x0002 | 0x0004)) = 0 AND `name` NOT REGEXP '".CDB_REGEXP_SERVERS."' AND `ao_script_ptn` < ((SELECT MAX(`ao_script_ptn`) FROM @computers) - ".TMAO_PATTERN_VERSION_LAG.")) AS `p_tmao`,
		(SELECT COUNT(*) FROM @computers WHERE (`flags` & (0x0001 | 0x0002 | 0x0004)) = 0 AND `name` REGEXP '".CDB_REGEXP_SHOPS."' AND `ao_script_ptn` < ((SELECT MAX(`ao_script_ptn`) FROM @computers) - ".TMAO_PATTERN_VERSION_LAG.")) AS `p_tmao_tt`,
		(SELECT COUNT(*) FROM @computers WHERE `name` regexp '^[[:digit:]]{4}-[nN][[:digit:]]+' AND (`flags` & (0x0001 | 0x0002 | 0x0004)) = 0 AND `ee_encryptionstatus` <> 2) AS `p_tmee`,
		(SELECT COUNT(*) FROM @tasks WHERE (`flags` & (0x0001 | 0x0200)) = 0x0200) AS `o_tmao`,
		(SELECT COUNT(*) FROM @tasks WHERE (`flags` & (0x0001 | 0x0100)) = 0x0100) AS `o_tmee`,
		(SELECT COUNT(*) FROM @computers AS m WHERE (m.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0 AND m.`dn` LIKE '%".LDAP_OU_COMPANY."' AND m.`laps_exp` < DATE_SUB(NOW(), INTERVAL 1 MONTH)) AS `p_laps`,
		(SELECT COUNT(*) FROM @tasks WHERE (`flags` & (0x0001 | 0x0800)) = 0x0800) AS `o_laps`,
		(SELECT COUNT(*) FROM @computers AS m WHERE (m.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0 AND m.`sccm_lastsync` < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND m.`name` NOT REGEXP '".CDB_REGEXP_SERVERS."') AS `p_sccm`,
		(SELECT COUNT(*) FROM @tasks WHERE (`flags` & (0x0001 | 0x1000)) = 0x1000) AS `o_sccm`,
		(SELECT COUNT(*) FROM @computers AS m WHERE (m.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0 AND m.`name` NOT REGEXP '".CDB_REGEXP_VALID_NAMES."') AS `p_name`,
		(SELECT COUNT(*) FROM @tasks WHERE (`flags` & (0x0001 | 0x0400)) = 0x0400) AS `o_name`,
		(
			SELECT
				COUNT(*)
			FROM @properties_str AS os
			LEFT JOIN @computers AS c
				ON
				os.`tid` = 1
				AND os.`oid` = ".CDB_PROP_OPERATINGSYSTEM."
				AND os.`pid` = c.`id`
			WHERE
				os.`tid` = 1
				AND os.`oid` = ".CDB_PROP_OPERATINGSYSTEM."
				AND (c.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
				AND os.`value` NOT IN ('Windows 10 Корпоративная 2016 с долгосрочным обслуживанием', 'Windows 10 Корпоративная')
				AND c.`name` NOT REGEXP '".CDB_REGEXP_SERVERS."'
		) AS `p_os`,
		(SELECT COUNT(*) FROM @tasks WHERE (`flags` & (0x0001 | 0x4000)) = 0x4000) AS `o_os`
	")))
	{
		$problems_tmao = $result[0]['p_tmao'];
		$problems_tmao_tt = $result[0]['p_tmao_tt'];
		$problems_tmee = $result[0]['p_tmee'];
		$opened_tmao = $result[0]['o_tmao'];
		$opened_tmee = $result[0]['o_tmee'];
		$problems_laps = $result[0]['p_laps'];
		$opened_laps = $result[0]['o_laps'];
		$problems_sccm = $result[0]['p_sccm'];
		$opened_sccm = $result[0]['o_sccm'];
		$problems_name = $result[0]['p_name'];
		$opened_name = $result[0]['o_name'];
		$problems_osup = $result[0]['p_os'];
		$opened_osup = $result[0]['o_os'];
	}

	$html .= '<p>';
	$html .= 'TMAO открытых заявок: '.$opened_tmao.', всего проблемных ПК : '.$problems_tmao.' (ТТ: '.$problems_tmao_tt.', Остальные: '.(intval($problems_tmao) - intval($problems_tmao_tt)).')<br />';
	$html .= 'TMEE открытых заявок: '.$opened_tmee.', всего проблемных ПК : '.$problems_tmee.'<br />';
	$html .= 'LAPS открытых заявок: '.$opened_laps.', всего проблемных ПК : '.$problems_laps.'<br />';
	$html .= 'SCCM открытых заявок: '.$opened_sccm.', всего проблемных ПК : '.$problems_sccm.'<br />';
	$html .= 'NAME открытых заявок: '.$opened_name.', всего проблемных ПК : '.$problems_name.'<br />';
	$html .= 'OS   открытых заявок: '.$opened_osup.', всего проблемных ПК : '.$problems_osup;
	$html .= '</p>';

	$html .= '<p>Обозначения: D - Отключен в AD, R - удалён, H - скрыт, T - временный флаг синхронизации, A - Active Directory, O - Apex One, E - Encryption Endpoint, C - Configuration Manager</p>';
	$html .= $table;
	$html .= '<br /><small>Для перезапуска отчёта:<br />1. <a href="'.CDB_URL.'/cdb.php?action=check-tasks-status">Обновить статус заявок из системы HelpDesk</a><br />2. <a href="'.CDB_URL.'/cdb.php?action=report-tasks-status">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	echo 'Opened: '.$i."\r\n";

	if(php_mailer(array(MAIL_TO_ADMIN, MAIL_TO_GUP, MAIL_TO_GOO), CDB_TITLE.': Opened tasks', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
