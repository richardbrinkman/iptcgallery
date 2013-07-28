<?php
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');

	require_once("vendor/autoload.php");

	$db = classes\Database::getInstance();

	$preparedStatements = array(
		"sqlGetPhotoId" => $db->prepare("SELECT photo_id FROM photo WHERE directory_id=:directory_id AND filename=:filename"),
		"sqlGetLastModified" => $db->prepare("SELECT UNIX_TIMESTAMP(last_modified) FROM photo WHERE directory_id=:directory_id AND filename=:filename"),
		"sqlDelPhoto" => $db->prepare("DELETE FROM photo WHERE directory_id=:directory_id AND filename=:filename"),
		"sqlInsPhoto" => $db->prepare("INSERT INTO photo(directory_id, filename, width, height) VALUES(:directory_id, :filename, :width, :height)"),
		"sqlInsTag" => $db->prepare("INSERT INTO tag(photo_id, iptc_id, value) VALUES(:photo_id, :iptc_id, :value)")
	);
	$preparedStatements["sqlGetPhotoId"]->setFetchMode(\PDO::FETCH_COLUMN, 0);
	$preparedStatements["sqlGetLastModified"]->setFetchMode(\PDO::FETCH_COLUMN, 0);

	function sendLog($message) {
		echo "retry: 3600000\n";
		echo "data: $message\n\n";
		ob_flush();
		flush();
	}

	function processDir($directoryId, $root, $dir, $preparedStatements) {
		$path = $root . $dir;
		sendLog("Scanning: $path");
		foreach (scandir($path) as $file) 
			if ($file != "." && $file != "..") {
				$file = ltrim($dir . DIRECTORY_SEPARATOR . $file, DIRECTORY_SEPARATOR);
				if (is_dir($root . $file))
					processDir($directoryId, $root, $file, $preparedStatements);
				else {
					if ($preparedStatements["sqlGetLastModified"]->execute(array(":directory_id" => $directoryId, ":filename" => $file))) {
						$lastModified = $preparedStatements["sqlGetLastModified"]->fetch();
						if ($lastModified < filemtime($root . $file)) {
							$sizes = getimagesize($root . $file, $imageinfo);
							if ($sizes["mime"] == "image/jpeg") {
								if ($lastModified) { //Photo exists and needs updating
									sendLog("Updating: " . $path . $file);
									if (!$preparedStatements["sqlDelPhoto"]->execute(array(":directory_id" => $directoryId, ":filename" => $file)))
										throw new Exception("$file already exists and cannot delete it from the database");
								} else
									sendLog("Adding: " . $path . $file);
								if (!$preparedStatements["sqlInsPhoto"]->execute(array(":directory_id" => $directoryId, ":filename" => $file, ":width" => $sizes[0], ":height" => $sizes[1])))
									throw new Exception("Could not insert $file into the database");
								if (isset($imageinfo["APP13"]) && $preparedStatements["sqlGetPhotoId"]->execute(array(":directory_id" => $directoryId, ":filename" => $file))) {
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

	foreach ($db->query("SELECT directory_id, dirname FROM directory") as list($directoryId, $dirname))
		processDir($directoryId, $dirname, "", $preparedStatements);

	sendLog("finished");
	echo "event: finished\n";
	echo "data: Finished gallery scan\n\n";
?>
