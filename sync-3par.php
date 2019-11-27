<?php
	// Retrieve information from 3PAR virtual volumes

	if(!defined('ROOTDIR'))
	{
		define('ROOTDIR', dirname(__FILE__));
	}

	if(!file_exists(ROOTDIR.DIRECTORY_SEPARATOR.'inc.config.php'))
	{
		header('Location: install.php');
		exit;
	}

	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.config.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.utils.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.db.php');

	error_reporting(E_ALL);
	define('Z_PROTECTED', 'YES');

/*
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
*/
	$db = new MySQLDB(DB_RW_HOST, NULL, DB_USER, DB_PASSWD, DB_NAME, DB_CPAGE, TRUE);

	header("Content-Type: text/plain; charset=utf-8");

	$table = '';
	
	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`address`, m.`name`
		FROM @devices AS m
		WHERE m.`type` = 1
	")))
	{
		foreach($result as &$row)
		{
			/*
			$table .= '<h3>'.$row['name'].'</h3>';
			$table .= '<table>';
			$table .= '<tr><th>Name</th><th>Usr_RawRsvd_MB</th></tr>';
			*/

			$conn = ssh2_connect($row['address'], 22);

			if(@ssh2_auth_password($conn, TPAR_USER, TPAR_PASSWD))
			{
				$stream = ssh2_exec($conn, 'showvv -showcols Id,Name,Usr_RawRsvd_MB -p -type base -notree');
				if($stream !== FALSE)
				{
					stream_set_blocking($stream, true);
					stream_get_line($stream, 2048, "\n"); // skip first header line
					while(!feof($stream))
					{
						$line = stream_get_line($stream, 2048, "\n");
						$line = preg_replace('/\s+/', ';', trim($line));
						if($line[0] == '-')
						{
							break;
						}
						$cols = explode(';', $line);
						echo $row['name'].': '.$cols[1].' = '.formatBytes($cols[2]*1048576, 0)."\r\n";
						//$table .= '<tr><td>'.$cols[1].'</td><td>'.$cols[2].'</td></tr>';
						
						$db->put(rpv("INSERT INTO @vv_history (`pid`, `date`, `name`, `usr_rawrsvd_mb`) VALUES (#, NOW(), !, #)", $row['id'], $cols[1], $cols[2]));
						$i++;
					}
					fclose($stream);
				}
			}
			
			ssh2_disconnect($conn);
			//$table .= '</table>';
		}
	}

	echo 'Total disks: '.$i."\r\n";

/*
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
	<h1>Отчёт по используемому месту дисками</h1>
EOT;

	$html .= $table;

	$html .= '<br /><small>Для перезапуска отчёта:<br /><br />1. <a href="'.CDB_URL.'/sync-3par.php">Выполнить синхронизацию с 3PAR и сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if($i > 0)
	{
		if(php_mailer(MAIL_TO, MAIL_TO, '3PAR VV snapshots used space', $html, 'You client does not support HTML'))
		{
			echo 'Send mail: OK';
		}
		else
		{
			echo 'Send mail: FAILED';
		}
	}
*/