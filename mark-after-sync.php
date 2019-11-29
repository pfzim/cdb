<?php
	// Mark all items after sync as deleted

	if(!defined('Z_PROTECTED')) exit;

	// Remove not existing PC after all syncs
	
	if($db->put(rpv("UPDATE @computers SET `flags` = ((`flags` & ~0x0008) | 0x0002) WHERE `flags` & 0x0008")))
	{
		echo 'DONE';
	}
	else
	{
		echo 'FAILED';
	}

