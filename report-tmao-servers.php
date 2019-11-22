<?php
	// Create report TMAO
/*
	SET @current_pattern = (SELECT MAX(ao_script_ptn) FROM c_computers) - 200;
	SELECT @current_pattern;
	SELECT * FROM c_computers WHERE ao_script_ptn < @current_pattern AND ao_script_ptn <> 0 OR (name regexp '^[[:digit:]]{4}-[nN][[:digit:]]+' AND ee_encryptionstatus <> 2);

	SELECT name, ao_script_ptn, ee_encryptionstatus FROM c_computers WHERE ao_script_ptn < (SELECT MAX(ao_script_ptn) FROM c_computers) - 200 AND ao_script_ptn <> 0 OR (name regexp '[[:digit:]]{4}-[nN][[:digit:]]+' AND ee_encryptionstatus <> 2)

	TMAO - Workstations:
	SELECT
		`name`,
		`ao_script_ptn`,
		DATE_FORMAT(`ao_ptnupdtime`, '%d.%m.%Y %H:%i:%s') AS `last_update`,
		DATE_FORMAT(`ao_as_pstime`, '%d.%m.%Y %H:%i:%s') AS `last_scan`,
		`flags`
	FROM c_computers
	WHERE (`flags` & (0x0001 | 0x0004)) = 0
		AND `ao_script_ptn` < (SELECT MAX(`ao_script_ptn`) FROM c_computers) - 200
		AND `name` regexp '^([[:digit:]]{4}-[NnWw][[:digit:]]{4})|([Pp][Cc]-[[:digit:]]{3})$';

	TMME:
	SELECT * FROM c_computers WHERE name regexp '^[[:digit:]]{4}-[nN][[:digit:]]+' AND (ee_encryptionstatus <> 2 OR ee_lastsync < DATE_SUB(NOW(), INTERVAL 2 WEEK));
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
	<h1>Список серверов с устаревшей антивирусной базой</h1>
	<p>Маска для отбора серверов: [brc|dln|nn|rc1]-[имя]-[цифры]<br />В отчёте присутствуют сервера отключенные в AD</p>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Name</th><th>Pattern version</th><th>Last update</th><th>Last full scan</th></tr>';

	$i = 0;

	if($db->select_assoc_ex($result, rpv("SELECT `name`, `ao_script_ptn`, DATE_FORMAT(`ao_ptnupdtime`, '%d.%m.%Y %H:%i:%s') AS `last_update`, DATE_FORMAT(`ao_as_pstime`, '%d.%m.%Y %H:%i:%s') AS `last_scan` FROM @computers WHERE (`flags` & (0x0004 | 0x0020)) = 0 AND `ao_script_ptn` < (SELECT MAX(`ao_script_ptn`) FROM @computers) - 2900 AND `name` regexp '^SQL|AVAYA-MGMT|LYNC-FE|RemoteApp|(brc|dln|nn|rc1)-[[:alnum:]]+-[[:digit:]]+$' ORDER BY `name`")))
	{
		foreach($result as &$row)
		{
			#echo $row['name']."\r\n";

			$td = getdate();
			$dd = &$td['mday'];
			$dm = &$td['mon'];
			$dy = &$td['year'];

			$class1 = '';
			$class2 = '';

			$d = explode('.', $row['last_update'], 3);
			$nd = intval(@$d[0]);
			$nm = intval(@$d[1]);
			$ny = intval(@$d[2]);
			dateadd($nd, $nm, $ny, 7);
			if(!datecheck($nd, $nm, $ny) || (datecmp($nd, $nm, $ny, $dd, $dm, $dy) < 0))
			{
				$class1 = ' class="error"';
			}

			$d = explode('.', $row['last_scan'], 3);
			$nd = intval(@$d[0]);
			$nm = intval(@$d[1]);
			$ny = intval(@$d[2]);
			dateadd($nd, $nm, $ny, 7);
			if(!datecheck($nd, $nm, $ny) || (datecmp($nd, $nm, $ny, $dd, $dm, $dy) < 0))
			{
				$class2 = ' class="error"';
			}

			$table .= '<tr><td>'.$row['name'].'</td><td>'.$row['ao_script_ptn'].'</td><td'.$class1.'>'.$row['last_update'].'</td><td'.$class2.'>'.$row['last_scan'].'</td></tr>';
			$i++;
		}
	}

	echo 'Count: '.$i."\r\n";

	$table .= '</table>';
	$html .= '<p>Всего: '.$i.'</p>';
	$html .= $table;

	$table = '<table>';
	$table .= '<tr><th>Name</th></tr>';

	if($db->select_assoc_ex($result, rpv("SELECT `name` FROM @computers WHERE (`flags` & 0x0004)")))
	{
		foreach($result as &$row)
		{
			$table .= '<tr><td>'.$row['name'].'</td></tr>';
		}
	}

	$table .= '</table>';

	$html .= '<h2>Список исключений</h2>';
	$html .= $table;
	$html .= '<br /><small>Для перезапуска отчёта:<br />1. <a href="'.CDB_URL.'/sync-ad.php">Выполнить синхронизацию с AD</a><br />2. <a href="'.CDB_URL.'/sync-tmao.php">Выполнить синхронизацию с Apex One</a><br />3. <a href="'.CDB_URL.'/sync-tmee.php">Выполнить синхронизацию с Endpoint Encryption</a><br />4. <a href="'.CDB_URL.'/report-tmao-servers.php">Сформировать отчёт</a></small>';
	$html .= '</body>';

	if(php_mailer(MAIL_TO, MAIL_TO, 'Audit antivirus protection', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK';
	}
	else
	{
		echo 'Send mail: FAILED';
	}
