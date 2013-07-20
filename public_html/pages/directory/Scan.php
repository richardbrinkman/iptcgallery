<?php
	require_once(CLASS_PATH . "Page.php");

	class Scan extends Page {
		public function __construct() {
			parent::__construct();
			$this->template->title = "Scan complete gallery";
			$this->template->load("directory/Scan");
		}
	}
?>
