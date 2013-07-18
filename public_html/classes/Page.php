<?php
	require_once("Menu.php");
	require_once("Template.php");

	class Page {
		protected $title;
		protected $template;

		public function __construct() {
			$this->template = new Template();
			$this->template->menu = new Menu();
		}

		public function execute() {
			if (isset($this->template))
				$this->template->display();
		}
	}
?>
