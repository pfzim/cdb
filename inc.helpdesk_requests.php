$templates_helpdesk_requests = array(
	TT_CLOSE => 
		'Source=cdb'
		.'&Action=resolved'
		.'&Id=%operid%'
		.'&Num=%opernum%'
		.'&Message='.urlencode("Заявка более не актуальна. Закрыта автоматически")
		,

	TT_MBOX_UNLIM				=> "
		",

	TT_INV_MOVE					=> "
		",

	TT_INV_TASKFIX				=> "
		",

	TT_WIN_UPDATE				=> "
		",

	TT_TMAC						=> "
		",

	TT_TMEE						=> "
		",

	TT_TMAO						=> "
		",

	TT_PC_RENAME				=> "
		",

	TT_LAPS						=> "
		",

	TT_SCCM						=> "
		",

	TT_PASSWD					=> "
		",

	TT_OS_REINSTALL				=> "
		",

	TT_INV_ADD					=> "
		",

	TT_VULN_FIX					=> "
		",

	TT_VULN_FIX_MASS			=> "
		",

	TT_NET_ERRORS				=> "
		",

	TT_INV_SOFT					=> "
		",

	TT_TMAO_DLP					=> "
		",

	TT_INV_ADD_DECOMIS			=> "
		",

	TT_RMS_INST					=> "
		",

	TT_RMS_SETT					=> "
		",

	TT_RMS_VERS					=> "
		"
);
