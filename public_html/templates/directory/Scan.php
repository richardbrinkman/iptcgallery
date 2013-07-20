<?php require(TEMPLATE_PATH . "header.php")?>

<progress id="progressbar"></progress>
<div id="output"></div>

<script>
	var events = new EventSource("http://<?php echo $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] ?>/scanevents.php");

	events.addEventListener("message", function(event) {
		var element = document.getElementById("output");
		element.innerHTML = event.data + "<br>" + element.innerHTML;
	}, false);

	events.addEventListener("finished", function(event) {
		document.getElementById("progressbar").style.visibility = "hidden";
		events.close();
	}, false);
</script>

<?php require(TEMPLATE_PATH . "footer.php")?>
