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

// Obtener datos completos del usuario
$stmt = $pdo->prepare("
    SELECT first_name, last_name, email, phone, birthdate, country,
           residence_country, address, city, postal_code, id_type, id_number, created_at
    FROM users WHERE id = ? LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$firstName = normalizeName($user['first_name'] ?? '');
$lastName  = normalizeName($user['last_name'] ?? '');
$avatarInitials = mb_strtoupper(
    mb_substr($firstName, 0, 1, 'UTF-8') . mb_substr($lastName, 0, 1, 'UTF-8'),
    'UTF-8'
);

// Fecha de registro formateada
$mesesEs = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$memberSince = '';
if (!empty($user['created_at'])) {
    $ts = strtotime($user['created_at']);
    $memberSince = $mesesEs[(int)date('m', $ts) - 1] . ' ' . date('Y', $ts);
}

// Estadísticas del usuario
$stmtTx = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ?");
$stmtTx->execute([$_SESSION['user_id']]);
$totalTransfers = (int)$stmtTx->fetchColumn();

$stmtBen = $pdo->prepare("SELECT COUNT(*) FROM beneficiaries WHERE user_id = ?");
$stmtBen->execute([$_SESSION['user_id']]);
$totalBeneficiaries = (int)$stmtBen->fetchColumn();

// ============================================
// ACCIONES POST
// ============================================
$actionMsg = '';
$actionType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ACTUALIZAR DATOS PERSONALES
    if ($action === 'update_personal') {
        $newFirst = trim($_POST['first_name'] ?? '');
        $newLast  = trim($_POST['last_name'] ?? '');
        $newPhone = trim($_POST['phone'] ?? '');
        $newBirth = trim($_POST['birthdate'] ?? '');

        if ($newFirst === '' || $newLast === '') {
            $actionMsg = 'Nombre y apellido son obligatorios';
            $actionType = 'error';
        } else {
            $stmtUp = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, birthdate = ? WHERE id = ?");
            $stmtUp->execute([$newFirst, $newLast, $newPhone, $newBirth ?: null, $_SESSION['user_id']]);
            $actionMsg = 'Datos personales actualizados';
            $actionType = 'success';

            // Refrescar datos
            $user['first_name'] = $newFirst;
            $user['last_name'] = $newLast;
            $user['phone'] = $newPhone;
            $user['birthdate'] = $newBirth;
            $firstName = normalizeName($newFirst);
            $lastName  = normalizeName($newLast);
            $avatarInitials = mb_strtoupper(
                mb_substr($firstName, 0, 1, 'UTF-8') . mb_substr($lastName, 0, 1, 'UTF-8'),
                'UTF-8'
            );
        }
    }

    // ACTUALIZAR DIRECCIÓN
    if ($action === 'update_address') {
        $newCountry = trim($_POST['residence_country'] ?? '');
        $newAddress = trim($_POST['address'] ?? '');
        $newCity    = trim($_POST['city'] ?? '');
        $newPostal  = trim($_POST['postal_code'] ?? '');

        $stmtUp = $pdo->prepare("UPDATE users SET residence_country = ?, address = ?, city = ?, postal_code = ? WHERE id = ?");
        $stmtUp->execute([$newCountry, $newAddress, $newCity, $newPostal, $_SESSION['user_id']]);
        $actionMsg = 'Dirección actualizada';
        $actionType = 'success';

        $user['residence_country'] = $newCountry;
        $user['address'] = $newAddress;
        $user['city'] = $newCity;
        $user['postal_code'] = $newPostal;
    }

    // ACTUALIZAR CORREO
    if ($action === 'update_email') {
        $newEmail = trim($_POST['email'] ?? '');

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $actionMsg = 'Correo electrónico inválido';
            $actionType = 'error';
        } else {
            // Verificar que no existe
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmtCheck->execute([$newEmail, $_SESSION['user_id']]);
            if ($stmtCheck->fetch()) {
                $actionMsg = 'Este correo ya está registrado';
                $actionType = 'error';
            } else {
                $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$newEmail, $_SESSION['user_id']]);
                $actionMsg = 'Correo actualizado';
                $actionType = 'success';
                $user['email'] = $newEmail;
            }
        }
    }

    // CAMBIAR CONTRASEÑA
    if ($action === 'update_password') {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw     = $_POST['new_password'] ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        if ($newPw !== $confirmPw) {
            $actionMsg = 'Las contraseñas no coinciden';
            $actionType = 'error';
        } elseif (strlen($newPw) < 8) {
            $actionMsg = 'La contraseña debe tener al menos 8 caracteres';
            $actionType = 'error';
        } else {
            $stmtPw = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmtPw->execute([$_SESSION['user_id']]);
            $hash = $stmtPw->fetchColumn();

            if (!password_verify($currentPw, $hash)) {
                $actionMsg = 'La contraseña actual es incorrecta';
                $actionType = 'error';
            } else {
                $newHash = password_hash($newPw, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $_SESSION['user_id']]);
                $actionMsg = 'Contraseña actualizada correctamente';
                $actionType = 'success';
            }
        }
    }
}

