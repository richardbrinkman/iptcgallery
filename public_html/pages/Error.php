<?php
	namespace pages;

	require_once("config.php");

	class Error extends \classes\Page {
		public function __construct() {
			parent::__construct();
			$this->template->title = "Error page";
			$this->template->load("pagenotfound");
		}
	}
?>
