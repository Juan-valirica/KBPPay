<?php
declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'u594657857_kbppay_pay');
define('DB_USER', 'u594657857_kbppay_user');
define('DB_PASS', 'MysqJapon0L.');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        )
    );
} catch (PDOException $e) {
    die('ERROR DB: ' . $e->getMessage());
}
