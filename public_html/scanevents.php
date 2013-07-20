<?php
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');

	require_once("classes/Database.php");
	$db = Database::getInstance();

	$preparedStatements = array(
		"sqlGetPhotoId" => $db->prepare("SELECT photo_id FROM photo WHERE filename=:filename"),
		"sqlGetLastModified" => $db->prepare("SELECT UNIX_TIMESTAMP(last_modified) FROM photo WHERE filename=:filename"),
		"sqlDelPhoto" => $db->prepare("DELETE FROM photo WHERE filename=:filename"),
		"sqlInsPhoto" => $db->prepare("INSERT INTO photo(filename, width, height) VALUES(:filename, :width, :height)"),
		"sqlInsTag" => $db->prepare("INSERT INTO tag(photo_id, iptc_id, value) VALUES(:photo_id, :iptc_id, :value)")
	);
	$preparedStatements["sqlGetPhotoId"]->setFetchMode(PDO::FETCH_COLUMN, 0);
	$preparedStatements["sqlGetLastModified"]->setFetchMode(PDO::FETCH_COLUMN, 0);

	function sendLog($message) {
		echo "retry: 3600000\n";
		echo "data: $message\n\n";
		ob_flush();
		flush();
	}

	function processDir($dir, $preparedStatements) {
		sendLog("Scanning: $dir");
		foreach (scandir($dir) as $file) 
			if ($file != "." && $file != "..") {
				$file = $dir . DIRECTORY_SEPARATOR . $file;
				if (is_dir($file))
					processDir($file, $preparedStatements);
				else {
					if ($preparedStatements["sqlGetLastModified"]->execute(array("filename" => $file))) {
						$lastModified = $preparedStatements["sqlGetLastModified"]->fetch();
						if ($lastModified < filemtime($file)) {
							$sizes = getimagesize($file, $imageinfo);
							if ($sizes["mime"] == "image/jpeg") {
								sendLog("Adding/Updating: $file");
								if (!$preparedStatements["sqlDelPhoto"]->execute(array("filename" => $file)))
									throw new Exception("$file already exists and cannot delete it from the database");
								if (!$preparedStatements["sqlInsPhoto"]->execute(array("filename" => $file, "width" => $sizes[0], "height" => $sizes[1])))
									throw new Exception("Could not insert $file into the database");
								if (isset($imageinfo["APP13"]) && $preparedStatements["sqlGetPhotoId"]->execute(array("filename" => $file))) {
									$photoId = $preparedStatements["sqlGetPhotoId"]->fetch();
									$iptc = iptcparse($imageinfo["APP13"]);
									foreach ($iptc as $iptcId => $values)
										foreach ($values as $value)
											if (!$preparedStatements["sqlInsTag"]->execute(array("photo_id" => $photoId, "iptc_id" => $iptcId, "value" => $value)))
												throw new Exception("Cannot set tag $iptcId on $file");
								}
							}
						}
					}
				}
			}
	}

	foreach ($db->query("SELECT dirname FROM directory", PDO::FETCH_COLUMN, 0) as $dirname)
		processDir($dirname, $preparedStatements);

	sendLog("finished");
	echo "event: finished\n";
	echo "data: Finished gallery scan\n\n";
?>
