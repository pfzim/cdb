<?php
	// Retrieve information from Active Directory

	/**
		\file
		\brief Синхронизация объектов УЗ Пользователя с Active Directory
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-ad-persons:\n";

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

				$sr = ldap_search($ldap, LDAP_BASE_DN, '(&(objectCategory=person)(objectClass=user))', explode(',', 'samaccountname,cn,useraccountcontrol,givenname,sn,initials'));
				if($sr)
				{
					$records = ldap_get_entries($ldap, $sr);
					foreach($records as $account)
					{
						if(!empty($account['samaccountname'][0]))
						{
							//echo $account['cn'][0]."\r\n";
							//print_r($account); break;

							$db->start_transaction();

							$row_id = 0;
							if(!$db->select_ex($result, rpv("SELECT m.`id` FROM @persons AS m WHERE m.`login` = ! LIMIT 1", $account['samaccountname'][0])))
							{
								if($db->put(rpv("INSERT INTO @persons (`login`, `dn`, `fname`, `mname`, `lname`, `flags`) VALUES (!, !, !, !, !, #)",
									$account['samaccountname'][0],
									$account['dn'],
									@$account['givenname'][0],
									@$account['initials'][0],
									@$account['sn'][0],
									(($account['useraccountcontrol'][0] & 0x02)?0x0001:0) | 0x0010
								)))
								{
									$row_id = $db->last_id();
								}
							}
							else
							{
								// before update remove marks: 0x0001 - Disabled in AD, 0x0002 - Deleted
								$row_id = $result[0][0];
								$db->put(rpv("UPDATE @persons SET `dn` = !, `fname` = !, `mname` = !, `lname` = !, `flags` = ((`flags` & ~(0x0001 | 0x0002 | 0x0008)) | #) WHERE `id` = # LIMIT 1",
									$account['dn'],
									@$account['givenname'][0],
									@$account['initials'][0],
									@$account['sn'][0],
									(($account['useraccountcontrol'][0] & 0x02)?0x0001:0) | 0x0010,
									$row_id
								));
							}

							if($row_id)
							{
								$db->put(rpv("INSERT INTO @properties_int (`tid`, `pid`, `oid`, `value`) VALUES (2, #, #, #) ON DUPLICATE KEY UPDATE `value` = #",
									$row_id,
									CDB_PROP_USERACCOUNTCONTROL,
									$account['useraccountcontrol'][0],
									$account['useraccountcontrol'][0]
								));
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
