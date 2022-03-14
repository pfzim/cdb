<?php
/*
    CDB
    Copyright (C) 2019 Dmitry V. Zimin

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

/** 
	\mainpage
	Описание модулей в разделе Файлы
*/

/**
	\file
	\brief Главный модуль.
	Здесь определены общие для всех модулей функции.
	Отсюда запускаются все остальные модули.
*/

	if(!defined('ROOTDIR'))
	{
		define('ROOTDIR', dirname(__FILE__));
	}

	define('CDB_VERSION', 11);

	if(!file_exists(ROOTDIR.DIRECTORY_SEPARATOR.'inc.config.php'))
	{
		header('Location: install.php');
		exit;
	}

	error_reporting(E_ALL);
	define('Z_PROTECTED', 'YES');

	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.config.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.utils.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.db.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.flags.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.ctulhumon.php');

/**
	Отправка почтового сообщения

	@return true - if success
*/

function php_mailer($to, $subject, $html, $plain)
{
	//require_once 'libs/PHPMailer/PHPMailerAutoload.php';
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'libs/PHPMailer/class.phpmailer.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'libs/PHPMailer/class.smtp.php');

	$mail = new PHPMailer;

	$mail->isSMTP();
	$mail->Host = MAIL_HOST;
	$mail->SMTPAuth = MAIL_AUTH;
	if(MAIL_AUTH)
	{
		$mail->Username = MAIL_LOGIN;
		$mail->Password = MAIL_PASSWD;
	}

	$mail->SMTPSecure = MAIL_SECURE;
	$mail->Port = MAIL_PORT;
					$mail->SMTPOptions = array(
    					'ssl' => array(
        					'verify_peer' => false,
        					'verify_peer_name' => false,
        					'allow_self_signed' => true
    					)
					);

	$mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
	foreach($to as &$address)
	{
		$mail->addAddress($address, $address);
	}
	//$mail->addReplyTo('helpdesk@example.com', 'Information');

	$mail->isHTML(true);

	$mail->Subject = $subject;
	$mail->Body    = $html;
	$mail->AltBody = $plain;
	//$mail->ContentType = 'text/html; charset=utf-8';
	$mail->CharSet = 'UTF-8';
	//$mail->SMTPDebug = 4;

	return $mail->send();
}

/**
	Функция сравнения двух SQL дат

	@param [in] $date1  Дата в формате 'YYYY-MM-DD HH:MM:SS'
	@param [in] $date2  Дата в формате 'YYYY-MM-DD HH:MM:SS'
	@retval 0 если даты равны
	@retval -1 если дата $date1 меньше $date2
	@retval 1 если дата $date1 больше $date2
*/
function sql_date_cmp($date1, $date2)
{
	$d1 = preg_split('/[-:\\.T\\s]/', $date1, 6);
	$d2 = preg_split('/[-:\\.T\\s]/', $date2, 6);
	
	for($i = 0; $i < 6; $i++)
	{
		$i1 = intval($d1[$i]);
		$i2 = intval($d2[$i]);

		if($i1 < $i2)
		{
			return -1;
		}
		else if($i1 > $i2)
		{
			return 1;
		}
	}
	
	return 0;	
}

/**
 *  @brief Функция проверяет соотствие IP адреса маске CIDR
 *  
 *  @param [in] $ip IP адрес
 *  @param [in] $cidr Маска CIDR. Например: 10.12.54.0/24
 *  @return True if equal
 */
 
function cidr_match($ip, $cidr)
{
    list($subnet, $mask) = explode('/', $cidr);

    if((ip2long($ip) & ~((1 << (32 - intval($mask))) - 1)) == ip2long($subnet))
    { 
        return true;
    }

    return false;
}

function log_file($message)
{
	if(defined('LOG_FILE'))
	{
		error_log(date('c').'  '.$message."\n", 3, LOG_FILE);
	}
}

function cdb_log($message)
{
	error_log(date('c').'  '.$message."\n", 3, '/var/log/cdb/cdb.log');
}

