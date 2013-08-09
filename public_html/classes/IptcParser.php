<?php
	namespace classes;
	
	class IptcParser {
		protected $db;
		protected $sqlGetLastModified;
		protected $sqlGetPhotoId;
		protected $sqlGetTag;
		protected $sqlDelPhoto;
		protected $sqlInsPhoto;
		protected $sqlInsTag;
		protected $sqlInsLink;

		public function __construct() {
			$this->db = Database::getInstance();
			$this->sqlGetLastModified = $this->db->prepare("SELECT UNIX_TIMESTAMP(last_modified) FROM photo WHERE directory_id=:directory_id AND filename=:filename");
			$this->sqlGetLastModified->setFetchMode(\PDO::FETCH_COLUMN, 0);
			$this->sqlGetPhotoId = $this->db->prepare("SELECT photo_id FROM photo WHERE directory_id=:directory_id AND filename=:filename");
			$this->sqlGetPhotoId->setFetchMode(\PDO::FETCH_COLUMN, 0);
			$this->sqlGetTag = $this->db->prepare("SELECT tag_id FROM tag WHERE iptc_id=:iptc_id AND value=:value");
			$this->sqlGetTag->setFetchMode(\PDO::FETCH_COLUMN, 0);
			$this->sqlDelPhoto = $this->db->prepare("DELETE FROM photo WHERE directory_id=:directory_id AND filename=:filename");
			$this->sqlInsPhoto = $this->db->prepare("INSERT INTO photo(directory_id, filename, width, height) VALUES(:directory_id, :filename, :width, :height)");
			$this->sqlInsTag = $this->db->prepare("INSERT INTO tag(iptc_id, value) VALUES(:iptc_id, :value)");
			$this->sqlInsLink = $this->db->prepare("INSERT INTO link(tag_id, photo_id) VALUES(:tag_id, :photo_id)");
		}

		public function __destruct() {
			//Delete tags that are no longer in use
			$this->db->exec("DELETE FROM tag WHERE tag_id NOT IN (SELECT tag_id FROM link)");
		}

		public function processPhoto($directoryId, $root, $file) {
			//Get date of last modification from the database if the photo already exists
			if ($this->sqlGetLastModified->execute(array(":directory_id" => $directoryId, ":filename" => $file))) {
				$lastModified = $this->sqlGetLastModified->fetch();
				if ($lastModified < filemtime($root . $file)) {
					//Retrieve meta-information about the photo from the file
					$sizes = getimagesize($root . $file, $imageinfo);
					if ($sizes["mime"] == "image/jpeg") {
						if ($lastModified) { //Photo exists and needs updating
							$this->log("Updating: " . $root . $file);
							//Delete the old photo from the database
							if (!$this->sqlDelPhoto->execute(array(":directory_id" => $directoryId, ":filename" => $file)))
								throw new \Exception("$file already exists and cannot delete it from the database");
						} else
							$this->log("Adding: " . $root . $file);

						//Insert photo into the database
						if (!$this->sqlInsPhoto->execute(array(":directory_id" => $directoryId, ":filename" => $file, ":width" => $sizes[0], ":height" => $sizes[1])))
							throw new \Exception("Could not insert $file into the database");

						//Check if photo has embedded IPTC meta-data and if so, retrieve the photo_id
						if (isset($imageinfo["APP13"]) && $this->sqlGetPhotoId->execute(array(":directory_id" => $directoryId, ":filename" => $file))) {
							$photoId = $this->sqlGetPhotoId->fetch();
							$iptc = iptcparse($imageinfo["APP13"]);
							foreach ($iptc as $iptcId => $values) 
								foreach ($values as $value) {
									//First check if the (iptcID,value)-tuple already exists in the database
									if ($this->sqlGetTag->execute(array(":iptc_id" => $iptcId, ":value" => $value)))
										$tagId = $this->sqlGetTag->fetch();
									else
										$tagId = false;

									//If not than add it and get the new tagId
									if (!$tagId) {
										$this->sqlInsTag->execute(array(":iptc_id" => $iptcId, ":value" => $value));
										if ($this->sqlGetTag->execute(array(":iptc_id" => $iptcId, ":value" => $value)))
											$tagId = $this->sqlGetTag->fetch();
									}

									//Link the photo and the tag togetcher
									$this->sqlInsLink->execute(array(":tag_id" => $tagId, ":photo_id" => $photoId));
								}
						}
					}
				}
			}
		}

		protected function log($message) {
		}
	}
?>
