<?php

require_once '../config/database.php';

foreach ([['Admin User','admin','Admin123!','admin'],['Dancer User','dancer','Dancer123!','dancer']] as [$name,$username,$password,$role]) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if (!$stmt->fetch()) {
        $insert = $pdo->prepare('INSERT INTO users (name,username,password,role) VALUES (?,?,?,?)');
        $insert->execute([$name,$username,password_hash($password,PASSWORD_DEFAULT),$role]);
    }
}
echo 'Demo users ready. Delete seed_users.php now.';
