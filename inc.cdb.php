<?php
/**
 *  @file inc.cdb.php
 *  @brief Общие функции используемые Снежинкой
 */

function is_excluded($mac, $last_sw_name, $port, $ip, $vlan, $mac_exclude_json, $mac_exclude_by_ip_list, $path_log)
{
	// Исключение по имени коммутатора, MAC адресу и порту
	if($mac_exclude_json !== NULL)
	{
		foreach($mac_exclude_json as &$excl)
		{
			if((($excl['mac_regex'] === NULL) || preg_match('/'.$excl['mac_regex'].'/i', $mac))
				&& (($excl['name_regex'] === NULL) || preg_match('/'.$excl['name_regex'].'/i', $last_sw_name))
				&& (($excl['port_regex'] === NULL) || preg_match('#'.$excl['port_regex'].'#i', $port))
				&& (($excl['vlan_regex'] === NULL) || preg_match('/'.$excl['vlan_regex'].'/i', $vlan))
			)
			{
				//log_file('MAC excluded: '.$mac);
				if(!empty($path_log))
				{
					error_log(date('c').'  MAC excluded: '.$mac.' by rule: MAC: '.$excl['mac_regex'].', name: '.$excl['name_regex'].', port: '.$excl['port_regex'].', VLAN: '.$excl['vlan_regex']."\n", 3, $path_log);
				}

				return TRUE;
			}
		}
	}

	// Исключение по IP адресу
	if(!empty($mac_exclude_by_ip_list))
	{
		if(!empty($ip))
		{
			$masks = explode(';', $mac_exclude_by_ip_list);
			foreach($masks as &$mask)
			{
				if(cidr_match($ip, $mask))
				{
					//log_file('MAC excluded: '.$mac.' by IP: '.$ip.' CIDR: '.$mask);
					if(!empty($path_log))
					{
						error_log(date('c').'  MAC excluded: '.$mac.' by IP: '.$ip.' CIDR: '.$mask."\n", 3, $path_log);
					}

					return TRUE;
				}
			}
		}
	}

	return FALSE;
}
