<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireAdmin();

$usuario = usuarioActual();
$modulo = trim((string)($_GET['modulo'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

$where = ["1=1"];
$params = [];
if ($modulo !== '') {
    $where[] = "a.modulo = ?";
    $params[] = $modulo;
}
if ($q !== '') {
    $where[] = "(u.nombre LIKE ? OR a.accion LIKE ? OR a.detalles LIKE ?)";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

$st = $pdo->prepare("
    SELECT a.*, u.nombre AS usuario_nombre
    FROM auditoria_eventos a
    LEFT JOIN usuarios u ON u.id = a.usuario_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY a.id DESC
    LIMIT 500
");
$st->execute($params);
$rows = $st->fetchAll();

$modulos = $pdo->query("SELECT DISTINCT modulo FROM auditoria_eventos ORDER BY modulo ASC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Auditoria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .toolbar { display:grid; grid-template-columns: 1.5fr 1fr auto; gap:10px; }
        .table-wrapper { overflow-x:auto; }
        .data-table { min-width:1200px; }
        @media (max-width: 900px) { .toolbar { grid-template-columns: 1fr; } }
    </style>
</head>
<body data-theme="<?= function_exists('temaActual') ? htmlspecialchars(temaActual()) : 'dark' ?>">
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo"><div class="logo-icon-sm"><i class="fa-solid fa-hexagon-nodes"></i></div><span class="logo-text-sm">Nexus<strong>Panel</strong></span></div>
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
    </div>
    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($usuario['nombre'], 0, 1)) ?></div>
        <div class="user-info"><span class="user-name"><?= htmlspecialchars($usuario['nombre']) ?></span><span class="user-role role-admin"><i class="fa-solid fa-shield-halved"></i> Administrador</span></div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="dashboard.php" class="nav-item"><i class="fa-solid fa-chart-line"></i><span>Inicio</span></a>
        <a href="usuarios.php" class="nav-item"><i class="fa-solid fa-users"></i><span>Usuarios</span></a>
        <a href="productos.php" class="nav-item"><i class="fa-solid fa-flask-vial"></i><span>Productos</span></a>
        <a href="pedidos.php" class="nav-item"><i class="fa-solid fa-receipt"></i><span>Pedidos</span></a>
        <a href="inventario.php" class="nav-item"><i class="fa-solid fa-boxes-stacked"></i><span>Inventario</span></a>
        <a href="auditoria.php" class="nav-item active"><i class="fa-solid fa-user-shield"></i><span>Auditoria</span><div class="nav-indicator"></div></a>
        <div class="nav-section-label">Cuenta</div>
        
        <a href="../logout.php" class="nav-item nav-logout"><i class="fa-solid fa-right-from-bracket"></i><span>Cerrar sesion</span></a>
    </nav>
</aside>

<main class="main-content" id="mainContent">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenu"><i class="fa-solid fa-bars"></i></button>
            <div class="breadcrumb-custom"><span>NexusPanel</span><i class="fa-solid fa-chevron-right"></i><span class="active">Auditoria</span></div>
        </div>
        <div class="topbar-right">
            <button id="btnToggleTheme" class="topbar-btn" title="Cambiar Paleta" onclick="toggleTheme()"><i class="fa-solid fa-palette"></i></button><div class="topbar-date" id="topbarDate"></div></div>
    </header>

    <div class="content-area">
        <div class="card-panel">
            <div class="panel-header mb-2">
                <div>
                    <h3 class="panel-title">Bitacora de seguridad y operaciones</h3>
                    <p class="panel-subtitle">Registro de acciones por modulo, usuario y rol.</p>
                </div>
            </div>
            <form method="get" class="toolbar mb-3">
                <input class="modal-input" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar usuario, accion o detalle">
                <select class="modal-select" name="modulo">
                    <option value="">Todos los modulos</option>
                    <?php foreach ($modulos as $m): ?>
                        <option value="<?= htmlspecialchars($m) ?>" <?= $modulo === $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-primary-custom" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
            </form>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Fecha</th><th>Usuario</th><th>Rol</th><th>Modulo</th><th>Accion</th><th>Referencia</th><th>IP</th><th>Detalles</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['created_at']) ?></td>
                            <td><?= htmlspecialchars($r['usuario_nombre'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['rol'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['modulo']) ?></td>
                            <td><?= htmlspecialchars($r['accion']) ?></td>
                            <td><?= htmlspecialchars(($r['referencia_tipo'] ?? '-') . ' #' . ($r['referencia_id'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars($r['ip_origen'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['detalles'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<script src="../assets/js/dashboard.js"></script>
</body>
</html>



