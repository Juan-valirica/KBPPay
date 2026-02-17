<?php ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// auth/register.php

require_once __DIR__ . '/../config/database.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register_form.php');
    exit;
}

// 1️⃣ Recoger datos
$firstName  = trim($_POST['first_name'] ?? '');
$lastName   = trim($_POST['last_name'] ?? '');
$email      = trim($_POST['email'] ?? '');
$password   = $_POST['password'] ?? '';
$country    = $_POST['country'] ?? '';
$phone      = trim($_POST['phone'] ?? '');
$birthdate  = $_POST['birthdate'] ?? '';

// Guardar datos para repoblar (UX premium)
$_SESSION['register_old'] = $_POST;

// 2️⃣ Validaciones básicas
if (
    empty($firstName) ||
    empty($lastName) ||
    empty($email) ||
    empty($password) ||
    empty($country) ||
    empty($phone) ||
    empty($birthdate)
) {
    $_SESSION['register_error'] = 'Todos los campos son obligatorios';
    header('Location: register_form.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['register_error'] = 'El email no tiene un formato válido';
    header('Location: register_form.php');
    exit;
}

if (strlen($password) < 8) {
    $_SESSION['register_error'] = 'La contraseña debe tener al menos 8 caracteres';
    header('Location: register_form.php');
    exit;
}

// Teléfono internacional
if (!preg_match('/^\+\d{10,15}$/', $phone)) {
    $_SESSION['register_error'] = 'Usa un teléfono en formato internacional, por ejemplo +34600123456';
    header('Location: register_form.php');
    exit;
}

// 3️⃣ Hashear contraseña
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// 4️⃣ Verificar si email ya existe
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);

if ($stmt->fetch()) {
    $_SESSION['register_error'] = 'Este email ya está registrado';
    header('Location: register_form.php');
    exit;
}

// 5️⃣ Insertar usuario
$sql = "
INSERT INTO users 
(first_name, last_name, email, password, country, phone, birthdate, created_at)
VALUES
(:first_name, :last_name, :email, :password, :country, :phone, :birthdate, NOW())
";

$stmt = $pdo->prepare($sql);

$success = $stmt->execute([
    ':first_name' => $firstName,
    ':last_name'  => $lastName,
    ':email'      => $email,
    ':password'   => $hashedPassword,
    ':country'    => $country,
    ':phone'      => $phone,
    ':birthdate'  => $birthdate
]);

if (!$success) {
    $_SESSION['register_error'] = 'No se pudo crear tu cuenta. Intenta nuevamente.';
    header('Location: register_form.php');
    exit;
}

// 6️⃣ ID del usuario creado
$userId = $pdo->lastInsertId();

// 7️⃣ Crear sesión base del usuario
$_SESSION['user_id'] = $userId;
$_SESSION['first_name'] = $firstName;

// 8️⃣ Onboarding: estado inicial (FUENTE DE VERDAD)
$_SESSION['onboarding'] = 'created';

// 9️⃣ Redirigir SIEMPRE al flujo de onboarding
header('Location: register_form_verification.php');
exit;
