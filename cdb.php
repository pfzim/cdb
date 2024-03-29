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
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.cdb.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.helpdesk.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.messages.php');

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

function get_config(string $name)
{
	global $db;
	global $g_config;
	
	if(!isset($g_config[$name]))
	{
		throw new Exception('Configuration: undefined parameter: '.$name);
		return NULL;
	}

	return $g_config[$name];
}

function get_config_int(string $name)
{
	return intval(get_config($name));
}

	$g_config = array();
	
	$db = new MySQLDB(DB_RW_HOST, NULL, DB_USER, DB_PASSWD, DB_NAME, DB_CPAGE, TRUE);

	// load config parameters from DB
	
	if($db->select_ex($cfg, rpv('
		SELECT
			m.`name`,
			m.`value`
		FROM @config AS m
		WHERE
			m.`uid` = 0
	')))
	{
		foreach($cfg as &$row)
		{
			$g_config[$row[0]] = $row[1];
		}
	}

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
			'sync-ad',
			'sync-tmao',
			'sync-tmee',
			'sync-vsphere',
			'sync-dtln',
			'sync-vk',
			'sync-maxpatrol',          // Must be before sync-cmdb
			'sync-cmdb',
			'sync-paloalto',
			'sync-sccm',
			'mark-after-sync',         // Компьютеры отсутствующие во всех системах посмечаем флагом CF_DELETED
			'sync-itinvent',
			//'sync-itinvent-sw',
			'sync-zabbix'
			//'sync-nessus'
		),
		'cron-daily' => array(
			'sync-all',
			'report-tmao-servers',
			'check-tasks-status',
			//'create-tasks-tmao',
			//'create-tasks-tmao-dlp',
			'create-tasks-tmee',
			'create-tasks-ac',
			'create-tasks-laps',
			//'create-tasks-rms',
			//'create-tasks-rmss',
			//'create-tasks-rmsv',
			'create-tasks-edge',
			'create-tasks-browsers',
			'create-tasks-rename',
			'create-tasks-sccm',
			'create-tasks-epwd',
			'create-tasks-epwd-persons',
			'create-tasks-itinvent',
			'create-tasks-itinvent-move',
			'create-tasks-itinvent-escalate',
			'create-tasks-itinvent-dup',
			//'create-tasks-vuln',
			//'create-tasks-vuln-mass',
			'create-tasks-os',
			'create-tasks-maxpatrol-os-scan',
			//'create-tasks-os-by-sccm',
			'create-tasks-net-errors',
			'create-tasks-wsus',
			'report-tasks-status',
			'report-tasks-neterrors',
			'report-tasks-itinvent',
			'report-new-mac',
			'report-laps',
			'report-wsus',
			//'report-vuln-top-servers',
			//'report-vuln-top',
			//'report-vuln-top-netdev',
			'report-users-lastlogon',
			'report-cmdb-vm',
			'report-cmdb-vm-backup',
			'report-cmdb-vpn',
			'report-cmdb-maxpatrol',
			'report-cmdb-maxpatrol-net',
			'report-cmdb-relations',
			//'report-maxpatrol-smb',  // moved to orchestrator
			'report-itinvent-bcc'
			//,'report-itinvent-files-top'
		),
		'cron-weekly' => array(
			'sync-3par',
			//'sync-sccm-files',
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
		'create-tasks-rms' => array(
			'@create-tasks-rms.php'
		),
		'create-tasks-rmss' => array(
			'@create-tasks-rmss.php'
		),
		'create-tasks-rmsv' => array(
			'@create-tasks-rmsv.php'
		),
		'create-tasks-edge' => array(
			'@create-tasks-edge.php'
		),
		'create-tasks-browsers' => array(
			'@create-tasks-browsers.php'
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
		'create-tasks-os-by-sccm' => array(
			'@create-tasks-os-by-sccm.php'
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
		'create-tasks-itinvent-dup' => array(
			'@create-tasks-itinvent-dup.php'
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
		'create-tasks-maxpatrol-os-scan' => array(
			'@create-tasks-maxpatrol-os-scan.php'
		),
		'create-tasks-vuln' => array(
			'@create-tasks-vuln.php'
		),
		'create-tasks-vuln-mass' => array(
			'@create-tasks-vuln-mass.php'
		),
		'create-tasks-edge' => array(
			'@create-tasks-edge.php'
		),
		'create-tasks-test' => array(
			'@create-tasks-test.php'
		),
		'check-tasks-status' => array(
			'@check-tasks-status.php'
		),
		'mark-after-sync' => array(
			'@mark-after-sync.php'
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
		'report-tasks-neterrors' => array(
			'@report-tasks-neterrors.php'
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
		'report-cmdb-vm' => array(
			'@report-cmdb-vm.php'
		),
		'report-cmdb-vm-backup' => array(
			'@report-cmdb-vm-backup.php'
		),
		'report-cmdb-maxpatrol' => array(
			'@report-cmdb-maxpatrol.php'
		),
		'report-cmdb-maxpatrol-net' => array(
			'@report-cmdb-maxpatrol-net.php'
		),
		'report-maxpatrol-smb' => array(
			'@report-maxpatrol-smb.php'
		),
		'report-cmdb-vpn' => array(
			'@report-cmdb-vpn.php'
		),
		'report-cmdb-relations' => array(
			'@report-cmdb-relations.php'
		),
		'report-zabbix-problems' => array(
			'@report-zabbix-problems.php'
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
		'sync-cmdb' => array(
			'@sync-cmdb.php'
		),
		'sync-vsphere' => array(
			'@sync-vsphere.php'
		),
		'sync-dtln' => array(
			'@sync-dtln.php'
		),
		'sync-vk' => array(
			'@sync-vk.php'
		),
		'sync-maxpatrol' => array(
			'@sync-maxpatrol.php'
		),
		'sync-paloalto' => array(
			'@sync-paloalto.php'
		),
		'import-mac' => array(
			'@import-mac.php'
		),
		'import-sn' => array(
			'@import-sn.php'
		),
		'import-errors' => array(
			'@import-errors.php'
		),
		'mac' => array(
			'@mac.php'
		)
	/*
		,
		'test' => array(
			'@test.php'
		),
		'temp-fix-mac-sn' => array(
			'@temp-fix-mac-sn.php'
		)
	*/
	);

	header("Content-Type: text/plain; charset=utf-8");

	walk_route($route, $action);
