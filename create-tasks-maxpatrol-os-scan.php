<?php
	// Create new and close resolved tasks (MaxPatrol scan OS)

	/**
		\file
		\brief Создание заявок на сканирование операционной системы на
		уязвимости MaxPatrol.
		
		Заявки выставляются, если сервер не сканировался (audit_time == NULL)
		и имеет root доступ
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo PHP_EOL.'create-tasks-maxpatrol-os-scan:'.PHP_EOL;

	$limit = get_config_int('tasks_limit_maxpatrol_audit_os');

	// Fill table with VM IDs. Required for `tasks` table
	$db->put(rpv("INSERT INTO @vm_id (`name`) SELECT `name` FROM @vm WHERE `name` NOT IN (SELECT `name` FROM @vm_id)"));

	// Close auto resolved tasks

	$i = 0;
	if($db->select_assoc_ex($result, rpv("
		SELECT
			t.`id`,
			t.`operid`,
			t.`opernum`,
			vi.`name`
		FROM @tasks AS t
		LEFT JOIN @vm_id AS vi
			ON vi.`id` = t.`pid`
		LEFT JOIN @vm AS vm
			ON vm.`name` = vi.`name`
		LEFT JOIN @maxpatrol AS mp
			ON mp.`name` = vi.`name`
		WHERE
			t.`tid` = {%TID_VM}
			AND t.`type` = {%TT_MAXPATROL_AUDIT}
			AND (t.`flags` & {%TF_CLOSED}) = 0
			AND (
				vm.`flags` & {%VMF_EXIST_CMDB} = 0
				OR mp.`audit_time` IS NOT NULL
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
				echo $row['name'].' '.$row['opernum']."\r\n";
				$db->put(rpv("UPDATE @tasks SET `flags` = (`flags` | {%TF_CLOSED}) WHERE `id` = # LIMIT 1", $row['id']));
				$i++;
			}
		}
	}

	echo 'Closed: '.$i."\r\n";

	// Open new tasks

	$i = 0;

	if($db->select_ex($result, rpv("SELECT COUNT(*) FROM @tasks AS t WHERE (t.`flags` & ({%TF_CLOSED} | {%TF_FAKE_TASK})) = 0 AND t.`type` = {%TT_MAXPATROL_AUDIT}")))
	{
		$i = intval($result[0][0]);
	}
	
	if($db->select_assoc_ex($result, rpv("
			SELECT
				vi.`id`,
				vm.`name`,
				DATE_FORMAT(mp.`audit_time`, '%d.%m.%Y %H:%i:%s') AS `audit_time`,
				vm.`cmdb_os`
			FROM @vm_id AS vi
			LEFT JOIN @vm AS vm
				ON vm.`name` = vi.`name`
			LEFT JOIN @maxpatrol AS mp
				ON mp.`name` = vi.`name`
				AND mp.`flags` & {%MPF_EXIST}
			LEFT JOIN @tasks AS t
				ON
				t.`tid` = {%TID_VM}
				AND t.`pid` = vi.`id`
				AND t.`type` = {%TT_MAXPATROL_AUDIT}
				AND (t.`flags` & {%TF_CLOSED}) = 0
			WHERE
				(vm.`flags` & ({%VMF_EXIST_CMDB} | {%VMF_HAVE_ROOT})) = ({%VMF_EXIST_CMDB} | {%VMF_HAVE_ROOT})
				AND mp.`audit_time` IS NULL
				AND vm.`cmdb_os` REGEXP 'win|linux|ubuntu|debian|centos'
			GROUP BY vi.`id`
			HAVING
				COUNT(t.`id`) = 0
		"
	)))
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
				.'&Type=vuln'
				.'&To=sas'
				.'&Host='.urlencode($row['name'])
				.'&Message='.helpdesk_message(
					TT_MAXPATROL_AUDIT,
					array(
						'host'			=> $row['name'],
						'os'			=> $row['cmdb_os']
					)
				)
			);

			if($xml !== FALSE && !empty($xml->extAlert->query['ref']))
			{
				echo $row['name'].' '.$xml->extAlert->query['number']."\r\n";
				$db->put(rpv("INSERT INTO @tasks (`tid`, `pid`, `type`, `flags`, `date`, `operid`, `opernum`) VALUES ({%TID_VM}, #, {%TT_MAXPATROL_AUDIT}, 0, NOW(), !, !)", $row['id'], $xml->extAlert->query['ref'], $xml->extAlert->query['number']));
				$i++;
			}
		}
	}

	echo 'Created: '.$i."\r\n";
