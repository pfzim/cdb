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
	
	if($db->put(rpv("UPDATE @computers SET `flags` = ((`flags` & ~{%CF_MASK_EXIST}) | {%CF_TEMP_MARK}) WHERE (`flags` & ({%CF_DELETED} | {%CF_HIDED})) = 0")))
	{
		echo "DONE\n";
	}
	else
	{
		echo "FAILED\n";
	}

	if($db->put(rpv("UPDATE @persons SET `flags` = ((`flags` & ~{%PF_MASK_EXIST}) | {%PF_TEMP_MARK}) WHERE (`flags` & ({%PF_DELETED} | {%PF_HIDED})) = 0")))
	{
		echo "DONE\n";
	}
	else
	{
		echo "FAILED\n";
	}

