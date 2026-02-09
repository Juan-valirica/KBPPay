<?php
// auth/register_form_verification.php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // MVP: aceptamos cualquier código y seguimos
    header('Location: register_form_amount.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Verificación de cuenta | KBPPAY</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/forms.css">

<!-- PWA / App-like -->
<link rel="manifest" href="/app/pay/manifest.json">
<meta name="theme-color" content="#0A2540">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

</head>
<body>

<div class="auth-wrapper">
  <div class="auth-card">

<div class="auth-logo">
  <img src="../assets/img/kbppay-logo.svg" alt="KBPPAY">
</div>

    <!-- Progress -->
    <div class="progress">
      <div class="progress-bar" style="width: 35%;"></div>
    </div>

    <h2>Verifica tu cuenta</h2>
    <p>
      Te enviamos un código de verificación a tu correo electrónico
      y a tu número de teléfono.
    </p>

    <form method="POST" action="">

      <div class="form-group">
        <label for="verification_code">Código de verificación</label>
        <input
          type="text"
          id="verification_code"
          name="verification_code"
          placeholder="000000"
          maxlength="6"
          required
          style="letter-spacing:4px; text-align:center;">
      </div>

      <button type="submit">Continuar</button>

    </form>

    <p style="margin-top:16px; font-size:13px; text-align:center;">
      ¿No recibiste el código? Podrás solicitar uno nuevo en el siguiente paso.
    </p>

  </div>
</div>


</body>
</html>

