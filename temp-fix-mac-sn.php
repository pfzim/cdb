<?php
	// TEST SCRIPT
	
	if(!defined('Z_PROTECTED')) exit;

	echo "\nTEMP FIX MAC-SN:\n";

	if($db->select_ex($result, rpv("SELECT ms.`sn`, ms.`mac` FROM @mac_sn AS ms")))
	{
		foreach($result as &$row)
		{
			$sn = $row[0];
			$mac = $row[1];
			
			// Проверяем существование SN в таблице mac
			if($db->select_ex($result2, rpv("SELECT m.`id` FROM @mac AS m WHERE m.`mac` = ! AND m.`flags` & 0x0080 LIMIT 1", $sn)))
			{
				// Если запись с таким SN уже существует, то помечаем дубликат с таким MAC как временно исключенный.
				if($db->put(rpv("UPDATE @mac SET `flags` = (`flags` | 0x0002) WHERE `mac` = ! AND (`flags` & (0x0002 | 0x0004)) = 0 LIMIT 1", $mac), $uprows))
				{
					if($uprows > 0)
					{
						echo 'Temporary disabled : '.$mac.' ('.$sn.')'."\n";
					}
				}
			}
			else
			{
				// Если запись с таким SN не существует, то у записи с таким MAC меняем MAC на SN.
				if($db->put(rpv("UPDATE @mac SET `mac` = {s1}, `flags` = (`flags` | 0x0080) WHERE `mac` = {s0} AND (`flags` & 0x0080) = 0 LIMIT 1", $mac, $sn), $uprows))
				{
					if($uprows > 0)
					{
						echo 'Replaced disabled : '.$mac.' -> '.$sn."\n";
					}
				}
			}
		}
	}
