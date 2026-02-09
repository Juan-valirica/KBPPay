<?php
// auth/register_form_beneficiario.php
session_start();
$old = $_POST ?? [];
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: register_form.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $country = 'VE';
    $idType    = trim($_POST['id_type'] ?? '');
    $idNumber = trim($_POST['id_number'] ?? '');
    
    $bank      = trim($_POST['bank_name'] ?? '');

    $accountNumber = strtoupper(trim($_POST['account_number'] ?? ''));
    // Normalizar IBAN (quitar espacios)
    $accountNumber = str_replace(' ', '', $accountNumber);


    $account   = $_POST['account_type'] ?? '';
    $email     = trim($_POST['email'] ?? '');
    $reference = trim($_POST['payment_reference'] ?? '');
    $sendReason = trim($_POST['send_reason'] ?? '');
    $relation   = trim($_POST['relation_beneficiary'] ?? '');

    $favorite  = isset($_POST['is_favorite']) ? 1 : 0;

    $errors = [];

    // Nombre y apellido
if ($firstName === '') {
    $errors[] = 'Nombre requerido';
}
if ($lastName === '') {
    $errors[] = 'Apellido requerido';
}



// Tipo de documento
if ($idType === '') {
    $errors[] = 'Tipo de documento requerido';
}

if ($idNumber === '') {
    $errors[] = 'Número de documento requerido';
}
if (mb_strlen($idNumber) > 50) {
    $errors[] = 'Número de documento demasiado largo';
}


switch ($idType) {

    case 'VE_NATURAL':
    case 'VE_EXTRANJERO':
        if (!preg_match('/^\d{6,9}$/', $idNumber)) {
            $errors[] = 'Número de cédula inválido';
        }
        break;

    case 'PASSPORT':
        if (!preg_match('/^[A-Z0-9]{6,9}$/', $idNumber)) {
            $errors[] = 'Número de pasaporte inválido';
        }
        break;

    case 'VE_RIF':
        if (!preg_match('/^[VJGEP][0-9]{9}$/', $idNumber)) {
            $errors[] = 'RIF inválido';
        }
        break;

    case 'VE_GOB':
        if (mb_strlen($idNumber) < 5 || mb_strlen($idNumber) > 20) {
            $errors[] = 'Código del organismo inválido';
        }
        break;

    default:
        $errors[] = 'Tipo de documento inválido';
}



// Banco
$allowedBanks = [
  'Banco de Venezuela',
  'Banesco Banco Universal',
  'BBVA Provincial',
  'Banco Mercantil',
  'Banco Nacional de Crédito',
  'Banco Exterior',
  'Banco Plaza',
  'Banco Activo',
  'Banco Caroní',
  'Banco de la Gente Emprendedora (Bangente)',
  'Banco Sofitasa',
  'Banplus',
  'Del Sur Banco Universal',
  '100% Banco',
  'Bancamiga',
  'BanCrecer',
  'Banco del Tesoro',
  'Banco Digital de los Trabajadores',
  'Banco de Desarrollo Económico y Social (BANDES)',
  'Banco de la Fuerza Armada Nacional Bolivariana (BFANB)',
  'Banco Venezolano de Crédito',
  'Bancaribe'
];


if (!in_array($bank, $allowedBanks, true)) {
    $errors[] = 'Banco inválido';
}



// Número de cuenta (mínimo razonable)
if (!preg_match('/^\d{20}$/', $accountNumber)) {
    $errors[] = 'Número de cuenta venezolano inválido';
}


// Tipo de cuenta
if (!in_array($account, ['checking','savings'], true)) {
    $errors[] = 'Tipo de cuenta inválido';
}

// Email (opcional)
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email inválido';
}


// Referencia (opcional, pero limitada)
if ($reference !== '' && mb_strlen($reference) > 255) {
    $errors[] = 'La referencia es demasiado larga';
}

if ($sendReason === '') {
    $errors[] = 'Motivo del envío requerido';
}

if ($relation === '') {
    $errors[] = 'Relación con el beneficiario requerida';
}

