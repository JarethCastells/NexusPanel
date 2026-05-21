<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/security.php';

// Si ya esta logueado, redirigir
if (estaLogueado()) {
    // Redirigir segun rol
            $destino = 'pages/dashboard.php';
            if ($_SESSION['rol'] === 'cliente') $destino = 'pages/tienda.php';
            if ($_SESSION['rol'] === 'operador') $destino = 'pages/operador_pedidos.php';
            if (in_array($_SESSION['rol'], ['inventario', 'distribucion'], true)) $destino = 'pages/pedidos.php';
            header('Location: ' . $destino);
    exit;
}

$error = '';
$success = '';
$turnstileEnabled = securityTurnstileEnabled();

if (isset($_GET['msg']) && $_GET['msg'] === 'logout') {
    $success = 'Sesion cerrada correctamente.';
}

// Proteccion contra fuerza bruta
$maxIntentos = 5;
$ventana     = 15 * 60; // 15 minutos
$ipKey       = 'login_intentos_' . md5($_SERVER['REMOTE_ADDR'] ?? 'local');
$intentos    = (int)($_SESSION[$ipKey . '_count'] ?? 0);
$primerIntento = (int)($_SESSION[$ipKey . '_time']  ?? 0);

// Resetear si paso la ventana
if ($primerIntento && (time() - $primerIntento) > $ventana) {
    $_SESSION[$ipKey . '_count'] = 0;
    $_SESSION[$ipKey . '_time']  = 0;
    $intentos = 0;
}

$bloqueado = $intentos >= $maxIntentos;

$ipMax = defined('LOGIN_RATE_LIMIT_IP_MAX') ? (int)LOGIN_RATE_LIMIT_IP_MAX : 20;
$ipWindow = defined('LOGIN_RATE_LIMIT_IP_WINDOW') ? (int)LOGIN_RATE_LIMIT_IP_WINDOW : 60;
$ipBlock = defined('LOGIN_RATE_LIMIT_IP_BLOCK') ? (int)LOGIN_RATE_LIMIT_IP_BLOCK : 180;
$emailMax = defined('LOGIN_RATE_LIMIT_EMAIL_MAX') ? (int)LOGIN_RATE_LIMIT_EMAIL_MAX : 8;
$emailWindow = defined('LOGIN_RATE_LIMIT_EMAIL_WINDOW') ? (int)LOGIN_RATE_LIMIT_EMAIL_WINDOW : 300;
$emailBlock = defined('LOGIN_RATE_LIMIT_EMAIL_BLOCK') ? (int)LOGIN_RATE_LIMIT_EMAIL_BLOCK : 300;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $remoteIp = securityClientIp();
    $ipBucket = 'login:ip:' . hash('sha256', $remoteIp);
    $emailBucket = '';

    $ipRate = securityRateLimitCheck($pdo, $ipBucket, $ipMax, $ipWindow, $ipBlock);

    if (!$ipRate['allowed']) {
        $wait = max(1, (int)$ipRate['retry_after']);
        $error = "Demasiadas solicitudes. Espera {$wait} segundo(s) e intenta de nuevo.";

    } elseif ($bloqueado) {
        $mins = ceil(($ventana - (time() - $primerIntento)) / 60);
        $error = "Demasiados intentos fallidos. Espera {$mins} minuto(s) e intenta de nuevo.";

    } elseif (empty($email) || empty($password)) {
        $error = 'Por favor completa todos los campos.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El formato del correo no es valido.';

    } elseif (strlen($password) < 4) {
        $error = 'La contrasena es demasiado corta.';

    } else {
        $emailBucket = 'login:email:' . hash('sha256', $email);
        $emailRate = securityRateLimitCheck($pdo, $emailBucket, $emailMax, $emailWindow, $emailBlock);

        if (!$emailRate['allowed']) {
            $wait = max(1, (int)$emailRate['retry_after']);
            $error = "Esta cuenta tiene demasiados intentos. Espera {$wait} segundo(s).";
        } else {
            $captcha = securityVerifyTurnstile($_POST['cf-turnstile-response'] ?? '', $remoteIp);
            if (!$captcha['ok']) {
                $error = $captcha['error'];
            } else {
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE LOWER(email) = ? AND activo = 1");
                $stmt->execute([$email]);
                $usuario = $stmt->fetch();

                if ($usuario && password_verify($password, $usuario['password'])) {
                    // Login OK, resetear intentos y throttles
                    $_SESSION[$ipKey . '_count'] = 0;
                    $_SESSION[$ipKey . '_time'] = 0;
                    securityRateLimitReset($pdo, $ipBucket);
                    securityRateLimitReset($pdo, $emailBucket);

                    $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")
                        ->execute([$usuario['id']]);

                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['nombre'] = $usuario['nombre'];
                    $_SESSION['email'] = $usuario['email'];
                    $_SESSION['rol'] = $usuario['rol'];
                    $_SESSION['domicilio'] = $usuario['domicilio'] ?? '';
                    $_SESSION['telefono'] = $usuario['telefono'] ?? '';
                    $_SESSION['lat'] = $usuario['lat'] ?? null;
                    $_SESSION['lng'] = $usuario['lng'] ?? null;
                    $_SESSION['zona_radio'] = $usuario['zona_radio'] ?? 50;

                    $destino = 'pages/dashboard.php';
                    if ($_SESSION['rol'] === 'cliente') $destino = 'pages/tienda.php';
                    if ($_SESSION['rol'] === 'operador') $destino = 'pages/operador_pedidos.php';
                    if (in_array($_SESSION['rol'], ['inventario', 'distribucion'], true)) $destino = 'pages/pedidos.php';
                    header('Location: ' . $destino);
                    exit;
                }

                // Incrementar intentos fallidos
                if (!$primerIntento) $_SESSION[$ipKey . '_time'] = time();
                $_SESSION[$ipKey . '_count'] = $intentos + 1;
                $restantes = $maxIntentos - ($intentos + 1);

                // Penalizacion breve por intento fallido (anti-fuerza bruta)
                usleep(350000);

                if ($restantes <= 0) {
                    $error = "Cuenta bloqueada temporalmente por $maxIntentos intentos fallidos. Intenta en 15 minutos.";
                    $bloqueado = true;
                } elseif (!$usuario) {
                    $error = 'No existe una cuenta con ese correo.';
                } else {
                    $error = "Contrasena incorrecta. Te quedan {$restantes} intento(s).";
                }
            }
        }
    }
}?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Iniciar Sesion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="assets/js/theme.js"></script>
    <?php if ($turnstileEnabled): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>

