<?php
	namespace pages\directory;

	class Prune extends \classes\Page {
		public function __construct() {
			parent::__construct();
			$this->template->title = "Prune database";
			$this->template->load("directory/Prune");
		}
	}
?>
