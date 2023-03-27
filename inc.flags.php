<?php
/**
	\file
	\brief Файл с описанием флагов и функций для работы с ними
*/

	// Идентификаторы свойств объектов в таблицах `properties_*`

	define('CDB_PROP_USERACCOUNTCONTROL',				101);  // UserAccesControl value from AD
	define('CDB_PROP_OPERATINGSYSTEM',					102);  // OS name from AD
	define('CDB_PROP_OPERATINGSYSTEMVERSION',			103);  // OS version from AD
	define('CDB_PROP_BASELINE_COMPLIANCE_HOTFIX',		104);  // CI - Check - PS - InstallHotFix
	define('CDB_PROP_MAILBOX_QUOTA',					105);  // Mailbox quota
	define('CDB_PROP_LASTLOGONTIMESTAMP',				106);  // Last logon timestamp from DC
	define('CDB_PROP_PWDLASTSET',						107);  // When password changed form AD
	define('CDB_PROP_SID',								108);  // SID from AD
	define('CDB_PROP_TMAO_DLP_STATUS',					109);  // TMAO DLP status
	define('CDB_PROP_BASELINE_COMPLIANCE_RMS_I',		110);  // CI - Software - RMS - Installed
	define('CDB_PROP_BASELINE_COMPLIANCE_RMS_S',		111);  // CI - Software - RMS - Settings
	define('CDB_PROP_BASELINE_COMPLIANCE_RMS_V',		112);  // CI - Software - RMS - Version
	define('CDB_PROP_OPERATINGSYSTEMVERSION_SCCM',		113);  // OS version from SCCM
	define('CDB_PROP_BASELINE_COMPLIANCE_MSDT',			114);  // CI - Check - Regkey: ms-msdt
	define('CDB_PROP_BASELINE_COMPLIANCE_EDGE',			115);  // CI - Check - Regkey: Edge Version
	define('CDB_PROP_OPERATINGSYSTEMVERSION_SCCM_CMP',	116);  // Результат сравнения версии ОС
	define('CDB_PROP_SCCM_CLIENT_VERSION',				117);  // SCCM agent version

	// `flags` from `persons` table

	define('PF_AD_DISABLED',        0x0001);
	define('PF_DELETED',            0x0002);
	define('PF_HIDED',              0x0004);
	//define('PF_TEMP_MARK',          0x0008);

	define('PF_MASK_EXIST',         0x00F0);  // Sum of next flags
	define('PF_EXIST_AD',           0x0010);
	//define('PF_EXIST_TMAO',         0x0020);
	//define('PF_EXIST_TMEE',         0x0040);
	//define('PF_EXIST_SCCM',         0x0080);

	// `flags` from `computers` table

	define('CF_AD_DISABLED',        0x0001);  // or meaning temporary excluded
	define('CF_DELETED',            0x0002);
	define('CF_HIDED',              0x0004);
	//define('CF_TEMP_MARK',          0x0008);

	define('CF_MASK_EXIST',         0x00F0);  // Sum of next flags
	define('CF_EXIST_AD',           0x0010);
	define('CF_EXIST_TMAO',         0x0020);
	define('CF_EXIST_TMEE',         0x0040);
	define('CF_EXIST_SCCM',         0x0080);

	// `flags` from `tasks` table

	define('TF_CLOSED',             0x0001);
	define('TF_FAKE_TASK',          0x0002);  // Used for disable checks
	
	// `type` from `names` table
	
	define('NT_STATUSES',           1);
	define('NT_CI_TYPES',           2);
	define('NT_CI_MODELS',          3);
	define('NT_BRANCHES',           4);
	define('NT_LOCATIONS',          5);

	// `type` from `tasks` table
	
	define('TT_CLOSE', 		        0);  // используется только в шаблонах заявок
	define('TT_MBOX_UNLIM',         1);
	define('TT_INV_MOVE',           2);
	define('TT_INV_TASKFIX',        3);
	define('TT_WIN_UPDATE',         4);
	define('TT_TMAC',               5);
	define('TT_TMEE',               6);
	define('TT_TMAO',               7);
	define('TT_PC_RENAME',          8);
	define('TT_LAPS',               9);
	define('TT_SCCM',               10);
	define('TT_PASSWD',             11);
	define('TT_OS_REINSTALL',       12);
	define('TT_INV_ADD',            13);
	define('TT_VULN_FIX',           14);
	define('TT_VULN_FIX_MASS',      15);
	define('TT_NET_ERRORS',         16);
	define('TT_INV_SOFT',           17);
	define('TT_TMAO_DLP',           18);
	define('TT_INV_ADD_DECOMIS',    19);
	define('TT_RMS_INST',           20);
	define('TT_RMS_SETT',           21);
	define('TT_RMS_VERS',           22);
	define('TT_EDGE_INSTALL',       23);
	define('TT_MSDT',               24);
	define('TT_INV_DUP',            25);
	define('TT_MAXPATROL_AUDIT',    26);
	define('TT_TEST',               999);

	$g_tasks_types = array(
		TT_CLOSE 				=>	'Something went wrong',
		TT_MBOX_UNLIM 			=>	'Не установлена квота на ПЯ',
		TT_INV_MOVE 			=>	'Неправильное местоположение в IT Invent',
		TT_INV_TASKFIX 			=>	'Выяснить причиную повторения заявок IT Invent',
		TT_WIN_UPDATE 			=>	'Несоответствие baseline установка обновлений',
		TT_TMAC 				=>	'Блокировка ПО TMAC',
		TT_TMEE 				=>	'Не установлен или не работает TMEE',
		TT_TMAO 				=>	'Не установлен или не работает TMAO',
		TT_PC_RENAME 			=>	'Имя не соответствует шаблону',
		TT_LAPS 				=>	'Не установлен или не работает LAPS',
		TT_SCCM 				=>	'Не установлен или не работает агент SCCM',
		TT_PASSWD 				=>	'Возможна установка пустого пароля',
		TT_OS_REINSTALL 		=>	'Устаревшая ОС',
		TT_INV_ADD 				=>	'Отсутствует в IT Invent',
		TT_VULN_FIX 			=>	'Обнаружена уязвимость',
		TT_VULN_FIX_MASS 		=>	'Обнаружена массовая уязвимость',
		TT_NET_ERRORS 			=>	'Обнаружены ошибки на сетевом оборудование',
		TT_INV_SOFT 			=>	'Инвентаризация ПО',
		TT_TMAO_DLP 			=>	'Не работает модуль DLP антивируса',
		TT_INV_ADD_DECOMIS 		=>	'Списанное оборудование обнаружено в сети',
		TT_RMS_INST 			=>	'RMS не установлен',
		TT_RMS_SETT 			=>	'Настройки RMS некорректные',
		TT_RMS_VERS 			=>	'Устаревшая версия RMS',
		TT_EDGE_INSTALL 		=>	'Не установлен MS Edge',
		TT_MSDT					=>	'Уязвимость CVE-2022-30190 MSDT',
		TT_INV_DUP				=>	'Обнаружены дубликаты в ИТ Инвент',
		TT_MAXPATROL_AUDIT		=>	'Операционная система не сканировалась MaxPatrol',
		TT_TEST 				=>	'Тестовая заявка. Не должна появляться'
	);

	// `flags` from `ac_log` table

	define('ALF_FIXED',              0x0002);

	// `flags` from `mac` table

	define('MF_TEMP_EXCLUDED',      0x0002);
	define('MF_PERM_EXCLUDED',      0x0004);
	define('MF_EXIST_IN_ZABBIX',    0x0008);
	define('MF_EXIST_IN_ITINV',     0x0010);
	define('MF_FROM_NETDEV',        0x0020);
	define('MF_INV_ACTIVE',         0x0040);
	define('MF_SERIAL_NUM',         0x0080);
	define('MF_INV_MOBILEDEV',      0x0100);
	define('MF_DUPLICATE',          0x0200);
	define('MF_INV_BCCDEV',         0x0400);

	// `flags` from `inv` table

	define('IF_TEMP_EXCLUDED',      0x0002);
	define('IF_PERM_EXCLUDED',      0x0004);
	define('IF_EXIST_IN_ZABBIX',    0x0008);
	define('IF_EXIST_IN_ITINV',     0x0010);
	define('IF_FROM_NETDEV',        0x0020);
	define('IF_INV_ACTIVE',         0x0040);
	define('IF_SERIAL_NUM',         0x0080);
	define('IF_INV_MOBILEDEV',      0x0100);
	define('IF_DUPLICATE',          0x0200);
	define('IF_INV_BCCDEV',         0x0400);

	// `flags` from `zabbix_hosts` table

	define('ZHF_DONT_SYNC',             0x0004);
	define('ZHF_EXIST_IN_ZABBIX',       0x0008);
	define('ZHF_MUST_BE_MONITORED',     0x0010);
	define('ZHF_TEMPLATE_WITH_BCC',     0x0020);

	// `type` from `devices` table

	define('DT_3PAR',               1);
	define('DT_HVCLUST',            2);
	define('DT_NETDEV',             3);
	define('DT_VULN_HOST',          4);

	// `flags` from `net_errors` table

	define('NEF_FIXED',       0x0002);

	// `flags` from `vuln_scans` table

	define('VSF_FIXED',       0x0002);
	define('VSF_HIDED',       0x0004);

	// `flags` from `vulnerbilities` table

	define('VF_HIDED',        0x0004);

	// `flags` from `devices` table

	define('DF_DELETED',      0x0002);
	define('DF_HIDED',        0x0004);

	// `flags` from `files` table

	define('FF_ALLOWED',      0x0010);

	// `flags` from `maxpatrol` table

	define('MPF_EXIST',       0x0010);
	
	// `flags` from `cmdb_assets` table

	define('CAF_NETWORK_ENTITY',     0x0010);

	// `flags` from `vm` table

	define('VMF_EXIST_VMM',       0x0010);
	define('VMF_EXIST_CMDB',      0x0020);
	define('VMF_EXIST_DTLN',      0x0040);
	define('VMF_EXIST_VK',        0x0080);
	define('VMF_EXIST_VSPHERE',   0x0100);
	define('VMF_BAREMETAL',       0x0200);
	define('VMF_EQUIPMENT',       0x0400);
	define('VMF_HAVE_ROOT',       0x0800);

	// `flags` from `vm` table

	define('AGF_EXIST_PALOALTO',    0x0010);
	define('AGF_EXIST_CMDB',  	    0x0020);

	// `flags` from `maxpatrol_smb` table

	define('MSF_UNAUTH_ACCESS',     0x0001);
	define('MSF_EXIST_MAXPATROL',   0x0010);
	define('MSF_EXIST_CMDB',  	    0x0020);

	// `flags` from `files_inventory` table
	
	define('FIF_DELETED',     0x0002);

	// `tid` from `properties_*` tables

	define('TID_COMPUTERS',   1);
	define('TID_PERSONS',     2);
	define('TID_MAC',         3);
	define('TID_AC_LOG',      4);
	define('TID_VULN_SCANS',  5);
	define('TID_VULNS',       6);
	define('TID_DEVICES',     7);
	define('TID_FILES',       8);
	define('TID_VM',          9);


