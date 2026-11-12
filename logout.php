<?php
session_start();
require_once __DIR__ . '/includes/auth.php';

require_login();
require_csrf();

session_unset();
session_destroy();
header('Location: /SBDTInventory/index.php');
exit();
