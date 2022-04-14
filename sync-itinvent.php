<?php
	// Retrieve information from IT Invent database
	/**
		\file
		\brief Синхронизация БД IT Invent.
		
		Загрузка информации о MAC адресах и серийных номера.
		
		Добавлена загрузка информации о местоположении оборудования.
		Местоположение состоит из двух значенией: Филиал (BRANCH_NO) и
		Местоположение (LOC_NO). Местоположение может содержать как номер
		кабинете или этажа, так и адреса магазинов. Если поле LOC_NO_BUH
		равно NULL, то Местоположение (LOC_NO) не загружается - это кабинет,
		а загружается только Филиал (BRANCH_NO).
		
		Активным считается оборудование имеющее статус Работает или
		Выдан пользователю для удаленной работы.
		
		Загружается информация только по активному оборудованию.

		Оборудование помечается Мобильным если имеет тип Ноутбук. В последующем
		такое оборудование исключается из проверок на местоположение.
		
		Добавлено "удаление" устройств, которые не появлялись в сети более 60 дней
	*/

	/*

		FIELD_NO	ITEM_NO		FIELD_NAME
		94			1			Усилитель 3G: mac-адрес
		106			1			MAC Адрес 1
		107			1			MAC Адрес 2
		133			1			MAC Адрес ТСД
		149			1			MAC Адрес 3
		163			1			Усилитель 3G: mac-адрес 2
		210			1			MAC Адрес 4
		
		TYPE_NO	CI_TYPE	TYPE_NAME
		2       1       Ноутбук
		85      1       Дублирующий канал связи

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
  */
	
	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-itinvent:\n";

	$active_statuses = array(
		1,    // Работает
		21    // Выдан пользователю для удаленной работы
	);

	$params = array(
		'Database' =>				ITINVENT_DB_NAME,
		'UID' =>					ITINVENT_DB_USER,
		'PWD' =>					ITINVENT_DB_PASSWD,
		'ReturnDatesAsStrings' =>	true
	);

	$conn = sqlsrv_connect(ITINVENT_DB_HOST, $params);
	if($conn === false)
	{
		print_r(sqlsrv_errors());
		exit;
	}

	// Before sync remove marks: 0x0010 - Exist in IT Invent, 0x0040 - Active, 0x0100 - Mobile, 0x0200 - Duplicate, 0x0400 - BCC, set status = 0
	$db->put(rpv("UPDATE @mac SET `flags` = (`flags` & ~({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE} | {%MF_INV_MOBILEDEV} | {%MF_DUPLICATE} | {%MF_INV_BCCDEV})), `status` = 0 WHERE `flags` & ({%MF_EXIST_IN_ITINV} | {%MF_INV_ACTIVE} | {%MF_INV_MOBILEDEV} | {%MF_DUPLICATE} | {%MF_INV_BCCDEV})"));

	// Temporarily exclude MAC addresses from checks that not seen in network more than 30 days
	$db->put(rpv("UPDATE @mac SET `flags` = (`flags` | {%MF_TEMP_EXCLUDED}) WHERE `flags` & {%MF_FROM_NETDEV} AND `date` < DATE_SUB(NOW(), INTERVAL 60 DAY)"));

	$invent_result = sqlsrv_query($conn, "
		SELECT
			[ID]
			,[INV_NO]
			,[TYPE_NO]
			,[CI_TYPE]
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
			,[MAC_ADDRESS] AS mac0
			,m1.[FIELD_VALUE] AS mac1
			,m2.[FIELD_VALUE] AS mac2
			,m3.[FIELD_VALUE] AS mac3
			,m4.[FIELD_VALUE] AS mac4
			,m5.[FIELD_VALUE] AS mac5
			,m6.[FIELD_VALUE] AS mac6
			,m7.[FIELD_VALUE] AS mac7
			,m8.[FIELD_VALUE] AS mac8
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
			)
	");

	if($invent_result !== FALSE)
	{
		sqlsrv_free_stmt($invent_result);

		$invent_result = sqlsrv_query($conn, 'SELECT * FROM #tmptable');

		$i = 0;
		while($row = sqlsrv_fetch_array($invent_result, SQLSRV_FETCH_ASSOC))
		{
			//if(in_array(intval($row['STATUS_NO']), $active_statuses)) // Временно отключил проверку статусов
			{
				//$active = 0x0040; //(in_array(intval($row['STATUS_NO']), $active_statuses) ? 0x0040 : 0x0000);
				$active = (in_array(intval($row['STATUS_NO']), $active_statuses) ? MF_INV_ACTIVE : 0x0000);
				$mobile = ((intval($row['TYPE_NO']) == 2 && intval($row['CI_TYPE']) == 1) ? MF_INV_MOBILEDEV : 0x0000);
				$bcc = ((intval($row['TYPE_NO']) == 85 && intval($row['CI_TYPE']) == 1 && intval($row['STATUS_NO']) == 1) ? MF_INV_BCCDEV : 0x0000); //backup communication channel (ДКС)
				$duplicate = 0;

				// Load SN
				$mac = strtoupper(preg_replace('/[-:;., ]/i', '', $row['SERIAL_NO']));
				if(!empty($mac))
				{
					$row_id = 0;
					if(!$db->select_ex($result, rpv("SELECT m.`id`, m.`inv_no`, m.`flags` FROM @mac AS m WHERE m.`mac` = ! AND (`flags` & {%MF_SERIAL_NUM}) = {%MF_SERIAL_NUM} LIMIT 1", $mac)))
					{
						if($db->put(rpv("INSERT INTO @mac (`mac`, `inv_no`, `status`, `branch_no`, `loc_no`, `flags`) VALUES (!, !, #, #, #, #)",
							$mac,
							$row['INV_NO'],
							$row['STATUS_NO'],
							$row['BRANCH_NO'],
							$row['LOC_NO'],
							MF_EXIST_IN_ITINV | MF_SERIAL_NUM | $active | $mobile| $bcc
						)))
						{
							$row_id = $db->last_id();
						}
					}
					else
					{
						$row_id = $result[0][0];

						if(intval($result[0][2]) & MF_EXIST_IN_ITINV && $mac !== 'N/A' && $mac !== 'N\A' && $mac !== 'NA')    // Exist in IT Invent?
						{
							$duplicate = MF_DUPLICATE;
							echo 'Possible duplicate: ID: '.$row_id.' INV_NO: '.$row['INV_NO'].' and '.$result[0][1].', SN: '.$mac.', STATUS_NO: '.intval($row['STATUS_NO'])."\r\n";
						}

						$db->put(rpv("UPDATE @mac SET `inv_no` = !, `status` = #, `branch_no` = #, `loc_no` = #, `flags` = (`flags` | #) WHERE `id` = # LIMIT 1",
							$row['INV_NO'],
							$row['STATUS_NO'],
							$row['BRANCH_NO'],
							$row['LOC_NO'],
							MF_EXIST_IN_ITINV | $active | $mobile | $bcc | $duplicate,
							$row_id
						));
					}
					$i++;
				}

				// Load MACs
				for($k = 1; $k <= 8; $k++)    // mac* fields count
				{
					$mac = strtolower(preg_replace('/[^0-9a-f]/i', '', $row['mac'.$k]));
					if(!empty($mac) && strlen($mac) == 12)
					{
						$row_id = 0;
						if(!$db->select_ex($result, rpv("SELECT m.`id`, m.`inv_no`, m.`flags` FROM @mac AS m WHERE m.`mac` = ! AND (`flags` & {%MF_SERIAL_NUM}) = 0 LIMIT 1", $mac)))
						{
							if($db->put(rpv("INSERT INTO @mac (`mac`, `inv_no`, `status`, `branch_no`, `loc_no`, `flags`) VALUES (!, !, #, #, #, #)",
								$mac,
								$row['INV_NO'],
								$row['STATUS_NO'],
								$row['BRANCH_NO'],
								$row['LOC_NO'],
								MF_EXIST_IN_ITINV | $active | $mobile | $bcc
							)))
							{
								$row_id = $db->last_id();
							}
						}
						else
						{
							$row_id = $result[0][0];

							if(intval($result[0][2]) & MF_EXIST_IN_ITINV)    // Exist in IT Invent?
							{
								$duplicate = MF_DUPLICATE;
								echo 'Possible duplicate: ID: '.$row_id.' INV_NO: '.$row['INV_NO'].' and '.$result[0][1].', MAC: '.$mac.', STATUS_NO: '.intval($row['STATUS_NO'])."\r\n";
							}

							$db->put(rpv("UPDATE @mac SET `inv_no` = !, `status` = #, `branch_no` = #, `loc_no` = #, `flags` = (`flags` | #) WHERE `id` = # LIMIT 1",
								$row['INV_NO'],
								$row['STATUS_NO'],
								$row['BRANCH_NO'],
								$row['LOC_NO'],
								MF_EXIST_IN_ITINV | $active | $mobile | $bcc | $duplicate,
								$row_id
							));
						}
						$i++;
					}
				}
			}
		}

		echo 'Count: '.$i."\r\n";

		sqlsrv_free_stmt($invent_result);
	}
	
	$invent_result = sqlsrv_query($conn, 'DROP TABLE #tmptable');

	if($invent_result !== FALSE)
	{
		sqlsrv_free_stmt($invent_result);
	}

	sqlsrv_close($conn);
