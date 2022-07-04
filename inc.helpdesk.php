function helpdesk_build_request($id, $params)
{
	global $templates_helpdesk_requests;

	if(!isset($templates_helpdesk_requests[$id]))
	{
		throw new Exception('ERROR: Unknown request ID');
	}
	
	$request = $templates_helpdesk_requests[$id];

	foreach($params as $key => $value)
	{
		$request = str_replace('%'.$key.'%', urlencode($value), $request);
	}
	
	return $request;
}

function helpdesk_api_request($post_data)
{
	$ch = curl_init(HELPDESK_URL.'/ExtAlert.aspx');

	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

	$answer = curl_exec($ch);

	if($answer === FALSE)
	{
		curl_close($ch);
		return FALSE;
	}

	return @simplexml_load_string($answer);
}
