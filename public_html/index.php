<?php
	require_once("config.php");
	require_once(CLASS_PATH . "Template.php");

	$class = array_key_exists("PATH_INFO", $_SERVER) ? substr($_SERVER["PATH_INFO"],1) : "Home";
	$file = PAGE_PATH . $class . ".php";
	if (file_exists($file)) {
		require_once($file);
		$class = basename($class);
		$page = new $class();
		$page->execute();
	} else {
		$template = new Template("pagenotfound");
		$template->display();
	}
?>
