<?php
	// Настройки подключения к БД MySQL

	define('DB_RW_HOST', 'localhost');
	define('DB_USER', 'cluser');
	define('DB_PASSWD', '');
	define('DB_NAME', 'cdb');
	define('DB_CPAGE', 'utf8');
	define('DB_PREFIX', 'c_');    // Префикс таблиц

	// Настройки подключения к Active Directory

	define('LDAP_HOST', '172.18.65.11');
	define('LDAP_PORT', 389);
	define('LDAP_URI', 'ldap://172.18.65.11');
	define('LDAP_USER', 'bristolcapital\\svc_cdb');
	define('LDAP_PASSWD', '');
	define('LDAP_BASE_DN', 'DC=bristolcapital,DC=ru');

	// Группа доступа к Web UI

	//define('LDAP_ADMIN_GROUP_DN', 'CN=G_Snezhinka_Web_Access,OU=Snezhinka,OU=AccessGroups,OU=Service Accounts,DC=bristolcapital,DC=ru');

	define('LDAP_OU_COMPANY', 'OU=Company,DC=bristolcapital,DC=ru');
	define('LDAP_OU_SHOPS', 'OU=Магазины,OU=Company,DC=bristolcapital,DC=ru');
	//define('LDAP_OU_EXCLUDE', 'OU=Thin_clients_HP,OU=Workstations,DC=go,DC=gkm,DC=ru');

	// Настройки подключения к почтовому серверу

	define('MAIL_HOST', 'smtp.bristolcapital.ru');
	define('MAIL_FROM', 'orchestrator@bristolcapital.ru');
	define('MAIL_FROM_NAME', 'Robot');
	define('MAIL_AUTH', true);
	define('MAIL_LOGIN', 'orchestrator@bristolcapital.ru');
	define('MAIL_PASSWD', '');
	define('MAIL_SECURE', '');
	define('MAIL_PORT', 25);

	define('MAIL_TO_ADMIN', 'admin@bristolcapital.ru');
	define('MAIL_TO_NET', 'network@bristolcapital.ru');
	define('MAIL_TO_GUP', 'Aleksander.Prokin@bristol.ru');
	define('MAIL_TO_GOO', 'Olga.Ivanova@bristol.ru');
	define('MAIL_TO_INVENT', 'Marina.Porfireva@bristol.ru');
	define('MAIL_TO_RITM', 'it_managers@bristol.ru');

	define('REPORT_ITINVENT_FILES_TOP_MAIL_TO', array(MAIL_TO_ADMIN, MAIL_TO_INVENT, MAIL_TO_GUP, 'dmitry.egorov@bristol.ru', 'snezhinka.reports@bristolcapital.ru'));
	//define('REPORT_ITINVENT_FILES_TOP_MAIL_TO', array('dvz@bristolcapital.ru'));
	define('REPORT_ITINVENT_MAIL_TO', array(MAIL_TO_ADMIN, MAIL_TO_NET, MAIL_TO_INVENT, MAIL_TO_RITM, MAIL_TO_GOO, 'snezhinka.reports@bristolcapital.ru'));
	define('REPORT_NET_ERRORS_MAIL_TO', array(MAIL_TO_ADMIN, MAIL_TO_NET, MAIL_TO_RITM, MAIL_TO_GUP, 'snezhinka.reports@bristolcapital.ru'));
	define('REPORT_ITINVENT_BCC_MAIL_TO', array(MAIL_TO_ADMIN, MAIL_TO_NET, MAIL_TO_GUP, 'Aleksandr.Panfilov@bristol.ru', 'Ilya.Gorelov@bristol.ru', 'snezhinka.reports@bristolcapital.ru'));
	define('REPORT_VULNS_MAIL_TO', array(MAIL_TO_ADMIN, 'snezhinka.reports@bristolcapital.ru'));
	define('REPORT_CMDB_VM_MAIL_TO', array(MAIL_TO_ADMIN, 'snezhinka.reports@bristolcapital.ru'));
	define('REPORT_CMDB_VM_BACKUPS_MAIL_TO', array(MAIL_TO_ADMIN, 'snezhinka.reports@bristolcapital.ru'));
	define('REPORT_CMDB_VPN_MAIL_TO', array(MAIL_TO_ADMIN, 'snezhinka.reports@bristolcapital.ru'));
	define('REPORT_CMDB_MAXPATROL_MAIL_TO', array(MAIL_TO_ADMIN, 'snezhinka.reports@bristolcapital.ru'));
	define('REPORT_CMDB_MAXPATROL_NET_MAIL_TO', array(MAIL_TO_ADMIN, 'snezhinka.reports@bristolcapital.ru', 'network@bristolcapital.ru'));
	define('REPORT_CMDB_RELATIONS_MAIL_TO', array(MAIL_TO_ADMIN, 'snezhinka.reports@bristolcapital.ru'));

	define('REPORT_WSUS_MAIL_TO', array(MAIL_TO_ADMIN, 'Aleksander.Prokin@bristol.ru', 'snezhinka.reports@bristolcapital.ru'));
	define('REPORT_TMAO_SERVERS_MAIL_TO', array(MAIL_TO_ADMIN, 'dezh.sysadmin@bristolcapital.ru', 'snezhinka.reports@bristolcapital.ru'));
	define('REPORT_MAXPATROL_SMB_MAIL_TO', array(MAIL_TO_ADMIN, 'snezhinka.reports@bristolcapital.ru'));
	define('REPORT_USERS_LASTLOGON_MAIL_TO', array('dvz@bristolcapital.ru', 'snezhinka.reports@bristolcapital.ru'));

	define('MAIL_TO_TASKS_STATUS', array(MAIL_TO_ADMIN, MAIL_TO_GUP, MAIL_TO_GOO, 'dmitry.egorov@bristol.ru', 'snezhinka.reports@bristolcapital.ru'));

	// Настройки подключения к HelpDesk

	define('HELPDESK_URL', 'http://helpdesk.bristol.ru');
	define('HELPDESK_LOGIN', 'orchestrator');
	define('HELPDESK_PASSWD', '');
	define('HELPDESK_COOKIE', 'OperuITAuthCookiehelpdeskbristolru');

	// Настройки подключения к Dataline

	define('DTLN_URL', 'https://dcloud.ru');
	define('DTLN_AUTH', array(
			'admin@Bristol_corp:password',
			'admin@bristol_Site:password'
		)
	);

	// Настройки подключения к vSphere

	define('VSPHERE_URL', 'https://brc-vcenter-01.bristolcapital.ru');
	define('VSPHERE_LOGIN', 'svc_cdb');
	define('VSPHERE_PASSWD', LDAP_PASSWD);

	// Настройки подключения к VK Cloud

	define('VK_AUTH_URL', 'https://infra.mail.ru:35357/v3');
	define('VK_NOVA_URL', 'https://infra.mail.ru:8774/v2.1');
	define('VK_KARBOII_URL', 'https://mcs.mail.ru/infra/karboii/v1');
	define('VK_LOGIN', 'vkcloud_viewer@bristolcapital.ru');
	define('VK_PASSWD', '');

	define('CDB_TITLE', 'Snezhinka');
	define('CDB_URL', 'http://web.bristolcapital.ru/cdb');
	define('WIKI_URL', 'http://wiki.bristolcapital.ru');
	define('ORCHESTRATOR_URL', 'http://brc-sco-01:81/Orchestrator2012/Orchestrator.svc/Jobs');

	// Шаблоны именования компьютеров

	define('CDB_REGEXP_SERVERS', '^(brc|brl|dln|nn|rc\\d+)-(?!UTM)\\w+-\\d+$');
	define('CDB_REGEXP_VALID_NAMES', '^((brc|brl|dln|nn|rc\\d+)-\\w+-\\d+)$|^(\\d{4}-[nNwW]\\d+)$|^(\\d{2}-\\d{4}-[vVmM]{0,1}\\d+)$|^(\\d{2}-[Ww][Hh]\\d{2}-\\d+)$|^(HD-EGAIS-\\d+)$|^(\\d{4}-[Ww]\\d{2}BK)$');
	define('CDB_REGEXP_SHOPS', '^\\d{2}-\\d{4}-[vVmM]{0,1}\\d+$');
	define('CDB_REGEXP_OFFICES', '^\\d{4}-[nNwW]\\d+$|^(\\d{4}-[Ww]\\d{2}BK)$|^rc\\d+-UTM-\\d+$');
	define('CDB_REGEXP_NOTEBOOK_NAME', '^\\d{4}-[nN]\\d+');
	// Offices PC: '^(([[:digit:]]{4}-[nNwW])|(HD-EGAIS-))[[:digit:]]+$'

	// Количество дней насколько может быть просрочен пароль LAPS
	define('LAPS_EXPIRE_DAYS', 30);

	// Настройки подключения к БД Trend Micro Encryption Endpoint

	define('TMEE_DB_HOST', 'brc-tmee-01');
	define('TMEE_DB_NAME', 'MobileArmorDB');
	define('TMEE_DB_USER', 'svc_collector');
	define('TMEE_DB_PASSWD', '');

	// SQL permissions for svc_collector: Server - [Connect SQL], DB - [db_datareader, public]

	// Настройки подключения к БД Trend Micro ApexOne (сервер 1)

	define('TMAO_01_DB_HOST', 'brc-ao-01');
	define('TMAO_01_DB_NAME', 'BRC-AO-01-ApexOne');
	define('TMAO_01_DB_USER', 'svc_collector');
	define('TMAO_01_DB_PASSWD', '');

	// Настройки подключения к БД Trend Micro ApexOne (сервер 2)

	define('TMAO_03_DB_HOST', 'brc-ao-03');
	define('TMAO_03_DB_NAME', 'BRC-AO-03-ApexOne');
	define('TMAO_03_DB_USER', 'svc_collector');
	define('TMAO_03_DB_PASSWD', '');

	// Допустимое отставание БД сигнатур на количество версий
	define('TMAO_PATTERN_VERSION_LAG', 6000);

	// Trend Micro Application Control exclude
	//define('TMAC_EXCLUDE_REGEX', '(?:pluginapi64|sihlib64|siph64|cloudf_p64|imf_s32|httpimparser|siph064|siph|klf_p64|mmpf_p32)\\.dll$');
	//define('TMAC_EXCLUDE_REGEX', '^C:\\\\(?:(?:Program Files(?: \\(x86\\))?)|(?:Windows))\\\\');
	define('TMAC_EXCLUDE_REGEX', '^C:\\\\Program Files(?: \\(x86\\))?\\\\');

	// Настройки подключения к БД SCCM
	define('SCCM_DB_HOST', 'brc-dbs-01');
	define('SCCM_DB_NAME', 'CM_M01');
	define('SCCM_DB_USER', 'svc_collector');
	define('SCCM_DB_PASSWD', '');

	/*
		SELECT
			  TOP 1000
			  v_ConfigurationItems.CI_ID,
			  v_LocalizedCIProperties.DisplayName,
			  v_ConfigurationItems.CIVersion,
			  v_LocalizedCIProperties.LocaleID
		FROM v_ConfigurationItems
		INNER JOIN v_LocalizedCIProperties
		ON v_ConfigurationItems.CI_ID = v_LocalizedCIProperties.CI_ID
		-- AND  (v_LocalizedCIProperties.TopicType = 401)
		WHERE
			  CIType_ID = 3
			  AND v_LocalizedCIProperties.DisplayName = 'CI - Check - PS - InstallHotFix'
	*/

	// CI - Check - PS - InstallHotFix
	define('SCCM_IHF_CI_ID', '16814719');
	define('SCCM_IHF_CI_VERSION', '7');

	// CI - Software - RMS - Installed
	define('SCCM_RMSI_CI_ID', '16821709');
	define('SCCM_RMSI_CI_VERSION', '3');

	// CI - Software - RMS - Settings
	define('SCCM_RMSS_CI_ID', '16821658');
	define('SCCM_RMSS_CI_VERSION', '11');

	// CI - Software - RMS - Version
	//define('SCCM_RMSV_CI_ID', '16820636');
	define('SCCM_RMSV_CI_ID', '16821652');
	define('SCCM_RMSV_CI_VERSION', '4');

	// CI - Check - Regkey: ms-msdt
	define('SCCM_MSDT_CI_ID', '16820757');
	define('SCCM_MSDT_CI_VERSION', '2');

	// CI - Check - Regkey: Edge Version
	define('SCCM_EDGE_CI_ID', '16820756');
	define('SCCM_EDGE_CI_VERSION', '2');

	// Custom property ReportsIgnore
	define('SCCM_PROP_REPORTS_IGNORE_ID', '16777216');

	// Проверка версии операционной системы по данным из SCCM
	define('CHECK_OPERATION_SYSTEM_VERSION_SCCM', '10.0.17763.1432');

	// Настройки подключения к БД IT Invent

	define('ITINVENT_DB_HOST', 'brc-itinv-01');
	define('ITINVENT_DB_NAME', 'ITINVENT');
	define('ITINVENT_DB_USER', 'svc_collector');
	define('ITINVENT_DB_PASSWD', '');

	define('ITINVENT_TYPE_COMPUTER', 1);     // Компьютер
	define('ITINVENT_TYPE_SWITCH',   13);    // Коммутатор (switch)
	define('ITINVENT_TYPE_ROUTER',   63);    // Маршрутизатор

	// Настройки подключения к БД CtulhuMonDB

	//define('CTULHU_DB_HOST', 'brc-report-01.bristolcapital.ru');
	//define('CTULHU_DB_NAME', 'CtulhuMonDB');
	//define('CTULHU_DB_USER', 'snowflake');
	//define('CTULHU_DB_PASSWD', '');

	// Настройки подключения и синхронизации CMDBuild

	define('CMDB_URL', 'http://brc-cmdb-01.bristolcapital.ru/cmdbuild/services/rest/v3');
	define('CMDB_LOGIN', 'snezhinka');
	define('CMDB_PASS', '');

	// Настройки подключения и синхронизации PaloAlto

	define('PALOALTO_URL', 'https://172.18.12.10/restapi/v10.1');
	define('PALOALTO_API_KEY', '');

	// Настройки подключения и синхронизации Zabbix

	define('ZABBIX_URL', 'http://zabbix4.bristolcapital.ru/zabbix');
	//define('ZABBIX_LOGIN', 'snezhinka');
	//define('ZABBIX_PASS', '');
	define('ZABBIX_TOKEN', '');
	define('ZABBIX_HOST_PROXY_1', '10447');
	define('ZABBIX_HOST_PROXY_2', '27534');
	define('ZABBIX_HOST_SNMP_SECNAME', '');
	define('ZABBIX_HOST_SNMP_SECAUTH', '');
	define('ZABBIX_HOST_SNMP_SECPASS', '');

	//define('ZABBIX_Host_Group', '118'); // TODO: remove
	//define('ZABBIX_Host_Groups', array('Default' => '118')); // array_values(ZABBIX_Host_Groups)
	//define('ZABBIX_Host_Template', '10449');
	//define('ZABBIX_Host_Prefix', 'BCC');
	//define('ZABBIX_Template_Array', ['20332','20535','20435']);

	$zabbix_templates = array(
		131 /* Cisco 881 */            => 12923	/* Template Bristol Cisco */
	);

	define('ZABBIX_MAINTENANCE_GROUP_PREFIX',    'MAINTENANCE');     // Префикс групп режима обслуживания
	define('ZABBIX_TT_GROUP_PREFIX',             'TT');     // Префикс группы для маршрутизаторов ТТ
	define('ZABBIX_TOF_GROUP_PREFIX',            'TOF');    // Префикс группы для маршрутизаторов ТОФ
	define('ZABBIX_RC_GROUP_PREFIX',             'RC');     // Префикс группы для маршрутизаторов РЦ
	define('ZABBIX_CO_GROUP_PREFIX',             'CO');     // Префикс группы для маршрутизаторов ЦО

	define('ZABBIX_MAINTENANCE_GROUP_ID',            958);      // В эту группу добавляются хосты к которым требуется применять режимом обслуживания с учётом временной зоны по тэгу reg
	define('ZABBIX_TEMPLATE_FALLBACK',               12923);    // Этот шаблон будет подключен, если типу оборудования не найден соответствующий шаблон /* Template Bristol Cisco */
	define('ZABBIX_TEMPLATE_FOR_BCC',                12924);    // Этот шаблон будет добавлен к основному, если к маршрутизатору подключен резервный комплект /* Template Bristol Cisco addition BCC */
	define('ZABBIX_TEMPLATE_FOR_SLA23',              27909);    // Этот шаблон будет добавлен к основному, если к маршрутизатору подключен резервный комплект /* Template Bristol Cisco SLA23 */
	define('ZABBIX_TEMPLATE_FOR_RC',                 27128);    // Этот шаблон будет добавлен к основному, если к маршрутизатор из РЦ /* Template Bristol Cisco addition RC and CO */
	define('ZABBIX_TEMPLATE_FOR_GO',                 27075);    // Этот шаблон будет добавлен к основному, если к маршрутизатор из ЦО /* Template Bristol Cisco addition GO */
	define('ZABBIX_TEMPLATE_SWITCH',                 21982);    // Шаблон для мониторинга Коммутаторов /* Template Bristol Switch */
	define('ZABBIX_TEMPLATE_WORKSTATION_GENERAL',    28501);    // Шаблон для мониторинга Рабочей станции /* Template Bristol Workstation General*/
	define('ZABBIX_TEMPLATE_WORKSTATION_KASSA',      27237);    // Шаблон для мониторинга Рабочей станции /* Template Bristol Workstation TT Kassa*/
	define('ZABBIX_TEMPLATE_WORKSTATION_ADMIN',      28297);    // Шаблон для мониторинга Рабочей станции /* Template Bristol Workstation TT Admin*/
	define('ZABBIX_USER_ROLE_ID',       '1');      // Роль присваеваемая пользователю /* User role */
	// Группа LDAP - разрешает (включает) доступ с использованием учётных данных Active Directory
	// Группа ALL read дополнительная - предоставляет доступ на чтение ко всем хостам
	define('ZABBIX_USER_GROUP_ID',      '21');     // Группа, в которую добавляется пользователь /* LDAP Users */ удалить в следующем релизе
	define('ZABBIX_USER_GROUP_IDS',     array('21', '14'));     // Группы, в которые добавляется новый пользователь /* LDAP, ALL read */
	define('ZABBIX_ACCESS_AD_GROUP_DN', 'CN=G_Zabbix_Access,OU=Zabbix,OU=AccessGroups,OU=Service Accounts,DC=bristolcapital,DC=ru');     // Группа в AD с пользователями, которым будет предоставлен доступ к Zabbix

	// Настройки исключений по MAC, имени коммутатора, порту
	//define('MAC_NOT_EXCLUDE_REGEX', '');
	//define('MAC_NOT_EXCLUDE_REGEX', '^(?!943fc2|001438|00fd45|1402ec|1c98ec|34fcb9|40b93c|4448c1|48df37|70106f|941882|9cdc71|a8bd27|c8b5ad|d89403|e0071b|e8f724|f40343|0001e6|0001e7|0002a5|004ea|000802|000883|0008c7|000a57|000bcd|000d9d|000e7f|000eb3|000f20|000f61|001083|0010e3|00110a|001185|001279|001321|0014c2|001560|001635|001708|0017a4|001871|0018fe|0019bb|001a4b|001b78|001cc4|001e0b|001f29|00215a|002264|00237d|002481|0025b3|002655|00306e|0030c1|00508b|0060b0|00805f|009c02|080009|082e5f|101f74|10604b|1458d0|18a905|1cc1de|24be05|288023|28924a|2c233a|2c27d7|2c4138|2c44fd|2c59e5|2c768a|308d99|30e171|3464a9|3863bb|38eaa7|3c4a92|3c5282|3ca82a|3cd92b|40a8f0|40b034|441ea1|443192|480fcf|5065f3|5820b1|5c8a38|5cb901|643150|645106|68b599|6c3be5|6cc217|705a0f|7446a0|784859|78acc0|78e3b5|78e7d1|80c16e|843497|8851fb|8cdcd4|9457a5|984be1|98e7f4|9c8e99|9cb654|a01d48|a02bb8|a0481c|a08cfd|a0b3cc|a0d3c1|a45d36|ac162d|b05ada|b499ba|b4b52f|b8af67|bceafa|c4346b|c8cbb8|c8d3ff|cc3e5f|d07e28|d0bf9c|d48564|d4c9ef|d89d67|d8d385|dc4a3e|e4115b|e83935|ec8eb5|ec9a74|ecb1d7|f0921c|f4ce46|fc15b4|fc3fdb|000e08|00eeab|007278|68cae4)');
	//define('MAC_FAKE_ADDRESS', '488f5a15ff14');		// Адрес, который отдают все РКС
	//define('MAC_EXCLUDE_VM', '');
	// define('MAC_EXCLUDE_VM', '^00155d|^9eafd8'); //все MAC для hyperv VM
	//define('MAC_EXCLUDE_VLAN', '^(?:20|22|23|95|102|104|105|107|115|123|124|189|191|667|1272)$'); // moved to DB as mac_exclude_vlan_regex

	// Константы из этого конфига (пока) имеют приоритет перед параметрами из БД (с целью сократить количество обращений к БД)
	//define('MAC_EXCLUDE_ARRAY', NULL); // moved to DB as mac_exclude_json
	/*
	!!! В регулярных выражениях слеш должен быть экранирован.
	Пример: 'foo\\/bar' is matched to: foo/bar

	mac_exclude_json:

	[
		{
			"vlan_regex":   "^(?:20|22|23|95|102|104|105|106|107|115|123|124|189|191|667|1272)$",
			"mac_regex":    null,
			"name_regex":   null,
			"port_regex":   null,
			"cidr_list":    null
		},
		{
			"vlan_regex":   "^(?:65)$",
			"mac_regex":    "^(?:000c29|005056)",
			"name_regex":   null,
			"port_regex":   null,
			"cidr_list":    null
		},
		{
			"vlan_regex":   null,
			"mac_regex":    null,
			"name_regex":   null,
			"port_regex":   null,
			"cidr_list":    ["172.20.203.0/24", "10.50.55.224/27"]
		}
	]

	define('MAC_EXCLUDE_ARRAY', array(array('vlan_regex'  => NULL, 'mac_regex' => NULL,'name_regex' => NULL,'port_regex' => NULL)));
	define('MAC_EXCLUDE_ARRAY', array(
			// Fire alarm exclude
			array(
				'vlan_regex'  => NULL,
				'mac_regex'   => NULL,
				'name_regex'  => '^RU-\\d{2}-\\d{4}-\\w{3}-SW$',
				'port_regex'  => '^(?:(?:GigabitEthernet1/0/1)|(?:fa1)|(?:Ethernet1/0/1))$'
			),
			// CCTV and VoIP exclude
			array(
				'mac_regex'  => MAC_NOT_EXCLUDE_REGEX,
				'name_regex' => '^RU-\\d{2}-\\d{4}-\\w{3}$',
				'port_regex' => '^(?:(?:FastEthernet2)|(?:Vlan106)|(?:fa2))$'
			),
			array(
				'mac_regex'  => MAC_NOT_EXCLUDE_REGEX,
				'name_regex' => '^RU-\\d{2}-B[o\d]\\d-\\w{3}$',
				'port_regex' => '^(?:(?:FastEthernet1)|(?:fa1)|(?:Gi0/1/1)|(?:Gi0/1/2))$'
			),
			// BRC WiFi port exclude
			array(
				'mac_regex'  => NULL,
				'name_regex' => '^BRC-LAN-SWI-02-H5120$',
				'port_regex' => '^GigabitEthernet1/0/45$|^gi1/0/45$'
			),
			// Exclude ANY VM (temporary)
			array(
				'mac_regex'  => MAC_EXCLUDE_VM,
				'name_regex' => NULL,
				'port_regex' => NULL
			),
			array(
				'mac_regex'  => NULL,
				'name_regex' => '^BRC-LAN-RTR-01-C3850$',
				'port_regex' => '^Po101$'
			),
			array(
				'mac_regex'  => NULL,
				'name_regex' => '^BRC-LAN-SWI-08-C2960X$',
				'port_regex' => '^Gi1/0/46$'
			),
			// Оборудование ДЭБ исключить
			array(
				'mac_regex'  => NULL,
				'name_regex' => '^RU-66-RC2-SW3-2530X1$',
				'port_regex' => '^[12]$'
			),
			array(
				'mac_regex'  => NULL,
				'name_regex' => '^RU-66-RC2-SW6-2530X1$',
				'port_regex' => '^[34]$'
			),
			array(
				'mac_regex'  => NULL,
				'name_regex' => '^RU-24-RC4-01$',
				'port_regex' => '^Gi0/1/6$'
			),
			array(
				'mac_regex'  => NULL,
				'name_regex' => '^RU-35-RC5-SW\\d-2960X\\d$',
				'port_regex' => '^Gi\\d/0/\\d{2}$|^Po\\d{1,2}$'
			),
			array(
					'mac_regex'  => NULL,
					'name_regex' => '^RU-35-RC5-0\\d$',
					'port_regex' => '^Gi0/1/[67]$'
			),
			// Исключаем все MAC адреса, которые подключены к этому маршрутизатору
			array(
				'mac_regex'  => NULL,
				'name_regex' => '^RU-77-RC6-01$',
				'port_regex' => NULL
			),
			// MAC адреса, которые исключаем только в ТТ без привязки к порту
			array(
				'mac_regex'  => '^006037',
				'name_regex' => '^RU-\\d{2}-\\d{4}-\\w{3}',
				'port_regex' => NULL
			),
			// Подключения провайдеров в ТТ+ТОФ
			array(
				'mac_regex'  => NULL,
				'name_regex' => '^RU-\\d{2}-\\d{4}-\\w{3}$|^RU-\\d{2}-B[o\d]\\d-\\w{3}$',
				'port_regex' => '^(?:(FastEthernet4)|(fa4))$'
			),
			// Оборудование провайдера
			array(
				'mac_regex'  => NULL,
				'name_regex' => '^BRC-LAN-SWI-01-5130$',
				'port_regex' => '^GigabitEthernet1/0/8$|^gi1/0/8$'
			),
			// Оборудование ДатаЛайн
			array(
				'mac_regex'  => NULL,
				'name_regex' => '^BRC-LAN-RTR-01-C3850$',
				'port_regex' => '^Te[12]/0/24$'
			)
		)
	);
	*/

	//define('IP_MASK_EXCLUDE_LIST', NULL); // moved to DB as mac_exclude_by_ip_list
	/*
	define('IP_MASK_EXCLUDE_LIST', '');
	define('IP_MASK_EXCLUDE_LIST',
		// Сети для серверного оборудования
		  '172.18.64.0/24;'
		. '172.18.65.0/24;'
		. '172.19.65.0/24;'
		. '172.20.65.0/24;'
		. '172.20.203.0/24;'
		. '172.20.200.0/24;'
		. '172.20.235.0/24;'
		. '172.20.232.0/24;'
		. '172.18.50.0/24;'
		. '172.18.51.0/24;'
		. '172.18.52.0/24;'
		. '172.18.53.0/24;'
		. '172.18.54.0/24;'
		. '172.18.55.0/24;'
		. '172.18.56.0/24;'
		. '172.18.57.0/24;'
		. '172.18.58.0/24;'
		. '172.18.59.0/24;'
		. '172.18.67.0/24;'
		. '172.18.97.0/24;'
		. '172.18.124.0/24;'
		. '172.18.127.0/24;'
		. '172.18.166.0/24;'
		. '172.18.66.0/27;'
		. '172.18.80.0/28;'
		. '172.18.96.0/28;'
		// Сети для мониторинга/управления сетевым оборудованием
		. '172.18.11.0/24;'
		. '172.18.12.0/24;'
		. '172.18.13.0/24;'
		// WiFi networks
		. '172.20.8.0/24;'
		. '172.20.2.0/23;'
		. '172.20.136.0/24;'
		. '172.20.130.0/23;'
		. '172.20.162.0/23;'
		. '172.21.0.0/24;'
		. '172.20.194.0/23;'
		. '172.20.226.0/23;'
		. '172.18.189.0/24;'
		. '172.18.129.0/24;'
		// Сети видеонаблюдения и СКУД (оборудование ДЭБ)
		. '172.20.4.0/24;'
		. '10.216.101.0/24;'
		. '10.216.102.0/24;'
		. '172.20.164.0/24;'
		. '172.20.228.0/24;'
		. '172.20.134.0/25;'
		. '172.20.9.0/24;'
		// Сеть для мультимедийного оборудования
		. '10.50.55.224/27'
	);
*/
	// Настройки подключения к 3PAR

	define('TPAR_USER', '3parscom');
	define('TPAR_PASSWD', '');

	// Учётные данные для PowerShell

	define('PWSH_USER', 'bristolcapital\\orchestrator');
	define('PWSH_PASSWD', '');

	// Настройки подключения к MaxPatrol

	define('MAXPATROL_AUTH_URL', 'https://brc-mpvmcore-01.bristolcapital.ru:3334');
	define('MAXPATROL_URL', 'https://brc-mpvmcore-01.bristolcapital.ru');
	define('MAXPATROL_CLIENT_SECRET', '');
	define('MAXPATROL_LOGIN', '');
	define('MAXPATROL_PASSWD', '');

	// Настройки подключения к Nessus

	define('NESSUS_URL', 'https://brc-ness-01.bristolcapital.ru:8834');
	define('NESSUS_ACCESS_KEY', '');
	define('NESSUS_SECRET_KEY', '');
	define('NESSUS_FOLDERS_IDS', array('212', '249', '783'));
	define('NESSUS_WORKSTATIONS_FOLDER_ID', 249);
	define('NESSUS_SERVERS_FOLDER_ID', 212);
	define('NESSUS_NETDEV_FOLDER_ID', 783);

	// Лимиты на создание заявок

	define('TASKS_LIMIT_LAPS',					70);
	define('TASKS_LIMIT_AC',					50);
	define('TASKS_LIMIT_EPWD',					1);
	define('TASKS_LIMIT_EPWD_PERSON',			1);
	define('TASKS_LIMIT_ITINVENT',				120);
	define('TASKS_LIMIT_ITINVENT_ESCALATE',		1);
	define('TASKS_LIMIT_ITINVENT_MOVE',			100);  // 300
	define('TASKS_LIMIT_ITINVENT_SW',			1);
	define('TASKS_LIMIT_ITINVENT_DUP',			20);
	define('TASKS_LIMIT_OS',					20);
	define('TASKS_LIMIT_RENAME',				20);
	define('TASKS_LIMIT_SCCM',					100);
	define('TASKS_LIMIT_TMAO_GUP',				0);  // 300
	define('TASKS_LIMIT_TMAO_GOO',				0);
	define('TASKS_LIMIT_TMAO_DLP_GUP',			1);
	define('TASKS_LIMIT_TMAO_DLP_GOO',			1);
	define('TASKS_LIMIT_VULN',					2);
	define('TASKS_LIMIT_VULN_MASS',				2);
	define('TASKS_LIMIT_WSUS_GUP',				0);
	define('TASKS_LIMIT_WSUS_GOO',				0);
	define('TASKS_LIMIT_EDGE',					5);
	define('TASKS_LIMIT_RMS_I',					10);
	define('TASKS_LIMIT_RMS_S',					10);
	define('TASKS_LIMIT_RMS_V',					10);
	define('TASKS_LIMIT_MBX',					1);
	define('TASKS_LIMIT_NET_ERRORS',			150);
