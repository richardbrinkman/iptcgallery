<?php
	namespace pages;

	require_once("config.php");

	class Home extends \classes\Page {
		public function __construct() {
			parent::__construct();
			$this->template->title = "Home page";
			$this->template->load("Home");
		}
	}
?>
