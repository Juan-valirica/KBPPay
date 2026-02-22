<?php
require_once __DIR__ . '/auth/admin_middleware.php';
require_once __DIR__ . '/config/database.php';

// Datos del administrador
$stmtAdm = $pdo->prepare("
    SELECT first_name, last_name, email, created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmtAdm->execute([$_SESSION['user_id']]);
$adminUser = $stmtAdm->fetch();

$firstName = mb_convert_case(mb_strtolower(trim($adminUser['first_name'] ?? ''), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
$lastName  = mb_convert_case(mb_strtolower(trim($adminUser['last_name'] ?? ''), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
$avatarInitials = mb_strtoupper(
    mb_substr($firstName, 0, 1, 'UTF-8') . mb_substr($lastName, 0, 1, 'UTF-8'),
    'UTF-8'
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Administración | KBPPAY</title>
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
   KBPPAY ADMIN — FINTECH PREMIUM UI
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
  --admin-accent:    #a78bfa;
  --admin-accent-bg: rgba(167,139,250,0.10);
  --admin-border:    rgba(167,139,250,0.20);
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

/* Gradient mesh — tono admin púrpura */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background:
    radial-gradient(ellipse 60% 50% at 15% 10%, rgba(167,139,250,0.08) 0%, transparent 60%),
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
  border-bottom: 1px solid rgba(167,139,250,0.12);
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
  box-shadow: 0 2px 8px rgba(167,139,250,0.3), 0 2px 8px rgba(50,89,253,0.15);
}


/* ============================================
   MAIN CONTENT
   ============================================ */

.app-content {
  position: relative;
  z-index: 1;
  max-width: 520px;
  margin: 0 auto;
  padding: calc(var(--header-h) + var(--safe-top) + 24px) 20px calc(var(--nav-h) + var(--safe-bottom) + 32px);
  min-height: 100vh;
}


/* ============================================
   ADMIN BADGE
   ============================================ */

.admin-hero {
  margin-bottom: 28px;
  animation: fadeInUp 0.45s ease both;
}

.admin-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px 5px 8px;
  background: var(--admin-accent-bg);
  border: 1px solid var(--admin-border);
  border-radius: 20px;
  margin-bottom: 14px;
}

.admin-badge-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--admin-accent);
  box-shadow: 0 0 6px var(--admin-accent);
  animation: pulse 2s ease infinite;
}

.admin-badge-text {
  font-size: 11px;
  font-weight: 600;
  color: var(--admin-accent);
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.admin-title {
  font-size: 26px;
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.2;
  margin-bottom: 4px;
}

.admin-sub {
  font-size: 13px;
  color: var(--text-muted);
}


/* ============================================
   SECTION HEADERS
   ============================================ */

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.section-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--text-secondary);
}

.section-soon {
  font-size: 11px;
  font-weight: 500;
  color: var(--admin-accent);
  background: var(--admin-accent-bg);
  border: 1px solid var(--admin-border);
  padding: 3px 9px;
  border-radius: 20px;
}


/* ============================================
   MODULE CARDS (coming soon)
   ============================================ */

.module-grid {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 28px;
}

.module-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  background: var(--glass);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius);
  animation: fadeInUp 0.4s ease both;
  position: relative;
  overflow: hidden;
}

.module-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(167,139,250,0.04) 0%, transparent 60%);
  pointer-events: none;
}

.module-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: var(--admin-accent-bg);
  border: 1px solid var(--admin-border);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: var(--admin-accent);
}

.module-icon svg {
  width: 20px;
  height: 20px;
}

.module-info {
  flex: 1;
  min-width: 0;
}

.module-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 3px;
}

.module-desc {
  font-size: 12px;
  color: var(--text-muted);
  line-height: 1.4;
}

.module-arrow {
  flex-shrink: 0;
  color: var(--text-muted);
  opacity: 0.4;
}

.module-arrow svg {
  width: 16px;
  height: 16px;
}

/* Stagger */
.module-card:nth-child(1) { animation-delay: 0.10s; }
.module-card:nth-child(2) { animation-delay: 0.15s; }
.module-card:nth-child(3) { animation-delay: 0.20s; }
.module-card:nth-child(4) { animation-delay: 0.25s; }
.module-card:nth-child(5) { animation-delay: 0.30s; }


/* ============================================
   COMING SOON OVERLAY
   ============================================ */

.coming-soon-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  background: rgba(167,139,250,0.05);
  border: 1px dashed rgba(167,139,250,0.25);
  border-radius: var(--radius);
  margin-bottom: 28px;
  animation: fadeInUp 0.5s ease 0.35s both;
}

.coming-soon-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: var(--admin-accent-bg);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: var(--admin-accent);
}

.coming-soon-icon svg {
  width: 18px;
  height: 18px;
}

.coming-soon-text strong {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 2px;
}

.coming-soon-text span {
  font-size: 12px;
  color: var(--text-muted);
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
  min-width: 48px;
  text-decoration: none;
  color: var(--text-muted);
  transition: color 0.2s ease;
  -webkit-tap-highlight-color: transparent;
  cursor: pointer;
  background: none;
  border: none;
  font-family: inherit;
}

