
<?php
require 'config.php';

$pdostatement = $pdo->prepare(
    'SELECT * FROM todo ORDER BY id DESC'
);

$pdostatement->execute();

$result = $pdostatement->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Home Page</title>

    <link
        rel="stylesheet"
        type="text/css"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    >
</head>

<body>

    <div class="container mt-5">

        <div class="card">

            <div class="card-body">

                <h2 class="mb-4">
                    Todo Home Page
                </h2>

                <div class="mb-3">
                    <a
                        href="add.php"
                        class="btn btn-success"
                    >
                        Create New
                    </a>
                </div>

                <table class="table table-striped table-bordered">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php

                        $i = 1;

                        if ($result) {

                            foreach ($result as $value) {

                        ?>

                                <tr>
                                    <td>
                                        <?php echo $i; ?>
                                    </td>

                                    <td>
                                        <?php echo $value['title']; ?>
                                    </td>

                                    <td>
                                        <?php echo $value['description']; ?>
                                    </td>

                                    <td>
                                        <?php echo $value['created_at']; ?>
                                    </td>

                                    <td>
                                        <a
                                            type="button"
                                            class="btn btn-warning btn-sm"
                                            href="edit.php?id=<?php echo $value['id'];?>"
                                        >
                                            Edit
                                        </a>

                                        <a
                                            type="button"
                                            class="btn btn-danger btn-sm"
                                            style="color: white;"
                                            href="delete.php?id=<?php echo $value['id'] ?>"

                                        >
                                            Delete
                                        </a>
                                    </td>
                                </tr>

                        <?php

                                $i++;
                            }
                        }

                        ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</body>

</html>

