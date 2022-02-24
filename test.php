<?php
	// TEST SCRIPT
	
	if(!defined('Z_PROTECTED')) exit;

	echo "\nTEST SCRIPT:\n";

	$params = array(
		'Database' =>				'ITINVENT',
		'UID' =>					ITINVENT_DB_USER,
		'PWD' =>					ITINVENT_DB_PASSWD,
		'ReturnDatesAsStrings' =>	true
	);

	$conn = sqlsrv_connect('brc-itinv-01', $params);
	if($conn === false)
	{
		print_r(sqlsrv_errors());
		exit;
	}

	$invent_result = sqlsrv_query($conn, '
	SELECT [FIELD_NO]
		  ,[ITEM_NO]
		  ,[FIELD_NAME]
		  ,[FIELD_TYPE]
		  ,[FIELD_DESCR]
		  ,[SORT_NO]
		  ,[LIST_VALUES]
		  ,[REQUIRED]
		  ,[TAB_NO]
		  ,[SQL_QUERY]
		  ,[USE_ON_TABLE]
	FROM [FIELDS]
	');

	$i = 0;
	while($row = sqlsrv_fetch_array($invent_result, SQLSRV_FETCH_ASSOC))
	{
		echo 'FIELD_NO: '.$row['FIELD_NO'].' ITEM_NO: '.$row['ITEM_NO'].' FIELD_NAME: '.$row['FIELD_NAME'].' FIELD_TYPE: '.$row['FIELD_TYPE']."\r\n";
	}

	sqlsrv_free_stmt($invent_result);
	
	sqlsrv_close($conn);
