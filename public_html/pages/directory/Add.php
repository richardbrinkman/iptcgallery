<?php
	namespace pages\directory;

	require_once("config.php");

	class Add extends \classes\Page {
		public function __construct() {
			parent::__construct();
			$this->template->title = "Add directory to gallery";
			$this->template->load("directory/Add");
		}

		public function execute() {
			if (isset($_POST["directory"])) {
				$query = $this->db->prepare("insert into directory(dirname) values (?)");
				if (!$query->execute(array($_POST["directory"])))
					throw new \Exception("Could not insert directory " . $_POST["directory"] . " in the database");
			}
			parent::execute();
		}
	}
?>
