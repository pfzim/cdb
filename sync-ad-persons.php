<?php
	// Retrieve information from Active Directory

	/**
		\file
		\brief Синхронизация объектов УЗ Пользователя с Active Directory
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-ad-persons:\n";

	// Binary to SID
	function bin_to_str_sid($binary_sid)
	{

		$sid = NULL;
		/* 64bt PHP */
		if(strlen(decbin(~0)) == 64)
		{
			// Get revision, indentifier, authority 
			$parts = unpack('Crev/x/nidhigh/Nidlow', $binary_sid);
			// Set revision, indentifier, authority 
			$sid = sprintf('S-%u-%d',  $parts['rev'], ($parts['idhigh']<<32) + $parts['idlow']);
			// Translate domain
			$parts = unpack('x8/V*', $binary_sid);
			// Append if parts exists
			if ($parts) $sid .= '-';
			// Join all
			$sid.= join('-', $parts);
		}
		/* 32bit PHP */
		else
		{   
			$sid = 'S-';
			$sidinhex = str_split(bin2hex($binary_sid), 2);
			// Byte 0 = Revision Level
			$sid = $sid.hexdec($sidinhex[0]).'-';
			// Byte 1-7 = 48 Bit Authority
			$sid = $sid.hexdec($sidinhex[6].$sidinhex[5].$sidinhex[4].$sidinhex[3].$sidinhex[2].$sidinhex[1]);
			// Byte 8 count of sub authorities - Get number of sub-authorities
			$subauths = hexdec($sidinhex[7]);
			//Loop through Sub Authorities
			for($i = 0; $i < $subauths; $i++) {
				$start = 8 + (4 * $i);
				// X amount of 32Bit (4 Byte) Sub Authorities
				$sid = $sid.'-'.hexdec($sidinhex[$start+3].$sidinhex[$start+2].$sidinhex[$start+1].$sidinhex[$start]);
			}
		}
		return $sid;
	}

	$i = 0;

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
				ldap_control_paged_result($ldap, 200, true, $cookie);

				$sr = ldap_search($ldap, LDAP_BASE_DN, '(&(objectCategory=person)(objectClass=user))', explode(',', 'samaccountname,cn,useraccountcontrol,givenname,sn,initials,pwdlastset,lastlogontimestamp,objectsid'));
				if($sr)
				{
					$records = ldap_get_entries($ldap, $sr);
					foreach($records as $account)
					{
						if(!empty($account['samaccountname'][0]))
						{
							//echo $account['cn'][0]."\r\n";
							//echo bin_to_str_sid($account['objectsid'][0])."\r\n";
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
									(($account['useraccountcontrol'][0] & 0x02)?PF_AD_DISABLED:0) | PF_EXIST_AD
								)))
								{
									$row_id = $db->last_id();
								}
							}
							else
							{
								// before update remove marks: 0x0001 - Disabled in AD, 0x0002 - Deleted
								$row_id = $result[0][0];
								$db->put(rpv("UPDATE @persons SET `dn` = !, `fname` = !, `mname` = !, `lname` = !, `flags` = ((`flags` & ~({%PF_AD_DISABLED} | {%PF_DELETED} | {%PF_TEMP_MARK})) | #) WHERE `id` = # LIMIT 1",
									$account['dn'],
									@$account['givenname'][0],
									@$account['initials'][0],
									@$account['sn'][0],
									(($account['useraccountcontrol'][0] & 0x02)?PF_AD_DISABLED:0) | PF_EXIST_AD,
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

								$db->put(rpv("INSERT INTO @properties_str (`tid`, `pid`, `oid`, `value`) VALUES (2, #, #, {s2}) ON DUPLICATE KEY UPDATE `value` = {s2}",
									$row_id,
									CDB_PROP_SID,
									bin_to_str_sid($account['objectsid'][0])
								));

								if(!empty($account['lastlogontimestamp'][0]))
								{
									$db->put(rpv("INSERT INTO @properties_date (`tid`, `pid`, `oid`, `value`) VALUES (2, #, #, {s2}) ON DUPLICATE KEY UPDATE `value` = {s2}",
										$row_id,
										CDB_PROP_LASTLOGONTIMESTAMP,
										date("Y-m-d H:i:s", $account['lastlogontimestamp'][0]/10000000-11644473600)
									));
								}

								if(!empty($account['pwdlastset'][0]))
								{
									$db->put(rpv("INSERT INTO @properties_date (`tid`, `pid`, `oid`, `value`) VALUES (2, #, #, {s2}) ON DUPLICATE KEY UPDATE `value` = {s2}",
										$row_id,
										CDB_PROP_PWDLASTSET,
										date("Y-m-d H:i:s", $account['pwdlastset'][0]/10000000-11644473600)
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
