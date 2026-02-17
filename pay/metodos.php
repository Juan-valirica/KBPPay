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
$stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$firstName = normalizeName($user['first_name'] ?? '');
$lastName  = normalizeName($user['last_name'] ?? '');
$avatarInitials = mb_strtoupper(
    mb_substr($firstName, 0, 1, 'UTF-8') . mb_substr($lastName, 0, 1, 'UTF-8'),
    'UTF-8'
);

// ============================================
// ACCIONES POST (agregar, eliminar, principal)
// ============================================
$actionMsg = '';
$actionType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // AGREGAR TARJETA
    if ($action === 'add') {
        $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
        $cardHolder = trim($_POST['card_holder'] ?? '');
        $expiryRaw  = trim($_POST['expiry'] ?? '');
        $cvv        = preg_replace('/\D/', '', $_POST['cvv'] ?? '');

        // Validaciones básicas
        $errors = [];
        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
            $errors[] = 'Número de tarjeta inválido';
        }
        if ($cardHolder === '') {
            $errors[] = 'Nombre del titular requerido';
        }
        if (!preg_match('/^\d{2}\/\d{2}$/', $expiryRaw)) {
            $errors[] = 'Fecha de expiración inválida';
        }
        if (strlen($cvv) < 3 || strlen($cvv) > 4) {
            $errors[] = 'CVV inválido';
        }

        if (empty($errors)) {
            $lastFour = substr($cardNumber, -4);
            $parts = explode('/', $expiryRaw);
            $expiryMonth = (int)$parts[0];
            $expiryYear  = (int)('20' . $parts[1]);

            // Detectar marca
            $brand = 'unknown';
            $first = substr($cardNumber, 0, 1);
            $firstTwo = substr($cardNumber, 0, 2);
            if ($first === '4') {
                $brand = 'visa';
            } elseif (in_array($firstTwo, ['51','52','53','54','55']) || ((int)$firstTwo >= 22 && (int)$firstTwo <= 27)) {
                $brand = 'mastercard';
            } elseif (in_array($firstTwo, ['34','37'])) {
                $brand = 'amex';
            }

            // Verificar si ya tiene tarjetas
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM payment_methods WHERE user_id = ?");
            $stmtCount->execute([$_SESSION['user_id']]);
            $cardCount = (int)$stmtCount->fetchColumn();
            $isPrimary = $cardCount === 0 ? 1 : 0;

            $stmtAdd = $pdo->prepare("
                INSERT INTO payment_methods (user_id, card_brand, last_four, card_holder, expiry_month, expiry_year, is_primary)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtAdd->execute([
                $_SESSION['user_id'],
                $brand,
                $lastFour,
                $cardHolder,
                $expiryMonth,
                $expiryYear,
                $isPrimary
            ]);

            $actionMsg = 'Tarjeta agregada correctamente';
            $actionType = 'success';
        } else {
            $actionMsg = implode('. ', $errors);
            $actionType = 'error';
        }
    }

    // ELIMINAR TARJETA
    if ($action === 'delete' && isset($_POST['card_id'])) {
        $cardId = (int)$_POST['card_id'];
        $stmtDel = $pdo->prepare("DELETE FROM payment_methods WHERE id = ? AND user_id = ?");
        $stmtDel->execute([$cardId, $_SESSION['user_id']]);

        if ($stmtDel->rowCount() > 0) {
            // Si era la principal, asignar otra
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM payment_methods WHERE user_id = ? AND is_primary = 1");
            $stmtCheck->execute([$_SESSION['user_id']]);
            if ((int)$stmtCheck->fetchColumn() === 0) {
                $stmtFirst = $pdo->prepare("UPDATE payment_methods SET is_primary = 1 WHERE user_id = ? ORDER BY created_at ASC LIMIT 1");
                $stmtFirst->execute([$_SESSION['user_id']]);
            }
            $actionMsg = 'Tarjeta eliminada';
            $actionType = 'success';
        }
    }

    // ESTABLECER COMO PRINCIPAL
    if ($action === 'set_primary' && isset($_POST['card_id'])) {
        $cardId = (int)$_POST['card_id'];
        $pdo->prepare("UPDATE payment_methods SET is_primary = 0 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
        $pdo->prepare("UPDATE payment_methods SET is_primary = 1 WHERE id = ? AND user_id = ?")->execute([$cardId, $_SESSION['user_id']]);
        $actionMsg = 'Método principal actualizado';
        $actionType = 'success';
    }
}

