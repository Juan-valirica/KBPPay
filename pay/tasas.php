<?php
require_once __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/config/database.php';

// Datos del usuario para el header avatar
$stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

function normalizeName(?string $name): string {
    if ($name === null) return '';
    $name = trim($name);
    if ($name === '') return '';
    $name = mb_strtolower($name, 'UTF-8');
    return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
}

$firstName = normalizeName($user['first_name'] ?? '');
$lastName  = normalizeName($user['last_name'] ?? '');
$avatarInitials = mb_strtoupper(
    mb_substr($firstName, 0, 1, 'UTF-8') . mb_substr($lastName, 0, 1, 'UTF-8'),
    'UTF-8'
);

// Tasas de cambio desde MySQL
$stmt = $pdo->prepare("SELECT currency, rate_to_ves, updated_at FROM exchange_rates WHERE currency IN ('EUR','USD')");
$stmt->execute();
$rates = [];
while ($row = $stmt->fetch()) {
    $rates[$row['currency']] = $row;
}

$eurRate = (float)($rates['EUR']['rate_to_ves'] ?? 0);
$usdRate = (float)($rates['USD']['rate_to_ves'] ?? 0);
$updatedAt = $rates['EUR']['updated_at'] ?? $rates['USD']['updated_at'] ?? null;

// Fecha en español
$mesesEs = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$updatedAtFormatted = '-';
if ($updatedAt) {
    $ts = strtotime($updatedAt);
    $updatedAtFormatted = date('d', $ts) . ' ' . $mesesEs[(int)date('m', $ts) - 1] . ' ' . date('Y', $ts) . ', ' . date('H:i', $ts);
}

// Cambio semanal (referencia semana anterior desde Google Finance)
$eurPrevWeek = 315.60;
$usdPrevWeek = 264.80;
$eurChange = $eurPrevWeek > 0 ? (($eurRate - $eurPrevWeek) / $eurPrevWeek) * 100 : 0;
$usdChange = $usdPrevWeek > 0 ? (($usdRate - $usdPrevWeek) / $usdPrevWeek) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Tasas de cambio | KBPPAY</title>
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
   KBPPAY — BASE DESIGN SYSTEM
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

.header-back {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  color: var(--text);
  -webkit-tap-highlight-color: transparent;
}

.header-back-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--glass);
  border: 1px solid var(--glass-border);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background 0.2s ease;
}

.header-back-icon svg {
  width: 18px;
  height: 18px;
  color: var(--text-secondary);
}

.header-back:hover .header-back-icon {
  background: var(--glass-hover);
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
   PAGE HEADER
   ============================================ */

.page-header {
  margin-bottom: 24px;
  animation: fadeInUp 0.5s ease both;
}

.page-title {
  font-size: 24px;
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.2;
  margin-bottom: 4px;
}

.page-updated {
  font-size: 13px;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  gap: 6px;
}

.page-updated svg {
  width: 14px;
  height: 14px;
  opacity: 0.6;
}

.live-dot {
  width: 6px;
  height: 6px;
  background: #34D399;
  border-radius: 50%;
  display: inline-block;
  animation: pulse 2s ease infinite;
}


/* ============================================
   RATE CARDS
   ============================================ */

.rate-cards {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 20px;
  animation: fadeInUp 0.5s ease 0.08s both;
}

.rate-card {
  background: var(--glass);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius);
  padding: 16px;
  transition: background 0.2s ease, border-color 0.2s ease;
}

.rate-card:hover {
  background: var(--glass-hover);
  border-color: rgba(255,255,255,0.10);
}

.rate-currency {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
}

.rate-currency-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 700;
  flex-shrink: 0;
}

.rate-currency-icon.eur {
  background: rgba(50,89,253,0.15);
  color: #60A5FA;
}

.rate-currency-icon.usd {
  background: rgba(82,174,50,0.15);
  color: #6EE7B7;
}

.rate-currency-label {
  font-size: 13px;
  font-weight: 500;
  color: var(--text-secondary);
}

.rate-value {
  font-size: 22px;
  font-weight: 700;
  letter-spacing: -0.02em;
  margin-bottom: 2px;
}

.rate-unit {
  font-size: 12px;
  color: var(--text-muted);
  margin-bottom: 6px;
}

.rate-change {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 12px;
  font-weight: 500;
  padding: 2px 8px;
  border-radius: 20px;
}

.rate-change.up {
  color: #34D399;
  background: rgba(52,211,153,0.10);
}

.rate-change.down {
  color: #F87171;
  background: rgba(248,113,113,0.10);
}

.rate-change svg {
  width: 12px;
  height: 12px;
}


/* ============================================
   CHART
   ============================================ */

.chart-card {
  background: var(--glass);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius);
  padding: 20px 16px 16px;
  margin-bottom: 20px;
  animation: fadeInUp 0.5s ease 0.16s both;
}

