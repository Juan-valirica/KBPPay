<?php
// auth/register_form_confirmation.php
session_start();

require_once __DIR__ . '/../../config/database.php';


if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');

    exit;
}

$userId = (int) $_SESSION['user_id'];

// Obtener beneficiary_id desde sesión
$beneficiaryId = $_SESSION['beneficiary_id'] ?? null;

// Datos del beneficiario (solo si existe)
$beneficiary = [
  'first_name' => '',
  'last_name'  => ''
];

if ($beneficiaryId) {
    $stmt = $pdo->prepare("
      SELECT first_name, last_name
      FROM beneficiaries
      WHERE id = ? AND user_id = ?
      LIMIT 1
    ");
    $stmt->execute([$beneficiaryId, $userId]);
    $beneficiary = $stmt->fetch(PDO::FETCH_ASSOC) ?: $beneficiary;
}





$beneficiary = $beneficiary ?: [
  'first_name' => '',
  'last_name' => ''
];


$txDraft = $_SESSION['tx_draft'] ?? null;

// Datos para mostrar el resumen
$tx = $txDraft ?: [
  'amount' => 0,
  'currency' => '',
  'commission' => 0,
  'total_to_pay' => 0,
  'amount_received' => 0
];


if ($txDraft && $beneficiaryId) {

    $stmt = $pdo->prepare("
        INSERT INTO transactions
        (user_id, beneficiary_id, amount, currency, commission, exchange_rate, amount_received, total_to_pay, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $userId,
        $beneficiaryId,
        $txDraft['amount'],
        $txDraft['currency'],
        $txDraft['commission'],
        $txDraft['exchange_rate'],
        $txDraft['amount_received'],
        $txDraft['total_to_pay']
    ]);

    // Limpieza de sesión tras guardar la transacción
    unset($_SESSION['tx_draft'], $_SESSION['beneficiary_id']);
    unset($_SESSION['exchange_rates']);
}


?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Envío exitoso | KBPPAY</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../../assets/css/forms.css">
  
   <!-- PWA / App-like -->
  <link rel="manifest" href="/app/pay/manifest.json">
  <meta name="theme-color" content="#0A2540">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  
</head>
<body>

<div class="auth-wrapper">
  <div class="auth-card" style="text-align:center;">

<div class="auth-logo">
  <img src="../../assets/img/kbppay-logo.svg" alt="KBPPAY">
</div>

    <!-- Success Icon -->
<div class="success-wrapper">
  <div class="success-visual">
    <img src="../../assets/img/kbppay-success.svg" alt="Envío exitoso">
  </div>
  <div class="confetti-layer"></div>
</div>



    <h2>¡Envío realizado con éxito!</h2>

    <p style="max-width:360px;margin:8px auto 20px;">
    Todo salió perfecto. Tu envío ya está en proceso y te avisaremos cuando el beneficiario lo reciba.
    </p>

    <!-- Resumen -->
    <div class="form-group" style="background:#f6f8fb;padding:16px;border-radius:12px;text-align:left;">
      <strong>Resumen de la transacción</strong>

      <div style="display:flex;justify-content:space-between;margin-top:12px;">
        <span>Beneficiario</span>
        <span><?= htmlspecialchars($beneficiary['first_name'].' '.$beneficiary['last_name']) ?></span>
      </div>

      <div style="display:flex;justify-content:space-between;">
        <span>Monto enviado</span>
        <span><?= number_format($tx['amount'], 2) ?> <?= htmlspecialchars($tx['currency']) ?></span>

      </div>

      <div style="display:flex;justify-content:space-between;">
        <span>Comisión</span>
        <span><?= number_format($tx['commission'], 2) ?> <?= htmlspecialchars($tx['currency']) ?></span>

      </div>

      <div style="display:flex;justify-content:space-between;">
        <span>Total pagado</span>
        <strong><?= number_format($tx['total_to_pay'], 2) ?> <?= htmlspecialchars($tx['currency']) ?></strong>

      </div>

      <div style="display:flex;justify-content:space-between;">
        <span>Monto recibido</span>
        <strong><?= number_format($tx['amount_received'], 2, ',', '.') ?> VES</strong>

      </div>


    </div>

<!-- Acciones -->

<div class="confirmation-actions">

  <a href="https://kbppay.es/app/pay/auth/login.php" class="btn-primary">
    Ir al dashboard
  </a>

  <button
    type="button"
    class="btn-link"
    onclick="alert('Confirmación enviada por correo (simulado)')">
    Enviar comprobante por email
  </button>

  <p class="confirmation-hint">
    Podrás consultar esta transacción en cualquier momento desde tu dashboard.
  </p>

</div>






  </div>
</div>

</body>
</html>
