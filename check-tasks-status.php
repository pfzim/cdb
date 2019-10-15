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

	$db = new MySQLDB(DB_RW_HOST, NULL, DB_USER, DB_PASSWD, DB_NAME, DB_CPAGE, TRUE);

	header("Content-Type: text/plain; charset=utf-8");

	$fields = array(
		'retUrl'   		=> '',
		'autoWa'		=> 1,
		'userLogin'		=> HELPDESK_LOGIN,
		'userPassword'	=> HELPDESK_PASSWD,
		'buttonLogOn'	=> '%D0%92%D1%85%D0%BE%D0%B4+%D0%B2+%D1%81%D0%B8%D1%81%D1%82%D0%B5%D0%BC%D1%83'
	);

	$fields_string = http_build_query($fields);

	$ch = curl_init('http://helpdesk.contoso.com/Logon.aspx');

	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
	curl_setopt($ch, CURLOPT_HEADER, true);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 

	$result = curl_exec($ch);
	
	//echo $result;
	
	if(preg_match('/Set-Cookie:\s+(OperuITAuthCookiehelpdeskcontosoru=[^; ]+)/', $result, $matches) !== FALSE)
	{
		$cookie = $matches[1];
		//echo 'Cookie: '.$cookie."\r\n";

		$flags = array(0x02, 0x04);
		$i = 0;

		if($db->select_assoc_ex($result, rpv("SELECT `id`, `name`, `ee_operid` FROM @computers WHERE `flags` & 0x02")))
		{
			foreach($result as &$row)
			{
				$tasks = array($row['ee_operid'], $row['ao_operid'])
				$task = 0;
				foreach($tasks as $task_id)
				{
					$task++
					$ch = curl_init('http://helpdesk.contoso.com/QueryView.aspx?KeyValue='.$task_id.'&xml=1');
					
					curl_setopt($ch, CURLOPT_COOKIE, $cookie);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 

					$answer = curl_exec($ch);
					if($answer !== FALSE)
					{
						//echo $answer;
						$xml = @simplexml_load_string($answer);
						if($xml !== FALSE)
						{
							// 1 Новый
							// 2 В очереди
							// 3 В работе
							// 4 На согласовании
							// 5 Приостановлен
							// 7 Предложено решение
							// 8 Закрыт
							// 9 Отменен
							// 12 Принят на ФГ
							// 14 Согласован
							// 15 Не согласован

							echo $row['name'].' -> '.$xml->docbody->params['stateID']."\r\n";
							if(in_array($xml->docbody->params['stateID'], array(7, 8, 9, 15)))
							{
								echo "    closed\r\n";
								$db->put(rpv("UPDATE @computers SET `flags` = (`flags` & ~#) WHERE `id` = # LIMIT 1", $flags[$task], $row['id']));
								$i++;
							}
						}
					}
				}
				//break;
			}
		}

		echo 'Count: '.$i."\r\n";
	}
