<?php
	// Checking HelpDesk tasks status for TMAO and TMEE

	/**
		\file
		\brief Получение стауса ранее созданных заявок из системы HelpDesk.
		
		Если заявка в ХД имеет статус Предложено решение, Закрыт, Отменен или Не согласован, то в Снежинке она
		помечается закрытой. Тем самым уменьшается счётчик открытых заявок по такой же проблеме. На их место
		выставляются новые заявки.
		
		При закрытии заявок Application Contol делается пометка Problem fixed об исправлении у проблемных событий.
		
		При закрытии заявок Vulnerability делается пометка Problem fixed об исправлении уязвимости у конкретного хоста.
		
		При закрытии заявок Vulnerability (mass) делается пометка Problem fixed об исправлении уязвимости у всех хостов.
		
		При закрытии заявок Net Errors делается пометка Problem fixed об исправлении у всех портов устройства.
		
		Пометки Problem fixed стираются при следующей синхронизации, если проблема обнаруживается вновь.
		
		При закрытии заявок Незарегистрированное ПО в ИТ Инвент делается пометка Deleted у всех файлов обнаруженых на ПК.
		Пометка Deleted у файла будет стёрта, если файл был обнаружен повторно при более свежем сканировании.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncheck-tasks-status:\n";

function get_status_name($strings, $code)
{
	if(isset($strings[$code]))
	{
		return $strings[$code];
	}
	
	return $strings[0];
}

	$g_task_status = array(
		0 =>	'Unknown',
		1 =>	'Новый',
		2 =>	'В очереди',
		3 =>	'В работе',
		4 =>	'На согласовании',
		5 =>	'Приостановлен',
		7 =>	'Предложено решение',
		8 =>	'Закрыт',
		9 =>	'Отменен',
		12 =>	'Принят на ФГ',
		14 =>	'Согласован',
		15 =>	'Не согласован'
	);


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

	if(!preg_match('/Set-Cookie:\s+('.HELPDESK_COOKIE.'=[^; ]+)/', $result, $matches))
	{
		echo 'HelpDesk login failed!\r\n';
		return;
	}
	
	$cookie = $matches[1];
	//echo 'Cookie: '.$cookie."\r\n";

	$i = 0;

	if($db->select_assoc_ex($result, rpv("SELECT `id`, `tid`, `pid`, `operid`, `opernum`, `flags` FROM @tasks WHERE (`flags` & 0x0001) = 0")))
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

						// Application Contol problem mark as Solved
						if(intval($row['flags']) & 0x0080)
						{
							$db->put(rpv("UPDATE @ac_log SET `flags` = (`flags` | 0x0002) WHERE (`flags` & 0x0002) = 0 AND `pid` = #", $row['pid']));
						}

						// Vulnerability mark as Solved
						if(intval($row['flags']) & 0x010000)
						{
							$db->put(rpv("UPDATE @vuln_scans SET `flags` = (`flags` | 0x0002), `scan_date` = NOW() WHERE `id` = # LIMIT 1", $row['pid']));
						}

						// Vulnerability (mass) mark all as Solved
						if(intval($row['flags']) & 0x020000)
						{
							$db->put(rpv("UPDATE @vuln_scans SET `flags` = (`flags` | 0x0002), `scan_date` = NOW() WHERE `plugin_id` = #", $row['pid']));
						}

						// Net errors mark all as Solved
						if(intval($row['flags']) & 0x040000)
						{
							$db->put(rpv("UPDATE @net_errors SET `flags` = (`flags` | 0x0002) WHERE `pid` = #", $row['pid']));
						}

						// IT Invent software mark all as Solved
						if(intval($row['flags']) & 0x0080000)
						{
							$db->put(rpv("UPDATE @files_inventory SET `flags` = (`flags` | 0x0002) WHERE `pid` = #", $row['pid']));
						}
						$i++;
					}
				}
			}

			curl_close($ch);
			//break;
		}
	}

	echo 'Closed: '.$i."\r\n";
