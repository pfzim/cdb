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
		    AND (`flags` & (0x0001 | 0x0100)) = 0
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

	$db = new MySQLDB(DB_RW_HOST, NULL, DB_USER, DB_PASSWD, DB_NAME, DB_CPAGE, TRUE);

	header("Content-Type: text/plain; charset=utf-8");

	// Open new tasks

	$i = 0;
	//if($db->select_assoc_ex($result, rpv("SELECT * FROM @computers WHERE `name` regexp '^[[:digit:]]{4}-[nN][[:digit:]]+' AND (`flags` & (0x0001 | 0x0100 | 0x0004 | 0x0002)) = 0 AND (`ee_encryptionstatus` <> 2 OR `ee_lastsync` < DATE_SUB(NOW(), INTERVAL 2 WEEK))")))
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`name`, m.`dn`, m.`ee_encryptionstatus`
		FROM @computers AS m
		LEFT JOIN @tasks AS j1 ON j1.pid = m.id AND (j1.flags & (0x0001 | 0x0100)) = 0x0100
		WHERE
			(m.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
			AND m.`ee_encryptionstatus` <> 2
			AND m.`name` regexp '^[[:digit:]]{4}-[nN][[:digit:]]+'
		GROUP BY m.`id`
		HAVING (BIT_OR(j1.`flags`) & 0x0100) = 0
	")))
	{
		foreach($result as &$row)
		{
			//$answer = '<?xml version="1.0" encoding="utf-8"? ><root><extAlert><event ref="c7db7df4-e063-11e9-8115-00155d420f11" date="2019-09-26T16:44:46" number="001437825" rule="" person=""/><query ref="" date="" number=""/><comment/></extAlert></root>';

			$answer = @file_get_contents(HELPDESK_URL.'/ExtAlert.aspx/?Source=cdb&Action=new&Type=tmee&To=byname&Host='.urlencode($row['name']).'&Message='.urlencode("Выявлена проблема с TMEE\nПК: ".$row['name']."\nСтатус шифрования: ".tmee_status(intval($row['ee_encryptionstatus']))."\nКод работ: FDERE\n".WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Инструкция%20по%20восстановлению%20работы%20агента%20Full%20Disk%20Encryption.ashx'));
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`pid`, `flags`, `date`, `operid`, `opernum`) VALUES (#, 0x0100, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
			//if($i > 9) break;
		}
	}

	echo 'Created: '.$i."\r\n";

	// Close auto resolved tasks

	$i = 0;
	//if($db->select_assoc_ex($result, rpv("SELECT * FROM @computers WHERE (`flags` & 0x0100) AND `name` regexp '^[[:digit:]]{4}-[nN][[:digit:]]+' AND ((`ee_encryptionstatus` = 2 AND `ee_lastsync` >= DATE_SUB(NOW(), INTERVAL 2 WEEK)) OR (`flags` & (0x0001 | 0x0004)))")))
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`operid`, m.`opernum`, j1.`name`
		FROM @tasks AS m
		LEFT JOIN @computers AS j1 ON j1.`id` = m.`pid`
		WHERE
			(m.`flags` & (0x0001 | 0x0100)) = 0x0100
			AND (j1.`flags` & (0x0001 | 0x0002 | 0x0004) OR j1.`ee_encryptionstatus` = 2)
	")))
	{
		foreach($result as &$row)
		{
			$answer = @file_get_contents(HELPDESK_URL.'/ExtAlert.aspx/?Source=cdb&Action=resolved&Type=tmee&Id='.urlencode($row['operid']).'&Num='.urlencode($row['opernum']).'&Host='.urlencode($row['name']).'&Message='.urlencode("Заявка более не актуальна"));
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE)
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$row['opernum']."\r\n";
					$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | 0x0001) WHERE `id` = # LIMIT 1", $row['id']));
					$i++;
				}
			}
		}
	}

	echo 'Closed: '.$i."\r\n";
