<?php
	// Report CMDB - MaxPatrol

	/**
		\file
		\brief Формирование отчёта по списку серверов из CMDB и дате их
		сканировния из MaxPatrol
	*/


	if(!defined('Z_PROTECTED')) exit;

	echo PHP_EOL.'report-cmdb-maxpatrol:'.PHP_EOL;

	global $g_cmdb_vmm_short_flags;

	$html = <<<'EOT'
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<style>
		body{font-family: Courier New; font-size: 8pt;}
		h1{font-size: 16px;}
		h2{font-size: 14px;}
		h3{font-size: 12px;}
		table{border: 1px solid black; border-collapse: collapse; font-size: 8pt;}
		th{border: 1px solid black; background: #dddddd; padding: 5px; color: #000000;}
		td{border: 1px solid black; padding: 5px; }
		.pass {background: #7FFF00;}
		.warn {background: #FFE600;}
		.error {background: #FF0000; color: #ffffff;}
		</style>
	</head>
	<body>
	<h1>Время последнего сканирования MaxPatrol серверов по списку из CMDB</h1>
EOT;

	$maxpatrol_audit_max_age_days = get_config_int('maxpatrol_audit_max_age_days');
	$scan_period = time() - $maxpatrol_audit_max_age_days*24*60*60;

	$i = 0;

	$data = array(
		'linux' => array(
			'table' => '',
			'total' => 0,
			'scanned' => 0,
			'unscanned_root' => 0
		),
		'windows' => array(
			'table' => '',
			'total' => 0,
			'scanned' => 0,
			'unscanned_root' => 0
		),
		'other' => array(
			'table' => '',
			'total' => 0,
			'scanned' => 0,
			'unscanned_root' => 0
		),
	);
	
	if($db->select_assoc_ex($result, rpv("
		SELECT
			vm.`name`,
			DATE_FORMAT(mp.`audit_time`, '%d.%m.%Y %H:%i:%s') AS `audit_time`,
			vm.`cmdb_os`,
			vm.`flags`
		FROM @vm AS vm
		LEFT JOIN @maxpatrol AS mp
			ON mp.`name` = vm.`name`
			AND mp.`flags` & {%MPF_EXIST}
		WHERE
			vm.`flags` & {%VMF_EXIST_CMDB}
		ORDER BY
			mp.`audit_time`,
			vm.`name`
	")))
	{
		foreach($result as &$row)
		{
			$recently_scanned = FALSE;

			if(!empty($row['audit_time']))
			{
				$audit_time = strtotime($row['audit_time']);
				if($audit_time > $scan_period)
				{
					$recently_scanned = TRUE;
				}
			}

			$os = 'other';
			if(stripos($row['cmdb_os'], 'win') !== FALSE)
			{
				$os = 'windows';
			}
			else if(preg_match('/linux|ubuntu|debian|centos/i', $row['cmdb_os']))
			{
				$os = 'linux';
			}

			$data[$os]['total']++;
			if($recently_scanned)
			{
				$data[$os]['scanned']++;
			}
			else
			{
				if(intval($row['flags']) & VMF_HAVE_ROOT)
				{
					$data[$os]['unscanned_root']++;
				}
				
				$data[$os]['table'] .= '<tr>';
				$data[$os]['table'] .= '<td>'.$row['name'].'</td>';
				$data[$os]['table'] .= '<td>'.$row['audit_time'].'</td>';
				$data[$os]['table'] .= '<td>'.$row['cmdb_os'].'</td>';
				$data[$os]['table'] .= '<td>'. (($os == 'windows') ? '' : ((intval($row['flags']) & VMF_HAVE_ROOT) ? '&#x2713;' : '&#x2717;')).'</td>';
				$data[$os]['table'] .= '</tr>';
			}

			$i++;
		}
	}

	$html .= '<p>';
	$html .= 'Актуальность сканирования: '.$maxpatrol_audit_max_age_days.' дней';
	$html .= '</p>';
	
	$html .= '<table>';
	$html .= '<tr><th>-</th><th>Всего активов</th><th>Сканируется</th><th>Не сканируется (с root доступом)</th></tr>';
	$html .= '<tr><td>Серверы Windows</td><td>'.$data['windows']['total'].'</td><td>'.$data['windows']['scanned'].'</td><td>'.($data['windows']['total'] - $data['windows']['scanned']).'</td></tr>';
	$html .= '<tr><td>Серверы Linux</td><td>'.$data['linux']['total'].'</td><td>'.$data['linux']['scanned'].'</td><td>'.($data['linux']['total'] - $data['linux']['scanned']).' ('.$data['linux']['unscanned_root'].')</td></tr>';
	$html .= '<tr><td>Остальные серверы</td><td>'.$data['other']['total'].'</td><td>'.$data['other']['scanned'].'</td><td>'.($data['other']['total'] - $data['other']['scanned']).'</td></tr>';
	$html .= '</table>';

	$html .= '<br />';

	$html .= '<table>';
	$html .= '<tr><th>Name</th><th>Audit time &#9650;</th><th>OS</th><th>Root Access</th></tr>';
	$html .= '<tr><th colspan="4">Windows</th></tr>';
	$html .= $data['windows']['table'];
	$html .= '<tr><th colspan="4">Linux</th></tr>';
	$html .= $data['linux']['table'];
	$html .= '<tr><th colspan="4">Other</th></tr>';
	$html .= $data['other']['table'];
	$html .= '</table>';

	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-cmdb-maxpatrol">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if(php_mailer(REPORT_CMDB_MAXPATROL_MAIL_TO, CDB_TITLE.': Report CMDB MaxPatrol (servers)', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK'.PHP_EOL;
	}
	else
	{
		echo 'Send mail: FAILED'.PHP_EOL;
	}
