<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/routes/auth.php';
require_once __DIR__ . '/routes/items.php';
// ... one require per resource file

dispatch();
