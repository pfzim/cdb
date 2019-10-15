<?php
	// Create new and close resolved tasks (TMAO)
	
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
	if($db->select_assoc_ex($result, rpv("SELECT * FROM @computers WHERE (`flags` & (0x01 | 0x04 | 0x08)) = 0 AND `name` regexp '^(([[:digit:]]{4}-[nN])|([Pp][Cc]-))[[:digit:]]+$' AND `ao_script_ptn` < (SELECT MAX(`ao_script_ptn`) FROM c_computers) - 200")))
	{
		foreach($result as &$row)
		{
			//$answer = '<?xml version="1.0" encoding="utf-8"? ><root><extAlert><event ref="c7db7df4-e063-11e9-8115-00155d420f11" date="2019-09-26T16:44:46" number="001437825" rule="" person=""/><query ref="" date="" number=""/><comment/></extAlert></root>';

			$answer = @file_get_contents(HELPDESK_URL.'/ExtAlert.aspx/?Source=cdb&Action=new&Type=tmao&Host='.urlencode($row['name']).'&Message='.urlencode("Выявлена проблема с TMAO\r\nПК: ".$row['name']."\r\nВерсия антивирусной базы: ".$row['ao_script_ptn']));
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE)
				{
					//echo $answer."\r\n".$row['name'].' '.$xml->extAlert->query['ref']."\r\n";
					$db->put(rpv("UPDATE @computers SET `ao_operid` = !, `ao_opernum` = !, `flags` = (`flags` | 0x08) WHERE `id` = # LIMIT 1", $xml->extAlert->query['ref'], $xml->extAlert->query['number'], $row['id']));
					$i++;
				}
			}
			break;
		}
	}

	echo 'Created: '.$i."\r\n";

	// Close resolved tasks
	
	$i = 0;
	if($db->select_assoc_ex($result, rpv("SELECT * FROM @computers WHERE (`flags` & 0x08) AND `name` regexp '^(([[:digit:]]{4}-[nN])|([Pp][Cc]-))[[:digit:]]+$' AND (`ao_script_ptn` >= (SELECT MAX(`ao_script_ptn`) FROM c_computers) OR (`flags` & (0x01 | 0x04)))")))
	{
		foreach($result as &$row)
		{
			$answer = @file_get_contents(HELPDESK_URL.'/ExtAlert.aspx/?Source=cdb&Action=resolved&Type=tmao&Id='.urlencode($row['ao_operid']).'&Num='.urlencode($row['ao_opernum']).'&Host='.urlencode($row['name']).'&Message='.urlencode("Заявка более не актуальна"));
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE)
				{
					//echo $answer."\r\n".$row['name'].' '.$xml->extAlert->query['ref']."\r\n";
					$db->put(rpv("UPDATE @computers SET `flags` = (`flags` & ~0x08) WHERE `id` = # LIMIT 1", $row['id']));
					$i++;
				}
			}
			break;
		}
	}

	echo 'Closed: '.$i."\r\n";
