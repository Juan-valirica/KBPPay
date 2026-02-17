<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || strlen($password) < 8) {
    $_SESSION['login_error'] = 'Credenciales inválidas';
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, password, is_active, info_complete
    FROM users
    WHERE email = :email
    LIMIT 1
");
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

if (
    !$user ||
    !$user['is_active'] ||
    !password_verify($password, $user['password'])
) {
    $_SESSION['login_error'] = 'Credenciales inválidas';
    header('Location: login.php');
    exit;
}

/* LOGIN OK */
session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['logged']  = true;

$pdo->prepare("
    UPDATE users SET last_login = NOW() WHERE id = ?
")->execute([$user['id']]);

if (!$user['info_complete']) {
    
    header('Location: ../dashboard.php');
}
exit;
