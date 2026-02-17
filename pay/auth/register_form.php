<?php
// auth/register_form.php
session_start();

$error = $_SESSION['register_error'] ?? null;
$old   = $_SESSION['register_old'] ?? [];

unset($_SESSION['register_error']);

if (!$error) {
    unset($_SESSION['register_old']);
}



$countries = [
  ['iso'=>'AF','name'=>'Afganistán','dial'=>'+93','flag'=>'🇦🇫'],
  ['iso'=>'AL','name'=>'Albania','dial'=>'+355','flag'=>'🇦🇱'],
  ['iso'=>'DE','name'=>'Alemania','dial'=>'+49','flag'=>'🇩🇪'],
  ['iso'=>'AD','name'=>'Andorra','dial'=>'+376','flag'=>'🇦🇩'],
  ['iso'=>'AR','name'=>'Argentina','dial'=>'+54','flag'=>'🇦🇷'],
  ['iso'=>'AU','name'=>'Australia','dial'=>'+61','flag'=>'🇦🇺'],
  ['iso'=>'AT','name'=>'Austria','dial'=>'+43','flag'=>'🇦🇹'],
  ['iso'=>'BE','name'=>'Bélgica','dial'=>'+32','flag'=>'🇧🇪'],
  ['iso'=>'BO','name'=>'Bolivia','dial'=>'+591','flag'=>'🇧🇴'],
  ['iso'=>'BR','name'=>'Brasil','dial'=>'+55','flag'=>'🇧🇷'],
  ['iso'=>'CA','name'=>'Canadá','dial'=>'+1','flag'=>'🇨🇦'],
  ['iso'=>'CL','name'=>'Chile','dial'=>'+56','flag'=>'🇨🇱'],
  ['iso'=>'CN','name'=>'China','dial'=>'+86','flag'=>'🇨🇳'],
  ['iso'=>'CO','name'=>'Colombia','dial'=>'+57','flag'=>'🇨🇴'],
  ['iso'=>'CR','name'=>'Costa Rica','dial'=>'+506','flag'=>'🇨🇷'],
  ['iso'=>'CU','name'=>'Cuba','dial'=>'+53','flag'=>'🇨🇺'],
  ['iso'=>'DK','name'=>'Dinamarca','dial'=>'+45','flag'=>'🇩🇰'],
  ['iso'=>'EC','name'=>'Ecuador','dial'=>'+593','flag'=>'🇪🇨'],
  ['iso'=>'SV','name'=>'El Salvador','dial'=>'+503','flag'=>'🇸🇻'],
  ['iso'=>'ES','name'=>'España','dial'=>'+34','flag'=>'🇪🇸'],
  ['iso'=>'US','name'=>'Estados Unidos','dial'=>'+1','flag'=>'🇺🇸'],
  ['iso'=>'FR','name'=>'Francia','dial'=>'+33','flag'=>'🇫🇷'],
  ['iso'=>'IT','name'=>'Italia','dial'=>'+39','flag'=>'🇮🇹'],
  ['iso'=>'MX','name'=>'México','dial'=>'+52','flag'=>'🇲🇽'],
  ['iso'=>'PE','name'=>'Perú','dial'=>'+51','flag'=>'🇵🇪'],
  ['iso'=>'PT','name'=>'Portugal','dial'=>'+351','flag'=>'🇵🇹'],
  ['iso'=>'VE','name'=>'Venezuela','dial'=>'+58','flag'=>'🇻🇪'],
  // 👉 puedes ampliar a ISO completo sin cambiar nada más
];



?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro | KBPPAY</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/forms.css">
    
    <!-- PWA / App-like -->
<link rel="manifest" href="/app/pay/manifest.json">
<meta name="theme-color" content="#0A2540">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

<link rel="apple-touch-icon" sizes="180x180" href="/app/pay/assets/icons/icon-180.png">
<link rel="apple-touch-icon" sizes="167x167" href="/app/pay/assets/icons/icon-167.png">
<link rel="apple-touch-icon" sizes="152x152" href="/app/pay/assets/icons/icon-152.png">


</head>
<body>


<div class="auth-wrapper">
  <div class="auth-card">

  <div class="auth-logo">
  <img src="../assets/img/kbppay-logo.svg" alt="KBPPAY">
</div>

    <!-- Progress -->
    <div class="progress">
      <div class="progress-bar" style="width: 20%;"></div>
    </div>

    <h2>Crea tu cuenta</h2>
    <p>Empieza a enviar dinero de forma rápida y segura.</p>

    <?php if ($error): ?>
      <p style="color:#dc2626; font-size:14px; margin-bottom:16px;">
        <?= htmlspecialchars($error) ?>
      </p>
    <?php endif; ?>

    <form action="register.php" method="POST">

  <div class="form-group">
    <label for="first_name">Nombre</label>
    <input
      type="text"
      id="first_name"
      name="first_name"
      required
      value="<?= htmlspecialchars($old['first_name'] ?? '') ?>">
  </div>

  <div class="form-group">
    <label for="last_name">Apellido</label>
    <input
      type="text"
      id="last_name"
      name="last_name"
      required
      value="<?= htmlspecialchars($old['last_name'] ?? '') ?>">
  </div>

  <div class="form-group">
    <label for="email">Email</label>
    <input
      type="email"
      id="email"
      name="email"
      required
      value="<?= htmlspecialchars($old['email'] ?? '') ?>">
  </div>
  
