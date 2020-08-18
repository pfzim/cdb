<?php
	// Mark all items before sync
	/**
		\file
		\brief Пометка объектов в БД перед синхронизацией.
		Требуется для выявления удаленных объектов.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nmark-before-sync:\n";

	// Set temporary flag for remove not existing PC after all syncs
	
	if($db->put(rpv("UPDATE @computers SET `flags` = ((`flags` & ~0x00F0) | 0x0008) WHERE (`flags` & (0x0002 | 0x0004)) = 0")))
	{
		echo "DONE\n";
	}
	else
	{
		echo "FAILED\n";
	}

	if($db->put(rpv("UPDATE @persons SET `flags` = ((`flags` & ~0x00F0) | 0x0008) WHERE (`flags` & (0x0002 | 0x0004)) = 0")))
	{
		echo "DONE\n";
	}
	else
	{
		echo "FAILED\n";
	}

