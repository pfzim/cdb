<?php
/**
	\file
	\brief Файл с функциями для работы с Zabbix API
*/

function zabbix_api_request(string $in_method, $in_auth, array $in_params, int $id = 1, bool $throw_exception = TRUE)
{
	$post_data = json_encode(array(
		'jsonrpc' => '2.0',
		'id'      => $id,
		'auth'    => $in_auth,
		'method'  => $in_method,
		'params'  => $in_params
	));
	
	$ch = curl_init(ZABBIX_URL.'/api_jsonrpc.php');

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json;'));
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

	$result = curl_exec($ch);
	curl_close($ch);

	if($result !== FALSE)
	{
		$rdecoded = json_decode($result, TRUE);
		if(array_key_exists('error', $rdecoded))
		{
			if($throw_exception)
			{
				throw new Exception('Zabbix: ERROR: '.json_encode($rdecoded['error'], JSON_UNESCAPED_UNICODE));
			}

			//echo "ERROR:\r\n";
			//var_dump($rdecoded['error']);
		}
		elseif(!array_key_exists('result',$rdecoded))
		{
			if($throw_exception)
			{
				throw new Exception('Zabbix: ERROR: RPC result format unexpected');
			}
			
			//echo "ERROR: RPC result format unexpected\r\n";
			//var_dump($rdecoded);
		}
		else
		{
			return $rdecoded['result'];
		}
	}

	return NULL;
}
