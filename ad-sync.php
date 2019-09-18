<?php

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
	
	$ldap = ldap_connect(LDAP_HOST, LDAP_PORT);
	if($ldap)
	{
		ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
		if(ldap_bind($ldap, LDAP_USER, LDAP_PASSWD))
		{
			$upload_dir = dirname(__FILE__).DIRECTORY_SEPARATOR.'photos';

			$data = array();
			$count_updated = 0;
			$count_added = 0;
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
						echo $account['cn'][0]."\r\n";
						//print_r($account);
						$db->put(rpv("INSERT INTO @computers (`name`, `flags`) VALUES (!, #) ON DUPLICATE KEY UPDATE `flags` = `flags` | #", $account['cn'][0], ($account['useraccountcontrol'][0] & 0x02)?0x01:0, ($account['useraccountcontrol'][0] & 0x02)?0x01:0));
					}
					ldap_control_paged_result_response($ldap, $sr, $cookie);
					ldap_free_result($sr);
				}

			}
			while($cookie !== null && $cookie != '');

			ldap_unbind($ldap);
		}
	}

