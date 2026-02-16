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

// Solo primer nombre para el saludo, primera letra mayúscula
$displayName = explode(' ', $firstName)[0];

// Meses en español para las fechas
$mesesEs = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Dashboard | KBPPAY</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <link rel="manifest" href="/app/pay/manifest.json">
  <meta name="theme-color" content="#0B0F1A">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="apple-touch-icon" sizes="180x180" href="/app/pay/assets/icons/icon-180.png">
  <link rel="apple-touch-icon" sizes="167x167" href="/app/pay/assets/icons/icon-167.png">
  <link rel="apple-touch-icon" sizes="152x152" href="/app/pay/assets/icons/icon-152.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ============================================
   KBPPAY DASHBOARD — FINTECH PREMIUM UI
   ============================================ */

:root {
  --bg:              #0B0F1A;
  --glass:           rgba(255,255,255,0.04);
  --glass-hover:     rgba(255,255,255,0.07);
  --glass-border:    rgba(255,255,255,0.07);
  --glass-strong:    rgba(255,255,255,0.06);
  --text:            #FFFFFF;
  --text-secondary:  rgba(255,255,255,0.65);
  --text-muted:      rgba(255,255,255,0.38);
  --green:           #52ae32;
  --blue:            #3259fd;
  --gradient:        linear-gradient(135deg, #52ae32, #3259fd);
  --status-ok:       #34D399;
  --status-ok-bg:    rgba(52,211,153,0.12);
  --status-pending:  #FBBF24;
  --status-pending-bg: rgba(251,191,36,0.12);
  --status-failed:   #F87171;
  --status-failed-bg: rgba(248,113,113,0.12);
  --radius:          16px;
  --radius-sm:       12px;
  --nav-h:           68px;
  --header-h:        60px;
  --safe-top:        env(safe-area-inset-top, 0px);
  --safe-bottom:     env(safe-area-inset-bottom, 0px);
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

html { height:100%; -webkit-text-size-adjust:100%; }

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100%;
  overflow-x: hidden;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

/* Gradient mesh background */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background:
    radial-gradient(ellipse 60% 50% at 15% 10%, rgba(82,174,50,0.10) 0%, transparent 60%),
    radial-gradient(ellipse 50% 60% at 85% 90%, rgba(50,89,253,0.10) 0%, transparent 60%),
    radial-gradient(ellipse 40% 40% at 50% 50%, rgba(82,174,50,0.03) 0%, transparent 50%);
  pointer-events: none;
  z-index: 0;
}

/* Hide scrollbar for app feel */
body::-webkit-scrollbar { width:0; height:0; }
body { scrollbar-width:none; }


/* ============================================
   HEADER
   ============================================ */

.app-header {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 100;
  padding-top: var(--safe-top);
  background: rgba(11,15,26,0.75);
  backdrop-filter: blur(24px) saturate(180%);
  -webkit-backdrop-filter: blur(24px) saturate(180%);
  border-bottom: 1px solid rgba(255,255,255,0.05);
}

.header-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: var(--header-h);
  padding: 0 20px;
  max-width: 520px;
  margin: 0 auto;
}

.header-logo {
  height: 28px;
  width: auto;
  display: block;
}

.header-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--gradient);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 12px;
  letter-spacing: 0.3px;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(82,174,50,0.2), 0 2px 8px rgba(50,89,253,0.15);
}


/* ============================================
   MAIN CONTENT
   ============================================ */

.app-content {
  position: relative;
  z-index: 1;
  max-width: 520px;
  margin: 0 auto;
  padding: calc(var(--header-h) + var(--safe-top) + 24px) 20px calc(var(--nav-h) + var(--safe-bottom) + 90px);
  min-height: 100vh;
}


/* ============================================
   WELCOME
   ============================================ */

.welcome {
  margin-bottom: 32px;
  animation: fadeInUp 0.5s ease both;
}

.welcome-greeting {
  font-size: 28px;
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.2;
  margin-bottom: 4px;
}

.welcome-sub {
  font-size: 14px;
  color: var(--text-muted);
  font-weight: 400;
}


/* ============================================
   SECTION HEADERS
   ============================================ */

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
  animation: fadeInUp 0.5s ease 0.1s both;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--text-secondary);
}

.section-count {
  font-size: 12px;
  color: var(--text-muted);
  background: var(--glass);
  padding: 4px 10px;
  border-radius: 20px;
  border: 1px solid var(--glass-border);
}


/* ============================================
   TRANSACTION LIST
   ============================================ */

