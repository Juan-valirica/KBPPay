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

// Usuario
$stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$firstName = normalizeName($user['first_name'] ?? '');
$lastName  = normalizeName($user['last_name'] ?? '');
$userEmail = trim($user['email'] ?? '');
$avatarInitials = mb_strtoupper(
    mb_substr($firstName, 0, 1, 'UTF-8') . mb_substr($lastName, 0, 1, 'UTF-8'),
    'UTF-8'
);

// Envío de formulario de contacto
$contactMsg = '';
$contactType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contact') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($subject === '' || $message === '') {
        $contactMsg = 'Completa todos los campos';
        $contactType = 'error';
    } else {
        $to = 'hola@kbppay.es';
        $headers  = "From: " . $userEmail . "\r\n";
        $headers .= "Reply-To: " . $userEmail . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: KBPPay/1.0\r\n";
        $fullMessage = "De: " . $firstName . " " . $lastName . " (" . $userEmail . ")\n\n" . $message;

        $sent = @mail($to, "[KBPPay Ayuda] " . $subject, $fullMessage, $headers);
        if ($sent) {
            $contactMsg = 'Mensaje enviado correctamente';
            $contactType = 'success';
        } else {
            $contactMsg = 'No se pudo enviar el mensaje. Intenta de nuevo.';
            $contactType = 'error';
        }
    }
}

$faqs = [
    [
        'q' => '¿Cuánto tiempo tarda en llegar el dinero?',
        'a' => 'Las transferencias con KBP Pay son casi instantáneas. En la mayoría de los casos, el dinero llega en menos de 5 minutos. El tiempo máximo de procesamiento es de 24 horas en casos excepcionales.',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'
    ],
    [
        'q' => '¿Qué documentos necesito para enviar dinero?',
        'a' => 'Para realizar tu primera transferencia necesitas un documento de identidad válido (DNI, NIE o pasaporte) y verificar tu cuenta. El proceso de verificación es simple y rápido, puedes completarlo en pocos minutos.',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>'
    ],
    [
        'q' => '¿Cuál es el límite de envío?',
        'a' => 'Los límites de envío varían según tu nivel de verificación. Para cuentas verificadas básicas, puedes enviar hasta €399 por transacción, límite diario de €1,000 y hasta €3,500 mensuales. Los límites pueden aumentarse completando la verificación avanzada.',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>'
    ],
    [
        'q' => '¿Cómo recibe el dinero mi familiar en Venezuela?',
        'a' => 'El dinero se deposita directamente en la cuenta bancaria venezolana que indiques. También ofrecemos la opción de pago móvil y transferencias a billeteras digitales populares en Venezuela.',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'
    ],
    [
        'q' => '¿Qué tasa de cambio utilizan?',
        'a' => 'Utilizamos tasas de cambio competitivas del mercado, actualizadas en tiempo real. Puedes ver la tasa exacta antes de confirmar cada transacción. No hay comisiones ocultas, el tipo de cambio que ves es el que obtienes.',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3L4 7l4 4"/><path d="M4 7h16"/><path d="M16 21l4-4-4-4"/><path d="M20 17H4"/></svg>'
    ],
    [
        'q' => '¿Es seguro enviar dinero con KBP Pay?',
        'a' => 'Absolutamente. Operamos con licencias white label reguladas en España y cumplimos con todas las normativas europeas de servicios financieros. Utilizamos encriptación de nivel bancario y cumplimos con los estándares PCI DSS para proteger tu información.',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'
    ],
    [
        'q' => '¿Puedo cancelar una transferencia?',
        'a' => 'Puedes cancelar una transferencia antes de que sea procesada. Una vez que el dinero ha sido enviado al beneficiario, la transacción no puede revertirse. Contacta a nuestro soporte si necesitas ayuda.',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
    ],
    [
        'q' => '¿Qué métodos de pago aceptan?',
        'a' => 'Aceptamos tarjetas de débito y crédito, transferencias bancarias y pagos desde tu cuenta verificada. Todos los métodos de pago son seguros y están protegidos por nuestros sistemas de seguridad.',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>'
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Ayuda | KBPPAY</title>
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
  margin-bottom: 28px;
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
  line-height: 1.4;
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

.toast svg { width: 18px; height: 18px; flex-shrink: 0; }

@keyframes slideDown {
  from { opacity: 0; transform: translateY(-12px); }
  to   { opacity: 1; transform: translateY(0); }
}


/* ============================================
   CONTACT CHANNELS
   ============================================ */

.channels {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 24px;
  animation: fadeInUp 0.5s ease 0.06s both;
}

.channel-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 20px 12px;
  background: var(--glass);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius);
  text-decoration: none;
  color: var(--text);
  transition: all 0.2s ease;
  -webkit-tap-highlight-color: transparent;
}

