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

	if(!defined('ROOTDIR'))
	{
		define('ROOTDIR', dirname(__FILE__));
	}

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

function php_mailer($to, $subject, $html, $plain)
{
	require_once 'libs/PHPMailer/PHPMailerAutoload.php';

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

function walk_route($route, $action)
{
	global $db;
	
	if(!isset($route[$action]))
	{
		echo 'Incorrect or no action defined!';
		exit;
	}

	foreach($route[$action] as &$operation)
	{
		if($operation[0] == '@')
		{
			include(ROOTDIR.DIRECTORY_SEPARATOR.substr($operation, 1));
		}
		else
		{
			walk_route($route, $operation);
		}
	}
}

	$db = new MySQLDB(DB_RW_HOST, NULL, DB_USER, DB_PASSWD, DB_NAME, DB_CPAGE, TRUE);

	$action = '';
	if(isset($_GET['action']))
	{
		$action = $_GET['action'];
	}

	$route = array(
		'sync-all' => array(
			'mark-before-sync',
			'sync-ad',
			'sync-tmao',
			'sync-tmee',
			'mark-after-sync'
		),
		'cron-daily' => array(
			'mark-before-sync',
			'sync-ad',
			'sync-tmao',
			'sync-tmee',
			'mark-after-sync',
			'report-tmao-servers',
			'check-tasks-status',
			'create-tasks-tmao',
			'create-tasks-tmee',
			'create-tasks-laps',
			//'create-tasks-rename',
			'report-tasks-status',
			'report-incorrect-names',
			'report-incorrect-names-goo',
			'report-incorrect-names-gup',
			'report-laps'
		),
		'cron-weekly' => array(
			'sync-3par',
			'report-3par',
			'report-vm'
		),
		'create-tasks-tmao' => array(
			'@create-tasks-tmao.php'
		),
		'create-tasks-tmee' => array(
			'@create-tasks-tmee.php'
		),
		'create-tasks-rename' => array(
			'@create-tasks-rename.php'
		),
		'create-tasks-laps' => array(
			'@create-tasks-laps.php'
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
		'report-incorrect-names-gup' => array(
			'@report-incorrect-names-gup.php'
		),
		'report-incorrect-names-goo' => array(
			'@report-incorrect-names-goo.php'
		),
		'report-laps' => array(
			'@report-laps.php'
		),
		'report-tasks-status' => array(
			'@report-tasks-status.php'
		),
		'report-tmao-servers' => array(
			'@report-tmao-servers.php'
		),
		'report-vm' => array(
			'@report-vm.php'
		),
		'sync-3par' => array(
			'@sync-3par.php'
		),
		'sync-ad' => array(
			'@sync-ad.php'
		),
		'sync-tmao' => array(
			'@sync-tmao.php'
		),
		'sync-tmee' => array(
			'@sync-tmee.php'
		)
	);

	header("Content-Type: text/plain; charset=utf-8");

	walk_route($route, $action);
