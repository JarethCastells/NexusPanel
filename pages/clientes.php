<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireGestion();

$usuario = usuarioActual();
$esAdmin = esAdmin();
$esInventario = esInventario();
$nombreRol = nombreRolActual();
$inicioHref = $esInventario ? 'pedidos.php' : 'dashboard.php';
$q = trim($_GET['q'] ?? '');
$clienteId = (int)($_GET['cliente_id'] ?? 0);
$tieneHistorialProductos = function_exists('tableExists') ? tableExists($pdo, 'cliente_producto_historial') : false;

$where = "WHERE u.rol='cliente'";
$params = [];
if ($q !== '') {
    $where .= " AND (u.nombre LIKE ? OR u.email LIKE ? OR u.telefono LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$sqlClientes = "
    SELECT
        u.id,
        u.nombre,
        u.email,
        u.telefono,
        u.domicilio,
        u.activo,
        COUNT(p.id) AS pedidos_total,
        COALESCE(SUM(CASE WHEN p.estado='entregado' THEN 1 ELSE 0 END),0) AS entregados,
        COALESCE(SUM(p.total),0) AS monto_total,
        MAX(p.created_at) AS ultima_compra
    FROM usuarios u
    LEFT JOIN pedidos p ON p.cliente_id = u.id
    $where
    GROUP BY u.id
    ORDER BY u.nombre ASC
";
$st = $pdo->prepare($sqlClientes);
$st->execute($params);
$clientes = $st->fetchAll();

if ($clienteId <= 0 && !empty($clientes)) {
    $clienteId = (int)$clientes[0]['id'];
}

$historialPedidos = [];
$historialProductos = [];
$clienteDetalle = null;
if ($clienteId > 0) {
    $stDet = $pdo->prepare("SELECT id, nombre, email, telefono, domicilio, activo FROM usuarios WHERE id=? AND rol='cliente' LIMIT 1");
    $stDet->execute([$clienteId]);
    $clienteDetalle = $stDet->fetch();

    if ($clienteDetalle) {
        $stPed = $pdo->prepare("
            SELECT
                p.id,
                p.folio_hex,
                p.estado,
                p.total,
                p.created_at,
                GROUP_CONCAT(CONCAT(pi.cantidad, 'x ', pr.nombre) ORDER BY pr.nombre SEPARATOR ' | ') AS productos
            FROM pedidos p
            LEFT JOIN pedido_items pi ON pi.pedido_id = p.id
            LEFT JOIN productos pr ON pr.id = pi.producto_id
            WHERE p.cliente_id = ?
            GROUP BY p.id
            ORDER BY p.created_at DESC
            LIMIT 10
        ");
        $stPed->execute([$clienteId]);
        $historialPedidos = $stPed->fetchAll();

        if ($tieneHistorialProductos) {
            $stProd = $pdo->prepare("
                SELECT
                    h.producto_id,
                    p.nombre,
                    h.cantidad_total,
                    h.veces_pedido,
                    h.ultima_fecha
                FROM cliente_producto_historial h
                JOIN productos p ON p.id = h.producto_id
                WHERE h.cliente_id = ?
                ORDER BY h.cantidad_total DESC, h.veces_pedido DESC
                LIMIT 10
            ");
            $stProd->execute([$clienteId]);
            $historialProductos = $stProd->fetchAll();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Clientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .clientes-layout { display:flex; gap:16px; height:calc(100vh - 64px); }
        .modal-input { width:100%; padding:10px 12px; border-radius:10px; border:1px solid var(--border); background:var(--bg-input); color:var(--text-primary); outline:none; }
        .clientes-lista { width:340px; flex-shrink:0; background:var(--bg-card); border-right:1px solid var(--border); overflow:auto; }
        .cliente-row { display:block; color:inherit; text-decoration:none; padding:14px 16px; border-bottom:1px solid var(--border); }
        .cliente-row:hover { background:rgba(0,212,255,.04); }
        .cliente-row.active { background:rgba(0,212,255,.08); border-left:3px solid var(--primary); padding-left:13px; }
        .cliente-meta { font-size:11px; color:var(--text-dim); }
        .panel-main { flex:1; min-width:0; overflow:auto; padding:14px; }
        .mini-grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; margin-bottom:14px; }
        .mini-card { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:14px; }
        .mini-num { font-family:var(--font-mono); font-size:20px; color:var(--primary); font-weight:700; }
        .mini-label { font-size:11px; color:var(--text-dim); }
        .table-mini { width:100%; border-collapse:collapse; }
        .table-mini th,.table-mini td { border-bottom:1px solid var(--border); padding:10px 8px; font-size:12px; }
        .table-mini th { color:var(--text-dim); font-weight:500; text-transform:uppercase; font-family:var(--font-mono); font-size:10px; }
        @media (max-width: 900px) {
            .clientes-layout { flex-direction:column; height:auto; }
            .clientes-lista { width:100%; max-height:220px; }
            .mini-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
        }
    </style>
</head>
<body data-theme="<?= function_exists('temaActual') ? htmlspecialchars(temaActual()) : 'dark' ?>">
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="logo-icon-sm"><i class="fa-solid fa-hexagon-nodes"></i></div>
            <span class="logo-text-sm">Nexus<strong>Panel</strong></span>
        </div>
    </div>
    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($usuario['nombre'],0,1)) ?></div>
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($usuario['nombre']) ?></span>
            <span class="user-role role-admin"><i class="fa-solid fa-boxes-stacked"></i> <?= htmlspecialchars($nombreRol) ?></span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="<?= htmlspecialchars($inicioHref) ?>" class="nav-item"><i class="fa-solid fa-gauge-high"></i><span>Inicio</span></a>
        <?php if ($esAdmin): ?>
        <a href="usuarios.php" class="nav-item"><i class="fa-solid fa-users"></i><span>Usuarios</span></a>
        <?php endif; ?>
        <a href="productos.php" class="nav-item"><i class="fa-solid fa-pills"></i><span>Productos</span></a>
        <a href="pedidos.php" class="nav-item"><i class="fa-solid fa-clipboard-check"></i><span>Pedidos</span></a>
        <a href="inventario.php" class="nav-item"><i class="fa-solid fa-boxes-stacked"></i><span>Inventario</span></a>
        <?php if ($esAdmin): ?>
        <div class="nav-section-label">Operaciones</div>
        <a href="mapa.php" class="nav-item"><i class="fa-solid fa-map-location-dot"></i><span>Mapa de Usuarios</span></a>
        <?php endif; ?>
        <div class="nav-section-label">Cuenta</div>
        
        <a href="../logout.php" class="nav-item nav-logout"><i class="fa-solid fa-right-from-bracket"></i><span>Cerrar sesion</span></a>
    </nav>
</aside>

<main class="main-content">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenu"><i class="fa-solid fa-bars"></i></button>
            <div class="breadcrumb-custom"><span>NexusPanel</span><i class="fa-solid fa-chevron-right"></i><span class="active">Clientes</span></div>
        </div>
        <div class="topbar-right">
            <button id="btnToggleTheme" class="topbar-btn" title="Cambiar Paleta" onclick="toggleTheme()"><i class="fa-solid fa-palette"></i></button><div class="topbar-date" id="topbarDate"></div></div>
    </header>

    <div class="content-area" style="padding:0;">
        <div class="clientes-layout">
            <div class="clientes-lista">
                <form method="GET" style="padding:12px; border-bottom:1px solid var(--border);">
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar cliente..." class="modal-input" style="width:100%;">
                </form>
                <?php if (empty($clientes)): ?>
                    <div style="padding:20px;color:var(--text-dim);font-size:12px;">No hay clientes.</div>
                <?php else: ?>
                    <?php foreach ($clientes as $c): ?>
                        <a class="cliente-row <?= (int)$c['id']===$clienteId ? 'active' : '' ?>" href="clientes.php?cliente_id=<?= (int)$c['id'] ?><?= $q!==''?'&q='.urlencode($q):'' ?>">
                            <div style="display:flex;justify-content:space-between;gap:8px;">
                                <strong><?= htmlspecialchars($c['nombre']) ?></strong>
                            </div>
                            <div class="cliente-meta"><?= htmlspecialchars($c['email']) ?></div>
                            <div class="cliente-meta">Pedidos: <?= (int)$c['pedidos_total'] ?> | Ultima: <?= $c['ultima_compra'] ? date('d/m/Y', strtotime($c['ultima_compra'])) : '-' ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="panel-main">
                <?php if (!$clienteDetalle): ?>
                    <div class="card-panel"><p class="panel-subtitle">Selecciona un cliente para ver historial.</p></div>
                <?php else: ?>
                    <div class="card-panel" style="margin-bottom:12px;">
                        <div class="panel-header">
                            <div>
                                <h3 class="panel-title">Cliente: <?= htmlspecialchars($clienteDetalle['nombre']) ?></h3>
                                <p class="panel-subtitle"><?= htmlspecialchars($clienteDetalle['email']) ?> - <?= htmlspecialchars($clienteDetalle['telefono'] ?? '-') ?></p>
                            </div>
                            <a href="pedidos.php?cliente_id=<?= (int)$clienteDetalle['id'] ?>" class="btn-panel"><i class="fa-solid fa-plus"></i> Crear pedido</a>
                        </div>
                    </div>

                    <div class="mini-grid">
                        <div class="mini-card"><div class="mini-num"><?= (int)($clienteDetalle['id'] ?? 0) ?></div><div class="mini-label">ID Cliente</div></div>
                        <div class="mini-card"><div class="mini-num"><?= count($historialPedidos) ?></div><div class="mini-label">Ultimos pedidos</div></div>
                        <div class="mini-card"><div class="mini-num"><?= count($historialProductos) ?></div><div class="mini-label">Productos en historial</div></div>
                        <div class="mini-card"><div class="mini-num"><?= htmlspecialchars($clienteDetalle['telefono'] ?: '-') ?></div><div class="mini-label">Telefono</div></div>
                    </div>

                    <div class="card-panel" style="margin-bottom:12px;">
                        <div class="panel-header"><h3 class="panel-title">Historial de compras</h3></div>
                        <div class="table-wrapper">
                            <table class="table-mini">
                                <thead><tr><th>#</th><th>Fecha</th><th>Productos</th><th>Estado</th><th>Total</th></tr></thead>
                                <tbody>
                                <?php if (empty($historialPedidos)): ?>
                                    <tr><td colspan="5" style="color:var(--text-dim);">Sin pedidos registrados</td></tr>
                                <?php else: ?>
                                    <?php foreach ($historialPedidos as $p): ?>
                                        <tr>
                                            <td>#<?= htmlspecialchars($p['folio_hex'] ?: strtoupper(dechex((int)$p['id']))) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
                                            <td><?= htmlspecialchars($p['productos'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($p['estado']) ?></td>
                                            <td>$<?= number_format((float)$p['total'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card-panel">
                        <div class="panel-header"><h3 class="panel-title">Productos mas solicitados</h3></div>
                        <div class="table-wrapper">
                            <table class="table-mini">
                                <thead><tr><th>Producto</th><th>Cantidad</th><th>Veces</th><th>Ultima fecha</th></tr></thead>
                                <tbody>
                                <?php if (empty($historialProductos)): ?>
                                    <tr><td colspan="4" style="color:var(--text-dim);">Sin historial de productos</td></tr>
                                <?php else: ?>
                                    <?php foreach ($historialProductos as $h): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($h['nombre']) ?></td>
                                            <td><?= (int)$h['cantidad_total'] ?></td>
                                            <td><?= (int)$h['veces_pedido'] ?></td>
                                            <td><?= $h['ultima_fecha'] ? date('d/m/Y', strtotime($h['ultima_fecha'])) : '-' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="../assets/js/dashboard.js"></script>
</body>
</html>



