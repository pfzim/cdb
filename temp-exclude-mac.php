<?php
	// Exclude MACs

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

	function cidr_match($ip, $cidr)
	{
		list($subnet, $mask) = explode('/', $cidr);

		if((ip2long($ip) & ~((1 << (32 - intval($mask))) - 1)) == ip2long($subnet))
		{ 
			return true;
		}

		return false;
	}

	function is_excluded($mac, $last_sw_name, $port, $ip, $vlan)
	{
		//echo $mac.'    '.$last_sw_name.'    '.$port."\n";
		
		if( !($vlan === "NULL") && preg_match('/'.MAC_EXCLUDE_VLAN.'/i', $vlan) ) {
			echo 'MAC excluded: '.$mac.' by VLAN ID: '.$vlan."\n";
			return TRUE;
		}

		foreach(MAC_EXCLUDE_ARRAY as &$excl)
		{
			if(   (($excl['mac_regex'] === NULL) || preg_match('/'.$excl['mac_regex'].'/i', $mac))
			   && (($excl['name_regex'] === NULL) || preg_match('/'.$excl['name_regex'].'/i', $last_sw_name))
			   && (($excl['port_regex'] === NULL) || preg_match('#'.$excl['port_regex'].'#i', $port))
			)
			{
				echo 'MAC excluded: '.$mac.'    '.$last_sw_name.'    '.$port."\n";
				return TRUE;
			}
		}
		
		// Исключение по IP адресу

		if(!empty($ip))
		{
			$masks = explode(';', IP_MASK_EXCLUDE_LIST);
			foreach($masks as &$mask)
			{
				if(cidr_match($ip, $mask))
				{
					echo 'MAC excluded: '.$mac.' by IP: '.$ip.' CIDR: '.$mask."\n";
					return TRUE;
				}
			}
		}

		return FALSE;
	}
	
	$db = new MySQLDB(DB_RW_HOST, NULL, DB_USER, DB_PASSWD, DB_NAME, DB_CPAGE, TRUE);

	header("Content-Type: text/plain; charset=utf-8");

	// Exclude MACs

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			m.`id`,
			m.`mac`,
			m.`ip`,
			d.`name` AS `netdev`,
			m.`port`,
			m.`vlan`
		FROM @mac AS m
		LEFT JOIN @devices AS d
			ON d.`id` = m.`pid` AND d.`type` = 3
		WHERE (m.`flags` & 0x0002) = 0
	")))
	{
		foreach($result as &$row)
		{
			if(is_excluded($row['mac'], $row['netdev'], $row['port'], $row['ip'], $row['vlan']))
			{
				$db->put(rpv("UPDATE @mac SET `flags` = (`flags` | 0x0002) WHERE `id` = # LIMIT 1", $row['id']));
				$i++;
			}
		}
	}

	echo 'Excluded: '.$i."\r\n";