.chart-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.chart-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--text-secondary);
}

.chart-legend {
  display: flex;
  gap: 14px;
}

.chart-legend-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  color: var(--text-muted);
}

.chart-legend-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.chart-legend-dot.eur { background: #3259fd; }
.chart-legend-dot.usd { background: #52ae32; }

.chart-container {
  position: relative;
  width: 100%;
  height: 200px;
}

.chart-container canvas {
  width: 100% !important;
  height: 100% !important;
}


/* ============================================
   CALCULATOR
   ============================================ */

.calc-card {
  background: var(--glass);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius);
  padding: 20px;
  animation: fadeInUp 0.5s ease 0.24s both;
}

.calc-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 18px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.calc-title svg {
  width: 18px;
  height: 18px;
  color: var(--text-muted);
}

.calc-label {
  font-size: 13px;
  font-weight: 500;
  color: var(--text-secondary);
  margin-bottom: 6px;
  display: block;
}

.calc-input,
.calc-select {
  width: 100%;
  padding: 14px 16px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: var(--radius-sm);
  color: var(--text);
  font-size: 15px;
  font-family: inherit;
  font-weight: 500;
  outline: none;
  transition: border-color 0.2s ease, background 0.2s ease;
  margin-bottom: 16px;
}

.calc-input::placeholder {
  color: var(--text-muted);
}

.calc-input:focus,
.calc-select:focus {
  border-color: rgba(82,174,50,0.4);
  background: rgba(255,255,255,0.07);
}

.calc-select {
  appearance: none;
  -webkit-appearance: none;
  cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='rgba(255,255,255,0.4)' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 14px center;
  background-size: 16px;
  padding-right: 40px;
}

.calc-select option {
  background: #161b2e;
  color: #fff;
}

.calc-result {
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.05);
  border-radius: var(--radius-sm);
  padding: 16px;
}

.calc-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
}

.calc-row + .calc-row {
  border-top: 1px solid rgba(255,255,255,0.04);
}

.calc-row-label {
  font-size: 13px;
  color: var(--text-muted);
}

.calc-row-value {
  font-size: 13px;
  font-weight: 500;
  color: var(--text-secondary);
}

.calc-row.total {
  margin-top: 4px;
  padding-top: 12px;
  border-top: 1px solid rgba(255,255,255,0.08);
}

.calc-row.total .calc-row-label,
.calc-row.total .calc-row-value {
  font-size: 14px;
  font-weight: 600;
  color: var(--text);
}

.calc-row.receive .calc-row-value {
  font-weight: 700;
  color: #34D399;
  font-size: 15px;
}

.calc-note {
  font-size: 11px;
  color: var(--text-muted);
  margin-top: 12px;
  text-align: center;
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

.nav-icon svg { width: 22px; height: 22px; }

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
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

@keyframes scaleIn {
  from { opacity: 0; transform: translateX(-50%) scale(0.85); }
  to   { opacity: 1; transform: translateX(-50%) scale(1); }
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50%      { opacity: 0.4; }
}

.bottom-nav { animation: slideUp 0.4s ease 0.2s both; }

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}

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
    <a href="/app/pay/dashboard.php" class="header-back">
      <span class="header-back-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
      </span>
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
    <div class="header-avatar"><?= htmlspecialchars($avatarInitials) ?></div>
  </div>
</header>


<!-- ============================================
     MAIN CONTENT
     ============================================ -->
