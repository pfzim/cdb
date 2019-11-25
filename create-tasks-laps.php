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

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS m WHERE (m.`flags` & (0x0001 | 0x0800)) = 0x0800")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`name`, m.`dn`, m.`laps_exp`
		FROM @computers AS m
		LEFT JOIN @tasks AS j1 ON j1.pid = m.id AND (j1.flags & (0x0001 | 0x0800)) = 0x0800
		WHERE
			(m.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
			AND m.`dn` LIKE '%".LDAP_OU_COMPANY."'
			AND m.`laps_exp` < DATE_SUB(NOW(), INTERVAL 1 MONTH)
		GROUP BY m.`id`
		HAVING (BIT_OR(j1.`flags`) & 0x0800) = 0
	")))
	{
		foreach($result as &$row)
		{
			if($i >= 1)
			{
				echo "Limit reached: 1\r\n";
				break;
			}
			
			if(pcre_match('/'.LDAP_OU_SHOPS.'$/i', $row['dn']))
			{
				$direction = 'gup';
			}
			else
			{
				$direction = 'goo';
			}

			$answer = @file_get_contents(HELPDESK_URL.'/ExtAlert.aspx/?Source=cdb&Action=new&Type=laps&To='.$direction.'&Host='.urlencode($row['name']).'&Message='.urlencode("Не установлен либо не работает LAPS.\nПК: ".$row['name']."\nПоследнее обновление LAPS: ".$row['laps_exp']."\nКод работ: RENAME\n".WIKI_URL));
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`pid`, `flags`, `date`, `operid`, `opernum`) VALUES (#, 0x0800, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
		}
	}

	echo 'Created: '.$i."\r\n";


	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`operid`, m.`opernum`, j1.`name`
		FROM @tasks AS m
		LEFT JOIN @computers AS j1 ON j1.`id` = m.`pid`
		WHERE
			(m.`flags` & (0x0001 | 0x0800)) = 0x0800
			AND (j1.`flags` & (0x0001 | 0x0002 | 0x0004) OR j1.`laps_exp` >= DATE_SUB(NOW(), INTERVAL 1 MONTH))
	"))
	{
		foreach($result as &$row)
		{
			$answer = @file_get_contents(HELPDESK_URL.'/ExtAlert.aspx/?Source=cdb&Action=resolved&Type=rename&Id='.urlencode($row['operid']).'&Num='.urlencode($row['opernum']).'&Host='.urlencode($row['name']).'&Message='.urlencode("Заявка более не актуальна"));
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
			//break;
		}
	}

	echo 'Closed: '.$i."\r\n";

