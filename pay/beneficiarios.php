<?php
require_once __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/config/database.php';

function normalizeName(?string $name): string {
    if ($name === null) return '';
    $name = trim($name);
    if ($name === '') return '';
    $name = mb_strtolower($name, 'UTF-8');
    return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
}

// Usuario para header
$stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$isAdmin = strtolower(trim($user['email'] ?? '')) === 'hola@kbppay.es';

$firstName = normalizeName($user['first_name'] ?? '');
$lastName  = normalizeName($user['last_name'] ?? '');
$avatarInitials = mb_strtoupper(
    mb_substr($firstName, 0, 1, 'UTF-8') . mb_substr($lastName, 0, 1, 'UTF-8'),
    'UTF-8'
);

// Beneficiarios con estadísticas de envíos
$stmt = $pdo->prepare("
  SELECT
    b.id,
    b.first_name,
    b.last_name,
    b.bank_name,
    b.relation_beneficiary,
    b.is_favorite,
    COUNT(t.id) AS total_transfers,
    MAX(t.created_at) AS last_transfer_at
  FROM beneficiaries b
  LEFT JOIN transactions t ON t.beneficiary_id = b.id
  WHERE b.user_id = ?
  GROUP BY b.id, b.first_name, b.last_name, b.bank_name, b.relation_beneficiary, b.is_favorite
  ORDER BY b.is_favorite DESC, last_transfer_at DESC, b.id DESC
");
$stmt->execute([$_SESSION['user_id']]);
$beneficiaries = $stmt->fetchAll();

// Separar favoritos
$favorites = array_filter($beneficiaries, fn($b) => (int)$b['is_favorite'] === 1);
$others    = array_filter($beneficiaries, fn($b) => (int)$b['is_favorite'] !== 1);

$mesesEs = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

$relationLabels = [
    'family'   => 'Familiar',
    'friend'   => 'Amigo',
    'business' => 'Negocio',
    'other'    => 'Otro',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Beneficiarios | KBPPAY</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <link rel="manifest" href="/app/pay/manifest.json">
  <meta name="theme-color" content="#0B0F1A">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="apple-touch-icon" sizes="180x180" href="/app/pay/assets/icons/icon-180.png">
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

.header-logo { height: 28px; width: auto; display: block; }

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

.page-sub {
  font-size: 13px;
  color: var(--text-muted);
}


/* ============================================
   SECTION LABELS
   ============================================ */

.section-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 10px;
  padding-left: 2px;
}

.section-label svg {
  width: 14px;
  height: 14px;
}

.section-label.favorites { color: #FBBF24; }

.section-group { margin-bottom: 24px; }


/* ============================================
   BENEFICIARY CARDS
   ============================================ */

.ben-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ben-card {
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

.ben-card:hover {
  background: var(--glass-hover);
  border-color: rgba(255,255,255,0.10);
}

.ben-card:active {
  transform: scale(0.985);
}

/* Avatar */
.ben-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 14px;
  letter-spacing: 0.3px;
  flex-shrink: 0;
}

.ben-avatar.fav {
  background: linear-gradient(135deg, rgba(251,191,36,0.15), rgba(251,191,36,0.05));
  border: 1px solid rgba(251,191,36,0.20);
  color: #FBBF24;
}

.ben-avatar.default {
  background: var(--glass-strong);
  border: 1px solid var(--glass-border);
  color: var(--text-secondary);
}

/* Info */
.ben-info {
  flex: 1;
  min-width: 0;
}

.ben-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: 6px;
  line-height: 1.3;
}

.ben-name-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ben-fav-icon {
  flex-shrink: 0;
  display: flex;
  color: #FBBF24;
}

.ben-meta {
  font-size: 12px;
  color: var(--text-muted);
  margin-top: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Stats (right side) */
.ben-stats {
  text-align: right;
  flex-shrink: 0;
}

.ben-transfers {
  font-size: 13px;
  font-weight: 500;
  color: var(--text-secondary);
  white-space: nowrap;
  margin-bottom: 3px;
}

.ben-last-date {
  font-size: 11px;
  color: var(--text-muted);
  white-space: nowrap;
}


/* ============================================
   EMPTY STATE
   ============================================ */

.empty-state {
  text-align: center;
  padding: 56px 20px;
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
}

.fab svg { width: 18px; height: 18px; flex-shrink: 0; }


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

.nav-item:hover, .nav-item:focus { color: var(--text-secondary); outline: none; }
.nav-item.active { color: var(--text); }
.nav-item.active .nav-indicator { opacity: 1; transform: scaleX(1); }

.nav-icon { width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; }
.nav-icon svg { width: 22px; height: 22px; }

.nav-label { font-size: 10px; font-weight: 500; letter-spacing: 0.01em; white-space: nowrap; }

.nav-indicator {
  width: 16px; height: 2px; border-radius: 1px;
  background: var(--gradient);
  opacity: 0; transform: scaleX(0);
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
    <h1 class="page-title">Beneficiarios</h1>
    <p class="page-sub"><?= count($beneficiaries) ?> contacto<?= count($beneficiaries) !== 1 ? 's' : '' ?> registrado<?= count($beneficiaries) !== 1 ? 's' : '' ?></p>
  </section>

  <?php if (!empty($beneficiaries)): ?>

    <?php if (!empty($favorites)): ?>
      <div class="section-group" style="animation: fadeInUp 0.5s ease 0.08s both;">
        <div class="section-label favorites">
          <svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
          </svg>
          Favoritos
        </div>
        <div class="ben-list">
          <?php
          $i = 0;
          foreach ($favorites as $b):
            $bName = trim(normalizeName($b['first_name']) . ' ' . normalizeName($b['last_name']));
            $bInitials = mb_strtoupper(
              mb_substr(normalizeName($b['first_name']), 0, 1, 'UTF-8') .
              mb_substr(normalizeName($b['last_name']), 0, 1, 'UTF-8'),
              'UTF-8'
            );
            $relation = $relationLabels[$b['relation_beneficiary']] ?? ucfirst($b['relation_beneficiary'] ?? '');
            $bank = htmlspecialchars($b['bank_name'] ?? '');
            $transfers = (int)$b['total_transfers'];
            $lastDate = '';
            if ($b['last_transfer_at']) {
              $ts = strtotime($b['last_transfer_at']);
              $lastDate = date('d', $ts) . ' ' . $mesesEs[(int)date('m', $ts) - 1] . ' ' . date('Y', $ts);
            }
          ?>
            <div class="ben-card" style="animation-delay: <?= 0.10 + ($i * 0.05) ?>s;">
              <div class="ben-avatar fav"><?= htmlspecialchars($bInitials) ?></div>
              <div class="ben-info">
                <div class="ben-name">
                  <span class="ben-name-text"><?= htmlspecialchars($bName) ?></span>
                  <span class="ben-fav-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                  </span>
                </div>
                <div class="ben-meta"><?= htmlspecialchars($relation) ?><?= $bank ? ' · ' . $bank : '' ?></div>
              </div>
              <div class="ben-stats">
                <?php if ($transfers > 0): ?>
                  <div class="ben-transfers"><?= $transfers ?> envío<?= $transfers !== 1 ? 's' : '' ?></div>
                  <div class="ben-last-date"><?= $lastDate ?></div>
                <?php else: ?>
                  <div class="ben-transfers" style="color: var(--text-muted);">Sin envíos</div>
                <?php endif; ?>
              </div>
            </div>
          <?php $i++; endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($others)): ?>
      <div class="section-group" style="animation: fadeInUp 0.5s ease <?= !empty($favorites) ? '0.2' : '0.08' ?>s both;">
        <div class="section-label">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
          </svg>
          Todos
        </div>
        <div class="ben-list">
          <?php
          $i = 0;
          foreach ($others as $b):
            $bName = trim(normalizeName($b['first_name']) . ' ' . normalizeName($b['last_name']));
            $bInitials = mb_strtoupper(
              mb_substr(normalizeName($b['first_name']), 0, 1, 'UTF-8') .
              mb_substr(normalizeName($b['last_name']), 0, 1, 'UTF-8'),
              'UTF-8'
            );
            $relation = $relationLabels[$b['relation_beneficiary']] ?? ucfirst($b['relation_beneficiary'] ?? '');
            $bank = htmlspecialchars($b['bank_name'] ?? '');
            $transfers = (int)$b['total_transfers'];
            $lastDate = '';
            if ($b['last_transfer_at']) {
              $ts = strtotime($b['last_transfer_at']);
              $lastDate = date('d', $ts) . ' ' . $mesesEs[(int)date('m', $ts) - 1] . ' ' . date('Y', $ts);
            }
          ?>
            <div class="ben-card" style="animation-delay: <?= 0.10 + ($i * 0.05) ?>s;">
              <div class="ben-avatar default"><?= htmlspecialchars($bInitials) ?></div>
              <div class="ben-info">
                <div class="ben-name">
                  <span class="ben-name-text"><?= htmlspecialchars($bName) ?></span>
                </div>
                <div class="ben-meta"><?= htmlspecialchars($relation) ?><?= $bank ? ' · ' . $bank : '' ?></div>
              </div>
              <div class="ben-stats">
                <?php if ($transfers > 0): ?>
                  <div class="ben-transfers"><?= $transfers ?> envío<?= $transfers !== 1 ? 's' : '' ?></div>
                  <div class="ben-last-date"><?= $lastDate ?></div>
                <?php else: ?>
                  <div class="ben-transfers" style="color: var(--text-muted);">Sin envíos</div>
                <?php endif; ?>
              </div>
            </div>
          <?php $i++; endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </div>
      <p class="empty-title">Aún no tienes beneficiarios</p>
      <p class="empty-sub">Se agregarán al realizar tu primer envío</p>
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
    <a class="nav-item" href="/app/pay/tasas.php">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M8 3L4 7l4 4"/><path d="M4 7h16"/><path d="M16 21l4-4-4-4"/><path d="M20 17H4"/>
        </svg>
      </span>
      <span class="nav-label">Tasas</span>
      <span class="nav-indicator"></span>
    </a>
    <a class="nav-item active" href="/app/pay/beneficiarios.php">
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
    <?php if ($isAdmin): ?>
    <a class="nav-item nav-admin" href="/app/pay/admin.php" style="color:rgba(167,139,250,0.6);">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
      </span>
      <span class="nav-label">Admin</span>
      <span class="nav-indicator" style="background:rgba(167,139,250,0.8);"></span>
    </a>
    <?php endif; ?>
  </div>
</nav>

<!-- PWA Service Worker -->
<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/app/pay/sw.js', { scope: '/app/pay/' });
}
</script>

</body>
</html>
