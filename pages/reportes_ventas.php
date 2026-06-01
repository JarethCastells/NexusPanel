<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireGestion();

$usuario = usuarioActual();
$esAdmin = esAdmin();
$inicioHref = esInventario() ? 'inventario.php?vista=inicio' : 'dashboard.php';

$periodo = $_GET['periodo'] ?? 'mes';
$anio = max(2020, min(2100, (int)($_GET['anio'] ?? date('Y'))));
$mes = max(1, min(12, (int)($_GET['mes'] ?? date('n'))));
$semana = max(1, min(53, (int)($_GET['semana'] ?? date('W'))));
$estado = trim((string)($_GET['estado'] ?? 'todos'));
$productoId = max(0, (int)($_GET['producto_id'] ?? 0));
$linea = trim((string)($_GET['linea'] ?? ''));

$periodo = in_array($periodo, ['semana', 'mes', 'anio'], true) ? $periodo : 'mes';
$estadosValidos = ['todos', 'pendiente', 'aceptado', 'en_camino', 'entregado', 'cancelado'];
if (!in_array($estado, $estadosValidos, true)) {
    $estado = 'todos';
}

[$inicio, $fin, $labelPeriodo] = rangoReporteVentas($periodo, $anio, $mes, $semana);
$rangoAnterior = rangoAnteriorReporteVentas($periodo, $inicio, $fin);
$rows = obtenerVentasReporte($pdo, $inicio, $fin, $estado, $productoId, $linea);
$rowsAnterior = obtenerVentasReporte($pdo, $rangoAnterior[0], $rangoAnterior[1], $estado, $productoId, $linea);
$resumen = resumirVentasReporte($rows, $rowsAnterior);
$chartData = prepararDatosGraficasReporte($rows, $resumen);
$sugerencias = generarSugerenciasReporte($resumen, $chartData);

