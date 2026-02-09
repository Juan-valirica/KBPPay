<?php
require_once __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/config/database.php';

/**
 * Normaliza nombres:
 * Juan, María José, etc.
 */
function normalizeName(?string $name): string {
    if ($name === null) return '';
    $name = trim($name);
    if ($name === '') return '';
    $name = mb_strtolower($name, 'UTF-8');
    return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
}


// Obtener datos del usuario
$stmt = $pdo->prepare("
    SELECT first_name, last_name
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$firstName = normalizeName($user['first_name'] ?? '');
$lastName  = normalizeName($user['last_name'] ?? '');

// Iniciales
$avatarInitials = mb_strtoupper(
    mb_substr($firstName, 0, 1, 'UTF-8') .
    mb_substr($lastName, 0, 1, 'UTF-8'),
    'UTF-8'
);

// Obtener últimas transacciones del usuario
$stmt = $pdo->prepare("
  SELECT
    t.id,
    t.amount,
    t.currency,
    t.status,
    t.created_at,

    COALESCE(b.first_name, '') AS beneficiary_first_name,
    COALESCE(b.last_name, '') AS beneficiary_last_name,
    COALESCE(b.relation_beneficiary, 'Beneficiario') AS relation_beneficiary,
    COALESCE(b.is_favorite, 0) AS is_favorite

  FROM transactions t
  LEFT JOIN beneficiaries b ON b.id = t.beneficiary_id
  WHERE t.user_id = ?
  ORDER BY t.created_at DESC
  LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$transactions = $stmt->fetchAll();




?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Dashboard | KBPPAY</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="stylesheet" href="/app/pay/assets/css/forms.css">
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

  <div class="auth-card dashboard-card dashboard-centered">

    <!-- Marca -->
    <div class="dashboard-brand">
      <img
        src="/app/pay/assets/img/kbppay-logo.svg"
        alt="KBPPAY"
      >
    </div>

    <!-- Perfil -->
    <div class="dashboard-profile">

      <div class="avatar avatar-lg avatar-elevated">
        <?= htmlspecialchars($avatarInitials) ?>
      </div>

      <h2 class="dashboard-name">
        <?= htmlspecialchars($firstName . ' ' . $lastName) ?>
      </h2>

      <p class="dashboard-welcome">
        Bienvenido a tu panel de KBPPAY
      </p>

    </div>
    
    <!-- dLista envíos -->
    
    
    <?php if (!empty($transactions)): ?>
  <div class="tx-list">

    <?php foreach ($transactions as $tx): ?>

      <?php
        $beneficiaryName = trim(
  normalizeName($tx['beneficiary_first_name']) . ' ' .
  normalizeName($tx['beneficiary_last_name'])
);

if ($beneficiaryName === '') {
    $beneficiaryName = 'Beneficiario';
}


        $date = date('d M Y', strtotime($tx['created_at']));
        $amount = number_format($tx['amount'], 2);
        $currency = strtoupper($tx['currency']);
      ?>

      <div class="tx-item">

        <!-- Izquierda -->
        <div class="tx-left">




<div style="display:flex; flex-direction:column; align-items:flex-start;">
  
  <div class="tx-name">
    <?= htmlspecialchars($beneficiaryName) ?>
    <?php if ((int)$tx['is_favorite'] === 1): ?>
      <span class="tx-fav" style="margin-left:6px;">⭐</span>
    <?php endif; ?>
  </div>

  <div class="tx-meta">
    <?= htmlspecialchars($tx['relation_beneficiary']) ?>
    · <?= $date ?>
  </div>

</div>


        </div>

        <!-- Derecha -->
        <div class="tx-right">
          <div class="tx-amount">
            <?= $currency ?> <?= $amount ?>
          </div>

          <span class="tx-status status-<?= htmlspecialchars($tx['status']) ?>">
            <?= ucfirst($tx['status']) ?>
          </span>
        </div>

      </div>

    <?php endforeach; ?>

  </div>
<?php endif; ?>

<!-- Acción principal -->
<div class="dashboard-action">
<form action="/app/pay/auth/transfers/new_register_form_amount.php" method="get">
    <button type="submit">
      Enviar dinero
    </button>
  </form>
</div>




  </div>


</div>


<script>
  let timeout;
  const subnav = document.querySelector('.dashboard-subnav');

  window.addEventListener('scroll', () => {
    subnav.classList.add('is-scrolling');
    clearTimeout(timeout);
    timeout = setTimeout(() => {
      subnav.classList.remove('is-scrolling');
    }, 120);
  });
</script>



</body>
</html>