/**
	Функция возвращает текстовое представление статуса шифрования TMEE

	@param [in] $code  Числовой код шифрования
	@return Преобразованный в читаемый вид код шифрования
	
	\todo Переделать на массив
*/
function tmee_status($code)
{
	switch($code)
	{
		case 1: return 'Not Encrypted';
		case 2: return 'Encrypted';
		case 3: return 'Encrypting';
		case 4: return 'Decrypting';
	}
	return 'Unknown';
}

$g_dlp_statuses = array(
	'Not installed',
	'Running',
	'Stopped',
	'Cannot install (Data Loss Prevention already exists)',
	'Requires restart'
);

$g_tasks_flags = array(
	'Заявка закрыта',
	'Фиктивная заявка для отключения проверки'
);

$g_comp_flags = array(
	'Disabled',
	'Deleted',
	'Hide',
	'',
	'Active Directory',
	'Apex One',
	'Encryption Endpoint',
	'Configuration Manager'
);

$g_comp_short_flags = array(
	'D',
	'R',
	'H',
	'',
	'A',
	'O',
	'E',
	'C'
);

$g_mac_flags = array(
	'',
	'Temporary excluded',
	'Permanently excluded',
	'',
	'IT Invent',
	'netdev',
	'Active',
	'Serial number',
	'Mobile device',
	'Duplicate detected',
	'Backup CommChannel'
);

