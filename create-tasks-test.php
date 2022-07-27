<?php
	// Create new and close resolved tasks (updates have not been installed for too long)
	/**
		\file
		\brief Создание нарядов на исправление несответствию базовому уровню установки обновлений на ПК.
		
		Выполняется проверка информации загруженной из SCCM на соответствие базовому уровню установки обновлений.
		Если ПК не соответствует базовому уровню, выставляется заявка в HelpDesk на устаранение проблемы.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-test:\n";

	$xml = helpdesk_api_request(
		'Source=cdb'
		.'&Action=new'
		.'&Type=rms'
		.'&To='.urlencode('byname')
		.'&Host='.urlencode('7701-W0000')
		.'&Message='.helpdesk_message(
			TT_TEST,
			array(
				'host'			=> '7701-W0000',
				'to'			=> 'byname',
				'flags'			=> 'Flags was here'
			)
		)
	);

	if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
	{
		echo '7701-W0000 '.$xml->extAlert->query['number']."\r\n";
	}
