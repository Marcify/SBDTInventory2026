<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit();
}

// Fetch the item
$stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    die("Item not found.");
}

// Fetch categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmt->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $new_total = (int)($_POST['total_stock'] ?? 0);

    if ($name === '' || $category_id === '' || $new_total < 0) {
        $error = 'Please fill in all fields.';
    } else {
        // If total stock increased, we need to increase available stock too
        $difference = $new_total - $item['total_stock'];
        $new_available = $item['available_stock'] + $difference;

        $sql = "UPDATE items SET name = ?, category_id = ?, total_stock = ?, available_stock = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $category_id, $new_total, $new_available, $id]);

        // If stock was added, log it in the history!
        if ($difference > 0) {
            $reason = "Added new stock via item edit";
            $stmt = $pdo->prepare("INSERT INTO stock_movements (item_id, user_id, type, quantity, reason) VALUES (?, ?, 'IN', ?, ?)");
            $stmt->execute([$id, $_SESSION['user_id'], $difference, $reason]);
        }

        header('Location: index.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Item</title>
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
    <h1>Edit Item</h1>
    <a href="index.php">&larr; Back to Items List</a>
    <br><br>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Item Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($item['name']) ?>" required>
        </div>

        <div class="form-group">
            <label>Category</label>
            <select name="category_id" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $item['category_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Total Stock (Changing this will adjust available stock)</label>
            <input type="number" name="total_stock" min="0" value="<?= $item['total_stock'] ?>" required>
        </div>

        <button type="submit">Update Item</button>
    </form>
</body>

</html>