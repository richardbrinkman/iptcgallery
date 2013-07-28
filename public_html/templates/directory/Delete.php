<?php require(TEMPLATE_PATH . "header.php") ?>

<script>
	function toggle() {
		var toggleAll = document.getElementById("toggleAll");
		var allCheckboxes = document.querySelectorAll("#directorytable input[type='checkbox']");
		for (var i=0; i<allCheckboxes.length; i++)
			allCheckboxes[i].checked = toggleAll.checked;
	}
</script>
<h1>Delete directory from the gallery</h1>
<form method="post" action="<?=$_SERVER["PHP_SELF"] ?>">
	<table id="directorytable">
		<tr>
			<th>Directory</th>
			<th>Delete</th>
		</tr>
		<?php foreach ($directories as $id => $directory): ?>
		<tr>
			<td><?=$directory?></td>
			<td>
				<input type="checkbox" name="directories[]" value="<?=$id?>">
			</td>
		</tr>
		<?php endforeach ?>
		<tr>
			<td>&nbsp;</td>
			<td>
				<input id="toggleAll" type="checkbox" onchange="toggle()">
				Select all
			</td>
	</table>
	<input type="submit" value="Delete selected">
</form>

<?php require(TEMPLATE_PATH . "footer.php") ?>

