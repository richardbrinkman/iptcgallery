<?php
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');

	session_start();

	require_once("config.php");

	set_time_limit(0); //allow this script to run al long as it takes

	$db = classes\Database::getInstance();

	function sendLog($message) {
		echo "retry: 3600000\n";
		echo "data: $message\n\n";
		ob_flush();
		flush();
	}

	$sqlDelete = $db->prepare("DELETE FROM photo WHERE photo_id=?");

	foreach ($db->query("SELECT photo_id, CONCAT(dirname, filename) AS pathname FROM directory NATURAL JOIN photo ORDER BY pathname") as list($photoId, $pathname))
		if (!file_exists($pathname)) {
			sendLog("Pruning:  $pathname");
			$sqlDelete->execute(array($photoId));
		}
	
	//Delete tags that are no longer in use
	$db->exec("DELETE FROM tag WHERE tag_id NOT IN (SELECT tag_id FROM link)");

	sendLog("finished");
	echo "event: finished\n";
	echo "data: Finished pruning\n\n";

	session_destroy(); //Forget about any stored query condition or dropdown menu
?>
