<?php
	require_once("config.php");

	$class = "pages\\" . (array_key_exists("PATH_INFO", $_SERVER) ? strtr(substr($_SERVER["PATH_INFO"],1), DIRECTORY_SEPARATOR, "\\") : "Home");
	if (!class_exists($class)) 
		$class = "pages\\Error";
	$page = new $class();
	$page->execute();
?>
