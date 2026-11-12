<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_csrf();

// Fetch categories for the dropdown
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmt->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $total_stock = (int)($_POST['total_stock'] ?? 0);

    if ($name === '' || $category_id === '' || $total_stock < 1) {
        $error = 'Please fill in all fields and ensure total stock is at least 1.';
    } else {
        // Insert the item. Available stock starts the same as total stock.
        $sql = "INSERT INTO items (category_id, name, total_stock, available_stock) 
                VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$category_id, $name, $total_stock, $total_stock]);

        header('Location: index.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add New Item</title>
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

        input[type="text"],
        input[type="number"],
        select {
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
    <h1>Add New Item</h1>
    <a href="index.php">&larr; Back to Items List</a>
    <br><br>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Item Name</label>
            <input type="text" name="name" required>
        </div>

        <div class="form-group">
            <label>Category</label>
            <select name="category_id" required>
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Total Stock</label>
            <input type="number" name="total_stock" min="1" required>
        </div>

        <button type="submit">Save Item</button>
    </form>
</body>

</html>