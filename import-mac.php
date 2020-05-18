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

	$i = 0;
	
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

	error_log(date('c').'  Start import from device: '.$net_dev." List:\n".$_POST['list']."\n", 3, '/var/log/cdb/import-mac.log');
	$line = strtok($_POST['list'], "\n");
	while($line !== FALSE)
	{
		$i++;
		
		$line = trim($line);
		
		if(!empty($line))
		{
			$row = explode(',', $line);  // format: mac,name,ip,sw_id,port
			if(count($row) != 5)
			{
				$code = 1;
				$error_msg .= 'Warning: Incorrect format. Line '.$i.';';
				
				error_log(date('c').'  Warning: Incorrect format ('.$i.'): '.$line."\n", 3, '/var/log/cdb/import-mac.log');

				$line = strtok("\n");
				continue;
			}

			$mac = strtolower(preg_replace('/[^0-9a-f]/i', '', $row[0]));

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

			$row_id = 0;
			if(!$db->select_ex($result, rpv("SELECT m.`id` FROM @mac AS m WHERE m.`mac` = ! LIMIT 1", $mac)))
			{
				if($db->put(rpv("INSERT INTO @mac (`pid`, `name`, `mac`, `ip`, `port`, `date`, `flags`) VALUES (#, !, !, !, !, NOW(), #)",
					$pid,
					$row[1],
					$mac,
					$row[2],
					$row[4],
					0x0020
				)))
				{
					$row_id = $db->last_id();
				}
			}
			else
			{
				$row_id = $result[0][0];
				$db->put(rpv("UPDATE @mac SET `pid` = #,`name` = !, `ip` = !, `port` = !, `date` = NOW(), `flags` = (`flags` | #) WHERE `id` = # LIMIT 1",
					$pid,
					$row[1],
					$row[2],
					$row[4],
					0x0020
				));
			}
		}

		$line = strtok("\n");
	}

	echo '{"code": '.$code.', "message": "'.$error_msg.'"}';
