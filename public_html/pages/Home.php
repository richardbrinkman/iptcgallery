<?php
	require_once("config.php");
	require_once(CLASS_PATH . "Page.php");

	class Home extends Page {
		public function __construct() {
			parent::__construct();
			$this->template->title = "Home page";
			$this->template->load("Home");
		}
	}
?>
