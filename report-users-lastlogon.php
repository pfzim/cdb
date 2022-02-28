<?php
	/**
		\file
		\brief Отчёт по пользователям у которых Last Logon был более 60 дней назад.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nreport-users-lastlogon:\n";

function name_join($lname, $fname, $mname)
{
	$delimiter = '';
	$output = '';
	
	if(!empty($lname))
	{
		$output = $lname;
		$delimiter = ' ';
	}

	if(!empty($fname))
	{
		$output .= $delimiter.$fname;
		$delimiter = ' ';
	}

	if(!empty($mname))
	{
		$output .= $delimiter.$mname;
		$delimiter = ' ';
	}
	
	return $output;
}

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
	<h1>Отчёт по пользователям у которых Last Logon был более 60 дней назад</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Login</th><th>Name</th><th>Last logon</th></tr>';

	$i = 0;
	$opened = 0;

	if($db->select_assoc_ex($result, rpv("
		SELECT p.`id`, p.`login`, p.`lname`, p.`fname`, p.`mname`, ll.`value`
		FROM c_properties_date AS ll
		LEFT JOIN c_persons AS p ON p.`id` = ll.`pid`
		WHERE
		  ll.`tid` = {%TID_PERSONS}
		  AND ll.`oid` = {d0}
		  AND (p.`flags` & ({%PF_AD_DISABLED} | {%PF_DELETED} | {%PF_HIDED})) = 0
		  AND ll.`value` < DATE_SUB(NOW(), INTERVAL 1 MONTH)
		ORDER BY ll.`value`, p.`login`
		LIMIT 100
	", CDB_PROP_LASTLOGONTIMESTAMP)))
	{
		foreach($result as &$row)
		{
			$table .= '<tr><td>'.$row['login'].'</td><td>'.name_join($row['lname'], $row['fname'], $row['mname']).'</td><td>'.$row['value'].'</td></tr>';
			$i++;
		}
	}

	echo 'Count: '.$i."\r\n";

	$table .= '</table>';

	$html .= $table;

	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-users-lastlogon">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if($i > 0)
	{
		if(php_mailer(REPORT_USERS_LASTLOGON_MAIL_TO, CDB_TITLE.': Report users last logon', $html, 'You client does not support HTML'))
		{
			echo 'Send mail: OK';
		}
		else
		{
			echo 'Send mail: FAILED';
		}
	}