// Obtener métodos de pago del usuario
$stmt = $pdo->prepare("
    SELECT id, card_brand, last_four, card_holder, expiry_month, expiry_year, is_primary, created_at
    FROM payment_methods
    WHERE user_id = ?
    ORDER BY is_primary DESC, created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$methods = $stmt->fetchAll();

$primaryCard = null;
$otherCards = [];
foreach ($methods as $m) {
    if ((int)$m['is_primary'] === 1 && $primaryCard === null) {
        $primaryCard = $m;
    } else {
        $otherCards[] = $m;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Métodos de pago | KBPPAY</title>
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
   TOAST NOTIFICATION
   ============================================ */

.toast {
  position: fixed;
  top: calc(var(--header-h) + var(--safe-top) + 12px);
  left: 20px;
  right: 20px;
  max-width: 480px;
  margin: 0 auto;
  z-index: 300;
  padding: 14px 16px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 10px;
  animation: slideDown 0.3s ease both;
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
}

.toast.success {
  background: rgba(52,211,153,0.15);
  border: 1px solid rgba(52,211,153,0.25);
  color: #34D399;
}

.toast.error {
  background: rgba(248,113,113,0.15);
  border: 1px solid rgba(248,113,113,0.25);
  color: #F87171;
}

.toast svg {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

@keyframes slideDown {
  from { opacity: 0; transform: translateY(-12px); }
  to   { opacity: 1; transform: translateY(0); }
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
  margin-bottom: 12px;
  padding-left: 2px;
}

.section-label svg { width: 14px; height: 14px; }

.section-label.primary-label { color: #60A5FA; }

.section-group { margin-bottom: 24px; }


/* ============================================
   CREDIT CARD VISUAL
   ============================================ */

.card-visual {
  position: relative;
  width: 100%;
  aspect-ratio: 1.586;
  max-width: 340px;
  margin: 0 auto 20px;
  border-radius: 16px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  overflow: hidden;
  animation: fadeInUp 0.5s ease 0.08s both;
}

.card-visual.card-primary {
  background: linear-gradient(135deg, rgba(50,89,253,0.35) 0%, rgba(82,174,50,0.25) 100%);
  border: 1px solid rgba(255,255,255,0.12);
  box-shadow: 0 8px 32px rgba(50,89,253,0.15), 0 4px 16px rgba(0,0,0,0.3);
}

.card-visual::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -30%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle at 70% 30%, rgba(255,255,255,0.06), transparent 50%);
  pointer-events: none;
}

.card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: relative;
  z-index: 1;
}

.card-chip {
  width: 36px;
  height: 28px;
  border-radius: 5px;
  background: linear-gradient(135deg, #d4af37, #c5a028);
  position: relative;
  overflow: hidden;
}

.card-chip::before {
  content: '';
  position: absolute;
  inset: 3px;
  border: 1px solid rgba(0,0,0,0.15);
  border-radius: 3px;
}

.card-chip::after {
  content: '';
  position: absolute;
  left: 50%;
  top: 3px;
  bottom: 3px;
  width: 1px;
  background: rgba(0,0,0,0.12);
}

.card-brand-logo {
  height: 28px;
  width: auto;
  opacity: 0.9;
}

.card-number {
  font-size: 18px;
  font-weight: 500;
  letter-spacing: 2.5px;
  color: rgba(255,255,255,0.90);
  position: relative;
  z-index: 1;
  font-variant-numeric: tabular-nums;
}

.card-bottom {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  position: relative;
  z-index: 1;
}

.card-holder-label,
.card-expiry-label {
  font-size: 9px;
  font-weight: 500;
  color: rgba(255,255,255,0.40);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 3px;
}

.card-holder-value {
  font-size: 13px;
  font-weight: 600;
  color: rgba(255,255,255,0.85);
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.card-expiry-value {
  font-size: 13px;
  font-weight: 600;
  color: rgba(255,255,255,0.85);
  letter-spacing: 0.04em;
  text-align: right;
}

.card-primary-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 500;
  color: #60A5FA;
  background: rgba(96,165,250,0.12);
  padding: 4px 10px;
  border-radius: 20px;
  margin-bottom: 12px;
  animation: fadeInUp 0.4s ease 0.15s both;
}

.card-primary-badge svg { width: 12px; height: 12px; }


/* ============================================
   CARD LIST (other cards)
   ============================================ */

.card-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.card-item {
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

.card-item:hover {
  background: var(--glass-hover);
  border-color: rgba(255,255,255,0.10);
}

.card-item-icon {
  width: 44px;
  height: 44px;
  border-radius: var(--radius-sm);
  background: rgba(255,255,255,0.04);
  border: 1px solid var(--glass-border);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.card-item-icon svg { width: 24px; height: 24px; }

.card-item-info {
  flex: 1;
  min-width: 0;
}

.card-item-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--text);
  line-height: 1.3;
}

.card-item-meta {
  font-size: 12px;
  color: var(--text-muted);
  margin-top: 2px;
}

.card-item-actions {
  display: flex;
  gap: 6px;
  flex-shrink: 0;
}

.card-action-btn {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--glass);
  border: 1px solid var(--glass-border);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--text-muted);
  transition: all 0.2s ease;
  -webkit-tap-highlight-color: transparent;
}

.card-action-btn:hover {
  background: var(--glass-hover);
  color: var(--text-secondary);
}

.card-action-btn.star:hover { color: #FBBF24; }
.card-action-btn.delete:hover { color: #F87171; }

.card-action-btn svg { width: 16px; height: 16px; }


/* ============================================
   ADD CARD BUTTON
   ============================================ */

.add-card-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  padding: 16px;
  background: var(--glass);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px dashed rgba(255,255,255,0.12);
  border-radius: var(--radius);
  color: var(--text-secondary);
  font-family: inherit;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  -webkit-tap-highlight-color: transparent;
  animation: fadeInUp 0.5s ease 0.2s both;
}

.add-card-btn:hover {
  background: var(--glass-hover);
  border-color: rgba(255,255,255,0.18);
  color: var(--text);
}

.add-card-btn svg { width: 20px; height: 20px; }


/* ============================================
   MODAL
   ============================================ */

.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 500;
  background: rgba(0,0,0,0.6);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  display: none;
  align-items: flex-end;
  justify-content: center;
  animation: fadeIn 0.2s ease;
}

.modal-overlay.active { display: flex; }

.modal-sheet {
  width: 100%;
  max-width: 520px;
  max-height: 92vh;
  background: #0F1422;
  border-radius: 24px 24px 0 0;
  border: 1px solid rgba(255,255,255,0.06);
  border-bottom: none;
  padding: 0 0 var(--safe-bottom);
  overflow-y: auto;
  animation: sheetUp 0.35s cubic-bezier(0.32, 0.72, 0, 1) both;
}

.modal-sheet::-webkit-scrollbar { width: 0; }

@keyframes sheetUp {
  from { transform: translateY(100%); }
  to   { transform: translateY(0); }
}

@keyframes fadeIn {
  from { opacity: 0; }
  to   { opacity: 1; }
}

.modal-handle {
  width: 36px;
  height: 4px;
  border-radius: 2px;
  background: rgba(255,255,255,0.15);
  margin: 10px auto 0;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px 0;
}

.modal-title {
  font-size: 20px;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.modal-close {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--glass);
  border: 1px solid var(--glass-border);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--text-muted);
  transition: all 0.2s ease;
}

