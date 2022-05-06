<?php
	// Create new and close resolved tasks (mailbox is unlimited)

	/**
		\file
		\brief Создание заявок по проблеме безлимитного почтового ящика.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-mbx-unlim:\n";

	$limit = TASKS_LIMIT_MBX;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT t.`id`, t.`operid`, t.`opernum`, p.`login`
		FROM @tasks AS t
		LEFT JOIN @persons AS p
			ON p.`id` = t.`pid`
		LEFT JOIN @properties_int AS j_quota
			ON j_quota.`tid` = {%TID_PERSONS}
			AND j_quota.`pid` = t.`pid`
			AND j_quota.`oid` = {%CDB_PROP_MAILBOX_QUOTA}
		WHERE
			t.`tid` = {%TID_PERSONS}
			AND t.`type` = {%TT_MBOX_UNLIM}
			AND (t.`flags` & {%TF_CLOSED}) = 0
			AND (p.`flags` & ({%PF_AD_DISABLED} | {%PF_DELETED} | {%PF_HIDED}) OR (j_quota.`value` <> 0))
	")))
	{
		foreach($result as &$row)
		{
			$answer = @file_get_contents(
				HELPDESK_URL.'/ExtAlert.aspx/'
				.'?Source=cdb'
				.'&Action=resolved'
				.'&Id='.urlencode($row['operid'])
				.'&Num='.urlencode($row['opernum'])
				.'&Message='.urlencode("Заявка более не актуальна. Закрыта автоматически")
			);

			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE)
				{
					//echo $answer."\r\n";
					echo $row['login'].' '.$row['opernum']."\r\n";
					$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | {%TF_CLOSED}) WHERE `id` = # LIMIT 1", $row['id']));
					$i++;
				}
			}
			//break;
		}
	}

	echo 'Closed: '.$i."\r\n";

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS m WHERE (m.`flags` & {%TF_CLOSED}) = 0 AND t.`type` = {%TT_MBOX_UNLIM}")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
		SELECT p.`id`, p.`login`, p.`dn`, p.`flags`
		FROM @persons AS p
		LEFT JOIN @tasks AS t
			ON t.`tid` = {%TID_PERSONS}
			AND t.`pid` = p.`id`
			AND t.`type` = {%TT_MBOX_UNLIM}
			AND (t.`flags` & {%TF_CLOSED}) = 0
		LEFT JOIN @properties_int AS j_quota
			ON j_quota.`tid` = {%TID_PERSONS}
			AND j_quota.`pid` = p.`id`
			AND j_quota.`oid` = {%CDB_PROP_MAILBOX_QUOTA}
		WHERE
			(p.`flags` & ({%PF_AD_DISABLED} | {%PF_DELETED} | {%PF_HIDED})) = 0
			AND j_quota.`value` = 0
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
			
			$answer = @file_get_contents(
				HELPDESK_URL.'/ExtAlert.aspx/'
				.'?Source=cdb'
				.'&Action=new'
				.'&Type=test'
				.'&To=goo'
				.'&Host='.urlencode($row['login'])
				.'&Message='.urlencode(
					'Не настроена квота на почтовом ящике пользователя. Установите квоту.'
					."\nУЗ: ".$row['login']
					."\nКод работ: MBXQ"
					//."\n\n".WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Сброс-флага-разрещающего-установить-пустой-пароль.ashx'
				)
			);
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['login'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_PERSONS}, #, {%TT_MBOX_UNLIM}, {%TF_MBOX_UNLIM}, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
		}
	}

	echo 'Created: '.$i."\r\n";
