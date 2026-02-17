<?php
// auth/register_form_metodo_pago.php
session_start();
require_once __DIR__ . '/../config/database.php';

// Seguridad básica
if (!isset($_SESSION['user_id'])) {
    header('Location: register_form.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];


// Última transacción del usuario
$stmt = $pdo->prepare("
  SELECT amount, currency, commission, total_to_pay, amount_received
  FROM transactions
  WHERE user_id = ?
  ORDER BY created_at DESC
  LIMIT 1
");
$stmt->execute([$userId]);
$tx = $stmt->fetch(PDO::FETCH_ASSOC);

// Último beneficiario
// Datos del envío (desde sesión, aún no persistidos)
$tx = $_SESSION['tx_draft'] ?? null;

$tx = $tx ?: [
  'amount' => 0,
  'currency' => '',
  'commission' => 0,
  'total_to_pay' => 0,
  'amount_received' => 0
];


$tx = $tx ?: [
  'amount' => 0,
  'currency' => '',
  'commission' => 0,
  'total_to_pay' => 0,
  'amount_received' => 0
];

$beneficiary = $beneficiary ?: [
  'first_name' => '',
  'last_name' => ''
];


// En este MVP NO guardamos método de pago.
// Solo validamos que haya selección y consentimiento para continuar.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $paymentMethod = $_POST['payment_method'] ?? '';
    $confirmed     = isset($_POST['confirm']);

    $errors = [];

    if (!in_array($paymentMethod, ['card', 'bank'], true)) {
        $errors[] = 'Selecciona un método de pago';
    }

    if (!$confirmed) {
        $errors[] = 'Debes confirmar los datos para continuar';
    }

    if (empty($errors)) {
        // En un MVP no guardamos nada sensible aquí
        header('Location: register_form_confirmation.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Método de pago | KBPPAY</title>
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
      <div class="progress-bar" style="width: 95%;"></div>
    </div>

    <h2>Método de pago</h2>
    <p>Selecciona cómo deseas pagar tu envío.</p>

    <?php if (!empty($errors)): ?>
      <div style="background:#fff1f2;color:#991b1b;padding:12px;border-radius:10px;margin-bottom:16px;font-size:14px;">
        <strong>Revisa lo siguiente:</strong>
        <ul style="margin:8px 0 0 18px;">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST">

      <!-- MÉTODO DE PAGO -->
<div class="form-group">
  <label
    style="
      display:flex;
      align-items:center;
      gap:10px;
      padding:16px;
      border-radius:14px;
      border:1.5px solid #e5e7eb;
      background:#fafafa;
      cursor:pointer;
      transition:all .2s ease;
    "
    onmouseenter="this.style.borderColor='#3259fd';this.style.background='#f4f7ff';"
    onmouseleave="this.style.borderColor='#e5e7eb';this.style.background='#fafafa';"
  >
    <input
      type="radio"
      name="payment_method"
      value="bank"
      required
      style="
        width:16px;
        height:16px;
        accent-color:#3259fd;
        flex-shrink:0;
      "
    >

    <div>
      <div style="font-weight:600;color:#111827;">
        Tarjeta de débito / crédito
      </div>
      <div style="font-size:13px;color:#6b7280;margin-top:2px;">
        Pago inmediato y seguro
      </div>
    </div>
  </label>
</div>




<div class="form-group">
  <label
    style="
      display:flex;
      align-items:center;
      gap:10px;
      padding:16px;
      border-radius:14px;
      border:1.5px solid #e5e7eb;
      background:#fafafa;
      cursor:pointer;
      transition:all .2s ease;
    "
    onmouseenter="this.style.borderColor='#3259fd';this.style.background='#f4f7ff';"
    onmouseleave="this.style.borderColor='#e5e7eb';this.style.background='#fafafa';"
  >
    <input
      type="radio"
      name="payment_method"
      value="card"
      required
      style="
        width:16px;
        height:16px;
        accent-color:#3259fd;
        flex-shrink:0;
      "
    >

    <div>
      <div style="font-weight:600;color:#111827;">
        Transferencia bancaria
      </div>
      <div style="font-size:13px;color:#6b7280;margin-top:4px;">
        Transferencia inmediata
      </div>
    </div>
  </label>
</div>




      <!-- RESUMEN DEL ENVÍO -->
<div class="form-group" style="background:#f6f8fb;padding:16px;border-radius:14px;">
  <strong>Resumen del envío</strong>

  <div style="display:flex;justify-content:space-between;margin-top:12px;">
    <span>Beneficiario</span>
    <span>
      <?= htmlspecialchars($beneficiary['first_name'].' '.$beneficiary['last_name']) ?>
    </span>
  </div>

  <div style="display:flex;justify-content:space-between;">
    <span>Monto enviado</span>
    <span>
      <?= number_format($tx['amount'],2) ?> <?= htmlspecialchars($tx['currency']) ?>
    </span>
  </div>

  <div style="display:flex;justify-content:space-between;">
    <span>Comisión</span>
    <span>
      <?= number_format($tx['commission'],2) ?> <?= htmlspecialchars($tx['currency']) ?>
    </span>
  </div>

  <div style="display:flex;justify-content:space-between;">
    <span>Total a pagar</span>
    <strong>
      <?= number_format($tx['total_to_pay'],2) ?> <?= htmlspecialchars($tx['currency']) ?>
    </strong>
  </div>

  <div style="display:flex;justify-content:space-between;margin-top:6px;">
    <span>Monto recibido</span>
    <strong>
      <?= number_format($tx['amount_received'],2,',','.') ?> VES
    </strong>
  </div>
</div>





      <!-- CONFIRMACIÓN -->
<div class="form-group">
  <label
    style="
      display:flex;
      align-items:center;
      gap:10px;
      padding:16px;
      border-radius:14px;
      border:1.5px solid #e5e7eb;
      background:#fafafa;
      cursor:pointer;
    "
  >
    <input
      type="checkbox"
      name="confirm"
      required
      style="
        width:16px;
        height:16px;
        accent-color:#3259fd;
        flex-shrink:0;
      "
    >

    <div style="font-weight:500;color:#111827;">
      Confirmo que los datos son correctos y autorizo el envío
    </div>
  </label>
</div>





      <button type="submit" onclick="this.disabled=true; this.form.submit();">
        Continuar
      </button>

    </form>

  </div>
</div>

</body>
</html>
