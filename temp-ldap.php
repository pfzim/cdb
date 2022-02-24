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

				$sr = ldap_search($ldap, LDAP_BASE_DN, '(&(objectCategory=person)(objectClass=user))', explode(',', '*'));
				if($sr)
				{
					$records = ldap_get_entries($ldap, $sr);
					foreach($records as $account)
					{
						if(!empty($account['cn'][0]) && (empty($account['operatingsystem'][0]) || empty($account['operatingsystemversion'][0])))
						{
							//echo $account['cn'][0]."\r\n";
							print_r($account);
							$laps_exp = '0000-00-00 00:00:00';
							if(!empty($account['ms-mcs-admpwdexpirationtime'][0]))
							{
								$laps_exp = date("Y-m-d H:i:s", $account['ms-mcs-admpwdexpirationtime'][0]/10000000-11644473600);
							}
							echo ' --- '.$laps_exp."\r\n";
							#break;
							$i++;
							if($i >= 10)
							{
								break;
							}
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
