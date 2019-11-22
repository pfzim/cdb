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

function task_status($code)
{
	switch($code)
	{
		case 1: return 'Новый';
		case 2: return 'В очереди';
		case 3: return 'В работе';
		case 4: return 'На согласовании';
		case 5: return 'Приостановлен';
		case 7: return 'Предложено решение';
		case 8: return 'Закрыт';
		case 9: return 'Отменен';
		case 12: return 'Принят на ФГ';
		case 14: return 'Согласован';
		case 15: return 'Не согласован';
	}
	return 'Unknown';
}

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

	$ch = curl_init(HELPDESK_URL.'/Logon.aspx');

	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
	curl_setopt($ch, CURLOPT_HEADER, true);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$result = curl_exec($ch);

	//echo $result;

	if(preg_match('/Set-Cookie:\s+('.HELPDESK_COOKIE.'=[^; ]+)/', $result, $matches) !== FALSE)
	{
		$cookie = $matches[1];
		//echo 'Cookie: '.$cookie."\r\n";

		$task_flags = array(0x0100, 0x0200);
		$i = 0;

		if($db->select_assoc_ex($result, rpv("SELECT `id`, `name`, `ao_operid`, `ao_opernum`, `ee_operid`, `ee_opernum`, `flags` FROM @computers WHERE `flags` & (0x0100 | 0x0200)")))
		{
			foreach($result as &$row)
			{
				$tasks = array($row['ee_operid'], $row['ao_operid']);
				$tasksnum = array($row['ee_opernum'], $row['ao_opernum']);
				$task = 0;
				$flags = 0;
				foreach($tasks as $task_id)
				{
					if($task_flags[$task] & intval($row['flags']) && !empty($task_id))
					{
						$ch = curl_init(HELPDESK_URL.'/QueryView.aspx?KeyValue='.$task_id.'&xml=1');

						curl_setopt($ch, CURLOPT_COOKIE, $cookie);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

						$answer = curl_exec($ch);
						if($answer !== FALSE)
						{
							//echo $answer;
							$xml = @simplexml_load_string($answer);
							if($xml !== FALSE)
							{
								echo $row['name'].'    '.$tasksnum[$task].' -> '.task_status(intval($xml->docbody->params['stateID']))."\r\n";
								if(in_array($xml->docbody->params['stateID'], array(7, 8, 9, 15)))
								{
									$flags |= $task_flags[$task];
								}
							}
						}
					}
					$task++;
				}

				if($flags)
				{
					echo "    closed\r\n";
					$db->put(rpv("UPDATE @computers SET `flags` = (`flags` & ~#) WHERE `id` = # LIMIT 1", $flags, $row['id']));
					$i++;
				}
				//break;
			}
		}

		echo 'Closed: '.$i."\r\n";
	}
