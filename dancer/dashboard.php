<?php
require_once __DIR__ . '/../includes/auth.php';
require_dancer();

$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dancer Dashboard</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 40px;
            max-width: 600px;
        }

        h1 {
            margin-bottom: 5px;
        }

        p {
            color: #555;
            margin-top: 0;
            margin-bottom: 30px;
        }

        .menu {
            list-style: none;
            padding: 0;
        }

        .menu li {
            margin-bottom: 10px;
        }

        .menu a {
            display: block;
            padding: 12px;
            background: #f4f4f4;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            border: 1px solid #ddd;
            font-weight: bold;
        }

        .menu a:hover {
            background: #e0e0e0;
        }

        .logout {
            margin-top: 30px;
            display: inline-block;
            color: #c00;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <h1>Dancer Dashboard</h1>
    <p>Welcome, <?= htmlspecialchars($user['username']) ?>!</p>

    <ul class="menu">
        <li><a href="items/index.php">Browse Available Items</a></li>
        <li><a href="borrowings/create.php">Borrow an Item</a></li>
    </ul>

    <form action="/SBDTInventory/logout.php" method="POST" style="margin-top: 30px;">
        <?= csrf_field() ?>
        <button type="submit" class="logout">Log out</button>
    </form>
</body>

</html>