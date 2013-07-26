<?php
	namespace pages\directory;

	class Scan extends \classes\Page {
		public function __construct() {
			parent::__construct();
			$this->template->title = "Scan complete gallery";
			$this->template->load("directory/Scan");
		}
	}
?>