.modal-close:hover {
  background: var(--glass-hover);
  color: var(--text);
}

.modal-close svg { width: 18px; height: 18px; }

.modal-body {
  padding: 24px;
}


/* ============================================
   CARD PREVIEW (inside modal)
   ============================================ */

.card-preview {
  position: relative;
  width: 100%;
  aspect-ratio: 1.586;
  max-width: 320px;
  margin: 0 auto 28px;
  border-radius: 14px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  overflow: hidden;
  background: linear-gradient(135deg, rgba(50,89,253,0.30) 0%, rgba(82,174,50,0.20) 100%);
  border: 1px solid rgba(255,255,255,0.10);
  box-shadow: 0 8px 32px rgba(0,0,0,0.3);
  transition: background 0.3s ease;
}

.card-preview::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -30%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle at 70% 30%, rgba(255,255,255,0.05), transparent 50%);
  pointer-events: none;
}

.card-preview .card-number {
  font-size: 16px;
  letter-spacing: 2px;
}

.card-preview .card-holder-value,
.card-preview .card-expiry-value {
  font-size: 12px;
}


/* ============================================
   FORM FIELDS
   ============================================ */

.form-group {
  margin-bottom: 18px;
}

.form-label {
  font-size: 13px;
  font-weight: 500;
  color: var(--text-secondary);
  margin-bottom: 6px;
  display: block;
}

