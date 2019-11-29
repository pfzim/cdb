<?php
	// Mark all items before sync

	if(!defined('Z_PROTECTED')) exit;

	echo "\nmark-before-sync:\n";

	// Set temporary flag for remove not existing PC after all syncs
	
	if($db->put(rpv("UPDATE @computers SET `flags` = (`flags` | 0x0008) WHERE (`flags` & (0x0002 | 0x0004)) = 0")))
	{
		echo 'DONE';
	}
	else
	{
		echo 'FAILED';
	}

