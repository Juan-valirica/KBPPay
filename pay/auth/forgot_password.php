<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingrese un correo válido.';
    } elseif (mb_strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Las contraseñas no coinciden.';
    } else {

        // Verificar usuario
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = 'No existe una cuenta con ese correo.';
        } else {
            // Actualizar contraseña
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
              UPDATE users
              SET password = ?
              WHERE id = ?
              LIMIT 1
            ");
            $stmt->execute([$hash, $user['id']]);

            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cambiar contraseña | KBPPAY</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="stylesheet" href="../assets/css/forms.css">

  <link rel="manifest" href="/app/pay/manifest.json">
  <meta name="theme-color" content="#F2F6F8">
</head>

<body>

<div class="auth-wrapper">
  <div class="auth-card">

    <div class="auth-logo">
      <img src="/app/pay/assets/img/kbppay-logo.svg" alt="KBPPAY">
    </div>

    <?php if ($success): ?>

<div class="success-wrapper">
  <div class="success-visual">
    <img
      src="../assets/img/kbppay-success.svg"
      alt="Contraseña actualizada"
      style="width:36px;height:36px;"
    >
  </div>
</div>


      <h2>Contraseña actualizada</h2>
      <p>Ya puede acceder a su cuenta con la nueva contraseña.</p>

      <a href="login.php" class="cta-secondary">
        <strong>Ir al inicio de sesión</strong>
        <span>Acceder a KBP Pay</span>
      </a>

    <?php else: ?>

      <h2>Cambiar contraseña</h2>
      <p>Introduzca su correo y defina una nueva contraseña.</p>

      <?php if ($error): ?>
        <div class="form-feedback error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">

        <div class="form-group">
          <label>Correo electrónico</label>
          <input
            type="email"
            name="email"
            required
            placeholder="tu@email.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
          >
        </div>

        <div class="form-group">
          <label>Nueva contraseña</label>
          <input
            type="password"
            name="password"
            required
            placeholder="Mínimo 8 caracteres"
          >
        </div>

        <div class="form-group">
          <label>Confirmar contraseña</label>
          <input
            type="password"
            name="password_confirm"
            required
            placeholder="Repita la contraseña"
          >
        </div>

        <button type="submit">
          Cambiar contraseña
        </button>

      </form>

      <div class="auth-divider"></div>

      <a href="login.php" class="cta-secondary">
        <strong>Volver al login</strong>
        <span>Acceder con mi cuenta</span>
      </a>

    <?php endif; ?>

  </div>
</div>

</body>
</html>
