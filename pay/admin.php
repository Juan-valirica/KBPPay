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

/* ── POST: actualizar estado transacción ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_tx_status') {
    $txId   = (int)($_POST['tx_id']       ?? 0);
    $status = trim($_POST['new_status']   ?? '');
    $reason = trim($_POST['failed_reason'] ?? '');
    $valid  = ['pending','paid','completed','cancelled'];
    if ($txId > 0 && in_array($status, $valid, true)) {
        $pdo->prepare("UPDATE transactions SET status = ?, failed_reason = ? WHERE id = ?")
            ->execute([$status, $status === 'cancelled' ? $reason : null, $txId]);
        $sub = in_array($status, ['paid','completed','cancelled']) ? 'gestionados' : 'pendientes';
        header("Location: /app/pay/admin.php?tab=movimientos&sub={$sub}&msg=tx_ok");
    } else {
        header("Location: /app/pay/admin.php?tab=movimientos&msg=tx_err");
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

/* ── Transacciones ── */
$txSQL = "
    SELECT
        t.id, t.amount, t.currency, t.commission, t.exchange_rate,
        t.amount_received, t.total_to_pay, t.status, t.failed_reason, t.created_at,
        COALESCE(u.first_name,'')             AS u_first,
        COALESCE(u.last_name,'')              AS u_last,
        COALESCE(u.email,'')                  AS u_email,
        COALESCE(b.first_name,'')             AS b_first,
        COALESCE(b.last_name,'')              AS b_last,
        COALESCE(b.bank_name,'')              AS b_bank,
        COALESCE(b.account_number,'')         AS b_account,
        COALESCE(b.account_type,'')           AS b_actype,
        COALESCE(b.id_type,'')                AS b_idtype,
        COALESCE(b.id_number,'')              AS b_idnum,
        COALESCE(b.email,'')                  AS b_email,
        COALESCE(b.residence_country,'')      AS b_country,
        COALESCE(b.send_reason,'')            AS b_reason,
        COALESCE(b.relation_beneficiary,'')   AS b_relation
    FROM transactions t
    LEFT JOIN users        u ON u.id = t.user_id
    LEFT JOIN beneficiaries b ON b.id = t.beneficiary_id
";
$pendingTx = $pdo->query($txSQL . " WHERE t.status = 'pending' ORDER BY t.created_at ASC")->fetchAll();
$managedTx = $pdo->query($txSQL . " WHERE t.status IN ('paid','completed','cancelled') ORDER BY t.created_at DESC LIMIT 100")->fetchAll();

/* mapa id→tx para JS */
$txMap = [];
foreach (array_merge($pendingTx, $managedTx) as $t) {
    $txMap[(int)$t['id']] = $t;
}

/* ── Page state ── */
$activeTab = $_GET['tab'] ?? 'tasas';
$activeSub = ($_GET['sub'] ?? '') === 'gestionados' ? 'gestionados' : 'pendientes';
$msgParam  = $_GET['msg'] ?? '';
$curParam  = strtoupper($_GET['cur'] ?? '');

$meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
function fmtUpd(?string $d, array $m): string {
    if (!$d) return 'Nunca actualizada';
    $t = strtotime($d);
    return 'Actualizada el ' . date('d',$t) . ' ' . $m[(int)date('m',$t)-1] . ' · ' . date('H:i',$t);
}