$g_mac_short_flags = array(
	'',
	'T',
	'R',
	'',
	'I',
	'N',
	'A',
	'S',
	'M',
	'D',
	'B'
);

$g_inv_flags = array(
	'',
	'Temporary excluded',
	'Permanently excluded',
	'',
	'IT Invent',
	'',
	'Active',
	'',
	'Mobile device',
	'Duplicate detected',
	'Backup CommChannel'
);

$g_inv_short_flags = array(
	'',
	'T',
	'R',
	'',
	'I',
	'',
	'A',
	'',
	'M',
	'D',
	'B'
);

$g_ac_flags = array(
	'',
	'Fixed'
);

$g_files_inventory_flags = array(
	'',
	'Deleted'
);

$g_files_inventory_short_flags = array(
	'',
	'D'
);

$g_vulns_flags = array(
	'',
	'',
	'Hided'
);

$g_vulns_short_flags = array(
	'',
	'',
	'H'
);

$g_vuln_scans_flags = array(
	'',
	'Fixed',
	'Hided'
);

$g_vuln_scans_short_flags = array(
	'',
	'F',
	'H'
);

$g_files_flags = array(
	'',
	'',
	'',
	'',
	'Allowed (exist in IT Invent)'
);

$g_files_short_flags = array(
	'',
	'',
	'',
	'',
	'A'
);

