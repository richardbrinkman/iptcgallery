<?php
	namespace classes;
	
	class IptcParser {
		protected $db;
		protected $internalUpdate;
		protected $sqlGetLastModified;
		protected $sqlGetFilename;
		protected $sqlGetDirname;
		protected $sqlGetTag;
		protected $sqlGetTagId;
		protected $sqlDelPhoto;
		protected $sqlDelLink;
		protected $sqlInsPhoto;
		protected $sqlInsTag;
		protected $sqlInsLink;
		protected $sqlUpdLastModified;

		public function __construct() {
			$this->db = Database::getInstance();
			$this->internalUpdate = true;
			$this->sqlGetLastModified = $this->db->prepare("SELECT photo_id,UNIX_TIMESTAMP(last_modified) FROM photo WHERE directory_id=:directory_id AND filename=:filename");
			$this->sqlGetFilename = $this->db->prepare("SELECT directory_id, dirname, filename FROM directory NATURAL JOIN photo WHERE photo_id=:photo_id");
			$this->sqlGetDirname = $this->db->prepare("SELECT dirname FROM directory WHERE directory_id=:directory_id");
			$this->sqlGetDirname->setFetchMode(\PDO::FETCH_COLUMN, 0);
			$this->sqlGetTag = $this->db->prepare("SELECT iptc_id,value FROM tag WHERE tag_id=:tag_id");
			$this->sqlGetTagId = $this->db->prepare("SELECT tag_id FROM tag WHERE iptc_id=:iptc_id AND value=:value");
			$this->sqlGetTagId->setFetchMode(\PDO::FETCH_COLUMN, 0);
			$this->sqlDelPhoto = $this->db->prepare("DELETE FROM photo WHERE photo_id=:photo_id");
			$this->sqlDelLink = $this->db->prepare("DELETE FROM link WHERE photo_id=:photo_id");
			$this->sqlInsPhoto = $this->db->prepare("INSERT INTO photo(directory_id, filename, width, height) VALUES(:directory_id, :filename, :width, :height)");
			$this->sqlInsTag = $this->db->prepare("INSERT INTO tag(iptc_id, value) VALUES(:iptc_id, :value)");
			$this->sqlInsLink = $this->db->prepare("INSERT INTO link(tag_id, photo_id) VALUES(:tag_id, :photo_id)");
			$this->sqlUpdLastModified = $this->db->prepare("UPDATE photo SET last_modified=CURRENT_TIMESTAMP WHERE photo_id=:photo_id");
		}

		public function __destruct() {
			//Delete tags that are no longer in use
			$this->db->exec("DELETE FROM tag WHERE tag_id NOT IN (SELECT tag_id FROM link)");
			if (isset($_SESSION) && isset($_SESSION["dropdownlist"]))
				$_SESSION["dropdownlist"] = array(
					"logicalOperand" => array(),
					"iptc" => array(),
					"comparisonOperand" => array(),
					"value" => array()
				);
		}

		public function processPhoto($directoryId, $root, $file) {
			//Get date of last modification from the database if the photo already exists
			if ($this->sqlGetLastModified->execute(array(":directory_id" => $directoryId, ":filename" => $file))) {
				list($photoId,$lastModified) = $this->sqlGetLastModified->fetch();
				if ($lastModified < filemtime($root . $file)) {
					//Retrieve meta-information about the photo from the file
					$sizes = getimagesize($root . $file, $imageinfo);
					if ($sizes["mime"] == "image/jpeg") {
						if ($lastModified) { //Photo exists and needs updating
							$this->log("Updating: " . $root . $file);
							if ($this->internalUpdate) {
								$this->sqlUpdLastModified->execute(array(":photo_id"=>$photoId));
								$this->sqlDelLink->execute(array(":photo_id"=>$photoId));
							} else {
								//Delete the old photo from the database
								$this->sqlDelPhoto->execute(array(":photo_id" => $photoId));
							}
						} else
							$this->log("Adding: " . $root . $file);

						//Insert photo into the database
						if (!$lastModified==false || !$this->internalUpdate) {
							$this->sqlInsPhoto->execute(array(
								":directory_id" => $directoryId,
								":filename" => $file,
								":width" => $sizes[0],
								":height" => $sizes[1]
							));
							$this->sqlGetLastModified->execute(array(":directory_id"=>$directoryId, ":filename"=>$file));
							list($photoId, $lastModified) = $this->sqlGetLastModified->fetch();
						}

						//Check if photo has embedded IPTC meta-data
						if (isset($imageinfo["APP13"])) {
							$iptc = iptcparse($imageinfo["APP13"]);
							foreach ($iptc as $iptcId => $values) 
								foreach ($values as $value) {
									//First check if the (iptcID,value)-tuple already exists in the database
									if ($this->sqlGetTagId->execute(array(":iptc_id" => $iptcId, ":value" => $value)))
										$tagId = $this->sqlGetTagId->fetch();
									else
										$tagId = false;

									//If not than add it and get the new tagId
									if (!$tagId) {
										$this->sqlInsTag->execute(array(":iptc_id" => $iptcId, ":value" => $value));
										if ($this->sqlGetTagId->execute(array(":iptc_id" => $iptcId, ":value" => $value)))
											$tagId = $this->sqlGetTagId->fetch();
									}

									//Link the photo and the tag togetcher
									$this->sqlInsLink->execute(array(":tag_id" => $tagId, ":photo_id" => $photoId));
								}
						}
					}
				}
			}
		}

		public function addTag($photo_id, $iptc_id, $value) {
			if ($this->sqlGetFilename->execute(array(":photo_id"=>$photo_id)))
				foreach ($this->sqlGetFilename as list($directory_id, $root, $filename)) {
					$size = getimagesize($root . $filename, $info);
					if (isset($info["APP13"]))
						$iptc = iptcparse($info["APP13"]);
					else
						$iptc = array();
					$iptc[$iptc_id][] = $value;
					$content = iptcembed($this->iptcToBinary($iptc), $root . $filename);
					$fp = fopen($root . $filename, "wb");
					fwrite($fp, $content);
					fclose($fp);
					$this->processPhoto($directory_id, $root, $filename);
				}
		}

		public function delTags($photo_id, $tag_ids) {
			if ($this->sqlGetFilename->execute(array(":photo_id"=>$photo_id)))
				foreach ($this->sqlGetFilename as list($directory_id, $root, $filename)) {
					$size = getimagesize($root . $filename, $info);
					if (isset($info["APP13"]))
						$iptc = iptcparse($info["APP13"]);
					else
						$iptc = array();
					foreach ($tag_ids as $tag_id)
						if ($this->sqlGetTag->execute(array(":tag_id"=>$tag_id))) {
							list($iptc_id, $value) = $this->sqlGetTag->fetch();
							if (isset($iptc[$iptc_id])) {
								$iptc[$iptc_id] = array_diff($iptc[$iptc_id], array($value));
								if (count($iptc[$iptc_id]) == 0)
									unset($iptc[$iptc_id]);
							}
						}
					$content = iptcembed($this->iptcToBinary($iptc), $root . $filename);
					$fp = fopen($root . $filename, "wb");
					fwrite($fp, $content);
					fclose($fp);
					$this->processPhoto($directory_id, $root, $filename);
				}
		}

		public static function iptcToBinary($iptc) {
			$result = "";
			foreach ($iptc as $iptc_id=>$values)
				foreach ($values as $value) {
					$length = strlen($value);
					list($rec, $data) = explode("#", $iptc_id);
					$result .= chr(0x1C) . chr($rec) . chr($data) . chr($length >> 8) . chr($length & 0xFF) . $value;
				}
			return $result;
		}

		protected function log($message) {
		}
	}
?>
