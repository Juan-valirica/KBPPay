<?php
/**
 * Admin Middleware — KBP PAY
 * Restringe el acceso ÚNICAMENTE al usuario hola@kbppay.es
 */

// Primero valida sesión activa (incluye session_start)
require_once __DIR__ . '/middleware.php';

// Conectar a base de datos si aún no está disponible
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/database.php';
}

// Verificar que el usuario logueado es el administrador
$_stmtAdm = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
$_stmtAdm->execute([$_SESSION['user_id']]);
$_adminRow = $_stmtAdm->fetch();

if (!$_adminRow || strtolower(trim($_adminRow['email'])) !== 'hola@kbppay.es') {
    // No es el administrador — redirigir al dashboard sin exponer nada
    header('Location: /app/pay/dashboard.php');
    exit;
}
