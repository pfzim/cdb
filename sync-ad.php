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

				$sr = ldap_search($ldap, LDAP_BASE_DN, '(objectCategory=computer)', explode(',', 'samaccountname,cn,useraccountcontrol,ms-mcs-admpwdexpirationtime,operatingsystem,operatingsystemversion'));
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

							$db->start_transaction();

							$row_id = 0;
							if(!$db->select_ex($result, rpv("SELECT m.`id` FROM @computers AS m WHERE m.`name` = ! LIMIT 1", $account['cn'][0])))
							{
								if($db->put(rpv("INSERT INTO @computers (`name`, `dn`, `laps_exp`, `flags`) VALUES (!, !, !, #)",
									$account['cn'][0],
									$account['dn'],
									$laps_exp,
									(($account['useraccountcontrol'][0] & 0x02)?CF_AD_DISABLED:0) | CF_EXIST_AD
								)))
								{
									$row_id = $db->last_id();
								}
							}
							else
							{
								// remove marks 0x0001 - Disabled in AD, 0x0002 - Deleted
								$row_id = $result[0][0];
								$db->put(rpv("UPDATE @computers SET `dn` = !, `laps_exp` = !, `flags` = ((`flags` & ~({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_TEMP_MARK})) | #) WHERE `id` = # LIMIT 1",
									$account['dn'],
									$laps_exp,
									(($account['useraccountcontrol'][0] & 0x02)?CF_AD_DISABLED:0) | CF_EXIST_AD,
									$row_id
								));
							}

							if($row_id)
							{
								$db->put(rpv("INSERT INTO @properties_int (`pid`, `oid`, `value`) VALUES (#, #, #) ON DUPLICATE KEY UPDATE `value` = #",
									$row_id,
									CDB_PROP_USERACCOUNTCONTROL,
									$account['useraccountcontrol'][0],
									$account['useraccountcontrol'][0]
								));
								
								if(!empty($account['operatingsystem'][0]))
								{
									$db->put(rpv("INSERT INTO @properties_str (`pid`, `oid`, `value`) VALUES (#, #, !) ON DUPLICATE KEY UPDATE `value` = !",
										$row_id,
										CDB_PROP_OPERATINGSYSTEM,
										$account['operatingsystem'][0],
										$account['operatingsystem'][0]
									));
								}

								if(!empty($account['operatingsystemversion'][0]))
								{
									$db->put(rpv("INSERT INTO @properties_str (`pid`, `oid`, `value`) VALUES (#, #, !) ON DUPLICATE KEY UPDATE `value` = !",
										$row_id,
										CDB_PROP_OPERATINGSYSTEMVERSION,
										$account['operatingsystemversion'][0],
										$account['operatingsystemversion'][0]
									));
								}
							}

							$db->commit();

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
