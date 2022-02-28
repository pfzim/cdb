<?php
	// Create new and close resolved tasks (empty password allowed)

	/**
		\file
		\brief Создание заявок по проблеме возможности установки пустого пароля на УЗ компьютера.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-passwd:\n";

	global $g_comp_flags;
	
	$limit = TASKS_LIMIT_EPWD;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`operid`, m.`opernum`, j1.`name`
		FROM @tasks AS m
		LEFT JOIN @computers AS j1
			ON j1.`id` = m.`pid`
		LEFT JOIN @properties_int AS j2
			ON
				j2.`tid` = {%TID_COMPUTERS}
				AND j2.`pid` = m.`pid`
				AND j2.`oid` = {%CDB_PROP_USERACCOUNTCONTROL}
		WHERE
			m.`tid` = {%TID_COMPUTERS}
			AND (m.`flags` & ({%TF_CLOSED} | {%TF_PASSWD})) = {%TF_PASSWD}
			AND (j1.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED}) OR (j2.`value` & 0x020) = 0)
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
					echo $row['name'].' '.$row['opernum']."\r\n";
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

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS m WHERE (m.`flags` & ({%TF_CLOSED} | {%TF_PASSWD})) = {%TF_PASSWD}")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
		SELECT m.`id`, m.`name`, m.`dn`, m.`flags`
		FROM @computers AS m
		LEFT JOIN @tasks AS j1
			ON j1.`tid` = {%TID_COMPUTERS}
			AND j1.`pid` = m.`id`
			AND (j1.`flags` & ({%TF_CLOSED} | {%TF_PASSWD})) = {%TF_PASSWD}
		LEFT JOIN @properties_int AS j2
			ON j2.`tid` = {%TID_COMPUTERS}
			AND j2.`pid` = m.`id`
			AND j2.`oid` = {%CDB_PROP_USERACCOUNTCONTROL}
		WHERE
			(m.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
			AND j2.`value` & 0x020
		GROUP BY m.`id`
		HAVING (BIT_OR(j1.`flags`) & {%TF_PASSWD}) = 0
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
				.'&Type=epwd'
				.'&To=sas'
				.'&Host='.urlencode($row['name'])
				.'&Message='.urlencode(
					'Требуется запретить установку пустого пароля у учётной записи.'
					."\nПК: ".$row['name']
					."\nИсточник информации о ПК: ".flags_to_string(intval($row['flags']) & CF_MASK_EXIST, $g_comp_flags, ', ')
					."\nКод работ: EPWD\n\n".WIKI_URL.'/Отдел%20ИТ%20Инфраструктуры.Сброс-флага-разрещающего-установить-пустой-пароль.ashx'
				)
			);
			if($answer !== FALSE)
			{
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_COMPUTERS}, #, {%TF_PASSWD}, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}
			}
		}
	}

	echo 'Created: '.$i."\r\n";
