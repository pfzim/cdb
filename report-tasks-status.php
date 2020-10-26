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

	if(!$db->select_assoc_ex($result, rpv("
			SELECT
			(SELECT COUNT(*) FROM @computers WHERE (`flags` & (0x0001 | 0x0002 | 0x0004)) = 0 AND `name` NOT REGEXP {s0} AND `ao_script_ptn` < ((SELECT MAX(`ao_script_ptn`) FROM @computers) - ".TMAO_PATTERN_VERSION_LAG.")) AS `p_tmao`,
			(SELECT COUNT(*) FROM @computers WHERE (`flags` & (0x0001 | 0x0002 | 0x0004)) = 0 AND `name` REGEXP {s1} AND `ao_script_ptn` < ((SELECT MAX(`ao_script_ptn`) FROM @computers) - ".TMAO_PATTERN_VERSION_LAG.")) AS `p_tmao_tt`,
			(SELECT COUNT(*) FROM @computers WHERE `name` regexp {s3} AND (`flags` & (0x0001 | 0x0002 | 0x0004)) = 0 AND `ee_encryptionstatus` <> 2) AS `p_tmee`,
			(SELECT COUNT(*) FROM @tasks WHERE (`flags` & (0x0001 | 0x0200)) = 0x0200) AS `o_tmao`,
			(SELECT COUNT(*) FROM @tasks WHERE (`flags` & (0x0001 | 0x0100)) = 0x0100) AS `o_tmee`,
			(SELECT COUNT(*) FROM @computers AS m WHERE (m.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0 AND m.`dn` LIKE '%".LDAP_OU_COMPANY."' AND m.`laps_exp` < DATE_SUB(NOW(), INTERVAL 1 MONTH)) AS `p_laps`,
			(SELECT COUNT(*) FROM @tasks WHERE (`flags` & (0x0001 | 0x0800)) = 0x0800) AS `o_laps`,
			(SELECT COUNT(*) FROM @computers AS m WHERE (m.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0 AND m.`sccm_lastsync` < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND m.`name` NOT REGEXP {s0}) AS `p_sccm`,
			(SELECT COUNT(*) FROM @tasks WHERE (`flags` & (0x0001 | 0x1000)) = 0x1000) AS `o_sccm`,
			(SELECT COUNT(*) FROM @computers AS m WHERE (m.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0 AND m.`name` NOT REGEXP {s2}) AS `p_name`,
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
					AND c.`name` NOT REGEXP {s0}
			) AS `p_os`,
			(SELECT COUNT(*) FROM @tasks WHERE (`flags` & (0x0001 | 0x0040)) = 0x0040) AS `o_os`,
			(
				SELECT
					COUNT(*)
				FROM @properties_int AS os
				LEFT JOIN @computers AS c
					ON
					os.`tid` = 1
					AND os.`oid` = ".CDB_PROP_BASELINE_COMPLIANCE_HOTFIX."
					AND os.`pid` = c.`id`
				WHERE
					os.`tid` = 1
					AND os.`oid` = ".CDB_PROP_BASELINE_COMPLIANCE_HOTFIX."
					AND (c.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
					AND os.`value` <> 1
			) AS `p_wsus`,
			(
				SELECT
					COUNT(*)
				FROM @properties_int AS os
				LEFT JOIN @computers AS c
					ON
					os.`tid` = 1
					AND os.`oid` = ".CDB_PROP_BASELINE_COMPLIANCE_HOTFIX."
					AND os.`pid` = c.`id`
				WHERE
					os.`tid` = 1
					AND os.`oid` = ".CDB_PROP_BASELINE_COMPLIANCE_HOTFIX."
					AND (c.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
					AND os.`value` <> 1
					AND c.`name` REGEXP {s1}
			) AS `p_wsus_tt`,
			(SELECT COUNT(*) FROM @tasks WHERE (`flags` & (0x0001 | 0x0040)) = 0x0040) AS `o_wsus`,
			(
				SELECT COUNT(*)
				FROM @persons AS p
				LEFT JOIN @properties_int AS j_quota
					ON j_quota.`tid` = 2
					AND j_quota.`pid` = p.`id`
					AND j_quota.`oid` = ".CDB_PROP_MAILBOX_QUOTA."
				WHERE
					(p.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
					AND j_quota.`value` = 0
			) AS `p_mbxq`,
			(SELECT COUNT(*) FROM @tasks WHERE (`flags` & (0x0001 | 0x0008)) = 0x0040) AS `o_mbxq`,
			(SELECT COUNT(*) FROM @tasks WHERE (`flags` & (0x0001 | 0x0040)) = 0x0040) AS `o_wsus`,
			(
				SELECT COUNT(*)
				FROM @mac AS m
				LEFT JOIN @devices AS d
					ON d.`id` = m.`pid` AND d.`type` = 3
				LEFT JOIN @mac AS dm
					ON
						dm.`name` = d.`name`
						AND (dm.`flags` & (0x0010 | 0x0040)) = (0x0010 | 0x0040)  -- Only exist and active in IT Invent
				WHERE
					(m.`flags` & (0x0002 | 0x0004 | 0x0010 | 0x0020 | 0x0040)) = 0x0070    -- Not deleted, not hidden, from netdev, exist in IT Invent, active in IT Invent
					AND (
						dm.`branch_no` IS NULL
						OR dm.`loc_no` IS NULL
						OR (
							m.`branch_no` <> dm.`branch_no`
							AND m.`loc_no` <> dm.`loc_no`
						)
					)
			) AS `p_iimv`,
			(SELECT COUNT(*) FROM @tasks WHERE (`flags` & (0x0001 | 0x0010)) = 0x0010) AS `o_iimv`
		",
		CDB_REGEXP_SERVERS,
		CDB_REGEXP_SHOPS,
		CDB_REGEXP_VALID_NAMES,
		CDB_REGEXP_NOTEBOOK_NAME
	)))
	{
		// error
	}

	$html .= '<table>';
	$html .= '<tr><th>Проблема</th><th>Проблемных ПК</th><th>Открыто заявок</th></tr>';
	$html .= '<tr><td>Устаревшая БД антивируса</td><td>'.$result[0]['p_tmao'].' (ТТ: '.$result[0]['p_tmao_tt'].')</td><td>'.$result[0]['o_tmao'].'</td></tr>';
	$html .= '<tr><td>Не зашифрован ноутбук</td><td>'.$result[0]['p_tmee'].'</td><td>'.$result[0]['o_tmee'].'</td></tr>';
	$html .= '<tr><td>Не обновляется пароль LAPS</td><td>'.$result[0]['p_laps'].'</td><td>'.$result[0]['o_laps'].'</td></tr>';
	$html .= '<tr><td>Агент SCCM не активен</td><td>'.$result[0]['p_sccm'].'</td><td>'.$result[0]['o_sccm'].'</td></tr>';
	$html .= '<tr><td>Имя ПК не соответствует стандарту именования</td><td>'.$result[0]['p_name'].'</td><td>'.$result[0]['o_name'].'</td></tr>';
	$html .= '<tr><td>Давно не устанавливались обновления</td><td>'.$result[0]['p_wsus'].' (ТТ: '.$result[0]['p_wsus_tt'].')</td><td>'.$result[0]['o_wsus'].'</td></tr>';
	$html .= '<tr><td>Не установлена квота на ПЯ</td><td>'.$result[0]['p_mbxq'].'</td><td>'.$result[0]['o_mbxq'].'</td></tr>';
	$html .= '<tr><td>Указано неверное местоположение в ИТ Инвент</td><td>'.$result[0]['p_iimv'].'</td><td>'.$result[0]['o_iimv'].'</td></tr>';
	$html .= '<tr><td>Устаревшая операционная система</td><td>'.$result[0]['p_os'].'</td><td>'.$result[0]['o_os'].'</td></tr>';
	$html .= '</table>';

/*
	$html .= '<p>';
	$html .= 'TMAO открытых заявок: '.$result[0]['o_tmao'].', всего проблемных ПК : '.$result[0]['p_tmao'].' (ТТ: '.$result[0]['p_tmao_tt'].', Остальные: '.(intval($result[0]['p_tmao']) - intval($result[0]['p_tmao_tt'])).')<br />';
	$html .= 'TMEE открытых заявок: '.$result[0]['o_tmee'].', всего проблемных ПК : '.$result[0]['p_tmee'].'<br />';
	$html .= 'LAPS открытых заявок: '.$result[0]['o_laps'].', всего проблемных ПК : '.$result[0]['p_laps'].'<br />';
	$html .= 'SCCM открытых заявок: '.$result[0]['o_sccm'].', всего проблемных ПК : '.$result[0]['p_sccm'].'<br />';
	$html .= 'NAME открытых заявок: '.$result[0]['o_name'].', всего проблемных ПК : '.$result[0]['p_name'].'<br />';
	$html .= 'WSUS открытых заявок: '.$result[0]['o_wsus'].', всего проблемных ПК : '.$result[0]['p_wsus'].' (ТТ: '.$result[0]['p_wsus_tt'].', Остальные: '.(intval($result[0]['p_wsus']) - intval($result[0]['p_wsus_tt'])).')<br />';
	$html .= 'MBXQ открытых заявок: '.$result[0]['o_mbxq'].', всего проблемных ПЯ : '.$result[0]['p_mbxq'].'<br />';
	$html .= 'IIMV открытых заявок: '.$result[0]['o_iimv'].', всего проблемных ПК : '.$result[0]['p_iimv'].'<br />';
	$html .= 'OSUP открытых заявок: '.$result[0]['o_os'].', всего проблемных ПК : '.$result[0]['p_os'];
	$html .= '</p>';
*/

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
