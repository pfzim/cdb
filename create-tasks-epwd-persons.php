<?php
	// Create new and close resolved tasks (empty password allowed)

	/**
		\file
		\brief Создание заявок по проблеме возможности установки пустого пароля на УЗ пользователя.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-epwd-persons:\n";
	
	$limit = TASKS_LIMIT_EPWD_PERSON;

	global $g_comp_flags;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT t.`id`, t.`operid`, t.`opernum`, p.`login`
		FROM @tasks AS t
		LEFT JOIN @persons AS p
			ON p.`id` = t.`pid`
		LEFT JOIN @properties_int AS uac
			ON uac.`tid` = {%TID_PERSONS}
			AND uac.`pid` = t.`pid`
			AND uac.`oid` = {%CDB_PROP_USERACCOUNTCONTROL}
		WHERE
			t.`tid` = {%TID_PERSONS}
			AND t.`type` = {%TT_PASSWD}
			AND (t.`flags` & {%TF_CLOSED}) = 0
			AND (
				p.`flags` & ({%PF_AD_DISABLED} | {%PF_DELETED} | {%PF_HIDED})
				OR (uac.`value` & 0x020) = 0
			)
	")))
	{
		foreach($result as &$row)
		{
			$xml = helpdesk_api_request(
				'Source=cdb'
				.'&Action=resolved'
				.'&Id='.urlencode($row['operid'])
				.'&Num='.urlencode($row['opernum'])
				.'&Message='.helpdesk_message(
					TT_CLOSE,
					array(
						'operid'	=> $row['operid'],
						'opernum'	=> $row['opernum']
					)
				)
			);

			if($xml !== FALSE)
			{
				echo $row['login'].' '.$row['opernum']."\r\n";
				$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | {%TF_CLOSED}) WHERE `id` = # LIMIT 1", $row['id']));
				$i++;
			}
		}
	}

	echo 'Closed: '.$i."\r\n";

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS t WHERE t.`type` = {%TT_PASSWD} AND (t.`flags` & {%TF_CLOSED}) = 0")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
		SELECT p.`id`, p.`login`, p.`dn`, p.`flags`
		FROM @persons AS p
		LEFT JOIN @tasks AS t
			ON t.`tid` = {%TID_PERSONS}
			AND t.`pid` = p.`id`
			AND t.`type` = {%TT_PASSWD}
			AND (t.`flags` & {%TF_CLOSED}) = 0
		LEFT JOIN @properties_int AS uac
			ON uac.`tid` = {%TID_PERSONS}
			AND uac.`pid` = p.`id`
			AND uac.`oid` = {%CDB_PROP_USERACCOUNTCONTROL}
		WHERE
			(p.`flags` & ({%PF_AD_DISABLED} | {%PF_DELETED} | {%PF_HIDED})) = 0
			AND uac.`value` & 0x020
		GROUP BY p.`id`
		HAVING
			COUNT(t.`id`) = 0
	")))
	{
		foreach($result as &$row)
		{
			if($i >= $limit)
			{
				echo 'Limit reached: '.$limit."\r\n";
				break;
			}
			
			$xml = helpdesk_api_request(
				'Source=cdb'
				.'&Action=new'
				.'&Type=epwd'
				.'&To=sas'
				.'&Host='.urlencode($row['login'])
				.'&Message='.helpdesk_message(
					TT_PASSWD,
					array(
						'host'			=> $row['login'],
						'flags'			=> flags_to_string(intval($row['flags']) & CF_MASK_EXIST, $g_comp_flags, ', ')
					)
				)
			);

			$xml = @simplexml_load_string($answer);
			if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
			{
				echo $row['login'].' '.$xml->extAlert->query['number']."\r\n";
				$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_PERSONS}, #, {%TT_PASSWD}, 0, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
				$i++;
			}
		}
	}

	echo 'Created: '.$i."\r\n";
