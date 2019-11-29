<?php
	// Retrieve information from Active Directory

	/*
		TODO:
			+ Clear flag 0x0001 if computer account was enabled again
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-ad:\n";

	$i = 0;
	
	$ldap = ldap_connect(LDAP_HOST, LDAP_PORT);
	if($ldap)
	{
		ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
		if(ldap_bind($ldap, LDAP_USER, LDAP_PASSWD))
		{
			$cookie = '';
			do
			{
				ldap_control_paged_result($ldap, 200, true, $cookie);

				$sr = ldap_search($ldap, LDAP_BASE_DN, '(objectCategory=computer)', explode(',', 'samaccountname,cn,useraccountcontrol,ms-mcs-admpwdexpirationtime'));
				if($sr)
				{
					$records = ldap_get_entries($ldap, $sr);
					foreach($records as $account)
					{
						if(!empty($account['cn'][0]))
						{
							//echo $account['cn'][0]."\r\n";
							//print_r($account); break;

							$laps_exp = '0000-00-00 00:00:00';
							if(!empty($account['ms-mcs-admpwdexpirationtime'][0]))
							{
								$laps_exp = date("Y-m-d H:i:s", $account['ms-mcs-admpwdexpirationtime'][0]/10000000-11644473600);
							}

							$db->put(rpv("INSERT INTO @computers (`name`, `dn`, `laps_exp`, `flags`) VALUES (!, !, !, #) ON DUPLICATE KEY UPDATE `dn` = !, `laps_exp` = !, `flags` = ((`flags` & ~(0x0001 | 0x0008)) | #)", 
								$account['cn'][0],
								$account['dn'],
								$laps_exp,
								($account['useraccountcontrol'][0] & 0x02)?0x0001:0, 
								$account['dn'], 
								$laps_exp,
								($account['useraccountcontrol'][0] & 0x02)?0x0001:0)
							);
							$i++;
						}
					}
					ldap_control_paged_result_response($ldap, $sr, $cookie);
					ldap_free_result($sr);
				}

			}
			while($cookie !== null && $cookie != '');

			ldap_unbind($ldap);
		}
	}

	echo 'Count: '.$i."\r\n";