// Formatear birthdate para el input
$birthdateValue = '';
if (!empty($user['birthdate'])) {
    $birthdateValue = date('Y-m-d', strtotime($user['birthdate']));
}

// Mapa de tipos de documento
$idTypeLabels = [
    'DNI' => 'DNI',
    'NIE' => 'NIE',
    'passport' => 'Pasaporte',
    'Pasaporte' => 'Pasaporte',
];
$idTypeDisplay = $idTypeLabels[$user['id_type'] ?? ''] ?? ($user['id_type'] ?? '-');
$idNumberDisplay = $user['id_number'] ?? '-';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Perfil | KBPPAY</title>
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

.header-back-icon svg { width: 18px; height: 18px; color: var(--text-secondary); }

.header-back:hover .header-back-icon { background: var(--glass-hover); }

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
   TOAST
   ============================================ */

.toast {
  position: fixed;
  top: calc(var(--header-h) + var(--safe-top) + 12px);
  left: 20px; right: 20px;
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

.toast.success { background: rgba(52,211,153,0.15); border: 1px solid rgba(52,211,153,0.25); color: #34D399; }
.toast.error   { background: rgba(248,113,113,0.15); border: 1px solid rgba(248,113,113,0.25); color: #F87171; }
.toast svg { width: 18px; height: 18px; flex-shrink: 0; }

@keyframes slideDown {
  from { opacity: 0; transform: translateY(-12px); }
  to   { opacity: 1; transform: translateY(0); }
}


/* ============================================
   PROFILE HERO
   ============================================ */

.profile-hero {
  text-align: center;
  padding: 8px 0 28px;
  animation: fadeInUp 0.5s ease both;
}

.profile-avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: var(--gradient);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 26px;
  letter-spacing: 0.5px;
  margin: 0 auto 14px;
  box-shadow:
    0 4px 20px rgba(82,174,50,0.25),
    0 8px 32px rgba(50,89,253,0.18);
  position: relative;
}

.profile-name {
  font-size: 22px;
  font-weight: 700;
  letter-spacing: -0.02em;
  margin-bottom: 4px;
}

.profile-email {
  font-size: 13px;
  color: var(--text-muted);
  margin-bottom: 16px;
}

.profile-stats {
  display: flex;
  justify-content: center;
  gap: 32px;
}

.profile-stat {
  text-align: center;
}

.profile-stat-value {
  font-size: 20px;
  font-weight: 700;
  color: var(--text);
  line-height: 1.2;
}

.profile-stat-label {
  font-size: 11px;
  color: var(--text-muted);
  margin-top: 2px;
}

.profile-member {
  font-size: 11px;
  color: var(--text-muted);
  margin-top: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.profile-member svg { width: 12px; height: 12px; }


/* ============================================
   SECTION
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

.section-group { margin-bottom: 24px; }


/* ============================================
   PROFILE CARDS (editable sections)
   ============================================ */

.profile-card {
  background: var(--glass);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius);
  overflow: hidden;
  transition: border-color 0.2s ease;
  animation: fadeInUp 0.4s ease both;
}

.profile-card + .profile-card { margin-top: 8px; }

/* View mode - field rows */
.field-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  transition: background 0.15s ease;
}

.field-row + .field-row { border-top: 1px solid rgba(255,255,255,0.04); }

.field-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.06);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: var(--text-muted);
}

