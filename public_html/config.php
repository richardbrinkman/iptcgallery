<?php
	define("APPLICATION_ROOT", __DIR__ . DIRECTORY_SEPARATOR);
	define("CLASS_PATH", APPLICATION_ROOT . "classes" . DIRECTORY_SEPARATOR);
	define("PAGE_PATH", APPLICATION_ROOT . "pages" . DIRECTORY_SEPARATOR);
	define("TEMPLATE_PATH", APPLICATION_ROOT . "templates" . DIRECTORY_SEPARATOR);

	define("THUMBNAIL_SIZE", 200);

	spl_autoload_register(function($class) {
		$file = APPLICATION_ROOT . strtr($class, "\\", DIRECTORY_SEPARATOR) . ".php";
		if (file_exists($file))
			require_once($file);
	});
?>
