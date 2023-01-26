<?php
	// Report CMDB - Relations

	/**
		\file
		\brief Формирование списка активов не привызянных к сервисам
	*/


	if(!defined('Z_PROTECTED')) exit;

	echo PHP_EOL.'report-cmdb-relations:'.PHP_EOL;


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
	<h1>Список активов CMDB не имеющих связи с информационными системами</h1>
EOT;

	$table = '<table>';
	$table .= '<tr><th>Class</th><th>_id</th><th>Code</th><th>brlCIName</th><th>Description</th></tr>';

	$post_data = json_encode(array(
		'username'  => CMDB_LOGIN,
		'password'  => CMDB_PASS
	));

	$ch = curl_init(CMDB_URL.'/sessions?scope=service&returnId=true');

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json;'));
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

	$result = curl_exec($ch);
	curl_close($ch);

	if($result === FALSE)
	{
		echo 'ERROR: Login failed.'.PHP_EOL;
		return;
	}

	$result_json = @json_decode($result, true);
	
	if(!isset($result_json['success'])
		|| ($result_json['success'] != 1)
		|| !isset($result_json['data'])
	)
	{
		echo 'ERROR: Login failed.'.PHP_EOL;
		return;
	}

	//print_r($result_json);

	$sess_id = $result_json['data']['_id'];
	//echo 'SessionID: '. $sess_id.PHP_EOL;

	echo 'Loading relations...'.PHP_EOL;
	
	$classes = array(
		//'brlVPNService',
		//'brlService',
		'brlPhyServ',
		'brlVirtualSrv',
		'brlSBM'
	);

	foreach($classes as &$class_name)
	{
		$ch = curl_init(CMDB_URL.'/classes/'.$class_name.'/cards');

		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json;', 'Cmdbuild-authorization: '.$sess_id));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$result = curl_exec($ch);
		curl_close($ch);

		$result_json = @json_decode($result, true);
		
		if(!isset($result_json['success'])
			|| ($result_json['success'] != 1)
			|| !isset($result_json['data'])
		)
		{
			echo 'ERROR: Invalid answer from server!'.PHP_EOL;
			return;
		}
		
		$i = 0;
		foreach($result_json['data'] as &$card)
		{
			$ch = curl_init(CMDB_URL.'/classes/'.$class_name.'/cards/'.$card['_id'].'/relations');

			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json;', 'Cmdbuild-authorization: '.$sess_id));
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$result = curl_exec($ch);
			curl_close($ch);

			$result_json2 = @json_decode($result, true);
			
			if(!isset($result_json2['success'])
				|| ($result_json2['success'] != 1)
				|| !isset($result_json2['data'])
			)
			{
				echo 'ERROR: Invalid answer from server!'.PHP_EOL;
				return;
			}

			$relation_founded = FALSE;

			foreach($result_json2['data'] as &$rel)
			{
				if($rel['_type'] === 'brlVirtualCIIS')
				{
					$relation_founded = TRUE;
					break;
				}
			}

			if(!$relation_founded)
			{
				$table .= '<tr>';
				$table .= '<td>'.$class_name.'</td>';
				$table .= '<td>'.$card['_id'].'</td>';
				$table .= '<td>'.$card['Code'].'</td>';
				$table .= '<td>'.$card['brlCIName'].'</td>';
				$table .= '<td>'.$card['Description'].'</td>';
				$table .= '</tr>';
			}
			else
			{
				echo $class_name.' - relation founded!'.PHP_EOL;
			}

			$i++;
		}
	}

	echo 'Count: '.$i.PHP_EOL;

	$table .= '</table>';

	$html .= $table;
	$html .= '<br /><small><a href="'.CDB_URL.'/cdb.php?action=report-cmdb-relations">Сформировать отчёт заново</a></small>';
	$html .= '</body>';

	if(php_mailer(REPORT_CMDB_RELATIONS_MAIL_TO, CDB_TITLE.': Report CMDB Relations', $html, 'You client does not support HTML'))
	{
		echo 'Send mail: OK'.PHP_EOL;
	}
	else
	{
		echo 'Send mail: FAILED'.PHP_EOL;
	}
