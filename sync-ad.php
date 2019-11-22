<?php
	// Retrieve information from Active Directory

	/*
		TODO:
			+ Clear flag 0x0001 if computer account was enabled again
	*/

	if(!defined('ROOTDIR'))
	{
		define('ROOTDIR', dirname(__FILE__));
	}

	if(!file_exists(ROOTDIR.DIRECTORY_SEPARATOR.'inc.config.php'))
	{
		header('Location: install.php');
		exit;
	}

	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.config.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.utils.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.db.php');

	error_reporting(E_ALL);
	define('Z_PROTECTED', 'YES');

	$db = new MySQLDB(DB_RW_HOST, NULL, DB_USER, DB_PASSWD, DB_NAME, DB_CPAGE, TRUE);

	header("Content-Type: text/plain; charset=utf-8");

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

				$sr = ldap_search($ldap, LDAP_BASE_DN, '(objectCategory=computer)', explode(',', 'samaccountname,cn,useraccountcontrol'));
				if($sr)
				{
					$records = ldap_get_entries($ldap, $sr);
					foreach($records as $account)
					{
						if(!empty($account['cn'][0]))
						{
							//echo $account['cn'][0]."\r\n";
							//print_r($account); break;
							$db->put(rpv("INSERT INTO @computers (`name`, `dn`, `flags`) VALUES (!, !, #) ON DUPLICATE KEY UPDATE `dn` = !, `flags` = ((`flags` & ~(0x0001 | 0x0008)) | #)", $account['cn'][0], $account['dn'], ($account['useraccountcontrol'][0] & 0x02)?0x0001:0, $account['dn'], ($account['useraccountcontrol'][0] & 0x02)?0x0001:0));
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