$productosFiltro = $pdo->query("SELECT id, nombre FROM productos WHERE activo = 1 ORDER BY nombre ASC")->fetchAll();
$lineasFiltro = [];
if (tableExists($pdo, 'logistica_lineas')) {
    $lineasFiltro = $pdo->query("
        SELECT DISTINCT nombre
        FROM logistica_lineas
        WHERE COALESCE(activo, 1) = 1
        ORDER BY COALESCE(prioridad, 99), nombre ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
} elseif (columnExists($pdo, 'pedidos', 'transporte_linea')) {
    $lineasFiltro = $pdo->query("
        SELECT DISTINCT transporte_linea FROM pedidos
        WHERE transporte_linea IS NOT NULL AND transporte_linea <> ''
        ORDER BY transporte_linea ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
}

$query = http_build_query([
    'periodo' => $periodo,
    'anio' => $anio,
    'mes' => $mes,
    'semana' => $semana,
    'estado' => $estado,
    'producto_id' => $productoId,
    'linea' => $linea,
]);

function rangoReporteVentas(string $periodo, int $anio, int $mes, int $semana): array {
    if ($periodo === 'semana') {
        $dt = new DateTime();
        $dt->setISODate($anio, $semana, 1)->setTime(0, 0, 0);
        $inicio = $dt->format('Y-m-d H:i:s');
        $finDt = clone $dt;
        $finDt->modify('+6 days')->setTime(23, 59, 59);
        return [$inicio, $finDt->format('Y-m-d H:i:s'), 'Semana ' . $semana . ' de ' . $anio];
    }
    if ($periodo === 'anio') {
        return [$anio . '-01-01 00:00:00', $anio . '-12-31 23:59:59', 'Anio ' . $anio];
    }
    $inicio = sprintf('%04d-%02d-01 00:00:00', $anio, $mes);
    $fin = date('Y-m-t 23:59:59', strtotime($inicio));
    return [$inicio, $fin, 'Mes ' . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . '/' . $anio];
}

function rangoAnteriorReporteVentas(string $periodo, string $inicio, string $fin): array {
    $inicioDt = new DateTime($inicio);
    $finDt = new DateTime($fin);
    $delta = $periodo === 'semana' ? '-7 days' : ($periodo === 'anio' ? '-1 year' : '-1 month');
    $inicioDt->modify($delta);
    $finDt->modify($delta);
    return [$inicioDt->format('Y-m-d H:i:s'), $finDt->format('Y-m-d H:i:s')];
}

function obtenerVentasReporte(PDO $pdo, string $inicio, string $fin, string $estado, int $productoId, string $linea): array {
    $folioExpr = columnExists($pdo, 'pedidos', 'folio_hex')
        ? "COALESCE(p.folio_hex, LPAD(UPPER(HEX(p.id)), 8, '0'))"
        : "LPAD(UPPER(HEX(p.id)), 8, '0')";
    $transporteExpr = columnExists($pdo, 'pedidos', 'transporte_linea')
        ? "COALESCE(NULLIF(p.transporte_linea, ''), 'Sin asignar')"
        : "'Sin asignar'";
    $rutaExpr = columnExists($pdo, 'pedidos', 'ruta_origen') && columnExists($pdo, 'pedidos', 'ruta_destino')
        ? "COALESCE(NULLIF(CONCAT_WS(' - ', NULLIF(p.ruta_origen, ''), NULLIF(p.ruta_destino, '')), ''), NULLIF(p.domicilio_entrega, ''), 'Ruta no definida')"
        : "COALESCE(NULLIF(p.domicilio_entrega, ''), 'Ruta no definida')";
    $where = ['p.created_at BETWEEN ? AND ?'];
    $params = [$inicio, $fin];
    if ($estado !== 'todos') {
        $where[] = 'p.estado = ?';
        $params[] = $estado;
    }
    if ($productoId > 0) {
        $where[] = 'pi.producto_id = ?';
        $params[] = $productoId;
    }
    if ($linea !== '' && columnExists($pdo, 'pedidos', 'transporte_linea')) {
        $where[] = 'p.transporte_linea = ?';
        $params[] = $linea;
    }

    $sql = "
        SELECT
            p.id AS pedido_id,
            {$folioExpr} AS folio,
            p.created_at,
            p.estado,
            p.total AS total_pedido,
            COALESCE(c.nombre, 'Cliente') AS cliente,
            {$transporteExpr} AS transporte_linea,
            {$rutaExpr} AS ruta,
            pr.id AS producto_id,
            pr.nombre AS producto,
            COALESCE(pr.stock, 0) AS stock_actual,
            COALESCE(pi.cantidad, 0) AS cantidad,
            COALESCE(pi.precio_unit, pr.precio, 0) AS precio_unit,
            COALESCE(pi.cantidad, 0) * COALESCE(pi.precio_unit, pr.precio, 0) AS subtotal
        FROM pedidos p
        LEFT JOIN usuarios c ON c.id = p.cliente_id
        LEFT JOIN pedido_items pi ON pi.pedido_id = p.id
        LEFT JOIN productos pr ON pr.id = pi.producto_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.created_at DESC, p.id DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function resumirVentasReporte(array $rows, array $rowsAnterior): array {
    $pedidos = [];
    $productos = [];
    $productosAnterior = [];
    foreach ($rowsAnterior as $r) {
        if (($r['estado'] ?? '') === 'cancelado') {
            continue;
        }
        $producto = (string)($r['producto'] ?? 'Sin producto');
        $productosAnterior[$producto] = ($productosAnterior[$producto] ?? 0) + (float)($r['subtotal'] ?? 0);
    }
    foreach ($rows as $r) {
        if (($r['estado'] ?? '') === 'cancelado') {
            continue;
        }
        $pid = (int)$r['pedido_id'];
        $pedidos[$pid] = (float)$r['total_pedido'];
        $producto = (string)($r['producto'] ?? 'Sin producto');
        if (!isset($productos[$producto])) {
            $productos[$producto] = [
                'unidades' => 0,
                'total' => 0.0,
                'anterior' => $productosAnterior[$producto] ?? 0.0,
                'variacion' => 0.0,
                'stock' => (int)($r['stock_actual'] ?? 0),
                'decision' => 'Mantener monitoreo',
            ];
        }
        $productos[$producto]['unidades'] += (int)($r['cantidad'] ?? 0);
        $productos[$producto]['total'] += (float)($r['subtotal'] ?? 0);
    }
    foreach ($productos as &$p) {
        $p['variacion'] = $p['anterior'] > 0 ? ($p['total'] - $p['anterior']) / $p['anterior'] : ($p['total'] > 0 ? 1 : 0);
        $p['decision'] = $p['variacion'] <= -0.25
            ? 'Revisar precio/promocion/disponibilidad'
            : ($p['variacion'] >= 0.30 ? 'Aumentar stock' : 'Mantener monitoreo');
    }
    unset($p);
    uasort($productos, static fn($a, $b) => $b['total'] <=> $a['total']);
    $totales = array_values($pedidos);
    $totalAnterior = 0.0;
    foreach ($rowsAnterior as $r) {
        if (($r['estado'] ?? '') !== 'cancelado') {
            $totalAnterior += (float)($r['subtotal'] ?? 0);
        }
    }
    $total = array_sum($totales);
    return [
        'total' => $total,
        'total_anterior' => $totalAnterior,
        'variacion' => $totalAnterior > 0 ? ($total - $totalAnterior) / $totalAnterior : ($total > 0 ? 1 : 0),
        'pedidos' => count($pedidos),
        'ticket' => count($pedidos) ? $total / count($pedidos) : 0,
        'mayor' => $totales ? max($totales) : 0,
        'menor' => $totales ? min($totales) : 0,
        'productos' => $productos,
        'top_producto' => array_key_first($productos) ?: 'Sin datos',
    ];
}

function prepararDatosGraficasReporte(array $rows, array $resumen): array {
    $porFecha = [];
    $porTransporte = [];
    foreach ($rows as $r) {
        if (($r['estado'] ?? '') === 'cancelado') {
            continue;
        }
        $fecha = substr((string)($r['created_at'] ?? ''), 0, 10) ?: 'Sin fecha';
        $porFecha[$fecha] = ($porFecha[$fecha] ?? 0) + (float)($r['subtotal'] ?? 0);
        $linea = trim((string)($r['transporte_linea'] ?? 'Sin asignar')) ?: 'Sin asignar';
        $porTransporte[$linea] = ($porTransporte[$linea] ?? 0) + (float)($r['subtotal'] ?? 0);
    }
    ksort($porFecha);
    arsort($porTransporte);

    $productos = array_slice($resumen['productos'], 0, 8, true);
    return [
        'fechas' => array_keys($porFecha),
        'ventas_fecha' => array_values($porFecha),
        'productos' => array_keys($productos),
        'ventas_producto' => array_map(static fn($p) => round((float)$p['total'], 2), array_values($productos)),
        'variacion_producto' => array_map(static fn($p) => round(((float)$p['variacion']) * 100, 1), array_values($productos)),
        'transportes' => array_keys($porTransporte),
        'ventas_transporte' => array_values($porTransporte),
    ];
}

function generarSugerenciasReporte(array $resumen, array $chartData): array {
    $sugerencias = [];
    if ($resumen['variacion'] < -0.10) {
        $sugerencias[] = ['Alta', 'Ventas a la baja', 'El periodo bajo ' . number_format(abs($resumen['variacion']) * 100, 1) . '% contra el periodo anterior. Revisar precios, disponibilidad y promociones.'];
    } elseif ($resumen['variacion'] > 0.10) {
        $sugerencias[] = ['Alta', 'Crecimiento del periodo', 'El periodo crecio ' . number_format($resumen['variacion'] * 100, 1) . '%. Conviene asegurar stock y capacidad de entrega.'];
    }

    foreach (array_slice($resumen['productos'], 0, 8, true) as $producto => $p) {
        if ((float)$p['variacion'] <= -0.25) {
            $sugerencias[] = ['Alta', 'Producto con baja significativa', $producto . ' bajo ' . number_format(abs((float)$p['variacion']) * 100, 1) . '%. Revisar precio, promocion, disponibilidad y reportes de usuarios.'];
        }
        if ((float)$p['variacion'] >= 0.30) {
            $sugerencias[] = ['Media', 'Producto con alta demanda', $producto . ' crecio ' . number_format((float)$p['variacion'] * 100, 1) . '%. Evaluar aumento de stock.'];
        }
        if ((int)$p['stock'] <= 20 && (float)$p['total'] > 0) {
            $sugerencias[] = ['Alta', 'Riesgo de inventario', $producto . ' tiene ventas activas y stock bajo. Priorizar reabasto.'];
        }
    }

    if (!empty($chartData['transportes'][0])) {
        $sugerencias[] = ['Media', 'Proveedor con mayor movimiento', $chartData['transportes'][0] . ' concentra el mayor monto del periodo. Validar SLA y capacidad antes de incrementar volumen.'];
    }
    if (empty($sugerencias)) {
        $sugerencias[] = ['Baja', 'Sin alertas criticas', 'El periodo no muestra caidas fuertes ni riesgos visibles con los filtros actuales.'];
    }
    return array_slice($sugerencias, 0, 6);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Reporte de ventas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <script src="../assets/js/theme.js"></script>
    <style>
        .report-filters { display:grid; grid-template-columns: repeat(auto-fit,minmax(150px,1fr)); gap:10px; align-items:end; }
        .report-kpi { padding:16px; border:1px solid rgba(68,114,196,.28); border-radius:8px; background:linear-gradient(180deg, rgba(68,114,196,.12), rgba(255,255,255,.025)); box-shadow: inset 0 1px 0 rgba(255,255,255,.05); }
        .report-kpi small { display:block; color:var(--text-muted); font-size:11px; text-transform:uppercase; letter-spacing:.08em; }
        .report-kpi strong { display:block; margin-top:6px; font-size:24px; color:var(--text-primary); overflow-wrap:anywhere; }
        .report-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:14px; margin-bottom:16px; }
        .report-chart { min-height:320px; padding:14px; border:1px solid rgba(68,114,196,.24); border-radius:8px; background:rgba(255,255,255,.025); }
        .report-chart h4, .suggestion-card h4 { font-size:14px; margin:0 0 10px; color:var(--text-primary); }
        .report-chart canvas { width:100% !important; height:260px !important; display:block; }
        .manager-note { padding:16px; border:1px solid rgba(68,114,196,.32); border-radius:8px; background:linear-gradient(90deg, rgba(68,114,196,.12), rgba(255,255,255,.02)); color:var(--text-muted); line-height:1.6; }
        .suggestions-grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(260px,1fr)); gap:12px; }
        .suggestion-card { border:1px solid rgba(68,114,196,.24); border-radius:8px; padding:14px; background:rgba(255,255,255,.025); }
        .suggestion-priority { display:inline-flex; align-items:center; gap:6px; font-size:11px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; margin-bottom:8px; }
        .suggestion-priority.alta { color:#fca5a5; }
        .suggestion-priority.media { color:#fbbf24; }
        .suggestion-priority.baja { color:#93c5fd; }
        .suggestion-card p { color:var(--text-muted); margin:0; font-size:13px; line-height:1.55; }
        .demo-seal { display:inline-flex; align-items:center; gap:8px; padding:8px 10px; border-radius:8px; background:rgba(68,114,196,.14); border:1px solid rgba(68,114,196,.28); color:#dbeafe; font-size:12px; font-weight:700; }
        @media (max-width: 980px) { .report-grid { grid-template-columns: 1fr; } }
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
        <div class="user-info"><span class="user-name"><?= htmlspecialchars($usuario['nombre']) ?></span><span class="user-role <?= $esAdmin ? 'role-admin' : 'role-operator' ?>"><i class="fa-solid fa-chart-column"></i> <?= htmlspecialchars(nombreRolActual()) ?></span></div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="<?= htmlspecialchars($inicioHref) ?>" class="nav-item"><i class="fa-solid fa-chart-line"></i><span>Inicio</span></a>
        <?php if ($esAdmin): ?><a href="usuarios.php" class="nav-item"><i class="fa-solid fa-users"></i><span>Usuarios</span></a><?php endif; ?>
        <a href="productos.php" class="nav-item"><i class="fa-solid fa-flask-vial"></i><span>Productos e inventario</span></a>
        <a href="pedidos.php" class="nav-item"><i class="fa-solid fa-receipt"></i><span>Pedidos</span></a>
        <a href="reportes_ventas.php" class="nav-item active"><i class="fa-solid fa-file-excel"></i><span>Reportes</span><div class="nav-indicator"></div></a>
        <a href="mensajes.php" class="nav-item"><i class="fa-solid fa-comments"></i><span>Mensajes internos</span></a>
        <a href="../whatsapp.php" class="nav-item"><i class="fa-brands fa-whatsapp"></i><span>WhatsApp</span></a>
        <div class="nav-section-label">Cuenta</div>
        <a href="../logout.php" class="nav-item nav-logout"><i class="fa-solid fa-right-from-bracket"></i><span>Cerrar sesion</span></a>
    </nav>
</aside>

<main class="main-content" id="mainContent">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenu"><i class="fa-solid fa-bars"></i></button>
            <div class="breadcrumb-custom"><span>NexusPanel</span><i class="fa-solid fa-chevron-right"></i><span class="active">Reportes</span></div>
        </div>
        <div class="topbar-right">
            <button class="topbar-btn theme-toggle" onclick="toggleTheme()" title="Cambiar tema"><i class="fa-solid fa-moon theme-toggle-icon"></i></button>
            <div class="topbar-date" id="topbarDate"></div>
        </div>
    </header>

    <div class="content-area">
        <div class="welcome-banner">
            <div class="welcome-text">
                <h1>Reporte gerencial de ventas</h1>
                <p>Vista ejecutiva con filtros, graficas, logistica y recomendaciones listas para presentar.</p>
            </div>
            <div class="demo-seal"><i class="fa-solid fa-chart-pie"></i> Demo gerencial | <?= htmlspecialchars($labelPeriodo) ?></div>
        </div>

        <div class="card-panel mb-3">
            <div class="panel-header mb-2">
                <div>
                    <h3 class="panel-title">Filtros del reporte</h3>
                    <p class="panel-subtitle">Estos mismos parametros se mandan al archivo Excel.</p>
                </div>
                <a class="btn-panel" href="../api/reporte_ventas_excel.php?<?= htmlspecialchars($query) ?>"><i class="fa-solid fa-file-excel"></i> Exportar Excel</a>
            </div>
            <form method="get" class="report-filters">
                <select class="modal-select" name="periodo">
                    <option value="semana" <?= $periodo === 'semana' ? 'selected' : '' ?>>Semana</option>
                    <option value="mes" <?= $periodo === 'mes' ? 'selected' : '' ?>>Mes</option>
                    <option value="anio" <?= $periodo === 'anio' ? 'selected' : '' ?>>Anio</option>
                </select>
                <input class="modal-input" type="number" name="semana" min="1" max="53" value="<?= (int)$semana ?>" placeholder="Semana">
                <input class="modal-input" type="number" name="mes" min="1" max="12" value="<?= (int)$mes ?>" placeholder="Mes">
                <input class="modal-input" type="number" name="anio" min="2020" max="2100" value="<?= (int)$anio ?>" placeholder="Anio">
                <select class="modal-select" name="producto_id">
                    <option value="0">Todos los productos</option>
                    <?php foreach ($productosFiltro as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $productoId === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="modal-select" name="linea">
                    <option value="">Todas las lineas</option>
                    <?php foreach ($lineasFiltro as $lf): ?>
                        <option value="<?= htmlspecialchars((string)$lf) ?>" <?= $linea === (string)$lf ? 'selected' : '' ?>><?= htmlspecialchars((string)$lf) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="modal-select" name="estado">
                    <?php foreach ($estadosValidos as $ev): ?>
                        <option value="<?= htmlspecialchars($ev) ?>" <?= $estado === $ev ? 'selected' : '' ?>><?= htmlspecialchars($ev === 'todos' ? 'Todos los estados' : $ev) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-primary-custom" type="submit"><i class="fa-solid fa-filter"></i> Aplicar</button>
            </form>
        </div>

        <div class="stats-grid mb-3">
            <div class="report-kpi"><small>Total vendido</small><strong>$<?= number_format($resumen['total'], 2) ?></strong></div>
            <div class="report-kpi"><small>Ventas</small><strong><?= number_format($resumen['pedidos']) ?></strong></div>
            <div class="report-kpi"><small>Ticket promedio</small><strong>$<?= number_format($resumen['ticket'], 2) ?></strong></div>
            <div class="report-kpi"><small>Producto lider</small><strong style="font-size:17px;"><?= htmlspecialchars($resumen['top_producto']) ?></strong></div>
        </div>

        <div class="card-panel mb-3">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Resumen gerencial</h3>
                    <p class="panel-subtitle">Lectura rapida del periodo seleccionado.</p>
                </div>
            </div>
            <div class="manager-note">
                En <?= htmlspecialchars($labelPeriodo) ?> se registraron <?= number_format($resumen['pedidos']) ?> venta(s)
                por $<?= number_format($resumen['total'], 2) ?>, con ticket promedio de $<?= number_format($resumen['ticket'], 2) ?>.
                El producto lider es <?= htmlspecialchars($resumen['top_producto']) ?>.
                La variacion contra el periodo anterior es de <?= number_format($resumen['variacion'] * 100, 1) ?>%.
                <?= $resumen['variacion'] < 0 ? 'Conviene revisar causas comerciales y disponibilidad.' : 'Conviene sostener inventario y capacidad logistica.' ?>
            </div>
        </div>

        <div class="report-grid">
            <div class="report-chart">
                <h4><i class="fa-solid fa-chart-line"></i> Evolucion de ventas</h4>
                <canvas id="chartVentasFecha"></canvas>
            </div>
            <div class="report-chart">
                <h4><i class="fa-solid fa-chart-column"></i> Ventas por producto</h4>
                <canvas id="chartProductos"></canvas>
            </div>
            <div class="report-chart">
                <h4><i class="fa-solid fa-truck-fast"></i> Ventas por transportista</h4>
                <canvas id="chartTransportes"></canvas>
            </div>
            <div class="report-chart">
                <h4><i class="fa-solid fa-arrow-trend-down"></i> Variacion vs periodo anterior</h4>
                <canvas id="chartVariacion"></canvas>
            </div>
        </div>

        <div class="card-panel mb-3">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Sugerencias automaticas</h3>
                    <p class="panel-subtitle">Reglas simples basadas en ventas, variacion, inventario y transporte.</p>
                </div>
            </div>
            <div class="suggestions-grid">
                <?php foreach ($sugerencias as $s): ?>
                    <?php $priorityClass = strtolower((string)$s[0]); ?>
                    <div class="suggestion-card">
                        <span class="suggestion-priority <?= htmlspecialchars($priorityClass) ?>"><i class="fa-solid fa-circle-info"></i> <?= htmlspecialchars((string)$s[0]) ?></span>
                        <h4><?= htmlspecialchars((string)$s[1]) ?></h4>
                        <p><?= htmlspecialchars((string)$s[2]) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card-panel">
            <div class="panel-header"><h3 class="panel-title">Ranking de productos</h3></div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Producto</th><th>Unidades</th><th>Venta total</th><th>Variacion</th><th>Stock</th><th>Lectura</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($resumen['productos'], 0, 8, true) as $producto => $p): ?>
                        <tr>
                            <td data-label="Producto"><?= htmlspecialchars($producto) ?></td>
                            <td data-label="Unidades"><?= number_format((int)$p['unidades']) ?></td>
                            <td data-label="Venta total">$<?= number_format((float)$p['total'], 2) ?></td>
                            <td data-label="Variacion"><?= number_format((float)$p['variacion'] * 100, 1) ?>%</td>
                            <td data-label="Stock"><?= number_format((int)$p['stock']) ?></td>
                            <td data-label="Lectura"><?= htmlspecialchars((string)$p['decision']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$resumen['productos']): ?>
                        <tr><td colspan="5">No hay ventas para los filtros seleccionados.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
<script>
const reportData = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
const money = value => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value || 0);
const baseOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { labels: { color: '#dbeafe', boxWidth: 10 } },
        tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${ctx.dataset.currency === false ? ctx.parsed.y + '%' : money(ctx.parsed.y)}` } }
    },
    scales: {
        x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,.12)' } },
        y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,.12)' } }
    }
};

function makeChart(id, type, labels, values, label, color, currency = true) {
    const el = document.getElementById(id);
    if (!el) return;
    new Chart(el, {
        type,
        data: { labels, datasets: [{ label, data: values, borderColor: color, backgroundColor: color.replace('1)', '.28)'), tension: .32, borderWidth: 2, currency }] },
        options: baseOptions
    });
}

makeChart('chartVentasFecha', 'line', reportData.fechas, reportData.ventas_fecha, 'Ventas', 'rgba(0,212,255,1)');
makeChart('chartProductos', 'bar', reportData.productos, reportData.ventas_producto, 'Venta total', 'rgba(34,197,94,1)');
makeChart('chartTransportes', 'bar', reportData.transportes, reportData.ventas_transporte, 'Venta total', 'rgba(245,158,11,1)');
makeChart('chartVariacion', 'bar', reportData.productos, reportData.variacion_producto, 'Variacion %', 'rgba(124,58,237,1)', false);
</script>
</body>
</html>
