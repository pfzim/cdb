<?php
	// Create new and close resolved tasks (RENAME)

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

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @computers WHERE (`flags` & (0x0001 | 0x0004 | 0x0002 | 0x0400)) = 0x0400")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
		SELECT * 
		FROM @computers
		WHERE
			(`flags` & (0x0001 | 0x0004 | 0x0002 | 0x0400)) = 0 
			AND `dn` LIKE '%".LDAP_OU_SHOPS."'
			AND `name` NOT REGEXP '^[[:digit:]]{2}-[[:digit:]]{4}-[vVmM]{0,1}[[:digit:]]+$'
	")))
	{
		foreach($result as &$row)
		{
			if($i >= 1)
			{
				echo "Limit reached: 1\r\n";
				break;
			}

			$answer = @file_get_contents(HELPDESK_URL.'/ExtAlert.aspx/?Source=cdb&Action=new&Type=rename&To=gup&Host='.urlencode($row['name']).'&Message='.urlencode("Имя ПК не соответствует шаблону.\nПереименуйте ПК: ".$row['name']."\nКод работ: RENAME\n".WIKI_URL));
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("UPDATE @computers SET `rn_operid` = !, `rn_opernum` = !, `flags` = (`flags` | 0x0400) WHERE `id` = # LIMIT 1", $xml->extAlert->query['ref'], $xml->extAlert->query['number'], $row['id']));
					$i++;
				}
			}
		}
	}

	if($db->select_assoc_ex($result, rpv("
		SELECT * 
		FROM @computers
		WHERE
			(`flags` & (0x0001 | 0x0004 | 0x0002 | 0x0400)) = 0 
			AND `dn` LIKE '%".LDAP_OU_COMPANY."'
			AND `dn` NOT LIKE '%".LDAP_OU_SHOPS."'
			AND `name` NOT REGEXP '^(([[:digit:]]{4}-[nNwW])|(HD-EGAIS-))[[:digit:]]+$'
	")))
	{
		foreach($result as &$row)
		{
			if($i >= 1)
			{
				echo "Limit reached: 1\r\n";
				break;
			}

			$answer = @file_get_contents(HELPDESK_URL.'/ExtAlert.aspx/?Source=cdb&Action=new&Type=rename&To=tsa&Host='.urlencode($row['name']).'&Message='.urlencode("Имя ПК не соответствует шаблону.\nПереименуйте ПК: ".$row['name']."\nКод работ: RENAME\n".WIKI_URL));
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("UPDATE @computers SET `rn_operid` = !, `rn_opernum` = !, `flags` = (`flags` | 0x0400) WHERE `id` = # LIMIT 1", $xml->extAlert->query['ref'], $xml->extAlert->query['number'], $row['id']));
					$i++;
				}
			}
		}
	}

	echo 'Created: '.$i."\r\n";
	

	// Close auto resolved tasks if PC was deleted from AD

	$i = 0;
	if($db->select_assoc_ex($result, rpv("SELECT * FROM @computers WHERE (`flags` & 0x0400) AND (`flags` & (0x0001 | 0x0004 | 0x0002))")))
	{
		foreach($result as &$row)
		{
			$answer = @file_get_contents(HELPDESK_URL.'/ExtAlert.aspx/?Source=cdb&Action=resolved&Type=rename&Id='.urlencode($row['rn_operid']).'&Num='.urlencode($row['rn_opernum']).'&Host='.urlencode($row['name']).'&Message='.urlencode("Заявка более не актуальна"));
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE)
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$row['rn_opernum']."\r\n";
					$db->put(rpv("UPDATE @computers SET `flags` = (`flags` & ~0x0400) WHERE `id` = # LIMIT 1", $row['id']));
					$i++;
				}
			}
			//break;
		}
	}

	echo 'Closed: '.$i."\r\n";

