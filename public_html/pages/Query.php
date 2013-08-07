<?php
	namespace pages;

	//define("debugmode", true);

	class Query extends \classes\Page {
		private $conditions;
		private $drowpdownlist;

		public function __construct() {
			parent::__construct();
			session_start();
			//unset($_SESSION["conditions"]);
			//unset($_SESSION["dropdownlist"]);
			if (!isset($_SESSION["conditions"]))
				$_SESSION["conditions"] = array();
			if (!isset($_SESSION["dropdownlist"]))
				$_SESSION["dropdownlist"] = array(
					"logicalOperand" => array(),
					"iptc" => array(),
					"comparisonOperand" => array(),
					"value" => array(),
					"common" => array()
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
				$this->delCondition($i+1);

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
					unset($this->dropdownlist["value"][$i]);
					$this->conditions[$i][2] = $_POST["comparisonOperand"];
					switch ($this->conditions[$i][2]) {
						case "=":
						case "!=": $this->conditions[$i][3] = "0"; break;
						case "IS":
						case "IS NOT": $this->conditions[$i][3] = "NULL"; $this->conditions[] = array("AND", "0", "=", "0"); break;
						case "LIKE":
						case "NOT LIKE": $this->conditions[$i][3] = ""; break;
					}
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
				"HAVING numberOfValues>1 OR COUNT(DISTINCT photo_id)<? ".
				"ORDER BY iptc_name";

			//Query for the common tags
			$commonQueryStart =
				"SELECT iptc_name, value ".
				"FROM iptc ".
				"NATURAL JOIN tag ".
				"NATURAL JOIN link ".
				"WHERE true ";
			$commonQueryEnd =
				"GROUP BY iptc_id ".
				"HAVING COUNT(DISTINCT value)=1 AND COUNT(DISTINCT photo_id)=? ".
				"ORDER BY iptc_name";

			//Query for the value dropdown list
			$valueQueryStart =
				"SELECT tag_id, value, COUNT(photo_id) AS numberOfPhotos ".
				"FROM tag ".
				"NATURAL JOIN link ".
				"WHERE  ";
			$valueQueryEnd =
				"GROUP BY tag_id ".
				"ORDER BY value";

			//Query for the number of found photos
			$countQueryStart =
				"SELECT COUNT(DISTINCT photo_id) AS numberOfPhotos ".
				"FROM photo ".
				"WHERE true ";

			//Query for the retrieved photos
			$photoQueryStart =
				"SELECT photo_id, CONCAT(dirname, filename) AS pathname ".
				"FROM directory ".
				"NATURAL JOIN photo ".
				"WHERE photo_id IN (SELECT photo_id FROM photo WHERE true ";
			$photoQueryEnd =
				") ".
				"ORDER BY pathname ".
				"LIMIT 100";

			//Query for the accumulated condition
			$accumulatedCondition = "";

			$result = "<div class=\"query\" id=\"query\">";
			$i = -1;
			foreach ($this->conditions as list($logicalOperand, $iptcId, $comparisonOperand, $value)) {
				$i++;
				
				$numberOfPhotos = $this->db->query($countQueryStart . $accumulatedCondition, \PDO::FETCH_COLUMN, 0)->fetch();

				//IPTC tag dropdown list
				$iptcList = $this->getIptcDropdownlist($i, $nameQueryStart . $accumulatedCondition . $nameQueryEnd, $numberOfPhotos);

				if ($iptcList != "") {
					$result .= "<form method=\"post\" action=\"" . $_SERVER["PHP_SELF"] . "\">";
					$result .= "<input type=\"hidden\" name=\"i\" value=\"$i\">";

					//logicalOperand dropdown list
					$result .= $this->getLogicalOperandDropdownlist($i);

					$result .= $iptcList;

					//ComparisonOperand dropdown list
					$result .= $this->getComparisonDropdownlist($i);

					//value dropdown list
					$result .= $this->getValueDropdownlist($i, $valueQueryStart . ($iptcId==0 ? "true " : "iptc_id='$iptcId' ") . $accumulatedCondition . $valueQueryEnd);

					if (isset($comparisonOperand) && isset($value) && $value != "0")
						switch ($comparisonOperand) {
							case "=":
								$accumulatedCondition .= " $logicalOperand photo_id IN (SELECT photo_id FROM link WHERE tag_id=$value) ";
								break;
							case "!=":
								$accumulatedCondition .= " $logicalOperand photo_id NOT IN (SELECT photo_id FROM link WHERE tag_id=$value) ";
								break;
							case "IS":
								$accumulatedCondition .= " $logicalOperand photo_id NOT IN (SELECT photo_id FROM link NATURAL JOIN tag WHERE iptc_id='$iptcId') ";
								break;
							case "IS NOT":
								$accumulatedCondition .= " $logicalOperand photo_id IN (SELECT photo_id FROM link NATURAL JOIN tag WHERE iptc_id='$iptcId') ";
								break;
							case "LIKE":
								$accumulatedCondition .= " $logicalOperand photo_id IN (SELECT photo_id FROM link NATURAL JOIN tag WHERE iptc_id='$iptcId' AND value LIKE '$value') ";
								break;
							case "NOT LIKE":
								$accumulatedCondition .= " $logicalOperand photo_id NOT IN (SELECT photo_id FROM link NATURAL JOIN tag WHERE iptc_id='$iptcId' AND value LIKE '$value') ";
								break;
						}
					
					$result .= "<a href=\"" . $_SERVER["PHP_SELF"] . "?del=$i\">delete</a>";
					$result .= "</form>";
				}
			}
			$result .= "</div>";
			
			//Get common iptc tags
			$result .= $this->getCommonTags($i, $commonQueryStart . $accumulatedCondition . $commonQueryEnd, $numberOfPhotos);

			$result .= "<p>Number of pictures: $numberOfPhotos</p>";
	
			$result .= $this->getThumbnails($photoQueryStart . $accumulatedCondition . $photoQueryEnd);

			return $result;
		}

		private function getLogicalOperandDropdownlist($i) {
			if (isset($this->dropdownlist["logicalOperand"][$i]))
				return $this->dropdownlist["logicalOperand"][$i];
			else {
				$result = "<select name=\"logicalOperand\" onchange=\"submit()\">";
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

		private function getIptcDropdownlist($i, $query, $numberOfPhotos) {
			if (isset($this->dropdownlist["iptc"][$i]))
				return $this->dropdownlist["iptc"][$i];
			else {
				if (defined("debugmode"))
					echo "<b>iptc:</b> ". htmlentities($query) . "<br>";
				$foundSomething = false;
				$iptcId = $this->conditions[$i][1];
				$result = "<select name=\"iptc\" onchange=\"submit()\">";
				$result .= "<option value=\"0\">Choose tag</option>";
				$sqlIptc = $this->db->prepare($query);
				if ($sqlIptc->execute(array($numberOfPhotos)))
					foreach ($sqlIptc as list($iptc_id, $iptcName, $photos)) {
						$selected = $iptcId==$iptc_id ? " selected" : "";
						$result .= "<option value=\"$iptc_id\"$selected>$iptcName ($photos)</option>";
						$foundSomething = true;
					}
				$result .= "</select>";
				$this->dropdownlist["iptc"][$i] = $foundSomething ? $result : "";
				return $this->dropdownlist["iptc"][$i];
			}
		}

		private function getComparisonDropdownlist($i) {
			if (isset($this->dropdownlist["comparisonOperand"][$i]))
				return $this->dropdownlist["comparisonOperand"][$i];
			else {
				$result = "<select name=\"comparisonOperand\" onchange=\"submit()\">";
				foreach(array("=", "!=", "IS", "IS NOT", "LIKE", "NOT LIKE") as $operand) {
					$selected = $this->conditions[$i][2] == $operand ? " selected" : "";
					$result .= "<option value=\"$operand\"$selected>$operand</option>";
				}
				$result .= "</select>";
				$this->dropdownlist["comparisonOperand"][$i] = $result;
				return $result;
			}
		}

		private function getValueDropdownlist($i, $query) {
			if (isset($this->dropdownlist["value"][$i]))
				return $this->dropdownlist["value"][$i];
			else {
				if ($this->conditions[$i][2] == "LIKE" || $this->conditions[$i][2] == "NOT LIKE") {
					$result = "<input type=\"text\" name=\"value\" onchange=\"submit()\" value=\"".$this->conditions[$i][3]."\">";
					$result .= "<input type=\"submit\" value=\"submit\">";
				} else {
					$result = "<select name=\"value\" onchange=\"submit()\">";
					if ($this->conditions[$i][1] == "0") //no iptc value chosen
						$result .= "<option value=\"0\">Choose tagname first</option>";
					else if ($this->conditions[$i][2] == "IS" || $this->conditions[$i][2] == "IS NOT")
						$result .= "<option value=\"NULL\">NULL</option>";
					else {
						if (defined("debugmode"))
							echo "<b>value:</b> $query<br>";
						$result .= "<option value=\"0\">Choose value</option>";
						foreach($this->db->query($query) as list($tagId, $value, $numberOfPhotos)) {
							$selected = $this->conditions[$i][3] == $tagId ? " selected" : "";
							$result .= "<option value=\"$tagId\"$selected>$value ($numberOfPhotos)</option>";
						}
					}
					$result .= "</select>";
				}
				$this->dropdownlist["value"][$i] = $result;
				return $result;
			}
		}

		private function getThumbnails($query) {
			if (defined("debugmode"))
				echo "<b>thumbnails:</b> $query<br>";
			$result = "<div class=\"thumbnails\" id=\"thumbnails\">";
			foreach ($this->db->query($query) as list($photoId, $filename))
				$result .= "<img src=\"/thumbnail.php?photo_id=$photoId\" title=\"$filename\" alt=\"$filename\">";
			$result .= "</div>";
			return $result;
		}

		private function delCondition($i) {
			for ($j=count($this->conditions)-1; $j>=$i; $j--)
				array_pop($this->conditions);
			foreach (array_keys($this->dropdownlist) as $key)
				for ($j=count($this->dropdownlist[$key])-1; $j>=$i; $j--)
					array_pop($this->dropdownlist[$key]);
		}
		
		private function getCommonTags($i, $query, $numberOfPhotos) {
			if (isset($this->dropdownlist["common"][$i]))
				return $this->dropdownlist["common"][$i];
			else {
				if (defined("debugmode"))
					echo "<b>common:</b> $query<br>";
				$foundSomething = false;
				$result = "<table>";
				$sqlCommon = $this->db->prepare($query);
				if ($sqlCommon->execute(array($numberOfPhotos)))
					foreach ($sqlCommon as list($iptc_name, $value)) {
						$result .= "<tr><td>$iptc_name</td><td>$value</td></tr>";
						$foundSomething = true;
					}
				$result .= "</table>";
				$this->dropdownlist["common"][$i] = $foundSomething ? $result : "";
				return $this->dropdownlist["common"][$i];
			}
		}
	}
?>
