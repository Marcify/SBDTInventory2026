<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_csrf();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        $error = 'Category name is required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$name]);

            header('Location: index.php');
            exit();
        } catch (PDOException $e) {
            // This catches the UNIQUE constraint error if they type a duplicate name
            $error = 'That category already exists.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Category</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 40px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        input[type="text"] {
            padding: 8px;
            width: 300px;
        }

        button {
            padding: 10px 15px;
            background: #333;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 3px;
        }

        .error {
            color: red;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
    <h1>Add New Category</h1>
    <a href="index.php">&larr; Back to Categories</a>
    <br><br>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Category Name</label>
            <input type="text" name="name" required placeholder="e.g. Costumes, Props, Shoes">
        </div>

        <button type="submit">Save Category</button>
    </form>
</body>

</html>