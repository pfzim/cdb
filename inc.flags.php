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
	'Exist in IT Invent'
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

