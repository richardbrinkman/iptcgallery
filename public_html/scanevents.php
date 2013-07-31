<?php
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');

	require_once("config.php");

	set_time_limit(0); //allow this script to run al long as it takes

	$db = classes\Database::getInstance();

	$preparedStatements = array(
		"sqlGetPhotoId" => $db->prepare("SELECT photo_id FROM photo WHERE directory_id=:directory_id AND filename=:filename"),
		"sqlGetLastModified" => $db->prepare("SELECT UNIX_TIMESTAMP(last_modified) FROM photo WHERE directory_id=:directory_id AND filename=:filename"),
		"sqlGetTag" => $db->prepare("SELECT tag_id FROM tag WHERE iptc_id=:iptc_id AND value=:value"),
		"sqlDelPhoto" => $db->prepare("DELETE FROM photo WHERE directory_id=:directory_id AND filename=:filename"),
		"sqlInsPhoto" => $db->prepare("INSERT INTO photo(directory_id, filename, width, height) VALUES(:directory_id, :filename, :width, :height)"),
		"sqlInsTag" => $db->prepare("INSERT INTO tag(iptc_id, value) VALUES(:iptc_id, :value)"),
		"sqlInsLink" => $db->prepare("INSERT INTO link(tag_id, photo_id) VALUES(:tag_id, :photo_id)")
	);
	$preparedStatements["sqlGetPhotoId"]->setFetchMode(\PDO::FETCH_COLUMN, 0);
	$preparedStatements["sqlGetLastModified"]->setFetchMode(\PDO::FETCH_COLUMN, 0);
	$preparedStatements["sqlGetTag"]->setFetchMode(\PDO::FETCH_COLUMN, 0);

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
					//Get date of last modification from the database if the photo already exists
					if ($preparedStatements["sqlGetLastModified"]->execute(array(":directory_id" => $directoryId, ":filename" => $file))) {
						$lastModified = $preparedStatements["sqlGetLastModified"]->fetch();
						if ($lastModified < filemtime($root . $file)) {
							//Retrieve meta-information about the photo from the file
							$sizes = getimagesize($root . $file, $imageinfo);
							if ($sizes["mime"] == "image/jpeg") {
								if ($lastModified) { //Photo exists and needs updating
									sendLog("Updating: " . $root . $file);
									//Delete the old photo from the database
									if (!$preparedStatements["sqlDelPhoto"]->execute(array(":directory_id" => $directoryId, ":filename" => $file)))
										throw new Exception("$file already exists and cannot delete it from the database");
								} else
									sendLog("Adding: " . $root . $file);

								//Insert photo into the database
								if (!$preparedStatements["sqlInsPhoto"]->execute(array(":directory_id" => $directoryId, ":filename" => $file, ":width" => $sizes[0], ":height" => $sizes[1])))
									throw new Exception("Could not insert $file into the database");

								//Check if photo has embedded IPTC meta-data and if so, retrieve the photo_id
								if (isset($imageinfo["APP13"]) && $preparedStatements["sqlGetPhotoId"]->execute(array(":directory_id" => $directoryId, ":filename" => $file))) {
									$photoId = $preparedStatements["sqlGetPhotoId"]->fetch();
									$iptc = iptcparse($imageinfo["APP13"]);
									foreach ($iptc as $iptcId => $values) 
										foreach ($values as $value) {
											//First check if the (iptcID,value)-tuple already exists in the database
											if ($preparedStatements["sqlGetTag"]->execute(array(":iptc_id" => $iptcId, ":value" => $value)))
												$tagId = $preparedStatements["sqlGetTag"]->fetch();
											else
												$tagId = false;

											//If not than add it and get the new tagId
											if (!$tagId) {
												$preparedStatements["sqlInsTag"]->execute(array(":iptc_id" => $iptcId, ":value" => $value));
												if ($preparedStatements["sqlGetTag"]->execute(array(":iptc_id" => $iptcId, ":value" => $value)))
													$tagId = $preparedStatements["sqlGetTag"]->fetch();
											}

											//Link the photo and the tag togetcher
											$preparedStatements["sqlInsLink"]->execute(array(":tag_id" => $tagId, ":photo_id" => $photoId));
										}
								}
							}
						}
					}
				}
			}
	}

	foreach ($db->query("SELECT directory_id, dirname FROM directory") as list($directoryId, $dirname))
		processDir($directoryId, $dirname, "", $preparedStatements);
	
	//Delete tags that are no longer in use
	$db->exec("DELETE FROM tag WHERE tag_id NOT IN (SELECT tag_id FROM link)");

	sendLog("finished");
	echo "event: finished\n";
	echo "data: Finished gallery scan\n\n";
?>
