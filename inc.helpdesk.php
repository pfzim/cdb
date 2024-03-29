<?php

function helpdesk_message($id, $params)
{
	global $template_helpdesk_messages;

	if(!isset($template_helpdesk_messages[$id]))
	{
		throw new Exception('ERROR: Unknown request ID');
	}

	$message = $template_helpdesk_messages[$id];

	foreach($params as $key => $value)
	{
		$message = str_replace('%'.$key.'%', $value, $message);
	}

	return urlencode($message);
}

function helpdesk_api_request($post_data)
{
	//$answer = '<?xml version="1.0" encoding="utf-8"? ><root><extAlert><event ref="c7db7df4-e063-11e9-8115-00155d420f11" date="2019-09-26T16:44:46" number="001437825" rule="" person=""/><query ref="" date="" number=""/><comment/></extAlert></root>';

	$ch = curl_init(HELPDESK_URL.'/ExtAlert.aspx');

	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

	$answer = curl_exec($ch);

	$xml = FALSE;

	if($answer !== FALSE)
	{
		$xml = @simplexml_load_string($answer);
	}

	curl_close($ch);

	return $xml;
}