.tx-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.tx-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  background: var(--glass);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius);
  transition: background 0.2s ease, border-color 0.2s ease, transform 0.15s ease;
  animation: fadeInUp 0.4s ease both;
}

.tx-card:hover {
  background: var(--glass-hover);
  border-color: rgba(255,255,255,0.10);
}

.tx-card:active {
  transform: scale(0.985);
}

/* Stagger animation for tx cards */
.tx-card:nth-child(1)  { animation-delay: 0.15s; }
.tx-card:nth-child(2)  { animation-delay: 0.20s; }
.tx-card:nth-child(3)  { animation-delay: 0.25s; }
.tx-card:nth-child(4)  { animation-delay: 0.30s; }
.tx-card:nth-child(5)  { animation-delay: 0.35s; }
.tx-card:nth-child(6)  { animation-delay: 0.38s; }
.tx-card:nth-child(7)  { animation-delay: 0.41s; }
.tx-card:nth-child(8)  { animation-delay: 0.44s; }
.tx-card:nth-child(9)  { animation-delay: 0.47s; }
.tx-card:nth-child(10) { animation-delay: 0.50s; }

/* Beneficiary avatar */
.tx-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--glass-strong);
  border: 1px solid var(--glass-border);
  color: var(--text-secondary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 13px;
  letter-spacing: 0.3px;
  flex-shrink: 0;
}

.tx-info {
  flex: 1;
  min-width: 0;
}

.tx-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: 6px;
  line-height: 1.3;
}

.tx-name-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tx-fav {
  flex-shrink: 0;
  display: flex;
}

.tx-meta {
  font-size: 12px;
  color: var(--text-muted);
  margin-top: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tx-detail {
  text-align: right;
  flex-shrink: 0;
}

.tx-amount {
  font-size: 14px;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 4px;
  white-space: nowrap;
}

.tx-status {
  display: inline-block;
  font-size: 11px;
  font-weight: 500;
  padding: 3px 8px;
  border-radius: 20px;
  white-space: nowrap;
}

.status-ok {
  color: var(--status-ok);
  background: var(--status-ok-bg);
}

.status-pending {
  color: var(--status-pending);
  background: var(--status-pending-bg);
}

.status-failed {
  color: var(--status-failed);
  background: var(--status-failed-bg);
}


/* ============================================
   EMPTY STATE
   ============================================ */

.empty-state {
  text-align: center;
  padding: 48px 20px;
  animation: fadeInUp 0.5s ease 0.15s both;
}

.empty-icon {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: var(--glass);
  border: 1px solid var(--glass-border);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
}

.empty-icon svg {
  width: 32px;
  height: 32px;
  color: var(--text-muted);
}

.empty-title {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 6px;
  color: var(--text-secondary);
}

.empty-sub {
  font-size: 13px;
  color: var(--text-muted);
}


/* ============================================
   FAB — ENVIAR
   ============================================ */

.fab {
  position: fixed;
  bottom: calc(var(--nav-h) + var(--safe-bottom) + 14px);
  left: 50%;
  transform: translateX(-50%);
  z-index: 101;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 50px;
  padding: 0 36px;
  border-radius: 25px;
  border: none;
  background: var(--gradient);
  color: #fff;
  font-family: inherit;
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.4px;
  text-decoration: none;
  cursor: pointer;
  box-shadow:
    0 4px 16px rgba(82,174,50,0.25),
    0 8px 32px rgba(50,89,253,0.20),
    inset 0 1px 0 rgba(255,255,255,0.15);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  animation: scaleIn 0.4s ease 0.3s both;
}

.fab:hover {
  transform: translateX(-50%) translateY(-2px);
  box-shadow:
    0 6px 24px rgba(82,174,50,0.35),
    0 12px 40px rgba(50,89,253,0.28),
    inset 0 1px 0 rgba(255,255,255,0.2);
}

.fab:active {
  transform: translateX(-50%) scale(0.96);
  box-shadow:
    0 2px 12px rgba(82,174,50,0.2),
    0 4px 20px rgba(50,89,253,0.15);
}

.fab svg {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}


/* ============================================
   BOTTOM NAVIGATION
   ============================================ */

.bottom-nav {
  position: fixed;
  bottom: 0; left: 0; right: 0;
  z-index: 100;
  background: rgba(11,15,26,0.82);
  backdrop-filter: blur(24px) saturate(180%);
  -webkit-backdrop-filter: blur(24px) saturate(180%);
  border-top: 1px solid rgba(255,255,255,0.05);
  padding-bottom: var(--safe-bottom);
}

.nav-inner {
  display: flex;
  align-items: center;
  justify-content: space-around;
  height: var(--nav-h);
  max-width: 520px;
  margin: 0 auto;
  padding: 0 4px;
}

.nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 6px 2px;
  min-width: 56px;
  text-decoration: none;
  color: var(--text-muted);
  transition: color 0.2s ease;
  -webkit-tap-highlight-color: transparent;
  cursor: pointer;
  background: none;
  border: none;
  font-family: inherit;
}

