<?php
	namespace classes;

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