<main class="app-content">

  <!-- Page Header -->
  <section class="page-header">
    <h1 class="page-title">Tasas de cambio</h1>
    <p class="page-updated">
      <span class="live-dot"></span>
      Actualizado: <?= htmlspecialchars($updatedAtFormatted) ?>
    </p>
  </section>

  <!-- Rate Cards -->
  <div class="rate-cards">

    <!-- EUR -->
    <div class="rate-card">
      <div class="rate-currency">
        <div class="rate-currency-icon eur">&euro;</div>
        <span class="rate-currency-label">EUR</span>
      </div>
      <div class="rate-value"><?= number_format($eurRate, 2) ?></div>
      <div class="rate-unit">VES por euro</div>
      <?php if ($eurRate > 0): ?>
        <span class="rate-change <?= $eurChange >= 0 ? 'up' : 'down' ?>">
          <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <?php if ($eurChange >= 0): ?>
              <path d="M6 9V3M3 5l3-3 3 3"/>
            <?php else: ?>
              <path d="M6 3v6M3 7l3 3 3-3"/>
            <?php endif; ?>
          </svg>
          <?= ($eurChange >= 0 ? '+' : '') . number_format($eurChange, 2) ?>%
        </span>
      <?php endif; ?>
    </div>

    <!-- USD -->
    <div class="rate-card">
      <div class="rate-currency">
        <div class="rate-currency-icon usd">$</div>
        <span class="rate-currency-label">USD</span>
      </div>
      <div class="rate-value"><?= number_format($usdRate, 2) ?></div>
      <div class="rate-unit">VES por dólar</div>
      <?php if ($usdRate > 0): ?>
        <span class="rate-change <?= $usdChange >= 0 ? 'up' : 'down' ?>">
          <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <?php if ($usdChange >= 0): ?>
              <path d="M6 9V3M3 5l3-3 3 3"/>
            <?php else: ?>
              <path d="M6 3v6M3 7l3 3 3-3"/>
            <?php endif; ?>
          </svg>
          <?= ($usdChange >= 0 ? '+' : '') . number_format($usdChange, 2) ?>%
        </span>
      <?php endif; ?>
    </div>

  </div>

  <!-- Chart -->
  <div class="chart-card">
    <div class="chart-header">
      <span class="chart-title">Tendencia 3 meses</span>
      <div class="chart-legend">
        <span class="chart-legend-item"><span class="chart-legend-dot eur"></span> EUR</span>
        <span class="chart-legend-item"><span class="chart-legend-dot usd"></span> USD</span>
      </div>
    </div>
    <div class="chart-container">
      <canvas id="rateChart"></canvas>
    </div>
  </div>

  <!-- Calculator -->
  <div class="calc-card">
    <div class="calc-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="4" y="2" width="16" height="20" rx="2"/>
        <line x1="8" y1="6" x2="16" y2="6"/>
        <line x1="8" y1="14" x2="8" y2="14.01"/>
        <line x1="12" y1="14" x2="12" y2="14.01"/>
        <line x1="16" y1="14" x2="16" y2="14.01"/>
        <line x1="8" y1="18" x2="8" y2="18.01"/>
        <line x1="12" y1="18" x2="12" y2="18.01"/>
        <line x1="16" y1="18" x2="16" y2="18.01"/>
        <line x1="8" y1="10" x2="16" y2="10"/>
      </svg>
      Calculadora
    </div>

    <label class="calc-label">Lo que envías</label>
    <input class="calc-input" id="kbp-amount" type="number" min="1" step="0.01" placeholder="0.00" inputmode="decimal">

    <label class="calc-label">Moneda</label>
    <select class="calc-select" id="kbp-currency">
      <option value="EUR">Euros (&euro;)</option>
      <option value="USD">Dólares ($)</option>
    </select>

    <div class="calc-result">
      <div class="calc-row">
        <span class="calc-row-label">Comisión</span>
        <span class="calc-row-value" id="kbp-commission">0.00</span>
      </div>
      <div class="calc-row">
        <span class="calc-row-label">Tasa de cambio</span>
        <span class="calc-row-value" id="kbp-rate">-</span>
      </div>
      <div class="calc-row receive">
        <span class="calc-row-label">Monto que recibe</span>
        <span class="calc-row-value" id="kbp-receive">0.00 VES</span>
      </div>
      <div class="calc-row total">
        <span class="calc-row-label">Total a pagar</span>
        <span class="calc-row-value" id="kbp-total">0.00</span>
      </div>
    </div>

    <p class="calc-note">Tasa de mercado KBPPAY, referencial.</p>
  </div>

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
    <a class="nav-item active" href="/app/pay/tasas.php">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M8 3L4 7l4 4"/><path d="M4 7h16"/><path d="M16 21l4-4-4-4"/><path d="M20 17H4"/>
        </svg>
      </span>
      <span class="nav-label">Tasas</span>
      <span class="nav-indicator"></span>
    </a>
    <a class="nav-item" href="/app/pay/beneficiarios.php">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </span>
      <span class="nav-label">Beneficiarios</span>
      <span class="nav-indicator"></span>
    </a>
    <a class="nav-item" href="/app/pay/metodos.php">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
        </svg>
      </span>
      <span class="nav-label">Métodos</span>
      <span class="nav-indicator"></span>
    </a>
    <a class="nav-item" href="/app/pay/ayuda.php">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      </span>
      <span class="nav-label">Ayuda</span>
      <span class="nav-indicator"></span>
    </a>
    <a class="nav-item" href="/app/pay/perfil.php">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
        </svg>
      </span>
      <span class="nav-label">Perfil</span>
      <span class="nav-indicator"></span>
    </a>
  </div>
</nav>


<!-- ============================================
     CHART.JS
     ============================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<script>
