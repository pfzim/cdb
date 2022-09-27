<?php
	// Report for opened HelpDesk tasks

	/**
		\file
		\brief Формирование отчёта по открытым заявкам в системе HelpDesk.
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
EOT;

	$html .= '<small>Версия: '.CDB_VERSION.'</small>';
	$html .= '<h1>Отчёт по ранее выставленным не закрытым заявкам в HelpDesk</h1>';

	$table = '<table>';
	$table .= '<tr><th>Name</th><th>AV Pattern version</th><th>Last update</th><th>TMEE Status</th><th>TMEE Last sync</th><th>HD Task</th><th>Reason</th><th>Source</th><th>Issues</th></tr>';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			c.`id`,
			c.`name`,
			c.`ao_script_ptn`,
			DATE_FORMAT(c.`ao_ptnupdtime`, '%d.%m.%Y %H:%i:%s') AS `last_update`,
			c.`ee_encryptionstatus`,
			DATE_FORMAT(c.`ee_lastsync`, '%d.%m.%Y %H:%i:%s') AS `last_sync`,
			t.`operid`,
			t.`opernum`,
			t.`type`,
			t.`flags`,
			c.`flags` AS c_flags,
			(
				SELECT COUNT(*)
				FROM @tasks AS t2
				WHERE t2.`pid` = t.`pid`
					AND t2.`tid` = {%TID_COMPUTERS}
					AND t2.`type` = t.`type`
					AND (t2.`flags` & {%TF_CLOSED}) = {%TF_CLOSED}
					AND t2.`date` > DATE_SUB(NOW(), INTERVAL 1 MONTH)
			) AS `issues`
		FROM @tasks AS t
		LEFT JOIN @computers AS c ON c.`id` = t.`pid`
		WHERE
			t.`tid` = {%TID_COMPUTERS}
			AND (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0
		ORDER BY c.`name`
	")))
	{
		global $g_comp_short_flags;
		global $g_tasks_types;

		foreach($result as &$row)
		{
			$table .= '<tr>';
			$table .= '<td><a href="'.CDB_URL.'-ui/cdb_ui.php?path=computer_info/0/'.$row['id'].'">'.$row['name'].'</a></td>';
			$table .= '<td>'.$row['ao_script_ptn'].'</td><td>'.$row['last_update'].'</td>';
			$table .= '<td>'.tmee_status(intval($row['ee_encryptionstatus'])).'</td><td>'.$row['last_sync'].'</td>';
			$table .= '<td><a href="'.HELPDESK_URL.'/QueryView.aspx?KeyValue='.$row['operid'].'">'.$row['opernum'].'</a></td>';
			$table .= '<td>'.code_to_string($g_tasks_types, intval($row['type'])).'</td>';
			$table .= '<td>'.flags_to_string(intval($row['c_flags']) & CF_MASK_EXIST, $g_comp_short_flags, '', '-').'</td>';
			$table .= '<td'.((intval($row['issues']) > 3)?' class="error"':'').'>'.$row['issues'].'</td>';
			$table .= '</tr>';

			$i++;
		}
	}

	$table .= '</table>';

	if(!$db->select_assoc_ex($result, rpv("
			SELECT
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_TMAO}) AS `o_tmao`,
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_TMEE}) AS `o_tmee`,
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_LAPS}) AS `o_laps`,
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_SCCM}) AS `o_sccm`,
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_RMS_INST}) AS `o_rmsi`,
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_RMS_SETT}) AS `o_rmss`,
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_RMS_VERS}) AS `o_rmsv`,
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_EDGE_INSTALL}) AS `o_edge`,
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_PC_RENAME}) AS `o_name`,
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_TMAO_DLP}) AS `o_tmao_dlp`,
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_OS_REINSTALL})  AS `o_os`,
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_WIN_UPDATE}) AS `o_wsus`,
			(SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_MBOX_UNLIM}) AS `o_mbxq`,

			(SELECT COUNT(*) FROM @computers AS c WHERE (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0 AND c.`delay_checks` < CURDATE() AND c.`name` NOT REGEXP {s0} AND c.`ao_script_ptn` < ((SELECT CAST(MAX(`ao_script_ptn`) AS SIGNED) FROM @computers) - {%TMAO_PATTERN_VERSION_LAG})) AS `p_tmao`,
			(SELECT COUNT(*) FROM @computers AS c WHERE (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0 AND c.`delay_checks` < CURDATE() AND c.`name` REGEXP {s1} AND c.`ao_script_ptn` < ((SELECT CAST(MAX(`ao_script_ptn`) AS SIGNED) FROM @computers) - {%TMAO_PATTERN_VERSION_LAG})) AS `p_tmao_tt`,
			(SELECT COUNT(*) FROM @computers AS c WHERE (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0 AND c.`delay_checks` < CURDATE() AND c.`name` regexp {s3} AND c.`ee_encryptionstatus` <> 2) AS `p_tmee`,
			(SELECT COUNT(*) FROM @computers AS c WHERE (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0 AND c.`delay_checks` < CURDATE() AND c.`laps_exp` < DATE_SUB(NOW(), INTERVAL {%LAPS_EXPIRE_DAYS} DAY)) AS `p_laps`,
			(SELECT COUNT(*) FROM @computers AS c WHERE (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0 AND c.`delay_checks` < CURDATE() AND c.`sccm_lastsync` < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND c.`name` NOT REGEXP {s0}) AS `p_sccm`,
			(SELECT COUNT(*) FROM @computers AS c WHERE (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0 AND c.`delay_checks` < CURDATE() AND c.`name` NOT REGEXP {s2}) AS `p_name`,
			(
				SELECT
					COUNT(*)
				FROM @properties_int AS dlp_status
				LEFT JOIN @computers AS c
					ON
					dlp_status.`tid` = {%TID_COMPUTERS}
					AND dlp_status.`oid` = {%CDB_PROP_TMAO_DLP_STATUS}
					AND dlp_status.`pid` = c.`id`
				WHERE
					dlp_status.`tid` = {%TID_COMPUTERS}
					AND dlp_status.`oid` = {%CDB_PROP_TMAO_DLP_STATUS}
					AND (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
					AND c.`delay_checks` < CURDATE()
					AND dlp_status.`value` <> 1
					AND c.`name` NOT REGEXP {s0}
			) AS `p_tmao_dlp`,
			(
				SELECT
					COUNT(*)
				FROM @properties_str AS os
				LEFT JOIN @computers AS c
					ON
					os.`tid` = {%TID_COMPUTERS}
					AND os.`oid` = {%CDB_PROP_OPERATINGSYSTEM}
					AND os.`pid` = c.`id`
				WHERE
					os.`tid` = {%TID_COMPUTERS}
					AND os.`oid` = {%CDB_PROP_OPERATINGSYSTEM}
					AND (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
					AND c.`delay_checks` < CURDATE()
					AND os.`value` NOT IN (
						'Windows 10 Корпоративная 2016 с долгосрочным обслуживанием',
						'Windows 10 Корпоративная',
						'Windows 10 Корпоративная LTSC',
						'Windows 10 Enterprise'
					)
					AND c.`name` NOT REGEXP {s0}
			) AS `p_os`,
			(
				SELECT
					COUNT(*)
				FROM @properties_int AS os_ver
				LEFT JOIN @computers AS c
					ON
					os_ver.`tid` = {%TID_COMPUTERS}
					AND os_ver.`oid` = {%CDB_PROP_OPERATINGSYSTEMVERSION_SCCM_CMP}
					AND os_ver.`pid` = c.`id`
				WHERE
					os_ver.`tid` = {%TID_COMPUTERS}
					AND os_ver.`oid` = {%CDB_PROP_OPERATINGSYSTEMVERSION_SCCM_CMP}
					AND (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
					AND c.`delay_checks` < CURDATE()
					AND os_ver.`value`  < 0
					AND c.`name` NOT REGEXP {s0}
			) AS `p_os_sccm`,
			(
				SELECT
					COUNT(*)
				FROM @properties_int AS os
				LEFT JOIN @computers AS c
					ON
						os.`tid` = {%TID_COMPUTERS}
						AND os.`oid` = {%CDB_PROP_BASELINE_COMPLIANCE_HOTFIX}
						AND os.`pid` = c.`id`
				WHERE
					os.`tid` = {%TID_COMPUTERS}
					AND os.`oid` = {%CDB_PROP_BASELINE_COMPLIANCE_HOTFIX}
					AND (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
					AND c.`delay_checks` < CURDATE()
					AND os.`value` <> 1
			) AS `p_wsus`,
			(
				SELECT
					COUNT(*)
				FROM @properties_int AS os
				LEFT JOIN @computers AS c
					ON
						os.`tid` = {%TID_COMPUTERS}
						AND os.`oid` = {%CDB_PROP_BASELINE_COMPLIANCE_HOTFIX}
						AND os.`pid` = c.`id`
				WHERE
					os.`tid` = {%TID_COMPUTERS}
					AND os.`oid` = {%CDB_PROP_BASELINE_COMPLIANCE_HOTFIX}
					AND (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
					AND c.`delay_checks` < CURDATE()
					AND os.`value` <> 1
					AND c.`name` REGEXP {s1}
			) AS `p_wsus_tt`,
			(
				SELECT
					COUNT(*)
				FROM @properties_int AS rmsi_value
				LEFT JOIN @computers AS c
					ON c.`id` = rmsi_value.`pid`
				WHERE
					rmsi_value.`tid` = {%TID_COMPUTERS}
					AND rmsi_value.`oid` = {%CDB_PROP_BASELINE_COMPLIANCE_RMS_I}
					AND (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
					AND c.`delay_checks` < CURDATE()
					AND rmsi_value.`value` <> 1
					AND c.`name` REGEXP {s4}
			) AS `p_rmsi`,
			(
				SELECT
					COUNT(*)
				FROM @properties_int AS rmss_value
				LEFT JOIN @computers AS c
					ON c.`id` = rmss_value.`pid`
				WHERE
					rmss_value.`tid` = {%TID_COMPUTERS}
					AND rmss_value.`oid` = {%CDB_PROP_BASELINE_COMPLIANCE_RMS_S}
					AND (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
					AND c.`delay_checks` < CURDATE()
					AND rmss_value.`value` <> 1
					AND c.`name` REGEXP {s4}
			) AS `p_rmss`,
			(
				SELECT
					COUNT(*)
				FROM @properties_int AS rmsv_value
				LEFT JOIN @computers AS c
					ON c.`id` = rmsv_value.`pid`
				WHERE
					rmsv_value.`tid` = {%TID_COMPUTERS}
					AND rmsv_value.`oid` = {%CDB_PROP_BASELINE_COMPLIANCE_RMS_V}
					AND (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
					AND c.`delay_checks` < CURDATE()
					AND rmsv_value.`value` <> 1
					AND c.`name` REGEXP {s4}
			) AS `p_rmsv`,

			(
				SELECT
					COUNT(*)
				FROM @properties_int AS msdt_value
				LEFT JOIN @computers AS c
					ON c.`id` = msdt_value.`pid`
				WHERE
					msdt_value.`tid` = {%TID_COMPUTERS}
					AND msdt_value.`oid` = {%CDB_PROP_BASELINE_COMPLIANCE_MSDT}
					AND (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
					AND c.`delay_checks` < CURDATE()
					AND msdt_value.`value` <> 1
					AND c.`name` NOT REGEXP {s0}
			) AS `p_msdt`,

			(
				SELECT
					COUNT(*)
				FROM @properties_int AS edge_value
				LEFT JOIN @computers AS c
					ON c.`id` = edge_value.`pid`
				WHERE
					edge_value.`tid` = {%TID_COMPUTERS}
					AND edge_value.`oid` = {%CDB_PROP_BASELINE_COMPLIANCE_EDGE}
					AND (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
					AND c.`delay_checks` < CURDATE()
					AND edge_value.`value` <> 1
					AND c.`name` NOT REGEXP {s0}
			) AS `p_edge`,

			(
				SELECT COUNT(*)
				FROM @persons AS p
				LEFT JOIN @properties_int AS j_quota
					ON j_quota.`tid` = {%TID_PERSONS}
					AND j_quota.`pid` = p.`id`
					AND j_quota.`oid` = {%CDB_PROP_MAILBOX_QUOTA}
				WHERE
					(p.`flags` & ({%PF_AD_DISABLED} | {%PF_DELETED} | {%PF_HIDED})) = 0
					AND j_quota.`value` = 0
			) AS `p_mbxq`,
			(SELECT COUNT(*) FROM @computers WHERE (`flags` & ({%CF_EXIST_AD})) = {%CF_EXIST_AD}) AS `objects_from_ad`,
			(SELECT COUNT(*) FROM @computers WHERE (`flags` & ({%CF_EXIST_AD})) = {%CF_EXIST_AD} AND (`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED}))) AS `disabled_objects_from_ad`,
			(SELECT COUNT(*) FROM @computers WHERE (`flags` & ({%CF_EXIST_TMAO})) = {%CF_EXIST_TMAO}) AS `objects_from_tmao`,
			(SELECT COUNT(*) FROM @computers WHERE (`flags` & ({%CF_EXIST_TMEE})) = {%CF_EXIST_TMEE}) AS `objects_from_tmee`,
			(SELECT COUNT(*) FROM @computers WHERE (`flags` & ({%CF_EXIST_SCCM})) = {%CF_EXIST_SCCM}) AS `objects_from_sccm`,
			(SELECT COUNT(*) FROM @persons WHERE (`flags` & ({%PF_EXIST_AD})) = {%PF_EXIST_AD}) AS `users_from_ad`
		",
		CDB_REGEXP_SERVERS,
		CDB_REGEXP_SHOPS,
		CDB_REGEXP_VALID_NAMES,
		CDB_REGEXP_NOTEBOOK_NAME,
		CDB_REGEXP_OFFICES
	)))
	{
		// error
	}

	$html .= '<table>';
	$html .= '<tr><th>Проблема</th>                                                <th>Проблемных ПК</th>                                               <th>Открыто заявок</th>               <th>Лимит заявок</th></tr>';
	$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_TMAO).'</td>         <td>'.$result[0]['p_tmao'].' (ТТ: '.$result[0]['p_tmao_tt'].')</td>  <td>'.$result[0]['o_tmao'].'</td>     <td>'.TASKS_LIMIT_TMAO_GOO.' + '.TASKS_LIMIT_TMAO_GUP.'</td></tr>';
	$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_TMAO_DLP).'</td>     <td>'.$result[0]['p_tmao_dlp'].'</td>                                <td>'.$result[0]['o_tmao_dlp'].'</td> <td>'.TASKS_LIMIT_TMAO_DLP_GUP.' + '.TASKS_LIMIT_TMAO_DLP_GOO.'</td></tr>';
	$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_TMEE).'</td>         <td>'.$result[0]['p_tmee'].'</td>                                    <td>'.$result[0]['o_tmee'].'</td>     <td>∞</td></tr>';
	$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_LAPS).'</td>         <td>'.$result[0]['p_laps'].'</td>                                    <td>'.$result[0]['o_laps'].'</td>     <td>'.TASKS_LIMIT_LAPS.'</td></tr>';
	$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_SCCM).'</td>         <td>'.$result[0]['p_sccm'].'</td>                                    <td>'.$result[0]['o_sccm'].'</td>     <td>'.TASKS_LIMIT_SCCM.'</td></tr>';
	$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_PC_RENAME).'</td>    <td>'.$result[0]['p_name'].'</td>                                    <td>'.$result[0]['o_name'].'</td>     <td>'.TASKS_LIMIT_RENAME.'</td></tr>';
	$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_WIN_UPDATE).' (CI: '.SCCM_IHF_CI_ID.', VER: '.SCCM_IHF_CI_VERSION.')</td>   <td>'.$result[0]['p_wsus'].' (ТТ: '.$result[0]['p_wsus_tt'].')</td>  <td>'.$result[0]['o_wsus'].'</td>     <td>'.TASKS_LIMIT_WSUS_GUP.'</td></tr>';
	$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_MBOX_UNLIM).'</td>   <td>'.$result[0]['p_mbxq'].'</td>                                    <td>'.$result[0]['o_mbxq'].'</td>     <td>'.TASKS_LIMIT_MBX.'</td></tr>';
	$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_OS_REINSTALL).'</td> <td>'.$result[0]['p_os'].'</td>                                      <td>'.$result[0]['o_os'].'</td>       <td>'.TASKS_LIMIT_OS.'</td></tr>';
	$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_OS_REINSTALL).' (< '.CHECK_OPERATION_SYSTEM_VERSION_SCCM.')</td> <td>'.$result[0]['p_os_sccm'].'</td>                                 <td>'.$result[0]['o_os'].'</td>       <td>'.TASKS_LIMIT_OS.'</td></tr>';
	$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_RMS_INST).' (CI: '.SCCM_RMSI_CI_ID.', VER: '.SCCM_RMSI_CI_VERSION.')</td>     <td>'.$result[0]['p_rmsi'].'</td>                                    <td>'.$result[0]['o_rmsi'].'</td>     <td>'.TASKS_LIMIT_RMS_I.'</td></tr>';
	$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_RMS_SETT).' (CI: '.SCCM_RMSS_CI_ID.', VER: '.SCCM_RMSS_CI_VERSION.')</td>     <td>'.$result[0]['p_rmss'].'</td>                                    <td>'.$result[0]['o_rmss'].'</td>     <td>'.TASKS_LIMIT_RMS_S.'</td></tr>';
	$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_RMS_VERS).' (CI: '.SCCM_RMSV_CI_ID.', VER: '.SCCM_RMSV_CI_VERSION.')</td>     <td>'.$result[0]['p_rmsv'].'</td>                                    <td>'.$result[0]['o_rmsv'].'</td>     <td>'.TASKS_LIMIT_RMS_V.'</td></tr>';
	$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_MSDT).' (CI: '.SCCM_MSDT_CI_ID.', VER: '.SCCM_MSDT_CI_VERSION.')</td>         <td>'.$result[0]['p_msdt'].'</td>                                    <td>0</td>                            <td>0</td></tr>';
	$html .= '<tr><td>'.code_to_string($g_tasks_types, TT_EDGE_INSTALL).' (CI: '.SCCM_EDGE_CI_ID.', VER: '.SCCM_EDGE_CI_VERSION.')</td> <td>'.$result[0]['p_edge'].'</td>                                    <td>'.$result[0]['o_edge'].'</td>     <td>'.TASKS_LIMIT_EDGE.'</td></tr>';
	$html .= '</table>';

	$html .= '<br /><table>';
	$html .= '<tr><th>Объект</th>               <th>Количество</th></tr>';
	$html .= '<tr><td>Компьютеров в AD</td>     <td>'.$result[0]['objects_from_ad'].' (в том числе отключенные '.$result[0]['disabled_objects_from_ad'].')</td></tr>';
	$html .= '<tr><td>Компьютеров в TMAO</td>   <td>'.$result[0]['objects_from_tmao'].'</td></tr>';
	$html .= '<tr><td>Компьютеров в TMEE</td>   <td>'.$result[0]['objects_from_tmee'].'</td></tr>';
	$html .= '<tr><td>Компьютеров в SCCM</td>   <td>'.$result[0]['objects_from_sccm'].'</td></tr>';
	$html .= '<tr><td>Пользователей в AD</td>   <td>'.$result[0]['users_from_ad'].'</td></tr>';
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

	if(php_mailer(MAIL_TO_TASKS_STATUS, CDB_TITLE.': Opened tasks', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
