<?php
	// Retrieve information from Active Directory

	/*
		TODO:
			+ Clear flag 0x0001 if computer account was enabled again
	*/

	/**
		\file
		\brief Синхронизация объектов Computer с Active Directory
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-ad-computers:\n";

	$entries_count = 0;

	$ldap = ldap_connect(LDAP_URI);
	if($ldap)
	{
		ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
		if(ldap_bind($ldap, LDAP_USER, LDAP_PASSWD))
		{
			$cookie = '';
			do
			{
				$sr = ldap_search(
					$ldap,
					LDAP_BASE_DN,
					'(objectCategory=computer)',
					explode(',', 'samaccountname,cn,useraccountcontrol,ms-mcs-admpwdexpirationtime,operatingsystem,operatingsystemversion'),
					0,
					0,
					0,
					LDAP_DEREF_NEVER,
					[['oid' => LDAP_CONTROL_PAGEDRESULTS, 'value' => ['size' => 200, 'cookie' => $cookie]]]
				);

				if($sr === FALSE)
				{
					throw new Exception('ldap_search return error: '.ldap_error($core->LDAP->get_link()));
				}
				
				$matcheddn = NULL;
				$referrals = NULL;
				$errcode = NULL;
				$errmsg = NULL;
				
				if(!ldap_parse_result($ldap, $sr, $errcode , $matcheddn , $errmsg , $referrals, $controls))
				{
					throw new Exception('ldap_parse_result return error code: '.$errcode.', message: '.$errmsg.', ldap_error: '.ldap_error($ldap));
				}

				$entries = ldap_get_entries($ldap, $sr);
				if($entries === FALSE)
				{
					throw new Exception('ldap_get_entries return error: '.ldap_error($ldap));
				}

				$i = $entries['count'];

				while($i > 0)
				{
					$i--;
					$account = &$entries[$i];

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
							// before update remove marks: 0x0001 - Disabled in AD, 0x0002 - Deleted
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
							$db->put(rpv("INSERT INTO @properties_int (`tid`, `pid`, `oid`, `value`) VALUES (1, #, #, #) ON DUPLICATE KEY UPDATE `value` = #",
								$row_id,
								CDB_PROP_USERACCOUNTCONTROL,
								$account['useraccountcontrol'][0],
								$account['useraccountcontrol'][0]
							));
							
							if(!empty($account['operatingsystem'][0]))
							{
								$db->put(rpv("INSERT INTO @properties_str (`tid`, `pid`, `oid`, `value`) VALUES (1, #, #, !) ON DUPLICATE KEY UPDATE `value` = !",
									$row_id,
									CDB_PROP_OPERATINGSYSTEM,
									$account['operatingsystem'][0],
									$account['operatingsystem'][0]
								));
							}

							if(!empty($account['operatingsystemversion'][0]))
							{
								$db->put(rpv("INSERT INTO @properties_str (`tid`, `pid`, `oid`, `value`) VALUES (1, #, #, !) ON DUPLICATE KEY UPDATE `value` = !",
									$row_id,
									CDB_PROP_OPERATINGSYSTEMVERSION,
									$account['operatingsystemversion'][0],
									$account['operatingsystemversion'][0]
								));
							}
						}

						$db->commit();

						$entries_count++;
					}
				}
				
				if(isset($controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie']))
				{
					$cookie = $controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'];
				}
				else
				{
					$cookie = '';
				}
				
				ldap_free_result($sr);

			}
			while(!empty($cookie));

			ldap_unbind($ldap);
		}
	}

	echo 'Count: '.$entries_count."\r\n";