.form-input {
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
}

.form-input::placeholder { color: var(--text-muted); }

.form-input:focus {
  border-color: rgba(82,174,50,0.4);
  background: rgba(255,255,255,0.07);
}

.form-input.has-icon {
  padding-left: 48px;
}

.form-input-wrap {
  position: relative;
}

.form-input-icon {
  position: absolute;
  left: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  display: flex;
  pointer-events: none;
  transition: color 0.2s ease;
}

.form-input-icon svg { width: 18px; height: 18px; }

.form-input:focus ~ .form-input-icon,
.form-input-wrap:focus-within .form-input-icon {
  color: var(--text-secondary);
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.form-submit {
  width: 100%;
  padding: 16px;
  border: none;
  border-radius: var(--radius);
  background: var(--gradient);
  color: #fff;
  font-family: inherit;
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.3px;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.2s ease;
  box-shadow:
    0 4px 16px rgba(82,174,50,0.20),
    0 8px 32px rgba(50,89,253,0.15);
  margin-top: 8px;
}

.form-submit:hover {
  transform: translateY(-1px);
  box-shadow:
    0 6px 20px rgba(82,174,50,0.30),
    0 10px 36px rgba(50,89,253,0.22);
}

.form-submit:active {
  transform: scale(0.98);
}

.form-hint {
  font-size: 11px;
  color: var(--text-muted);
  margin-top: 6px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.form-hint svg { width: 12px; height: 12px; flex-shrink: 0; }


/* ============================================
   EMPTY STATE
   ============================================ */

.empty-state {
  text-align: center;
  padding: 56px 20px;
  animation: fadeInUp 0.5s ease 0.15s both;
}

.empty-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: var(--glass);
  border: 1px solid var(--glass-border);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
}

.empty-icon svg { width: 36px; height: 36px; color: var(--text-muted); }

.empty-title {
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 6px;
  color: var(--text-secondary);
}

.empty-sub {
  font-size: 13px;
  color: var(--text-muted);
  margin-bottom: 24px;
  line-height: 1.5;
}

.empty-add-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 14px 28px;
  border: none;
  border-radius: 25px;
  background: var(--gradient);
  color: #fff;
  font-family: inherit;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  box-shadow:
    0 4px 16px rgba(82,174,50,0.20),
    0 8px 32px rgba(50,89,253,0.15);
  transition: transform 0.15s ease, box-shadow 0.2s ease;
}

.empty-add-btn:hover {
  transform: translateY(-1px);
}

.empty-add-btn svg { width: 18px; height: 18px; }


/* ============================================
   CONFIRM DIALOG
   ============================================ */

.confirm-overlay {
  position: fixed;
  inset: 0;
  z-index: 600;
  background: rgba(0,0,0,0.65);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  display: none;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.confirm-overlay.active { display: flex; }

.confirm-box {
  width: 100%;
  max-width: 340px;
  background: #161b2e;
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: var(--radius);
  padding: 28px 24px 20px;
  text-align: center;
  animation: scaleUp 0.25s cubic-bezier(0.32, 0.72, 0, 1) both;
}

@keyframes scaleUp {
  from { opacity: 0; transform: scale(0.92); }
  to   { opacity: 1; transform: scale(1); }
}

.confirm-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: rgba(248,113,113,0.12);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
  color: #F87171;
}

.confirm-icon svg { width: 24px; height: 24px; }

.confirm-title {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 6px;
}

.confirm-text {
  font-size: 13px;
  color: var(--text-muted);
  margin-bottom: 20px;
  line-height: 1.5;
}

.confirm-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.confirm-cancel,
.confirm-delete {
  padding: 12px;
  border-radius: var(--radius-sm);
  font-family: inherit;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  border: none;
}

.confirm-cancel {
  background: var(--glass);
  border: 1px solid var(--glass-border);
  color: var(--text-secondary);
}

.confirm-cancel:hover {
  background: var(--glass-hover);
}

.confirm-delete {
  background: rgba(248,113,113,0.15);
  color: #F87171;
}

