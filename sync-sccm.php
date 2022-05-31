<?php
	// Retrieve information from SCCM database

	/**
		\file
		\brief Синхронизация с БД SCCM (состояние агента, baseline обновления).

		Загрузка данных о дате последней активности агента и информации по соответствию базовому уровню установки обновлений на ПК.

		Дата последней активности агента вычисляется из параметров LastDDR, LastPolicyRequest, LastOnline, LastSW,
		LastHealthEvaluation, LastStatusMessage, LastHW. Берется самое свежее из значений.

	*/

	/*
		Определение идентификатора SCCM_CI_ID

		SELECT
			TOP 1000
			v_ConfigurationItems.CI_ID,
			v_LocalizedCIProperties.DisplayName
		FROM v_ConfigurationItems
		INNER JOIN v_LocalizedCIProperties
		ON v_ConfigurationItems.CI_ID = v_LocalizedCIProperties.CI_ID
		--AND  (v_LocalizedCIProperties.TopicType = 401)
		WHERE
			CIType_ID = 3
			AND v_LocalizedCIProperties.DisplayName = 'CI - Check - PS - InstallHotFix'

	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-sccm:\n";

	$params = array(
		'Database' =>				SCCM_DB_NAME,
		'UID' =>					SCCM_DB_USER,
		'PWD' =>					SCCM_DB_PASSWD,
		'ReturnDatesAsStrings' =>	true
	);

	$conn = sqlsrv_connect(SCCM_DB_HOST, $params);
	if($conn === false)
	{
		print_r(sqlsrv_errors());
		exit;
	}

	$result = sqlsrv_query($conn, rpv("
		SELECT
			m.ItemKey AS ResourceID,
			m.Netbios_Name0 AS DeviceName,
			m.Creation_Date0 AS CreationDate,
			j1.LastDDR,
			j1.LastPolicyRequest,
			j1.LastOnline,
			j1.LastSW,
			j1.LastHealthEvaluation,
			j1.LastStatusMessage,
			j1.LastHW,
			ihf.ComplianceState AS ihf_value,
			rmsi.ComplianceState AS rmsi_value,
			rmss.ComplianceState AS rmss_value,
			rmsv.ComplianceState AS rmsv_value,
			CASE
				WHEN ReportsIgnore.Value IS NULL THEN 0
				WHEN ReportsIgnore.Value = '' THEN 0
				WHEN ReportsIgnore.Value = '0' THEN 0
				ELSE 1
			END AS delay_checks
		FROM [dbo].[System_DISC] AS m
		LEFT JOIN [dbo].[CH_ClientSummary] AS j1
			ON m.ItemKey = j1.MachineID
		LEFT JOIN [dbo].[vCICurrentComplianceStatus] AS ihf
			ON m.ItemKey = ihf.ItemKey
			AND ihf.CI_ID = {%SCCM_IHF_CI_ID}
			AND ihf.CIVersion = {%SCCM_IHF_CI_VERSION}
		LEFT JOIN [dbo].[vCICurrentComplianceStatus] AS rmsi
			ON m.ItemKey = rmsi.ItemKey
			AND rmsi.CI_ID = {%SCCM_RMSI_CI_ID}
			AND rmsi.CIVersion = {%SCCM_RMSI_CI_VERSION}
		LEFT JOIN [dbo].[vCICurrentComplianceStatus] AS rmss
			ON m.ItemKey = rmss.ItemKey
			AND rmss.CI_ID = {%SCCM_RMSS_CI_ID}
			AND rmss.CIVersion = {%SCCM_RMSS_CI_VERSION}
		LEFT JOIN [dbo].[vCICurrentComplianceStatus] AS rmsv
			ON m.ItemKey = rmsv.ItemKey
			AND rmsv.CI_ID = {%SCCM_RMSV_CI_ID}
			AND rmsv.CIVersion = {%SCCM_RMSV_CI_VERSION}
		LEFT JOIN [dbo].[DeviceExtensionData] AS ReportsIgnore
			ON ReportsIgnore.ResourceID = m.ItemKey
			AND ReportsIgnore.PropertyId = {%SCCM_PROP_REPORTS_IGNORE_ID}
		WHERE
			ISNULL(m.Obsolete0, 0) <> 1
			AND ISNULL(m.Decommissioned0, 0) <> 1
			AND m.Client0 = 1
	"));

/*
		SELECT
			m.ItemKey AS ResourceID,
			m.Netbios_Name0 AS DeviceName,
			j1.LastDDR,
			j1.LastPolicyRequest,
			j1.LastOnline,
			j1.LastSW,
			j1.LastHealthEvaluation,
			j1.LastStatusMessage,
			j1.LastHW
		FROM [".SCCM_DB_NAME."].[dbo].[System_DISC] AS m
		LEFT JOIN [".SCCM_DB_NAME."].[dbo].[CH_ClientSummary] AS j1 ON m.ItemKey = j1.MachineID
		WHERE ISNULL(m.Obsolete0, 0) <> 1 AND ISNULL(m.Decommissioned0, 0) <> 1 AND m.Client0 = 1


		v_R_System.Name0,
		v_R_System.Netbios_Name0,
		[vCICurrentComplianceStatus].ComplianceState
	FROM [CM_M01].[dbo].[vCICurrentComplianceStatus]
	LEFT JOIN v_R_System ON v_R_System.ResourceID = [vCICurrentComplianceStatus].ItemKey
	LEFT JOIN v_StateNames ON [vCICurrentComplianceStatus].ComplianceState = v_StateNames.StateID AND (v_StateNames.TopicType = 401)
	WHERE CI_ID = '' AND CIVersion = 6



		SELECT
			m.ItemKey AS ResourceID,
			m.Netbios_Name0 AS DeviceName,
			j1.LastDDR,
			j1.LastPolicyRequest,
			j1.LastOnline,
			j1.LastSW,
			j1.LastHealthEvaluation,
			j1.LastStatusMessage,
			j1.LastHW,
			j2.ComplianceState
		FROM [dbo].[System_DISC] AS m
		LEFT JOIN [dbo].[CH_ClientSummary] AS j1
			ON m.ItemKey = j1.MachineID
		LEFT JOIN [dbo].[vCICurrentComplianceStatus] AS j2
			ON m.ItemKey = j2.ItemKey
			AND CI_ID = ''
			AND CIVersion = 6
		WHERE ISNULL(m.Obsolete0, 0) <> 1 AND ISNULL(m.Decommissioned0, 0) <> 1 AND m.Client0 = 1

*/

	$columns = array('LastDDR', 'LastPolicyRequest', 'LastSW', 'LastHealthEvaluation', 'LastStatusMessage', 'LastHW');

	$i = 0;
	while($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC))
	{
		//echo $row['DeviceName'].", ".$row['LastSync'].", ".$row['EncryptionStatus']."\r\n";

		$lastsync = '0000-00-00 00:00:00';
		$max_date = strtotime($lastsync);

		foreach($columns as &$col)
		{
			if(!empty($row[$col]))
			{
				$tmp_date = strtotime($row[$col]);
				if($tmp_date > $max_date)
				{
					$max_date = $tmp_date;
					$lastsync = $row[$col];
				}
			}
		}

		$dt = new DateTime($row['CreationDate']);

		if(intval($row['delay_checks']))
		{
			$dt->add(new DateInterval('P7D'));
		}

		$delay_checks = $dt->format('Y-m-d');

		$row_id = 0;
		if(!$db->select_ex($res, rpv("SELECT m.`id` FROM @computers AS m WHERE m.`name` = ! LIMIT 1", $row['DeviceName'])))
		{
			if($db->put(rpv("INSERT INTO @computers (`name`, `sccm_lastsync`, `delay_checks`, `flags`) VALUES (!, !, !, {%CF_EXIST_SCCM})",
				$row['DeviceName'],
				$lastsync,
				$delay_checks,
			)))
			{
				$row_id = $db->last_id();
			}
		}
		else
		{
			$row_id = $res[0][0];
			$db->put(rpv("UPDATE @computers SET `sccm_lastsync` = !, `delay_checks` = !, `flags` = ((`flags` & ~{%CF_TEMP_MARK}) | {%CF_EXIST_SCCM}) WHERE `id` = # LIMIT 1",
				$lastsync,
				$delay_checks,
				$row_id
			));
		}

		if($row_id)
		{
			$db->put(rpv("INSERT INTO @properties_int (`tid`, `pid`, `oid`, `value`) VALUES ({%TID_COMPUTERS}, {d0}, {d1}, {d2}) ON DUPLICATE KEY UPDATE `value` = {d2}",
				$row_id,
				CDB_PROP_BASELINE_COMPLIANCE_HOTFIX,
				(intval($row['ihf_value']) == 1) ? 1 : 0
			));

			$db->put(rpv("INSERT INTO @properties_int (`tid`, `pid`, `oid`, `value`) VALUES ({%TID_COMPUTERS}, {d0}, {d1}, {d2}) ON DUPLICATE KEY UPDATE `value` = {d2}",
				$row_id,
				CDB_PROP_BASELINE_COMPLIANCE_RMS_I,
				(intval($row['rmsi_value']) == 1) ? 1 : 0
			));

			$db->put(rpv("INSERT INTO @properties_int (`tid`, `pid`, `oid`, `value`) VALUES ({%TID_COMPUTERS}, {d0}, {d1}, {d2}) ON DUPLICATE KEY UPDATE `value` = {d2}",
				$row_id,
				CDB_PROP_BASELINE_COMPLIANCE_RMS_S,
				(intval($row['rmss_value']) == 1) ? 1 : 0
			));

			$db->put(rpv("INSERT INTO @properties_int (`tid`, `pid`, `oid`, `value`) VALUES ({%TID_COMPUTERS}, {d0}, {d1}, {d2}) ON DUPLICATE KEY UPDATE `value` = {d2}",
				$row_id,
				CDB_PROP_BASELINE_COMPLIANCE_RMS_V,
				(intval($row['rmsv_value']) == 1) ? 1 : 0
			));
		}

		$i++;
	}

	echo 'Count: '.$i."\r\n";

	sqlsrv_free_stmt($result);

	sqlsrv_close($conn);
