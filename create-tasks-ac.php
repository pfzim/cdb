<?php
	// Create new and close resolved tasks (TMAO AC)

	/**
		\file
		\brief Создание заявок по выявленным блокировкам ПО
		
		Анализируются загруженные данные по блокировкам из БД TMAO. Проблемы группируются
		по имени ПК и создаётся в HelpDesk заявка на устранение проблемы.
		После закрытия наряда, проблема автоматически считается решенной.
		При появлении в логах аналогичной записи с более свежей датой, наряд будет создан вновь.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\ncreate-tasks-ac\n";

	$limit = TASKS_LIMIT_AC;

	global $g_comp_flags;

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT t.`id`, t.`operid`, t.`opernum`, c.`name`
		FROM @tasks AS t
		LEFT JOIN @computers AS c
			ON c.`id` = t.`pid`
		WHERE
			t.`tid` = {%TID_COMPUTERS}
			AND (t.`flags` & ({%TF_TMAC} | {%TF_CLOSED})) = {%TF_TMAC}
			AND c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})
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
					$db->put(rpv("UPDATE @ac_log SET `flags` = (`flags` | {%ALF_FIXED}) WHERE (`flags` & {%ALF_FIXED}) = 0 AND `pid` = #", $row['id']));
					$i++;
				}
			}
			//break;
		}
	}

	echo 'Closed that auto resolved: '.$i."\r\n";

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_TMAC})) = {%TF_TMAC}")))
	{
		$i = intval($result[0][0]);
	}
	
/*
	if($db->select_assoc_ex($result, rpv("
		SELECT
			al.`id`,
			c.`name`,
			c.`dn`,
			c.`flags`,
			al.`app_path`,
			al.`cmdln`
		FROM @ac_log AS al
		LEFT JOIN @tasks AS t ON
			t.`tid` = 4
			AND t.`pid` = al.`id`
			AND (t.`flags` & (0x0001 | 0x0080)) = 0x0080
		LEFT JOIN @computers AS c ON
			c.`id` = al.`pid`
		WHERE
			(c.`flags` & (0x0001 | 0x0002 | 0x0004)) = 0
			AND (al.`flags` & 0x0002) = 0
		GROUP BY al.`id`
		HAVING (BIT_OR(t.`flags`) & 0x0080) = 0
	")))
*/
	if($db->select_assoc_ex($result, rpv("
			SELECT
				c.`id`,
				c.`name`,
				-- c.`dn`,
				c.`flags`
			FROM @computers AS c
			LEFT JOIN @tasks AS t ON
				t.`tid` = {%TID_COMPUTERS}
				AND t.`pid` = c.`id`
				AND (t.`flags` & ({%TF_CLOSED} | {%TF_TMAC})) = {%TF_TMAC}
			LEFT JOIN @ac_log AS al ON
				al.`pid` = c.`id`
			WHERE
				(c.`flags` & ({%CF_AD_DISABLED} | {%CF_DELETED} | {%CF_HIDED})) = 0
				AND (al.`flags` & {%ALF_FIXED}) = 0
				AND c.`name` REGEXP {s0}
			GROUP BY c.`id`
			HAVING 
				(BIT_OR(t.`flags`) & {%TF_TMAC}) = 0
				-- AND (BIT_AND(al.`flags`) & {%ALF_FIXED}) = 0
				AND COUNT(al.`id`) > 0
		",
		CDB_REGEXP_OFFICES
	)))
	{
		foreach($result as &$row)
		{
			if($i >= $limit)
			{
				echo 'Limit reached: '.$limit."\r\n";
				break;
			}
			
			if($db->select_assoc_ex($ac_log, rpv("
				SELECT
					al.`id`,
					al.`app_path`,
					al.`cmdln`,
					al.`hash`,
					al.`last`,
					al.`flags`
				FROM @ac_log AS al
				WHERE
					al.`pid` = #
					AND (al.`flags` & {%ALF_FIXED}) = 0
			",
			$row['id'])))
			{
				$message = '';
				foreach($ac_log as &$ac_row)
				{
					$message .= 
						"\n\nПуть к заблокированному файлу: ".$ac_row['app_path']
						."\nHash: ".$ac_row['hash']
						."\nПроцесс запускавший файл: ".$ac_row['cmdln']
						."\nДата последнего запуска: ".$ac_row['last']
					;
				}

				$ch = curl_init(HELPDESK_URL.'/ExtAlert.aspx');

				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

				curl_setopt($ch, CURLOPT_POSTFIELDS,
					'Source=cdb'
					.'&Action=new'
					.'&Type=ac'
					.'&To=goo'
					//.'&Type=test'
					//.'&To=sas'
					.'&Host='.urlencode($row['name'])
					.'&Message='.urlencode(
						'Обнаружена попытка запуска ПО из запрещённого расположения. Удалите или переустановите ПО в Program Files.'
						."\nПК: ".$row['name']
						//."\nПуть к заблокированному файлу: ".$row['app_path']
						//."\nПроцесс запускавший файл: ".$row['cmdln']
						."\nИсточник информации о ПК: ".flags_to_string(intval($row['flags']) & CF_MASK_EXIST, $g_comp_flags, ', ')
						."\nКод работ: AC001\n\n"//.WIKI_URL.'/Департамент%20ИТ.ashx'
						."\n\nСписок обнаруженного и заблокированного ПО:".$message
					)
				);

				$answer = curl_exec($ch);

				if($answer === FALSE)
				{
					curl_close($ch);
					break;
				}
				
				$xml = @simplexml_load_string($answer);
				if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
				{
					//echo $answer."\r\n";
					echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
					$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_COMPUTERS}, #, {%TF_TMAC}, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
					$i++;
				}

				curl_close($ch);
			}
		}
	}

	echo 'Created: '.$i."\r\n";