.confirm-delete:hover {
  background: rgba(248,113,113,0.25);
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
     TOAST (mensajes de acción)
     ============================================ -->
<?php if ($actionMsg !== ''): ?>
<div class="toast <?= $actionType ?>" id="toast">
  <?php if ($actionType === 'success'): ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
      <path d="M22 4L12 14.01l-3-3"/>
    </svg>
  <?php else: ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/>
      <line x1="15" y1="9" x2="9" y2="15"/>
      <line x1="9" y1="9" x2="15" y2="15"/>
    </svg>
  <?php endif; ?>
  <?= htmlspecialchars($actionMsg) ?>
</div>
<?php endif; ?>


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
    <h1 class="page-title">Métodos de pago</h1>
    <p class="page-sub"><?= count($methods) ?> tarjeta<?= count($methods) !== 1 ? 's' : '' ?> registrada<?= count($methods) !== 1 ? 's' : '' ?></p>
  </section>

  <?php if (!empty($methods)): ?>

    <!-- Primary Card Visual -->
    <?php if ($primaryCard): ?>
      <div class="card-primary-badge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
          <path d="M22 4L12 14.01l-3-3"/>
        </svg>
        Método principal
      </div>

      <div class="card-visual card-primary">
        <div class="card-top">
          <div class="card-chip"></div>
          <!-- Brand logo -->
          <?php if ($primaryCard['card_brand'] === 'visa'): ?>
            <svg class="card-brand-logo" viewBox="0 0 80 26" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M33.6 1L27.2 25H21.6L28 1H33.6ZM54.8 16.2L57.6 8.4L59.2 16.2H54.8ZM60.8 25H66L61.6 1H56.8C55.6 1 54.6 1.8 54.2 2.8L45.6 25H51.2L52.2 22H59.2L60.8 25ZM48.4 17.4C48.4 10.6 38.8 10.2 38.8 7.2C38.8 6.2 39.8 5.2 41.8 4.8C42.8 4.8 45.4 4.6 48.4 6L49.6 1.6C48 1 46 0.4 43.6 0.4C38.4 0.4 34.6 3.4 34.6 7.6C34.6 10.8 37.4 12.4 39.4 13.6C41.6 14.8 42.4 15.6 42.4 16.6C42.4 18.2 40.4 19 38.6 19C35.8 19 34.2 18.4 32 17.4L30.8 22C33 22.8 36 23.6 38.4 23.6C44 23.6 48.4 20.8 48.4 17.4ZM24 1L15.6 25H10L5.8 5.4C5.6 4.2 5.4 3.8 4.4 3.2C2.8 2.4 0 1.4 0 1.4L0.2 1H9C10.4 1 11.6 1.8 11.8 3.4L14 16L19.4 1H24Z" fill="rgba(255,255,255,0.85)"/>
            </svg>
          <?php elseif ($primaryCard['card_brand'] === 'mastercard'): ?>
            <svg class="card-brand-logo" viewBox="0 0 48 30" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="18" cy="15" r="12" fill="rgba(235,0,27,0.8)"/>
              <circle cx="30" cy="15" r="12" fill="rgba(255,159,0,0.8)"/>
              <path d="M24 5.4A11.96 11.96 0 0 0 18 15a11.96 11.96 0 0 0 6 9.6A11.96 11.96 0 0 0 30 15a11.96 11.96 0 0 0-6-9.6Z" fill="rgba(255,95,0,0.8)"/>
            </svg>
          <?php elseif ($primaryCard['card_brand'] === 'amex'): ?>
            <svg class="card-brand-logo" viewBox="0 0 48 30" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect width="48" height="30" rx="4" fill="rgba(0,111,207,0.5)"/>
              <text x="24" y="19" text-anchor="middle" font-family="Inter, sans-serif" font-weight="700" font-size="10" fill="rgba(255,255,255,0.85)">AMEX</text>
            </svg>
          <?php else: ?>
            <svg class="card-brand-logo" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="height:22px;">
              <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
            </svg>
          <?php endif; ?>
        </div>

        <div class="card-number">
          &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; <?= htmlspecialchars($primaryCard['last_four']) ?>
        </div>

        <div class="card-bottom">
          <div>
            <div class="card-holder-label">Titular</div>
            <div class="card-holder-value"><?= htmlspecialchars(mb_strtoupper($primaryCard['card_holder'], 'UTF-8')) ?></div>
          </div>
          <div>
            <div class="card-expiry-label">Vence</div>
            <div class="card-expiry-value"><?= str_pad($primaryCard['expiry_month'], 2, '0', STR_PAD_LEFT) ?>/<?= substr($primaryCard['expiry_year'], -2) ?></div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Other Cards -->
    <?php if (!empty($otherCards)): ?>
      <div class="section-group">
        <div class="section-label">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
          </svg>
          Otras tarjetas
        </div>
        <div class="card-list">
          <?php $i = 0; foreach ($otherCards as $card):
            $brandLabel = ['visa' => 'Visa', 'mastercard' => 'Mastercard', 'amex' => 'Amex', 'unknown' => 'Tarjeta'];
            $bLabel = $brandLabel[$card['card_brand']] ?? 'Tarjeta';
          ?>
            <div class="card-item" style="animation-delay: <?= 0.10 + ($i * 0.05) ?>s;">
              <div class="card-item-icon">
                <?php if ($card['card_brand'] === 'visa'): ?>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                  </svg>
                <?php elseif ($card['card_brand'] === 'mastercard'): ?>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                  </svg>
                <?php else: ?>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                  </svg>
                <?php endif; ?>
              </div>
              <div class="card-item-info">
                <div class="card-item-name"><?= $bLabel ?> •••• <?= htmlspecialchars($card['last_four']) ?></div>
                <div class="card-item-meta"><?= htmlspecialchars($card['card_holder']) ?> · Vence <?= str_pad($card['expiry_month'], 2, '0', STR_PAD_LEFT) ?>/<?= substr($card['expiry_year'], -2) ?></div>
              </div>
              <div class="card-item-actions">
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="set_primary">
                  <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
                  <button type="submit" class="card-action-btn star" title="Establecer como principal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                  </button>
                </form>
                <button type="button" class="card-action-btn delete" title="Eliminar" onclick="showDeleteConfirm(<?= $card['id'] ?>, '<?= $bLabel ?> •••• <?= htmlspecialchars($card['last_four']) ?>')">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                  </svg>
                </button>
              </div>
            </div>
          <?php $i++; endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Add another card -->
    <button class="add-card-btn" onclick="openAddModal()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="16"/>
        <line x1="8" y1="12" x2="16" y2="12"/>
      </svg>
      Agregar otra tarjeta
    </button>

  <?php else: ?>

    <!-- Empty State -->
    <div class="empty-state">
      <div class="empty-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
          <line x1="1" y1="10" x2="23" y2="10"/>
        </svg>
      </div>
      <p class="empty-title">Sin métodos de pago</p>
      <p class="empty-sub">Agrega una tarjeta de débito o crédito para realizar tus envíos de forma rápida y segura.</p>
      <button class="empty-add-btn" onclick="openAddModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="5" x2="12" y2="19"/>
          <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Agregar tarjeta
      </button>
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
    <a class="nav-item" href="/app/pay/beneficiarios.php">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </span>
      <span class="nav-label">Beneficiarios</span>
      <span class="nav-indicator"></span>
    </a>
    <a class="nav-item active" href="/app/pay/metodos.php">
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
    <a class="nav-item" href="#">
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
     MODAL — AGREGAR TARJETA
     ============================================ -->
<div class="modal-overlay" id="addModal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div class="modal-header">
      <h2 class="modal-title">Nueva tarjeta</h2>
      <button class="modal-close" onclick="closeAddModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <div class="modal-body">

      <!-- Live Card Preview -->
      <div class="card-preview" id="cardPreview">
        <div class="card-top">
          <div class="card-chip"></div>
          <span id="previewBrand">
            <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.4)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="height:22px;width:auto;">
              <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
            </svg>
          </span>
        </div>
        <div class="card-number" id="previewNumber">&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;</div>
        <div class="card-bottom">
          <div>
            <div class="card-holder-label">Titular</div>
            <div class="card-holder-value" id="previewHolder">TU NOMBRE</div>
          </div>
          <div>
            <div class="card-expiry-label">Vence</div>
            <div class="card-expiry-value" id="previewExpiry">MM/AA</div>
          </div>
        </div>
      </div>

      <!-- Card Form -->
      <form method="POST" id="addCardForm" autocomplete="off">
        <input type="hidden" name="action" value="add">

        <div class="form-group">
          <label class="form-label">Número de tarjeta</label>
          <div class="form-input-wrap">
            <input class="form-input has-icon" id="cardNumber" name="card_number" type="text" inputmode="numeric" placeholder="0000 0000 0000 0000" maxlength="19" autocomplete="cc-number">
            <span class="form-input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
              </svg>
            </span>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Nombre del titular</label>
          <div class="form-input-wrap">
            <input class="form-input has-icon" id="cardHolder" name="card_holder" type="text" placeholder="Como aparece en la tarjeta" maxlength="100" autocomplete="cc-name">
            <span class="form-input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
              </svg>
            </span>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Expiración</label>
            <input class="form-input" id="cardExpiry" name="expiry" type="text" inputmode="numeric" placeholder="MM/AA" maxlength="5" autocomplete="cc-exp">
          </div>
          <div class="form-group">
            <label class="form-label">CVV</label>
            <div class="form-input-wrap">
              <input class="form-input has-icon" id="cardCvv" name="cvv" type="text" inputmode="numeric" placeholder="•••" maxlength="4" autocomplete="cc-csc">
              <span class="form-input-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
              </span>
            </div>
          </div>
        </div>

        <div class="form-hint">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Tus datos están protegidos con cifrado de extremo a extremo
        </div>

        <button type="submit" class="form-submit">Guardar tarjeta</button>
      </form>

    </div>
  </div>
