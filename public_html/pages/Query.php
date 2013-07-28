<?php
	namespace pages;

	class Query extends \classes\Page {
		private $conditions;

		public function __construct() {
			parent::__construct();
			session_start();
			if (!isset($_SESSION["conditions"]))
				$_SESSION["conditions"] = array();
			$this->conditions = &$_SESSION["conditions"];
			$this->template->title = "Query the gallery";
			$this->template->content = $this;
			$this->template->load("Empty");
		}

		public function execute() {
			if (isset($_GET["del"]))
				array_splice($this->conditions, $_GET["del"], 1);
			parent::execute();
		}

		public function __toString() {
			$result = "<div class=\"query\" id=\"query\">";
			$i = -1;
			foreach ($this->conditions as list($logicalOperand, $iptcId, $comparisonOperand, $value)) {
				$i++;
				$result .= "<form method=\"post\" action=\"" . $_SERVER["PHP_SELF"] . "\">";
				$result .= "<input type=\"hidden\" name=\"i\" value=\"$i\">";
				$result .= "<input type=\"text\" name=\"value\" value=\"$value\">";
				$result .= "<a href=\"" . $_SERVER["PHP_SELF"] . "?del=$i\">delete</a>";
				$result .= "</form>";
			}
			$result .= "</div>";
			
			$result .= "<div class=\"thumbnails\" id=\"thumbnails\">";
			$query = "SELECT photo_id, CONCAT(dirname,filename) AS pathname FROM directory NATURAL JOIN photo WHERE true";
			foreach ($this->conditions as list($logicalOperand, $iptcId, $comparisonOperand, $value)) {
				$query .= " $logicalOperand photo_id IN (SELECT photo_id FROM tag WHERE iptc_id = \"$iptcId\" AND value $comparisonOperand \"$value\")";
			}
			$query .= " ORDER BY pathname LIMIT 100";
			$sqlGetPhotoIds = $this->db->query($query);
			foreach ($sqlGetPhotoIds as list($photoId, $filename))
				$result .= "<img src=\"thumbnail.php?photo_id=$photoId\" title=\"$filename\" alt=\"$filename\">";
			$result .= "</div>";

			return $result;
		}
	}
?>