if (mb_strlen($sendReason) > 255) {
    $errors[] = 'Motivo del envío demasiado largo';
}



    if (empty($errors)) {
        $stmt = $pdo->prepare("
    INSERT INTO beneficiaries
(user_id, first_name, last_name, residence_country, id_type, id_number,
 bank_name, account_number, account_type,
 email, payment_reference, send_reason, relation_beneficiary, is_favorite)


   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");


    $stmt->execute([
    $userId,
    $firstName,
    $lastName,
    $country,
    $idType,
    $idNumber,
    $bank,
    $accountNumber,
    $account,
    $email,
    $reference,
    $sendReason,
    $relation,
    $favorite
    ]);

$beneficiaryId = (int) $pdo->lastInsertId();
$_SESSION['beneficiary_id'] = $beneficiaryId;



        header('Location: register_form_metodo_pago.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Beneficiario | KBPPAY</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/forms.css">
  
  <!-- PWA / App-like -->
<link rel="manifest" href="/app/pay/manifest.json">
<meta name="theme-color" content="#0A2540">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  
</head>
<body>

<div class="auth-wrapper">
  <div class="auth-card">

<div class="auth-logo">
  <img src="../assets/img/kbppay-logo.svg" alt="KBPPAY">
</div>

    <!-- Progress -->
    <div class="progress">
      <div class="progress-bar" style="width: 90%;"></div>
    </div>

    <h2>Datos del beneficiario</h2>
    <p>Indica a quién deseas enviar el dinero.</p>

    
<?php if (!empty($errors)): ?>
  <div style="background:#fff1f2;color:#991b1b;padding:12px;border-radius:10px;margin-bottom:16px;font-size:14px;">
    <strong>Revisa lo siguiente:</strong>
    <ul style="margin:8px 0 0 18px;">
      <?php foreach ($errors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>


<form method="POST">

  <!-- Nombre -->
  <div class="form-group">
    <label>Nombre</label>
    <input
        type="text"
        name="first_name"
        required
        autofocus
        value="<?= htmlspecialchars($old['first_name'] ?? '') ?>">

  </div>

  <!-- Apellido -->
  <div class="form-group">
    <label>Apellido</label>
    <input
      type="text"
      name="last_name"
      required
      value="<?= htmlspecialchars($old['last_name'] ?? '') ?>">
  </div>

  <!-- País -->
<div class="form-group">
  <label>País de residencia</label>

  <!-- Campo visual -->
  <input
    type="text"
    value="Venezuela"
    disabled
    style="
      background:#f9fafb;
      color:#111827;
      font-weight:500;
      cursor:not-allowed;
    "
  >

  <!-- Valor real enviado -->
  <input type="hidden" name="residence_country" value="VE">
</div>


  <!-- Tipo de documento -->
  <div class="form-group">
    <label>Tipo de documento</label>
    <select name="id_type" id="id_type" required>
      <option value="">Selecciona el tipo de documento</option>
      <?php if (!empty($old['id_type'])): ?>
        <option value="<?= htmlspecialchars($old['id_type']) ?>" selected>
          <?= htmlspecialchars($old['id_type']) ?>
        </option>
      <?php endif; ?>
    </select>
  </div>
  
<div class="form-group">
  <label>Número de documento</label>
  <input
    type="text"
    name="id_number"
    id="id_number"
    required
    placeholder="Selecciona el tipo de documento"
    value="<?= htmlspecialchars($old['id_number'] ?? '') ?>">
</div>


  <!-- Banco -->
<div class="form-group">
  <label>Banco</label>
<select name="bank_name" id="bank_name" required>
  <option value="">Selecciona el banco</option>

  <option value="Banco de Venezuela">Banco de Venezuela</option>
  <option value="Banesco Banco Universal">Banesco Banco Universal</option>
  <option value="BBVA Provincial">BBVA Provincial</option>
  <option value="Banco Mercantil">Banco Mercantil</option>
  <option value="Banco Nacional de Crédito">Banco Nacional de Crédito (BNC)</option>
  <option value="Banco Exterior">Banco Exterior</option>
  <option value="Banco Plaza">Banco Plaza</option>
  <option value="Banco Activo">Banco Activo</option>
  <option value="Banco Caroní">Banco Caroní</option>
  <option value="Banco de la Gente Emprendedora (Bangente)">Banco de la Gente Emprendedora (Bangente)</option>
  <option value="Banco Sofitasa">Banco Sofitasa</option>
  <option value="Banplus">Banplus</option>
  <option value="Del Sur Banco Universal">Del Sur Banco Universal</option>
  <option value="100% Banco">100% Banco</option>
  <option value="Bancamiga">Bancamiga</option>
  <option value="BanCrecer">BanCrecer</option>
  <option value="Banco del Tesoro">Banco del Tesoro</option>
  <option value="Banco Digital de los Trabajadores">Banco Digital de los Trabajadores</option>
  <option value="Banco de Desarrollo Económico y Social (BANDES)">Banco de Desarrollo Económico y Social (BANDES)</option>
  <option value="Banco de la Fuerza Armada Nacional Bolivariana (BFANB)">Banco de la Fuerza Armada Nacional Bolivariana (BFANB)</option>
  <option value="Banco Venezolano de Crédito">Banco Venezolano de Crédito</option>
  <option value="Bancaribe">Bancaribe</option>
</select>

</div>





  <!-- IBAN / Número de cuenta -->
  <div class="form-group">
  <label>Número de cuenta</label>
  <input
    type="text"
    name="account_number"
    id="account_number"
    required
    placeholder="Ej: 0102 0421 65 1234567890"
    maxlength="23"
    value="<?= htmlspecialchars($old['account_number'] ?? '') ?>">
</div>


  <!-- Tipo de cuenta -->
  <div class="form-group">
    <label>Tipo de cuenta</label>
    <select name="account_type" required>
      <option value="">Selecciona</option>
      <option value="checking" <?= (($old['account_type'] ?? '') === 'checking') ? 'selected' : '' ?>>Cuenta corriente</option>
      <option value="savings" <?= (($old['account_type'] ?? '') === 'savings') ? 'selected' : '' ?>>Cuenta de ahorros</option>
    </select>
  </div>
  
  
  <!-- Motivo -->  
  <div class="form-group">
  <label>Motivo del envío</label>
  <select name="send_reason" required>
    <option value="">Selecciona</option>
    <option value="support_family">Apoyo familiar</option>
    <option value="payment_service">Pago por servicios</option>
    <option value="purchase">Compra de bienes</option>
    <option value="savings">Ahorro / transferencia personal</option>
    <option value="other">Otro</option>
  </select>
</div>

  <!-- Relación --> 
<div class="form-group">
  <label>Relación con el beneficiario</label>
  <select name="relation_beneficiary" required>
    <option value="">Selecciona</option>
    <option value="family">Familiar</option>
    <option value="friend">Amigo</option>
    <option value="business">Empresa / Cliente</option>
    <option value="other">Otro</option>
  </select>
</div>



  <!-- Referencia -->
  <div class="form-group">
    <label>Referencia del pago (opcional)</label>
    <input
      type="text"
      name="payment_reference"
      placeholder="Ej: Ayuda familiar, renta, estudios"
      value="<?= htmlspecialchars($old['payment_reference'] ?? '') ?>">
  </div>

  <!-- Email -->
  <div class="form-group">
    <label>Email del beneficiario</label>
    <input
      type="email"
      name="email"
      required
      value="<?= htmlspecialchars($old['email'] ?? '') ?>">
  </div>

  <!-- Favorito -->
<div class="form-group">

  <label
    for="is_favorite"
    style="
      display:flex;
      align-items:flex-start;
      gap:14px;
      padding:16px;
      border-radius:14px;
      border:1.5px solid #e5e7eb;
      background:#fafafa;
      cursor:pointer;
      transition:all .2s ease;
    "
    onmouseenter="this.style.borderColor='#3259fd';this.style.background='#f4f7ff';"
    onmouseleave="this.style.borderColor='#e5e7eb';this.style.background='#fafafa';"
  >

    <!-- Checkbox -->
    <input
      id="is_favorite"
      type="checkbox"
      name="is_favorite"
      <?= !empty($old['is_favorite']) ? 'checked' : '' ?>
      style="
        margin-top:4px;
        width:18px;
        height:18px;
        accent-color:#3259fd;
        cursor:pointer;
      "
    >

    <!-- Texto -->
    <div>
      <div style="font-weight:600; color:#111827;">
        ⭐️ Guardar beneficiario como favorito
      </div>

      <div style="font-size:13px; color:#6b7280; margin-top:4px;">
        Podrás seleccionarlo rápidamente en futuros envíos.
      </div>
    </div>

  </label>

</div>




  <button type="submit" onclick="this.disabled=true; this.form.submit();">
    Continuar
  </button>

</form>


  </div>
</div>

<script>
/* Tipos de documento por país */
const idTypes = {
  VE: [
    { value: 'VE_NATURAL', label: 'Venezolano' },
    { value: 'VE_EXTRANJERO', label: 'Extranjero' },
    { value: 'PASSPORT', label: 'Pasaporte' },
    { value: 'VE_RIF', label: 'Para empresas (RIF)' },
    { value: 'VE_GOB', label: 'Grupo social (Organismos del Estado)' }
  ]
};


const idTypeSel = document.getElementById('id_type');

/* Precargar opciones para Venezuela */
idTypeSel.innerHTML = '<option value="">Selecciona</option>';

idTypes.VE.forEach(opt => {
  const option = document.createElement('option');
  option.value = opt.value;
  option.textContent = opt.label;
  idTypeSel.appendChild(option);
});

</script>


<script>
const accountInput = document.getElementById('account_number');

accountInput.addEventListener('input', function () {
  // Eliminar todo lo que no sea número
  let value = this.value.replace(/\D/g, '');

  // Limitar a 20 dígitos
  value = value.substring(0, 20);

  // Aplicar formato venezolano: 4-4-2-10
  let formatted = '';
  if (value.length > 0) formatted += value.substring(0, 4);
  if (value.length >= 5) formatted += ' ' + value.substring(4, 8);
  if (value.length >= 9) formatted += ' ' + value.substring(8, 10);
  if (value.length >= 11) formatted += ' ' + value.substring(10, 20);

  this.value = formatted;
});
</script>


<script>
const idTypeSelect = document.getElementById('id_type');
const idNumberInput = document.getElementById('id_number');

function applyIdFormat() {
  const type = idTypeSelect.value;
  let value = idNumberInput.value;

  switch (type) {

    case 'VE_NATURAL':
      // Cédula venezolana: solo números, 6–9
      value = value.replace(/\D/g, '').substring(0, 9);
      idNumberInput.value = value;
      idNumberInput.placeholder = 'Ej: 12345678';
      idNumberInput.maxLength = 9;
      break;

    case 'VE_EXTRANJERO':
      // Cédula extranjero: solo números, 6–9
      value = value.replace(/\D/g, '').substring(0, 9);
      idNumberInput.value = value;
      idNumberInput.placeholder = 'Ej: 98765432';
      idNumberInput.maxLength = 9;
      break;

    case 'PASSPORT':
      // Pasaporte: alfanumérico
      value = value.replace(/[^a-zA-Z0-9]/g, '').substring(0, 9);
      idNumberInput.value = value.toUpperCase();
      idNumberInput.placeholder = 'Ej: A1234567';
      idNumberInput.maxLength = 9;
      break;

    case 'VE_RIF':
      // RIF: J123456789 / G123456789 / V123456789
      value = value.replace(/[^a-zA-Z0-9]/g, '').substring(0, 10);
      idNumberInput.value = value.toUpperCase();
      idNumberInput.placeholder = 'Ej: J123456789';
      idNumberInput.maxLength = 10;
      break;

    case 'VE_GOB':
      // Organismos: alfanumérico más largo
      value = value.replace(/[^a-zA-Z0-9\-]/g, '').substring(0, 20);
      idNumberInput.value = value.toUpperCase();
      idNumberInput.placeholder = 'Ej: MIN-012345';
      idNumberInput.maxLength = 20;
      break;

    default:
      idNumberInput.placeholder = 'Número del documento';
      idNumberInput.maxLength = 20;
  }
}


// Cambia formato al cambiar tipo
idTypeSelect.addEventListener('change', () => {
  idNumberInput.value = '';
  applyIdFormat();
});

// Aplica formato mientras escribe
idNumberInput.addEventListener('input', applyIdFormat);
</script>



</body>
</html>
