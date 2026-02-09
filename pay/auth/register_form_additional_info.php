<?php
// auth/register_form_additional_info.php
session_start();
$old = $_POST ?? [];
require_once __DIR__ . '/../config/database.php';

// Seguridad mínima
if (!isset($_SESSION['user_id'])) {
    die('Acceso no autorizado');
}

$userId = (int) $_SESSION['user_id'];

$errors = [];

// --------- Procesamiento POST -----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Normalizar y recoger
    $residence_country = strtoupper(trim($_POST['residence_country'] ?? ''));
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $id_type = trim($_POST['id_type'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');


    // Validaciones servidor (clara y específica)
    $errors = [];

    $allowed_countries = ['ES','US','OT'];
if ($residence_country === 'OT') {
    $errors[] = 'En este momento solo puedes enviar dinero si resides en Estados Unidos o España.';
}

    if (!in_array($residence_country, $allowed_countries, true)) {
        $errors[] = 'País de residencia inválido.';
    }
    if ($address === '') $errors[] = 'Dirección requerida.';
    if ($city === '') $errors[] = 'Ciudad requerida.';
    if ($postal_code === '') $errors[] = 'Código postal requerido.';
    if ($id_type === '') $errors[] = 'Tipo de documento requerido.';
    if ($id_number === '') $errors[] = 'Número de documento requerido.';


    // Limitar longitudes para seguridad
    if (mb_strlen($address) > 255) $errors[] = 'Dirección demasiado larga.';

    if (!empty($errors)) {
        // mostramos errores en la misma página, abajo
    } else {
        // Guardar en users (UPDATE)
        $sql = "UPDATE users SET
         residence_country = :residence_country,
         address = :address,
         city = :city,
          postal_code = :postal_code,
          id_type = :id_type,
          id_number = :id_number
        WHERE id = :user_id
        LIMIT 1";



        $stmt = $pdo->prepare($sql);
        $stmt->execute([
    ':residence_country' => $residence_country,
    ':address' => $address,
    ':city' => $city,
    ':postal_code' => $postal_code,
    ':id_type' => $id_type,
    ':id_number' => $id_number,
    ':user_id' => $userId
    ]);


        // Redirigir al siguiente paso (crea este archivo si hace falta)
       header("Location: register_form_verificacion_kyc.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Información adicional | KBPPAY</title>
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
      <div class="progress-bar" style="width: 50%;"></div>
    </div>

    <h2>Completa tu información</h2>
    <p>
      Para cumplir con requisitos regulatorios necesitamos algunos datos adicionales
      sobre tu residencia y documento de identidad.
    </p>

    <?php if (!empty($errors)): ?>
      <p style="color:#dc2626; font-size:14px; margin-bottom:16px;">
        Corrige los campos marcados para poder continuar.
      </p>
    <?php endif; ?>

<form method="POST" action="" id="additionalForm">

<div class="form-group">
  <label for="residence_country">País de residencia</label>
  <select name="residence_country" id="residence_country" required>
    <option value="">Selecciona</option>

    <option value="US" <?= (($old['residence_country'] ?? '') === 'US') ? 'selected' : '' ?>>
      🇺🇸 Estados Unidos
    </option>

    <option value="ES" <?= (($old['residence_country'] ?? '') === 'ES') ? 'selected' : '' ?>>
      🇪🇸 España
    </option>

    <option value="OT" <?= (($old['residence_country'] ?? '') === 'OT') ? 'selected' : '' ?>>
      Otro país
    </option>
  </select>
  <p class="help" id="residence_help" style="display:none;">
  En este momento, KBPPAY solo permite enviar dinero desde
  <strong>Estados Unidos</strong> o <strong>España</strong>.
</p>

</div>



<div class="form-group">
  <label for="address">Dirección completa</label>
  <input
    type="text"
    name="address"
    id="address"
    placeholder="Calle, número, piso, etc."
    required
    value="<?= htmlspecialchars($old['address'] ?? '') ?>">
</div>


<div class="form-group">
  <label for="city">Ciudad</label>
  <input
    type="text"
    name="city"
    id="city"
    required
    value="<?= htmlspecialchars($old['city'] ?? '') ?>">
</div>


<div class="form-group">
  <label for="postal_code">Código postal</label>
  <input
    type="text"
    name="postal_code"
    id="postal_code"
    required
    value="<?= htmlspecialchars($old['postal_code'] ?? '') ?>">
</div>


<div class="form-group">
  <label for="id_type">Tipo de documento</label>
  <select name="id_type" id="id_type" required>
    <option value="">Selecciona</option>
  </select>
  <p class="help" id="id_help">
    Selecciona el país para ver opciones de documento.
  </p>
</div>

<div class="form-group">
  <label for="id_number">Número de documento</label>
  <input
    type="text"
    name="id_number"
    id="id_number"
    required
    value="<?= htmlspecialchars($old['id_number'] ?? '') ?>">
</div>




<button type="submit">Continuar</button>

    </form>

  </div>
</div>




<script>
// ===============================
// Tipos de documento por país
// ===============================
const idTypes = {
    "US": [
        { v: "Driving License", t: "Driving License / State ID" },
        { v: "Passport", t: "Passport" }
    ],
    "ES": [
        { v: "DNI", t: "DNI (Documento Nacional de Identidad)" },
        { v: "NIE", t: "NIE (Número de Identidad de Extranjero)" },
        { v: "Passport", t: "Passport" }
    ]
};

// ===============================
// Referencias DOM
// ===============================
const countrySel     = document.getElementById('residence_country');
const idTypeSel      = document.getElementById('id_type');
const idHelp         = document.getElementById('id_help');
const residenceHelp  = document.getElementById('residence_help');
const submitBtn      = document.querySelector('button[type="submit"]');

// ===============================
// Poblar tipos de documento
// ===============================
function populateIdTypes(country) {
    idTypeSel.innerHTML = '<option value="">Selecciona</option>';

    if (!idTypes[country]) {
        idTypeSel.innerHTML = '<option value="">No disponible</option>';
        idHelp.textContent = 'Selecciona Estados Unidos o España para continuar.';
        return;
    }

    idHelp.textContent = 'Selecciona el tipo de documento válido para tu país.';
    idTypes[country].forEach(function(item) {
        const o = document.createElement('option');
        o.value = item.v;
        o.textContent = item.t;
        idTypeSel.appendChild(o);
    });
}

// ===============================
// Cambio de país de residencia
// ===============================
countrySel.addEventListener('change', function () {
    const country = this.value;

    if (country === 'OT') {
        // Caso OTRO
        residenceHelp.style.display = 'block';
        idTypeSel.innerHTML = '<option value="">No disponible</option>';
        idHelp.textContent =
            'Actualmente KBPPAY solo permite enviar dinero desde Estados Unidos o España.';
        submitBtn.disabled = true;
        return;
    }

    // Caso válido (US / ES)
    residenceHelp.style.display = 'none';
    submitBtn.disabled = false;
    populateIdTypes(country);
});

// ===============================
// Estado inicial (por si hay POST previo)
// ===============================
if (countrySel.value) {
    if (countrySel.value === 'OT') {
        residenceHelp.style.display = 'block';
        submitBtn.disabled = true;
    } else {
        populateIdTypes(countrySel.value);
    }
}
</script>

</body>
</html>