/**
	Функция для выполнения маршрута (запуска модулей)
	\param [in] $route Массив с описанием маршрутов
	\param [in] $action Команда для выполнения
*/
function walk_route($route, $action)
{
	global $db;
	
	if(!isset($route[$action]))
	{
		echo 'Incorrect or no action defined!';
		exit;
	}

	cdb_log('INFO: Start action: '.$action);
	
	foreach($route[$action] as &$operation)
	{
		if($operation[0] == '@')
		{
			$module_name = ROOTDIR.DIRECTORY_SEPARATOR.substr($operation, 1);
			if(file_exists($module_name))
			{
				cdb_log('INFO: Call file: '.$module_name);
				include($module_name);
				cdb_log('INFO: End file: '.$module_name);
			}
			else
			{
				cdb_log('ERROR: File not found: '.$module_name);
				echo 'ERROR: '.$module_name.' - file not found!';
			}
		}
		else
		{
			walk_route($route, $operation);
		}
	}
}

	$db = new MySQLDB(DB_RW_HOST, NULL, DB_USER, DB_PASSWD, DB_NAME, DB_CPAGE, TRUE);

	$action = '';

	if((php_sapi_name() == 'cli') && ($argc > 1) && !empty($argv[1]))
	{
		$action = $argv[1];
	}
	elseif(isset($_GET['action']))
	{
		$action = $_GET['action'];
	}

	$route = array(
		'sync-all' => array(
			'mark-before-sync',
			'sync-ad',
			'sync-tmao',
			'sync-tmee',
			'sync-sccm',
			'sync-itinvent',
			'sync-itinvent-sw',
			'sync-zabbix',
			'sync-nessus',
			'mark-after-sync'
		),
		'cron-daily' => array(
			'sync-all',
			'report-tmao-servers',
			'check-tasks-status',
			'create-tasks-tmao',
			'create-tasks-tmee',
			'create-tasks-ac',
			'create-tasks-laps',
			'create-tasks-rename',
			'create-tasks-sccm',
			'create-tasks-epwd',
			'create-tasks-epwd-persons',
			'create-tasks-itinvent',
			'create-tasks-itinvent-move',
			'create-tasks-itinvent-escalate',
			'create-tasks-vuln',
			'create-tasks-vuln-mass',
			'create-tasks-os',
			//'create-tasks-net-errors',
			'create-tasks-wsus',
			'report-tasks-status',
			'report-tasks-itinvent',
			'report-new-mac',
			'report-laps',
			'report-wsus',
			'report-vuln-top-servers',
			'report-vuln-top',
			'report-vuln-top-netdev',
			'report-users-lastlogon',
			'report-itinvent-files-top',
			'report-itinvent-bcc'
		),
		'cron-weekly' => array(
			'sync-3par',
			'sync-sccm-files',
			'report-3par',
			'report-vm'
		),
		'create-tasks-tmao' => array(
			'@create-tasks-tmao.php'
		),
		'create-tasks-tmee' => array(
			'@create-tasks-tmee.php'
		),
		'create-tasks-ac' => array(
			'@create-tasks-ac.php'
		),
		'create-tasks-rename' => array(
			'@create-tasks-rename.php'
		),
		'create-tasks-laps' => array(
			'@create-tasks-laps.php'
		),
		'create-tasks-sccm' => array(
			'@create-tasks-sccm.php'
		),
		'create-tasks-epwd' => array(
			'@create-tasks-epwd.php'
		),
		'create-tasks-epwd-persons' => array(
			'@create-tasks-epwd-persons.php'
		),
		'create-tasks-os' => array(
			'@create-tasks-os.php'
		),
		'create-tasks-wsus' => array(
			'@create-tasks-wsus.php'
		),
		'create-tasks-mbx-unlim' => array(
			'@create-tasks-mbx-unlim.php'
		),
		'create-tasks-itinvent' => array(
			'@create-tasks-itinvent.php'
		),
		'create-tasks-itinvent-sw' => array(
			'@create-tasks-itinvent-sw.php'
		),
		'create-tasks-itinvent-escalate' => array(
			'@create-tasks-itinvent-escalate.php'
		),
		'create-tasks-itinvent-move' => array(
			'@create-tasks-itinvent-move.php'
		),
		'create-tasks-net-errors' => array(
			'@create-tasks-net-errors.php'
		),
		'create-tasks-vuln' => array(
			'@create-tasks-vuln.php'
		),
		'create-tasks-vuln-mass' => array(
			'@create-tasks-vuln-mass.php'
		),
		'check-tasks-status' => array(
			'@check-tasks-status.php'
		),
		'mark-after-sync' => array(
			'@mark-after-sync.php'
		),
		'mark-before-sync' => array(
			'@mark-before-sync.php'
		),
		'report-3par' => array(
			'@report-3par.php'
		),
		'report-incorrect-names' => array(
			'@report-incorrect-names.php'
		),
		'report-incorrect-names-hd' => array(
			'@report-incorrect-names-hd.php'
		),
		'report-laps' => array(
			'@report-laps.php'
		),
		'report-wsus' => array(
			'@report-wsus.php'
		),
		'report-users-lastlogon' => array(
			'@report-users-lastlogon.php'
		),
		'report-tasks-status' => array(
			'@report-tasks-status.php'
		),
		'report-tmao-servers' => array(
			'@report-tmao-servers.php'
		),
		'report-tasks-itinvent' => array(
			'@report-tasks-itinvent.php'
		),
		'report-itinvent-files-top' => array(
			'@report-itinvent-files-top.php'
		),
		'report-itinvent-files-top-1' => array(
			'@report-itinvent-files-top-1.php'
		),
		'report-itinvent-bcc' => array(
			'@report-itinvent-bcc.php'
		), 
		'report-new-mac' => array(
			'@report-new-mac.php'
		),
		'report-vuln-top' => array(
			'@report-vuln-top.php'
		),
		'report-vuln-top-servers' => array(
			'@report-vuln-top-servers.php'
		),
		'report-vuln-top-netdev' => array(
			'@report-vuln-top-netdev.php'
		),
		'report-vm' => array(
			'@report-vm.php'
		),
		'sync-3par' => array(
			'@sync-3par.php'
		),
		'sync-ad' => array(
			'sync-ad-computers',
			'sync-ad-persons'
		),
		'sync-ad-computers' => array(
			'@sync-ad-computers.php',
		),
		'sync-ad-persons' => array(
			'@sync-ad-persons.php'
		),
		'sync-tmao' => array(
			'@sync-tmao.php'
		),
		'sync-tmee' => array(
			'@sync-tmee.php'
		),
		'sync-sccm' => array(
			'@sync-sccm.php'
		),
		'sync-sccm-files' => array(
			'@sync-sccm-files.php'
		),
		'sync-itinvent' => array(
			'@sync-itinvent.php'
		),
		'sync-itinvent-sw' => array(
			'@sync-itinvent-sw.php'
		),
		'sync-nessus' => array(
			'@sync-nessus.php'
		),
		'sync-zabbix' => array(
			'@sync-zabbix.php'
		),
		'get-computer-info' => array(
			'@get-computer-info.php'
		),
		'get-mac-info' => array(
			'@get-mac-info.php'
		),
		'import-mac' => array(
			'@import-mac.php'
		),
		'import-mac-old' => array(
			'@import-mac-old.php'
		), // old mac, no vlan (backup)
		'import-sn' => array(
			'@import-sn.php'
		),
		'import-errors' => array(
			'@import-errors.php'
		),
		'computer' => array(
			'@computer.php'
		),
		'mac' => array(
			'@mac.php'
		),
		'test' => array(
			'@test.php'
		),
		'temp-fix-mac-sn' => array(
			'@temp-fix-mac-sn.php'
		)
	);

	header("Content-Type: text/plain; charset=utf-8");

	walk_route($route, $action);
