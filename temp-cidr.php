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

function cidr_match($ip, $cidr)
{
    list($subnet, $mask) = explode('/', $cidr);

    if((ip2long($ip) & ~((1 << (32 - intval($mask))) - 1)) == ip2long($subnet))
    { 
        return true;
    }

    return false;
}

	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.config.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.utils.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.db.php');

	$db = new MySQLDB(DB_RW_HOST, NULL, DB_USER, DB_PASSWD, DB_NAME, DB_CPAGE, TRUE);

	header("Content-Type: text/plain; charset=utf-8");

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`mac`, m.`ip`
		FROM c_mac AS m
		WHERE m.`flags` & 0x0002 = 0
	")))
	{
		foreach($result as &$row)
		{
			$ip = $row['ip'];

			$masks = explode(';', IP_MASK_EXCLUDE_LIST);
			foreach($masks as &$mask)
			{
				if(cidr_match($ip, $mask))
				{
					echo 'MAC excluded: '.$row['mac'].' by IP: '.$ip.' CIDR: '.$mask."\n";
					$db->put(rpv("UPDATE @mac SET `flags` = (`flags` | 0x0002) WHERE `id` = # LIMIT 1", $row['id']));
					break;
				}
			}
		}
	}