.channel-btn:hover {
  background: var(--glass-hover);
  border-color: rgba(255,255,255,0.12);
  transform: translateY(-1px);
}

.channel-btn:active {
  transform: scale(0.97);
}

.channel-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.channel-icon svg { width: 24px; height: 24px; }

.channel-icon.whatsapp {
  background: rgba(37,211,102,0.12);
  color: #25D366;
}

.channel-icon.phone {
  background: rgba(96,165,250,0.12);
  color: #60A5FA;
}

.channel-label {
  font-size: 13px;
  font-weight: 600;
  text-align: center;
  line-height: 1.3;
}

.channel-sub {
  font-size: 11px;
  color: var(--text-muted);
  text-align: center;
  margin-top: -4px;
}


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

.section-group { margin-bottom: 28px; }


/* ============================================
   CONTACT FORM
   ============================================ */

.contact-card {
  background: var(--glass);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius);
  padding: 20px;
  animation: fadeInUp 0.5s ease 0.12s both;
}

.contact-from {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.05);
  border-radius: var(--radius-sm);
  margin-bottom: 14px;
  font-size: 13px;
  color: var(--text-secondary);
}

.contact-from svg { width: 16px; height: 16px; color: var(--text-muted); flex-shrink: 0; }
.contact-from span { color: var(--text); font-weight: 500; }

.form-group { margin-bottom: 14px; }

.form-label {
  font-size: 13px;
  font-weight: 500;
  color: var(--text-secondary);
  margin-bottom: 6px;
  display: block;
}

.form-input,
.form-textarea {
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

.form-input::placeholder,
.form-textarea::placeholder { color: var(--text-muted); }

.form-input:focus,
.form-textarea:focus {
  border-color: rgba(82,174,50,0.4);
  background: rgba(255,255,255,0.07);
}

.form-textarea {
  resize: vertical;
  min-height: 110px;
  line-height: 1.5;
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
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: transform 0.15s ease, box-shadow 0.2s ease;
  box-shadow:
    0 4px 16px rgba(82,174,50,0.20),
    0 8px 32px rgba(50,89,253,0.15);
  margin-top: 4px;
}

.form-submit:hover {
  transform: translateY(-1px);
  box-shadow:
    0 6px 20px rgba(82,174,50,0.30),
    0 10px 36px rgba(50,89,253,0.22);
}

.form-submit:active { transform: scale(0.98); }
.form-submit svg { width: 18px; height: 18px; }


/* ============================================
   FAQ ACCORDION
   ============================================ */

.faq-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  animation: fadeInUp 0.5s ease 0.18s both;
}

.faq-item {
  background: var(--glass);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius);
  overflow: hidden;
  transition: border-color 0.2s ease, background 0.2s ease;
}

.faq-item.open {
  border-color: rgba(255,255,255,0.10);
  background: var(--glass-hover);
}

.faq-question {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
  user-select: none;
  transition: background 0.15s ease;
}

.faq-question:hover { background: rgba(255,255,255,0.02); }

.faq-icon {
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
  transition: color 0.2s ease, background 0.2s ease;
}

.faq-icon svg { width: 18px; height: 18px; }

.faq-item.open .faq-icon {
  background: rgba(82,174,50,0.10);
  color: var(--green);
}

.faq-q-text {
  flex: 1;
  font-size: 14px;
  font-weight: 600;
  color: var(--text);
  line-height: 1.35;
}

.faq-chevron {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  color: var(--text-muted);
  transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
  display: flex;
  align-items: center;
  justify-content: center;
}

.faq-chevron svg { width: 18px; height: 18px; }

.faq-item.open .faq-chevron {
  transform: rotate(180deg);
  color: var(--text-secondary);
}

.faq-answer {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.35s cubic-bezier(0.32, 0.72, 0, 1), opacity 0.25s ease;
  opacity: 0;
}

.faq-item.open .faq-answer {
  opacity: 1;
}