.field-icon svg { width: 18px; height: 18px; }

.field-info { flex: 1; min-width: 0; }

.field-label {
  font-size: 11px;
  color: var(--text-muted);
  margin-bottom: 2px;
}

.field-value {
  font-size: 14px;
  font-weight: 500;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.field-value.empty { color: var(--text-muted); font-style: italic; }

/* Edit button */
.card-edit-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  padding: 12px;
  border: none;
  border-top: 1px solid rgba(255,255,255,0.04);
  background: transparent;
  color: var(--text-muted);
  font-family: inherit;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  -webkit-tap-highlight-color: transparent;
}

.card-edit-btn:hover { background: var(--glass-hover); color: var(--text-secondary); }
.card-edit-btn svg { width: 14px; height: 14px; }


/* ============================================
   MODAL (edit forms)
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
  width: 36px; height: 4px; border-radius: 2px;
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
  width: 32px; height: 32px;
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

.modal-close:hover { background: var(--glass-hover); color: var(--text); }
.modal-close svg { width: 18px; height: 18px; }

.modal-body { padding: 24px; }


/* ============================================
   FORM FIELDS
   ============================================ */

.form-group { margin-bottom: 16px; }

.form-label {
  font-size: 13px;
  font-weight: 500;
  color: var(--text-secondary);
  margin-bottom: 6px;
  display: block;
}

