<?php

require_once __DIR__ . '/../config/database.php';

function seedSbdtInventory(PDO $conn): array
{
    $catalog = [
        'Costumes' => [
            ['Serpintina', 15],
            ['Tapis', 20],
            ['Panuelo', 18],
            ['Petticoat', 12],
            ['Sapatilya', 25],
            ['Traje', 10],
            ['Barong', 15],
            ['Kamisa', 20],
            ['Pants', 25],
            ['Alampay', 15],
            ['Bahay', 8],
            ['Malong', 12],
        ],
        'Props' => [
            ['Castanets', 20],
            ['Bilao', 10],
            ['Sako', 8],
            ['Kawayan', 30],
            ['Buho', 12],
            ['Anahao', 15],
            ['Kumpas', 10],
            ['Kupya', 15],
        ],
        'Accessories' => [
            ['Paineta', 15],
            ['Necklace', 20],
            ['Scapular', 12],
            ['Brooch', 15],
            ['Cross', 20],
            ['Choker', 15],
            ['Haircomb', 18],
            ['Crown', 8],
            ['Belo', 12],
            ['Flowers', 30],
            ['Bracelet', 20],
            ['Headdress', 15],
        ],
    ];

    $created = ['categories' => [], 'items' => []];
    $skipped = ['categories' => [], 'items' => []];

    $conn->beginTransaction();

    try {
        $findCategory = $conn->prepare('SELECT id FROM categories WHERE name = ?');
        $insertCategory = $conn->prepare('INSERT INTO categories (name) VALUES (?)');

        $findItem = $conn->prepare('SELECT id FROM items WHERE category_id = ? AND name = ?');
        // borrowed_stock omitted -> uses DEFAULT 0
        $insertItem = $conn->prepare(
            'INSERT INTO items
                (category_id, name, total_stock, available_stock, damaged_stock, lost_stock)
             VALUES (?, ?, ?, ?, 0, 0)'
        );

        foreach ($catalog as $categoryName => $items) {
            $findCategory->execute([$categoryName]);
            $existingCategory = $findCategory->fetch();

            if ($existingCategory) {
                $categoryId = (int) $existingCategory['id'];
                $skipped['categories'][] = $categoryName;
            } else {
                $insertCategory->execute([$categoryName]);
                $categoryId = (int) $conn->lastInsertId();
                $created['categories'][] = $categoryName;
            }

            foreach ($items as $item) {
                $itemName = $item[0];
                $quantity = $item[1];

                $findItem->execute([$categoryId, $itemName]);
                if ($findItem->fetch()) {
                    $skipped['items'][] = $itemName . ' (' . $categoryName . ')';
                    continue;
                }

                $insertItem->execute([$categoryId, $itemName, $quantity, $quantity]);
                $created['items'][] = $itemName . ' (' . $categoryName . ') — quantity ' . $quantity;
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    return ['created' => $created, 'skipped' => $skipped];
}

function seedReport(array $result): string
{
    $lines = ['SBDT Inventory sample data', ''];
    $labels = ['categories' => 'Category', 'items' => 'Item'];

    foreach ($labels as $key => $label) {
        foreach ($result['created'][$key] as $name) {
            $lines[] = 'Created ' . $label . ': ' . $name;
        }
        foreach ($result['skipped'][$key] as $name) {
            $lines[] = 'Skipped ' . $label . ': ' . $name . ' (already exists)';
        }
    }

    $createdCount = 0;
    $skippedCount = 0;
    foreach ($labels as $key => $label) {
        $createdCount += count($result['created'][$key]);
        $skippedCount += count($result['skipped'][$key]);
    }

    $lines[] = '';
    $lines[] = 'Finished. Created ' . $createdCount . ' record(s). Skipped ' . $skippedCount . ' record(s) that already existed.';
    $lines[] = 'Existing stock quantities, borrowings, returns, and stock history were not changed.';
    $lines[] = '';
    $lines[] = 'After the items exist, delete seed_data.php so it cannot be opened in a browser.';

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

$script = PHP_SAPI === 'cli'
    ? ($_SERVER['argv'][0] ?? '')
    : ($_SERVER['SCRIPT_FILENAME'] ?? '');
$runningDirectly = $script !== '' && realpath($script) === realpath(__FILE__);

if ($runningDirectly) {
    if (PHP_SAPI !== 'cli') {
        header('Content-Type: text/plain; charset=utf-8');
    }

    try {
        global $pdo;
        echo seedReport(seedSbdtInventory($pdo));
    } catch (Throwable $e) {
        if (PHP_SAPI !== 'cli') {
            http_response_code(500);
        }
        echo "Seeding failed. Changes from this run were rolled back.\n";
        echo $e->getMessage() . "\n";
        exit(1);
    }
}