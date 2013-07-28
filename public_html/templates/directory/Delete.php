<?php require(TEMPLATE_PATH . "header.php") ?>

<h1>Delete directory from the gallery</h1>
<form method="post" action="<?=$_SERVER["PHP_SELF"] ?>">
	<table>
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
	</table>
	<input type="submit" value="Delete selected">
</form>

<?php require(TEMPLATE_PATH . "footer.php") ?>

