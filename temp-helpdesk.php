<?php
	// Retrieve information from Active Directory

	if(!defined('ROOTDIR'))
	{
		define('ROOTDIR', dirname(__FILE__));
	}

	define('Z_PROTECTED', 'YES');

	error_reporting(E_ALL);

	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.config.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.utils.php');
	require_once(ROOTDIR.DIRECTORY_SEPARATOR.'inc.db.php');

	header("Content-Type: text/plain; charset=utf-8");

	echo @file_get_contents(
				HELPDESK_URL.'/ExtAlert.aspx/'
				.'?Source=cdb'
				.'&Action=new'
				.'&Type=epwd'
				.'&To=sas'
				.'&Host='.urlencode('This is a test')
				.'&Message='.urlencode(
					'Требуется запретить установку пустого пароля у учётной записи.'
					."\nПК: ".'This is a test'
					."\nИсточник информации о ПК: "
					."\nКод работ: EPWD\n\n".WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Сброс-флага-разрещающего-установить-пустой-пароль.ashx'
				)
		);