.nav-item:hover,
.nav-item:focus {
  color: var(--text-secondary);
  outline: none;
}

.nav-item.active {
  color: var(--text);
}

.nav-item.active .nav-indicator {
  opacity: 1;
  transform: scaleX(1);
}

.nav-icon {
  width: 22px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.nav-icon svg {
  width: 22px;
  height: 22px;
}

.nav-label {
  font-size: 10px;
  font-weight: 500;
  letter-spacing: 0.01em;
  white-space: nowrap;
}

.nav-indicator {
  width: 16px;
  height: 2px;
  border-radius: 1px;
  background: var(--gradient);
  opacity: 0;
  transform: scaleX(0);
  transition: all 0.2s ease;
}


/* ============================================
   ANIMATIONS
   ============================================ */

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(12px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes scaleIn {
  from {
    opacity: 0;
    transform: translateX(-50%) scale(0.85);
  }
  to {
    opacity: 1;
    transform: translateX(-50%) scale(1);
  }
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.bottom-nav { animation: slideUp 0.4s ease 0.2s both; }


/* ============================================
   UTILITIES
   ============================================ */

/* Reduce motion for accessibility */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}

/* Larger screens — center the app */
@media (min-width: 600px) {
  .app-content { padding-left: 24px; padding-right: 24px; }
}
</style>
</head>

<body>

<!-- ============================================
     HEADER
     ============================================ -->
<header class="app-header">
  <div class="header-inner">
    <!-- Logo inline SVG (texto en blanco para fondo oscuro) -->
    <a href="/app/pay/dashboard.php">
    <svg class="header-logo" viewBox="0 0 292.01 108.35" xmlns="http://www.w3.org/2000/svg">
      <polygon fill="#52ae32" points="42.81 102.09 42.81 77.82 61.22 65.68 42.81 53.55 42.81 29.27 79.63 53.55 79.63 77.82 42.81 102.09"/>
      <polygon fill="#3259fd" points="42.84 5.72 42.84 29.99 24.43 42.13 42.84 54.26 42.84 78.54 6.02 54.26 6.02 29.99 42.84 5.72"/>
      <g fill="#FFFFFF">
        <path d="M106.35,57.71l-4.16,4.34v7.84h-7.13v-31h7.13v14.48l13.73-14.48h7.97l-12.84,13.82,13.6,17.19h-8.37l-9.92-12.18Z"/>
        <path d="M156.27,61.43c0,5.36-4.25,8.46-12.4,8.46h-16.03v-31h15.15c7.75,0,11.74,3.23,11.74,8.06,0,3.1-1.59,5.49-4.12,6.82,3.45,1.11,5.67,3.76,5.67,7.66ZM134.97,44.29v7.31h7.13c3.5,0,5.4-1.24,5.4-3.68s-1.9-3.63-5.4-3.63h-7.13ZM149.05,60.67c0-2.61-1.99-3.85-5.71-3.85h-8.37v7.66h8.37c3.72,0,5.71-1.15,5.71-3.81Z"/>
        <path d="M188.61,48.9c0,6.91-5.18,9.98-13.46,9.98h-6.24v11.01h-7.18v-31h13.42c8.28,0,13.46,3.07,13.46,10.02ZM181.35,48.9c0-3.41-2.21-4.17-6.6-4.17h-5.85v8.3h5.85c4.38,0,6.6-.76,6.6-4.13Z"/>
        <path d="M231.13,48.9c0,6.91-5.18,9.98-13.46,9.98h-6.24v11.01h-7.18v-31h13.42c8.28,0,13.46,3.07,13.46,10.02ZM223.87,48.9c0-3.41-2.21-4.17-6.6-4.17h-5.85v8.3h5.85c4.38,0,6.6-.76,6.6-4.13Z"/>
        <path d="M255.28,56.29v13.6l-3.81-3.7h-1.43c-1.28,2.17-4.99,4.05-8.48,4.05-5.58,0-8.9-3.1-8.9-7.22s2.97-7.13,10.23-7.13h4.54c0-2.97-.43-4.25-4.15-4.25-1.65,0-3.61,1.28-3.61,2.6h-7.05c0-7.36,7.38-8.54,11.19-8.54,7.26,0,11.47,3.37,11.47,10.59ZM248.37,60.5h-4.74c-3.23,0-4.25.62-4.25,2.22,0,1.1.57,2.43,3,2.43,2.3,0,5.99-1.23,5.99-4.65Z"/>
        <path d="M286.06,46.06l-10.76,25.29c-2.3,5.76-3.94,8.3-8.24,8.3h-5.2l-.07-5.84h4.89s.91-1.62,1.84-3.75h0l-9.65-24.01h7.13l6.69,16.17,6.73-16.17h6.64Z"/>
      </g>
      <rect fill="#FFFFFF" x="251.47" y="63.11" width="7.01" height="7.01"/>
    </svg>
    </a>

    <div class="header-avatar">
      <?= htmlspecialchars($avatarInitials) ?>
    </div>
  </div>
</header>


<!-- ============================================
     MAIN CONTENT
     ============================================ -->
<main class="app-content">

  <!-- Welcome -->
  <section class="welcome">
    <h1 class="welcome-greeting">Hola, <?= htmlspecialchars($displayName) ?></h1>
    <p class="welcome-sub">Bienvenido a KBPPAY</p>
  </section>

  <!-- Transaction History -->
  <div class="section-header">
    <h2 class="section-title">Historial de envíos</h2>
    <?php if (!empty($transactions)): ?>
      <span class="section-count"><?= count($transactions) ?> envío<?= count($transactions) !== 1 ? 's' : '' ?></span>
    <?php endif; ?>
  </div>

  <?php if (!empty($transactions)): ?>
    <div class="tx-list">
      <?php foreach ($transactions as $tx):
        $bFirstName = normalizeName($tx['beneficiary_first_name']);
        $bLastName  = normalizeName($tx['beneficiary_last_name']);
        $beneficiaryName = trim($bFirstName . ' ' . $bLastName);
        if ($beneficiaryName === '') $beneficiaryName = 'Beneficiario';

        $bInitials = mb_strtoupper(
          mb_substr($bFirstName, 0, 1, 'UTF-8') . mb_substr($bLastName, 0, 1, 'UTF-8'),
          'UTF-8'
        );
        if (trim($bInitials) === '') $bInitials = '?';

        $ts = strtotime($tx['created_at']);
        $date = date('d', $ts) . ' ' . $mesesEs[(int)date('m', $ts) - 1] . ' ' . date('Y', $ts);
        $amount   = number_format($tx['amount'], 2);
        $currency = strtoupper($tx['currency']);

        $statusLabels = ['ok' => 'Completado', 'pending' => 'Pendiente', 'failed' => 'Fallido'];
        $statusLabel  = $statusLabels[$tx['status']] ?? ucfirst($tx['status']);
        $statusClass  = 'status-' . htmlspecialchars($tx['status']);
      ?>
        <div class="tx-card">
          <div class="tx-avatar"><?= htmlspecialchars($bInitials) ?></div>
          <div class="tx-info">
            <div class="tx-name">
              <span class="tx-name-text"><?= htmlspecialchars($beneficiaryName) ?></span>
              <?php if ((int)$tx['is_favorite'] === 1): ?>
                <span class="tx-fav">
                  <svg width="14" height="14" viewBox="0 0 16 16" fill="#FBBF24">
                    <path d="M8 0.5L10.1 5.3L15.5 5.9L11.5 9.5L12.5 15L8 12.3L3.5 15L4.5 9.5L0.5 5.9L5.9 5.3Z"/>
                  </svg>
                </span>
              <?php endif; ?>
            </div>
            <div class="tx-meta"><?= htmlspecialchars($tx['relation_beneficiary']) ?> · <?= $date ?></div>
          </div>
          <div class="tx-detail">
            <div class="tx-amount"><?= $currency ?> <?= $amount ?></div>
            <span class="tx-status <?= $statusClass ?>"><?= $statusLabel ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 2L11 13"/>
          <path d="M22 2L15 22L11 13L2 9L22 2Z"/>
        </svg>
      </div>
      <p class="empty-title">Aún no tienes envíos</p>
      <p class="empty-sub">Realiza tu primer envío a Venezuela</p>
    </div>
  <?php endif; ?>

</main>


<!-- ============================================
     FAB — ENVIAR
     ============================================ -->
<a class="fab" href="/app/pay/auth/transfers/new_register_form_amount.php">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M22 2L11 13"/>
    <path d="M22 2L15 22L11 13L2 9L22 2Z"/>
  </svg>
  Enviar
</a>


<!-- ============================================
     BOTTOM NAVIGATION
     ============================================ -->
<nav class="bottom-nav">
  <div class="nav-inner">

    <!-- Tasas -->
    <a class="nav-item" href="/app/pay/tasas.php">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M8 3L4 7l4 4"/>
          <path d="M4 7h16"/>
          <path d="M16 21l4-4-4-4"/>
          <path d="M20 17H4"/>
        </svg>
      </span>
      <span class="nav-label">Tasas</span>
      <span class="nav-indicator"></span>
    </a>

    <!-- Beneficiarios -->
    <a class="nav-item" href="/app/pay/beneficiarios.php">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </span>
      <span class="nav-label">Beneficiarios</span>
      <span class="nav-indicator"></span>
    </a>

    <!-- Métodos de pago -->
    <a class="nav-item" href="#">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
          <line x1="1" y1="10" x2="23" y2="10"/>
        </svg>
      </span>
      <span class="nav-label">Métodos</span>
      <span class="nav-indicator"></span>
    </a>

    <!-- Ayuda -->
    <a class="nav-item" href="#">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      </span>
      <span class="nav-label">Ayuda</span>
      <span class="nav-indicator"></span>
    </a>

    <!-- Perfil -->
    <a class="nav-item" href="#">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
      </span>
      <span class="nav-label">Perfil</span>
      <span class="nav-indicator"></span>
    </a>

  </div>
</nav>

<!-- ============================================
     iOS INSTALL BANNER
     ============================================ -->
<div class="ios-install-banner" id="iosBanner" style="display:none;">
  <div class="ios-install-inner">
    <div class="ios-install-icon">
      <svg viewBox="0 0 292.01 108.35" xmlns="http://www.w3.org/2000/svg" width="32" height="32">
        <polygon fill="#52ae32" points="42.81 102.09 42.81 77.82 61.22 65.68 42.81 53.55 42.81 29.27 79.63 53.55 79.63 77.82 42.81 102.09"/>
        <polygon fill="#3259fd" points="42.84 5.72 42.84 29.99 24.43 42.13 42.84 54.26 42.84 78.54 6.02 54.26 6.02 29.99 42.84 5.72"/>
      </svg>
    </div>
    <div class="ios-install-text">
      <strong>Instalar KBPPAY</strong>
      <span>Toca <svg class="ios-share-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg> y luego <strong>&laquo;Agregar al inicio&raquo;</strong></span>
    </div>
    <button class="ios-install-close" id="iosBannerClose" aria-label="Cerrar">&times;</button>
  </div>
</div>

<style>
/* iOS Install Banner */
.ios-install-banner {
  position: fixed;
  bottom: calc(var(--nav-h) + var(--safe-bottom) + 70px);
  left: 20px;
  right: 20px;
  z-index: 200;
  max-width: 480px;
  margin: 0 auto;
  animation: fadeInUp 0.4s ease both;
}

.ios-install-inner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  background: rgba(25,30,50,0.92);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(255,255,255,0.10);
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.4);
}

