<?php
	// Checking HelpDesk tasks status for TMAO and TMEE

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

	header("Content-Type: text/plain; charset=utf-8");

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
	<h1>Отчёт по ранее выставленным не закрытым заявкам в HelpDesk</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Name</th><th>AV Pattern version</th><th>Last update</th><th>HD TMAO</th><th>TMEE Status</th><th>TMEE Last sync</th><th>HD TMEE</th></tr>';

	$i = 0;
	if($db->select_assoc_ex($result, rpv("SELECT `id`, `name`, `ao_script_ptn`, DATE_FORMAT(`ao_ptnupdtime`, '%d.%m.%Y %H:%i:%s') AS `last_update`, `ee_encryptionstatus`, DATE_FORMAT(`ee_lastsync`, '%d.%m.%Y %H:%i:%s') AS `last_sync`, `ao_operid`, `ao_opernum`, `ee_operid`, `ee_opernum`, `flags` FROM @computers WHERE `flags` & (0x02 | 0x08)")))
	{
		foreach($result as &$row)
		{
			$table .= '<tr><td>'.$row['name'].'</td><td>'.$row['ao_script_ptn'].'</td><td>'.$row['last_update'].'</td><td>';
			if(intval($row['flags']) & 0x08)
			{
				$table .= '<a href="'.HELPDESK_URL.'/QueryView.aspx?KeyValue='.$row['ao_operid'].'">'.$row['ao_opernum'].'</a>';
			}
			$table .= '</td><td>'.tmee_status(intval($row['ee_encryptionstatus'])).'</td><td>'.$row['last_sync'].'</td><td>';
			if(intval($row['flags']) & 0x02)
			{
				$table .= '<a href="'.HELPDESK_URL.'/QueryView.aspx?KeyValue='.$row['ee_operid'].'">'.$row['ee_opernum'].'</a>';
			}
			$table .= '</td></tr>';
			
			$i++;
		}
	}

	$table .= '</table>';

	$to_open = 0;
	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @computers WHERE (`flags` & (0x01 | 0x04 | 0x20 | 0x08)) = 0 AND `name` regexp '^(([[:digit:]]{4}-[nNwW])|([Pp][Cc]-))[[:digit:]]+$' AND `ao_script_ptn` = 0")))
	{
		$to_open = $result[0][0];
	}

	$html .= '<p>Всего открытых: '.$i.'<br />Предстоит открыть: '.$to_open.'</p>';
	$html .= $table;
	$html .= '<br /><small>Для перезапуска отчёта:<br />1. <a href="'.CDB_URL.'/check-tasks-status.php">Обновить статус заявок из системы HelpDesk</a><br />2. <a href="'.CDB_URL.'/report-tasks-status.php">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	echo 'Opened: '.$i."\r\n";

	if(php_mailer(MAIL_TO, MAIL_TO, 'Audit antivirus opened tasks', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
