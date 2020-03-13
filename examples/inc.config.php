<?php
	define('DB_RW_HOST', 'localhost');
	define('DB_USER', 'cluser');
	define('DB_PASSWD', 'Pa$$word');
	define('DB_NAME', 'cdb');
	define('DB_CPAGE', 'utf8');
	define('DB_PREFIX', 'c_');

	define('LDAP_HOST', 'dc.contoso.com');
	define('LDAP_PORT', 389);
	define('LDAP_USER', 'contoso\\user');
	define('LDAP_PASSWD', 'Pa$$word');
	define('LDAP_BASE_DN', 'DC=contoso,DC=com');

	define('MAIL_HOST', 'smtp.contoso.com');
	define('MAIL_FROM', 'robot@contoso.com');
	define('MAIL_FROM_NAME', 'Robot');
	define('MAIL_AUTH', true);
	define('MAIL_LOGIN', 'robot@contoso.com');
	define('MAIL_PASSWD', 'Pa$$word');
	define('MAIL_SECURE', '');
	define('MAIL_PORT', 25);
	define('MAIL_TO_ADMIN', 'admin@contoso.com');

	define('HELPDESK_URL', 'http://helpdesk.contoso.com');
	define('HELPDESK_LOGIN', 'hduser');
	define('HELPDESK_PASSWD', 'Pa$$word');
	define('HELPDESK_COOKIE', 'OperuITAuthCookie');

	define('WIKI_URL', 'http://wiki.contoso.com');

	define('CDB_TITLE', 'CDB');
	define('CDB_URL', 'http://web.contoso.com/cdb');
	define('ORCHESTRATOR_URL', 'http://srv-sco-01:81/Orchestrator2012/Orchestrator.svc/Jobs');
	define('CDB_REGEXP_SERVERS', '^(srv|dt|db)-[[:alnum:]]+-[[:digit:]]+$');
	define('CDB_REGEXP_VALID_NAMES', '^((srv|dt|db)-[[:alnum:]]+-[[:digit:]]+)$|^([[:digit:]]{4}-[nNwW][[:digit:]]+)$|^([[:digit:]]{2}-[[:digit:]]{4}-[vVmM]{0,1}[[:digit:]]+)$');
	define('CDB_REGEXP_SHOPS', '^[[:digit:]]{2}-[[:digit:]]{4}-[vVmM]{0,1}[[:digit:]]+$');

	// SQL permissions for svc_collector: Server - [Connect SQL], DB - [db_datareader, public]
	define('TMEE_DB_HOST', 'srv-tmee-01');
	define('TMEE_DB_NAME', 'MobileArmorDB');
	define('TMEE_DB_USER', 'svc_collector');
	define('TMEE_DB_PASSWD', 'passw0rd');

	define('TMAO_DB_HOST', 'srv-ao-01');
	define('TMAO_DB_NAME', 'SRV-AO-01-ApexOne');
	define('TMAO_DB_USER', 'svc_collector');
	define('TMAO_DB_PASSWD', 'passw0rd');

	define('SCCM_DB_HOST', 'srv-dbs-01');
	define('SCCM_DB_NAME', 'CM_M01');
	define('SCCM_DB_USER', 'svc_collector');
	define('SCCM_DB_PASSWD', 'passw0rd');
