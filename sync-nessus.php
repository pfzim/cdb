<?php
	// Retrieve information from Nessus

	/**
		\file
		\brief Получение информации из Nessus о выявленных уязвимостях
		
		\todo Сравнивать дату сканирования. Если дата сканирования не новее, то не обновлять запись в vuln_scans. Done.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nsync-nessus:\n";

	$i = 0;
	$hosts_list = array();

	$ch = curl_init(NESSUS_URL.'/scans');

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-ApiKeys: accessKey='.NESSUS_ACCESS_KEY.'; secretKey='.NESSUS_SECRET_KEY.';'));
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$result = curl_exec($ch);
	curl_close($ch);

	if($result !== FALSE)
	{
		
		$scans_list = @json_decode($result, true);
		
		foreach($scans_list['scans'] as &$scan)
		{
			if(in_array($scan['folder_id'], NESSUS_FOLDERS_IDS))
			{
				echo "\r\n".$scan['name']."\r\n\r\n";

				$ch = curl_init(NESSUS_URL.'/scans/'.$scan['id']);

				curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-ApiKeys: accessKey='.NESSUS_ACCESS_KEY.'; secretKey='.NESSUS_SECRET_KEY.';'));
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

				$result = curl_exec($ch);
				curl_close($ch);
				
				if($result !== FALSE)
				{
					$scans = @json_decode($result, true);

					if($scans['info']['status'] == 'completed')
					{
						$scan_date = date('Y-m-d H:i:s', $scans['info']['scan_end']);
						
						// Формируем список хостов

						foreach($scans['hosts'] as &$host)
						{
							//echo '  '.$host['host_id'].': '.$host['hostname']."\r\n";
							
							$host_id = strval($host['host_id']);
							
							if(!isset($hosts_list[$host_id]))
							{
								$hosts_list[$host_id] = array(
									'host_name' => $host['hostname'],
									'scans' => array()
								);
							}

							$hosts_list[$host_id]['scans'][] = array(
								'folder_id' => $scan['folder_id'],
								'id' => $scan['id'],
								'date' => $scan_date
							);
						}
					}
				}
				else
				{
					echo 'ERROR'."\r\n\r\n"	;
				}
			}
		}

		unset($scan_list);
		unset($result);

		foreach($hosts_list as $host_id => &$host_info)
		{
			foreach($host_info['scans'] as &$scan)
			{
				echo '  '.$host_id.': '.$host_info['host_name']."\r\n";

				$device_id = 0;
				if($db->select_ex($res, rpv("SELECT d.`id` FROM @devices AS d WHERE d.type = {%DT_VULN_HOST} AND d.`name` = ! LIMIT 1", $host_info['host_name'])))
				{
					$device_id = $res[0][0];
				}
				else
				{
					if($db->put(rpv("INSERT INTO @devices (`type`, `pid`, `name`, `flags`) VALUES ({%DT_VULN_HOST}, 0, !, 0)", $host_info['host_name'])))
					{
						$device_id = $db->last_id();
					}
				}

				if($device_id)
				{
					$ch = curl_init(NESSUS_URL.'/scans/'.$scan['id'].'/hosts/'.$host_id);

					curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-ApiKeys: accessKey='.NESSUS_ACCESS_KEY.'; secretKey='.NESSUS_SECRET_KEY.';'));
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

					$result = curl_exec($ch);
					curl_close($ch);

					if($result !== FALSE)
					{
						// Помечаем предыдущие найденные уязвимости перед загрузкой

						$db->put(rpv("
								UPDATE
									@vuln_scans
								SET
									`flags` = (`flags` | {%VSF_FIXED})     -- set Fixed flag
								WHERE
									`pid` = #
									AND (`flags` & {%VSF_FIXED}) = 0
							",
							$device_id
						));

						$host_details = @json_decode($result, true);
						foreach($host_details['vulnerabilities'] as &$vuln)
						{
							if(intval($vuln['severity']) > 0)
							{
								//echo '    '.$vuln['severity_index'].' '.$vuln['severity'].' '.' '.$vuln['plugin_id'].' '.$vuln['plugin_name']."\r\n"; flush();

								if(!$db->select_ex($res, rpv("SELECT v.`plugin_id` FROM @vulnerabilities AS v WHERE v.`plugin_id` = # LIMIT 1", $vuln['plugin_id'])))
								{
									$db->put(rpv("
											INSERT INTO @vulnerabilities (`plugin_id`, `plugin_name`, `severity`, `flags`)
											VALUES ( #, !, #, #)
										",
										$vuln['plugin_id'],
										$vuln['plugin_name'],
										$vuln['severity'],
										0x0000
									));
								}
								/* update if needed
								else
								{
									$db->put(rpv("
											UPDATE
												@vulnerabilities
											SET
												`plugin_name` = !,
												`severity` = #,
											WHERE
												`plugin_id` = #
											LIMIT 1
										",
										$vuln['plugin_name'],
										$vuln['severity'],
										$vuln['plugin_id']
									));
								}
								*/

								if(!$db->select_ex($res, rpv("SELECT s.`id`, s.`scan_date` FROM @vuln_scans AS s WHERE s.`pid` = # AND s.`plugin_id` = # LIMIT 1", $device_id, $vuln['plugin_id'])))
								{
									$db->put(rpv("
											INSERT INTO @vuln_scans (`pid`, `plugin_id`, `scan_date`, `folder_id`, `flags`)
											VALUES (#, #, !, #, #)
										",
										$device_id,
										$vuln['plugin_id'],
										$scan['date'],
										$scan['folder_id'],
										0x0000
									));
								}
								else
								{
									if(sql_date_cmp($scan_date, $res[0][1]) > 0)
									{
										$db->put(rpv("
												UPDATE
													@vuln_scans
												SET
													`scan_date` = !,
													`folder_id` = #,
													`flags` = (`flags` & ~{%VSF_FIXED})     -- reset Fixed flag
												WHERE
													`id` = #
												LIMIT 1
											",
											$scan['date'],
											$scan['folder_id'],
											$res[0][0]
										));
									}
								}
								
								$i++;
							}
						}
					}
					else
					{
						echo 'ERROR'."\r\n\r\n"	;
					}
				}
			}
		}
	}
	else
	{
		echo 'ERROR'."\r\n\r\n"	;
	}

	echo 'Count: '.$i."\n";
