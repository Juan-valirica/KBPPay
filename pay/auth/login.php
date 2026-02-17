<?php
session_start();

// Si ya hay sesión activa, no mostramos login
if (!empty($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Acceso | KBPPAY</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- CSS global de formularios KBPPAY -->
  <link rel="stylesheet" href="../assets/css/forms.css">

  <!-- PWA / App-like -->
 <link rel="manifest" href="/app/pay/manifest.json">
<meta name="theme-color" content="#F2F6F8">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">

<link rel="apple-touch-icon" sizes="180x180" href="/app/pay/assets/icons/icon-180.png">
<link rel="apple-touch-icon" sizes="167x167" href="/app/pay/assets/icons/icon-167.png">
<link rel="apple-touch-icon" sizes="152x152" href="/app/pay/assets/icons/icon-152.png">

</head>

<body>

<div class="auth-wrapper">

  <div class="auth-card">

    <!-- Logo -->
    <div class="auth-logo">
      <img src="/app/pay/assets/img/kbppay-logo.svg" alt="KBPPAY">
    </div>

    <!-- Título -->
    <h2>Accede a tu cuenta</h2>
    <p>Ingresa con tu correo y contraseña</p>

    <!-- Error de login -->
    <?php if (!empty($_SESSION['login_error'])): ?>
      <div class="form-feedback error">
        <?= htmlspecialchars($_SESSION['login_error']) ?>
      </div>
      <?php unset($_SESSION['login_error']); ?>
    <?php else: ?>
      <div class="form-feedback"></div>
    <?php endif; ?>

    <!-- Formulario -->
    <form method="POST" action="login_process.php" autocomplete="off">

      <div class="form-group">
        <label for="email">Correo electrónico</label>
        <input
          type="email"
          id="email"
          name="email"
          required
          placeholder="tu@email.com"
        >
      </div>

      <div class="form-group">
        <label for="password">Contraseña</label>
        <input
          type="password"
          id="password"
          name="password"
          required
          placeholder="••••••••"
        >
      </div>

      <button type="submit">
        Entrar
      </button>

    </form>
    
    
    
    <!-- Links secundarios -->
    <div class="auth-links" style="margin-top:16px;text-align:center;">
<a href="forgot_password.php" style="font-size:13px;color:var(--muted);text-decoration:none;">
  ¿Olvidaste tu contraseña?
</a>

    </div>
    
    
<!-- CTA para nuevos usuarios -->
<div class="auth-divider">
  
</div>

<a href="https://kbppay.es/app/pay/auth/register_form.php" class="cta-secondary">
    <span>¿Primera vez en KBPPAY?</span>
  <strong>Haz tu primer envío rápido y seguro</strong>
 
</a>




  </div>

</div>

</body>
</html>
