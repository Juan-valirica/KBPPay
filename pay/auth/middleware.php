<?php
session_start();

// ÚNICA VALIDACIÓN: sesión activa
if (empty($_SESSION['user_id'])) {
    header('Location: /app/pay/auth/login.php');
    exit;
}
