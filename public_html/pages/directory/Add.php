<?php
	require_once("config.php");
	require_once(CLASS_PATH . "Database.php");
	require_once(CLASS_PATH . "Page.php");

	class Add extends Page {
		public function __construct() {
			parent::__construct();
			$this->template->title = "Add directory to gallery";
			$this->template->load("directory/Add");
		}

		public function execute() {
			if (isset($_POST["directory"])) {
				$query = Database::getInstance()->prepare("insert into directory(dirname) values (?)");
				if (!$query->execute(array($_POST["directory"])))
					throw new Exception("Could not insert directory " . $_POST["directory"] . " in the database");
			}
			parent::execute();
		}
	}
?>
