<?php
/**
	\file
	\brief Файл с описанием флагов и функцийй для работы с ними
*/

	// Идентификаторы свойств объектов

	define('CDB_PROP_USERACCOUNTCONTROL',			101);
	define('CDB_PROP_OPERATINGSYSTEM',				102);
	define('CDB_PROP_OPERATINGSYSTEMVERSION',		103);
	define('CDB_PROP_BASELINE_COMPLIANCE_HOTFIX',	104);
	define('CDB_PROP_MAILBOX_QUOTA',				105);
	define('CDB_PROP_LASTLOGONTIMESTAMP',			106);
	define('CDB_PROP_PWDLASTSET',					107);
	define('CDB_PROP_SID',							108);

	// `flags` from `persons` table

	define('PF_AD_DISABLED',        0x0001);
	define('PF_DELETED',            0x0002);
	define('PF_HIDED',              0x0004);
	define('PF_TEMP_MARK',          0x0008);

	define('PF_MASK_EXIST',         0x00F0);
	define('PF_EXIST_AD',           0x0010);
	//define('PF_EXIST_TMAO',         0x0020);
	//define('PF_EXIST_TMEE',         0x0040);
	//define('PF_EXIST_SCCM',         0x0080);

	// `flags` from `computers` table

	define('CF_AD_DISABLED',        0x0001);
	define('CF_DELETED',            0x0002);
	define('CF_HIDED',              0x0004);
	define('CF_TEMP_MARK',          0x0008);

	define('CF_MASK_EXIST',         0x00F0);
	define('CF_EXIST_AD',           0x0010);
	define('CF_EXIST_TMAO',         0x0020);
	define('CF_EXIST_TMEE',         0x0040);
	define('CF_EXIST_SCCM',         0x0080);

	// `flags` from `tasks` table

	define('TF_CLOSED',             0x000001);
	define('TF_MBOX_UNLIM',         0x000008);
	define('TF_INV_MOVE',           0x000010);
	define('TF_INV_TASKFIX',        0x000020);
	define('TF_WIN_UPDATE',         0x000040);
	define('TF_TMAC',               0x000080);
	define('TF_TMEE',               0x000100);
	define('TF_TMAO',               0x000200);
	define('TF_PC_RENAME',          0x000400);
	define('TF_LAPS',               0x000800);
	define('TF_SCCM',               0x001000);
	define('TF_PASSWD',             0x002000);
	define('TF_OS_REINSTALL',       0x004000);
	define('TF_INV_ADD',            0x008000);
	define('TF_VULN_FIX',           0x010000);
	define('TF_VULN_FIX_MASS',      0x020000);
	define('TF_NET_ERRORS',         0x040000);
	define('TF_INV_SOFT',           0x080000);

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

	// `flags` from `zabbix_hosts` table

	define('ZHF_EXIST_IN_ZABBIX',       0x0008);
	define('ZHF_MUST_BE_MONITORED',     0x0010);
	//define('ZHF_NEED_UPDATE_IN_ZABBIX', 0x0020);

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

$g_tasks_flags = array(
	'Заявка закрыта',
	'',
	'',
	'Не установлена квота на ПЯ',
	'Неправильное местоположение в IT Invent',
	'Выяснить причиную повторения заявок IT Invent',
	'Несоответствие baseline установка обновлений',
	'Блокировка ПО TMAC',
	'Не установлен или не работает TMEE',
	'Не установлен или не работает TMAO',
	'Имя не соответствует шаблону',
	'Не установлен или не работает LAPS',
	'Не установлен или не работает агент SCCM',
	'Возможна установка пустого пароля',
	'Устаревшая ОС',
	'Отсутствует в IT Invent',
	'Обнаружена уязвимость'
);

$g_comp_flags = array(
	'Disabled',
	'Deleted',
	'Hide',
	'Temp sync flag',
	'Active Directory',
	'Apex One',
	'Encryption Endpoint',
	'Configuration Manager'
);

$g_comp_short_flags = array(
	'D',
	'R',
	'H',
	'T',
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
	'D'
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

