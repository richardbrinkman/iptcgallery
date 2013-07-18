<?php require(TEMPLATE_PATH . "header.php") ?>

<h1>Add directory to gallery</h1>
<form method="post" action="<?php echo $_SERVER["PHP_SELF"]?>">
	<input type="text" name="directory">
	<input type="submit">
</form>

<?php require(TEMPLATE_PATH . "footer.php") ?>