.form-input,
.form-select {
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

.form-input:focus,
.form-select:focus {
  border-color: rgba(82,174,50,0.4);
  background: rgba(255,255,255,0.07);
}

.form-select {
  appearance: none;
  -webkit-appearance: none;
  cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='rgba(255,255,255,0.4)' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 14px center;
  background-size: 16px;
  padding-right: 40px;
}

.form-select option { background: #161b2e; color: #fff; }

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.form-submit {
  width: 100%;
  padding: 15px;
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

.form-submit:active { transform: scale(0.98); }

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
   ID CARD (read-only)
   ============================================ */

.id-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 10px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  background: rgba(52,211,153,0.10);
  color: #34D399;
  border: 1px solid rgba(52,211,153,0.15);
}

.id-badge svg { width: 12px; height: 12px; }


/* ============================================
   LOGOUT BUTTON
   ============================================ */

.logout-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 16px;
  background: rgba(248,113,113,0.08);
  border: 1px solid rgba(248,113,113,0.12);
  border-radius: var(--radius);
  color: #F87171;
  font-family: inherit;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  -webkit-tap-highlight-color: transparent;
  text-decoration: none;
  animation: fadeInUp 0.4s ease 0.3s both;
}

.logout-btn:hover {
  background: rgba(248,113,113,0.14);
  border-color: rgba(248,113,113,0.20);
}

.logout-btn:active { transform: scale(0.98); }
.logout-btn svg { width: 18px; height: 18px; }


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

.fab:active { transform: translateX(-50%) scale(0.96); }
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
     TOAST
     ============================================ -->
<?php if ($actionMsg !== ''): ?>
<div class="toast <?= $actionType ?>" id="toast">
  <?php if ($actionType === 'success'): ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/>
    </svg>
  <?php else: ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
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

  <!-- Profile Hero -->
  <div class="profile-hero">
    <div class="profile-avatar"><?= htmlspecialchars($avatarInitials) ?></div>
    <div class="profile-name"><?= htmlspecialchars($firstName . ' ' . $lastName) ?></div>
    <div class="profile-email"><?= htmlspecialchars($user['email'] ?? '') ?></div>

    <div class="profile-stats">
      <div class="profile-stat">
        <div class="profile-stat-value"><?= $totalTransfers ?></div>
        <div class="profile-stat-label">Envíos</div>
      </div>
      <div class="profile-stat">
        <div class="profile-stat-value"><?= $totalBeneficiaries ?></div>
        <div class="profile-stat-label">Beneficiarios</div>
      </div>
    </div>

    <?php if ($memberSince): ?>
      <div class="profile-member">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        Miembro desde <?= htmlspecialchars($memberSince) ?>
      </div>
    <?php endif; ?>
  </div>


  <!-- ==========================================
       DATOS PERSONALES
       ========================================== -->
  <div class="section-group" style="animation: fadeInUp 0.4s ease 0.08s both;">
    <div class="section-label">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
      </svg>
      Datos personales
    </div>

    <div class="profile-card">
      <div class="field-row">
        <div class="field-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
          </svg>
        </div>
        <div class="field-info">
          <div class="field-label">Nombre completo</div>
          <div class="field-value"><?= htmlspecialchars($firstName . ' ' . $lastName) ?></div>
        </div>
      </div>

      <div class="field-row">
        <div class="field-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
          </svg>
        </div>
        <div class="field-info">
          <div class="field-label">Teléfono</div>
          <div class="field-value <?= empty($user['phone']) ? 'empty' : '' ?>"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Sin registrar' ?></div>
        </div>
      </div>

      <div class="field-row">
        <div class="field-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
          </svg>
        </div>
        <div class="field-info">
          <div class="field-label">Fecha de nacimiento</div>
          <div class="field-value <?= empty($user['birthdate']) ? 'empty' : '' ?>">
            <?php
              if (!empty($user['birthdate'])) {
                  $bts = strtotime($user['birthdate']);
                  echo date('d', $bts) . ' de ' . $mesesEs[(int)date('m', $bts) - 1] . ' de ' . date('Y', $bts);
              } else {
                  echo 'Sin registrar';
              }
            ?>
          </div>
        </div>
      </div>

      <div class="field-row">
        <div class="field-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
          </svg>
        </div>
        <div class="field-info">
          <div class="field-label">Nacionalidad</div>
          <div class="field-value <?= empty($user['country']) ? 'empty' : '' ?>"><?= !empty($user['country']) ? htmlspecialchars($user['country']) : 'Sin registrar' ?></div>
        </div>
      </div>

      <button class="card-edit-btn" onclick="openModal('personalModal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
        </svg>
        Editar datos personales
      </button>
    </div>
  </div>


  <!-- ==========================================
       DIRECCIÓN
       ========================================== -->
  <div class="section-group" style="animation: fadeInUp 0.4s ease 0.14s both;">
    <div class="section-label">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
      </svg>
      Dirección
    </div>

    <div class="profile-card">
      <div class="field-row">
        <div class="field-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
          </svg>
        </div>
        <div class="field-info">
          <div class="field-label">Dirección</div>
          <div class="field-value <?= empty($user['address']) ? 'empty' : '' ?>"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'Sin registrar' ?></div>
        </div>
      </div>

      <div class="field-row">
        <div class="field-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
        </div>
        <div class="field-info">
          <div class="field-label">Ciudad</div>
          <div class="field-value <?= empty($user['city']) ? 'empty' : '' ?>"><?= !empty($user['city']) ? htmlspecialchars($user['city']) : 'Sin registrar' ?></div>
        </div>
      </div>

      <div class="field-row">
        <div class="field-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
          </svg>
        </div>
        <div class="field-info">
          <div class="field-label">Código postal</div>
          <div class="field-value <?= empty($user['postal_code']) ? 'empty' : '' ?>"><?= !empty($user['postal_code']) ? htmlspecialchars($user['postal_code']) : 'Sin registrar' ?></div>
        </div>
      </div>

      <div class="field-row">
        <div class="field-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
          </svg>
        </div>
        <div class="field-info">
          <div class="field-label">País de residencia</div>
          <div class="field-value <?= empty($user['residence_country']) ? 'empty' : '' ?>"><?= !empty($user['residence_country']) ? htmlspecialchars($user['residence_country']) : 'Sin registrar' ?></div>
        </div>
      </div>

      <button class="card-edit-btn" onclick="openModal('addressModal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
        </svg>
        Editar dirección
      </button>
    </div>
  </div>


  <!-- ==========================================
       DOCUMENTO DE IDENTIDAD (read-only)
       ========================================== -->
  <?php if (!empty($user['id_type']) && !empty($user['id_number'])): ?>
  <div class="section-group" style="animation: fadeInUp 0.4s ease 0.18s both;">
    <div class="section-label">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
      </svg>
      Verificación
    </div>

    <div class="profile-card">
      <div class="field-row">
        <div class="field-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
          </svg>
        </div>
        <div class="field-info">
          <div class="field-label">Documento de identidad</div>
          <div class="field-value"><?= htmlspecialchars($idTypeDisplay) ?> · <?= htmlspecialchars($idNumberDisplay) ?></div>
        </div>
        <span class="id-badge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/>
          </svg>
          Verificado
        </span>
      </div>
    </div>
  </div>
  <?php endif; ?>


  <!-- ==========================================
       SEGURIDAD
       ========================================== -->
  <div class="section-group" style="animation: fadeInUp 0.4s ease 0.22s both;">
    <div class="section-label">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
      </svg>
      Seguridad
    </div>

    <div class="profile-card">
      <div class="field-row">
        <div class="field-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
          </svg>
        </div>
        <div class="field-info">
          <div class="field-label">Correo electrónico</div>
          <div class="field-value"><?= htmlspecialchars($user['email'] ?? '') ?></div>
        </div>
      </div>

      <button class="card-edit-btn" onclick="openModal('emailModal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
        </svg>
        Cambiar correo
      </button>
    </div>

    <div class="profile-card" style="margin-top: 8px;">
      <div class="field-row">
        <div class="field-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
        </div>
        <div class="field-info">
          <div class="field-label">Contraseña</div>
          <div class="field-value">••••••••</div>
        </div>
      </div>

      <button class="card-edit-btn" onclick="openModal('passwordModal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
        </svg>
        Cambiar contraseña
      </button>
    </div>
  </div>


  <!-- ==========================================
       CERRAR SESIÓN
       ========================================== -->
  <a class="logout-btn" href="/app/pay/auth/logout.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
    </svg>
    Cerrar sesión
  </a>

</main>


<!-- ============================================
     FAB — ENVIAR
     ============================================ -->
<a class="fab" href="/app/pay/auth/transfers/new_register_form_amount.php">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M22 2L11 13"/><path d="M22 2L15 22L11 13L2 9L22 2Z"/>
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
    <a class="nav-item active" href="/app/pay/perfil.php">
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
     MODALS
     ============================================ -->

<!-- MODAL: Datos Personales -->
<div class="modal-overlay" id="personalModal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div class="modal-header">
      <h2 class="modal-title">Datos personales</h2>
      <button class="modal-close" onclick="closeModal('personalModal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="update_personal">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nombre</label>
            <input class="form-input" name="first_name" type="text" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Apellido</label>
            <input class="form-input" name="last_name" type="text" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Teléfono</label>
          <input class="form-input" name="phone" type="tel" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+34 600 000 000">
        </div>
        <div class="form-group">
          <label class="form-label">Fecha de nacimiento</label>
          <input class="form-input" name="birthdate" type="date" value="<?= htmlspecialchars($birthdateValue) ?>">
        </div>
        <button type="submit" class="form-submit">Guardar cambios</button>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: Dirección -->
<div class="modal-overlay" id="addressModal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div class="modal-header">
      <h2 class="modal-title">Dirección</h2>
      <button class="modal-close" onclick="closeModal('addressModal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="update_address">
        <div class="form-group">
          <label class="form-label">Dirección</label>
          <input class="form-input" name="address" type="text" value="<?= htmlspecialchars($user['address'] ?? '') ?>" placeholder="Calle, número, piso...">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Ciudad</label>
            <input class="form-input" name="city" type="text" value="<?= htmlspecialchars($user['city'] ?? '') ?>" placeholder="Madrid">
          </div>
          <div class="form-group">
            <label class="form-label">Código postal</label>
            <input class="form-input" name="postal_code" type="text" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>" placeholder="28001">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">País de residencia</label>
          <select class="form-select" name="residence_country">
            <option value="">Seleccionar</option>
            <option value="España" <?= ($user['residence_country'] ?? '') === 'España' ? 'selected' : '' ?>>España</option>
            <option value="Portugal" <?= ($user['residence_country'] ?? '') === 'Portugal' ? 'selected' : '' ?>>Portugal</option>
            <option value="Francia" <?= ($user['residence_country'] ?? '') === 'Francia' ? 'selected' : '' ?>>Francia</option>
            <option value="Italia" <?= ($user['residence_country'] ?? '') === 'Italia' ? 'selected' : '' ?>>Italia</option>
            <option value="Alemania" <?= ($user['residence_country'] ?? '') === 'Alemania' ? 'selected' : '' ?>>Alemania</option>
            <option value="Reino Unido" <?= ($user['residence_country'] ?? '') === 'Reino Unido' ? 'selected' : '' ?>>Reino Unido</option>
            <option value="Países Bajos" <?= ($user['residence_country'] ?? '') === 'Países Bajos' ? 'selected' : '' ?>>Países Bajos</option>
            <option value="Bélgica" <?= ($user['residence_country'] ?? '') === 'Bélgica' ? 'selected' : '' ?>>Bélgica</option>
            <option value="Otro" <?= ($user['residence_country'] ?? '') === 'Otro' ? 'selected' : '' ?>>Otro</option>
          </select>
        </div>
        <button type="submit" class="form-submit">Guardar dirección</button>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: Cambiar correo -->
<div class="modal-overlay" id="emailModal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div class="modal-header">
      <h2 class="modal-title">Cambiar correo</h2>
      <button class="modal-close" onclick="closeModal('emailModal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="update_email">
        <div class="form-group">
          <label class="form-label">Nuevo correo electrónico</label>
          <input class="form-input" name="email" type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
        </div>
        <div class="form-hint">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
          </svg>
          Este será tu correo de inicio de sesión
        </div>
        <button type="submit" class="form-submit">Actualizar correo</button>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: Cambiar contraseña -->
<div class="modal-overlay" id="passwordModal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div class="modal-header">
      <h2 class="modal-title">Cambiar contraseña</h2>
      <button class="modal-close" onclick="closeModal('passwordModal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="update_password">
        <div class="form-group">
          <label class="form-label">Contraseña actual</label>
          <input class="form-input" name="current_password" type="password" placeholder="••••••••" required>
        </div>
        <div class="form-group">
          <label class="form-label">Nueva contraseña</label>
          <input class="form-input" name="new_password" type="password" placeholder="Mínimo 8 caracteres" minlength="8" required>
        </div>
        <div class="form-group">
          <label class="form-label">Confirmar nueva contraseña</label>
          <input class="form-input" name="confirm_password" type="password" placeholder="Repite la contraseña" minlength="8" required>
        </div>
        <div class="form-hint">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Usa al menos 8 caracteres con letras y números
        </div>
        <button type="submit" class="form-submit">Cambiar contraseña</button>
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
  window.openModal = function(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
  };

  window.closeModal = function(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
  };

  // Close on overlay click
  document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
      }
    });
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
