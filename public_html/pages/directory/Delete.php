<?php
	namespace pages\directory;

	require_once("config.php");

	class Delete extends \classes\Page {
		public function __construct() {
			parent::__construct();
			$this->template->title = "Delete directory from the gallery";
			$this->template->load("directory/Delete");
		}

		public function execute() {
			if (isset($_POST["directories"])) {
				$sqlDelete = $this->db->prepare("DELETE FROM directory WHERE directory_id=?");
				$sqlDelete->bindParam(1, $id, \PDO::PARAM_INT);
				foreach ($_POST["directories"] as $id) 
					$sqlDelete->execute();
			}
			$directories = array();
			foreach ($this->db->query("SELECT directory_id, dirname FROM directory ORDER BY dirname") as list($id, $dirname)) {
				$directories[$id] = $dirname;
			}
			$this->template->directories = $directories;
			parent::execute();
		}
	}
?>