(function () {
  /* ==========================================
     RATES FROM MYSQL
     ========================================== */
  const RATES = {
    EUR: <?= json_encode($eurRate) ?>,
    USD: <?= json_encode($usdRate) ?>
  };

  /* ==========================================
     CHART — Tendencia 3 meses
     Datos de referencia: Google Finance
     Último punto: tasa en vivo desde MySQL
     ========================================== */
  const labels = [
    '11 Nov','18 Nov','25 Nov','02 Dic','09 Dic','16 Dic',
    '23 Dic','30 Dic','06 Ene','13 Ene','20 Ene','27 Ene','03 Feb','Hoy'
  ];

  const eurData = [282.40,285.10,288.30,291.50,294.20,297.80,300.50,303.20,306.10,308.90,311.40,313.80,315.60, RATES.EUR];
  const usdData = [236.50,238.80,241.20,243.60,246.30,248.90,251.40,254.10,256.70,259.30,261.80,263.50,264.80, RATES.USD];

  const canvas = document.getElementById('rateChart');
  const ctx = canvas.getContext('2d');

  // Gradient fills
  const gradEUR = ctx.createLinearGradient(0, 0, 0, 200);
  gradEUR.addColorStop(0, 'rgba(50,89,253,0.25)');
  gradEUR.addColorStop(1, 'rgba(50,89,253,0)');

  const gradUSD = ctx.createLinearGradient(0, 0, 0, 200);
  gradUSD.addColorStop(0, 'rgba(82,174,50,0.25)');
  gradUSD.addColorStop(1, 'rgba(82,174,50,0)');

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'EUR/VES',
          data: eurData,
          borderColor: '#3259fd',
          backgroundColor: gradEUR,
          fill: true,
          tension: 0.4,
          borderWidth: 2,
          pointRadius: 0,
          pointHoverRadius: 5,
          pointHoverBackgroundColor: '#3259fd',
          pointHoverBorderColor: '#fff',
          pointHoverBorderWidth: 2
        },
        {
          label: 'USD/VES',
          data: usdData,
          borderColor: '#52ae32',
          backgroundColor: gradUSD,
          fill: true,
          tension: 0.4,
          borderWidth: 2,
          pointRadius: 0,
          pointHoverRadius: 5,
          pointHoverBackgroundColor: '#52ae32',
          pointHoverBorderColor: '#fff',
          pointHoverBorderWidth: 2
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(15,20,40,0.92)',
          titleColor: 'rgba(255,255,255,0.7)',
          bodyColor: '#fff',
          bodyFont: { weight: '600' },
          padding: 12,
          cornerRadius: 10,
          borderColor: 'rgba(255,255,255,0.08)',
          borderWidth: 1,
          displayColors: true,
          boxWidth: 8,
          boxHeight: 8,
          boxPadding: 4,
          callbacks: {
            label: function(ctx) {
              return ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(2) + ' VES';
            }
          }
        }
      },
      scales: {
        x: {
          grid: { color: 'rgba(255,255,255,0.03)', drawBorder: false },
          ticks: {
            color: 'rgba(255,255,255,0.3)',
            font: { size: 10 },
            maxRotation: 0,
            maxTicksLimit: 7
          },
          border: { display: false }
        },
        y: {
          grid: { color: 'rgba(255,255,255,0.03)', drawBorder: false },
          ticks: {
            color: 'rgba(255,255,255,0.3)',
            font: { size: 10 },
            maxTicksLimit: 5,
            callback: function(v) { return v.toFixed(0); }
          },
          border: { display: false }
        }
      }
    }
  });


  /* ==========================================
     CALCULATOR
     ========================================== */
  const BASE_COMMISSION_EUR = 2;

  const amountEl     = document.getElementById('kbp-amount');
  const currencyEl   = document.getElementById('kbp-currency');
  const commissionEl = document.getElementById('kbp-commission');
  const rateEl       = document.getElementById('kbp-rate');
  const totalEl      = document.getElementById('kbp-total');
  const receiveEl    = document.getElementById('kbp-receive');

  function updateCalc() {
    const value = parseFloat(amountEl.value) || 0;
    const curr  = currencyEl.value;
    const rate  = RATES[curr];

    let commission;
    if (curr === 'EUR') {
      commission = BASE_COMMISSION_EUR;
    } else {
      commission = RATES.EUR > 0 ? BASE_COMMISSION_EUR * (RATES.EUR / RATES.USD) : 0;
    }

    const total    = value + commission;
    const received = value * rate;

    commissionEl.textContent = commission.toFixed(2) + ' ' + curr;
    rateEl.textContent       = rate.toFixed(2) + ' VES';
    totalEl.textContent      = total.toFixed(2) + ' ' + curr;
    receiveEl.textContent    = received.toLocaleString('es-VE', { maximumFractionDigits: 2 }) + ' VES';
  }

  amountEl.addEventListener('input', updateCalc);
  currencyEl.addEventListener('change', updateCalc);
  updateCalc();

})();
</script>

<!-- PWA Service Worker -->
<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/app/pay/sw.js', { scope: '/app/pay/' });
}
</script>

</body>
</html>
