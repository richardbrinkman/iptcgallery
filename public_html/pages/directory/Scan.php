<?php
	require_once(CLASS_PATH . "Page.php");

	class Scan extends Page {
		private $sqlGetPhotoId;
		private $sqlGetLastModified;
		private $sqlDelPhoto;
		private $sqlInsPhoto;
		private $sqlInsTag;

		public function __construct() {
			parent::__construct();
			$this->sqlGetPhotoId = $this->db->prepare("SELECT photo_id FROM photo WHERE filename=:filename");
			$this->sqlGetPhotoId->setFetchMode(PDO::FETCH_COLUMN, 0);
			$this->sqlGetLastModified = $this->db->prepare("SELECT UNIX_TIMESTAMP(last_modified) FROM photo WHERE filename=:filename");
			$this->sqlGetLastModified->setFetchMode(PDO::FETCH_COLUMN, 0);
			$this->sqlDelPhoto = $this->db->prepare("DELETE FROM photo WHERE filename=:filename");
			$this->sqlInsPhoto = $this->db->prepare("INSERT INTO photo(filename, width, height) VALUES(:filename, :width, :height)");
			$this->sqlInsTag = $this->db->prepare("INSERT INTO tag(photo_id, iptc_id, value) VALUES(:photo_id, :iptc_id, :value)");
			$this->template->title = "Scan complete gallery";
			$this->template->load("directory/Scan");
		}

		public function execute() {
			parent::execute();
			foreach ($this->db->query("SELECT dirname FROM directory", PDO::FETCH_COLUMN, 0) as $dirname)
				$this->processDir($dirname);
			require(TEMPLATE_PATH . "footer.php");
		}

		private function processDir($dir) {
			foreach (scandir($dir) as $file) 
				if ($file != "." && $file != "..") {
					$file = $dir . DIRECTORY_SEPARATOR . $file;
					if (is_dir($file))
						$this->processDir($file);
					else {
						if ($this->sqlGetLastModified->execute(array("filename" => $file))) {
							$lastModified = $this->sqlGetLastModified->fetch();
							if ($lastModified < filemtime($file)) {
								$sizes = getimagesize($file, $imageinfo);
								if ($sizes["mime"] == "image/jpeg") {
									$this->log($file);
									if (!$this->sqlDelPhoto->execute(array("filename" => $file)))
										throw new Exception("$file already exists and cannot delete it from the database");
									if (!$this->sqlInsPhoto->execute(array("filename" => $file, "width" => $sizes[0], "height" => $sizes[1])))
										throw new Exception("Could not insert $file into the database");
									if (isset($imageinfo["APP13"]) && $this->sqlGetPhotoId->execute(array("filename" => $file))) {
										$photoId = $this->sqlGetPhotoId->fetch();
										$iptc = iptcparse($imageinfo["APP13"]);
										foreach ($iptc as $iptcId => $values)
											foreach ($values as $value)
												if (!$this->sqlInsTag->execute(array("photo_id" => $photoId, "iptc_id" => $iptcId, "value" => $value)))
													throw new Exception("Cannot set tag $iptcId on $file");
									}
								}
							}
						}
					}
				}
		}

		private function log($message) {
			echo "<script>document.getElementById(\"output\").innerHTML += \"$message<br>\";</script>";
		}
	}
?>
