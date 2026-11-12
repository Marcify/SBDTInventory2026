<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/routes/auth.php';
require_once __DIR__ . '/routes/categories.php';
require_once __DIR__ . '/routes/items.php';
require_once __DIR__ . '/routes/borrowings.php';
require_once __DIR__ . '/routes/stock_movements.php';

dispatch();