.nav-item:hover, .nav-item:focus {
  color: var(--text-secondary);
  outline: none;
}

.nav-item.active {
  color: var(--admin-accent);
}

.nav-item.active .nav-indicator {
  opacity: 1;
  transform: scaleX(1);
  background: var(--admin-accent);
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

/* Admin nav icon tinted */
.nav-item.active.nav-admin .nav-icon {
  color: var(--admin-accent);
}


/* ============================================
   ANIMATIONS
   ============================================ */

@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.45; }
}

.bottom-nav { animation: slideUp 0.4s ease 0.2s both; }


/* ============================================
   UTILITIES
   ============================================ */

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

<!-- HEADER -->
<header class="app-header">
  <div class="header-inner">
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
    <div class="header-avatar"><?= htmlspecialchars($avatarInitials) ?></div>
  </div>
</header>


<!-- MAIN CONTENT -->
<main class="app-content">

  <!-- Admin hero -->
  <div class="admin-hero">
    <div class="admin-badge">
      <span class="admin-badge-dot"></span>
      <span class="admin-badge-text">Panel de administración</span>
    </div>
    <h1 class="admin-title">Control KBP PAY</h1>
    <p class="admin-sub">Acceso exclusivo · <?= htmlspecialchars($adminUser['email'] ?? '') ?></p>
  </div>

  <!-- Coming soon banner -->
  <div class="coming-soon-banner">
    <div class="coming-soon-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/>
        <polyline points="12 6 12 12 16 14"/>
      </svg>
    </div>
    <div class="coming-soon-text">
      <strong>Módulos en construcción</strong>
      <span>Los features del panel admin se agregarán próximamente</span>
    </div>
  </div>

  <!-- Módulos -->
  <div class="section-header" style="animation: fadeInUp 0.45s ease 0.05s both;">
    <span class="section-title">Módulos disponibles</span>
    <span class="section-soon">Próximamente</span>
  </div>

  <div class="module-grid">

    <!-- Usuarios -->
    <div class="module-card">
      <div class="module-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </div>
      <div class="module-info">
        <div class="module-name">Gestión de usuarios</div>
        <div class="module-desc">Ver, activar, suspender y gestionar cuentas</div>
      </div>
      <div class="module-arrow">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"/>
        </svg>
      </div>
    </div>

    <!-- Transacciones -->
    <div class="module-card">
      <div class="module-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M8 3L4 7l4 4"/>
          <path d="M4 7h16"/>
          <path d="M16 21l4-4-4-4"/>
          <path d="M20 17H4"/>
        </svg>
      </div>
      <div class="module-info">
        <div class="module-name">Control de transacciones</div>
        <div class="module-desc">Monitorear, aprobar y gestionar transferencias</div>
      </div>
      <div class="module-arrow">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"/>
        </svg>
      </div>
    </div>

    <!-- Tasas -->
    <div class="module-card">
      <div class="module-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="1" x2="12" y2="23"/>
          <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
        </svg>
      </div>
      <div class="module-info">
        <div class="module-name">Configuración de tasas</div>
        <div class="module-desc">Actualizar tipos de cambio EUR/VES y USD/VES</div>
      </div>
      <div class="module-arrow">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"/>
        </svg>
      </div>
    </div>

    <!-- KYC / Verificación -->
    <div class="module-card">
      <div class="module-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
      </div>
      <div class="module-info">
        <div class="module-name">Verificación KYC</div>
        <div class="module-desc">Revisar y aprobar documentos de identidad</div>
      </div>
      <div class="module-arrow">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"/>
        </svg>
      </div>
    </div>

    <!-- Soporte -->
    <div class="module-card">
      <div class="module-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
      </div>
      <div class="module-info">
        <div class="module-name">Soporte y mensajes</div>
        <div class="module-desc">Gestionar tickets y consultas de usuarios</div>
      </div>
      <div class="module-arrow">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"/>
        </svg>
      </div>
    </div>

  </div>

</main>


<!-- BOTTOM NAV -->
<nav class="bottom-nav">
  <div class="nav-inner">

    <a class="nav-item" href="/app/pay/tasas.php">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M8 3L4 7l4 4"/><path d="M4 7h16"/>
          <path d="M16 21l4-4-4-4"/><path d="M20 17H4"/>
        </svg>
      </span>
      <span class="nav-label">Tasas</span>
      <span class="nav-indicator"></span>
    </a>

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

    <a class="nav-item" href="/app/pay/metodos.php">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
          <line x1="1" y1="10" x2="23" y2="10"/>
        </svg>
      </span>
      <span class="nav-label">Métodos</span>
      <span class="nav-indicator"></span>
    </a>

    <a class="nav-item" href="/app/pay/ayuda.php">
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

    <a class="nav-item" href="/app/pay/perfil.php">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
      </span>
      <span class="nav-label">Perfil</span>
      <span class="nav-indicator"></span>
    </a>

    <!-- Admin — activo en esta página -->
    <a class="nav-item active nav-admin" href="/app/pay/admin.php">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
      </span>
      <span class="nav-label">Admin</span>
      <span class="nav-indicator"></span>
    </a>

  </div>
</nav>

<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/app/pay/sw.js', { scope: '/app/pay/' });
}
</script>

</body>
</html>
