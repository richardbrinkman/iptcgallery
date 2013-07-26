<?php
	namespace classes;

	class Database {
		private static $db;

		public static function getInstance() {
			if (!isset(self::$db))
				self::$db = new \PDO("mysql:dbname=gallery;host=127.0.0.1", "php", "f2jffwjfw29134agfa");
			return self::$db;
		}
	}
?>
