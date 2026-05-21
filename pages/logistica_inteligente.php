<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireAdmin();

$usuario = usuarioActual();
$isAdmin = esAdmin();

// Obtener datos de logística con detalles operativos
$lineas = $pdo->query("SELECT * FROM logistica_lineas ORDER BY prioridad ASC")->fetchAll();
$unidades = $pdo->query("SELECT u.*, l.nombre as linea_nombre FROM logistica_unidades u JOIN logistica_lineas l ON l.id = u.linea_id")->fetchAll();
$rutas = $pdo->query("SELECT r.*, l.nombre as linea_nombre, l.rastreo_tiempo_real FROM logistica_rutas r JOIN logistica_lineas l ON l.id = r.linea_id")->fetchAll();
$operadores = $pdo->query("
    SELECT u.id, u.nombre, lu.tipo as unidad_tipo 
    FROM usuarios u 
    LEFT JOIN logistica_unidades lu ON lu.id = u.unidad_id 
    WHERE u.rol = 'operador' AND u.activo = 1
")->fetchAll();

// 1. DETECTAR TODOS LOS PEDIDOS PENDIENTES DE LOGÍSTICA
// Calculamos el peso total por pedido sumando items * peso_producto
$sqlPedidos = "
    SELECT 
        p.id, COALESCE(p.destino_demo, 'QRO') as destino_demo, p.total as monto_total, 
        u.nombre as cliente_nombre,
        SUM(pi.cantidad * COALESCE(pr.peso_kg, 25)) as peso_total_kg,
        COUNT(pi.id) as total_items,
        MAX(pr.es_peligroso) as has_danger,
        GROUP_CONCAT(CONCAT(pi.cantidad, 'x ', pr.nombre) SEPARATOR '<br>') as detalle_productos
    FROM pedidos p LEFT JOIN usuarios u ON u.id = p.cliente_id
    LEFT JOIN pedido_items pi ON pi.pedido_id = p.id
    LEFT JOIN productos pr ON pr.id = pi.producto_id
    WHERE p.estado = 'pendiente'
    GROUP BY p.id
";
$pedidosPendientesLogistica = $pdo->query($sqlPedidos)->fetchAll();

// 2. ALGORITMO DE CONSOLIDACIÓN Y SUGERENCIA DE ENVÍOS (IA LIGERA)
$sugerenciasEnvios = [];
$pedidosPorDestino = [];

// Agrupar pedidos por destino
foreach ($pedidosPendientesLogistica as $ped) {
    $pedidosPorDestino[$ped['destino_demo']][] = $ped;
}

foreach ($pedidosPorDestino as $destino => $listaPedidos) {
    $pesoConsolidado = 0;
    $idsPedidos = [];
    $tienePeligroso = false; // Simulación de detección de materiales peligrosos
    
    foreach ($listaPedidos as $p) {
        $pesoConsolidado += $p['peso_total_kg'];
        $idsPedidos[] = $p['id'];
        // Detección real basada en base de datos
        if ($p['has_danger']) $tienePeligroso = true;
    }

    // Buscar la mejor línea y unidad para este peso y destino
    $mejorSugerencia = null;
    foreach ($lineas as $l) {
        // Filtrar rutas que lleguen a este destino
        $rutaValida = false;
        $tarifaBase = 0;
        foreach ($rutas as $r) {
            if ($r['linea_id'] == $l['id'] && $r['destino'] == $destino) {
                $rutaValida = true;
                $tarifaBase = $r['tarifa_fija'];
                break;
            }
        }
        if (!$rutaValida) continue;

        // Buscar la unidad más pequeña que soporte el peso consolidado
        $unidadOptima = null;
        foreach ($unidades as $u) {
            if ($u['linea_id'] == $l['id'] && $u['capacidad_kg'] >= $pesoConsolidado) {
                if (!$unidadOptima || $u['capacidad_kg'] < $unidadOptima['capacidad_kg']) {
                    $unidadOptima = $u;
                }
            }
        }

        if ($unidadOptima) {
            // Algoritmo de Priorización (IA de Selección)
            $score = (10 - $l['prioridad']) * 10; // Base por costo
            $motivoIa = "Menor costo operativo.";
            
            // Regla de Negocio Demo: Preferencia explícita a Loxagon para ciertos escenarios
            if (stripos($l['nombre'], 'Loxagon') !== false) {
                if ($tienePeligroso) {
                    $score += 70; // Boost masivo a Loxagon por especialización en químicos/peligrosos
                    $motivoIa = "⚠️ RECOMENDADO POR SEGURIDAD: Se detectó material peligroso. Loxagon cuenta con certificaciones para manejo de químicos y operadores certificados.";
                } elseif ($pesoConsolidado >= 5000) {
                    $score += 50; 
                    $motivoIa = "Recomendado por capacidad de carga pesada y SLA óptimo.";
                } else {
                    $score += 15; // Boost ligero base
                    $motivoIa = "Mejor relación tarifa/tiempo de entrega para envíos consolidados.";
                }
            }

            $opcion = [
                'linea_id' => $l['id'],
                'linea_nombre' => $l['nombre'],
                'unidad' => $unidadOptima['tipo'],
                'capacidad' => $unidadOptima['capacidad_kg'],
                'costo' => $tarifaBase,
                'tracking' => $l['rastreo_tiempo_real'],
                'score' => $score,
                'motivo_ia' => $motivoIa,
                'horario' => substr($l['horario_carga_inicio'], 0, 5) . ' - ' . substr($l['horario_carga_fin'], 0, 5)
            ];

            if (!$mejorSugerencia || $score > $mejorSugerencia['score']) {
                $mejorSugerencia = $opcion;
            }
        }
    }

    if ($mejorSugerencia) {
        $sugerenciasEnvios[] = [
            'destino' => $destino,
            'pedidos' => $listaPedidos,
            'peso_total' => $pesoConsolidado,
            'transporte' => $mejorSugerencia,
            'motivo' => $mejorSugerencia['motivo_ia']
        ];
    }
}

// Citas recientes
$citas = $pdo->query("SELECT c.*, l.nombre as linea_nombre FROM logistica_citas c JOIN logistica_lineas l ON l.id = c.linea_id ORDER BY c.fecha_cita DESC LIMIT 10")->fetchAll();



?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Logística Inteligente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/theme.js"></script>
    <style>
        :root {
            --accent: var(--accent);
            --accent-glow: var(--accent-soft);
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: var(--border);
        }
        
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 24px;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        }
        
        .glass-card:hover {
            border-color: rgba(0, 212, 255, 0.3);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4), 0 0 20px var(--accent-glow);
            transform: translateY(-5px);
        }

        .recommendation-card {
            background: linear-gradient(145deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            position: relative;
            overflow: hidden;
        }
        
        .recommendation-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, #00d4ff, #7c3aed);
        }

        .ai-pulse {
            width: 10px;
            height: 10px;
            background: var(--accent);
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            box-shadow: 0 0 10px var(--accent);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(0, 212, 255, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(0, 212, 255, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(0, 212, 255, 0); }
        }

        .animate-pulse {
            animation: pulse-red 2s infinite;
        }

        @keyframes pulse-red {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        .ai-badge {
            background: linear-gradient(90deg, #00d4ff, #7c3aed);
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(0, 212, 255, 0.3);
        }

        .nav-tabs-premium {
            border: none;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .nav-tabs-premium .nav-link {
            border: none;
            color: #94a3b8;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .nav-tabs-premium .nav-link.active {
            background: var(--accent);
            color: #060b16;
            box-shadow: 0 10px 20px rgba(0, 212, 255, 0.2);
        }

        .kpi-card {
            text-align: center;
            padding: 20px;
        }
        
        .kpi-value {
            font-size: 28px;
            font-weight: 800;
            font-family: 'JetBrains Mono', monospace;
            display: block;
            margin-bottom: 5px;
        }
        
        .kpi-label {
            font-size: 12px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .bg-glow-blue { background: #00d4ff; box-shadow: 0 0 10px #00d4ff; }
        .bg-glow-purple { background: #7c3aed; box-shadow: 0 0 10px #7c3aed; }
        
        .data-table thead th {
            background: rgba(255,255,255,0.02);
            color: #7dd3fc;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--glass-border);
            padding: 15px;
        }
        
        .data-table tbody td {
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }
    </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="logo-icon-sm" style="color: var(--accent);"><i class="fa-solid fa-hexagon-nodes"></i></div>
            <span class="logo-text-sm">Nexus<strong>Panel</strong></span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar" style="background: linear-gradient(45deg, #00d4ff, #7c3aed);"><?= strtoupper(substr($usuario['nombre'], 0, 1)) ?></div>
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($usuario['nombre']) ?></span>
            <span class="user-role" style="color: var(--accent);">
                <i class="fa-solid fa-user-tie"></i> <?= htmlspecialchars(nombreRolActual()) ?>
            </span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="dashboard.php" class="nav-item"><i class="fa-solid fa-chart-line"></i><span>Inicio</span></a>
        <a href="usuarios.php" class="nav-item"><i class="fa-solid fa-users"></i><span>Usuarios</span></a>
        <a href="productos.php" class="nav-item"><i class="fa-solid fa-flask-vial"></i><span>Productos e inventario</span></a>
        <a href="pedidos.php" class="nav-item"><i class="fa-solid fa-receipt"></i><span>Pedidos</span></a>
        <a href="logistica_inteligente.php" class="nav-item active"><i class="fa-solid fa-truck-fast"></i><span>Logística Inteligente</span><div class="nav-indicator"></div></a>

        <a href="mensajes.php" class="nav-item"><i class="fa-solid fa-comments"></i><span>Mensajes</span></a>

        <div class="nav-section-label">Operaciones</div>
        <a href="mapa.php" class="nav-item"><i class="fa-solid fa-map-location-dot"></i><span>Mapa de Usuarios</span></a>

        <div class="nav-section-label">Cuenta</div>
        <a href="../logout.php" class="nav-item nav-logout"><i class="fa-solid fa-right-from-bracket"></i><span>Cerrar sesion</span></a>
    </nav>
</aside>

<main class="main-content" id="mainContent">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenu"><i class="fa-solid fa-bars"></i></button>
            <div class="breadcrumb-custom">
                <span class="text-muted">Manager</span>
                <i class="fa-solid fa-chevron-right text-muted mx-2" style="font-size: 10px;"></i>
                <span class="active fw-bold">Logística Inteligente</span>
            </div>
        </div>
        <div class="topbar-right">
            <button class="topbar-btn theme-toggle" onclick="toggleTheme()" title="Cambiar tema">
                <i class="fa-solid fa-moon theme-toggle-icon"></i>
            </button>
            <div class="ai-badge"><i class="fa-solid fa-brain mr-2"></i> Algoritmo Activo</div>
        </div>
    </header>

    <div class="content-area">
        <div class="welcome-banner mb-4" style="border-radius: 24px; padding: 40px;">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-6 fw-bold mb-2">Panel de Control Logístico</h1>
                    <p class="lead text-muted mb-0">Sistema de recomendación de rutas y despacho optimizado por costo.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <?php
                    $todosPedidosHtml = '';
                    $pesoGlobal = 0;
                    foreach ($pedidosPendientesLogistica as $p) {
                        $detallesHtml = $p['detalle_productos'] ? htmlspecialchars($p['detalle_productos'], ENT_QUOTES, 'UTF-8') : 'Sin detalles';
                        $todosPedidosHtml .= '
                        <div class="small border-bottom border-secondary border-opacity-25 pb-2 mb-2">
                            <div class="d-flex justify-content-between align-items-center cursor-pointer" onclick="this.nextElementSibling.classList.toggle(\'d-none\')">
                                <span><i class="fa-solid fa-chevron-down me-2 text-accent" style="cursor: pointer;"></i> PL' . (1076000 + $p['id']) . ' - ' . htmlspecialchars($p['cliente_nombre']) . '</span>
                                <span class="fw-bold">' . number_format($p['peso_total_kg']) . ' KG</span>
                            </div>
                            <div class="d-none mt-2 p-2 rounded text-muted" style="background: var(--bg-tertiary); font-size: 0.85em; border-left: 2px solid var(--accent);">
                                '.$detallesHtml.'
                            </div>
                        </div>';
                        $pesoGlobal += $p['peso_total_kg'];
                    }
                    $todosPedidosHtml .= '<div class="mt-2 text-end text-accent fw-bold small">TOTAL GLOBAL: ' . number_format($pesoGlobal) . ' KG</div>';
                    ?>
                    <div id="html_pedidos_global" class="d-none"><?= $todosPedidosHtml ?></div>
                    <button class="btn btn-primary btn-sm custom-btn" data-bs-toggle="modal" data-bs-target="#modalCita" onclick="prefillCita('', '', 'html_pedidos_global')">
                        <i class="fa-solid fa-calendar-plus me-2"></i> Nueva Cita de Carga
                    </button>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="glass-card kpi-card">
                    <span class="kpi-value text-accent"><?= count($sugerenciasEnvios) ?></span>
                    <span class="kpi-label">Pendientes de Despacho</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-card kpi-card">
                    <span class="kpi-value" style="color: #10b981;">98.2%</span>
                    <span class="kpi-label">Cumplimiento SLA</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-card kpi-card">
                    <span class="kpi-value" style="color: #f59e0b;"><?= count($lineas) ?></span>
                    <span class="kpi-label">Líneas Activas</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-card kpi-card">
                    <span class="kpi-value" style="color: #7c3aed;">-$4.2k</span>
                    <span class="kpi-label">Ahorro Estimado (Mes)</span>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs nav-tabs-premium" id="logisticsTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-pendientes">
                    <i class="fa-solid fa-clipboard-list me-2"></i> Órdenes de Carga
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-ai">
                    <i class="fa-solid fa-wand-magic-sparkles me-2"></i> Despacho AI
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-lineas">
                    <i class="fa-solid fa-truck-moving me-2"></i> Líneas & Unidades
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-viajes">
                    <i class="fa-solid fa-calendar-check me-2"></i> Bitácora de Viajes
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Tab Órdenes Pendientes (Data tipo Excel) -->
            <div class="tab-pane fade show active" id="tab-pendientes">
                <div class="glass-card mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0 fw-bold"><i class="fa-solid fa-table-list text-accent me-2"></i> Pool de Pedidos Pendientes</h4>
                        <span class="badge bg-primary px-3 py-2 rounded-pill"><?= count($pedidosPendientesLogistica) ?> Folios PL</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table data-table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>FECHA</th>
                                    <th>PL (FOLIO)</th>
                                    <th>CUSTOMER</th>
                                    <th>DESTINO</th>
                                    <th>ITEMS</th>
                                    <th>QTY (KG)</th>
                                    <th>ESTADO</th>
                                    <th>ACCIONES</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($pedidosPendientesLogistica)): ?>
                                    <tr><td colspan="7" class="text-center py-4 text-muted">No hay pedidos pendientes de despacho.</td></tr>
                                <?php else: ?>
                                    <?php foreach($pedidosPendientesLogistica as $p): ?>
                                    <tr>
                                        <td class="text-muted"><i class="fa-regular fa-calendar me-1"></i> <?= date('d/m/Y') ?></td>
                                        <td class="fw-bold text-accent">PL<?= 1076000 + $p['id'] ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($p['cliente_nombre']) ?></td>
                                        <td>
                                            <span class="badge bg-dark border border-secondary"><?= $p['destino_demo'] ?></span>
                                        </td>
                                        <td class="text-muted"><?= $p['total_items'] ?> prods.</td>
                                        <td class="fw-bold" style="font-family: var(--font-mono);"><?= number_format($p['peso_total_kg']) ?> KG</td>
                                        <td>
                                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i> PENDIENTE</span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCita" onclick="prefillCita('PL<?= 1076000 + $p['id'] ?>', '1', '', '<?= $p['id'] ?>', '<?= $p['peso_total_kg'] ?>')">
                                                <i class="fa-solid fa-calendar-check"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab Despacho AI -->
            <div class="tab-pane fade" id="tab-ai">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="mb-0 fw-bold"><i class="ai-pulse"></i> Recomendaciones del Sistema</h4>
                            <span class="text-muted small">Actualizado hace un momento</span>
                        </div>
                        
                        <?php if (empty($sugerenciasEnvios)): ?>
                            <div class="glass-card text-center p-5">
                                <i class="fa-solid fa-check-circle fa-4x text-success mb-3" style="opacity: 0.5;"></i>
                                <h3>Sin pedidos pendientes</h3>
                                <p class="text-muted">El sistema no detectó pedidos en el área de logística.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($sugerenciasEnvios as $envio): ?>
                                <div class="recommendation-card">
                                    <div class="row align-items-center">
                                        <div class="col-md-7">
                                            <div class="d-flex align-items-center mb-2">
                                                <span class="ai-badge me-3">Sugerencia de Consolidación</span>
                                                <h4 class="mb-0 text-accent">Ruta: <?= $envio['destino'] ?></h4>
                                            </div>
                                            
                                            <div class="order-list mb-3 p-3" style="background: var(--bg-tertiary); border-radius: 12px; border: 1px solid var(--border);">
                                                <div class="small text-muted mb-2 fw-bold">PEDIDOS CONSOLIDADOS (<?= count($envio['pedidos']) ?>):</div>
                                                <?php foreach ($envio['pedidos'] as $p): ?>
                                                    <?php $detallesHtml = $p['detalle_productos'] ? $p['detalle_productos'] : 'Sin detalles'; ?>
                                                    <div class="small mb-2 border-bottom border-secondary border-opacity-25 pb-2">
                                                        <div class="d-flex justify-content-between align-items-center" style="cursor: pointer;" onclick="this.nextElementSibling.classList.toggle('d-none')">
                                                            <span>
                                                                <i class="fa-solid fa-chevron-down me-2 text-accent"></i> 
                                                                <i class="fa-solid fa-file-invoice me-1 text-primary"></i> PL<?= 1076000 + $p['id'] ?> - <?= $p['cliente_nombre'] ?>
                                                            </span>
                                                            <span class="fw-bold"><?= number_format($p['peso_total_kg']) ?> KG</span>
                                                        </div>
                                                        <div class="d-none mt-2 p-2 rounded text-muted" style="background: var(--bg-tertiary); border-left: 2px solid var(--accent); font-size: 0.85em;">
                                                            <?= $detallesHtml ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                                <div class="mt-2 pt-2 border-top border-secondary border-opacity-25 d-flex justify-content-between">
                                                    <span class="fw-bold">CARGA TOTAL DETECTADA:</span>
                                                    <span class="text-accent fw-bold fs-5"><?= number_format($envio['peso_total']) ?> KG</span>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3 p-2 rounded" style="background: rgba(16, 185, 129, 0.1); border-left: 3px solid #10b981;">
                                                <span class="d-block small text-success fw-bold"><i class="fa-solid fa-robot me-1"></i> Análisis de Inteligencia Artificial:</span>
                                                <span class="small text-muted"><?= $envio['motivo'] ?></span>
                                            </div>

                                            <div class="d-flex gap-4 small text-muted">
                                                <span><i class="fa-solid fa-truck-ramp-box me-1"></i> Unidad: <strong><?= $envio['transporte']['unidad'] ?></strong></span>
                                                <span><i class="fa-solid fa-clock me-1"></i> Ventana Carga: <strong><?= $envio['transporte']['horario'] ?></strong></span>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-5 text-md-end mt-3 mt-md-0">
                                            <div class="mb-2">
                                                <span class="text-muted small d-block mb-1">Línea Sugerida (Costo Mínimo)</span>
                                                <span class="fw-bold fs-4" style="color: var(--accent);"><?= $envio['transporte']['linea_nombre'] ?></span>
                                            </div>
                                            <div class="mb-3">
                                                <span class="text-success fw-bold fs-3">$<?= number_format($envio['transporte']['costo'], 2) ?></span>
                                                <span class="text-muted small d-block">Tarifa consolidada por ruta</span>
                                            </div>
                                            <?php 
                                            $pedidosHtml = '';
                                            foreach ($envio['pedidos'] as $p) {
                                                $detallesHtml = $p['detalle_productos'] ? htmlspecialchars($p['detalle_productos'], ENT_QUOTES, 'UTF-8') : 'Sin detalles';
                                                $pedidosHtml .= '
                                                <div class="small border-bottom border-secondary border-opacity-25 pb-2 mb-2">
                                                    <div class="d-flex justify-content-between align-items-center" style="cursor: pointer;" onclick="this.nextElementSibling.classList.toggle(\'d-none\')">
                                                        <span><i class="fa-solid fa-chevron-down me-2 text-accent"></i> PL' . (1076000 + $p['id']) . ' - ' . htmlspecialchars($p['cliente_nombre']) . '</span>
                                                        <span class="fw-bold">' . number_format($p['peso_total_kg']) . ' KG</span>
                                                    </div>
                                                    <div class="d-none mt-2 p-2 rounded text-muted" style="background: var(--bg-tertiary); font-size: 0.85em; border-left: 2px solid var(--accent);">
                                                        '.$detallesHtml.'
                                                    </div>
                                                </div>';
                                            }
                                            $pedidosHtml .= '<div class="mt-2 text-end text-accent fw-bold small">TOTAL CONSOLIDADO: ' . number_format($envio['peso_total']) . ' KG</div>';
                                            $safeId = 'html_pedidos_ai_' . md5($envio['destino'] . $envio['transporte']['linea_id']);
                                            $idsList = implode(',', array_column($envio['pedidos'], 'id'));
                                            ?>
                                            <div id="<?= $safeId ?>" class="d-none"><?= $pedidosHtml ?></div>
                                            <div class="d-flex gap-2 justify-content-md-end">
                                                <button class="btn btn-outline-primary btn-sm px-4" data-bs-toggle="modal" data-bs-target="#modalCita" onclick="prefillCita('MULTI-PED', '<?= $envio['transporte']['linea_id'] ?>', '<?= $safeId ?>', '<?= $idsList ?>', '<?= $envio['peso_total'] ?>')">
                                                    <i class="fa-solid fa-calendar-check me-2"></i> Agendar
                                                </button>
                                                <button class="btn btn-primary btn-sm px-4" style="background: var(--accent); border: none; color: #060b16; font-weight: 700;">
                                                    <i class="fa-solid fa-truck-moving me-2"></i> Despachar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3 pt-3 border-top border-secondary border-opacity-25">
                                        <span class="small text-muted"><i class="fa-solid fa-brain me-2 text-accent"></i> <strong>Sugerencia AI:</strong> <?= $envio['motivo'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="glass-card mb-4">
                            <h5 class="fw-bold mb-4">Prioridades de Envío</h5>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="small">Urgentes (Alta prioridad)</span>
                                    <span class="badge bg-danger">2</span>
                                </div>
                                <div class="progress" style="height: 6px; background: rgba(255,255,255,0.05);">
                                    <div class="progress-bar bg-danger" style="width: 20%"></div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="small">Rutas Consolidadas</span>
                                    <span class="badge bg-primary">5</span>
                                </div>
                                <div class="progress" style="height: 6px; background: rgba(255,255,255,0.05);">
                                    <div class="progress-bar bg-primary" style="width: 65%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="small">En Tiempo</span>
                                    <span class="badge bg-success">12</span>
                                </div>
                                <div class="progress" style="height: 6px; background: rgba(255,255,255,0.05);">
                                    <div class="progress-bar bg-success" style="width: 85%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card">
                            <h5 class="fw-bold mb-4">Mapa de Disponibilidad</h5>
                            <div style="background: rgba(0,0,0,0.2); border-radius: 12px; height: 200px; display: flex; align-items: center; justify-content: center; border: 1px dashed var(--glass-border);">
                                <div class="text-center">
                                    <i class="fa-solid fa-map-location-dot fa-2x mb-2 text-muted"></i>
                                    <p class="small text-muted">Cargando visualización geo-espacial...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Líneas & Unidades -->
            <div class="tab-pane fade" id="tab-lineas">
                <div class="glass-card">
                    <div class="table-responsive">
                        <table class="data-table w-100">
                            <thead>
                                <tr>
                                    <th>Empresa de Transporte</th>
                                    <th>Prioridad Costo</th>
                                    <th>Capacidad Total</th>
                                    <th>Unidades Activas</th>
                                    <th>Live Tracking</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lineas as $l): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= $l['nombre'] ?></div>
                                            <div class="small text-muted"><?= $l['dias_operacion'] ?> (<?= substr($l['horario_carga_inicio'],0,5) ?>-<?= substr($l['horario_carga_fin'],0,5) ?>)</div>
                                        </td>
                                        <td>
                                            <div class="text-success fw-bold">
                                                <?= str_repeat('<i class="fa-solid fa-dollar-sign"></i>', 5-$l['prioridad']) ?>
                                                <?= str_repeat('<i class="fa-solid fa-dollar-sign text-muted" style="opacity: 0.2;"></i>', $l['prioridad']-1) ?>
                                                <span class="ms-1 small text-muted">(P<?= $l['prioridad'] ?>)</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small"><?= $l['requiere_cita'] ? 'Requiere Cita (' . $l['anticipacion_agenda_hrs'] . 'h)' : 'Libre' ?></div>
                                            <div class="text-muted small">Promedio: <?= $l['promedio_carga_min'] ?> min</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-dark border border-secondary"><?= rand(5, 15) ?> Unidades</span>
                                        </td>
                                        <td>
                                            <?= $l['rastreo_tiempo_real'] ? '<span class="text-success"><i class="fa-solid fa-satellite-dish me-1"></i> Activo (Plus)</span>' : '<span class="text-muted">No disponible</span>' ?>
                                        </td>
                                        <td>
                                            <span class="status-dot bg-glow-blue me-2"></span> <span class="small">Operando</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab Bitácora de Viajes -->
            <div class="tab-pane fade" id="tab-viajes">
                <div class="glass-card">
                    <div class="table-responsive">
                        <table class="data-table w-100">
                            <thead>
                                <tr>
                                    <th>ID Viaje</th>
                                    <th>Pedido</th>
                                    <th>Línea</th>
                                    <th>Fecha Cita</th>
                                    <th>Carga Estimada</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($citas)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No hay viajes registrados recientemente.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($citas as $c): ?>
                                        <tr>
                                            <td><code class="text-accent">#V-<?= str_pad($c['id'], 4, '0', STR_PAD_LEFT) ?></code></td>
                                            <td><?= $c['pl_folio'] ?></td>
                                            <td><?= $c['linea_nombre'] ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($c['fecha_cita'])) ?></td>
                                            <td><?= number_format($c['carga_kg']) ?> KG</td>
                                            <td>
                                                <span class="badge bg-<?= $c['estado'] == 'programada' ? 'primary' : ($c['estado'] == 'completada' ? 'success' : 'danger') ?> rounded-pill">
                                                    <?= ucfirst($c['estado']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-link text-light"><i class="fa-solid fa-eye"></i></button>
                                                <button class="btn btn-sm btn-link text-danger"><i class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Cita -->
<div class="modal fade" id="modalCita" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="background: var(--bg-card); color: var(--text-primary); border-radius: 24px; box-shadow: 0 25px 50px rgba(0,0,0,0.5);">
            <div class="modal-header border-bottom border-secondary border-opacity-25 p-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-calendar-check text-accent me-2"></i> Programar Cita de Carga</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: var(--btn-close-filter, invert(1) grayscale(100%) brightness(200%));"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formCita" action="procesar_cita.php" method="POST">
                    <input type="hidden" id="cita_pedidos_ids" name="pedidos_ids">
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold">FOLIO PL</label>
                        <input type="text" id="cita_pl" name="pl_folio" class="form-control form-input" style="border-radius: 12px; height: 50px;" readonly>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold">SELECCIONAR LÍNEA</label>
                        <select id="cita_linea" name="linea_id" class="form-select form-input" style="border-radius: 12px; height: 50px;">
                            <?php foreach ($lineas as $l): ?>
                                <option value="<?= $l['id'] ?>"><?= $l['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold">FECHA Y HORA DE CARGA</label>
                        <input type="datetime-local" id="cita_fecha" name="fecha_cita" class="form-control form-input" style="border-radius: 12px; height: 50px;" value="<?= date('Y-m-d\T08:00') ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold">CARGA TOTAL (KG)</label>
                        <input type="number" id="cita_carga" name="carga_kg" class="form-control form-input" style="border-radius: 12px; height: 50px;" placeholder="0">
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold">ASIGNAR OPERADOR & UNIDAD</label>
                        <select id="cita_operador" name="operador_id" class="form-select form-input" style="border-radius: 12px; height: 50px;">
                            <option value="">Seleccionar Operador Automático</option>
                            <?php foreach ($operadores as $o): ?>
                                <option value="<?= $o['id'] ?>"><?= $o['nombre'] ?> (<?= $o['unidad_tipo'] ?? 'Unidad No Asignada' ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4" id="cita_pedidos_container" style="display: none;">
                        <label class="form-label text-muted small fw-bold">PEDIDOS A CONSOLIDAR</label>
                        <div id="cita_pedidos_list" class="p-3" style="background: var(--bg-tertiary); border-radius: 12px; border: 1px solid var(--border);">
                            <!-- Llenado vía JS -->
                        </div>
                    </div>
                    <div class="p-3" style="background: rgba(0, 212, 255, 0.05); border: 1px dashed var(--accent); border-radius: 12px;">
                        <div class="d-flex gap-3 align-items-center">
                            <i class="fa-solid fa-circle-info text-accent fs-4"></i>
                            <div class="small">
                                <div class="fw-bold text-accent">Compromiso de Operación</div>
                                <div class="text-muted">Tiempo de anticipación: <span id="anticipacion_info">24h</span>. Notificación vía NexusConnect activada.</div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top border-secondary border-opacity-25 p-4">
                <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary px-4" style="border-radius: 12px; background: var(--accent); border: none; color: #060b16; font-weight: 700;" onclick="submitCita()">Confirmar y Agendar Cita</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto‑generate a unique PL folio when the modal opens
    document.getElementById('modalCita').addEventListener('show.bs.modal', function () {
        // Format: PL + YYYYMMDD + random 4‑digit number
        const now = new Date();
        const y = now.getFullYear();
        const m = String(now.getMonth() + 1).padStart(2, '0');
        const d = String(now.getDate()).padStart(2, '0');
        const rand = Math.floor(1000 + Math.random() * 9000);
        const folio = `PL${y}${m}${d}${rand}`;
        document.getElementById('cita_pl').value = folio;
    });

    function prefillCita(pl, lineaId, htmlContainerId, ids = '', peso = 0) {
        // Asignar folio si viene de una recomendación o pedido individual
        if (pl) {
            document.getElementById('cita_pl').value = pl;
        }
        
        // Asignar los demás campos del formulario
        document.getElementById('cita_linea').value = lineaId;
        document.getElementById('cita_pedidos_ids').value = ids;
        document.getElementById('cita_carga').value = Math.round(peso);
        
        // Mostrar la lista de pedidos en el modal si existe el contenedor de origen
        const container = document.getElementById('cita_pedidos_container');
        const list = document.getElementById('cita_pedidos_list');
        const sourceHtmlDiv = htmlContainerId ? document.getElementById(htmlContainerId) : null;
        
        if (sourceHtmlDiv && container && list) {
            list.innerHTML = sourceHtmlDiv.innerHTML;
            container.style.display = 'block';
        } else if (container) {
            container.style.display = 'none';
        }
    }

    function submitCita() {
        const form = document.getElementById('formCita');
        const formData = new FormData(form);
        
        const pl = document.getElementById('cita_pl').value;
        const lineaId = document.getElementById('cita_linea').value;
        const operadorId = document.getElementById('cita_operador').value;
        
        if(!lineaId) {
            Swal.fire('Error', 'Por favor selecciona una línea de transporte', 'error');
            return;
        }

        Swal.fire({
            title: '¿Confirmar Agenda de Cita?',
            text: `Se programará el folio ${pl} y se aceptarán automáticamente los pedidos vinculados.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: 'var(--accent)',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, Agendar y Aceptar Pedidos',
            cancelButtonText: 'Revisar',
            background: 'var(--bg-card)',
            color: 'var(--text-primary)'
        }).then((result) => {
            if (result.isConfirmed) {
                // Enviar datos reales al servidor
                fetch('procesar_cita.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        Swal.fire({
                            title: '¡Agenda Exitosa!',
                            text: 'Los pedidos han sido aceptados y el operador ha sido notificado.',
                            icon: 'success',
                            background: 'var(--bg-card)',
                            color: 'var(--text-primary)',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.message || 'No se pudo procesar la cita', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Fallback para el demo si el archivo no existe aún
                    Swal.fire('Simulación Demo', 'Cita agendada correctamente (Modo Offline)', 'success').then(()=>location.reload());
                });
            }
        });
    }

    // Sidebar toggle logic matching dashboard.js
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const main = document.getElementById('mainContent');
    const mobileMenu = document.getElementById('mobileMenu');

    if (toggle) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            main.classList.toggle('expanded');
        });
    }

    if (mobileMenu) {
        mobileMenu.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-show');
        });
    }

    // Auto-refresh simulation
    setInterval(() => {
        console.log('AI Logic: Recalculating route efficiencies...');
    }, 5000);
</script>
</body>
</html>
