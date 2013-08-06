<?php
	header("Content-type: image/jpeg");

	require_once("config.php");

	$photoId = $_GET["photo_id"];
	if (isset($photoId)) {
		$db = classes\Database::getInstance();

		//Remove all thumbnails from photo that are modified afterwards
		$db->exec("
			DELETE FROM thumbnail 
			WHERE photo_id IN (SELECT thumbnail.photo_id 
			                   FROM thumbnail NATURAL JOIN photo 
			                   WHERE thumbnail.last_modified < photo.last_modified)
		");

		//Check if thumbnail is already in the database
		$sqlGetThumbnail = $db->prepare("SELECT image FROM thumbnail WHERE photo_id=?");
		$sqlGetThumbnail->bindColumn(1, $blob, \PDO::PARAM_LOB);
		if ($sqlGetThumbnail->execute(array($photoId)) && $sqlGetThumbnail->fetch())
			echo $blob;
		else {
			//Get the filename of the origional photo from the database
			$sqlGetImage = $db->prepare("SELECT CONCAT(dirname,filename),width,height FROM photo NATURAL JOIN directory WHERE photo_id=:photo_id");
			if ($sqlGetImage->execute(array("photo_id" => $photoId)) && $row = $sqlGetImage->fetch()) {
				list($filename, $width, $height) = $row;
				$image = new \IMagick($filename);
				if ($image->thumbnailimage(THUMBNAIL_SIZE, THUMBNAIL_SIZE, true)) {
					echo $image;
					$blob = $image->getImageBlob();
					$sqlInsertThumbnail = $db->prepare("INSERT INTO thumbnail(photo_id,width,height,image) VALUES(:photo_id, :width, :height, :image)");
					$sqlInsertThumbnail->bindParam("photo_id", $photoId, \PDO::PARAM_INT);
					$sqlInsertThumbnail->bindValue("width", $image->getImageWidth(), \PDO::PARAM_INT);
					$sqlInsertThumbnail->bindValue("height", $image->getImageHeight(), \PDO::PARAM_INT);
					$sqlInsertThumbnail->bindParam("image", $blob, \PDO::PARAM_LOB);
					$sqlInsertThumbnail->execute();
				}
			}
		}
	}
?>