.ios-install-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: rgba(255,255,255,0.06);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.ios-install-text {
  flex: 1;
  min-width: 0;
}

.ios-install-text strong {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #fff;
  margin-bottom: 2px;
}

.ios-install-text span {
  font-size: 11px;
  color: rgba(255,255,255,0.55);
  display: inline;
  line-height: 1.5;
}

.ios-share-icon {
  width: 14px;
  height: 14px;
  color: #3b82f6;
  display: inline-block;
  vertical-align: -2px;
}

.ios-install-close {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: rgba(255,255,255,0.08);
  border: none;
  color: rgba(255,255,255,0.5);
  font-size: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  line-height: 1;
  padding: 0;
}
</style>

<!-- ============================================
     PWA — Service Worker + Install
     ============================================ -->
<script>
// Register Service Worker
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/app/pay/sw.js', { scope: '/app/pay/' });
}

// iOS install banner
(function() {
  const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
  const dismissed = localStorage.getItem('kbp_ios_dismiss');

  if (isIOS && !isStandalone && !dismissed) {
    document.getElementById('iosBanner').style.display = 'block';
  }

  document.getElementById('iosBannerClose').addEventListener('click', function() {
    document.getElementById('iosBanner').style.display = 'none';
    localStorage.setItem('kbp_ios_dismiss', '1');
  });
})();

// Android install prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', function(e) {
  e.preventDefault();
  deferredPrompt = e;
});
</script>

</body>
</html>
