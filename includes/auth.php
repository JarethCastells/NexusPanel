<?php
if (session_status() === PHP_SESSION_NONE) {
    $fallbackSessionDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($fallbackSessionDir)) {
        @mkdir($fallbackSessionDir, 0777, true);
    }
    if (is_dir($fallbackSessionDir) && is_writable($fallbackSessionDir)) {
        @session_save_path($fallbackSessionDir);
    }
    @session_start();
}

function estaLogueado()  { return isset($_SESSION['usuario_id']); }
function usuarioActual() { return $_SESSION ?? []; }
function esAdmin()    { return ($_SESSION['rol'] ?? '') === 'administrador'; }
function esOperador() { return ($_SESSION['rol'] ?? '') === 'operador'; }
function esCliente()  { return ($_SESSION['rol'] ?? '') === 'cliente'; }
function esInventario() { return in_array(($_SESSION['rol'] ?? ''), ['inventario', 'distribucion'], true); }
function esGestion() { return esAdmin() || esInventario(); }

function nombreRolActual(): string {
    $rol = $_SESSION['rol'] ?? '';
    return match ($rol) {
        'administrador' => 'Administrador',
        'operador' => 'Operador',
        'cliente' => 'Cliente',
        'inventario', 'distribucion' => 'Manager',
        default => ucfirst($rol),
    };
}

function requireAuth() {
    if (!estaLogueado()) {
        header('Location: ../index.php');
        exit;
    }
}

function requireAdmin() {
    requireAuth();
    if (!esAdmin()) {
        header('Location: dashboard.php?error=acceso_denegado');
        exit;
    }
}

function requireGestion() {
    requireAuth();
    if (!esGestion()) {
        header('Location: dashboard.php?error=acceso_denegado');
        exit;
    }
}

function requireOperador() {
    requireAuth();
    if (!esOperador() && !esAdmin()) {
        header('Location: dashboard.php?error=acceso_denegado');
        exit;
    }
}

function logout() {
    session_destroy();
    header('Location: index.php');
    exit;
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
