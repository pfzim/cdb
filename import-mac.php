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

	$line = strtok($_POST['list'], "\n");
	while($line !== FALSE)
	{
		$i++;
		
		$line = trim($line);
		
		if(!empty($line))
		{
			$row = explode(',', $line);
			if(count($row) != 4)
			{
				$code = 1;
				$error_msg .= 'Warning: Incorrect format. Line '.$i.';';

				$line = strtok("\n");
				continue;
			}

			$db->start_transaction();

			$dev_id = 0;
			if($db->select_ex($result, rpv("SELECT m.`id` FROM @devices AS m WHERE m.`name` = ! LIMIT 1", $_POST['netdev'])))
			{
				$dev_id = $result[0][0];
			}
			else
			{
				if($db->put(rpv("INSERT INTO @devices (`type`, `name`, `flags`) VALUES (3, !, 0)",
					$_POST['netdev']
				)))
				{
					$dev_id = $db->last_id();
				}
			}

			$mac = strtolower(str_replace(array(':', '.', ' '), '', $row[0]));

			$row_id = 0;
			if(!$db->select_ex($result, rpv("SELECT m.`id` FROM @mac AS m WHERE m.`mac` = ! LIMIT 1", $mac)))
			{
				if($db->put(rpv("INSERT INTO @mac (`pid`, `name`, `mac`, `ip`, `port`, `date`, `flags`) VALUES (#, !, !, !, !, NOW(), #)",
					$dev_id,
					$row[1],
					$mac,
					$row[2],
					$row[3],
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
					$dev_id,
					$row[1],
					$row[2],
					$row[3],
					0x0020
				));
			}

			$db->commit();
		}

		$line = strtok("\n");
	}

	echo '{"code": '.$code.', "message": "'.$error_msg.'"}';
