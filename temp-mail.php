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
	$mail->SMTPDebug = 3;
	$mail->Debugoutput = 'echo';

	$mail->send();
	echo "\n\nErrorInfo: ".$mail->ErrorInfo."\n";
}

php_mailer(array('dvz@bristolcapital.ru'), CDB_TITLE.': TEST', "TEST", 'You client does not support HTML');

