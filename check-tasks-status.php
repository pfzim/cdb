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

function get_status_name($strings, $code)
{
	if(isset($strings[$code]))
	{
		return $strings[$code];
	}
	
	return $strings[0];
}

	$g_task_status = array(
		'Unknown',
		'Новый',
		'В очереди',
		'В работе',
		'На согласовании',
		'Приостановлен',
		'Предложено решение',
		'Закрыт',
		'Отменен',
		'Принят на ФГ',
		'Согласован',
		'Не согласован'
	);

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

		$i = 0;

		if($db->select_assoc_ex($result, rpv("SELECT `id`, `operid`, `opernum` FROM @tasks WHERE (`flags` & 0x0001) = 0")))
		{
			foreach($result as &$row)
			{
						$ch = curl_init(HELPDESK_URL.'/QueryView.aspx?KeyValue='.$row['operid'].'&xml=1');

						curl_setopt($ch, CURLOPT_COOKIE, $cookie);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

						$answer = curl_exec($ch);
						if($answer !== FALSE)
						{
							//echo $answer;
							$xml = @simplexml_load_string($answer);
							if($xml !== FALSE)
							{
								echo $row['opernum'].' -> '.get_status_name($g_task_status, intval($xml->docbody->params['stateID']))."\r\n";
								if(in_array($xml->docbody->params['stateID'], array(7, 8, 9, 15)))
								{
									echo "   is closed\r\n";
									$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | 0x0001) WHERE `id` = # LIMIT 1", $row['id']));
									$i++;
								}
							}
						}
				//break;
			}
		}

		echo 'Closed: '.$i."\r\n";
	}
