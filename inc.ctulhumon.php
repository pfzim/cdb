<?php
/**
	\file
	\brief Файл с функциями для сервиса CtulhuMon
*/

function new_guid(): string {
	return sprintf(
		'%04X%04X-%04X-%04X-%04X-%04X%04X%04X', 
		mt_rand(0, 65535), 
		mt_rand(0, 65535), 
		mt_rand(0, 65535), 
		mt_rand(16384, 20479), 
		mt_rand(32768, 49151), 
		mt_rand(0, 65535), 
		mt_rand(0, 65535), 
		mt_rand(0, 65535)
	);
}

function call_json_zabbix(string $in_method, $in_auth, array $in_params) {
	$message = json_encode( array(
		'jsonrpc' => '2.0', 
		'id' => new_guid(), 
		'method' => $in_method, 
		'params' => $in_params,
		'auth' => $in_auth
	));
	//DEBUG
	//echo "Initial RPC:\r\n";
	//var_dump($message); echo "\r\n";
	$ch = curl_init(ZABBIX_URL.'/api_jsonrpc.php');
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json;'));
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $message);

	$result = curl_exec($ch);
	curl_close($ch);

	if($result !== FALSE) {
		$rdecoded = json_decode($result, TRUE);
		if(array_key_exists('error',$rdecoded)) {
			echo "ERROR:\r\n";
			var_dump($rdecoded['error']);
		} elseif(!array_key_exists('result',$rdecoded)) {
			echo "ERROR: RPC result format unexpected\r\n";
			var_dump($rdecoded);
		} else {
			return $rdecoded['result'];
		}
	}
	return null;
}

function zabbix_trigger_id(array $var) {
	return in_array($var['templateid'], ZABBIX_Template_Array);
}

