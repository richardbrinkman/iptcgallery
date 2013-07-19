<?php
	namespace classes;

	class Menu {
		protected $items;

		public function __construct() {
			$this->items = $this->getMenu();
		}

		public function __toString() {
			return $this->itemsToHTML($this->items);
		}

		public function getStandardMenu() {
			return array("Home", "/Home", array(
				array("Directory management", "/directory/", array(
					array("Add", "/directory/Add", null),
					array("Delete", "/directory/Delete", null),
					array("Scan", "/directory/Scan", null),
					array("Prune", "/directory/Prune", null)
				)),
				array("Query", "Query", null)
			));
		}

		protected function getMenu() {
			return $this->getStandardMenu();
		}

		private function itemsToHTML($menu) {
			$result = "<ul>";
			list($description, $page, $submenu) = $menu;
			$result .= "<li><a href=\"$page\">$description</a>";
			if (isset($submenu))
				foreach ($submenu as $item)
					$result .= $this->itemsToHTML($item);
			$result .= "</li>";
			$result .= "</ul>";
			return $result;
		}
	}
?>
