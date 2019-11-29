<?php
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

function php_mailer($to, $name, $subject, $html, $plain)
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
	$mail->addAddress($to, $name);
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

	$db = new MySQLDB(DB_RW_HOST, NULL, DB_USER, DB_PASSWD, DB_NAME, DB_CPAGE, TRUE);

	$action = '';
	if(isset($_GET['action']))
	{
		$action = $_GET['action'];
	}

	$route = array(
		'sync-all' => array(
			'mark-before-sync.php',
			'sync-ad.php',
			'sync-tmao.php',
			'sync-tmee.php',
			'mark-after-sync.php'
		),
		'create-tasks-tmao' => array(
			'create-tasks-tmao.php'
		),
		'create-tasks-tmee' => array(
			'create-tasks-tmee.php'
		),
		'create-tasks-rename' => array(
			'create-tasks-rename.php'
		),
		'create-tasks-laps' => array(
			'create-tasks-laps.php'
		),
		'check-tasks-status' => array(
			'check-tasks-status.php'
		),
		'mark-after-sync' => array(
			'mark-after-sync.php'
		),
		'mark-before-sync' => array(
			'mark-before-sync.php'
		),
		'report-3par' => array(
			'report-3par.php'
		),
		'report-incorrect-names' => array(
			'report-incorrect-names.php'
		),
		'report-laps' => array(
			'report-laps.php'
		),
		'report-tasks-status' => array(
			'report-tasks-status.php'
		),
		'report-tasks-status' => array(
			'report-tasks-status.php'
		),
		'report-tmao-servers' => array(
			'report-tmao-servers.php'
		),
		'report-vm' => array(
			'report-vm.php'
		),
		'sync-3par' => array(
			'sync-3par.php'
		),
		'sync-ad' => array(
			'sync-ad.php'
		),
		'sync-tmao' => array(
			'sync-tmao.php'
		),
		'sync-tmee' => array(
			'sync-tmee.php'
		)
	};

	header("Content-Type: text/plain; charset=utf-8");
	
	if(!isset($route[$action]))
	{
		echo 'Incorrect or no action defined!';
		exit;
	}

	foreach($route[$action] as &$incfile)
	{
		include(ROOTDIR.DIRECTORY_SEPARATOR.$incfile);
	}