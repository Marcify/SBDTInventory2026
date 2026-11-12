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

// Get a single category by ID.
route('GET', '/categories/{id}', function ($id) {
    api_require_login();

    $categoryId = filter_var(
        $id,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($categoryId === false) {
        json_validation([
            'id' => 'Category ID must be a positive integer',
        ]);
    }

    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT c.id, c.name, COUNT(i.id) AS item_count
         FROM categories c
         LEFT JOIN items i ON i.category_id = c.id
         WHERE c.id = ?
         GROUP BY c.id, c.name'
    );

    $stmt->execute([$categoryId]);
    $category = $stmt->fetch();

    if (!$category) {
        json_error('Category not found', 404);
    }

    json_success([
        'category' => [
            'id' => (int)$category['id'],
            'name' => $category['name'],
            'item_count' => (int)$category['item_count'],
        ],
    ]);
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

// Rename a category.
route('PATCH', '/categories/{id}', function ($id) {
    api_require_admin();
    api_require_csrf();

    $categoryId = filter_var(
        $id,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($categoryId === false) {
        json_validation([
            'id' => 'Category ID must be a positive integer',
        ]);
    }

    $data = request_body();

    $unknownFields = array_diff(array_keys($data), ['name']);

    if ($unknownFields) {
        json_validation([
            'fields' => 'Unsupported fields: ' . implode(', ', $unknownFields),
        ]);
    }

    $name = $data['name'] ?? null;

    if (!is_string($name) || trim($name) === '') {
        json_validation([
            'name' => 'Category name is required and must be a string',
        ]);
    }

    $name = trim($name);

    $length = preg_match_all('/./us', $name);

    if ($length === false || $length > 100) {
        json_validation([
            'name' => 'Category name must not exceed 100 characters',
        ]);
    }

    global $pdo;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT id FROM categories WHERE id = ? FOR UPDATE'
        );

        $stmt->execute([$categoryId]);

        if (!$stmt->fetch()) {
            $pdo->rollBack();
            json_error('Category not found', 404);
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE categories SET name = ? WHERE id = ?'
            );

            $stmt->execute([$name, $categoryId]);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                $pdo->rollBack();
                json_error('A category with that name already exists', 409);
            }

            throw $e;
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    json_success([
        'category' => [
            'id' => $categoryId,
            'name' => $name,
        ],
    ]);
});

// Delete a category. Rejected if any item still references it.
route('DELETE', '/categories/{id}', function ($id) {
    api_require_admin();
    api_require_csrf();

    $categoryId = filter_var(
        $id,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($categoryId === false) {
        json_validation([
            'id' => 'Category ID must be a positive integer',
        ]);
    }

    global $pdo;

    try {
        $pdo->beginTransaction();

        // Lock the row so an item cannot be assigned to this
        // category between the usage check and the delete.
        $stmt = $pdo->prepare(
            'SELECT id FROM categories WHERE id = ? FOR UPDATE'
        );

        $stmt->execute([$categoryId]);

        if (!$stmt->fetch()) {
            $pdo->rollBack();
            json_error('Category not found', 404);
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM items WHERE category_id = ?'
        );

        $stmt->execute([$categoryId]);
        $itemCount = (int)$stmt->fetchColumn();

        if ($itemCount > 0) {
            $pdo->rollBack();

            json_error(
                'Category is in use by ' . $itemCount . ' item(s) and cannot be deleted',
                409,
                ['item_count' => $itemCount]
            );
        }

        $stmt = $pdo->prepare(
            'DELETE FROM categories WHERE id = ?'
        );

        $stmt->execute([$categoryId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    json_success(['message' => 'Category deleted']);
});
