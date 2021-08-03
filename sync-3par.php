<?php
	// Retrieve information from 3PAR virtual volumes

	/**
		\file
		\brief Синхронизация информации из 3Par
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-3par:\n";

	//$table = '';
	
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

	$html .= '<br /><small>Для перезапуска отчёта:<br /><br />1. <a href="'.CDB_URL.'/cdb.php?action=sync-3par">Выполнить синхронизацию с 3PAR и сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if($i > 0)
	{
		if(php_mailer(array(MAIL_TO_ADMIN), '3PAR VV snapshots used space', $html, 'You client does not support HTML'))
		{
			echo 'Send mail: OK';
		}
		else
		{
			echo 'Send mail: FAILED';
		}
	}
*/