<?php
	// Create new and close resolved tasks (TMEE)

	/**
		\file
		\brief Создание заявок на шифрование ноутбука (TMEE).
		
		Завки создаются, если ноутбук находится в статусе не зашифрован.
		Шаблон именования хранится в константе CDB_REGEXP_NOTEBOOK_NAME.
	*/

	/*
		1. Сбор информации - закрытие заявок, если статус изменился на ОК
		2. Создание заявок
		3. Проверка статуса заявок. Если заявка закрыта, но статус не ОК, то эскалация.

		SET @current_pattern = (SELECT MAX(ao_script_ptn) FROM c_computers) - 200;
		SELECT @current_pattern;
		SELECT * FROM c_computers WHERE ao_script_ptn < @current_pattern AND ao_script_ptn <> 0 OR (name regexp '^[[:digit:]]{4}-[nN][[:digit:]]+' AND ee_encryptionstatus <> 2);

		SELECT name, ao_script_ptn, ee_encryptionstatus FROM c_computers WHERE ao_script_ptn < (SELECT MAX(ao_script_ptn) FROM c_computers) - 200 AND ao_script_ptn <> 0 OR (name regexp '[[:digit:]]{4}-[nN][[:digit:]]+' AND ee_encryptionstatus <> 2)

		TMME:

		SELECT *
		  FROM c_computers
		  WHERE name regexp '^[[:digit:]]{4}-[nN][[:digit:]]+'
		    AND (`flags` & (0x0001 | 0x0100)) = 0
		    AND (ee_encryptionstatus <> 2 OR ee_lastsync < DATE_SUB(NOW(), INTERVAL 2 WEEK));
	*/


	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-tmee:\n";

	global $g_comp_flags;

	// Close auto resolved tasks

	$i = 0;
	//if($db->select_assoc_ex($result, rpv("SELECT * FROM @computers WHERE (`flags` & {%TF_TMEE}) AND `name` regexp '^[[:digit:]]{4}-[nN][[:digit:]]+' AND ((`ee_encryptionstatus` = 2 AND `ee_lastsync` >= DATE_SUB(NOW(), INTERVAL 2 WEEK)) OR (`flags` & (0x0001 | 0x0004)))")))
	if($db->select_assoc_ex($result, rpv("
		SELECT t.`id`, t.`operid`, t.`opernum`, c.`name`
		FROM @tasks AS t
		LEFT JOIN @computers AS c
			ON c.`id` = t.`pid`
		WHERE
			t.`tid` = {%TID_COMPUTERS}
			AND t.`type` = {%TT_TMEE}
			AND (t.`flags` & {%TF_CLOSED}) = 0
			AND (c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED}) OR c.`ee_encryptionstatus` = 2)
	")))
	{
		foreach($result as &$row)
		{
			$xml = helpdesk_api_request(helpdesk_build_request(
				TT_CLOSE,
				array(
					'operid'	=> $row['operid'],
					'opernum'	=> $row['opernum']
				)
			));

			if($xml !== FALSE)
			{
				echo $row['name'].' '.$row['opernum']."\r\n";
				$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | {%TF_CLOSED}) WHERE `id` = # LIMIT 1", $row['id']));
				$i++;
			}
		}
	}

	echo 'Closed: '.$i."\r\n";

	// Open new tasks

	$i = 0;
	//if($db->select_assoc_ex($result, rpv("SELECT * FROM @computers WHERE `name` regexp '^[[:digit:]]{4}-[nN][[:digit:]]+' AND (`flags` & (0x0001 | 0x0100 | 0x0004 | 0x0002)) = 0 AND (`ee_encryptionstatus` <> 2 OR `ee_lastsync` < DATE_SUB(NOW(), INTERVAL 2 WEEK))")))
	if($db->select_assoc_ex($result, rpv("
			SELECT c.`id`, c.`name`, c.`dn`, c.`ee_encryptionstatus`, c.`flags`
			FROM @computers AS c
			LEFT JOIN @tasks AS t
				ON
					t.`tid` = {%TID_COMPUTERS}
					AND t.pid = c.id
					AND t.`type` = {%TT_TMEE}
					AND (t.`flags` & {%TF_CLOSED}) = 0
			WHERE
				(c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
				AND c.`delay_checks` < CURDATE()
				AND c.`ee_encryptionstatus` <> 2
				AND c.`name` regexp {s0}
			GROUP BY c.`id`
			HAVING
				COUNT(t.`id`) = 0
		",
		CDB_REGEXP_NOTEBOOK_NAME
	)))
	{
		foreach($result as &$row)
		{
			$xml = helpdesk_api_request(helpdesk_build_request(
				TT_TMEE,
				array(
					'host'					=> $row['name'],
					'ee_encryptionstatus'	=> tmee_status(intval($row['ee_encryptionstatus'])),
					'flags'					=> flags_to_string(intval($row['flags']) & CF_MASK_EXIST, $g_comp_flags, ', ')
				)
			));

			if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
			{
				echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
				$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_COMPUTERS}, #, {%TT_TMEE}, 0, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
				$i++;
			}
		}
	}

	echo 'Created: '.$i."\r\n";