.faq-a-inner {
  padding: 0 16px 18px 64px;
  font-size: 13px;
  line-height: 1.65;
  color: var(--text-secondary);
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
<?php if ($contactMsg !== ''): ?>
<div class="toast <?= $contactType ?>" id="toast">
  <?php if ($contactType === 'success'): ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/>
    </svg>
  <?php else: ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
    </svg>
  <?php endif; ?>
  <?= htmlspecialchars($contactMsg) ?>
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
    <h1 class="page-title">Ayuda</h1>
    <p class="page-sub">Estamos aquí para ti. Contáctanos o revisa nuestras preguntas frecuentes.</p>
  </section>


  <!-- ==========================================
       CONTACT CHANNELS
       ========================================== -->
  <div class="channels">
    <a class="channel-btn" href="https://wa.me/34600095260" target="_blank" rel="noopener">
      <div class="channel-icon whatsapp">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347zM12.05 21.785c-1.795 0-3.56-.483-5.1-1.396l-.365-.218-3.79 .994 1.012-3.697-.238-.38A9.69 9.69 0 0 1 2.3 12.05C2.3 6.674 6.674 2.3 12.05 2.3c2.607 0 5.058 1.015 6.9 2.858a9.7 9.7 0 0 1 2.85 6.899c-.003 5.376-4.377 9.75-9.75 9.75zM12.05 0C5.405 0 0 5.405 0 12.05c0 2.125.554 4.2 1.607 6.03L0 24l6.084-1.596A12.01 12.01 0 0 0 12.05 24.1C18.695 24.1 24.1 18.695 24.1 12.05S18.695 0 12.05 0z"/>
        </svg>
      </div>
      <span class="channel-label">WhatsApp</span>
      <span class="channel-sub">Chat en vivo</span>
    </a>

    <a class="channel-btn" href="tel:+34600095260">
      <div class="channel-icon phone">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
        </svg>
      </div>
      <span class="channel-label">Llamar</span>
      <span class="channel-sub">+34 600 095 260</span>
    </a>
  </div>


  <!-- ==========================================
       CONTACT FORM
       ========================================== -->
  <div class="section-group">
    <div class="section-label">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
      </svg>
      Envíanos un correo
    </div>

    <div class="contact-card">
      <div class="contact-from">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
        </svg>
        De: <span><?= htmlspecialchars($userEmail) ?></span>
      </div>

      <form method="POST" id="contactForm">
        <input type="hidden" name="action" value="contact">

        <div class="form-group">
          <label class="form-label">Asunto</label>
          <input class="form-input" name="subject" type="text" placeholder="¿En qué podemos ayudarte?" maxlength="200" required>
        </div>

        <div class="form-group">
          <label class="form-label">Mensaje</label>
          <textarea class="form-textarea" name="message" placeholder="Describe tu consulta con el mayor detalle posible..." maxlength="2000" required></textarea>
        </div>

        <button type="submit" class="form-submit">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 2L11 13"/><path d="M22 2L15 22L11 13L2 9L22 2Z"/>
          </svg>
          Enviar mensaje
        </button>
      </form>
    </div>
  </div>


  <!-- ==========================================
       FAQ SECTION
       ========================================== -->
  <div class="section-group">
    <div class="section-label">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
      Preguntas frecuentes
    </div>

    <div class="faq-list">
      <?php foreach ($faqs as $i => $faq): ?>
        <div class="faq-item" style="animation: fadeInUp 0.4s ease <?= 0.22 + ($i * 0.04) ?>s both;">
          <div class="faq-question" onclick="toggleFaq(this)">
            <div class="faq-icon">
              <?= $faq['icon'] ?>
            </div>
            <span class="faq-q-text"><?= htmlspecialchars($faq['q']) ?></span>
            <span class="faq-chevron">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"/>
              </svg>
            </span>
          </div>
          <div class="faq-answer">
            <div class="faq-a-inner"><?= htmlspecialchars($faq['a']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
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
    <a class="nav-item active" href="/app/pay/ayuda.php">
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
     JAVASCRIPT
     ============================================ -->
<script>
(function() {

  // ==========================================
  // FAQ ACCORDION
  // ==========================================
  window.toggleFaq = function(el) {
    var item = el.closest('.faq-item');
    var answer = item.querySelector('.faq-answer');
    var isOpen = item.classList.contains('open');

    // Close all others
    document.querySelectorAll('.faq-item.open').forEach(function(openItem) {
      if (openItem !== item) {
        openItem.classList.remove('open');
        openItem.querySelector('.faq-answer').style.maxHeight = '0';
      }
    });

    if (isOpen) {
      item.classList.remove('open');
      answer.style.maxHeight = '0';
    } else {
      item.classList.add('open');
      answer.style.maxHeight = answer.scrollHeight + 'px';
    }
  };

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
