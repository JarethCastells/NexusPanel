<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireGestion();

$usuario = usuarioActual();
$esAdmin = esAdmin();
$esInventario = esInventario();
$nombreRol = nombreRolActual();
$vista = (($_GET['vista'] ?? '') === 'inicio') ? 'inicio' : 'inventario';
$inicioHref = $esInventario ? 'inventario.php?vista=inicio' : 'dashboard.php';
$inicioPendHref = $inicioHref . (strpos($inicioHref, '?') !== false ? '&' : '?') . 'focus_pending=1';
$msg = '';
$err = '';
$pendientesCount = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente'")->fetchColumn();

$pedidosPendientesInicio = [];
try {
    $stPend = $pdo->query("
        SELECT
            p.id,
            " . (columnExists($pdo, 'pedidos', 'folio_hex') ? "p.folio_hex" : "UPPER(HEX(p.id))") . " AS folio_hex_ui,
            p.estado,
            p.total,
            p.created_at,
            c.nombre AS cliente_nombre,
            COALESCE(SUM(pi.cantidad), 0) AS total_items
        FROM pedidos p
        JOIN usuarios c ON c.id = p.cliente_id
        LEFT JOIN pedido_items pi ON pi.pedido_id = p.id
        WHERE p.estado = 'pendiente'
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT 8
    ");
    $pedidosPendientesInicio = $stPend->fetchAll();
} catch (Throwable $e) {
    $pedidosPendientesInicio = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'movimiento') {
    $productoId = (int)($_POST['producto_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? 'entrada';
    $cantidad = (int)($_POST['cantidad'] ?? 0);
    $stockObjetivo = (int)($_POST['stock_objetivo'] ?? 0);
    $nota = trim((string)($_POST['nota'] ?? ''));
    $fechaCaducidadLote = trim((string)($_POST['fecha_caducidad_lote'] ?? ''));

    try {
        if ($productoId <= 0) throw new RuntimeException('Producto invalido.');
        if (!in_array($tipo, ['entrada', 'salida', 'ajuste', 'reserva', 'liberacion'], true)) {
            throw new RuntimeException('Tipo de movimiento invalido.');
        }
        if ($tipo !== 'ajuste' && $cantidad <= 0) throw new RuntimeException('Cantidad invalida.');
        if (in_array($tipo, ['entrada', 'liberacion'], true) && $fechaCaducidadLote === '') {
            throw new RuntimeException('La fecha de caducidad es obligatoria para nuevas entradas de lote.');
        }

        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT id, nombre, stock, lote_activo_id FROM productos WHERE id = ? FOR UPDATE");
        $st->execute([$productoId]);
        $pr = $st->fetch();
        if (!$pr) throw new RuntimeException('Producto no encontrado.');

        $stockAntes = (int)$pr['stock'];
        $stockNuevo = $stockAntes;
        $qtyMov = $cantidad;

        if (in_array($tipo, ['entrada', 'liberacion'], true)) {
            crearLoteProducto(
                $pdo,
                $productoId,
                $cantidad,
                $fechaCaducidadLote,
                fechaMysqlAhora(),
                (int)($usuario['usuario_id'] ?? 0)
            );
            $stockNuevo = recalcularStockProductoDesdeLotes($pdo, $productoId);
        } elseif (in_array($tipo, ['salida', 'reserva'], true)) {
            if ($stockAntes < $cantidad) throw new RuntimeException('Stock insuficiente para salida.');
            $loteActivo = (int)($pr['lote_activo_id'] ?? 0);
            if ($loteActivo <= 0) {
                $stLote = $pdo->prepare("SELECT id FROM producto_lotes WHERE producto_id = ? ORDER BY fecha_ingreso ASC, lote_numero ASC LIMIT 1");
                $stLote->execute([$productoId]);
                $loteActivo = (int)$stLote->fetchColumn();
            }
            if ($loteActivo <= 0) throw new RuntimeException('No hay lotes disponibles para salida.');

            $stUn = $pdo->prepare("SELECT unidades FROM producto_lotes WHERE id = ? AND producto_id = ? FOR UPDATE");
            $stUn->execute([$loteActivo, $productoId]);
            $unidadesLote = (int)$stUn->fetchColumn();
            if ($unidadesLote < $cantidad) {
                throw new RuntimeException('El lote activo no tiene unidades suficientes para salida.');
            }
            $pdo->prepare("UPDATE producto_lotes SET unidades = unidades - ? WHERE id = ?")->execute([$cantidad, $loteActivo]);
            $stockNuevo = recalcularStockProductoDesdeLotes($pdo, $productoId);
        } else {
            $objetivo = max(0, $stockObjetivo);
            $delta = $objetivo - $stockAntes;
            $qtyMov = abs($delta);
            if ($delta !== 0) {
                $loteActivo = (int)($pr['lote_activo_id'] ?? 0);
                if ($loteActivo <= 0) {
                    $stLote = $pdo->prepare("SELECT id FROM producto_lotes WHERE producto_id = ? ORDER BY fecha_ingreso DESC, lote_numero DESC LIMIT 1");
                    $stLote->execute([$productoId]);
                    $loteActivo = (int)$stLote->fetchColumn();
                }
                if ($loteActivo <= 0) throw new RuntimeException('No hay lote activo para ajuste.');
                $stUn = $pdo->prepare("SELECT unidades FROM producto_lotes WHERE id = ? AND producto_id = ? FOR UPDATE");
                $stUn->execute([$loteActivo, $productoId]);
                $actualLote = (int)$stUn->fetchColumn();
                $nuevoLote = $actualLote + $delta;
                if ($nuevoLote < 0) throw new RuntimeException('El ajuste no puede dejar unidades negativas en lote activo.');
                $pdo->prepare("UPDATE producto_lotes SET unidades = ? WHERE id = ?")->execute([$nuevoLote, $loteActivo]);
            }
            $stockNuevo = recalcularStockProductoDesdeLotes($pdo, $productoId);
        }

        registrarMovimientoInventario(
            $pdo,
            $productoId,
            $tipo,
            max(1, $qtyMov),
            $stockAntes,
            $stockNuevo,
            'manual',
            null,
            $nota !== '' ? $nota : 'Movimiento desde panel de inventario',
            (int)$usuario['usuario_id']
        );
        registrarAuditoria(
            $pdo,
            (int)$usuario['usuario_id'],
            (string)($usuario['rol'] ?? ''),
            'inventario',
            'movimiento_' . $tipo,
            'producto',
            $productoId,
            'Stock ' . $stockAntes . ' -> ' . $stockNuevo
        );
        $pdo->commit();
        $msg = 'Movimiento registrado para "' . $pr['nombre'] . '".';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = $e->getMessage();
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$stockMax = isset($_GET['stock_max']) && $_GET['stock_max'] !== '' ? (int)$_GET['stock_max'] : null;

$where = ["1=1"];
$params = [];
if ($q !== '') {
    $where[] = "(p.codigo LIKE ? OR p.nombre LIKE ?)";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($stockMax !== null) {
    $where[] = "p.stock <= ?";
    $params[] = $stockMax;
}

$st = $pdo->prepare("
    SELECT
        p.id,
        p.codigo,
        p.nombre,
        p.stock,
        p.activo,
        p.unidad_medida,
        p.fecha_ingreso,
        p.fecha_caducidad,
        COALESCE(e.nombre, 'Sin especie') AS especie
    FROM productos p
    LEFT JOIN especies e ON e.id = p.especie_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY p.stock ASC, p.nombre ASC
    LIMIT 500
");
$st->execute($params);
$productos = $st->fetchAll();

$movs = $pdo->query("
    SELECT im.*, p.nombre AS producto_nombre, u.nombre AS usuario_nombre
    FROM inventario_movimientos im
    JOIN productos p ON p.id = im.producto_id
    LEFT JOIN usuarios u ON u.id = im.created_by
    ORDER BY im.id DESC
    LIMIT 120
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .toolbar { display:grid; grid-template-columns: 2fr 1fr auto; gap:10px; }
        .table-wrapper { overflow-x:auto; }
        .data-table { min-width: 1100px; }
        .stock-low { color:#ef4444; font-weight:700; }
        .stock-mid { color:#f59e0b; font-weight:700; }
        .stock-ok { color:#10b981; font-weight:700; }
        .mov-entrada,.mov-liberacion { color:#10b981; font-weight:600; }
        .mov-salida,.mov-reserva { color:#ef4444; font-weight:600; }
        .mov-ajuste { color:#00d4ff; font-weight:600; }
        .notif-count {
            position: absolute;
            top: -2px;
            right: -2px;
            min-width: 18px;
            height: 18px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }
        .notif-count.hidden { display: none; }
        .panel-noti-dropdown {
            width: 360px;
            max-width: calc(100vw - 24px);
            border: 1px solid var(--border);
            background: #0b1528;
        }
        .panel-noti-header {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #fff;
        }
        .panel-noti-list {
            max-height: 320px;
            overflow: auto;
            padding: 8px;
            display: grid;
            gap: 8px;
        }
        .panel-noti-item {
            display: block;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: rgba(255,255,255,.02);
            color: inherit;
            text-decoration: none;
            padding: 10px;
        }
        .panel-noti-item strong { display: block; font-size: 13px; color: #fff; }
        .panel-noti-item small { display: block; color: var(--text-muted); margin-top: 2px; font-size: 11px; }
        @media (max-width: 900px) { .toolbar { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .toolbar { grid-template-columns: 1fr !important; gap: 8px !important; }
            .table-wrapper { margin: 0 -6px; padding: 0 6px; }
            .data-table { min-width: 760px !important; }
            .stats-grid { grid-template-columns: 1fr !important; gap: 10px !important; }
            .card-panel { border-radius: 12px; }
            .panel-header { padding: 14px 14px !important; }
        }
    </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo"><div class="logo-icon-sm"><i class="fa-solid fa-hexagon-nodes"></i></div><span class="logo-text-sm">Nexus<strong>Panel</strong></span></div>
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
    </div>
    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($usuario['nombre'], 0, 1)) ?></div>
        <div class="user-info"><span class="user-name"><?= htmlspecialchars($usuario['nombre']) ?></span><span class="user-role role-admin"><i class="fa-solid fa-boxes-stacked"></i> <?= htmlspecialchars($nombreRol) ?></span></div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="<?= htmlspecialchars($inicioHref) ?>" class="nav-item <?= $vista === 'inicio' ? 'active' : '' ?>"><i class="fa-solid fa-gauge-high"></i><span>Inicio</span><?= $vista === 'inicio' ? '<div class="nav-indicator"></div>' : '' ?></a>
        <?php if ($esAdmin): ?><a href="usuarios.php" class="nav-item"><i class="fa-solid fa-users"></i><span>Usuarios</span></a><?php endif; ?>
        <a href="productos.php" class="nav-item"><i class="fa-solid fa-pills"></i><span>Productos</span></a>
        <a href="pedidos.php" class="nav-item"><i class="fa-solid fa-clipboard-check"></i><span>Pedidos</span></a>
        <?php if ($esAdmin): ?><a href="inventario.php" class="nav-item <?= $vista !== 'inicio' ? 'active' : '' ?>"><i class="fa-solid fa-boxes-stacked"></i><span>Inventario</span><?= $vista !== 'inicio' ? '<div class="nav-indicator"></div>' : '' ?></a><?php endif; ?>
        <a href="mensajes.php" class="nav-item"><i class="fa-solid fa-comments"></i><span>Mensajes</span></a>
        <?php if ($esAdmin): ?><div class="nav-section-label">Operaciones</div>
        <a href="mapa.php" class="nav-item"><i class="fa-solid fa-map-location-dot"></i><span>Mapa de Usuarios</span></a><?php endif; ?>
        <div class="nav-section-label">Cuenta</div>
        <a href="../logout.php" class="nav-item nav-logout"><i class="fa-solid fa-right-from-bracket"></i><span>Cerrar sesion</span></a>
    </nav>
</aside>

<main class="main-content" id="mainContent">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenu"><i class="fa-solid fa-bars"></i></button>
            <div class="breadcrumb-custom"><span>NexusPanel</span><i class="fa-solid fa-chevron-right"></i><span class="active"><?= $vista === 'inicio' ? 'Inicio' : 'Inventario' ?></span></div>
        </div>
        <div class="topbar-right">
            <div class="topbar-date" id="topbarDate"></div>
            <div class="dropdown">
                <button class="topbar-btn" type="button" id="panelNotiBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Notificaciones">
                    <i class="fa-solid fa-bell"></i><span class="notif-count hidden" id="panelNotiCount">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0 panel-noti-dropdown" aria-labelledby="panelNotiBtn">
                    <div class="panel-noti-header">
                        <strong>Notificaciones</strong>
                        <button id="btnPanelNotiClear" class="btn btn-sm btn-link p-0" type="button">Limpiar</button>
                    </div>
                    <div class="panel-noti-list" id="panelNotiList">
                        <div style="color:var(--text-muted);font-size:12px;padding:8px;">Sin notificaciones.</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="content-area">
        <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
        <?php if ($vista === 'inicio'): ?>
        <div class="card-panel mb-3">
            <div class="panel-header mb-2">
                <div>
                    <h3 class="panel-title">Inicio de inventario</h3>
                    <p class="panel-subtitle">Resumen de pedidos pendientes y acciones rapidas.</p>
                </div>
                <a class="btn-primary-custom" href="pedidos.php?estado=pendiente"><i class="fa-solid fa-clipboard-check"></i> Ver todos los pendientes</a>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                <div style="padding:14px;border:1px solid rgba(245,158,11,.35);border-radius:14px;background:linear-gradient(135deg,rgba(245,158,11,.14),rgba(245,158,11,.04));box-shadow:0 12px 30px rgba(245,158,11,.08);">
                    <div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#fdba74;">Pedidos pendientes</div>
                    <div style="font-size:32px;font-weight:800;color:#f59e0b;line-height:1.1;"><?= (int)$pendientesCount ?></div>
                </div>
                <div style="padding:14px;border:1px solid rgba(0,212,255,.35);border-radius:14px;background:linear-gradient(135deg,rgba(0,212,255,.14),rgba(59,130,246,.06));box-shadow:0 12px 30px rgba(37,99,235,.10);">
                    <div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#67e8f9;">Productos en catalogo</div>
                    <div style="font-size:32px;font-weight:800;color:var(--primary);line-height:1.1;"><?= (int)count($productos) ?></div>
                </div>
            </div>
        </div>

        <div class="card-panel mb-3">
            <div class="panel-header"><h3 class="panel-title">Detalle de pedidos pendientes</h3></div>
            <?php if (empty($pedidosPendientesInicio)): ?>
                <div style="padding:16px;color:var(--text-muted);">No hay pedidos pendientes en este momento.</div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead><tr><th>Pedido</th><th>Cliente</th><th>Items</th><th>Fecha</th><th>Accion</th></tr></thead>
                        <tbody>
                        <?php foreach ($pedidosPendientesInicio as $pp): ?>
                            <tr>
                                <td>#<?= htmlspecialchars((string)($pp['folio_hex_ui'] ?: strtoupper(dechex((int)$pp['id'])))) ?></td>
                                <td><?= htmlspecialchars((string)$pp['cliente_nombre']) ?></td>
                                <td><?= (int)$pp['total_items'] ?></td>
                                <td><?= htmlspecialchars((string)$pp['created_at']) ?></td>
                                <td><a class="btn-secondary-custom" href="pedidos.php?historial=<?= (int)$pp['id'] ?>"><i class="fa-solid fa-eye"></i> Ver pedido</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php else: ?>

        <div class="card-panel mb-3">
            <div class="panel-header mb-2">
                <div>
                    <h3 class="panel-title">Control de inventario</h3>
                    <p class="panel-subtitle">Control de stock, fechas, alertas y exportacion operativa.</p>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn-primary-custom" href="../api/reportes.php?action=excel_inventario"><i class="fa-solid fa-file-excel"></i> Excel inventario</a>
                    <a class="btn-secondary-custom" href="../api/reportes.php?action=excel_pedidos"><i class="fa-solid fa-file-export"></i> Excel pedidos</a>
                </div>
            </div>
            <form method="get" class="toolbar">
                <input class="modal-input" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar">
                <input class="modal-input" type="number" min="0" name="stock_max" value="<?= $stockMax !== null ? (int)$stockMax : '' ?>" placeholder="Stock maximo">
                <button class="btn-primary-custom" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Filtrar</button>
            </form>
        </div>

        <div class="card-panel mb-3">
            <div class="panel-header"><h3 class="panel-title">Registrar movimiento</h3></div>
            <form method="post" class="row g-2">
                <input type="hidden" name="action" value="movimiento">
                <div class="col-md-3">
                    <select class="modal-select" name="producto_id" required>
                        <option value="">Selecciona producto...</option>
                        <?php foreach ($productos as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['codigo'] . ' - ' . $p['nombre']) ?> (stock <?= (int)$p['stock'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="modal-select" name="tipo" id="movTipo" required>
                        <option value="entrada">Entrada</option>
                        <option value="salida">Salida</option>
                        <option value="ajuste">Ajuste</option>
                        <option value="reserva">Reserva</option>
                        <option value="liberacion">Liberacion</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input class="modal-input" type="number" min="1" name="cantidad" id="movCantidad" placeholder="Cantidad">
                </div>
                <div class="col-md-2">
                    <input class="modal-input" type="number" min="0" name="stock_objetivo" id="movObjetivo" placeholder="Stock objetivo">
                </div>
                <div class="col-md-2">
                    <input class="modal-input" type="date" name="fecha_caducidad_lote" id="movFechaCaducidad" placeholder="Caducidad lote">
                </div>
                <div class="col-md-1">
                    <button class="btn-primary-custom w-100" type="submit">Guardar</button>
                </div>
                <div class="col-12">
                    <input class="modal-input" name="nota" maxlength="255" placeholder="Nota del movimiento (opcional)">
                </div>
            </form>
        </div>

        <div class="card-panel mb-3">
            <div class="panel-header"><h3 class="panel-title">Stock actual</h3></div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Producto</th><th>Especie</th><th>Stock</th><th>Semaforo</th></tr></thead>
                    <tbody>
                    <?php foreach ($productos as $p): ?>
                        <?php
                        $stock = (int)$p['stock'];
                        $stockClass = $stock <= 20 ? 'stock-low' : ($stock <= 50 ? 'stock-mid' : 'stock-ok');
                        ?>
                        <tr>
                            <td>#<?= (int)$p['id'] ?></td>
                            <td><?= htmlspecialchars($p['nombre']) ?></td>
                            <td><?= htmlspecialchars($p['especie']) ?></td>
                            <td class="<?= $stockClass ?>"><?= $stock ?> <?= htmlspecialchars($p['unidad_medida'] ?: 'piezas') ?></td>
                            <td class="<?= $stockClass ?>"><?= $stock <= 20 ? 'Bajo' : ($stock <= 50 ? 'Medio' : 'Disponible') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-panel">
            <div class="panel-header"><h3 class="panel-title">Ultimos movimientos</h3></div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th>Cantidad</th><th>Stock antes</th><th>Stock nuevo</th><th>Referencia</th><th>Usuario</th><th>Nota</th></tr></thead>
                    <tbody>
                    <?php foreach ($movs as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['created_at']) ?></td>
                            <td><?= htmlspecialchars($m['producto_nombre']) ?></td>
                            <td class="mov-<?= htmlspecialchars($m['tipo']) ?>"><?= htmlspecialchars($m['tipo']) ?></td>
                            <td><?= (int)$m['cantidad'] ?></td>
                            <td><?= (int)$m['stock_anterior'] ?></td>
                            <td><?= (int)$m['stock_nuevo'] ?></td>
                            <td><?= htmlspecialchars(($m['referencia_tipo'] ?? '-') . ' #' . ($m['referencia_id'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars($m['usuario_nombre'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($m['nota'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
<script>
const PANEL_USER_ID = <?= (int)($usuario['usuario_id'] ?? 0) ?>;
let panelNotiCache = [];
function safe(s){ return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;'); }
function panelNotiSeenKey(){ return `panel_noti_seen_${PANEL_USER_ID}`; }
function panelNotiSeenGet(){ try{ return new Set(JSON.parse(localStorage.getItem(panelNotiSeenKey()) || '[]')); }catch(_){ return new Set(); } }
function panelNotiSeenSet(setObj){ try{ localStorage.setItem(panelNotiSeenKey(), JSON.stringify(Array.from(setObj).slice(-600))); }catch(_){} }
function renderPanelNotis(rows){
    const list = document.getElementById('panelNotiList');
    const badge = document.getElementById('panelNotiCount');
    if(!list || !badge) return;
    panelNotiCache = Array.isArray(rows) ? rows : [];
    const seen = panelNotiSeenGet();
    const unseen = panelNotiCache.filter(r => !seen.has(String(r.uid || '')));
    badge.textContent = String(unseen.length);
    badge.classList.toggle('hidden', unseen.length < 1);
    if (!unseen.length){
        list.innerHTML = '<div style="color:var(--text-muted);font-size:12px;padding:8px;">Sin notificaciones.</div>';
        return;
    }
    list.innerHTML = unseen.map(r => `
        <a href="${safe(r.goto || '#')}" class="panel-noti-item" style="${seen.has(String(r.uid||'')) ? '' : 'border-color:rgba(0,212,255,.55);box-shadow:0 0 0 1px rgba(0,212,255,.18) inset;'}">
            <strong>${safe(r.title || 'Notificacion')}</strong>
            <small>${safe(r.from || 'Sistema')}</small>
            <div style="font-size:12px;color:var(--text-light);margin-top:4px;">${safe(r.body || '')}</div>
            <small>${safe(r.ts || '')}</small>
        </a>
    `).join('');
}
async function pollPanelNotis(){
    try{
        const res = await fetch('../api/pedido.php?action=panel_notificaciones&limit=80', { cache:'no-store' });
        if(!res.ok) return;
        const rows = await res.json();
        if(!Array.isArray(rows)) return;
        renderPanelNotis(rows);
    }catch(_){}
}
function markPanelNotisRead(){
    const seen = panelNotiSeenGet();
    (panelNotiCache || []).forEach(r => seen.add(String(r.uid || '')));
    panelNotiSeenSet(seen);
    renderPanelNotis(panelNotiCache);
}
function initPanelNotis(){
    const clearBtn = document.getElementById('btnPanelNotiClear');
    const dropBtn = document.getElementById('panelNotiBtn');
    clearBtn?.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); markPanelNotisRead(); });
    dropBtn?.addEventListener('show.bs.dropdown', () => markPanelNotisRead());
    pollPanelNotis();
    setInterval(pollPanelNotis, 7000);
}
const movTipo = document.getElementById('movTipo');
const movCantidad = document.getElementById('movCantidad');
const movObjetivo = document.getElementById('movObjetivo');
const movFechaCaducidad = document.getElementById('movFechaCaducidad');
function refreshMovFields() {
    const ajuste = movTipo.value === 'ajuste';
    const requiereLote = movTipo.value === 'entrada' || movTipo.value === 'liberacion';
    movObjetivo.disabled = !ajuste;
    movObjetivo.required = ajuste;
    movCantidad.disabled = ajuste;
    movCantidad.required = !ajuste;
    movFechaCaducidad.disabled = !requiereLote;
    movFechaCaducidad.required = requiereLote;
}
movTipo.addEventListener('change', refreshMovFields);
refreshMovFields();
initPanelNotis();
</script>
</body>
</html>





