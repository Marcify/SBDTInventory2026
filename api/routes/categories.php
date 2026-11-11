<?php
declare(strict_types=1);

// List categories.
route('GET', '/categories', function () {
    api_require_login();

    global $pdo;

    $stmt = $pdo->query(
        'SELECT id, name
         FROM categories
         ORDER BY name ASC'
    );

    $categories = array_map(
        static fn(array $category): array => [
            'id' => (int)$category['id'],
            'name' => $category['name'],
        ],
        $stmt->fetchAll()
    );

    json_success(['categories' => $categories]);
});

// Create a category.
route('POST', '/categories', function () {
    api_require_admin();
    api_require_csrf();

    $name = body('name');

    if (!is_string($name) || trim($name) === '') {
        json_validation([
            'name' => 'Category name is required and must be a string',
        ]);
    }

    $name = trim($name);

    // The database column is VARCHAR(100).
    $length = preg_match_all('/./us', $name);

    if ($length === false || $length > 100) {
        json_validation([
            'name' => 'Category name must not exceed 100 characters',
        ]);
    }

    global $pdo;

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO categories (name) VALUES (?)'
        );

        $stmt->execute([$name]);
    } catch (PDOException $e) {
        // MySQL/MariaDB duplicate-key error.
        if ((int)($e->errorInfo[1] ?? 0) === 1062) {
            json_error('A category with that name already exists', 409);
        }

        throw $e;
    }

    json_success([
        'category' => [
            'id' => (int)$pdo->lastInsertId(),
            'name' => $name,
        ],
    ], 201);
});
