<?php
/**
 *  @file inc.cdb.php
 *  @brief Общие функции используемые Снежинкой
 */

function cidr_list_match($cidr_list, $ip, $path_log)
{
	foreach($cidr_list as &$mask)
	{
		if(cidr_match($ip, $mask))
		{
			//log_file('MAC excluded: '.$mac.' by IP: '.$ip.' CIDR: '.$mask);
			if(!empty($path_log))
			{
				error_log(date('c').'  IP excluded: '.$ip.' by CIDR: '.$mask."\n", 3, $path_log);
			}

			return TRUE;
		}
	}

	return FALSE;
}

function is_excluded($mac, $last_sw_name, $port, $ip, $vlan, $mac_exclude_json, $path_log)
{
	// Исключение по имени коммутатора, MAC адресу, порту и IP адресу
	if($mac_exclude_json !== NULL)
	{
		$rule_index = 0;
		foreach($mac_exclude_json as &$excl)
		{
			$rule_index++;

			if((($excl['mac_regex'] === NULL) || preg_match('/'.$excl['mac_regex'].'/i', $mac))
				&& (($excl['name_regex'] === NULL) || preg_match('/'.$excl['name_regex'].'/i', $last_sw_name))
				&& (($excl['port_regex'] === NULL) || preg_match('/'.$excl['port_regex'].'/i', $port))
				&& (($excl['vlan_regex'] === NULL) || preg_match('/'.$excl['vlan_regex'].'/i', $vlan))
				&& (($excl['cidr_list'] === NULL) || cidr_list_match($excl['cidr_list'], $ip, $path_log))
			)
			{
				//log_file('MAC excluded: '.$mac);
				if(!empty($path_log))
				{
					//error_log(date('c').'  MAC excluded: '.$mac.' by rule '.$rule_index."\n", 3, $path_log);
					error_log(date('c').'  MAC excluded: '.$mac.' by rule '.$rule_index.': MAC: '.$excl['mac_regex'].', name: '.$excl['name_regex'].', port: '.$excl['port_regex'].', VLAN: '.$excl['vlan_regex']."\n", 3, $path_log);
				}

				return TRUE;
			}
		}
	}

	return FALSE;
}