</div>


<!-- ============================================
     CONFIRM DELETE DIALOG
     ============================================ -->
<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <div class="confirm-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
      </svg>
    </div>
    <div class="confirm-title">Eliminar tarjeta</div>
    <div class="confirm-text" id="confirmText">¿Estás seguro de eliminar esta tarjeta?</div>
    <div class="confirm-actions">
      <button class="confirm-cancel" onclick="closeDeleteConfirm()">Cancelar</button>
      <form method="POST" id="deleteForm" style="display:contents;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="card_id" id="deleteCardId" value="">
        <button type="submit" class="confirm-delete">Eliminar</button>
      </form>
    </div>
  </div>
</div>


<!-- ============================================
     JAVASCRIPT
     ============================================ -->
<script>
(function() {

  // ==========================================
  // MODAL OPEN / CLOSE
  // ==========================================
  const modal = document.getElementById('addModal');
  const confirmOv = document.getElementById('confirmOverlay');

  window.openAddModal = function() {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    // Focus first input after animation
    setTimeout(function() {
      document.getElementById('cardNumber').focus();
    }, 400);
  };

  window.closeAddModal = function() {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  };

  // Close on overlay click
  modal.addEventListener('click', function(e) {
    if (e.target === modal) closeAddModal();
  });

  // ==========================================
  // DELETE CONFIRM
  // ==========================================
  window.showDeleteConfirm = function(cardId, cardName) {
    document.getElementById('deleteCardId').value = cardId;
    document.getElementById('confirmText').textContent = '¿Eliminar ' + cardName + '? Esta acción no se puede deshacer.';
    confirmOv.classList.add('active');
  };

  window.closeDeleteConfirm = function() {
    confirmOv.classList.remove('active');
  };

  confirmOv.addEventListener('click', function(e) {
    if (e.target === confirmOv) closeDeleteConfirm();
  });

  // ==========================================
  // CARD NUMBER FORMATTING
  // ==========================================
  const numInput = document.getElementById('cardNumber');
  const holderInput = document.getElementById('cardHolder');
  const expiryInput = document.getElementById('cardExpiry');
  const cvvInput = document.getElementById('cardCvv');

  const previewNumber = document.getElementById('previewNumber');
  const previewHolder = document.getElementById('previewHolder');
  const previewExpiry = document.getElementById('previewExpiry');
  const previewBrand  = document.getElementById('previewBrand');

  numInput.addEventListener('input', function(e) {
    let value = this.value.replace(/\D/g, '');
    if (value.length > 16) value = value.substring(0, 16);

    // Format with spaces
    let formatted = '';
    for (let i = 0; i < value.length; i++) {
      if (i > 0 && i % 4 === 0) formatted += ' ';
      formatted += value[i];
    }
    this.value = formatted;

    // Update preview
    let display = '';
    for (let g = 0; g < 4; g++) {
      const start = g * 4;
      const group = value.substring(start, start + 4);
      if (g > 0) display += ' ';
      if (group.length === 4) {
        display += group;
      } else if (group.length > 0) {
        display += group + '\u2022'.repeat(4 - group.length);
      } else {
        display += '\u2022\u2022\u2022\u2022';
      }
    }
    previewNumber.textContent = display;

    // Detect brand
    updateBrand(value);
  });

  function updateBrand(digits) {
    let brandHTML = '';
    const first = digits.charAt(0);
    const firstTwo = digits.substring(0, 2);

    if (first === '4') {
      // Visa
      brandHTML = '<svg viewBox="0 0 80 26" fill="none" xmlns="http://www.w3.org/2000/svg" style="height:24px;width:auto;"><path d="M33.6 1L27.2 25H21.6L28 1H33.6ZM54.8 16.2L57.6 8.4L59.2 16.2H54.8ZM60.8 25H66L61.6 1H56.8C55.6 1 54.6 1.8 54.2 2.8L45.6 25H51.2L52.2 22H59.2L60.8 25ZM48.4 17.4C48.4 10.6 38.8 10.2 38.8 7.2C38.8 6.2 39.8 5.2 41.8 4.8C42.8 4.8 45.4 4.6 48.4 6L49.6 1.6C48 1 46 0.4 43.6 0.4C38.4 0.4 34.6 3.4 34.6 7.6C34.6 10.8 37.4 12.4 39.4 13.6C41.6 14.8 42.4 15.6 42.4 16.6C42.4 18.2 40.4 19 38.6 19C35.8 19 34.2 18.4 32 17.4L30.8 22C33 22.8 36 23.6 38.4 23.6C44 23.6 48.4 20.8 48.4 17.4ZM24 1L15.6 25H10L5.8 5.4C5.6 4.2 5.4 3.8 4.4 3.2C2.8 2.4 0 1.4 0 1.4L0.2 1H9C10.4 1 11.6 1.8 11.8 3.4L14 16L19.4 1H24Z" fill="rgba(255,255,255,0.85)"/></svg>';
    } else if (['51','52','53','54','55'].includes(firstTwo) || (parseInt(firstTwo) >= 22 && parseInt(firstTwo) <= 27)) {
      // Mastercard
      brandHTML = '<svg viewBox="0 0 48 30" fill="none" xmlns="http://www.w3.org/2000/svg" style="height:24px;width:auto;"><circle cx="18" cy="15" r="12" fill="rgba(235,0,27,0.8)"/><circle cx="30" cy="15" r="12" fill="rgba(255,159,0,0.8)"/><path d="M24 5.4A11.96 11.96 0 0 0 18 15a11.96 11.96 0 0 0 6 9.6A11.96 11.96 0 0 0 30 15a11.96 11.96 0 0 0-6-9.6Z" fill="rgba(255,95,0,0.8)"/></svg>';
    } else if (['34','37'].includes(firstTwo)) {
      // Amex
      brandHTML = '<svg viewBox="0 0 48 30" fill="none" xmlns="http://www.w3.org/2000/svg" style="height:24px;width:auto;"><rect width="48" height="30" rx="4" fill="rgba(0,111,207,0.5)"/><text x="24" y="19" text-anchor="middle" font-family="Inter, sans-serif" font-weight="700" font-size="10" fill="rgba(255,255,255,0.85)">AMEX</text></svg>';
    } else {
      brandHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.4)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="height:22px;width:auto;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>';
    }
    previewBrand.innerHTML = brandHTML;
  }

  // ==========================================
  // CARD HOLDER
  // ==========================================
  holderInput.addEventListener('input', function() {
    const val = this.value.trim();
    previewHolder.textContent = val.length > 0 ? val.toUpperCase() : 'TU NOMBRE';
  });

  // ==========================================
  // EXPIRY FORMATTING
  // ==========================================
  expiryInput.addEventListener('input', function(e) {
    let value = this.value.replace(/\D/g, '');
    if (value.length > 4) value = value.substring(0, 4);

    if (value.length >= 2) {
      // Validate month
      let month = parseInt(value.substring(0, 2));
      if (month > 12) month = 12;
      if (month < 1 && value.substring(0, 2) !== '0' && value.substring(0, 2) !== '00') month = 1;
      const monthStr = month.toString().padStart(2, '0');
      this.value = monthStr + (value.length > 2 ? '/' + value.substring(2) : '');
    } else {
      this.value = value;
    }

    previewExpiry.textContent = this.value.length > 0 ? this.value : 'MM/AA';
  });

  // ==========================================
  // CVV — only digits
  // ==========================================
  cvvInput.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '');
  });

  // ==========================================
  // TOAST AUTO-DISMISS
  // ==========================================
  var toast = document.getElementById('toast');
  if (toast) {
    setTimeout(function() {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(-12px)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(function() { toast.remove(); }, 300);
    }, 3500);
  }

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
