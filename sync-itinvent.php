<?php
	// Retrieve information from IT Invent database
	/**
		\file
		\brief Синхронизация с БД IT Invent.

		Загрузка из БД ИТ Инвент информации о MAC адресах и серийных номера.

		Добавлена загрузка информации о местоположении оборудования.
		Местоположение состоит из двух значенией: Филиал (BRANCH_NO) и
		Местоположение (LOC_NO). Местоположение может содержать как номер
		кабинете или этажа, так и адреса магазинов. Если поле LOC_NO_BUH
		равно NULL, то Местоположение (LOC_NO) не загружается - это кабинет,
		а загружается только Филиал (BRANCH_NO).

		Активным считается оборудование имеющее статус Работает или
		Выдан пользователю для удаленной работы.

		Оборудование помечается Мобильным если имеет тип Ноутбук. В последующем
		такое оборудование исключается из проверок на местоположение.

		Устройства, которые не появлялись в сети более 60 дней помечаются
		флагом MF_TEMP_EXCLUDED (удалено).
		
		Добавлена загрузка словаря по наменованиям "Тип оборудования" и "Статус"
		для отображения их в веб-интерфейсе и заявках.
		
		Добавлены таблица инвентарных карточек inv и таблица взаимосвязей MAC
		адресов с инвентарными карточками. Одна карточка (инвентарный номер)
		может иметь множество MAC адресов и MAC адрес может иметь множество
		карточек (инвентарных номеров) из-за ошибочных дубликатов в ИТ Инвент.
		
		Если обнаружена смена статуса в ИТ Инвент с активного на не активный,
		то у MAC адреса обновляется дата последнего его обнаружения и
		проставляется флаг MF_TEMP_EXCLUDED, чтобы не выставлялись заявки по
		выведенному из работы оборудованию и не примимались фантомные записи
		из ARP кэша коммутаторов скриптом import-mac.
		
		Добавлено исключение MAC адреса из проверок, если в ИТ Инвент он
		соответствует Тип УЕ = РКС (ID  45) & Местоположение = Склад (ID 1)
		& Статус = ЗИП (ID 16)
		Это связано с тем, что ТСА регулярно тестируют ЗИП оборудование на
		складе.
	*/

	/*
		Зависимости: CI_TYPE <- TYPE_NO <- MODEL_NO

		CI_TYPE - Встроенные типы учётных единиц
		1  - Оборудование
		2  - ПО
		3  - Комплектующие
		4  - Расходники
		5  - Инвентарь
		6  - Работы
		10 - Документы
		13 - Задачи

		TYPE_NO	CI_TYPE	TYPE_NAME
		2       1       Ноутбук
		85      1       Дублирующий канал связи
		63      1       Маршрутизатор
		13      1       Коммутатор

		FIELD_NO	ITEM_NO		FIELD_NAME
		94			1			Усилитель 3G: mac-адрес
		106			1			MAC Адрес 1
		107			1			MAC Адрес 2
		133			1			MAC Адрес ТСД
		149			1			MAC Адрес 3
		163			1			Усилитель 3G: mac-адрес 2
		210			1			MAC Адрес 4
		222			1			Вторая сетевая карта: MAC 1
		223			1			Вторая сетевая карта: MAC 2
		224			1			Вторая сетевая карта: MAC 3
		225			1			Вторая сетевая карта: MAC 4

		MODEL_NO	MODEL_NAME
		130			Cisco 800
		131			Cisco 881
		716			Cisco C1111
		752			Cisco ISR4331/K9
		891			Cisco 4451
		904			Cisco ASR1001-X
		981			Cisco ISR900 Series
		983			Cisco C921
		1034		Cisco C931-4P
		1048		Cisco C921-4P


		List all fields:

		SELECT [FIELD_NO]
			  ,[ITEM_NO]
			  ,[FIELD_NAME]
			  ,[FIELD_TYPE]
			  ,[FIELD_DESCR]
			  ,[SORT_NO]
			  ,[LIST_VALUES]
			  ,[REQUIRED]
			  ,[TAB_NO]
			  ,[SQL_QUERY]
			  ,[USE_ON_TABLE]
		FROM [ITINVENT].[dbo].[FIELDS]

		List all statuses:

		SELECT
			[STATUS_NO]
			,[DESCR]
			,[ADDINFO]
			,[IMAGE_DATA]
		FROM [ITINVENT].[dbo].[STATUS]

		STATUS_NO		DESCR										ADDINFO
		1				Работает									Функционирует на рабочем месте
		2				На Складе OK								Находится на складе в рабочем состоянии
		3				Сломан										Ожидает ремонта или ремонтируется
		5				На Складе Новый								Находится на складе в рабочем состоянии до этого не спользовался.
		7				Списан										Списано по данным бухгалтерии
		12				К Списанию									Подготовлено к списанию
		13				Аренда										Оборудование находится в аренде
		15				Пломба удалена								NULL
		16				ЗИП											NULL
		17				На складе не проверено						NULL
		18				В пути										Статус используется при перемещении учетных единиц между местоположениями
		19				Дубль-экземпляр								запись об УЕ ошибочно внесенная второй раз
		20				Готово к эксплуатации						NULL
		21				Выдан пользователю для удаленной работы		Временный. Действует только в течении периода выдачи оборудования для удаленной саботы в связи с COVID-19

		List all types:

		SELECT [TYPE_NO]
			  ,[CI_TYPE]
			  ,[TYPE_NAME]
			  ,[ADDINFO]
			  ,[IMAGE_DATA]
			  ,[NETWORK]
			  ,[GEODATA]
			  ,[PRINTER]
			  ,[VENDOR_NO]
		  FROM [ITINVENT].[dbo].[CI_TYPES]
		  ORDER BY [TYPE_NO], [CI_TYPE]
		  
		List models:
		  
		SELECT
			[MODEL_NO]
			,[MODEL_NAME]
			,[ADDINFO]
		FROM [ITINVENT].[dbo].[CI_MODELS]
		WHERE [CI_TYPE] = 1 AND [TYPE_NO] = 63
		ORDER BY [MODEL_NO]
  */

	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-itinvent:\n";

	$active_statuses = array(
		1,    // Работает
		21,   // Выдан пользователю для удаленной работы
		22    // Персональное оборудование
	);

	$params = array(
		'Database' =>				ITINVENT_DB_NAME,
		'UID' =>					ITINVENT_DB_USER,
		'PWD' =>					ITINVENT_DB_PASSWD,
		'Encrypt' =>				true,
		'TrustServerCertificate' =>	true,
		'ReturnDatesAsStrings' =>	true
	);

	$conn = sqlsrv_connect(ITINVENT_DB_HOST, $params);
	if($conn === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	// Загрузка названий статусов

	$invent_result = sqlsrv_query($conn, "
		SELECT
			[STATUS_NO]
			,[DESCR]
		INTO #tmptable
		FROM [dbo].[STATUS]
	");

	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	sqlsrv_free_stmt($invent_result);

	$invent_result = sqlsrv_query($conn, 'SELECT * FROM #tmptable');

	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	$i = 0;
	while($row = sqlsrv_fetch_array($invent_result, SQLSRV_FETCH_ASSOC))
	{
		$db->put(rpv("INSERT INTO @names (`type`, `pid`, `id`, `name`) VALUES ({%NT_STATUSES}, 0, {d0}, {s1}) ON DUPLICATE KEY UPDATE `name` = {s1}",
			$row['STATUS_NO'],
			$row['DESCR']
		));

		$i++;
	}

	echo 'Count: '.$i."\r\n";

	if($row === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	sqlsrv_free_stmt($invent_result);

	$invent_result = sqlsrv_query($conn, 'DROP TABLE #tmptable');

	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	// Загрузка названий типа оборудования

	$invent_result = sqlsrv_query($conn, "
		SELECT
			[TYPE_NO]
			,[CI_TYPE]
			,[TYPE_NAME]
		INTO #tmptable
		FROM [dbo].[CI_TYPES]
		WHERE [CI_TYPE] = 1
	");

	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	sqlsrv_free_stmt($invent_result);

	$invent_result = sqlsrv_query($conn, 'SELECT * FROM #tmptable');

	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	$i = 0;
	while($row = sqlsrv_fetch_array($invent_result, SQLSRV_FETCH_ASSOC))
	{
		$db->put(rpv("INSERT INTO @names (`type`, `pid`, `id`, `name`) VALUES ({%NT_CI_TYPES}, {d0}, {d1}, {s2}) ON DUPLICATE KEY UPDATE `name` = {s2}",
			$row['CI_TYPE'],
			$row['TYPE_NO'],
			$row['TYPE_NAME']
		));

		$i++;
	}

	echo 'Count: '.$i."\r\n";

	if($row === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	sqlsrv_free_stmt($invent_result);

	$invent_result = sqlsrv_query($conn, 'DROP TABLE #tmptable');

	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	sqlsrv_free_stmt($invent_result);

	// Загрузка названий моделей оборудования

	$invent_result = sqlsrv_query($conn, "
		SELECT
			[MODEL_NO]
			,[CI_TYPE]
			,[MODEL_NAME]
		INTO #tmptable
		FROM [dbo].[CI_MODELS]
		WHERE [CI_TYPE] = 1
	");

	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	sqlsrv_free_stmt($invent_result);

	$invent_result = sqlsrv_query($conn, 'SELECT * FROM #tmptable');

	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	$i = 0;
	while($row = sqlsrv_fetch_array($invent_result, SQLSRV_FETCH_ASSOC))
	{
		$db->put(rpv("INSERT INTO @names (`type`, `pid`, `id`, `name`) VALUES ({%NT_CI_MODELS}, {d0}, {d1}, {s2}) ON DUPLICATE KEY UPDATE `name` = {s2}",
			$row['CI_TYPE'],
			$row['MODEL_NO'],
			$row['MODEL_NAME']
		));

		$i++;
	}

	echo 'Count: '.$i."\r\n";

	if($row === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	sqlsrv_free_stmt($invent_result);

	$invent_result = sqlsrv_query($conn, 'DROP TABLE #tmptable');

	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	sqlsrv_free_stmt($invent_result);

	// Загрузка названий филиалов

	$invent_result = sqlsrv_query($conn, "
		SELECT
			[BRANCH_NO]
			,[BRANCH_NAME]
		INTO #tmptable
		FROM [dbo].[BRANCHES]
	");

	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	sqlsrv_free_stmt($invent_result);

	$invent_result = sqlsrv_query($conn, 'SELECT * FROM #tmptable');

	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	$i = 0;
	while($row = sqlsrv_fetch_array($invent_result, SQLSRV_FETCH_ASSOC))
	{
		$db->put(rpv("INSERT INTO @names (`type`, `pid`, `id`, `name`) VALUES ({%NT_BRANCHES}, 0, {d0}, {s1}) ON DUPLICATE KEY UPDATE `name` = {s1}",
			$row['BRANCH_NO'],
			$row['BRANCH_NAME']
		));

		$i++;
	}

	echo 'Count: '.$i."\r\n";

	if($row === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	sqlsrv_free_stmt($invent_result);

	$invent_result = sqlsrv_query($conn, 'DROP TABLE #tmptable');

	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	sqlsrv_free_stmt($invent_result);

	// Загрузка названий местоположений

	$invent_result = sqlsrv_query($conn, "
		SELECT
			[LOC_NO]
			,[DESCR]
		INTO #tmptable
		FROM [dbo].[LOCATIONS]
	");

	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	sqlsrv_free_stmt($invent_result);

	$invent_result = sqlsrv_query($conn, 'SELECT * FROM #tmptable');

	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	$i = 0;
	while($row = sqlsrv_fetch_array($invent_result, SQLSRV_FETCH_ASSOC))
	{
		$db->put(rpv("INSERT INTO @names (`type`, `pid`, `id`, `name`) VALUES ({%NT_LOCATIONS}, 0, {d0}, {s1}) ON DUPLICATE KEY UPDATE `name` = {s1}",
			$row['LOC_NO'],
			$row['DESCR']
		));

		$i++;
	}

	echo 'Count: '.$i."\r\n";

	if($row === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	sqlsrv_free_stmt($invent_result);

	$invent_result = sqlsrv_query($conn, 'DROP TABLE #tmptable');

	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	sqlsrv_free_stmt($invent_result);

	// Загрузка оборудования

	// Before sync remove marks: 0x0010 - Exist in IT Invent, 0x0040 - Active, 0x0100 - Mobile, 0x0200 - Duplicate, 0x0400 - BCC, set status = 0
	$db->put(rpv("UPDATE @mac SET `flags` = (`flags` & ~({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE} | {%MF_INV_MOBILEDEV} | {%MF_DUPLICATE} | {%MF_INV_BCCDEV})), `status` = 0 WHERE `flags` & ({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE} | {%MF_INV_MOBILEDEV} | {%MF_DUPLICATE} | {%MF_INV_BCCDEV})"));
	$db->put(rpv("UPDATE @inv SET `flags` = (`flags` & ~({%IF_EXIST_IN_ITINV} | {%IF_INV_MOBILEDEV} | {%IF_DUPLICATE} | {%IF_INV_BCCDEV})), `status` = 0 WHERE `flags` & ({%IF_EXIST_IN_ITINV} | {%IF_INV_MOBILEDEV} | {%IF_DUPLICATE} | {%IF_INV_BCCDEV})"));
	//$db->put(rpv("UPDATE @inv SET `flags` = (`flags` & ~({%IF_EXIST_IN_ITINV} | {%IF_INV_ACTIVE} | {%IF_INV_MOBILEDEV} | {%IF_DUPLICATE} | {%IF_INV_BCCDEV})), `status` = 0 WHERE `flags` & ({%IF_EXIST_IN_ITINV} | {%IF_INV_ACTIVE} | {%IF_INV_MOBILEDEV} | {%IF_DUPLICATE} | {%IF_INV_BCCDEV})"));

	// Temporarily exclude MAC addresses from checks that not seen in network more than 60 days
	$db->put(rpv("UPDATE @mac SET `flags` = (`flags` | {%MF_TEMP_EXCLUDED}) WHERE `flags` & {%MF_FROM_NETDEV} AND `date` < DATE_SUB(NOW(), INTERVAL 60 DAY)"));

	// Очистка таблицы связей MAC <-> INV_NO
	$db->put(rpv("TRUNCATE TABLE @mac_inv"));

	$invent_result = sqlsrv_query($conn, "
		SELECT
			[ID]
			,CAST(CAST([INV_NO] AS DECIMAL(20)) AS VARCHAR(20)) AS [INV_NO]
			,[CI_TYPE]
			,[TYPE_NO]
			,[MODEL_NO]
			,item.[BRANCH_NO]
			,[LOC_NO] =
				CASE
					WHEN loc.[LOC_NO_BUH] IS NULL THEN 0
					ELSE item.[LOC_NO]
				END
			-- ,brn.[BRANCH_NAME]
			-- ,loc.[DESCR]
			,[STATUS_NO]
			,[SERIAL_NO]
			,[MAC_ADDRESS] AS mac0  -- Ignored
			,m1.[FIELD_VALUE] AS mac1
			,m2.[FIELD_VALUE] AS mac2
			,m3.[FIELD_VALUE] AS mac3
			,m4.[FIELD_VALUE] AS mac4
			,m5.[FIELD_VALUE] AS mac5
			,m6.[FIELD_VALUE] AS mac6
			,m7.[FIELD_VALUE] AS mac7
			,m8.[FIELD_VALUE] AS mac8
			,m9.[FIELD_VALUE] AS mac9
			,m10.[FIELD_VALUE] AS mac10
			,m11.[FIELD_VALUE] AS mac11
			,m12.[FIELD_VALUE] AS mac12
			,m13.[FIELD_VALUE] AS mac13
			,m14.[FIELD_VALUE] AS mac14
			,m15.[FIELD_VALUE] AS mac15
			,m16.[FIELD_VALUE] AS mac16
			,m17.[FIELD_VALUE] AS mac17
		INTO #tmptable
		FROM [ITEMS] AS item WITH (NOLOCK)
		LEFT JOIN [FIELDS_VALUES] AS m1 WITH (NOLOCK) ON m1.[ITEM_ID] = item.[ID] AND m1.[FIELD_NO] = 106 AND m1.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m2 WITH (NOLOCK) ON m2.[ITEM_ID] = item.[ID] AND m2.[FIELD_NO] = 107 AND m2.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m3 WITH (NOLOCK) ON m3.[ITEM_ID] = item.[ID] AND m3.[FIELD_NO] = 133 AND m3.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m4 WITH (NOLOCK) ON m4.[ITEM_ID] = item.[ID] AND m4.[FIELD_NO] = 149 AND m4.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m5 WITH (NOLOCK) ON m5.[ITEM_ID] = item.[ID] AND m5.[FIELD_NO] = 150 AND m5.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m6 WITH (NOLOCK) ON m6.[ITEM_ID] = item.[ID] AND m6.[FIELD_NO] = 94 AND m6.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m7 WITH (NOLOCK) ON m7.[ITEM_ID] = item.[ID] AND m7.[FIELD_NO] = 163 AND m7.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m8 WITH (NOLOCK) ON m8.[ITEM_ID] = item.[ID] AND m8.[FIELD_NO] = 210 AND m8.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m9 WITH (NOLOCK) ON m9.[ITEM_ID] = item.[ID] AND m9.[FIELD_NO] = 222 AND m9.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m10 WITH (NOLOCK) ON m10.[ITEM_ID] = item.[ID] AND m10.[FIELD_NO] = 223 AND m10.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m11 WITH (NOLOCK) ON m11.[ITEM_ID] = item.[ID] AND m11.[FIELD_NO] = 224 AND m11.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m12 WITH (NOLOCK) ON m12.[ITEM_ID] = item.[ID] AND m12.[FIELD_NO] = 225 AND m12.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m13 WITH (NOLOCK) ON m13.[ITEM_ID] = item.[ID] AND m13.[FIELD_NO] = 226 AND m13.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m14 WITH (NOLOCK) ON m14.[ITEM_ID] = item.[ID] AND m14.[FIELD_NO] = 227 AND m14.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m15 WITH (NOLOCK) ON m15.[ITEM_ID] = item.[ID] AND m15.[FIELD_NO] = 228 AND m15.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m16 WITH (NOLOCK) ON m16.[ITEM_ID] = item.[ID] AND m16.[FIELD_NO] = 229 AND m16.[ITEM_NO] = 1
		LEFT JOIN [FIELDS_VALUES] AS m17 WITH (NOLOCK) ON m17.[ITEM_ID] = item.[ID] AND m17.[FIELD_NO] = 233 AND m17.[ITEM_NO] = 1
		-- LEFT JOIN [BRANCHES] AS brn WITH (NOLOCK) ON brn.[BRANCH_NO] = item.[BRANCH_NO]
		LEFT JOIN [LOCATIONS] AS loc WITH (NOLOCK) ON loc.[LOC_NO] = item.[LOC_NO]
		WHERE
			[CI_TYPE] = 1
			AND (
				[SERIAL_NO] IS NOT NULL
				OR m1.[FIELD_VALUE] IS NOT NULL
				OR m2.[FIELD_VALUE] IS NOT NULL
				OR m3.[FIELD_VALUE] IS NOT NULL
				OR m4.[FIELD_VALUE] IS NOT NULL
				OR m5.[FIELD_VALUE] IS NOT NULL
				OR m6.[FIELD_VALUE] IS NOT NULL
				OR m7.[FIELD_VALUE] IS NOT NULL
				OR m8.[FIELD_VALUE] IS NOT NULL
				OR m9.[FIELD_VALUE] IS NOT NULL
				OR m10.[FIELD_VALUE] IS NOT NULL
				OR m11.[FIELD_VALUE] IS NOT NULL
				OR m12.[FIELD_VALUE] IS NOT NULL
				OR m13.[FIELD_VALUE] IS NOT NULL
				OR m14.[FIELD_VALUE] IS NOT NULL
				OR m15.[FIELD_VALUE] IS NOT NULL
				OR m16.[FIELD_VALUE] IS NOT NULL
				OR m17.[FIELD_VALUE] IS NOT NULL
			)
	");

	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	sqlsrv_free_stmt($invent_result);

	$invent_result = sqlsrv_query($conn, 'SELECT * FROM #tmptable');
	
	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	$i = 0;
	
	while($row = sqlsrv_fetch_array($invent_result, SQLSRV_FETCH_ASSOC))
	{
		//if(in_array(intval($row['STATUS_NO']), $active_statuses)) // Временно отключил проверку статусов
		{
			//$active = 0x0040; //(in_array(intval($row['STATUS_NO']), $active_statuses) ? 0x0040 : 0x0000);
			$active = (in_array(intval($row['STATUS_NO']), $active_statuses) ? MF_INV_ACTIVE : 0x0000);
			$mobile = ((intval($row['CI_TYPE']) == 1 && intval($row['TYPE_NO']) == 2) ? MF_INV_MOBILEDEV : 0x0000);
			$bcc = ((intval($row['STATUS_NO']) == 1 && intval($row['CI_TYPE']) == 1 && (intval($row['TYPE_NO']) == 85 || intval($row['TYPE_NO']) == 45)) ? MF_INV_BCCDEV : 0x0000); //backup communication channel (ДКС)
			$duplicate = 0;

			$inv_active = ($active ? IF_INV_ACTIVE : 0x0000);
			$inv_mobile = ($mobile ? IF_INV_MOBILEDEV : 0x0000);
			$inv_bcc = ($bcc ? IF_INV_BCCDEV : 0x0000);

			$mac_exclude = 0;

			// Пометить MAC исключенным, если в ИТ Инвент Тип УЕ = РКС (ID  45) & Местоположение = Склад (ID 1) & Статус = ЗИП (ID 16)
			if(
				(intval($row['CI_TYPE']) == 45)
				&& (intval($row['LOC_NO']) == 1)
				&& (intval($row['STATUS_NO']) == 16)
			)
			{
				$mac_exclude = MF_TEMP_EXCLUDED;
			}

			// Load SN and MACs

			$sn = strtoupper(preg_replace('/[-:;., ]/i', '', (string) $row['SERIAL_NO']));

			if(strcasecmp($sn, 'N/A') == 0)
			{
				$sn = '';
			}
			
			$macs = array();
			
			for($k = 1; $k <= 17; $k++)    // mac* fields count
			{
				$mac = strtolower(preg_replace('/[^0-9a-f]/i', '', (string) $row['mac'.$k]));

				if(!empty($mac) && strlen($mac) == 12)
				{
					$macs[] = $mac;
				}
			}
			
			if(!empty($sn) || count($macs) > 0)
			{
				$inv_id = 0;
				if(!$db->select_ex($result, rpv("SELECT i.`id`, i.`inv_no`, i.`flags` FROM @inv AS i WHERE i.`inv_no` = ! LIMIT 1", $row['INV_NO'])))
				{
					if($db->put(rpv("INSERT INTO @inv (`inv_no`, `type_no`, `model_no`, `status`, `branch_no`, `loc_no`, `flags`) VALUES (!, #, #, #, #, #, #)",
						$row['INV_NO'],
						$row['TYPE_NO'],
						$row['MODEL_NO'],
						$row['STATUS_NO'],
						$row['BRANCH_NO'],
						$row['LOC_NO'],
						IF_EXIST_IN_ITINV | $inv_active | $inv_mobile| $inv_bcc
					)))
					{
						$inv_id = $db->last_id();
					}
				}
				else
				{
					$inv_id = $result[0][0];

					// Пометить MAC исключенным, если статус в ИТ Инвент сменился на "не рабочий"
					if(!$inv_active && ((intval($result[0][2]) & IF_INV_ACTIVE) == 0))
					{
						$mac_exclude = MF_TEMP_EXCLUDED;
					}
					
					$db->put(rpv("UPDATE @inv SET `inv_no` = !, `type_no` = #, `model_no` = #, `status` = #, `branch_no` = #, `loc_no` = #, `flags` = ((`flags` & ~{%IF_INV_ACTIVE}) | #) WHERE `id` = # LIMIT 1",
						$row['INV_NO'],
						$row['TYPE_NO'],
						$row['MODEL_NO'],
						$row['STATUS_NO'],
						$row['BRANCH_NO'],
						$row['LOC_NO'],
						IF_EXIST_IN_ITINV | $inv_active | $inv_mobile| $inv_bcc,
						$inv_id
					));
				}
			}

			// Save SN

			if(!empty($sn))
			{
				$mac_id = 0;
				if(!$db->select_ex($result, rpv("SELECT m.`id`, m.`inv_no`, m.`flags` FROM @mac AS m WHERE m.`mac` = ! AND (`flags` & {%MF_SERIAL_NUM}) = {%MF_SERIAL_NUM} LIMIT 1", $sn)))
				{
					if($db->put(rpv("INSERT INTO @mac (`mac`, `inv_no`, `type_no`, `model_no`, `status`, `branch_no`, `loc_no`, `flags`) VALUES (!, !, #, #, #, #, #, #)",
						$sn,
						$row['INV_NO'],
						$row['TYPE_NO'],
						$row['MODEL_NO'],
						$row['STATUS_NO'],
						$row['BRANCH_NO'],
						$row['LOC_NO'],
						MF_EXIST_IN_ITINV | MF_SERIAL_NUM | $active | $mobile| $bcc
					)))
					{
						$mac_id = $db->last_id();
					}
				}
				else
				{
					$mac_id = $result[0][0];

					if(intval($result[0][2]) & MF_EXIST_IN_ITINV && $sn !== 'N/A' && $sn !== 'N\A' && $sn !== 'NA')    // Exist in IT Invent?
					{
						$duplicate = MF_DUPLICATE;
						echo 'Possible duplicate: ID: '.$mac_id.' INV_NO: '.$row['INV_NO'].' and '.$result[0][1].', SN: '.$sn.', STATUS_NO: '.intval($row['STATUS_NO'])."\r\n";
					}

					$db->put(rpv("UPDATE @mac SET `inv_no` = !, `type_no` = #, `model_no` = #, `status` = #, `branch_no` = #, `loc_no` = #, `flags` = (`flags` | #) WHERE `id` = # LIMIT 1",
						$row['INV_NO'],
						$row['TYPE_NO'],
						$row['MODEL_NO'],
						$row['STATUS_NO'],
						$row['BRANCH_NO'],
						$row['LOC_NO'],
						MF_EXIST_IN_ITINV | $active | $mobile | $bcc | $duplicate,
						$mac_id
					));
				}

				$db->put(rpv("INSERT INTO @mac_inv (`mac_id`, `inv_id`) VALUES ({d0}, {d1}) ON DUPLICATE KEY UPDATE `inv_id` = `inv_id`", $mac_id, $inv_id));

				$i++;
			}

			// Save MACs
			foreach($macs as &$mac)    // mac* fields count
			{
				$duplicate = 0;

				$mac_id = 0;
				if(!$db->select_ex($result, rpv("SELECT m.`id`, m.`inv_no`, m.`flags` FROM @mac AS m WHERE m.`mac` = ! AND (`flags` & {%MF_SERIAL_NUM}) = 0 LIMIT 1", $mac)))
				{
					if($db->put(rpv("INSERT INTO @mac (`mac`, `inv_no`, `type_no`, `model_no`, `status`, `branch_no`, `loc_no`, `flags`) VALUES (!, !, #, #, #, #, #, #)",
						$mac,
						$row['INV_NO'],
						$row['TYPE_NO'],
						$row['MODEL_NO'],
						$row['STATUS_NO'],
						$row['BRANCH_NO'],
						$row['LOC_NO'],
						MF_EXIST_IN_ITINV | $active | $mobile | $bcc
					)))
					{
						$mac_id = $db->last_id();
					}
				}
				else
				{
					$mac_id = $result[0][0];

					if(intval($result[0][2]) & MF_EXIST_IN_ITINV)    // Exist in IT Invent?
					{
						$duplicate = MF_DUPLICATE;
						echo 'Possible duplicate: ID: '.$mac_id.' INV_NO: '.$row['INV_NO'].' and '.$result[0][1].', MAC: '.$mac.', STATUS_NO: '.intval($row['STATUS_NO'])."\r\n";
					}

					$db->put(rpv("UPDATE @mac SET `inv_no` = !, `type_no` = #, `model_no` = #, `status` = #, `branch_no` = #, `loc_no` = #, `date` = IF(#, NOW(), `date`), `flags` = (`flags` | #) WHERE `id` = # LIMIT 1",
						$row['INV_NO'],
						$row['TYPE_NO'],
						$row['MODEL_NO'],
						$row['STATUS_NO'],
						$row['BRANCH_NO'],
						$row['LOC_NO'],
						$mac_exclude,		// reset date if status was changed in IT Invent
						MF_EXIST_IN_ITINV | $active | $mobile | $bcc | $duplicate | $mac_exclude,
						$mac_id
					));
				}

				$db->put(rpv("INSERT INTO @mac_inv (`mac_id`, `inv_id`) VALUES ({d0}, {d1}) ON DUPLICATE KEY UPDATE `inv_id` = `inv_id`", $mac_id, $inv_id));

				$i++;
			}

			unset($mac);
		}
	}

	echo 'Count: '.$i."\r\n";

	if($row === FALSE)
	{
		throw new Exception('ERROR: sqlsrv_fetch_array complete with error: '.print_r(sqlsrv_errors(), true));
	}

	sqlsrv_free_stmt($invent_result);

	$invent_result = sqlsrv_query($conn, 'DROP TABLE #tmptable');

	if($invent_result === FALSE)
	{
		throw new Exception('ERROR: '.print_r(sqlsrv_errors(), true));
	}

	sqlsrv_free_stmt($invent_result);

	sqlsrv_close($conn);
