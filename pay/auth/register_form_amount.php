<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: register_form.php');
    exit;
}

$userName = $_SESSION['first_name'] ?? '';


// === Obtener tasa de mercado KBPPAY ===
$stmt = $pdo->query("
  SELECT currency, rate_to_ves
  FROM exchange_rates
  WHERE currency IN ('USD','EUR')
");

$rates = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $rates[strtoupper(trim($row['currency']))] = (float)$row['rate_to_ves'];
}

// Fallback de seguridad
if (empty($rates['USD']) || empty($rates['EUR'])) {
    $rates = [
        'USD' => 270.45,
        'EUR' => 270.45
    ];
}

// Guardar en sesión
$_SESSION['exchange_rates'] = $rates;





// Procesar SOLO si es POST
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['amount'], $_POST['currency'])
) {


    $amount = (float) $_POST['amount'];
    $currency = $_POST['currency'];

if ($amount <= 0) {
    $_SESSION['amount_error'] = 'Ingresa un monto válido';
    header('Location: register_form_amount.php');
    exit;
}

if (!in_array($currency, ['EUR', 'USD'], true)) {
    $_SESSION['amount_error'] = 'Selecciona una moneda válida';
    header('Location: register_form_amount.php');
    exit;
}


// Comisión fija base
$BASE_COMMISSION_EUR = 2;

// Comisión según moneda
if ($currency === 'EUR') {
    $commission = $BASE_COMMISSION_EUR;
} else {
    // Convertir 2 EUR a USD usando tasas reales
    $commission = round(
        $BASE_COMMISSION_EUR * ($rates['EUR'] / $rates['USD']),
        2
    );
}


    // Tasas simuladas
$exchangeRate = $_SESSION['exchange_rates'][$currency];
$amountReceived = round($amount * $exchangeRate, 2);
$totalToPay = round($amount + $commission, 2);

    // Guardar transacción
$_SESSION['tx_draft'] = [
    'amount' => $amount,
    'currency' => $currency,
    'commission' => $commission,
    'exchange_rate' => $exchangeRate,
    'amount_received' => $amountReceived,
    'total_to_pay' => $totalToPay
];


    header("Location: register_form_additional_info.php");
    exit;


}

?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Enviar dinero | KBPPAY</title>
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
      <div class="progress-bar" style="width: 65%;"></div>
    </div>

    <h2>
      Listo <?= htmlspecialchars($userName); ?>,
      ¿cuánto dinero quieres enviar?
    </h2>

    <p>Indica el monto y la moneda para calcular el total.</p>

    <?php if (!empty($_SESSION['amount_error'])): ?>
      <p style="color:#dc2626; font-size:14px; margin-bottom:16px;">
        <?= htmlspecialchars($_SESSION['amount_error']) ?>
      </p>
      <?php unset($_SESSION['amount_error']); ?>
    <?php endif; ?>

    <form method="POST" action="register_form_amount.php">

      <div class="form-group">
        <label for="amount">Monto a enviar</label>
        <input
        type="number"
        step="0.01"
        min="1"
        id="amount"
        name="amount"
        inputmode="decimal"
        required>

      </div>

      <div class="form-group">
        <label for="currency">Moneda de pago</label>
        <select id="currency" name="currency" required>
          <option value="EUR">Euros (€)</option>
          <option value="USD">Dólares ($)</option>
        </select>
      </div>

      <div class="form-group" style="background:#f6f8fb; padding:14px; border-radius:10px;">
        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
          <span>Comisión (1%)</span>
          <span id="commission">0.00</span>
        </div>
        
        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
          <span>Tasa de cambio</span>
          <span id="rate">-</span>
        </div>
        
        <div style="display:flex; justify-content:space-between; margin:10px 0;">
         <span>Monto que recibe</span>
         <strong id="receive">0.00 VES</strong>
        </div>

        <div style="display:flex; justify-content:space-between;">
          <strong>Total a pagar</strong>
          <strong id="total">0.00</strong>
        </div>
        
         <small style="font-size:12px;color:#6b7280;">
  Tasa de mercado KBPPAY, referencial. El monto final puede variar según el proveedor.
</small>
      </div>

<button type="submit" onclick="this.disabled=true; this.form.submit();">
  Continuar
</button>

    </form>

    <p style="margin-top:16px; font-size:13px; text-align:center;">
      El destinatario recibe siempre en moneda venezolana (VES).
    </p>

  </div>
</div>


<script>
const EXCHANGE_RATES = {
  EUR: <?= json_encode($_SESSION['exchange_rates']['EUR']) ?>,
  USD: <?= json_encode($_SESSION['exchange_rates']['USD']) ?>
};
</script>


<script>
const amountInput = document.getElementById('amount');
const currencySelect = document.getElementById('currency');

const commissionEl = document.getElementById('commission');
const rateEl = document.getElementById('rate');
const totalEl = document.getElementById('total');

function updateCalculations() {
    const amount = parseFloat(amountInput.value) || 0;
    const currency = currencySelect.value;

    const BASE_COMMISSION_EUR = 2;

let commission;
if (currency === 'EUR') {
  commission = BASE_COMMISSION_EUR;
} else {
  commission = BASE_COMMISSION_EUR * (EXCHANGE_RATES.EUR / EXCHANGE_RATES.USD);
}

    const rate = EXCHANGE_RATES[currency];

    const total = amount + commission;
    const received = amount * rate;

    commissionEl.textContent = commission.toFixed(2) + ' ' + currency;
    rateEl.textContent = rate.toFixed(2) + ' VES';
    totalEl.textContent = total.toFixed(2) + ' ' + currency;

    document.getElementById('receive').textContent =
        received.toLocaleString('es-VE', { maximumFractionDigits: 2 }) + ' VES';
}



amountInput.addEventListener('input', updateCalculations);
currencySelect.addEventListener('change', updateCalculations);
</script>

</body>
</html>
