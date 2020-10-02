<?php
	// Retrieve information from TMAO database
	
	// As I can see:
	//   SCAN_TYPE   = 1 - Smart scan, 0 - Conventional scan
	//   SCRIPT_PTN  = Smart scan pattern version
	//   PTNFILE     = Conventional scan pattern version

	/**
		\file
		\brief Синхронизация с БД TMAO.
		
		Загрузка информации о версии антивирусных баз и выявленных блокировках ПО с помощью Application Control.
		По выявленным блокировкам загружаются:
			- путь к заблокированному файлу
			- командная сторка последнено запуска
			- хэш файла
			- дата последней попытки запуска
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-tmao:\n";

	$servers = array(
		TMAO_01_DB_HOST => array(
			'Database' =>				TMAO_01_DB_NAME,
			'UID' =>					TMAO_01_DB_USER,
			'PWD' =>					TMAO_01_DB_PASSWD,
			'ReturnDatesAsStrings' =>	true
		),
		TMAO_03_DB_HOST => array(
			'Database' =>				TMAO_03_DB_NAME,
			'UID' =>					TMAO_03_DB_USER,
			'PWD' =>					TMAO_03_DB_PASSWD,
			'ReturnDatesAsStrings' =>	true
		)
	);

	foreach($servers as $server => $params)
	{
		$conn = sqlsrv_connect($server, $params);
		if($conn === false)
		{
			print_r(sqlsrv_errors());
			exit;
		}

		$result = sqlsrv_query($conn, "
			SELECT [COMP_NAME]
				,[PTNUPDTIME]
				,[SCAN_TYPE]
				,[PTNFILE]
				,[SCRIPT_PTN]
				,[AS_PSTIME]
			FROM [".$params['Database']."].[dbo].[TBL_CLIENT_INFO] WHERE [CLIENTTYPE] = 0
			ORDER BY [SCRIPT_PTN], [PTNFILE]
		");

		$i = 0;
		while($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC))
		{
			//echo $row['COMP_NAME'].", ".$row['PTNUPDTIME'].", ".$row['SCRIPT_PTN'].", ".$row['AS_PSTIME']."\r\n";
			
			if(!empty($row['PTNUPDTIME']))
			{
				$ptnupdtime = $row['PTNUPDTIME'];
			}
			else
			{
				$ptnupdtime = '0000-00-00 00:00:00';
			}
			
			if(!empty($row['AS_PSTIME']))
			{
				$as_pstime = $row['AS_PSTIME'];
			}
			else
			{
				$as_pstime = '0000-00-00 00:00:00';
			}
			
			if(intval($row['SCAN_TYPE']) == 1)
			{
				$script_ptn = $row['SCRIPT_PTN'];
			}
			else
			{
				$script_ptn = $row['PTNFILE'];
			}
			
			$row_id = 0;
			if(!$db->select_ex($res, rpv("SELECT m.`id` FROM @computers AS m WHERE m.`name` = ! LIMIT 1", $row['COMP_NAME'])))
			{
				if($db->put(rpv("INSERT INTO @computers (`name`, `ao_ptnupdtime`, `ao_script_ptn`, `ao_as_pstime`, `flags`) VALUES (!, !, #, !, 0x0020)",
					$row['COMP_NAME'],
					$ptnupdtime,
					$script_ptn,
					$as_pstime
				)))
				{
					$row_id = $db->last_id();
				}
			}
			else
			{
				$row_id = $res[0][0];
				$db->put(rpv("UPDATE @computers SET `ao_ptnupdtime` = !, `ao_script_ptn` = #, `ao_as_pstime` = !, `flags` = ((`flags` & ~0x0008) | 0x0020) WHERE `id` = # LIMIT 1",
					$ptnupdtime,
					$script_ptn,
					$as_pstime,
					$row_id
				));
			}
			$i++;
		}

		echo 'Count ['.$server.']: '.$i."\n";
		
		sqlsrv_free_stmt($result);

		// Load Application Control logs

		$result = sqlsrv_query($conn, "
			SELECT
				[SLF_ClientHostName]
				,MAX([SLF_LogGenLocalDatetime]) AS last_event
				,[SLF_ApplicationPath]
				,[SLF_ApplicationFileHash]
				,[SLF_ApplicationProcessCommandline]
			FROM
				[iac].[DetectionLog]
			WHERE
				SLF_LogGenLocalDatetime > DATEADD(DAY, -7, GETDATE())
			GROUP BY
				[SLF_ClientHostName]
				,[SLF_ApplicationPath]
				,[SLF_ApplicationFileHash]
				,[SLF_ApplicationProcessCommandline]
			ORDER BY
				[SLF_ClientHostName]
				, last_event DESC
		");

		$i = 0;
		$client = NULL;
		$client_id = 0;

		while($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC))
		{
			if(preg_match('/'.TMAC_EXCLUDE_REGEX.'/i', $row['SLF_ApplicationPath']) === 0)
			{
				continue;
			}
			
			//echo $row['COMP_NAME'].", ".$row['PTNUPDTIME'].", ".$row['SCRIPT_PTN'].", ".$row['AS_PSTIME']."\r\n";
			if($client !== $row['SLF_ClientHostName'])
			{
				if($db->select_ex($res, rpv("
						SELECT
							c.`id`
						FROM @computers AS c
						WHERE
							c.`name` = !
						LIMIT 1
					",
					$row['SLF_ClientHostName']
				)))
				{
					$client = $row['SLF_ClientHostName'];
					$client_id = $res[0][0];
				}
				else
				{
					//echo 'Skip. Client '.$row['SLF_ClientHostName']." does not exist in table `computers`\n";
					continue;
				}
			}
			
			if(!$db->select_ex($res, rpv("
					SELECT
						al.`id`,
						al.`last`
					FROM @ac_log AS al
					WHERE
						al.`pid` = #
						AND al.`app_path` = !
						AND al.`hash` = !
					LIMIT 1
				",
				$client_id,
				$row['SLF_ApplicationPath'],
				$row['SLF_ApplicationFileHash']
			)))
			{
				if($db->put(rpv("
						INSERT INTO @ac_log (
							`pid`,
							`last`,
							`app_path`,
							`hash`,
							`cmdln`,
							`flags`
						)
						VALUES (#, !, !, !, !, 0x0000)
					",
					$client_id,
					$row['last_event'],
					$row['SLF_ApplicationPath'],
					$row['SLF_ApplicationFileHash'],
					$row['SLF_ApplicationProcessCommandline']
				)))
				{
					$row_id = $db->last_id();
				}
			}
			else
			{
				$row_id = $res[0][0];
				if(sql_date_cmp($row['last_event'], $res[0][1]) > 0)
				{
					$db->put(rpv("
							UPDATE @ac_log
							SET
								`last` = !,
								`cmdln` = !,
								`flags` = (`flags` & ~0x0002)
							WHERE
								`id` = #
							LIMIT 1
						",
						$row['last_event'],
						$row['SLF_ApplicationProcessCommandline'],
						$row_id
					));
					//echo 'UPDATE: '.$row['last_event'].' in szdb: '.$res[0][1].' == '.$c."\n";
				}
				//else
				//{
				//	echo 'Skip. Already exist newer: '.$row['last_event'].' in szdb: '.$res[0][1].' == '.$c."\n";
				//}
			}
			$i++;
		}

		echo 'Count AC ['.$server.']: '.$i."\n";

		sqlsrv_free_stmt($result);

		sqlsrv_close($conn);
	}
