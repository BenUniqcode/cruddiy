<?php
	/**
	 * AJAX handler to fetch the column names for the given table
	 */

	require ('app/config.php');

	$table_name = $_GET[ 'table' ];

	$sql = "select COLUMN_NAME as ColumnName from information_schema.columns where table_schema = '$db_name' and TABLE_NAME = '$table_name'"; // Leave them in the default order
	$result = mysqli_query($link, $sql);

	$columns = [];
	while (( $row = mysqli_fetch_array($result) )) {
		$columns[] = $row[0];
	};

	header('Content-type: application/json');
	echo json_encode( $columns );
	exit;

