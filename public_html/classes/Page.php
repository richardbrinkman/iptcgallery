<?php
	require_once("Database.php");
	require_once("Menu.php");
	require_once("Template.php");

	class Page {
		protected $title;
		protected $template;
		protected $db;

		public function __construct() {
			$this->template = new Template();
			$this->template->menu = new Menu();
			$this->db = Database::getInstance();
		}

		public function execute() {
			if (isset($this->template))
				$this->template->display();
		}
	}
?>