<div class="form-group">
  <label for="email_confirm">Repite tu email</label>
  <input
    type="email"
    id="email_confirm"
    name="email_confirm"
    required
    autocomplete="off">
  <div class="form-feedback" id="email-feedback"></div>
</div>



  <div class="form-group">
    <label for="password">Contraseña</label>
    <input
      type="password"
      id="password"
      name="password"
      required
      minlength="8">

  </div>

<div class="form-group">
  <label for="password_confirm">Repite tu contraseña</label>
  <input
    type="password"
    id="password_confirm"
    name="password_confirm"
    required
    minlength="8">
    <div class="form-feedback" id="password-feedback"></div>

</div>


<div class="form-group">
  <label for="country">Nacionalidad</label>
  <select id="country" name="country" required>
    <option value="">Selecciona tu país</option>
    <?php foreach ($countries as $c): ?>
      <option value="<?= $c['iso'] ?>"
        <?= (($old['country'] ?? '') === $c['iso']) ? 'selected' : '' ?>>
        <?= $c['flag'] ?> <?= $c['name'] ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>




<div class="form-group">
  <label>Teléfono móvil</label>

  <div class="phone-group">
    <select id="phone_country">
      <?php foreach ($countries as $c): ?>
        <option value="<?= $c['dial'] ?>">
          <?= $c['flag'] ?> <?= $c['iso'] ?> <?= $c['dial'] ?>
        </option>
      <?php endforeach; ?>
    </select>

    <input
      type="tel"
      id="phone_number"
      placeholder="Número de teléfono"
      required>
  </div>

  <!-- Campo final que usa el backend -->
  <input type="hidden" name="phone" id="phone">
</div>



  <div class="form-group">
    <label for="birthdate">Fecha de nacimiento</label>
    <input
      type="date"
      id="birthdate"
      name="birthdate"
      required
      value="<?= htmlspecialchars($old['birthdate'] ?? '') ?>">
  </div>

<div class="form-group checkbox-group">
  <label class="checkbox-label">
    <input type="checkbox" name="accept_policies" value="1" required>
    <span>
      Acepto los <a href="/legal/privacidad" target="_blank">términos de tratamiento de datos personales</a> y confirmo que estoy de acuerdo con las <a href="/legal/divisas" target="_blank">políticas de la empresa para el manejo de divisas</a>.
    </span>
  </label>
</div>


<button type="submit" id="submit-btn" disabled>Continuar</button>

</form>

  </div>
 
</div>



<script>
  const email = document.getElementById('email');
  const emailConfirm = document.getElementById('email_confirm');
  const emailFeedback = document.getElementById('email-feedback');

  const password = document.getElementById('password');
  const passwordConfirm = document.getElementById('password_confirm');
  const passwordFeedback = document.getElementById('password-feedback');

  const submitBtn = document.getElementById('submit-btn');

  function validateEmailMatch() {
    if (!emailConfirm.value) {
      emailFeedback.textContent = '';
      emailFeedback.className = 'form-feedback';
      return false;
    }

    if (email.value === emailConfirm.value) {
      emailFeedback.textContent = 'Los emails coinciden';
      emailFeedback.className = 'form-feedback success';
      return true;
    } else {
      emailFeedback.textContent = 'Los emails no coinciden';
      emailFeedback.className = 'form-feedback error';
      return false;
    }
  }

  function validatePasswordMatch() {
    if (!passwordConfirm.value) {
      passwordFeedback.textContent = '';
      passwordFeedback.className = 'form-feedback';
      return false;
    }

    if (password.value === passwordConfirm.value) {
      passwordFeedback.textContent = 'Las contraseñas coinciden';
      passwordFeedback.className = 'form-feedback success';
      return true;
    } else {
      passwordFeedback.textContent = 'Las contraseñas no coinciden';
      passwordFeedback.className = 'form-feedback error';
      return false;
    }
  }

  function validateForm() {
    const emailOk = validateEmailMatch();
    const passwordOk = validatePasswordMatch();

    submitBtn.disabled = !(emailOk && passwordOk);
  }

  email.addEventListener('input', validateForm);
  emailConfirm.addEventListener('input', validateForm);
  password.addEventListener('input', validateForm);
  passwordConfirm.addEventListener('input', validateForm);
</script>


<script>
  const phoneCountry = document.getElementById('phone_country');
  const phoneNumber  = document.getElementById('phone_number');
  const phoneHidden  = document.getElementById('phone');

  function updatePhone() {
    phoneHidden.value = phoneCountry.value + phoneNumber.value.replace(/\s+/g, '');
  }

  phoneCountry.addEventListener('change', updatePhone);
  phoneNumber.addEventListener('input', updatePhone);
</script>


</body>
</html>
