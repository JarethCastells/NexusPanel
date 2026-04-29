<?php
// =============================================
// CONFIGURACION DE BASE DE DATOS - MySQL
// Edita estos valores antes de usar el sistema
// =============================================

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'nexuspanel');   // Nombre de tu BD local
define('DB_USER', 'root');         // Usuario MySQL local
define('DB_PASS', '');             // Contrasena MySQL local
define('DB_CHARSET', 'utf8mb4');


// Clave para firmar links publicos de tracking (cambiala en produccion).
define('TRACKING_SHARE_SECRET', 'cambia-esta-clave-super-larga-en-produccion');

// URL base publica para links compartidos (sin slash final).
// Ejemplo LAN: http://192.168.1.50/login-demo
// Ejemplo internet: https://tudominio.com/login-demo
define('APP_PUBLIC_BASE_URL', 'http://localhost/login-demo');

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