$g_cmdb_vmm_flags = array(
	'',
	'',
	'',
	'',
	'Exist in VMM',
	'Exist in CMDB',
	'Exist in DTLN',
	'Exist in VK',
	'Exist in vSphere'
);

$g_cmdb_vmm_short_flags = array(
	'',
	'',
	'',
	'',
	'V',
	'C',
	'D',
	'M',
	'S'
);

/**
	Функция преобразует значения бит в человекочитабельный вид

	@param [in] $flags  Числовое значение битовых флагов
	@param [in] $texts  Массив с текстовым описанием флагов
	@param [in] $delimiter  Разделитель. По умолчанию равен ' '
	@param [in] $notset  Текстовое значение для неустановленного бита. По умолчанию равен ''
	@return Значения бит преобразованные в читабельный текст
*/
function flags_to_string($flags, $texts, $delimiter = ' ', $notset = '')
{
	$result = '';
	$delim = '';
	for($i = 0; $i < count($texts); $i++)
	{
		if(($flags >> $i) & 0x01)
		{
			$result .= $delim.$texts[$i];
			$delim = $delimiter;
		}
		else
		{
			$result .= $notset;
		}
	}
	return $result;
}

/**
	Функция возвращает текстовое представление кода

	@param [in] $code  Числовой код
	@return Преобразованный в читаемый вид код
*/
function code_to_string($codes, $code)
{
	if(isset($codes[$code]))
	{
		return $codes[$code];
	}

	return 'Unknown';
}

/**
	Устаревшая функция заменена flags_to_string.
	\sa flags_to_string()
	\todo Заменить во всех модулях на flags_to_string()
*/
function tasks_flags_to_string($flags)  // replace with flags_to_string() later
{
	global $g_tasks_flags;

	$result = '';
	$delimiter = '';
	for($i = 0; $i < count($g_tasks_flags); $i++)
	{
		if(($flags >> $i) & 0x01)
		{
			$result .= $delimiter.$g_tasks_flags[$i];
			$delimiter = ' ';
		}
	}
	return $result;
}

/**
	Функция формирует описание для флагой из коротких и длинных наименований

	@param [in] $short_flags  Массив с кратким текстовым описанием флагов
	@param [in] $long_flags  Массив с полным текстовым описанием флагов
	@param [in] $delimiter  Разделитель. По умолчанию равен ', '
	@return Суммарное описание всех флагов
*/
function flags_to_legend($short_flags, $long_flags, $delimiter = ', ')
{
	$result = '';
	$delim = '';
	for($i = 0; $i < count($short_flags); $i++)
	{
		if(!empty($short_flags[$i]) && !empty($long_flags[$i]))
		{
			$result .= $delim.$short_flags[$i].' - '.$long_flags[$i];
			$delim = $delimiter;
		}
	}
	return $result;
}