/* helper: title-case UTF-8 */
function tc(string $s): string {
    return mb_convert_case(mb_strtolower(trim($s), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

/* ── Analítica ── */
$anaRows = $pdo->query(
    "SELECT status, currency, amount, commission, user_id, created_at FROM transactions"
)->fetchAll();
$totalEnvios = count($anaRows);
$anaEUR = 0.0; $anaUSD = 0.0; $anaCommEUR = 0.0; $anaCommUSD = 0.0;
$anaUsers = []; $anaStat = ['pending'=>0,'paid'=>0,'completed'=>0,'cancelled'=>0];
foreach ($anaRows as $r) {
    if ($r['currency'] === 'EUR') { $anaEUR     += (float)$r['amount']; $anaCommEUR += (float)$r['commission']; }
    if ($r['currency'] === 'USD') { $anaUSD     += (float)$r['amount']; $anaCommUSD += (float)$r['commission']; }
    $anaUsers[$r['user_id']] = 1;
    if (isset($anaStat[$r['status']])) $anaStat[$r['status']]++;
}
$anaActiveUsers = count($anaUsers);

$ana7 = [];
for ($i = 6; $i >= 0; $i--) { $ana7[date('Y-m-d', strtotime("-{$i} days"))] = 0; }
foreach ($anaRows as $r) { $d = substr($r['created_at'], 0, 10); if (isset($ana7[$d])) $ana7[$d]++; }
$ana7Max = max(array_values($ana7) ?: [1]) ?: 1;

$anaTopBanks = $pdo->query("
    SELECT COALESCE(b.bank_name,'Sin banco') AS bank, COUNT(*) AS cnt
    FROM transactions t
    LEFT JOIN beneficiaries b ON b.id = t.beneficiary_id
    WHERE b.bank_name IS NOT NULL AND b.bank_name != ''
    GROUP BY b.bank_name ORDER BY cnt DESC LIMIT 5
")->fetchAll();
$anaTopMax = !empty($anaTopBanks) ? (int)$anaTopBanks[0]['cnt'] : 1;

/* ── Toast ── */
$toasts = [
    'rate_ok'  => ['msg' => "Tasa {$curParam}/VES actualizada correctamente", 'type' => 'success'],
    'rate_err' => ['msg' => 'Valor inválido — no se actualizó la tasa',        'type' => 'error'],
    'tx_ok'    => ['msg' => 'Estado del envío actualizado',                    'type' => 'success'],
    'tx_err'   => ['msg' => 'Error al actualizar el estado',                   'type' => 'error'],
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
  --bg:               #0B0F1A;
  --glass:            rgba(255,255,255,0.04);
  --glass-hover:      rgba(255,255,255,0.07);
  --glass-border:     rgba(255,255,255,0.07);
  --text:             #FFFFFF;
  --text-secondary:   rgba(255,255,255,0.65);
  --text-muted:       rgba(255,255,255,0.38);
  --green:            #52ae32;
  --blue:             #3259fd;
  --gradient:         linear-gradient(135deg, #52ae32, #3259fd);
  --admin-accent:     #a78bfa;
  --admin-accent-bg:  rgba(167,139,250,0.10);
  --admin-border:     rgba(167,139,250,0.20);
  --status-ok:        #34D399;
  --status-ok-bg:     rgba(52,211,153,0.12);
  --status-pending:   #FBBF24;
  --status-pending-bg:rgba(251,191,36,0.12);
  --status-paid:      #60a5fa;
  --status-paid-bg:   rgba(96,165,250,0.12);
  --status-failed:    #F87171;
  --status-failed-bg: rgba(248,113,113,0.12);
  --radius:           16px;
  --nav-h:            68px;
  --header-h:         60px;
  --safe-top:         env(safe-area-inset-top,    0px);
  --safe-bottom:      env(safe-area-inset-bottom, 0px);
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
html { height:100%; -webkit-text-size-adjust:100%; }
body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  background: var(--bg); color: var(--text);
  min-height:100%; overflow-x:hidden;
  -webkit-font-smoothing:antialiased;
}
body::before {
  content:''; position:fixed; inset:0;
  background:
    radial-gradient(ellipse 60% 50% at 15% 10%, rgba(167,139,250,0.08) 0%, transparent 60%),
    radial-gradient(ellipse 50% 60% at 85% 90%, rgba(50,89,253,0.10) 0%, transparent 60%);
  pointer-events:none; z-index:0;
}
body::-webkit-scrollbar { width:0; }
body { scrollbar-width:none; }

/* ── HEADER ── */
.app-header {
  position:fixed; top:0; left:0; right:0; z-index:100;
  padding-top:var(--safe-top);
  background:rgba(11,15,26,0.75);
  backdrop-filter:blur(24px) saturate(180%);
  -webkit-backdrop-filter:blur(24px) saturate(180%);
  border-bottom:1px solid rgba(167,139,250,0.12);
}
.header-inner {
  display:flex; align-items:center; justify-content:space-between;
  height:var(--header-h); padding:0 20px;
  max-width:520px; margin:0 auto;
}
.header-logo   { height:28px; width:auto; display:block; }
.header-avatar {
  width:36px; height:36px; border-radius:50%;
  background:var(--gradient); color:#fff;
  display:flex; align-items:center; justify-content:center;
  font-weight:700; font-size:12px; flex-shrink:0;
  box-shadow:0 2px 8px rgba(167,139,250,0.3);
}

/* ── CONTENT ── */
.app-content {
  position:relative; z-index:1;
  max-width:520px; margin:0 auto;
  padding:calc(var(--header-h) + var(--safe-top) + 24px) 20px calc(var(--nav-h) + var(--safe-bottom) + 32px);
  min-height:100vh;
}

/* ── HERO ── */
.admin-hero { margin-bottom:24px; animation:fadeInUp 0.4s ease both; }
.admin-badge {
  display:inline-flex; align-items:center; gap:6px;
  padding:5px 12px 5px 8px;
  background:var(--admin-accent-bg); border:1px solid var(--admin-border);
  border-radius:20px; margin-bottom:12px;
}
.admin-badge-dot {
  width:6px; height:6px; border-radius:50%;
  background:var(--admin-accent); box-shadow:0 0 6px var(--admin-accent);
  animation:pulse 2s ease infinite;
}
.admin-badge-text { font-size:11px; font-weight:600; color:var(--admin-accent); letter-spacing:.06em; text-transform:uppercase; }
.admin-title { font-size:26px; font-weight:700; letter-spacing:-.02em; line-height:1.2; margin-bottom:4px; }
.admin-sub   { font-size:13px; color:var(--text-muted); }

/* ── MAIN TABS ── */
.tab-nav {
  display:flex; gap:6px; margin-bottom:24px;
  background:var(--glass); border:1px solid var(--glass-border);
  border-radius:12px; padding:4px;
  animation:fadeInUp 0.4s ease 0.05s both;
}
.tab-btn {
  flex:1; padding:9px 4px; border-radius:9px; border:none;
  background:transparent; color:var(--text-muted);
  font-family:inherit; font-size:13px; font-weight:500;
  cursor:pointer; transition:all .2s ease;
  display:flex; align-items:center; justify-content:center; gap:6px;
  -webkit-tap-highlight-color:transparent;
}
.tab-btn:hover { color:var(--text-secondary); }
.tab-btn.active { background:var(--admin-accent-bg); color:var(--admin-accent); border:1px solid var(--admin-border); }
.tab-badge {
  background:var(--status-pending); color:#000;
  font-size:10px; font-weight:700; padding:1px 6px; border-radius:20px; line-height:1.6;
}
.tab-section { display:none; }
.tab-section.active { display:block; }

/* ── RATE CARDS ── */
.rate-cards { display:flex; flex-direction:column; gap:12px; }
.rate-card {
  background:var(--glass); border:1px solid var(--glass-border);
  border-radius:var(--radius); overflow:hidden;
  animation:fadeInUp 0.4s ease both;
}
.rate-card:nth-child(1) { animation-delay:.08s; }
.rate-card:nth-child(2) { animation-delay:.14s; }
.rate-card-header { display:flex; align-items:center; justify-content:space-between; padding:16px 16px 0; }
.rate-flag {
  width:36px; height:36px; border-radius:10px;
  background:var(--admin-accent-bg); border:1px solid var(--admin-border);
  display:flex; align-items:center; justify-content:center;
  font-size:20px; flex-shrink:0;
}
.rate-cur-label { font-size:13px; font-weight:600; color:var(--text-secondary); }
.rate-cur-name  { font-size:11px; color:var(--text-muted); margin-top:2px; }
.rate-current   { text-align:right; }
.rate-current-value { font-size:22px; font-weight:700; letter-spacing:-.02em; }
.rate-current-unit  { font-size:11px; color:var(--text-muted); margin-top:1px; }
.rate-updated { padding:8px 16px 12px; font-size:11px; color:var(--text-muted); border-bottom:1px solid var(--glass-border); }
.rate-form { padding:14px 16px; display:flex; gap:8px; align-items:center; }
.rate-input-wrap { flex:1; position:relative; }
.rate-input-label { position:absolute; top:8px; left:12px; font-size:10px; font-weight:600; color:var(--text-muted); letter-spacing:.04em; text-transform:uppercase; }
.rate-input {
  width:100%; padding:22px 12px 8px;
  background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.10);
  border-radius:10px; color:var(--text);
  font-family:inherit; font-size:16px; font-weight:600;
  outline:none; transition:border-color .2s; -moz-appearance:textfield;
}
.rate-input::-webkit-outer-spin-button,
.rate-input::-webkit-inner-spin-button { -webkit-appearance:none; }
.rate-input:focus { border-color:var(--admin-accent); }
.rate-btn {
  height:52px; padding:0 18px;
  background:var(--admin-accent-bg); border:1px solid var(--admin-border);
  border-radius:10px; color:var(--admin-accent);
  font-family:inherit; font-size:13px; font-weight:600;
  cursor:pointer; transition:all .2s ease; white-space:nowrap;
  -webkit-tap-highlight-color:transparent;
}
.rate-btn:hover  { background:rgba(167,139,250,0.18); }
.rate-btn:active { transform:scale(.96); }

/* ── SUB-TABS (movimientos) ── */
.sub-nav {
  display:flex; gap:0; margin-bottom:16px;
  border-bottom:1px solid var(--glass-border);
}
.sub-btn {
  padding:9px 18px 11px; border:none; background:transparent;
  color:var(--text-muted); font-family:inherit; font-size:13px; font-weight:500;
  cursor:pointer; border-bottom:2px solid transparent;
  transition:all .2s; margin-bottom:-1px;
  -webkit-tap-highlight-color:transparent;
  display:flex; align-items:center; gap:6px;
}
.sub-btn.active { color:var(--admin-accent); border-bottom-color:var(--admin-accent); }
.sub-section { display:none; }
.sub-section.active { display:block; }

/* ── TX CARDS ── */
.atx-list { display:flex; flex-direction:column; gap:8px; }
.atx-card {
  display:flex; align-items:center; gap:12px;
  padding:13px 14px;
  background:var(--glass); border:1px solid var(--glass-border);
  border-radius:14px; cursor:pointer;
  transition:background .2s, transform .15s;
  -webkit-tap-highlight-color:transparent;
}
.atx-card:hover  { background:var(--glass-hover); }
.atx-card:active { transform:scale(.985); }
.atx-av {
  width:38px; height:38px; border-radius:50%; flex-shrink:0;
  background:var(--admin-accent-bg); border:1px solid var(--admin-border);
  display:flex; align-items:center; justify-content:center;
  font-size:13px; font-weight:700; color:var(--admin-accent);
}
.atx-info { flex:1; min-width:0; }
.atx-names {
  font-size:13px; font-weight:600;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.atx-names span { color:var(--text-muted); font-weight:400; }
.atx-meta  { font-size:11px; color:var(--text-muted); margin-top:3px; }
.atx-right { text-align:right; flex-shrink:0; }
.atx-amount { font-size:13px; font-weight:700; white-space:nowrap; }
.atx-badge {
  display:inline-block; font-size:10px; font-weight:600;
  padding:2px 8px; border-radius:20px; margin-top:4px;
}
.s-pending   { color:var(--status-pending);  background:var(--status-pending-bg); }
.s-paid      { color:var(--status-paid);     background:var(--status-paid-bg); }
.s-completed { color:var(--status-ok);       background:var(--status-ok-bg); }
.s-cancelled { color:var(--status-failed);   background:var(--status-failed-bg); }

/* ── EMPTY STATE ── */
.empty-tx {
  text-align:center; padding:48px 20px;
  color:var(--text-muted); font-size:13px; line-height:1.6;
}
.empty-tx svg { width:28px; height:28px; margin:0 auto 12px; display:block; opacity:.3; }

/* ── SHEET BACKDROP ── */
.sheet-backdrop {
  position:fixed; inset:0; z-index:300;
  background:rgba(0,0,0,0.65);
  backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px);
  opacity:0; visibility:hidden;
  transition:opacity .3s ease, visibility .3s ease;
}
.sheet-backdrop.open { opacity:1; visibility:visible; }

/* ── DETAIL SHEET ── */
.detail-sheet {
  position:fixed; bottom:0; left:0; right:0; z-index:310;
  max-width:520px; margin:0 auto;
  background:#141826;
  border:1px solid rgba(255,255,255,0.09);
  border-bottom:none; border-radius:22px 22px 0 0;
  max-height:91vh; display:flex; flex-direction:column;
  transform:translateY(100%);
  transition:transform .36s cubic-bezier(.32,.72,0,1);
}
.detail-sheet.open { transform:translateY(0); }

.sheet-header {
  padding:10px 18px 14px; flex-shrink:0;
  border-bottom:1px solid rgba(255,255,255,0.06);
}
.sheet-drag {
  width:36px; height:4px; border-radius:2px;
  background:rgba(255,255,255,0.14); margin:0 auto 14px;
}
.sheet-header-row { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
.sheet-title  { font-size:17px; font-weight:700; margin-bottom:5px; }
.sheet-close  {
  width:30px; height:30px; border-radius:50%; flex-shrink:0;
  background:rgba(255,255,255,0.08); border:none;
  color:var(--text-muted); font-size:18px; line-height:1;
  display:flex; align-items:center; justify-content:center;
  cursor:pointer; transition:background .2s; margin-top:-2px;
}
.sheet-close:hover { background:rgba(255,255,255,0.13); }
.sheet-status-badge {
  display:inline-block; font-size:11px; font-weight:600;
  padding:3px 10px; border-radius:20px;
}

.sheet-body {
  flex:1; overflow-y:auto; padding:0 18px 6px;
  -webkit-overflow-scrolling:touch;
}
.sheet-body::-webkit-scrollbar { width:0; }

/* Amount hero */
.sheet-amt-block {
  padding:18px 0 14px;
  border-bottom:1px solid rgba(255,255,255,0.06);
  margin-bottom:14px;
}
.sheet-amt-main { font-size:30px; font-weight:700; letter-spacing:-.02em; }
.sheet-amt-sub  { font-size:13px; color:var(--text-muted); margin-top:4px; }

/* Info sections */
.sheet-section { margin-bottom:14px; }
.sheet-section-title {
  font-size:10px; font-weight:600; color:var(--text-muted);
  letter-spacing:.08em; text-transform:uppercase; margin-bottom:6px;
}
.sheet-rows {
  background:var(--glass); border:1px solid var(--glass-border);
  border-radius:12px; overflow:hidden;
}
.sheet-row {
  display:flex; align-items:flex-start; justify-content:space-between;
  padding:10px 13px; gap:14px;
  border-bottom:1px solid rgba(255,255,255,0.04);
}
.sheet-row:last-child { border-bottom:none; }
.sheet-row-label { font-size:12px; color:var(--text-muted); flex-shrink:0; padding-top:1px; }
.sheet-row-value { font-size:12px; font-weight:500; text-align:right; word-break:break-word; }
.sheet-row-cancelled .sheet-row-value { color:var(--status-failed); }

/* ── SHEET FOOTER ── */
.sheet-footer {
  padding:13px 18px calc(var(--safe-bottom) + 13px);
  border-top:1px solid rgba(255,255,255,0.06);
  flex-shrink:0;
}
.sheet-footer-label {
  font-size:10px; font-weight:600; color:var(--text-muted);
  letter-spacing:.07em; text-transform:uppercase; margin-bottom:9px;
}
.status-pick-row { display:flex; gap:6px; margin-bottom:9px; flex-wrap:wrap; }
.spick {
  flex:1; min-width:62px; padding:9px 4px;
  border-radius:9px; border:1px solid var(--glass-border);
  background:var(--glass); color:var(--text-secondary);
  font-family:inherit; font-size:12px; font-weight:600;
  cursor:pointer; transition:all .2s; text-align:center;
  -webkit-tap-highlight-color:transparent;
}
.spick:hover { background:var(--glass-hover); }
.spick.sel-pending   { background:var(--status-pending-bg);  border-color:var(--status-pending);  color:var(--status-pending); }
.spick.sel-paid      { background:var(--status-paid-bg);     border-color:var(--status-paid);     color:var(--status-paid); }
.spick.sel-completed { background:var(--status-ok-bg);       border-color:var(--status-ok);       color:var(--status-ok); }
.spick.sel-cancelled { background:var(--status-failed-bg);   border-color:var(--status-failed);   color:var(--status-failed); }

.reason-group { margin-bottom:9px; }
.reason-label { font-size:11px; color:var(--text-muted); margin-bottom:5px; }
.reason-input {
  width:100%; padding:10px 12px;
  background:var(--glass); border:1px solid rgba(248,113,113,0.3);
  border-radius:10px; color:var(--text);
  font-family:inherit; font-size:13px; line-height:1.5;
  resize:none; outline:none; min-height:70px;
  transition:border-color .2s;
}
.reason-input:focus { border-color:var(--status-failed); }
.reason-input::placeholder { color:var(--text-muted); }

.sheet-confirm-btn {
  width:100%; padding:14px; border-radius:12px; border:none;
  background:var(--admin-accent-bg); border:1px solid var(--admin-border);
  color:var(--admin-accent); font-family:inherit; font-size:14px; font-weight:600;
  cursor:pointer; transition:all .2s;
  opacity:.35; pointer-events:none;
}
.sheet-confirm-btn.ready { opacity:1; pointer-events:auto; }
.sheet-confirm-btn.ready:hover  { background:rgba(167,139,250,0.18); }
.sheet-confirm-btn.ready:active { transform:scale(.97); }

/* ── MODAL ── */
.modal-overlay {
  position:fixed; inset:0; z-index:400;
  background:rgba(0,0,0,0.65);
  backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px);
  display:flex; align-items:flex-end; justify-content:center;
  padding-bottom:calc(var(--safe-bottom) + 16px);
  opacity:0; visibility:hidden;
  transition:opacity .25s ease, visibility .25s ease;
}
.modal-overlay.open { opacity:1; visibility:visible; }
.modal-box {
  width:100%; max-width:480px;
  background:#181d2e; border:1px solid rgba(255,255,255,0.08);
  border-radius:20px; padding:24px;
  transform:translateY(20px); transition:transform .25s ease;
}
.modal-overlay.open .modal-box { transform:translateY(0); }
.modal-title   { font-size:16px; font-weight:700; margin-bottom:8px; }
.modal-body    { font-size:14px; color:var(--text-secondary); line-height:1.6; margin-bottom:20px; }
.modal-body strong { color:var(--admin-accent); }
.modal-actions { display:flex; gap:10px; }
.modal-cancel  {
  flex:1; padding:13px; border-radius:10px;
  background:var(--glass); border:1px solid var(--glass-border);
  color:var(--text-secondary); font-family:inherit; font-size:14px; font-weight:600;
  cursor:pointer; transition:background .2s;
}
.modal-cancel:hover { background:var(--glass-hover); }
.modal-confirm {
  flex:2; padding:13px; border-radius:10px;
  background:var(--admin-accent-bg); border:1px solid var(--admin-border);
  color:var(--admin-accent); font-family:inherit; font-size:14px; font-weight:600;
  cursor:pointer; transition:all .2s;
}
.modal-confirm:hover  { background:rgba(167,139,250,0.18); }
.modal-confirm:active { transform:scale(.97); }

/* ── TOAST ── */
.toast {
  position:fixed; bottom:calc(var(--nav-h) + var(--safe-bottom) + 16px);
  left:50%; transform:translateX(-50%) translateY(8px);
  z-index:500; padding:11px 20px; border-radius:25px;
  font-size:13px; font-weight:500; white-space:nowrap;
  pointer-events:none; opacity:0;
  transition:opacity .3s ease, transform .3s ease;
}
.toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
.toast-success { background:#1a3d1a; border:1px solid rgba(52,211,153,.3); color:var(--status-ok); }
.toast-error   { background:#3d1a1a; border:1px solid rgba(248,113,113,.3); color:var(--status-failed); }

/* ── COMING SOON ── */
.coming-card {
  background:var(--glass); border:1px dashed rgba(167,139,250,0.2);
  border-radius:var(--radius); padding:40px 20px;
  text-align:center; color:var(--text-muted); font-size:14px;
  animation:fadeInUp 0.4s ease both;
}
.coming-card svg { width:32px; height:32px; margin-bottom:12px; opacity:.4; }

/* ── BOTTOM NAV ── */
.bottom-nav {
  position:fixed; bottom:0; left:0; right:0; z-index:100;
  background:rgba(11,15,26,0.82);
  backdrop-filter:blur(24px) saturate(180%);
  -webkit-backdrop-filter:blur(24px) saturate(180%);
  border-top:1px solid rgba(255,255,255,0.05);
  padding-bottom:var(--safe-bottom);
  animation:slideUp 0.4s ease 0.2s both;
}
.nav-inner {
  display:flex; align-items:center; justify-content:space-around;
  height:var(--nav-h); max-width:520px; margin:0 auto; padding:0 4px;
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
.nav-icon  { width:22px; height:22px; display:flex; align-items:center; justify-content:center; }
.nav-icon svg { width:22px; height:22px; }
.nav-label { font-size:10px; font-weight:500; letter-spacing:.01em; white-space:nowrap; }
.nav-indicator {
  width:16px; height:2px; border-radius:1px;
  background:var(--gradient); opacity:0; transform:scaleX(0);
  transition:all .2s ease;
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

/* ── ANALYTICS ── */
.ana-block { margin-bottom:22px; }
.ana-label {
  font-size:11px; font-weight:600; color:var(--text-muted);
  letter-spacing:.08em; text-transform:uppercase; margin-bottom:10px;
}

/* KPI grid */
.kpi-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.kpi-card {
  background:var(--glass); border:1px solid var(--glass-border);
  border-radius:var(--radius); padding:14px 15px;
  animation:fadeInUp .4s ease both;
}
.kpi-card.span2 { grid-column:span 2; }
.kpi-icon {
  width:32px; height:32px; border-radius:9px;
  background:var(--admin-accent-bg); border:1px solid var(--admin-border);
  display:flex; align-items:center; justify-content:center;
  margin-bottom:10px; color:var(--admin-accent);
}
.kpi-icon svg { width:16px; height:16px; }
.kpi-value { font-size:20px; font-weight:700; letter-spacing:-.02em; line-height:1.15; }
.kpi-value.v-eur  { color:var(--status-paid); }
.kpi-value.v-usd  { color:var(--status-ok); }
.kpi-label { font-size:11px; color:var(--text-muted); margin-top:4px; }
.kpi-comm-row { display:flex; gap:20px; }
.kpi-comm-item { flex:1; }
.kpi-comm-val { font-size:18px; font-weight:700; letter-spacing:-.01em; }
.kpi-comm-val.eur { color:var(--status-paid); }
.kpi-comm-val.usd { color:var(--status-ok); }
.kpi-comm-cur { font-size:10px; color:var(--text-muted); margin-top:2px; }

/* Status distribution */
.stat-dist {
  background:var(--glass); border:1px solid var(--glass-border);
  border-radius:var(--radius); padding:14px 15px;
  animation:fadeInUp .4s ease .1s both;
}
.stat-row { display:flex; align-items:center; gap:10px; margin-bottom:11px; }
.stat-row:last-child { margin-bottom:0; }
.stat-name { font-size:12px; font-weight:500; width:82px; flex-shrink:0; }
.stat-bar-wrap { flex:1; height:6px; border-radius:3px; background:rgba(255,255,255,.07); }
.stat-bar { height:100%; border-radius:3px; transition:width .7s cubic-bezier(.4,0,.2,1); }
.stat-bar.b-pending   { background:var(--status-pending); }
.stat-bar.b-paid      { background:var(--status-paid); }
.stat-bar.b-completed { background:var(--status-ok); }
.stat-bar.b-cancelled { background:var(--status-failed); }
.stat-count { font-size:12px; font-weight:700; width:28px; text-align:right; flex-shrink:0; }

/* 7-day bar chart */
.chart-card {
  background:var(--glass); border:1px solid var(--glass-border);
  border-radius:var(--radius); padding:16px 14px 12px;
  animation:fadeInUp .4s ease .15s both;
}
.bar-chart-area {
  display:flex; align-items:flex-end; gap:5px;
  height:64px; margin-bottom:6px;
}
.bar-item {
  flex:1; border-radius:4px 4px 0 0; min-height:4px;
  background:linear-gradient(180deg, var(--admin-accent) 0%, rgba(167,139,250,.3) 100%);
}
.bar-item.b-zero { background:rgba(255,255,255,.08); }
.bar-labels-row { display:flex; gap:5px; }
.bar-label-col  { flex:1; text-align:center; }
.bar-day-lbl    { font-size:10px; color:var(--text-muted); }
.bar-cnt-lbl    { font-size:9px; color:var(--admin-accent); margin-top:1px; font-weight:600; }

/* Top banks */
.banks-card {
  background:var(--glass); border:1px solid var(--glass-border);
  border-radius:var(--radius); overflow:hidden;
  animation:fadeInUp .4s ease .2s both;
}
.bank-row {
  display:flex; align-items:center; gap:10px; padding:11px 14px;
  border-bottom:1px solid rgba(255,255,255,.04);
}
.bank-row:last-child { border-bottom:none; }
.bank-rank { font-size:12px; font-weight:700; color:var(--admin-accent); width:18px; flex-shrink:0; }
.bank-name { flex:1; font-size:12px; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.bank-bar-wrap { width:56px; height:5px; background:rgba(255,255,255,.07); border-radius:3px; flex-shrink:0; }
.bank-bar-fill { height:100%; border-radius:3px; background:var(--admin-accent); opacity:.6; }
.bank-count { font-size:12px; font-weight:700; color:var(--text-secondary); width:22px; text-align:right; flex-shrink:0; }
.banks-empty { padding:28px; text-align:center; color:var(--text-muted); font-size:13px; }
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
    <button class="tab-btn" data-tab="movimientos">
      Movimientos
      <?php if (count($pendingTx) > 0): ?>
        <span class="tab-badge"><?= count($pendingTx) ?></span>
      <?php endif; ?>
    </button>
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
       SECCIÓN: MOVIMIENTOS
       ═══════════════════════════════ -->
  <div class="tab-section" id="section-movimientos">

    <!-- Sub-tabs -->
    <div class="sub-nav">
      <button class="sub-btn" data-sub="pendientes">
        Pendientes
        <?php if (count($pendingTx) > 0): ?>
          <span class="tab-badge"><?= count($pendingTx) ?></span>
        <?php endif; ?>
      </button>
      <button class="sub-btn" data-sub="gestionados">Gestionados</button>
    </div>

    <!-- SUB: Pendientes -->
    <div class="sub-section" id="sub-pendientes">
      <?php if (empty($pendingTx)): ?>
        <div class="empty-tx">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8 3L4 7l4 4"/><path d="M4 7h16"/>
            <path d="M16 21l4-4-4-4"/><path d="M20 17H4"/>
          </svg>
          No hay envíos pendientes
        </div>
      <?php else: ?>
        <div class="atx-list">
          <?php foreach ($pendingTx as $tx): ?>
            <?php
              $bFirst = tc($tx['b_first']); $bLast = tc($tx['b_last']);
              $uFirst = tc($tx['u_first']); $uLast = tc($tx['u_last']);
              $bName  = trim($bFirst . ' ' . $bLast) ?: 'Destinatario';
              $uShort = explode(' ', $uFirst)[0] ?: 'Remitente';
              $ini    = mb_strtoupper(mb_substr($bFirst,0,1,'UTF-8').mb_substr($bLast,0,1,'UTF-8'),'UTF-8') ?: '?';
              $ts     = strtotime($tx['created_at']);
              $fecha  = date('d',$ts) . ' ' . $meses[(int)date('m',$ts)-1];
              $bank   = tc($tx['b_bank']) ?: '—';
            ?>
            <div class="atx-card" onclick="openSheet(<?= (int)$tx['id'] ?>)">
              <div class="atx-av"><?= htmlspecialchars($ini) ?></div>
              <div class="atx-info">
                <div class="atx-names">
                  <?= htmlspecialchars($uShort) ?> <span>→</span> <?= htmlspecialchars($bName) ?>
                </div>
                <div class="atx-meta"><?= htmlspecialchars($bank) ?> · <?= $fecha ?></div>
              </div>
              <div class="atx-right">
                <div class="atx-amount"><?= strtoupper(htmlspecialchars($tx['currency'])) ?> <?= number_format((float)$tx['amount'], 2) ?></div>
                <span class="atx-badge s-pending">Pendiente</span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div><!-- /pendientes -->

    <!-- SUB: Gestionados -->
    <div class="sub-section" id="sub-gestionados">
      <?php if (empty($managedTx)): ?>
        <div class="empty-tx">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="20" x2="18" y2="10"/>
            <line x1="12" y1="20" x2="12" y2="4"/>
            <line x1="6"  y1="20" x2="6"  y2="14"/>
          </svg>
          Aún no hay envíos gestionados
        </div>
      <?php else: ?>
        <div class="atx-list">
          <?php
            $sLabels = ['paid'=>'Pagado','completed'=>'Completado','cancelled'=>'Cancelado'];
            $sCls    = ['paid'=>'s-paid','completed'=>'s-completed','cancelled'=>'s-cancelled'];
          ?>
          <?php foreach ($managedTx as $tx): ?>
            <?php
              $bFirst = tc($tx['b_first']); $bLast = tc($tx['b_last']);
              $uFirst = tc($tx['u_first']); $uLast = tc($tx['u_last']);
              $bName  = trim($bFirst . ' ' . $bLast) ?: 'Destinatario';
              $uShort = explode(' ', $uFirst)[0] ?: 'Remitente';
              $ini    = mb_strtoupper(mb_substr($bFirst,0,1,'UTF-8').mb_substr($bLast,0,1,'UTF-8'),'UTF-8') ?: '?';
              $ts     = strtotime($tx['created_at']);
              $fecha  = date('d',$ts) . ' ' . $meses[(int)date('m',$ts)-1];
              $bank   = tc($tx['b_bank']) ?: '—';
              $sLabel = $sLabels[$tx['status']] ?? $tx['status'];
              $sCl    = $sCls[$tx['status']] ?? '';
            ?>
            <div class="atx-card" onclick="openSheet(<?= (int)$tx['id'] ?>)">
              <div class="atx-av"><?= htmlspecialchars($ini) ?></div>
              <div class="atx-info">
                <div class="atx-names">
                  <?= htmlspecialchars($uShort) ?> <span>→</span> <?= htmlspecialchars($bName) ?>
                </div>
                <div class="atx-meta"><?= htmlspecialchars($bank) ?> · <?= $fecha ?></div>
              </div>
              <div class="atx-right">
                <div class="atx-amount"><?= strtoupper(htmlspecialchars($tx['currency'])) ?> <?= number_format((float)$tx['amount'], 2) ?></div>
                <span class="atx-badge <?= $sCl ?>"><?= htmlspecialchars($sLabel) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div><!-- /gestionados -->

  </div><!-- /movimientos -->

  <!-- ═══════════════════════════════
       SECCIÓN: ANALÍTICA
       ═══════════════════════════════ -->
  <div class="tab-section" id="section-analitica">

    <!-- KPIs -->
    <div class="ana-block">
      <div class="ana-label">Resumen general</div>
      <div class="kpi-grid">

        <!-- Total envíos -->
        <div class="kpi-card" style="animation-delay:.05s">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <line x1="22" y1="2" x2="11" y2="13"/>
              <polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
          </div>
          <div class="kpi-value"><?= number_format($totalEnvios) ?></div>
          <div class="kpi-label">Total envíos</div>
        </div>

        <!-- Usuarios activos -->
        <div class="kpi-card" style="animation-delay:.08s">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </div>
          <div class="kpi-value"><?= number_format($anaActiveUsers) ?></div>
          <div class="kpi-label">Usuarios activos</div>
        </div>

        <!-- EUR enviado -->
        <div class="kpi-card" style="animation-delay:.11s">
          <div class="kpi-icon" style="font-size:18px;background:rgba(96,165,250,.12);border-color:rgba(96,165,250,.25)">🇪🇺</div>
          <div class="kpi-value v-eur"><?= number_format($anaEUR, 2, '.', ',') ?></div>
          <div class="kpi-label">EUR total enviado</div>
        </div>

        <!-- USD enviado -->
        <div class="kpi-card" style="animation-delay:.14s">
          <div class="kpi-icon" style="font-size:18px;background:rgba(52,211,153,.12);border-color:rgba(52,211,153,.25)">🇺🇸</div>
          <div class="kpi-value v-usd"><?= number_format($anaUSD, 2, '.', ',') ?></div>
          <div class="kpi-label">USD total enviado</div>
        </div>

        <!-- Comisión total (ancho completo) -->
        <div class="kpi-card span2" style="animation-delay:.17s">
          <div class="kpi-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <line x1="12" y1="1" x2="12" y2="23"/>
              <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
          </div>
          <div class="kpi-label" style="margin-bottom:8px;">Comisión total recaudada</div>
          <div class="kpi-comm-row">
            <div class="kpi-comm-item">
              <div class="kpi-comm-val eur"><?= number_format($anaCommEUR, 2, '.', ',') ?></div>
              <div class="kpi-comm-cur">Euros</div>
            </div>
            <div class="kpi-comm-item">
              <div class="kpi-comm-val usd"><?= number_format($anaCommUSD, 2, '.', ',') ?></div>
              <div class="kpi-comm-cur">Dólares</div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Envíos por estado -->
    <div class="ana-block">
      <div class="ana-label">Envíos por estado</div>
      <div class="stat-dist">
        <?php
          $statLabels = ['pending'=>'Pendiente','paid'=>'Pagado','completed'=>'Completado','cancelled'=>'Cancelado'];
          $statColors = [
            'pending'   => 'var(--status-pending)',
            'paid'      => 'var(--status-paid)',
            'completed' => 'var(--status-ok)',
            'cancelled' => 'var(--status-failed)',
          ];
          $statBarMax = max(array_values($anaStat) ?: [1]) ?: 1;
          foreach ($anaStat as $st => $cnt):
            $w = $statBarMax > 0 ? round(($cnt / $statBarMax) * 100) : 0;
        ?>
        <div class="stat-row">
          <div class="stat-name" style="color:<?= $statColors[$st] ?>"><?= $statLabels[$st] ?></div>
          <div class="stat-bar-wrap">
            <div class="stat-bar b-<?= $st ?>" style="width:<?= $w ?>%"></div>
          </div>
          <div class="stat-count" style="color:<?= $statColors[$st] ?>"><?= $cnt ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Últimos 7 días -->
    <div class="ana-block">
      <div class="ana-label">Últimos 7 días</div>
      <div class="chart-card">
        <?php $dayEs = ['Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mié','Thu'=>'Jue','Fri'=>'Vie','Sat'=>'Sáb','Sun'=>'Dom']; ?>
        <div class="bar-chart-area">
          <?php foreach ($ana7 as $date => $count):
            $hpx = $count > 0 ? max(6, (int)round(($count / $ana7Max) * 60)) : 4;
          ?>
          <div class="bar-item<?= $count === 0 ? ' b-zero' : '' ?>" style="height:<?= $hpx ?>px"></div>
          <?php endforeach; ?>
        </div>
        <div class="bar-labels-row">
          <?php foreach ($ana7 as $date => $count):
            $dayLbl = $dayEs[date('D', strtotime($date))] ?? substr($date, 8);
          ?>
          <div class="bar-label-col">
            <div class="bar-day-lbl"><?= htmlspecialchars($dayLbl) ?></div>
            <?php if ($count > 0): ?>
            <div class="bar-cnt-lbl"><?= $count ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Top bancos destinatarios -->
    <div class="ana-block">
      <div class="ana-label">Top bancos destinatarios</div>
      <div class="banks-card">
        <?php if (empty($anaTopBanks)): ?>
          <div class="banks-empty">Sin datos de bancos aún</div>
        <?php else: ?>
          <?php foreach ($anaTopBanks as $idx => $bk):
            $bw = round(((int)$bk['cnt'] / $anaTopMax) * 100);
          ?>
          <div class="bank-row">
            <div class="bank-rank">#<?= $idx + 1 ?></div>
            <div class="bank-name"><?= htmlspecialchars(tc($bk['bank'])) ?></div>
            <div class="bank-bar-wrap">
              <div class="bank-bar-fill" style="width:<?= $bw ?>%"></div>
            </div>
            <div class="bank-count"><?= (int)$bk['cnt'] ?></div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>

</main>


<!-- ═══════════════════════════════════════
     DETAIL SHEET
     ═══════════════════════════════════════ -->
<div class="sheet-backdrop" id="sheetBackdrop"></div>
<div class="detail-sheet" id="detailSheet">

  <!-- Header -->
  <div class="sheet-header">
    <div class="sheet-drag"></div>
    <div class="sheet-header-row">
      <div>
        <div class="sheet-title" id="sheetTitle">Envío #</div>
        <span class="sheet-status-badge" id="sheetStatusBadge"></span>
      </div>
      <button class="sheet-close" onclick="closeSheet()">×</button>
    </div>
  </div>

  <!-- Body (scrollable) -->
  <div class="sheet-body">

    <!-- Monto -->
    <div class="sheet-amt-block">
      <div class="sheet-amt-main" id="sheetAmt"></div>
      <div class="sheet-amt-sub"  id="sheetRecv"></div>
    </div>

    <!-- Remitente -->
    <div class="sheet-section">
      <div class="sheet-section-title">Remitente</div>
      <div class="sheet-rows">
        <div class="sheet-row">
          <span class="sheet-row-label">Nombre</span>
          <span class="sheet-row-value" id="sheetUName"></span>
        </div>
        <div class="sheet-row">
          <span class="sheet-row-label">Email</span>
          <span class="sheet-row-value" id="sheetUEmail"></span>
        </div>
      </div>
    </div>

    <!-- Destinatario -->
    <div class="sheet-section">
      <div class="sheet-section-title">Destinatario</div>
      <div class="sheet-rows">
        <div class="sheet-row">
          <span class="sheet-row-label">Nombre</span>
          <span class="sheet-row-value" id="sheetBName"></span>
        </div>
        <div class="sheet-row">
          <span class="sheet-row-label">Relación</span>
          <span class="sheet-row-value" id="sheetBRel"></span>
        </div>
        <div class="sheet-row">
          <span class="sheet-row-label">Cédula / ID</span>
          <span class="sheet-row-value" id="sheetBId"></span>
        </div>
        <div class="sheet-row">
          <span class="sheet-row-label">Banco</span>
          <span class="sheet-row-value" id="sheetBBank"></span>
        </div>
        <div class="sheet-row">
          <span class="sheet-row-label">N° cuenta</span>
          <span class="sheet-row-value" id="sheetBAcct"></span>
        </div>
        <div class="sheet-row">
          <span class="sheet-row-label">Tipo cuenta</span>
          <span class="sheet-row-value" id="sheetBAcctType"></span>
        </div>
        <div class="sheet-row">
          <span class="sheet-row-label">Email</span>
          <span class="sheet-row-value" id="sheetBEmail"></span>
        </div>
        <div class="sheet-row">
          <span class="sheet-row-label">País</span>
          <span class="sheet-row-value" id="sheetBCountry"></span>
        </div>
        <div class="sheet-row">
          <span class="sheet-row-label">Motivo envío</span>
          <span class="sheet-row-value" id="sheetBReason"></span>
        </div>
      </div>
    </div>

    <!-- Financiero -->
    <div class="sheet-section">
      <div class="sheet-section-title">Detalle financiero</div>
      <div class="sheet-rows">
        <div class="sheet-row">
          <span class="sheet-row-label">Total a pagar</span>
          <span class="sheet-row-value" id="sheetTotal"></span>
        </div>
        <div class="sheet-row">
          <span class="sheet-row-label">Comisión</span>
          <span class="sheet-row-value" id="sheetComm"></span>
        </div>
        <div class="sheet-row">
          <span class="sheet-row-label">Tasa aplicada</span>
          <span class="sheet-row-value" id="sheetRate"></span>
        </div>
        <div class="sheet-row">
          <span class="sheet-row-label">Fecha</span>
          <span class="sheet-row-value" id="sheetDate"></span>
        </div>
      </div>
    </div>

    <!-- Motivo cancelación (oculto por defecto) -->
    <div class="sheet-section" id="sheetCancelSection" style="display:none;">
      <div class="sheet-section-title">Motivo de cancelación</div>
      <div class="sheet-rows">
        <div class="sheet-row sheet-row-cancelled">
          <span class="sheet-row-label">Razón</span>
          <span class="sheet-row-value" id="sheetFailedReason"></span>
        </div>
      </div>
    </div>

  </div><!-- /sheet-body -->

  <!-- Footer: cambio de estado -->
  <div class="sheet-footer">
    <div class="sheet-footer-label">Cambiar estado</div>
    <div class="status-pick-row" id="statusBtnGroup"></div>
    <div class="reason-group" id="reasonGroup">
      <div class="reason-label">Motivo de cancelación</div>
      <textarea class="reason-input" id="reasonInput" placeholder="Describe el motivo…" rows="3"></textarea>
    </div>
    <button class="sheet-confirm-btn" id="sheetConfirmBtn" onclick="openStatusModal()">
      Confirmar cambio
    </button>
  </div>

</div><!-- /detail-sheet -->


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

<!-- MODAL: confirmación estado TX -->
<div class="modal-overlay" id="statusModal">
  <div class="modal-box">
    <div class="modal-title">Confirmar cambio de estado</div>
    <div class="modal-body">
      ¿Cambiar el envío <strong>#<span id="sModalId"></span></strong> a
      <strong id="sModalLabel"></strong>?
    </div>
    <div class="modal-actions">
      <button class="modal-cancel" onclick="closeModal('statusModal')">Cancelar</button>
      <button class="modal-confirm" onclick="submitStatusChange()">Confirmar</button>
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
const TOAST_DATA  = <?= json_encode($toast) ?>;
const ACTIVE_TAB  = <?= json_encode($activeTab) ?>;
const ACTIVE_SUB  = <?= json_encode($activeSub) ?>;
const TX_DATA     = <?= json_encode($txMap, JSON_UNESCAPED_UNICODE) ?>;

/* ─────────────────────────────
   MAIN TABS
───────────────────────────── */
function switchTab(name) {
  document.querySelectorAll('.tab-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  const sec = document.getElementById('section-' + name);
  const btn = document.querySelector('[data-tab="' + name + '"]');
  if (sec) sec.classList.add('active');
  if (btn) btn.classList.add('active');
  const url = new URL(window.location);
  url.searchParams.set('tab', name);
  history.replaceState({}, '', url);
}
document.querySelectorAll('.tab-btn').forEach(b =>
  b.addEventListener('click', () => switchTab(b.dataset.tab))
);

/* ─────────────────────────────
   SUB-TABS
───────────────────────────── */
function switchSub(name) {
  document.querySelectorAll('.sub-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.sub-btn').forEach(b => b.classList.remove('active'));
  const sec = document.getElementById('sub-' + name);
  const btn = document.querySelector('[data-sub="' + name + '"]');
  if (sec) sec.classList.add('active');
  if (btn) btn.classList.add('active');
  const url = new URL(window.location);
  url.searchParams.set('sub', name);
  history.replaceState({}, '', url);
}
document.querySelectorAll('.sub-btn').forEach(b =>
  b.addEventListener('click', () => switchSub(b.dataset.sub))
);

/* ─────────────────────────────
   RATE MODAL
───────────────────────────── */
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
  if (pendingForm) { pendingForm.onsubmit = null; pendingForm.submit(); }
  closeModal('rateModal');
});

function closeModal(id) {
  document.getElementById(id).classList.remove('open');
  if (id === 'rateModal') pendingForm = null;
}

document.getElementById('rateModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal('rateModal');
});
document.getElementById('statusModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal('statusModal');
});

/* ─────────────────────────────
   DETAIL SHEET
───────────────────────────── */
const STATUS_LABELS  = {pending:'Pendiente', paid:'Pagado', completed:'Completado', cancelled:'Cancelado'};
const STATUS_CLS     = {pending:'s-pending', paid:'s-paid', completed:'s-completed', cancelled:'s-cancelled'};
const ACTYPE_LABELS  = {checking:'Corriente', savings:'Ahorro'};
const MONTHS         = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

let currentTxId    = null;
let selectedStatus = null;

function cap(s) {
  if (!s) return '';
  return s.toLowerCase().replace(/(?:^|\s|-)(\S)/g, c => c.toUpperCase());
}
function fmtNum(v, d) {
  return parseFloat(v).toLocaleString('es-ES', {minimumFractionDigits: d ?? 2, maximumFractionDigits: d ?? 2});
}
function fmtDate(str) {
  const d = new Date(str);
  return d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() + ' · ' +
         String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
}

function openSheet(txId) {
  const tx = TX_DATA[txId];
  if (!tx) return;
  currentTxId    = txId;
  selectedStatus = null;
  populateSheet(tx);
  document.getElementById('detailSheet').classList.add('open');
  document.getElementById('sheetBackdrop').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeSheet() {
  document.getElementById('detailSheet').classList.remove('open');
  document.getElementById('sheetBackdrop').classList.remove('open');
  document.body.style.overflow = '';
  currentTxId    = null;
  selectedStatus = null;
}

document.getElementById('sheetBackdrop').addEventListener('click', closeSheet);

function set(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val || '—';
}

function populateSheet(tx) {
  const cur = (tx.currency || 'EUR').toUpperCase();

  /* header */
  set('sheetTitle', 'Envío #' + tx.id);
  const badge = document.getElementById('sheetStatusBadge');
  badge.textContent = STATUS_LABELS[tx.status] || tx.status;
  badge.className   = 'sheet-status-badge ' + (STATUS_CLS[tx.status] || '');

  /* montos */
  set('sheetAmt',  cur + ' ' + fmtNum(tx.amount));
  set('sheetRecv', 'Destinatario recibe: VES ' + fmtNum(tx.amount_received));

  /* remitente */
  set('sheetUName',  cap(tx.u_first) + ' ' + cap(tx.u_last));
  set('sheetUEmail', tx.u_email);

  /* destinatario */
  set('sheetBName',     cap(tx.b_first) + ' ' + cap(tx.b_last));
  set('sheetBRel',      cap(tx.b_relation));
  set('sheetBId',       (tx.b_idtype ? tx.b_idtype + ' ' : '') + tx.b_idnum);
  set('sheetBBank',     cap(tx.b_bank));
  set('sheetBAcct',     tx.b_account);
  set('sheetBAcctType', ACTYPE_LABELS[tx.b_actype] || cap(tx.b_actype));
  set('sheetBEmail',    tx.b_email);
  set('sheetBCountry',  tx.b_country);
  set('sheetBReason',   cap(tx.b_reason));

  /* financiero */
  set('sheetTotal', cur + ' ' + fmtNum(tx.total_to_pay));
  set('sheetComm',  cur + ' ' + fmtNum(tx.commission));
  set('sheetRate',  fmtNum(tx.exchange_rate, 4) + ' VES');
  set('sheetDate',  fmtDate(tx.created_at));

  /* motivo cancelación */
  const cancelSec = document.getElementById('sheetCancelSection');
  if (tx.status === 'cancelled' && tx.failed_reason) {
    set('sheetFailedReason', tx.failed_reason);
    cancelSec.style.display = '';
  } else {
    cancelSec.style.display = 'none';
  }

  /* botones de estado (todos excepto el actual) */
  const group = document.getElementById('statusBtnGroup');
  group.innerHTML = '';
  ['pending','paid','completed','cancelled'].forEach(s => {
    if (s === tx.status) return;
    const btn = document.createElement('button');
    btn.type      = 'button';
    btn.className = 'spick';
    btn.dataset.status = s;
    btn.textContent    = STATUS_LABELS[s];
    btn.addEventListener('click', () => selectStatus(s));
    group.appendChild(btn);
  });

  /* reset footer */
  document.getElementById('reasonGroup').style.display = 'none';
  document.getElementById('reasonInput').value         = '';
  document.getElementById('sheetConfirmBtn').classList.remove('ready');
}

function selectStatus(status) {
  selectedStatus = status;
  document.querySelectorAll('.spick').forEach(b => {
    b.className = 'spick';
    if (b.dataset.status === status) b.classList.add('sel-' + status);
  });
  document.getElementById('reasonGroup').style.display =
    status === 'cancelled' ? 'block' : 'none';
  document.getElementById('sheetConfirmBtn').classList.add('ready');
}

function openStatusModal() {
  if (!selectedStatus || !currentTxId) return;
  if (selectedStatus === 'cancelled' &&
      !document.getElementById('reasonInput').value.trim()) {
    showToast('Escribe el motivo de cancelación', 'error');
    return;
  }
  document.getElementById('sModalLabel').textContent = STATUS_LABELS[selectedStatus];
  document.getElementById('sModalId').textContent    = currentTxId;
  document.getElementById('statusModal').classList.add('open');
}

function submitStatusChange() {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '/app/pay/admin.php';
  const fields = {
    action:        'update_tx_status',
    tx_id:         currentTxId,
    new_status:    selectedStatus,
    failed_reason: document.getElementById('reasonInput').value.trim()
  };
  for (const [k, v] of Object.entries(fields)) {
    const inp = document.createElement('input');
    inp.type  = 'hidden'; inp.name = k; inp.value = v;
    form.appendChild(inp);
  }
  document.body.appendChild(form);
  form.submit();
}

/* ─────────────────────────────
   TOAST
───────────────────────────── */
function showToast(msg, type) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className   = 'toast toast-' + type + ' show';
  setTimeout(() => { t.className = 'toast'; }, 3500);
}

/* ─────────────────────────────
   INIT
───────────────────────────── */
document.addEventListener('DOMContentLoaded', function() {
  switchTab(ACTIVE_TAB || 'tasas');
  switchSub(ACTIVE_SUB || 'pendientes');
  if (TOAST_DATA) setTimeout(() => showToast(TOAST_DATA.msg, TOAST_DATA.type), 300);
});

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/app/pay/sw.js', { scope: '/app/pay/' });
}
</script>

</body>
</html>
