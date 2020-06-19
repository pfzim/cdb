<?php
	// Set computer flags

	if(!defined('Z_PROTECTED')) exit;

	if(!empty($_GET['do']) && !empty($_GET['id']))
	{
		if($_GET['do'] === 'show')
		{
			$db->put(rpv("UPDATE @computers SET `flags` = (`flags` & ~0x0004) WHERE `id` = # LIMIT 1", $_GET['id']));
			echo 'OK';
		}
		else if($_GET['do'] === 'hide')
		{
			$db->put(rpv("UPDATE @computers SET `flags` = (`flags` | 0x0004) WHERE `id` = # LIMIT 1", $_GET['id']));
			echo 'OK';
		}
	}
