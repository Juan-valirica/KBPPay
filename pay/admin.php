<?php
require_once __DIR__ . '/auth/admin_middleware.php';
require_once __DIR__ . '/config/database.php';

/* ── POST: actualizar tasa ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_rate') {
    $cur  = strtoupper(trim($_POST['currency'] ?? ''));
    $rate = $_POST['new_rate'] ?? '';
    if (in_array($cur, ['EUR','USD']) && is_numeric($rate) && (float)$rate > 0) {
        $pdo->prepare("UPDATE exchange_rates SET rate_to_ves = ?, updated_at = NOW() WHERE currency = ?")
            ->execute([(float)$rate, $cur]);
        header("Location: /app/pay/admin.php?tab=tasas&msg=rate_ok&cur={$cur}");
    } else {
        header("Location: /app/pay/admin.php?tab=tasas&msg=rate_err");
    }
    exit;
}

/* ── Datos admin ── */
$stmtA = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ? LIMIT 1");
$stmtA->execute([$_SESSION['user_id']]);
$adm = $stmtA->fetch();
$fn  = mb_convert_case(mb_strtolower(trim($adm['first_name'] ?? ''), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
$ln  = mb_convert_case(mb_strtolower(trim($adm['last_name']  ?? ''), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
$avi = mb_strtoupper(mb_substr($fn,0,1,'UTF-8').mb_substr($ln,0,1,'UTF-8'), 'UTF-8');

/* ── Tasas actuales ── */
$ratesRaw = $pdo->query("SELECT currency, rate_to_ves, updated_at FROM exchange_rates WHERE currency IN ('EUR','USD')")->fetchAll();
$rates = [];
foreach ($ratesRaw as $r) $rates[$r['currency']] = $r;
$eurRate    = (float)($rates['EUR']['rate_to_ves'] ?? 0);
$usdRate    = (float)($rates['USD']['rate_to_ves'] ?? 0);
$eurUpdated = $rates['EUR']['updated_at'] ?? null;
$usdUpdated = $rates['USD']['updated_at'] ?? null;

/* ── Page state ── */
$activeTab = $_GET['tab'] ?? 'tasas';
$msgParam  = $_GET['msg'] ?? '';
$curParam  = strtoupper($_GET['cur'] ?? '');

$meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
function fmtUpd(?string $d, array $m): string {
    if (!$d) return 'Nunca actualizada';
    $t = strtotime($d);
    return 'Actualizada el ' . date('d',$t) . ' ' . $m[(int)date('m',$t)-1] . ' · ' . date('H:i',$t);
}

/* ── Toast ── */
$toasts = [
    'rate_ok'  => ['msg' => "Tasa {$curParam}/VES actualizada correctamente", 'type' => 'success'],
    'rate_err' => ['msg' => 'Valor inválido — no se actualizó la tasa',         'type' => 'error'],
];
$toast = $toasts[$msgParam] ?? null;
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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
  --bg:              #0B0F1A;
  --glass:           rgba(255,255,255,0.04);
  --glass-hover:     rgba(255,255,255,0.07);
  --glass-border:    rgba(255,255,255,0.07);
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
  --nav-h:           68px;
  --header-h:        60px;
  --safe-top:        env(safe-area-inset-top, 0px);
  --safe-bottom:     env(safe-area-inset-bottom, 0px);
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
html { height:100%; -webkit-text-size-adjust:100%; }
body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100%;
  overflow-x: hidden;
  -webkit-font-smoothing: antialiased;
}
body::before {
  content: '';
  position: fixed; inset: 0;
  background:
    radial-gradient(ellipse 60% 50% at 15% 10%, rgba(167,139,250,0.08) 0%, transparent 60%),
    radial-gradient(ellipse 50% 60% at 85% 90%, rgba(50,89,253,0.10) 0%, transparent 60%);
  pointer-events: none; z-index: 0;
}
body::-webkit-scrollbar { width:0; }
body { scrollbar-width:none; }

/* ── HEADER ── */
.app-header {
  position: fixed; top:0; left:0; right:0; z-index:100;
  padding-top: var(--safe-top);
  background: rgba(11,15,26,0.75);
  backdrop-filter: blur(24px) saturate(180%);
  -webkit-backdrop-filter: blur(24px) saturate(180%);
  border-bottom: 1px solid rgba(167,139,250,0.12);
}
.header-inner {
  display:flex; align-items:center; justify-content:space-between;
  height: var(--header-h); padding:0 20px;
  max-width:520px; margin:0 auto;
}
.header-logo { height:28px; width:auto; display:block; }
.header-avatar {
  width:36px; height:36px; border-radius:50%;
  background: var(--gradient); color:#fff;
  display:flex; align-items:center; justify-content:center;
  font-weight:700; font-size:12px; flex-shrink:0;
  box-shadow: 0 2px 8px rgba(167,139,250,0.3);
}

/* ── CONTENT ── */
.app-content {
  position:relative; z-index:1;
  max-width:520px; margin:0 auto;
  padding: calc(var(--header-h) + var(--safe-top) + 24px) 20px calc(var(--nav-h) + var(--safe-bottom) + 32px);
  min-height:100vh;
}

/* ── HERO ── */
.admin-hero { margin-bottom:24px; animation: fadeInUp 0.4s ease both; }
.admin-badge {
  display:inline-flex; align-items:center; gap:6px;
  padding:5px 12px 5px 8px;
  background: var(--admin-accent-bg);
  border:1px solid var(--admin-border); border-radius:20px;
  margin-bottom:12px;
}
.admin-badge-dot {
  width:6px; height:6px; border-radius:50%;
  background: var(--admin-accent);
  box-shadow: 0 0 6px var(--admin-accent);
  animation: pulse 2s ease infinite;
}
.admin-badge-text { font-size:11px; font-weight:600; color:var(--admin-accent); letter-spacing:.06em; text-transform:uppercase; }
.admin-title { font-size:26px; font-weight:700; letter-spacing:-.02em; line-height:1.2; margin-bottom:4px; }
.admin-sub   { font-size:13px; color:var(--text-muted); }

/* ── TABS ── */
.tab-nav {
  display:flex; gap:6px; margin-bottom:24px;
  background: var(--glass); border:1px solid var(--glass-border);
  border-radius:12px; padding:4px;
  animation: fadeInUp 0.4s ease 0.05s both;
}
.tab-btn {
  flex:1; padding:9px 4px; border-radius:9px; border:none;
  background:transparent; color:var(--text-muted);
  font-family:inherit; font-size:13px; font-weight:500;
  cursor:pointer; transition: all .2s ease;
  display:flex; align-items:center; justify-content:center; gap:6px;
  -webkit-tap-highlight-color: transparent;
}
.tab-btn:hover { color:var(--text-secondary); }
.tab-btn.active {
  background: var(--admin-accent-bg);
  color: var(--admin-accent);
  border:1px solid var(--admin-border);
}
.tab-badge {
  background: var(--status-pending);
  color: #000; font-size:10px; font-weight:700;
  padding:1px 6px; border-radius:20px; line-height:1.6;
}

/* ── TAB SECTIONS ── */
.tab-section { display:none; }
.tab-section.active { display:block; }

/* ── RATE CARDS ── */
.rate-cards { display:flex; flex-direction:column; gap:12px; }
.rate-card {
  background: var(--glass);
  border:1px solid var(--glass-border);
  border-radius: var(--radius);
  overflow:hidden;
  animation: fadeInUp 0.4s ease both;
}
.rate-card:nth-child(1) { animation-delay:.08s; }
.rate-card:nth-child(2) { animation-delay:.14s; }
.rate-card-header {
  display:flex; align-items:center; justify-content:space-between;
  padding:16px 16px 0;
}
.rate-flag {
  width:36px; height:36px; border-radius:10px;
  background: var(--admin-accent-bg); border:1px solid var(--admin-border);
  display:flex; align-items:center; justify-content:center;
  font-size:20px; flex-shrink:0;
}
.rate-cur-label { font-size:13px; font-weight:600; color:var(--text-secondary); }
.rate-cur-name  { font-size:11px; color:var(--text-muted); margin-top:2px; }
.rate-current {
  text-align:right;
}
.rate-current-value { font-size:22px; font-weight:700; letter-spacing:-.02em; }
.rate-current-unit  { font-size:11px; color:var(--text-muted); margin-top:1px; }
.rate-updated {
  padding:8px 16px 12px;
  font-size:11px; color:var(--text-muted);
  border-bottom:1px solid var(--glass-border);
}
.rate-form {
  padding:14px 16px;
  display:flex; gap:8px; align-items:center;
}
.rate-input-wrap { flex:1; position:relative; }
.rate-input-label {
  position:absolute; top:8px; left:12px;
  font-size:10px; font-weight:600; color:var(--text-muted); letter-spacing:.04em; text-transform:uppercase;
}
.rate-input {
  width:100%; padding:22px 12px 8px;
  background: rgba(255,255,255,0.06);
  border:1px solid rgba(255,255,255,0.10);
  border-radius:10px; color:var(--text);
  font-family:inherit; font-size:16px; font-weight:600;
  outline:none; transition: border-color .2s;
  -moz-appearance:textfield;
}
.rate-input::-webkit-outer-spin-button,
.rate-input::-webkit-inner-spin-button { -webkit-appearance:none; }
.rate-input:focus { border-color: var(--admin-accent); }
.rate-btn {
  height:52px; padding:0 18px;
  background: var(--admin-accent-bg);
  border:1px solid var(--admin-border);
  border-radius:10px; color:var(--admin-accent);
  font-family:inherit; font-size:13px; font-weight:600;
  cursor:pointer; transition: all .2s ease; white-space:nowrap;
  -webkit-tap-highlight-color: transparent;
}
.rate-btn:hover { background: rgba(167,139,250,0.18); }
.rate-btn:active { transform: scale(.96); }

/* ── MODAL ── */
.modal-overlay {
  position:fixed; inset:0; z-index:400;
  background: rgba(0,0,0,0.65);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  display:flex; align-items:flex-end; justify-content:center;
  padding-bottom: calc(var(--safe-bottom) + 16px);
  opacity:0; visibility:hidden;
  transition: opacity .25s ease, visibility .25s ease;
}
.modal-overlay.open { opacity:1; visibility:visible; }
.modal-box {
  width:100%; max-width:480px;
  background: #181d2e;
  border:1px solid rgba(255,255,255,0.08);
  border-radius:20px; padding:24px;
  transform: translateY(20px);
  transition: transform .25s ease;
}
.modal-overlay.open .modal-box { transform: translateY(0); }
.modal-title { font-size:16px; font-weight:700; margin-bottom:8px; }
.modal-body  { font-size:14px; color:var(--text-secondary); line-height:1.6; margin-bottom:20px; }
.modal-body strong { color:var(--admin-accent); }
.modal-actions { display:flex; gap:10px; }
.modal-cancel {
  flex:1; padding:13px; border-radius:10px;
  background: var(--glass); border:1px solid var(--glass-border);
  color:var(--text-secondary); font-family:inherit; font-size:14px; font-weight:600;
  cursor:pointer; transition: background .2s;
}
.modal-cancel:hover { background: var(--glass-hover); }
.modal-confirm {
  flex:2; padding:13px; border-radius:10px;
  background: var(--admin-accent-bg);
  border:1px solid var(--admin-border);
  color:var(--admin-accent); font-family:inherit; font-size:14px; font-weight:600;
  cursor:pointer; transition: all .2s;
}
.modal-confirm:hover { background: rgba(167,139,250,0.18); }
.modal-confirm:active { transform: scale(.97); }

/* ── TOAST ── */
.toast {
  position:fixed; bottom: calc(var(--nav-h) + var(--safe-bottom) + 16px);
  left:50%; transform: translateX(-50%) translateY(8px);
  z-index:500; padding:11px 20px;
  border-radius:25px; font-size:13px; font-weight:500;
  white-space:nowrap; pointer-events:none;
  opacity:0; transition: opacity .3s ease, transform .3s ease;
}
.toast.show { opacity:1; transform: translateX(-50%) translateY(0); }
.toast-success { background:#1a3d1a; border:1px solid rgba(52,211,153,.3); color: var(--status-ok); }
.toast-error   { background:#3d1a1a; border:1px solid rgba(248,113,113,.3); color: var(--status-failed); }

/* ── COMING SOON placeholder ── */
.coming-card {
  background: var(--glass); border:1px dashed rgba(167,139,250,0.2);
  border-radius: var(--radius); padding:40px 20px;
  text-align:center; color:var(--text-muted); font-size:14px;
  animation: fadeInUp 0.4s ease both;
}
.coming-card svg { width:32px; height:32px; margin-bottom:12px; opacity:.4; }

/* ── BOTTOM NAV ── */
.bottom-nav {
  position:fixed; bottom:0; left:0; right:0; z-index:100;
  background: rgba(11,15,26,0.82);
  backdrop-filter: blur(24px) saturate(180%);
  -webkit-backdrop-filter: blur(24px) saturate(180%);
  border-top:1px solid rgba(255,255,255,0.05);
  padding-bottom: var(--safe-bottom);
  animation: slideUp 0.4s ease 0.2s both;
}
.nav-inner {
  display:flex; align-items:center; justify-content:space-around;
  height: var(--nav-h); max-width:520px; margin:0 auto; padding:0 4px;
}
.nav-item {
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  gap:4px; padding:6px 2px; min-width:48px;
  text-decoration:none; color:var(--text-muted);
  transition:color .2s ease; -webkit-tap-highlight-color:transparent;
  background:none; border:none; font-family:inherit; cursor:pointer;
}
.nav-item:hover { color:var(--text-secondary); }
.nav-item.active { color:var(--admin-accent); }
.nav-item.active .nav-indicator { opacity:1; transform:scaleX(1); background:var(--admin-accent); }
.nav-icon { width:22px; height:22px; display:flex; align-items:center; justify-content:center; }
.nav-icon svg { width:22px; height:22px; }
.nav-label { font-size:10px; font-weight:500; letter-spacing:.01em; white-space:nowrap; }
.nav-indicator {
  width:16px; height:2px; border-radius:1px;
  background: var(--gradient); opacity:0; transform:scaleX(0);
  transition: all .2s ease;
}

/* ── ANIMATIONS ── */
@keyframes fadeInUp { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
@keyframes slideUp  { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
@keyframes pulse    { 0%,100% { opacity:1; } 50% { opacity:.45; } }

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { animation-duration:.01ms !important; transition-duration:.01ms !important; }
}
@media (min-width:600px) {
  .app-content { padding-left:24px; padding-right:24px; }
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
    <div class="header-avatar"><?= htmlspecialchars($avi) ?></div>
  </div>
</header>


<!-- MAIN -->
<main class="app-content">

  <!-- Hero -->
  <div class="admin-hero">
    <div class="admin-badge">
      <span class="admin-badge-dot"></span>
      <span class="admin-badge-text">Panel de administración</span>
    </div>
    <h1 class="admin-title">Control KBP PAY</h1>
    <p class="admin-sub">Acceso exclusivo · <?= htmlspecialchars($adm['email'] ?? '') ?></p>
  </div>

  <!-- Tab nav -->
  <div class="tab-nav">
    <button class="tab-btn" data-tab="tasas">Tasas</button>
    <button class="tab-btn" data-tab="movimientos">Movimientos</button>
    <button class="tab-btn" data-tab="analitica">Analítica</button>
  </div>

  <!-- ═══════════════════════════════
       SECCIÓN: TASAS
       ═══════════════════════════════ -->
  <div class="tab-section" id="section-tasas">
    <div class="rate-cards">

      <!-- EUR -->
      <div class="rate-card">
        <div class="rate-card-header">
          <div style="display:flex;align-items:center;gap:12px;">
            <div class="rate-flag">🇪🇺</div>
            <div>
              <div class="rate-cur-label">EUR / VES</div>
              <div class="rate-cur-name">Euro · Bolívar venezolano</div>
            </div>
          </div>
          <div class="rate-current">
            <div class="rate-current-value"><?= number_format($eurRate, 2, '.', ',') ?></div>
            <div class="rate-current-unit">VES por EUR</div>
          </div>
        </div>
        <div class="rate-updated"><?= fmtUpd($eurUpdated, $meses) ?></div>
        <form class="rate-form" id="form-EUR" method="POST" onsubmit="openRateModal(event,'EUR',<?= $eurRate ?>)">
          <input type="hidden" name="action" value="update_rate">
          <input type="hidden" name="currency" value="EUR">
          <div class="rate-input-wrap">
            <label class="rate-input-label">Nueva tasa VES</label>
            <input class="rate-input" type="number" name="new_rate" id="input-EUR"
                   value="<?= number_format($eurRate, 2, '.', '') ?>"
                   step="0.01" min="0.01" required>
          </div>
          <button class="rate-btn" type="submit">Actualizar</button>
        </form>
      </div>

      <!-- USD -->
      <div class="rate-card">
        <div class="rate-card-header">
          <div style="display:flex;align-items:center;gap:12px;">
            <div class="rate-flag">🇺🇸</div>
            <div>
              <div class="rate-cur-label">USD / VES</div>
              <div class="rate-cur-name">Dólar · Bolívar venezolano</div>
            </div>
          </div>
          <div class="rate-current">
            <div class="rate-current-value"><?= number_format($usdRate, 2, '.', ',') ?></div>
            <div class="rate-current-unit">VES por USD</div>
          </div>
        </div>
        <div class="rate-updated"><?= fmtUpd($usdUpdated, $meses) ?></div>
        <form class="rate-form" id="form-USD" method="POST" onsubmit="openRateModal(event,'USD',<?= $usdRate ?>)">
          <input type="hidden" name="action" value="update_rate">
          <input type="hidden" name="currency" value="USD">
          <div class="rate-input-wrap">
            <label class="rate-input-label">Nueva tasa VES</label>
            <input class="rate-input" type="number" name="new_rate" id="input-USD"
                   value="<?= number_format($usdRate, 2, '.', '') ?>"
                   step="0.01" min="0.01" required>
          </div>
          <button class="rate-btn" type="submit">Actualizar</button>
        </form>
      </div>

    </div>
  </div><!-- /tasas -->

  <!-- ═══════════════════════════════
       SECCIÓN: MOVIMIENTOS (próximo)
       ═══════════════════════════════ -->
  <div class="tab-section" id="section-movimientos">
    <div class="coming-card">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M8 3L4 7l4 4"/><path d="M4 7h16"/>
        <path d="M16 21l4-4-4-4"/><path d="M20 17H4"/>
      </svg>
      <div>Módulo de movimientos — próximamente</div>
    </div>
  </div>

  <!-- ═══════════════════════════════
       SECCIÓN: ANALÍTICA (próximo)
       ═══════════════════════════════ -->
  <div class="tab-section" id="section-analitica">
    <div class="coming-card">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="20" x2="18" y2="10"/>
        <line x1="12" y1="20" x2="12" y2="4"/>
        <line x1="6"  y1="20" x2="6"  y2="14"/>
      </svg>
      <div>Módulo de analítica — próximamente</div>
    </div>
  </div>

</main>


<!-- MODAL: confirmación tasa -->
<div class="modal-overlay" id="rateModal">
  <div class="modal-box">
    <div class="modal-title">Confirmar cambio de tasa</div>
    <div class="modal-body">
      ¿Actualizar la tasa <strong id="mCur"></strong> de
      <strong id="mOld"></strong> a <strong id="mNew"></strong> VES?
    </div>
    <div class="modal-actions">
      <button class="modal-cancel" onclick="closeModal('rateModal')">Cancelar</button>
      <button class="modal-confirm" id="rateConfirmBtn">Confirmar</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>


<!-- BOTTOM NAV -->
<nav class="bottom-nav">
  <div class="nav-inner">

    <a class="nav-item" href="/app/pay/tasas.php">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3L4 7l4 4"/><path d="M4 7h16"/><path d="M16 21l4-4-4-4"/><path d="M20 17H4"/></svg></span>
      <span class="nav-label">Tasas</span>
      <span class="nav-indicator"></span>
    </a>

    <a class="nav-item" href="/app/pay/beneficiarios.php">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
      <span class="nav-label">Beneficiarios</span>
      <span class="nav-indicator"></span>
    </a>

    <a class="nav-item" href="/app/pay/metodos.php">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span>
      <span class="nav-label">Métodos</span>
      <span class="nav-indicator"></span>
    </a>

    <a class="nav-item" href="/app/pay/ayuda.php">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
      <span class="nav-label">Ayuda</span>
      <span class="nav-indicator"></span>
    </a>

    <a class="nav-item" href="/app/pay/perfil.php">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
      <span class="nav-label">Perfil</span>
      <span class="nav-indicator"></span>
    </a>

    <a class="nav-item active nav-admin" href="/app/pay/admin.php">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
      <span class="nav-label">Admin</span>
      <span class="nav-indicator"></span>
    </a>

  </div>
</nav>


<script>
const TOAST_DATA = <?= json_encode($toast) ?>;
const ACTIVE_TAB = <?= json_encode($activeTab) ?>;

/* ── Tabs ── */
function switchTab(name) {
  document.querySelectorAll('.tab-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  const section = document.getElementById('section-' + name);
  const btn = document.querySelector('[data-tab="' + name + '"]');
  if (section) section.classList.add('active');
  if (btn) btn.classList.add('active');
  const url = new URL(window.location);
  url.searchParams.set('tab', name);
  history.replaceState({}, '', url);
}

document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => switchTab(btn.dataset.tab));
});

/* ── Rate modal ── */
let pendingForm = null;

function openRateModal(e, currency, oldRate) {
  e.preventDefault();
  const input = document.getElementById('input-' + currency);
  const newRate = parseFloat(input.value);
  if (isNaN(newRate) || newRate <= 0) return;

  document.getElementById('mCur').textContent = currency;
  document.getElementById('mOld').textContent = oldRate.toLocaleString('es-ES', {minimumFractionDigits:2, maximumFractionDigits:4});
  document.getElementById('mNew').textContent = newRate.toLocaleString('es-ES', {minimumFractionDigits:2, maximumFractionDigits:4});

  pendingForm = document.getElementById('form-' + currency);
  document.getElementById('rateModal').classList.add('open');
}

document.getElementById('rateConfirmBtn').addEventListener('click', function() {
  if (pendingForm) {
    pendingForm.onsubmit = null; // evitar loop
    pendingForm.submit();
  }
  closeModal('rateModal');
});

function closeModal(id) {
  document.getElementById(id).classList.remove('open');
  pendingForm = null;
}

// Cerrar modal al clic en backdrop
document.getElementById('rateModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal('rateModal');
});

/* ── Toast ── */
function showToast(msg, type) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast toast-' + type + ' show';
  setTimeout(() => { t.className = 'toast'; }, 3500);
}

/* ── Init ── */
document.addEventListener('DOMContentLoaded', function() {
  switchTab(ACTIVE_TAB || 'tasas');
  if (TOAST_DATA) {
    setTimeout(() => showToast(TOAST_DATA.msg, TOAST_DATA.type), 300);
  }
});

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/app/pay/sw.js', { scope: '/app/pay/' });
}
</script>

</body>
</html>
