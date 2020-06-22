<?php
	// Import MAC addresses

	if(!defined('Z_PROTECTED')) exit;

	if(empty($_POST['netdev']))
	{
		echo '{"code": 1, "message": "Error: netdev undefined"}';
		exit;
	}

	if(empty($_POST['list']))
	{
		echo '{"code": 1, "message": "Error: list undefined"}';
		exit;
	}

	$code = 0;
	$error_msg = '';

	$pid = 0;
	$last_sw_id = '';
	
	$net_dev = $_POST['netdev'];
	$dev_id = 0;
	if($db->select_ex($result, rpv("SELECT m.`id` FROM @devices AS m WHERE m.`type` = 3 AND m.`name` = ! LIMIT 1", $net_dev)))
	{
		$dev_id = $result[0][0];
	}
	else
	{
		if($db->put(rpv("INSERT INTO @devices (`type`, `name`, `flags`) VALUES (3, !, 0)", $net_dev)))
		{
			$dev_id = $db->last_id();
		}
	}

	$line_no = 0;
	
	error_log("\n".date('c').'  Start import from device: '.$net_dev." List:\n".$_POST['list']."\n", 3, '/var/log/cdb/import-mac.log');
	$line = strtok($_POST['list'], "\n");
	while($line !== FALSE)
	{
		$line_no++;
		
		$line = trim($line);
		
		if(!empty($line))
		{
			$row = explode(',', $line);  // format: mac,name,ip,sw_id,port
			if(count($row) != 5)
			{
				$code = 1;
				$error_msg .= 'Warning: Incorrect line format. Line '.$line_no.';';
				
				error_log(date('c').'  Warning: Incorrect line format ('.$line_no.'): '.$line."\n", 3, '/var/log/cdb/import-mac.log');

				$line = strtok("\n");
				continue;
			}

			$is_sn = false;
			if(preg_match('/^[0-9a-f]{4}\\.[0-9a-f]{4}\\.[0-9a-f]{4}$/i', $row[0]))
			{
				$mac = strtolower(preg_replace('/[^0-9a-f]/i', '', $row[0]));
			}
			else
			{
				$is_sn = true;
				$mac = strtoupper(preg_replace('/[-:;., ]/i', '', $row[0]));
			}

			if(empty($mac) || (!$is_sn && strlen($mac) != 12))
			{
				$code = 1;
				$error_msg .= 'Warning: Invalid MAC. Line '.$line_no.';';

				error_log(date('c').'  Warning: Invalid MAC ('.$line_no.'): '.$line."\n", 3, '/var/log/cdb/import-mac.log');

				$line = strtok("\n");
				continue;
			}

			if($row[3] === $net_dev)
			{
				$last_sw_id = $net_dev;
				$pid = $dev_id;
			}
			else if($last_sw_id !== $row[3])
			{
				$pid = 0;
				$last_sw_id = $row[3];
				if($db->select_ex($result, rpv("SELECT m.`id` FROM @devices AS m WHERE m.`type` = 3 AND m.`name` = ! LIMIT 1", $row[3])))
				{
					$pid = $result[0][0];
				}
				else
				{
					if($db->put(rpv("INSERT INTO @devices (`type`, `pid`, `name`, `flags`) VALUES (3, #, !, 0)", $dev_id, $row[3])))
					{
						$pid = $db->last_id();
					}
				}
			}
			
			$excluded = 0x0000;
			
			if(
				!$is_sn
				&& (
					(
						preg_match('/'.NETDEV_SHOPS_REGEX.'/i', $last_sw_id)
						&& preg_match('#'.NETDEV_EXCLUDE_SHOPS_PORT.'#i', $row[4])
					) || (
						preg_match('/'.NETDEV_TOF_REGEX.'/i', $last_sw_id)
						&& preg_match('#'.NETDEV_EXCLUDE_TOF_PORT.'#i', $row[4])
					)
				)
				&& (preg_match('/'.MAC_EXCLUDE_REGEX.'/i', $mac) === 0)
			)
			{
				$excluded = 0x0002;
				error_log(date('c').'  MAC excluded: '.$mac."\n", 3, '/var/log/cdb/import-mac.log');
			}

			$row_id = 0;
			if(!$db->select_ex($result, rpv("SELECT m.`id` FROM @mac AS m WHERE m.`mac` = ! AND ((`flags` & 0x0080) = #) LIMIT 1", $mac, $is_sn ? 0x0080 : 0x0000 )))
			{
				if($db->put(rpv("INSERT INTO @mac (`pid`, `name`, `mac`, `ip`, `port`, `date`, `flags`) VALUES (#, !, !, !, !, NOW(), #)",
					$pid,
					$row[1],  // name
					$mac,
					$row[2],  // ip
					$row[4],  // port
					0x0020 | $excluded | ($is_sn ? 0x0080 : 0x0000)
				)))
				{
					$row_id = $db->last_id();
				}
			}
			else
			{
				$row_id = $result[0][0];
				$db->put(rpv("UPDATE @mac SET `pid` = #,`name` = !, `ip` = !, `port` = !, `date` = NOW(), `flags` = ((`flags` & ~0x0002) | #) WHERE `id` = # LIMIT 1",
					$pid,
					$row[1],  // name
					$row[2],  // ip
					$row[4],  // port
					0x0020 | $excluded,
					$row_id
				));
			}
		}

		$line = strtok("\n");
	}

	echo '{"code": '.$code.', "message": "'.$error_msg.'"}';
