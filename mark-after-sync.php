<?php
	// Mark all items after sync as deleted
	/**
		\file
		\brief Выявление удаленных объектов после полной синхорнизации.
		Выполняется после полной синхорнизации (sync-all) для выявления удаленных объектов.
	*/

	if(!defined('Z_PROTECTED')) exit;

	echo "\nmark-after-sync:\n";

	// Remove not existing PC after all syncs
	
	if($db->put(rpv("UPDATE @computers SET `flags` = ((`flags` & ~{%CF_TEMP_MARK}) | {%CF_DELETED}) WHERE `flags` & {%CF_TEMP_MARK}")))
	{
		echo "DONE\n";
	}
	else
	{
		echo "FAILED\n";
	}

	if($db->put(rpv("UPDATE @persons SET `flags` = ((`flags` & ~{%PF_TEMP_MARK}) | {%PF_DELETED}) WHERE `flags` & {%PF_TEMP_MARK}")))
	{
		echo "DONE\n";
	}
	else
	{
		echo "FAILED\n";
	}

