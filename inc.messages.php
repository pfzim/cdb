<?php
	/**
		\file
		\brief Файл содержит шаблоны сообщений
	*/

$template_helpdesk_messages = array(
	TT_CLOSE =>
		'Заявка более не актуальна. Закрыта автоматически'
		,

	TT_MBOX_UNLIM =>
		'Не настроена квота на почтовом ящике пользователя. Установите квоту.'
		."\nУЗ: %host%"
		."\nКод работ: MBXQ"
		,

	TT_INV_MOVE =>
		'Обнаружено расхождение в IT Invent: местоположение оборудования отличается от местоположения коммутатора/маршрутизатора, в который оно подключено.'
		."\n\nИнвентарный номер оборудования: %m_inv_no%"
		."\nDNS имя: %m_name%"
		."\n%mac_or_sn%"
		."\nПорт: %port%"
		."\nОписание порта: %port_desc%"
		."\nVLAN ID: %vlan%"
		."\nВремя регистрации: %regtime%"
		."\nFlags: %flags%"
		."\n\nИнвентарный номер коммутатора/маршрутизатора: %d_inv_no%"
		."\nDNS имя: %host%"
		."\nСерийный номер: %d_mac%"
		."\nFlags: %d_flags%"
		."\n\nКод работ: IIV10"
		."\n\nПодробнее: ".WIKI_URL.'/Процессы%20и%20функции%20ИТ.Местоположение-оборудования-отличается-от-местоположения-коммутатора-в-которыи-оно-подключено.ashx'
		,

	TT_INV_TASKFIX =>
		'Необходимо проанализировать заявки по данному сетевому устройству. Принять меры: добавить в ИТ Инвент, удалить из базы Снежинки или добавить в исключение.'
		."\n\n%mac_or_sn%"
		."\nIP: %ip%"
		."\nDNS name: %dns_name%"
		."\nУстройство подключено к: %host%"
		."\nПорт: %port%"
		."\nОписание порта: %port_desc%"
		."\nVLAN ID: %vlan%"
		."\nВремя регистрации: %regtime%"
		."\nКоличество повторных заявок: %issues%"
		."\n\nКод работ: IIV09"
		."\n\nИстория выставленных нарядов: ".CDB_URL.'-ui/cdb_ui.php?path=mac_info/%id%'
		."\n\nВ решении указать причину и принятые меры по недопущению открытия повторных заявок."
		,

	TT_WIN_UPDATE =>
		'Необходимо устранить проблему установки обновлений ОС.'
		."\nПК: %host%"
		."\nОперационная система: %os% (%os_version%)"
		."\nИсточник информации о ПК: %flags%"
		."\nКод работ: OSUP\n\n".WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Инструкция-Устранение-ошибок-установки-обновлений.ashx'
		,

	TT_TMAC =>
		'Обнаружена попытка запуска ПО из запрещённого расположения. Удалите или переустановите ПО в Program Files.'
		."\nПК: %host%"
		."\nИсточник информации о ПК: %flags%"
		."\nКод работ: AC001"
		."\n\nСписок обнаруженного и заблокированного ПО:%data%"
		,

	TT_TMEE =>
		'Выявлена проблема с TMEE'
		."\nПК: %host%"
		."\nСтатус шифрования: %ee_encryptionstatus%"
		."\nИсточник информации о ПК: %flags%"
		."\nКод работ: FDERE\n\n".WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Инструкция%20по%20восстановлению%20работы%20агента%20Full%20Disk%20Encryption.ashx'
		,

	TT_TMAO =>
		'Выявлена проблема с антивирусом Trend Micro Apex One.'
		."\n\nПК: %host%"
		."\nВерсия антивирусной базы: %ao_script_ptn%"
		."\nИсточник информации о ПК: %flags%"
		."\nКод работ: AVCTRL\n\n".WIKI_URL."/Отдел%20ИТ%20Инфраструктуры.Restore_AO_agent.ashx"
		,

	TT_PC_RENAME =>
		"Имя ПК не соответствует шаблону. Переименуйте ПК %host%"
		."\nDN: %dn%"
		."\nИсточник информации о ПК: %flags%"
		."\nКод работ: RNM01\n\n"
		.WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Регламент-именования-ресурсов-в-каталоге-Active-Directory.ashx'
		,

	TT_LAPS =>
		'Не установлен либо не работает LAPS.'
		."\nПК: %host%"
		."\nПоследнее обновление LAPS: %laps_exp%"
		."\nИсточник информации о ПК: %flags%"
		."\nКод работ: LPS01\n\n".WIKI_URL.'/Сервисы.laps%20troubleshooting.ashx'
		,

	TT_SCCM =>
		'Выявлена проблема с агентом SCCM'
		."\nПК: %host%"
		."\nПоследняя синхронизация с сервером: %sccm_lastsync%"
		."\nИсточник информации о ПК: %flags%"
		."\nКод работ: SC001\n\n".WIKI_URL.'/Группа%20удалённой%20поддержки.SC001-отсутствует-клиент-SCCM.ashx'
		,

	TT_PASSWD =>
		'Требуется запретить установку пустого пароля у учётной записи.'
		."\nПК: %host%"
		."\nИсточник информации о ПК: %flags%"
		."\nКод работ: EPWD\n\n".WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Сброс-флага-разрещающего-установить-пустой-пароль.ashx'
		,

	TT_OS_REINSTALL =>
		'Версия операционной системы не соответсвует стандартам компании.'
		."\nПК: %host%"
		."\nТекущая ОС: %os%"
		."\nВерсия: %os_version%"
		."\nИсточник информации о ПК: %flags%"
		."\nКод работ: OSUP\n\n".WIKI_URL.'/Департамент%20ИТ%20Отдел%20ИТ%20поддержки%20Регионов%20(ТСА).Установка-ОС-с-использованием-SCCM.ashx'
		,

	TT_INV_ADD =>
		'Обнаружено сетевое устройство %data_type% которого не зафиксирован в IT Invent'
		."\n\n%mac_or_sn%"
		."\nDNS name: %dns_name%"
		."\nIP: %ip%"
		."\nСтатус: %status_code% %status_name%"
		."\nFlags: %flags%"
		."\n\nУстройство подключено к: %host%"
		."\nПорт: %port%"
		."\nОписание порта: %port_desc%"
		."\nVLAN ID: %vlan%"
		."\nВремя регистрации: %regtime%"
		."\n\nКод работ: IIV09"
		."\n\nСледует актуализировать данные по указанному устройству и заполнить соответствующий атрибут. Подробнее: ".WIKI_URL.'/Процессы%20и%20функции%20ИТ.Обнаружено-сетевое-устроиство-MAC-адрес-которого-не-зафиксирован-в-IT-Invent.ashx'
		."\nВ решении укажите Инвентарный номер оборудования!"
		,

	TT_VULN_FIX =>
		'Nessus: Обнаружена уязвимость требующая устранения. #%id%'
		."\n\nПК: %host%"
		."\nУязвимость: %plugin_name%"
		."\nУровень опасности: %severity%"
		."\nДата обнаружения: %scan_date%"
		,

	TT_VULN_FIX_MASS =>
		'Nessus: Обнаружена массовая уязвимость требующая устранения. #%plugin_id%'
		."\n\nУязвимость: %plugin_name%"
		."\nУровень опасности: %severity%"
		."\nКоличество уязвимых устройств: %vuln_count%"
		,

	TT_NET_ERRORS =>
		'Необходимо заменить кабель в ТТ на заводской патч-корд 5м'
		."\nИмя маршрутизатора: %host%"
		."\n\n%data%"
		."\n\nКод работ: NET01"
		."\nПодробнее: ".WIKI_URL.'/Департамент%20ИТ%20Отдел%20ИТ%20поддержки%20Регионов%20(ТСА).FAQ-Износ-кабеля-LAN-нет-линка.ashx'
		,

	TT_INV_SOFT =>
		'На компьютере обнаружено ПО не зарегистрированное в IT Invent'
		."\n\nИмя ПК: %host%"
		."\nИсточник информации о ПК: %flags%"
		."\nСписок обнаруженного ПО доступен по ссылке: ".CDB_URL.'-ui/cdb_ui.php?path=computer_info/%id%'
		."\n\nКод работ: INV06"
		."\n\nСледует зарегистрировать ПО в ИТ Инвент или удалить с ПК пользователя. Подробнее: ".WIKI_URL.'/Процессы%20и%20функции%20ИТ.Обнаружено-сетевое-устроиство-MAC-адрес-которого-не-зафиксирован-в-IT-Invent.ashx'
		,

	TT_TMAO_DLP =>
		'Выявлена проблема с модулем Data Protection антивируса Trend Micro Apex One.'
		."\n\nПК: %host%"
		."\nСтатус модуля: %dlp_status%"
		."\nИсточник информации о ПК: %flags%"
		."\nКод работ: AVCTRL\n\n".WIKI_URL."/Отдел%20ИТ%20Инфраструктуры.Restore_AO_agent.ashx"
		,

	TT_INV_ADD_DECOMIS =>
		'Списанное оборудование появилось в сети'
		."\n\n%mac_or_sn%"
		."\nИнвентарный номер оборудования: %inv_no%"
		."\nТип: %type_name%"
		."\nСтатус: %status_code% %status_name%"
		."\nDNS name: %dns_name%"
		."\nIP: %ip%"
		."\nFlags: %flags%"
		."\n\nУстройство подключено к: %host%"
		."\nПорт: %port%"
		."\nОписание порта: %port_desc%"
		."\nVLAN ID: %vlan%"
		."\nВремя регистрации: %regtime%"
		."\n\nКод работ: IIV11"
		."\n\nПодробнее: ".WIKI_URL.'/Процессы%20и%20функции%20ИТ.FAQ-Зафиксировано-списанное-оборудование-в-сети.ashx'
		."\nВ решении укажите Инвентарный номер оборудования!"
		,

	TT_RMS_INST =>
		'На компьютере не установлен RMS.'
		."\n\nПК: %host%"
		."\nИсточник информации о ПК: %flags%"
		,

	TT_RMS_SETT => 'Unused',
	TT_RMS_VERS => 'Unused',

	TT_EDGE_INSTALL =>
		'На компьютере не установлен Edge.'
		."\n\nПК: %host%"
		."\nИсточник информации о ПК: %flags%"
		,

	TT_TEST =>
		'THIS IS A TEST!'
		."\n\nПК: %host%"
		."\nИсточник информации о ПК: %flags%"
);
