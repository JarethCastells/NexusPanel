<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (estaLogueado()) {
    header('Location: pages/dashboard.php');
    exit;
}

$error   = '';
$success = '';
$form    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'nombre'    => trim($_POST['nombre']    ?? ''),
        'domicilio' => trim($_POST['domicilio'] ?? ''),
        'email'     => trim($_POST['email']     ?? ''),
        'edad'      => trim($_POST['edad']      ?? ''),
        'telefono'  => trim($_POST['telefono']  ?? ''),
        'password'  => $_POST['password']       ?? '',
        'confirmar' => $_POST['confirmar']      ?? '',
        'lat'       => $_POST['lat']            ?? '',
        'lng'       => $_POST['lng']            ?? '',
    ];

    if (!$form['nombre'] || !$form['domicilio'] || !$form['email'] || !$form['edad'] || !$form['telefono'] || !$form['password']) {
        $error = 'Todos los campos son obligatorios.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico no es válido.';
    } elseif (!is_numeric($form['edad']) || $form['edad'] < 1 || $form['edad'] > 120) {
        $error = 'La edad debe ser un número válido.';
    } elseif (!preg_match('/^[0-9+\-\s()]{7,15}$/', $form['telefono'])) {
        $error = 'El número telefónico no es válido.';
    } elseif (strlen($form['password']) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($form['password'] !== $form['confirmar']) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $hash = password_hash($form['password'], PASSWORD_DEFAULT);
            $lat  = is_numeric($form['lat']) ? (float)$form['lat'] : null;
            $lng  = is_numeric($form['lng']) ? (float)$form['lng'] : null;

            $pdo->prepare("INSERT INTO usuarios (nombre, domicilio, email, edad, telefono, password, rol, lat, lng) VALUES (?,?,?,?,?,?,'cliente',?,?)")
                ->execute([$form['nombre'], $form['domicilio'], $form['email'], (int)$form['edad'], $form['telefono'], $hash, $lat, $lng]);

            $success = '¡Registro exitoso! Ya puedes iniciar sesión.';
            $form = [];
        } catch (PDOException $e) {
            $error = 'El correo electrónico ya está registrado.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel — Crear Cuenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="assets/css/registro.css">
</head>
<body>

<div class="bg-scene">
    <div class="grid-overlay"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    <div class="particles" id="particles"></div>
</div>

<div class="registro-wrapper">
    <div class="registro-header">
        <div class="brand-logo" style="justify-content:center;margin-bottom:0;">
            <div class="logo-icon"><i class="fa-solid fa-hexagon-nodes"></i></div>
            <span class="logo-text">Nexus<strong>Panel</strong></span>
        </div>
    </div>

    <div class="registro-card">
        <div class="registro-card-header">
            <div class="registro-icon"><i class="fa-solid fa-user-plus"></i></div>
            <div>
                <h2>Crear cuenta</h2>
                <p>Completa tu información para registrarte</p>
            </div>
        </div>

        <div class="steps-bar">
            <div class="step active" id="step-dot-1">
                <div class="step-dot"><i class="fa-solid fa-user"></i></div>
                <span>Personal</span>
            </div>
            <div class="step-line" id="line-1"></div>
            <div class="step" id="step-dot-2">
                <div class="step-dot"><i class="fa-solid fa-location-dot"></i></div>
                <span>Ubicación</span>
            </div>
            <div class="step-line" id="line-2"></div>
            <div class="step" id="step-dot-3">
                <div class="step-dot"><i class="fa-solid fa-lock"></i></div>
                <span>Seguridad</span>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="alert-custom alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert-custom alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <div>
                <strong><?= htmlspecialchars($success) ?></strong><br>
                <a href="index.php" style="color:inherit;text-decoration:underline;font-size:13px;">Ir al inicio de sesión →</a>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" id="registroForm" novalidate>
            <input type="hidden" name="lat" id="inputLat" value="<?= htmlspecialchars($form['lat'] ?? '') ?>">
            <input type="hidden" name="lng" id="inputLng" value="<?= htmlspecialchars($form['lng'] ?? '') ?>">

            <!-- PASO 1 -->
            <div class="form-step" id="step-1">
                <div class="step-title">
                    <span class="step-num">01</span>
                    <span>Datos Personales</span>
                </div>
                <div class="fields-grid">
                    <div class="input-group-custom full">
                        <label for="nombre"><i class="fa-solid fa-id-card"></i> Nombre completo</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user input-icon"></i>
                            <input type="text" id="nombre" name="nombre" class="form-input"
                                placeholder="Ej: Juan Carlos Pérez López"
                                value="<?= htmlspecialchars($form['nombre'] ?? '') ?>" autocomplete="name">
                            <div class="input-focus-bar"></div>
                        </div>
                    </div>
                    <div class="input-group-custom">
                        <label for="edad"><i class="fa-solid fa-cake-candles"></i> Edad</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-hashtag input-icon"></i>
                            <input type="number" id="edad" name="edad" class="form-input"
                                placeholder="25" min="1" max="120"
                                value="<?= htmlspecialchars($form['edad'] ?? '') ?>">
                            <div class="input-focus-bar"></div>
                        </div>
                    </div>
                    <div class="input-group-custom">
                        <label for="telefono"><i class="fa-solid fa-phone"></i> Número telefónico</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-phone input-icon"></i>
                            <input type="tel" id="telefono" name="telefono" class="form-input"
                                placeholder="55 1234 5678"
                                value="<?= htmlspecialchars($form['telefono'] ?? '') ?>" autocomplete="tel">
                            <div class="input-focus-bar"></div>
                        </div>
                    </div>
                    <div class="input-group-custom full">
                        <label for="email"><i class="fa-solid fa-envelope"></i> Correo electrónico</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-envelope input-icon"></i>
                            <input type="email" id="email" name="email" class="form-input"
                                placeholder="tu@correo.com"
                                value="<?= htmlspecialchars($form['email'] ?? '') ?>" autocomplete="email">
                            <div class="input-focus-bar"></div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-next" onclick="nextStep(1)">
                    Siguiente <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>

            <!-- PASO 2: Ubicación -->
            <div class="form-step hidden" id="step-2">
                <div class="step-title">
                    <span class="step-num">02</span>
                    <span>Tu Ubicación</span>
                </div>

                <div class="input-group-custom full" style="margin-bottom:14px;">
                    <label for="domicilio"><i class="fa-solid fa-house"></i> Domicilio</label>
                    <div class="domicilio-row">
                        <div style="flex:1;position:relative;">
                            <i class="fa-solid fa-location-dot input-icon"></i>
                            <input type="text" id="domicilio" name="domicilio" class="form-input"
                                placeholder="Calle, número, colonia, ciudad"
                                value="<?= htmlspecialchars($form['domicilio'] ?? '') ?>"
                                autocomplete="street-address">
                            <div class="input-focus-bar"></div>
                        </div>
                        <button type="button" id="btnGPS" class="btn-gps" onclick="detectarUbicacion()">
                            <i class="fa-solid fa-crosshairs" id="gpsIcon"></i>
                            <span id="gpsText">Mi ubicación</span>
                        </button>
                    </div>
                    <button type="button" class="btn-buscar" onclick="geocodeManual()">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Buscar esta dirección en el mapa
                    </button>
                </div>

                <div id="geoStatus" class="geo-status hidden"></div>

                <!-- Mini mapa -->
                <div class="mapa-container">
                    <div id="miniMapa"></div>
                    <div class="mapa-placeholder" id="mapaPlaceholder">
                        <i class="fa-solid fa-map-location-dot"></i>
                        <p>Escribe tu dirección o usa<br><strong>Mi ubicación</strong> para ver el mapa</p>
                    </div>
                    <div class="mapa-coords hidden" id="mapaCoords"></div>
                    <div class="mapa-tip hidden" id="mapaTip">
                        <i class="fa-solid fa-hand-pointer"></i>
                        Arrastra el pin para ajustar tu ubicación exacta
                    </div>
                </div>

                <div class="step-nav" style="margin-top:20px;">
                    <button type="button" class="btn-back" onclick="prevStep(2)">
                        <i class="fa-solid fa-arrow-left"></i> Atrás
                    </button>
                    <button type="button" class="btn-next" onclick="nextStep(2)">
                        Siguiente <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- PASO 3: Seguridad -->
            <div class="form-step hidden" id="step-3">
                <div class="step-title">
                    <span class="step-num">03</span>
                    <span>Seguridad de la Cuenta</span>
                </div>
                <div class="fields-grid">
                    <div class="input-group-custom full">
                        <label for="password"><i class="fa-solid fa-lock"></i> Contraseña</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" id="password" name="password" class="form-input"
                                placeholder="Mínimo 6 caracteres" autocomplete="new-password">
                            <button type="button" class="toggle-password" onclick="togglePass('password','eye1')">
                                <i class="fa-solid fa-eye" id="eye1"></i>
                            </button>
                            <div class="input-focus-bar"></div>
                        </div>
                        <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                        <div class="strength-label" id="strengthLabel"></div>
                    </div>
                    <div class="input-group-custom full">
                        <label for="confirmar"><i class="fa-solid fa-shield-check"></i> Confirmar contraseña</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" id="confirmar" name="confirmar" class="form-input"
                                placeholder="Repite tu contraseña" autocomplete="new-password">
                            <button type="button" class="toggle-password" onclick="togglePass('confirmar','eye2')">
                                <i class="fa-solid fa-eye" id="eye2"></i>
                            </button>
                            <div class="input-focus-bar"></div>
                        </div>
                        <div class="match-label" id="matchLabel"></div>
                    </div>
                </div>

                <div class="resumen-box">
                    <div class="resumen-title"><i class="fa-solid fa-list-check"></i> Resumen de registro</div>
                    <div class="resumen-grid">
                        <div class="resumen-item">
                            <span class="resumen-key">Nombre</span>
                            <span class="resumen-val" id="r-nombre">—</span>
                        </div>
                        <div class="resumen-item">
                            <span class="resumen-key">Edad</span>
                            <span class="resumen-val" id="r-edad">—</span>
                        </div>
                        <div class="resumen-item">
                            <span class="resumen-key">Teléfono</span>
                            <span class="resumen-val" id="r-tel">—</span>
                        </div>
                        <div class="resumen-item">
                            <span class="resumen-key">Correo</span>
                            <span class="resumen-val" id="r-email">—</span>
                        </div>
                        <div class="resumen-item full">
                            <span class="resumen-key">Domicilio</span>
                            <span class="resumen-val" id="r-dom">—</span>
                        </div>
                        <div class="resumen-item full">
                            <span class="resumen-key">📍 Coordenadas GPS</span>
                            <span class="resumen-val" id="r-ubic" style="font-family:var(--font-mono);font-size:12px;">Sin ubicación</span>
                        </div>
                    </div>
                </div>

                <div class="step-nav">
                    <button type="button" class="btn-back" onclick="prevStep(3)">
                        <i class="fa-solid fa-arrow-left"></i> Atrás
                    </button>
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span class="btn-text">
                            <i class="fa-solid fa-user-check"></i> Crear cuenta
                        </span>
                        <span class="btn-loading" style="display:none;">
                            <i class="fa-solid fa-spinner fa-spin"></i> Registrando...
                        </span>
                    </button>
                </div>
            </div>

        </form>

        <div class="registro-footer">
            ¿Ya tienes cuenta?
            <a href="index.php">Iniciar sesión <i class="fa-solid fa-arrow-right"></i></a>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/login.js"></script>
<script src="assets/js/registro.js"></script>
</body>
</html>
