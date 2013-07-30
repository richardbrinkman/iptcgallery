<?php
	namespace pages;

	class Query extends \classes\Page {
		private $conditions;
		private $drowpdownlist;

		public function __construct() {
			parent::__construct();
			session_start();
			if (!isset($_SESSION["conditions"]))
				$_SESSION["conditions"] = array();
			if (!isset($_SESSION["dropdownlist"]))
				$_SESSION["dropdownlist"] = array(
					"logicalOperand" => array(),
					"iptc" => array(),
					"comparison" => array(),
					"value" => array()
				);
			$this->conditions = &$_SESSION["conditions"];
			$this->dropdownlist = &$_SESSION["dropdownlist"];
			if (count($this->conditions) == 0)
				$this->conditions[0] = array("AND", "0", "=", "0");
			$this->template->title = "Query the gallery";
			$this->template->content = $this;
			$this->template->load("Empty");
		}

		public function execute() {
			if (isset($_GET["del"])) {
				$this->delcondition($_GET["del"]);
				if (count($this->conditions) == 0)
					$this->conditions[0] = array("AND", "0", "=", "0");
			}
			if (isset($_POST["i"])) {
				$i = $_POST["i"];
				
				//delete all further conditions and dropdownlists
				for ($j=$i + 1; $j<count($this->conditions); $j++) 
					$this->delCondition($j);

				//logicalOperand changed
				if ($this->conditions[$i][0] != $_POST["logicalOperand"]) {
					unset($this->dropdownlist["logicalOperand"][$i]);
					$this->conditions[$i][0] = $_POST["logicalOperand"];
				}

				//value changed
				if ($this->conditions[$i][3] != $_POST["value"]) {
					unset($this->dropdownlist["value"][$i]);
					$this->conditions[$i][3] = $_POST["value"];
					$this->conditions[] = array("AND", "0", "=", "0");			
				}

				//iptc changed
				if ($this->conditions[$i][1] != $_POST["iptc"]) {
					unset($this->dropdownlist["iptc"][$i]);
					unset($this->dropdownlist["value"][$i]);
					$this->conditions[$i][1] = $_POST["iptc"];
					$this->conditions[$i][3] = "0"; //set the value to 0 as well
				}

				//comparisonOperand changed
				if ($this->conditions[$i][2] != $_POST["comparisonOperand"]) {
					unset($this->dropdownlist["comparisonOperand"][$i]);
					$this->conditions[$i][2] = $_POST["comparisonOperand"];
				}
			}
			parent::execute();
		}

		public function __toString() {
			//Query for the IPTC tag dropdown list
			$nameQueryStart = 
				"SELECT iptc_id, iptc_name, COUNT(DISTINCT value) AS numberOfValues ".
				"FROM iptc ".
				"NATURAL JOIN tag ".
				"NATURAL JOIN link ".
				"WHERE true ";
			$nameQueryEnd = 
				"GROUP BY iptc_id ".
				"ORDER BY iptc_name";

			//Query for the value dropdown list
			$valueQueryStart =
				"SELECT tag_id, value, COUNT(photo_id) as numberOfPhotos ".
				"FROM tag ".
				"NATURAL JOIN link ".
				"WHERE  ";
			$valueQueryEnd =
				"GROUP BY tag_id ".
				"ORDER BY value";

			//Query for the retrieved photos
			$photoQueryStart =
				"SELECT photo_id, CONCAT(dirname, filename) AS pathname ".
				"FROM directory ".
				"NATURAL JOIN photo ".
				"WHERE true ";
			$photoQueryEnd =
				"ORDER BY pathname ".
				"LIMIT 100";

			//Query for the accumulated condition
			$accumulatedCondition = "";

			$result = "<div class=\"query\" id=\"query\">";
			$i = -1;
			foreach ($this->conditions as list($logicalOperand, $iptcId, $comparisonOperand, $value)) {
				$i++;
				
				$result .= "<form method=\"post\" action=\"" . $_SERVER["PHP_SELF"] . "\">";
				$result .= "<input type=\"hidden\" name=\"i\" value=\"$i\">";

				//logicalOperand dropdown list
				$result .= $this->getLogicalOperandDropdownlist($i);

				//IPTC tag dropdown list
				$result .= $this->getIptcDropdownlist($i, $nameQueryStart . $accumulatedCondition . $nameQueryEnd);

				//ComparisonOperand dropdown list
				$result .= $this->getComparisonDropdownlist($i);

				//value dropdown list
				$result .= $this->getValueDropdownlist($i, $valueQueryStart . ($iptcId==0 ? "true " : "iptc_id='$iptcId' ") . $accumulatedCondition . $valueQueryEnd);

				if (isset($comparisonOperand) && isset($value) && $value != "0")
					$accumulatedCondition .= " $logicalOperand photo_id IN (SELECT photo_id FROM link WHERE tag_id $comparisonOperand '$value') ";
				
				$result .= "<a href=\"" . $_SERVER["PHP_SELF"] . "?del=$i\">delete</a>";
				$result .= "</form>";
			}
			$result .= "</div>";
	
			$result .= $this->getThumbnails($photoQueryStart . $accumulatedCondition . $photoQueryEnd);

			return $result;
		}

		private function getLogicalOperandDropdownlist($i) {
			if (isset($this->dropdownlist["logicalOperand"][$i]))
				return $this->dropdownlist["logicalOperand"][$i];
			else {
				$result = "<select name=\"logicalOperand\">";
				if (isset($this->conditions[$i][0]) && $this->conditions[$i][0] == "AND")
					$result .= "<option value=\"AND\" selected>AND</option>";
				else
					$result .= "<option value=\"AND\">AND</option>";
				if (isset($this->conditions[$i][0]) && $this->conditions[$i][0] == "OR")
					$result .= "<option value=\"OR\" selected>OR</option>";
				else
					$result .= "<option value=\"OR\">OR</option>";
				$result .= "</select>";
				$this->dropdownlist["logicalOperand"][$i] = $result;
				return $result;
			}
		}

		private function getIptcDropdownlist($i, $query) {
			if (isset($this->dropdownlist["iptc"][$i]))
				return $this->dropdownlist["iptc"][$i];
			else {
				echo "<b>iptc:</b> $query<br>";
				$iptcId = $this->conditions[$i][1];
				$result = "<select name=\"iptc\" onchange=\"submit()\">";
				$result .= "<option value=\"0\">Choose tag</option>";
				foreach ($this->db->query($query) as list($iptc_id, $iptcName, $photos)) {
					$selected = $iptcId==$iptc_id ? " selected" : "";
					$result .= "<option value=\"$iptc_id\"$selected>$iptcName ($photos)</option>";
				}
				$result .= "</select>";
				$this->dropdownlist["iptc"][$i] = $result;
				return $result;
			}
		}

		private function getComparisonDropdownlist($i) {
			if (isset($this->dropdownlist["comparison"][$i]))
				return $this->dropdownlist["comparison"][$i];
			else {
				$result = "<select name=\"comparisonOperand\">";
				$result .= "<option value=\"=\">=</option>";
				$result .= "</select>";
				$this->dropdownlist["comparison"][$i] = $result;
				return $result;
			}
		}

		private function getValueDropdownlist($i, $query) {
			if (isset($this->dropdownlist["value"][$i]))
				return $this->dropdownlist["value"][$i];
			else {
				echo "<b>value:</b> $query<br>";
				$result = "<select name=\"value\" onchange=\"submit()\">";
				if ($this->conditions[$i][1] == "0") //no iptc value chosen
					$result .= "<option value=\"0\">Choose tagname first</option>";
				else {
					$result .= "<option value=\"0\">Choose value</option>";
					foreach($this->db->query($query) as list($tagId, $value, $numberOfPhotos)) {
						$selected = $this->conditions[$i][3] == $tagId ? " selected" : "";
						$result .= "<option value=\"$tagId\"$selected>$value ($numberOfPhotos)</option>";
					}
				}
				$result .= "</select>";
				$this->dropdownlist["value"][$i] = $result;
				return $result;
			}
		}

		private function getThumbnails($query) {
			echo "<b>thumbnails:</b> $query<br>";
			$result = "<div class=\"thumbnails\" id=\"thumbnails\">";
			foreach ($this->db->query($query) as list($photoId, $filename))
				$result .= "<img src=\"/thumbnail.php?photo_id=$photoId\" title=\"$filename\" alt=\"$filename\">";
			$result .= "</div>";
			return $result;
		}

		private function delCondition($i) {
			array_splice($this->conditions, $i, 1);
			foreach (array_keys($this->dropdownlist) as $key) {
				array_splice($this->dropdownlist[$key], $i, 1);
			}
		}
	}
?>
