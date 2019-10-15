<?php
	// Create new and close resolved tasks (TMEE)
	
	/*
		1. Сбор информации - закрытие заявок, если статус изменился на ОК
		2. Создание заявок
		3. Проверка статуса заявок. Если заявка закрыта, но статус не ОК, то эскалация.
		
		SET @current_pattern = (SELECT MAX(ao_script_ptn) FROM c_computers) - 200;
		SELECT @current_pattern;
		SELECT * FROM c_computers WHERE ao_script_ptn < @current_pattern AND ao_script_ptn <> 0 OR (name regexp '^[[:digit:]]{4}-[nN][[:digit:]]+' AND ee_encryptionstatus <> 2);

		SELECT name, ao_script_ptn, ee_encryptionstatus FROM c_computers WHERE ao_script_ptn < (SELECT MAX(ao_script_ptn) FROM c_computers) - 200 AND ao_script_ptn <> 0 OR (name regexp '[[:digit:]]{4}-[nN][[:digit:]]+' AND ee_encryptionstatus <> 2)
		
		TMME:
		
		SELECT * 
		  FROM c_computers
		  WHERE name regexp '^[[:digit:]]{4}-[nN][[:digit:]]+'
		    AND (`flags` & (0x01 | 0x02)) = 0
		    AND (ee_encryptionstatus <> 2 OR ee_lastsync < DATE_SUB(NOW(), INTERVAL 2 WEEK));
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

	$db = new MySQLDB(DB_RW_HOST, NULL, DB_USER, DB_PASSWD, DB_NAME, DB_CPAGE, TRUE);

	header("Content-Type: text/plain; charset=utf-8");

	// Open new tasks
	
	$i = 0;
	if($db->select_assoc_ex($result, rpv("SELECT * FROM @computers WHERE `name` regexp '^[[:digit:]]{4}-[nN][[:digit:]]+' AND (`flags` & (0x01 | 0x02 | 0x04)) = 0 AND (`ee_encryptionstatus` <> 2 OR `ee_lastsync` < DATE_SUB(NOW(), INTERVAL 2 WEEK))")))
	{
		foreach($result as &$row)
		{
			//$answer = '<?xml version="1.0" encoding="utf-8"? ><root><extAlert><event ref="c7db7df4-e063-11e9-8115-00155d420f11" date="2019-09-26T16:44:46" number="001437825" rule="" person=""/><query ref="" date="" number=""/><comment/></extAlert></root>';

			$answer = @file_get_contents(HELPDESK_URL.'/ExtAlert.aspx/?Source=cdb&Action=new&Type=tmee&Host='.urlencode($row['name']).'&Message='.urlencode("Выявлена проблема с TMEE\r\nПК: ".$row['name']."\r\nСтатус шифрования: ".$row['ee_encryptionstatus']));
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE)
				{
					//echo $answer."\r\n".$row['name'].' '.$xml->extAlert->query['ref']."\r\n";
					$db->put(rpv("UPDATE @computers SET `ee_operid` = !, `ee_opernum` = !, `flags` = (`flags` | 0x02) WHERE `id` = # LIMIT 1", $xml->extAlert->query['ref'], $xml->extAlert->query['number'], $row['id']));
					$i++;
				}
			}
			break;
		}
	}

	echo 'Created: '.$i."\r\n";

	// Close resolved tasks
	
	$i = 0;
	if($db->select_assoc_ex($result, rpv("SELECT * FROM @computers WHERE (`flags` & 0x02) AND `name` regexp '^[[:digit:]]{4}-[nN][[:digit:]]+' AND (`ee_encryptionstatus` = 2 AND `ee_lastsync` >= DATE_SUB(NOW(), INTERVAL 2 WEEK) OR (`flags` & (0x01 | 0x04)))")))
	{
		foreach($result as &$row)
		{
			$answer = @file_get_contents(HELPDESK_URL.'/ExtAlert.aspx/?Source=cdb&Action=resolved&Type=tmee&Id='.urlencode($row['ee_operid']).'&Num='.urlencode($row['ee_opernum']).'&Host='.urlencode($row['name']).'&Message='.urlencode("Заявка более не актуальна"));
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE)
				{
					//echo $answer."\r\n".$row['name'].' '.$xml->extAlert->query['ref']."\r\n";
					$db->put(rpv("UPDATE @computers SET `flags` = (`flags` & ~0x02) WHERE `id` = # LIMIT 1", $row['id']));
					$i++;
				}
			}
			break;
		}
	}

	echo 'Closed: '.$i."\r\n";
