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
	//$answer = '<?xml version="1.0" encoding="utf-8"? ><root><extAlert><event ref="c7db7df4-e063-11e9-8115-00155d420f11" date="2019-09-26T16:44:46" number="001437825" rule="" person=""/><query ref="" date="" number=""/><comment/></extAlert></root>';

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
