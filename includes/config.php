<?php
// Cargar .env local simple (KEY=VALUE) si existe.
$envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (is_file($envPath) && is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        if ($key === '') {
            continue;
        }
        $val = trim($val, " \t\n\r\0\x0B\"'");
        $_ENV[$key] = $val;
        putenv($key . '=' . $val);
    }
}

function envv(string $key, $default = null) {
    $v = getenv($key);
    if ($v === false || $v === '') return $default;
    return $v;
}

// =============================================
// CONFIGURACION DE BASE DE DATOS - MySQL
// Edita estos valores antes de usar el sistema
// =============================================

define('DB_HOST', (string)envv('DB_HOST', 'localhost'));
define('DB_PORT', (string)envv('DB_PORT', '3306'));
define('DB_NAME', (string)envv('DB_NAME', 'nexuspanel')); // Nombre de tu BD local
define('DB_USER', (string)envv('DB_USER', 'root'));       // Usuario MySQL local
define('DB_PASS', (string)envv('DB_PASS', ''));           // Contrasena MySQL local
define('DB_CHARSET', (string)envv('DB_CHARSET', 'utf8mb4'));


// Clave para firmar links publicos de tracking (cambiala en produccion).
define('TRACKING_SHARE_SECRET', 'cambia-esta-clave-super-larga-en-produccion');

// URL base publica para links compartidos (sin slash final).
// Ejemplo LAN: http://192.168.1.50/login-demo
// Ejemplo internet: https://tudominio.com/login-demo
define('APP_PUBLIC_BASE_URL', (string)envv('APP_PUBLIC_BASE_URL', 'http://localhost:8080'));

// Turnstile desactivado para la demo estable (evita dependencia externa).
// Si luego lo reactivas, coloca aqui tus llaves reales.
define('LOGIN_TURNSTILE_SITE_KEY', '');
define('LOGIN_TURNSTILE_SECRET_KEY', '');

// Throttling de login contra flood/DDOS.
define('LOGIN_RATE_LIMIT_IP_MAX', 20);       // intentos por ventana
define('LOGIN_RATE_LIMIT_IP_WINDOW', 60);    // segundos de ventana
define('LOGIN_RATE_LIMIT_IP_BLOCK', 180);    // bloqueo temporal (seg)
define('LOGIN_RATE_LIMIT_EMAIL_MAX', 8);     // intentos por correo por ventana
define('LOGIN_RATE_LIMIT_EMAIL_WINDOW', 300);
define('LOGIN_RATE_LIMIT_EMAIL_BLOCK', 300);

// Configuracion de correo (fase 1: lectura IMAP + envio SMTP).
// IMPORTANTE: usar cuenta tecnica dedicada en produccion.
define('MAIL_IMAP_HOST', (string)envv('MAIL_IMAP_HOST', 'mail.teotek.com.mx'));
define('MAIL_IMAP_PORT', (int)envv('MAIL_IMAP_PORT', 993));
define('MAIL_IMAP_SECURE', filter_var((string)envv('MAIL_IMAP_SECURE', 'true'), FILTER_VALIDATE_BOOLEAN));
define('MAIL_IMAP_USER', (string)envv('MAIL_IMAP_USER', 'demianromero@teotek.com.mx'));
define('MAIL_IMAP_PASS', (string)envv('MAIL_IMAP_PASS', ''));
define('MAIL_IMAP_MAILBOX', (string)envv('MAIL_IMAP_MAILBOX', 'INBOX'));
define('MAIL_IMAP_ONLY_UNSEEN', filter_var((string)envv('MAIL_IMAP_ONLY_UNSEEN', 'true'), FILTER_VALIDATE_BOOLEAN));

define('MAIL_SMTP_HOST', (string)envv('MAIL_SMTP_HOST', 'mail.teotek.com.mx'));
define('MAIL_SMTP_PORT', (int)envv('MAIL_SMTP_PORT', 465));
define('MAIL_SMTP_SECURE', (string)envv('MAIL_SMTP_SECURE', 'ssl'));
define('MAIL_SMTP_USER', (string)envv('MAIL_SMTP_USER', 'demianromero@teotek.com.mx'));
define('MAIL_SMTP_PASS', (string)envv('MAIL_SMTP_PASS', ''));

// IA local para extraccion asistida de pedidos desde correos.
// Primera etapa: solo prellenado inteligente revisado por administrador.
define('OLLAMA_BASE_URL', rtrim((string)envv('OLLAMA_BASE_URL', 'http://localhost:11434'), '/'));
define('OLLAMA_MODEL', (string)envv('OLLAMA_MODEL', 'llama3.2:3b'));
