<?php
// auth/register_form_verificacion_kyc.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: register_form.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // MVP: simulamos KYC exitoso
    header('Location: register_form_beneficiario.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Verificación de identidad | KBPPAY</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/forms.css">
  
  <!-- PWA / App-like -->
<link rel="manifest" href="/app/pay/manifest.json">
<meta name="theme-color" content="#0A2540">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  
</head>

<style>
@keyframes fadeIn {
  from {
    opacity:0;
    transform:translateY(6px);
  }
  to {
    opacity:1;
    transform:none;
  }
}
</style>


<body>
    

<div class="auth-wrapper">
  <div class="auth-card">

<div class="auth-logo">
  <img src="../assets/img/kbppay-logo.svg" alt="KBPPAY">
</div>

    <!-- Progress -->
    <div class="progress">
      <div class="progress-bar" style="width: 80%;"></div>
    </div>

    <h2>Verifica tu identidad</h2>
    <p>
      Para cumplir con requisitos regulatorios necesitamos validar tu documento
      de identidad. Solo te tomará unos segundos.
    </p>

    <form method="POST">

      <!-- Zona de carga -->
      <div
      id="kyc-upload"
        style="
        border:2px dashed #d1d5db;
        border-radius:16px;
        padding:28px;
        text-align:center;
        cursor:pointer;
        margin-bottom:20px;
        transition:all .25s ease;
        background:#fafafa;
        "
        >

        <div id="kyc-placeholder">
          <p style="font-weight:600; margin-bottom:6px;">
             Verificar documento de identidad
          </p>
          <p style="font-size:13px; color:#6b7280;">
             DNI o Pasaporte • Proceso seguro y automático
          </p>

        </div>

        <!-- Documento ficticio -->
<div id="kyc-preview" style="display:none;">

  <div
    style="
      background:linear-gradient(135deg,#1e3a8a,#2563eb);
      color:#ffffff;
      border-radius:14px;
      padding:18px;
      text-align:left;
      box-shadow:0 10px 30px rgba(0,0,0,.15);
      animation:fadeIn .35s ease;
    "
  >
    <p style="margin:0;font-size:12px;color:rgba(255,255,255,0.9);">
      Documento de identidad verificado
    </p>

    <p style="margin:6px 0 14px;font-weight:600;font-size:15px;color:rgba(255,255,255,0.95);">
      <?= htmlspecialchars($_SESSION['first_name'] ?? 'Usuario'); ?> • ********
    </p>

    <div style="display:flex;justify-content:space-between;font-size:12px;color:rgba(255,255,255,0.85);">
      <span>KBPPAY</span>
      <span>✔ Verificado</span>
    </div>
  </div>

  <p style="margin-top:12px;font-size:13px;color:#16a34a;font-weight:500;">
    ✔ Documento validado correctamente
  </p>

</div>


      </div>

      <button id="continueBtn" type="submit" disabled>
        Continuar
      </button>

    </form>

    <p style="margin-top:16px;font-size:12px;text-align:center;color:#6b7280;">
      Tus datos se procesan de forma segura y cifrada conforme a normativa KYC.
    </p>


  </div>
</div>

<script>
const uploadBox = document.getElementById('kyc-upload');
const placeholder = document.getElementById('kyc-placeholder');
const preview = document.getElementById('kyc-preview');
const continueBtn = document.getElementById('continueBtn');

uploadBox.addEventListener('mouseenter', () => {
  uploadBox.style.borderColor = '#3259fd';
  uploadBox.style.background = '#f4f7ff';
});

uploadBox.addEventListener('mouseleave', () => {
  uploadBox.style.borderColor = '#d1d5db';
  uploadBox.style.background = '#fafafa';
});

uploadBox.addEventListener('click', () => {
  placeholder.style.display = 'none';
  preview.style.display = 'block';

  continueBtn.disabled = false;
  continueBtn.textContent = 'Continuar verificación';
});

</script>

</body>
</html>
