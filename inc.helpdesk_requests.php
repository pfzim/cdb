$templates_helpdesk_requests = array(
	TT_CLOSE => 
		'Source=cdb'
		.'&Action=resolved'
		.'&Id=%operid%'
		.'&Num=%opernum%'
		.'&Message='.urlencode("Заявка более не актуальна. Закрыта автоматически")
		,

	TT_MBOX_UNLIM =>
		'Source=cdb'
		.'&Action=new'
		.'&Type=test'
		.'&To=goo'
		.'&Host=%host%'
		.'&Message='.urlencode(
			'Не настроена квота на почтовом ящике пользователя. Установите квоту.'
			."\nУЗ: %host%"
			."\nКод работ: MBXQ"
			//."\n\n".WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Сброс-флага-разрещающего-установить-пустой-пароль.ashx'
		)
		,

	TT_INV_MOVE =>
		'Source=cdb'
		.'&Action=new'
		.'&Type=itinvmove'
		.'&To=bynetdev'
		.'&Host=%host%'
		.'&Vlan=%vlan%'
		.'&Message='.urlencode(
			'Обнаружено расхождение в IT Invent: местоположение оборудования отличается от местоположения коммутатора/маршрутизатора, в который оно подключено.'
			."\n\nИнвентарный номер оборудования: %m_inv_no%"
			."\nDNS имя: %m_name%"
			."\n%mac_or_sn%"
			."\nПорт: %port%"
			."\nVLAN ID: %vlan%"
			."\nВремя регистрации: %regtime%"
			."\nFlags: %flags%"
			."\n\nИнвентарный номер коммутатора/маршрутизатора: %d_inv_no%"
			."\nDNS имя: %host%"
			."\nСерийный номер: %d_mac%"
			."\nFlags: %d_flags%"
			."\n\nКод работ: IIV10"
			."\n\nПодробнее: ".WIKI_URL.'/Процессы%20и%20функции%20ИТ.Местоположение-оборудования-отличается-от-местоположения-коммутатора-в-которыи-оно-подключено.ashx'
		)
		,

	TT_INV_TASKFIX =>
		'Source=cdb'
		.'&Action=new'
		.'&Type=itinvent'
		.'&To=ritm'
		.'&Host=%host%'
		.'&Vlan=%vlan%'
		.'&Message='.urlencode(
			'Необходимо проанализировать заявки по данному сетевому устройству. Принять меры: добавить в ИТ Инвент, удалить из базы Снежинки или добавить в исключение.'
			."\n\n%mac_or_sn%"
			."\nIP: %ip%"
			."\nDNS name: %dns_name%"
			."\nУстройство подключено к: %host%"
			."\nПорт: %port%"
			."\nVLAN ID: %vlan%"
			."\nВремя регистрации: %regtime%"
			."\nКоличество повторных заявок: %issues%"
			."\n\nКод работ: IIV09"
			."\n\nИстория выставленных нарядов: ".CDB_URL.'/cdb.php?action=get-mac-info&id=%id%'
			."\n\nВ решении указать причину и принятые меры по недопущению открытия повторных заявок."
		)
		,

	TT_WIN_UPDATE =>
		'Source=cdb'
		.'&Action=new'
		.'&Type=update'
		.'&To=%to%'
		.'&Host=%host%'
		.'&Message='.urlencode(
			'Необходимо устранить проблему установки обновлений ОС.'
			."\nПК: %host%"
			."\nОперационная система: %os% (%os_version%)"
			."\nИсточник информации о ПК: %flags%"
			."\nКод работ: OSUP\n\n".WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Инструкция-Устранение-ошибок-установки-обновлений.ashx'
		)
		,

	TT_TMAC =>
		'Source=cdb'
		.'&Action=new'
		.'&Type=ac'
		.'&To=goo'
		.'&Host=%host%'
		.'&Message='.urlencode(
			'Обнаружена попытка запуска ПО из запрещённого расположения. Удалите или переустановите ПО в Program Files.'
			."\nПК: %host%"
			."\nИсточник информации о ПК: %flags%"
			."\nКод работ: AC001"
			."\n\nСписок обнаруженного и заблокированного ПО:%data%"
		)
		,

	TT_TMEE =>
		'Source=cdb'
		.'&Action=new'
		.'&Type=tmee'
		.'&To=byname'
		.'&Host=%host%'
		.'&Message='.urlencode(
			'Выявлена проблема с TMEE'
			."\nПК: %host%"
			."\nСтатус шифрования: %ee_encryptionstatus%"
			."\nИсточник информации о ПК: %flags%"
			."\nКод работ: FDERE\n\n".WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Инструкция%20по%20восстановлению%20работы%20агента%20Full%20Disk%20Encryption.ashx'
		)
		,

	TT_TMAO =>
		'Source=cdb'
		.'&Action=new'
		.'&Type=tmao'
		.'&To=byname'
		.'&Host=%host%'
		.'&Message='.urlencode(
			'Выявлена проблема с антивирусом Trend Micro Apex One.'
			."\n\nПК: %host%"
			."\nВерсия антивирусной базы: %ao_script_ptn%"
			."\nИсточник информации о ПК: %flags%"
			."\nКод работ: AVCTRL\n\n".WIKI_URL."/Отдел%20ИТ%20Инфраструктуры.Restore_AO_agent.ashx"
		)
		,

	TT_PC_RENAME =>
		'Source=cdb'
		.'&Action=new'
		.'&Type=rename'
		.'&To=hd'
		.'&Host=%host%'
		.'&Message='.urlencode(
			"Имя ПК не соответствует шаблону. Переименуйте ПК %host%"
			."\nDN: ".$row['dn']
			."\nИсточник информации о ПК: %flags%"
			."\nКод работ: RNM01\n\n"
			.WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Регламент-именования-ресурсов-в-каталоге-Active-Directory.ashx'
		)
		,

	TT_LAPS =>
		'Source=cdb'
		.'&Action=new'
		.'&Type=laps'
		.'&To=byname'
		.'&Host=%host%'
		.'&Message='.urlencode(
			'Не установлен либо не работает LAPS.'
			."\nПК: %host%"
			."\nПоследнее обновление LAPS: %laps_exp%"
			."\nИсточник информации о ПК: %flags%"
			."\nКод работ: LPS01\n\n".WIKI_URL.'/Сервисы.laps%20troubleshooting.ashx'
		)
		,

	TT_SCCM =>
		'Source=cdb'
		.'&Action=new'
		.'&Type=sccm'
		.'&To=byname'
		.'&Host=%host%'
		.'&Message='.urlencode(
			'Выявлена проблема с агентом SCCM'
			."\nПК: ".$row['name']
			."\nПоследняя синхронизация с сервером: %sccm_lastsync%"
			."\nИсточник информации о ПК: %flags%"
			."\nКод работ: SC001\n\n".WIKI_URL.'/Группа%20удалённой%20поддержки.SC001-отсутствует-клиент-SCCM.ashx'
		)
		,

	TT_PASSWD =>
		'Source=cdb'
		.'&Action=new'
		.'&Type=epwd'
		.'&To=sas'
		.'&Host=%host%'
		.'&Message='.urlencode(
			'Требуется запретить установку пустого пароля у учётной записи.'
			."\nПК: %host%"
			."\nИсточник информации о ПК: %flags%"
			."\nКод работ: EPWD\n\n".WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Сброс-флага-разрещающего-установить-пустой-пароль.ashx'
		)
		,

	TT_OS_REINSTALL => 
		'Source=cdb'
		.'&Action=new'
		.'&Type=update'
		.'&To=byname'
		.'&Host=%host%'
		.'&Message='.urlencode(
			'Версия операционной системы не соответсвует стандартам компании.'
			."\nПК: %host%"
			."\nТекущая ОС: %os%"
			."\nВерсия: %os_vesion%"
			."\nИсточник информации о ПК: %flags%"
			."\nКод работ: OSUP\n\n".WIKI_URL.'/Департамент%20ИТ%20Отдел%20ИТ%20поддержки%20Регионов%20(ТСА).Установка-ОС-с-использованием-SCCM.ashx'
		)
		,

	TT_INV_ADD =>
		'Source=cdb'
		.'&Action=new'
		.'&Type=itinvent'
		.'&To=bynetdev'
		.'&Host=%host%'
		.'&Vlan=%vlan%'
		.'&Message='.urlencode(
			'Обнаружено сетевое устройство %data_type% которого не зафиксирован в IT Invent'
			."\n\n%mac_or_sn%".
			."\nDNS name: %dns_name%"
			."\nIP: %ip%"
			."\nFlags: %flags%"
			."\n\nУстройство подключено к: %host%"
			."\nПорт: %port%"
			."\nVLAN ID: %vlan%"
			."\nВремя регистрации: %regtime%"
			."\n\nКод работ: IIV09"
			."\n\nСледует актуализировать данные по указанному устройству и заполнить соответствующий атрибут. Подробнее: ".WIKI_URL.'/Процессы%20и%20функции%20ИТ.Обнаружено-сетевое-устроиство-MAC-адрес-которого-не-зафиксирован-в-IT-Invent.ashx'
			."\nВ решении укажите Инвентарный номер оборудования!"
		)
		,

	TT_VULN_FIX =>
		'?Source=cdb'
		.'&Action=new'
		.'&Type=vuln'
		.'&To=byname'
		.'&Host=%host%'
		.'&Message='.urlencode(
			'Nessus: Обнаружена уязвимость требующая устранения. #%id%'
			."\n\nПК: %host%"
			."\nУязвимость: %plugin_name%"
			."\nУровень опасности: %severity%"
			."\nДата обнаружения: %scan_date%"
		)
		,

	TT_VULN_FIX_MASS =>
		'Source=cdb'
		.'&Action=new'
		.'&Type=vuln'
		.'&To=sas'
		.'&Host=%host%'
		.'&Message='.urlencode(
			'Nessus: Обнаружена массовая уязвимость требующая устранения. #%plugin_id%'
			."\n\nУязвимость: %plugin_name%"
			."\nУровень опасности: %severity%"
			."\nКоличество уязвимых устройств: %vuln_count%"
		)
		,

	TT_NET_ERRORS =>
		'Source=cdb'
		.'&Action=new'
		.'&Type=neterrors'
		.'&To=bynetdev'
		.'&Host=%host%'
		.'&Message='.urlencode(
			'Необходимо заменить кабель в ТТ на заводской патч-корд 5м'
			."\n\nDNS имя маршрутизатора: %host%"
			."\n\n%data%"
			."\n\nКод работ: NET01"
		)
		,

	TT_INV_SOFT =>
		,

	TT_TMAO_DLP =>
		.'Source=cdb'
		.'&Action=new'
		.'&Type=tmao'
		.'&To=byname'
		.'&Host=%host%'
		.'&Message='.urlencode(
			'Выявлена проблема с модулем Data Protection антивируса Trend Micro Apex One.'
			."\n\nПК: %host%"
			."\nСтатус модуля: %dlp_status%"
			."\nИсточник информации о ПК: %flags%"
			."\nКод работ: AVCTRL\n\n".WIKI_URL."/Отдел%20ИТ%20Инфраструктуры.Restore_AO_agent.ashx"
		)
		,

	TT_INV_ADD_DECOMIS =>
		'Source=cdb'
		.'&Action=new'
		.'&Type=itinvstatus'
		.'&To=itinvent'
		.'&Host=%host%'
		.'&Vlan=%vlan%'
		.'&Message='.urlencode(
			'Списанное оборудование появилось в сети'
			."\n\n%mac_or_sn%".
			."\nИнвентарный номер оборудования: %inv_no%"
			."\nТип: %type_name%"
			."\nСтатус: %status_name"
			."\nDNS name: %dns_name%"
			."\nIP: %ip%"
			."\nFlags: %flags%"
			."\n\nУстройство подключено к: %host%"
			."\nПорт: %port%"
			."\nVLAN ID: %vlan%"
			."\nВремя регистрации: %regtime%"
			."\n\nКод работ: IIV11"
			."\n\nПодробнее: ".WIKI_URL.'/Процессы%20и%20функции%20ИТ.FAQ-Зафиксировано-списанное-оборудование-в-сети.ashx'
			."\nВ решении укажите Инвентарный номер оборудования!"
		)
		,

	TT_RMS_INST => '',
	TT_RMS_SETT => '',
	TT_RMS_VERS => ''
);