<!-- Fondo animado -->
<div class="bg-scene">
    <div class="grid-overlay"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    <div class="particles" id="particles"></div>
</div>

<div class="login-wrapper">
    <!-- Panel izquierdo - branding -->
    <div class="brand-panel">
        <div class="brand-content">
            <div class="brand-logo">
                <div class="logo-icon">
                    <i class="fa-solid fa-hexagon-nodes"></i>
                </div>
                <span class="logo-text">Nexus<strong>Panel</strong></span>
            </div>

            <div class="brand-tagline">
                <h1>Sistema de<br><span class="highlight">Gestion</span><br>Centralizado</h1>
                <p>Plataforma segura para administracion y operaciones con control total de accesos y roles.</p>
            </div>

            <div class="brand-features">
                <div class="feature-item">
                    <div class="feature-dot"></div>
                    <span>Control de roles Administrator / Operador</span>
                </div>
                <div class="feature-item">
                    <div class="feature-dot"></div>
                    <span>Sesiones seguras con autenticacion</span>
                </div>
                <div class="feature-item">
                    <div class="feature-dot"></div>
                    <span>Panel de gestion de usuarios</span>
                </div>
            </div>

            <div class="brand-stats">
                <div class="stat">
                    <span class="stat-number" data-target="99">0</span>
                    <span class="stat-suffix">%</span>
                    <span class="stat-label">Uptime</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat">
                    <span class="stat-number" data-target="256">0</span>
                    <span class="stat-suffix">bit</span>
                    <span class="stat-label">Encriptacion</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat">
                    <span class="stat-number" data-target="24">0</span>
                    <span class="stat-suffix">/7</span>
                    <span class="stat-label">Soporte</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel derecho - formulario -->
    <div class="form-panel">
        <div class="form-container">
            <div class="form-header">
                <div class="form-badge">
                    <span class="status-dot"></span>
                    Sistema en linea
                </div>
                <h2>Bienvenido de vuelta</h2>
                <p>Ingresa tus credenciales para acceder al panel</p>
            </div>

            <?php if ($error): ?>
            <div class="alert-custom alert-error" id="alertBox">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
                <button type="button" class="alert-close" onclick="dismissAlert()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert-custom alert-success" id="alertBox">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" id="loginForm" novalidate>
                <div class="input-group-custom">
                    <label for="email">Correo electronico</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-envelope input-icon"></i>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input"
                            placeholder="usuario@empresa.com"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            autocomplete="email"
                            required
                        >
                        <div class="input-focus-bar"></div>
                    </div>
                </div>

                <div class="input-group-custom">
                    <label for="password">Contrasena</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="********"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fa-solid fa-eye" id="eyeIcon"></i>
                        </button>
                        <div class="input-focus-bar"></div>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-custom">
                        <input type="checkbox" name="remember">
                        <span class="checkmark"></span>
                        <span>Recordarme</span>
                    </label>
                </div>

                <?php if ($turnstileEnabled): ?>
                <div style="margin: 10px 0 16px 0;">
                    <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars(LOGIN_TURNSTILE_SITE_KEY) ?>"></div>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn-login" id="submitBtn" <?= $bloqueado ? 'disabled' : '' ?>>
                    <span class="btn-text">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <?= $bloqueado ? 'Cuenta bloqueada' : 'Iniciar sesion' ?>
                    </span>
                    <span class="btn-loading" style="display:none;">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                        Verificando...
                    </span>
                </button>
            </form>



            <div class="form-footer">
                <span>NexusPanel</span>
                <span>Plataforma de gestion</span>
            </div>
        </div>

<button class="theme-toggle-floating" onclick="toggleTheme()" title="Cambiar tema">
    <i class="fa-solid fa-moon theme-toggle-icon"></i>
</button>

<style>
.theme-toggle-floating {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--bg-card);
    border: 1px solid var(--border);
    color: var(--text-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 1000;
    box-shadow: var(--card-shadow);
    transition: all 0.3s ease;
}
.theme-toggle-floating:hover {
    transform: scale(1.1);
    border-color: var(--accent);
}
</style>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/login.js"></script>
</body>
</html>

