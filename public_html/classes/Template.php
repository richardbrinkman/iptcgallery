<?php
	namespace classes;

	require_once("config.php");

	class Template {
		protected $page;
		protected $properties;

		public function __construct() {
			$this->properties = array();
		}

		public function __get($name) {
			return $this->properties[$name];
		}

		public function __set($name, $value) {
			$this->properties[$name] = $value;
		}

		public function load($page = "index.php") {
			$this->page = TEMPLATE_PATH . $page . ".php";
			if (!file_exists($this->page)) {
				if (substr($page, -1) == DIRECTORY_SEPARATOR)
					$this->page .= DIRECTORY_SEPARATOR;
				$this->page .= "index.php";
				if (!file_exists($this->page)) {
					$this->page = TEMPLATE_PATH . DIRECTORY_SEPARATOR . "pagenotfound.php";
				}
			}
		}

		public function display() {
			foreach ($this->properties as $name=>$value)
				$$name = $value;
			require_once($this->page);
		}
	}
?>
