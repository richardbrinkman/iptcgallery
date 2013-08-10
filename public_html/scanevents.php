<?php
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');

	session_start();

	require_once("config.php");

	set_time_limit(0); //allow this script to run al long as it takes

	$scanner = new \classes\Scanner();
	$scanner->scan();
?>
