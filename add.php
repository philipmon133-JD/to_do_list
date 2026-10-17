<?php
require 'config.php';

if ($_POST) {
    $title = $_POST['title'];
    $desc = $_POST['description'];

    $sql = "INSERT INTO todo(title, description) VALUES (:title, :description)";
    $pdostatement = $pdo->prepare($sql);
    $result = $pdostatement->execute(
        array(
            ':title' => $title,
            ':description' => $desc
        )
    );

    if ($result) {
        echo "<script>alert('New todo is added successfully'); window.location.href='index.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Create New</title>
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
</head>
<body>
	<div class="card">
		<div class="card-body">
			<h2>Create New ToDo</h2>
			<form action="add.php" method="post">
				<div class="form-group">
					<label>Title</label>
					<input type="text" class="form-control" name="title" value="" required>
				</div>
				<div class="form-group">
					<label>Description</label>
					<textarea name="description" class="form-control" rows="8" cols="80"></textarea>
				</div>
				<div class="form-group">
					<input type="submit" class="btn btn-primary" value="SUBMIT">
					<a href="index.php" type="button" class="btn btn-warning">Back</a>
				</div>
			</form>
		</div>
	</div>
</body>
</html>