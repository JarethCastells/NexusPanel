<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireAuth();

$u = usuarioActual();
$pedidoId = (int)($_GET['pedido_id'] ?? $_GET['nuevo'] ?? 0);

// Obtener todos los pedidos del cliente
$todosPedidos = $pdo->prepare("
    SELECT p.*, u.nombre AS operador_nombre
    FROM pedidos p LEFT JOIN usuarios u ON u.id=p.operador_id
    WHERE p.cliente_id=? ORDER BY p.id DESC
");
$todosPedidos->execute([$u['usuario_id']]);
$todosPedidos = $todosPedidos->fetchAll();

// Si no hay pedido seleccionado, usar el mas reciente
if (!$pedidoId && !empty($todosPedidos)) {
    $pedidoId = $todosPedidos[0]['id'];
}

// Pedido activo seleccionado
$pedido = null;
$itemsPedido = [];
if ($pedidoId) {
    $st = $pdo->prepare("SELECT p.*,u.nombre AS op_nombre,u.telefono AS op_tel,u.lat AS op_lat,u.lng AS op_lng FROM pedidos p LEFT JOIN usuarios u ON u.id=p.operador_id WHERE p.id=? AND p.cliente_id=?");
    $st->execute([$pedidoId, $u['usuario_id']]);
    $pedido = $st->fetch();

    if ($pedido) {
        $stItems = $pdo->prepare("
            SELECT
                pi.*,
                COALESCE(pr.nombre, CONCAT('Producto #', pi.producto_id)) AS nombre,
                COALESCE(pr.codigo, '') AS codigo,
                COALESCE(pr.imagen, '') AS imagen,
                COALESCE(pr.unidad_medida, 'piezas') AS unidad_medida
            FROM pedido_items pi
            LEFT JOIN productos pr ON pr.id = pi.producto_id
            WHERE pi.pedido_id = ?
        ");
        $stItems->execute([$pedidoId]);
        $itemsPedido = $stItems->fetchAll();
    }
}

$estadoLabels = ['pendiente'=>'Pendiente','aceptado'=>'Aceptado','en_camino'=>'En camino','entregado'=>'Entregado','cancelado'=>'Cancelado'];
$estadoColors = ['pendiente'=>'#f59e0b','aceptado'=>'#10b981','en_camino'=>'#00d4ff','entregado'=>'#7c3aed','cancelado'=>'#ef4444'];
$estadoDescriptions = [
    'pendiente' => 'Tu pedido esta esperando ser asignado a un operador',
    'aceptado' => 'Un operador ha aceptado tu pedido y se esta preparando',
    'en_camino' => 'Tu pedido esta en camino, pronto llegara',
    'entregado' => 'Tu pedido ha sido entregado exitosamente',
    'cancelado' => 'Este pedido fue cancelado',
];

$promocionesPedido = [];
if (!empty($itemsPedido)) {
    $promoTitulosPedido = ['Combo recomendado', 'Oferta de temporada', 'Entrega prioritaria'];
    $promoMensajesPedido = [
        'Ideal para repetir en tu siguiente compra.',
        'Disponible para agregar rapidamente al carrito.',
        'Producto con alta rotacion para entrega agil.',
    ];
    foreach (array_slice($itemsPedido, 0, min(3, count($itemsPedido))) as $idxPromo => $itPromo) {
        $promocionesPedido[] = [
            'titulo' => $promoTitulosPedido[$idxPromo] ?? 'Promocion disponible',
            'mensaje' => $promoMensajesPedido[$idxPromo] ?? 'Disponible para tu siguiente pedido.',
            'producto_id' => (int)($itPromo['producto_id'] ?? 0),
            'nombre' => (string)($itPromo['nombre'] ?? 'Producto'),
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Mis Pedidos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/pedidos.css">
    <style>
        /* ==============================================================================
       NEXUSPANEL CLIENTE: LAYOUT BASE, MAPA Y CHAT FLOTANTE
       ============================================================================== */
    
    /* â”€â”€ Layout Principal â”€â”€ */
    .detalle-grid { 
        display: flex; gap: 20px; align-items: stretch; margin-top: 20px; 
        height: calc(100vh - 280px); min-height: 500px;
    }
    .detalle-info { 
        width: 340px; flex-shrink: 0; display: flex; flex-direction: column; overflow-y: auto;
    }
    .detalle-info::-webkit-scrollbar { width: 4px; }
    .detalle-info::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

    /* â”€â”€ Contenedor del Mapa (La Esponja) â”€â”€ */
    .mapa-wrap { 
        flex: 1; display: flex; flex-direction: column; position: relative;
        border-radius: 16px; overflow: hidden; border: 1px solid rgba(255,255,255,0.08);
        background: #0a0f1c; 
    }
    #trackingMapa { width: 100%; height: 100%; flex: 1; display: block !important; }

    /* â”€â”€ Textos flotantes del mapa (EN VIVO) â”€â”€ */
    .mapa-legend {
        position: absolute; top: 16px; left: 16px; z-index: 1000;
        display: flex; flex-direction: column; gap: 6px;
        background: rgba(6,10,18,0.88); backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.12); border-radius: 12px;
        padding: 10px 16px; font-size: 11px; color: #94a3b8;
    }
    .live-badge { display: inline-flex; align-items: center; gap: 6px; color: #ef4444; font-weight: 700; font-family: var(--font-mono); }
    .blink-dot { width: 6px; height: 6px; border-radius: 50%; background: #ef4444; animation: blink 1s infinite; }

    /* â”€â”€ FAB Chat flotante (Cliente) â”€â”€ */
    .chat-fab {
        position: absolute; bottom: 20px; right: 20px; z-index: 1000;
        display: flex; align-items: center; gap: 10px;
        padding: 12px 20px; background: linear-gradient(135deg, #3b82f6, #2563eb);
        border: none; border-radius: 100px; color: #fff; 
        font-family: var(--font-main); font-size: 14px; font-weight: 700; cursor: pointer;
        box-shadow: 0 4px 20px rgba(59,130,246,0.4);
    }
    .fab-badge {
        background: #ef4444; color: #fff; padding: 2px 6px; border-radius: 100px;
        font-size: 11px; margin-left: -4px;
    }

    /* â”€â”€ Modal del Chat (Cliente) â”€â”€ */
    .chat-modal {
        position: absolute; bottom: 80px; right: 20px; z-index: 1001;
        width: 340px; height: 450px; background: #0d1422;
        border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;
        display: flex; flex-direction: column; overflow: hidden;
        box-shadow: 0 10px 40px rgba(0,0,0,0.5);
    }
    /* Â¡Clave para que no se vea siempre abierto! */
    .chat-modal.hidden { display: none !important; }
    
    .chat-modal-header {
        display: flex; justify-content: space-between; align-items: center;
        padding: 12px 16px; background: rgba(255,255,255,0.03); border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .chat-modal-info { display: flex; align-items: center; gap: 10px; color: #fff; font-weight: 600; }
    .chat-modal-dot { width: 32px; height: 32px; background: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; }
    .chat-modal-close { background: transparent; border: none; color: #94a3b8; cursor: pointer; font-size: 18px; }
    .wc-messages { flex: 1; overflow-y: auto; padding: 12px; background: #060a12; }
    .wc-input-bar { display: flex; padding: 12px; background: rgba(255,255,255,0.03); border-top: 1px solid rgba(255,255,255,0.08); gap: 8px;}
    .wc-input { flex: 1; padding: 8px 12px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); background: transparent; color: #fff; outline: none; }
    .wc-send { width: 36px; height: 36px; border-radius: 50%; background: var(--primary); border: none; color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center;}

    /* ==============================================================================
       RESPONSIVE CLIENTE (MÃ“VIL)
       ============================================================================== */
    @media (max-width: 900px) {
        .pedidos-layout { flex-direction: column !important; height: auto !important; overflow: auto !important; }
        .pedidos-lista {
            width: 100% !important; max-height: 180px !important; border-right: none !important; border-bottom: 1px solid var(--border) !important;
            display: flex !important; flex-direction: row !important; overflow-x: auto !important; overflow-y: hidden !important; -webkit-overflow-scrolling: touch;
        }
        .pedidos-lista-header { display: none !important; }
        .pedido-item { min-width: 180px !important; flex-shrink: 0 !important; border-bottom: none !important; border-right: 1px solid var(--border) !important; }
        .sin-pedidos { min-width: 200px; }
        
        .detalle-grid { flex-direction: column !important; height: auto !important; padding-bottom: 24px !important; }
        .detalle-info { width: 100% !important; overflow-y: visible !important; }
        .mapa-wrap { width: 100% !important; height: 350px !important; min-height: 350px !important; margin-top: 12px !important; flex: none !important;}
        .chat-fab-standalone { margin-top: 12px; }
    }

    @media (max-width: 768px) {
        /* Chat Pantalla Completa MÃ³vil (Solo cuando NO estÃ¡ oculto) */
        .chat-modal:not(.hidden) {
            position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;
            width: 100vw !important; max-width: 100vw !important; height: 100dvh !important; max-height: 100dvh !important;
            border-radius: 0 !important; z-index: 999999 !important; border: none !important;
            display: flex !important; flex-direction: column !important; margin: 0 !important;
        }
        .chat-modal-header { padding-top: max(16px, env(safe-area-inset-top)); }
        .wc-input-bar { padding-bottom: max(16px, env(safe-area-inset-bottom)); }
        .chat-modal-close { width: 44px; height: 44px; font-size: 20px; }
    }
    /* â”€â”€ CUANDO NO HAY MAPA (PEDIDOS ENTREGADOS) â”€â”€ */
    /* Este es el contenedor que reemplaza al mapa cuando el pedido ya se entregÃ³ */
    .chat-fab-standalone {
        flex: 1; /* ActÃºa como esponja y absorbe la pantalla negra */
        display: flex;
        align-items: center; 
        justify-content: center;
        min-height: 300px;
        background: #0a0f1c; /* Mismo fondo oscuro elegante */
        border-radius: 16px;
        border: 1px solid rgba(255,255,255,0.08);
        margin-top: 10px;
    }
    
    /* El botÃ³n grande para chatear con el operador del pedido entregado */
    .chat-fab-inline {
        display: flex; align-items: center; gap: 10px;
        padding: 14px 28px;
        background: linear-gradient(135deg, #3b82f6, #2563eb); 
        border: none; border-radius: 100px;
        color: #fff; font-family: var(--font-main);
        font-size: 15px; font-weight: 700; cursor: pointer;
        box-shadow: 0 4px 20px rgba(59,130,246,0.3);
        transition: all 0.2s;
    }
    .chat-fab-inline:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(59,130,246,0.5);
    }
    /* Hard-fix movil: asegurar scroll y botones legibles */
    @media (max-width: 768px) {
        .pedidos-layout {
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch;
        }

        .detalle-grid {
            height: auto !important;
            min-height: 0 !important;
            gap: 12px !important;
        }

        .detalle-info {
            max-height: none !important;
            overflow: visible !important;
        }

        .mapa-wrap {
            height: 320px !important;
            min-height: 320px !important;
        }

        .op-actions {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px !important;
            width: 100%;
        }

        .op-actions > * {
            width: 100%;
            justify-content: center !important;
        }
    }

    @media (max-width: 480px) {
        .op-actions { grid-template-columns: 1fr; }
    }
    /* FORZAR AL MAPA A LLENAR EL ESPACIO NEGRO */
    .activo-panel, .delivery-view {
        display: flex !important;
        flex-direction: column !important;
        height: 100% !important;
        flex: 1 !important;
    }
    .delivery-map {
        flex: 1 !important;
    }
    .delivery-bar {
        margin-top: auto !important; /* Empuja la barra hasta el fondo */
    }

    /* Reglas finales para movil/tablet */
    @media (max-width: 900px) {
        .pedidos-layout {
            display: flex !important;
            flex-direction: column !important;
            height: auto !important;
            min-height: calc(100dvh - 64px);
            overflow: visible !important;
        }
        .pedidos-lista {
            width: 100% !important;
            max-height: none !important;
            padding-bottom: 6px;
        }
        .detalle-grid {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
            height: auto !important;
            min-height: 0 !important;
            margin-top: 12px !important;
        }
        .detalle-info {
            width: 100% !important;
            max-height: none !important;
            overflow: visible !important;
        }
        .mapa-wrap {
            width: 100% !important;
            height: 44dvh !important;
            min-height: 280px !important;
            max-height: 420px !important;
            flex: none !important;
        }
        #trackingMapa {
            width: 100% !important;
            height: 100% !important;
        }
        .chat-fab {
            bottom: 12px !important;
            right: 12px !important;
            padding: 10px 14px !important;
            font-size: 13px !important;
        }
        .chat-fab-standalone {
            min-height: 180px !important;
            padding: 16px !important;
        }
    }

    @media (max-width: 480px) {
        .mapa-wrap {
            height: 300px !important;
            min-height: 300px !important;
        }
        .op-actions {
            grid-template-columns: 1fr !important;
        }
    }

    /* Override final movil cliente */
    @media (max-width: 768px) {
        .main-content,
        .content-area,
        .pedidos-layout,
        .pedido-detalle,
        .detalle-grid,
        .detalle-info {
            width: 100% !important;
            max-width: 100% !important;
        }

        .content-area {
            padding: 0 !important;
        }

        .pedidos-lista .pedido-item {
            min-width: 86vw !important;
            max-width: 86vw !important;
        }

        .mapa-wrap {
            height: 42dvh !important;
            min-height: 260px !important;
            max-height: 360px !important;
        }

        .chat-fab-standalone {
            flex: none !important;
            min-height: 120px !important;
        }

        .sidebar {
            width: 100vw !important;
            max-width: 100vw !important;
            z-index: 4000 !important;
        }

        body.sidebar-open .mapa-wrap,
        body.sidebar-open #trackingMapa,
        body.sidebar-open .leaflet-container,
        body.sidebar-open .leaflet-pane,
        body.sidebar-open .leaflet-control-container,
        body.sidebar-open .mapa-legend,
        body.sidebar-open .chat-fab {
            visibility: hidden !important;
            opacity: 0 !important;
        }
    }
    </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="logo-icon-sm"><i class="fa-solid fa-hexagon-nodes"></i></div>
            <span class="logo-text-sm">Nexus<strong>Panel</strong></span>
        </div>
    </div>
    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($u['nombre'],0,1)) ?></div>
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($u['nombre']) ?></span>
            <span class="user-role role-operator"><i class="fa-solid fa-user"></i> Rol: Cliente</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Mi cuenta</div>
        <a href="tienda.php?vista=inicio" class="nav-item"><i class="fa-solid fa-house"></i><span>Inicio</span></a>
        <a href="tienda.php?vista=catalogo" class="nav-item"><i class="fa-solid fa-store"></i><span>Catalogo</span></a>
        <a href="mis_pedidos.php" class="nav-item active"><i class="fa-solid fa-box"></i><span>Mis Pedidos</span><div class="nav-indicator"></div></a>
        <div class="nav-section-label">Cuenta</div>
        <a href="../logout.php" class="nav-item nav-logout"><i class="fa-solid fa-right-from-bracket"></i><span>Cerrar sesion</span></a>
    </nav>
</aside>

<main class="main-content">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenu"><i class="fa-solid fa-bars"></i></button>
            <div class="breadcrumb-custom">
                <span>NexusPanel</span>
                <i class="fa-solid fa-chevron-right"></i>
                <span class="active">Mis Pedidos</span>
            </div>
        </div>
        <div class="topbar-right">
            <div class="topbar-date" id="topbarDate"></div>
        </div>
    </header>

    <div class="pedidos-layout">

        <!-- Lista pedidos -->
        <div class="pedidos-lista">
            <div class="pedidos-lista-header">
                <h3>Mis Pedidos</h3>
                <a href="tienda.php" class="btn-nueva-compra"><i class="fa-solid fa-plus"></i> Nueva compra</a>
            </div>
            <?php if (!empty($todosPedidos)): ?>
            <div style="padding:10px 14px;border-bottom:1px solid var(--border);">
                <div style="position:relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:12px;color:var(--text-dim);"></i>
                    <input
                        type="text"
                        id="pedidosSearchInput"
                        placeholder="Buscar pedido por numero, estado o fecha"
                        style="width:100%;padding:9px 12px 9px 34px;border-radius:10px;border:1px solid var(--border);background:var(--bg-input);color:var(--text-primary);font-size:12px;outline:none;"
                    >
                </div>
            </div>
            <?php endif; ?>
            <?php if (empty($todosPedidos)): ?>
            <div class="sin-pedidos">
                <i class="fa-solid fa-box-open"></i>
                <p>Aun no tienes pedidos</p>
                <a href="tienda.php">Ir a la tienda -></a>
            </div>
            <?php else: ?>
            <?php foreach ($todosPedidos as $p): ?>
            <?php $color = $estadoColors[$p['estado']] ?? '#888'; ?>
            <a href="mis_pedidos.php?pedido_id=<?= $p['id'] ?>" class="pedido-item <?= $p['id']==$pedidoId?'active':'' ?>" data-pedido-search="<?= htmlspecialchars(strtolower('#'.$p['id'].' '.($estadoLabels[$p['estado']] ?? $p['estado']).' '.date('d/m/Y H:i', strtotime($p['created_at']))), ENT_QUOTES) ?>">
                <div class="pedido-item-header">
                    <span class="pedido-num">#<?= $p['id'] ?></span>
                    <span class="pedido-estado-pill" style="background:<?= $color ?>22;color:<?= $color ?>;border:1px solid <?= $color ?>44">
                        <?= $estadoLabels[$p['estado']] ?? $p['estado'] ?>
                    </span>
                </div>
                <div class="pedido-item-total">Estado: <?= $estadoLabels[$p['estado']] ?? $p['estado'] ?></div>
                <div class="pedido-item-fecha">
                    <?= date('d/m/Y H:i', strtotime($p['created_at'])) ?>
                </div>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Detalle pedido -->
        <?php if ($pedido): ?>
        <div class="pedido-detalle">
            <!-- Stepper de estado -->
            <div class="estado-stepper card-panel" style="padding:24px;margin-bottom:0;">
                <div class="stepper-wrap">
                    <?php
                    $pasos = ['pendiente'=>['fa-clock','Pendiente'],'aceptado'=>['fa-user-check','Aceptado'],'en_camino'=>['fa-truck','En camino'],'entregado'=>['fa-box-check','Entregado']];
                    $estadosOrden = array_keys($pasos);
                    $idxActual = array_search($pedido['estado'], $estadosOrden);
                    foreach ($pasos as $key => [$ico, $label]):
                        $idx = array_search($key, $estadosOrden);
                        $done    = $idx < $idxActual;
                        $current = $idx == $idxActual;
                    ?>
                    <div class="stepper-step <?= $done?'done':($current?'current':'') ?>">
                        <div class="stepper-dot">
                            <i class="fa-solid <?= $done ? 'fa-check' : $ico ?>"></i>
                        </div>
                        <span><?= $label ?></span>
                        <small style="max-width:150px;text-align:center;color:var(--text-dim);font-size:10px;line-height:1.25;opacity:<?= $current ? '1' : '0.7' ?>;">
                            <?= htmlspecialchars($estadoDescriptions[$key] ?? '') ?>
                        </small>
                    </div>
                    <?php if ($key !== 'entregado'): ?><div class="stepper-line <?= $done?'filled':'' ?>"></div><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Grid info + mapa + chat -->
            <div class="detalle-grid">

                <!-- Info pedido -->
                <div class="card-panel detalle-info">
                    <div class="panel-header">
                        <div>
                        <h3 class="panel-title" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            Pedido #<?= $pedido['id'] ?>
                            <span class="pedido-estado-pill" style="background:<?= ($estadoColors[$pedido['estado']] ?? '#888') ?>22;color:<?= ($estadoColors[$pedido['estado']] ?? '#888') ?>;border:1px solid <?= ($estadoColors[$pedido['estado']] ?? '#888') ?>44">
                                <?= $estadoLabels[$pedido['estado']] ?? $pedido['estado'] ?>
                            </span>
                        </h3>
                        <p class="panel-subtitle">
                            <?= date('d/m/Y H:i', strtotime($pedido['created_at'])) ?>
                        </p></div>
                    </div>
                    <div style="padding:16px 20px;">
                        <?php foreach ($itemsPedido as $item): ?>
                        <div class="detalle-item">
                            <div class="di-icon" style="overflow:hidden;">
                                <?php if (!empty($item['imagen'])): ?>
                                    <img src="../uploads/productos/<?= htmlspecialchars($item['imagen']) ?>" alt="<?= htmlspecialchars($item['nombre']) ?>" style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?>
                                    <i class="fa-solid fa-flask"></i>
                                <?php endif; ?>
                            </div>
                            <div class="di-info">
                                <div class="di-nombre"><?= htmlspecialchars($item['nombre']) ?></div>
                                <div class="di-precio">
                                    <?= $item['cantidad'] ?> unidad(es)
                                </div>
                                <div class="di-precio" style="opacity:.8;">SKU <?= htmlspecialchars($item['codigo']) ?></div>
                            </div>
                            <div class="di-total"><?= (int)$item['cantidad'] ?> unidades</div>
                        </div>
                        <?php endforeach; ?>
                        <div class="detalle-total">
                            <span>Total de productos</span>
                            <strong style="color:var(--primary);font-family:var(--font-mono)"><?= array_sum(array_map(static fn($it) => (int)($it['cantidad'] ?? 0), $itemsPedido)) ?> unidad(es)</strong>
                        </div>
                        <?php if (!empty($promocionesPedido)): ?>
                        <div style="margin-top:14px;">
                            <div style="font-size:11px;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Promociones disponibles</div>
                            <div style="display:grid;gap:8px;">
                                <?php foreach ($promocionesPedido as $promoItem): ?>
                                <a
                                    href="tienda.php?vista=catalogo&producto=<?= (int)$promoItem['producto_id'] ?>#producto-card-<?= (int)$promoItem['producto_id'] ?>"
                                    style="text-decoration:none;padding:10px 12px;border-radius:10px;border:1px solid rgba(59,130,246,0.26);background:rgba(37,99,235,0.1);display:block;"
                                >
                                    <strong style="display:block;color:#93c5fd;font-size:12px;"><?= htmlspecialchars($promoItem['titulo']) ?></strong>
                                    <span style="display:block;color:#e2e8f0;font-size:13px;margin-top:2px;"><?= htmlspecialchars($promoItem['nombre']) ?></span>
                                    <small style="display:block;color:var(--text-muted);margin-top:3px;font-size:11px;"><?= htmlspecialchars($promoItem['mensaje']) ?></small>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($pedido['operador_id']): ?>
                        <div class="operador-info">
                            <div class="op-avatar"><?= strtoupper(substr($pedido['op_nombre']??'O',0,1)) ?></div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:11px;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;margin-bottom:2px;">Tu operador</div>
                                <strong><?= htmlspecialchars($pedido['op_nombre']??'') ?></strong>
                                <?php if ($pedido['op_tel'] && !in_array($pedido['estado'], ['entregado','cancelado'])): ?>
                                <div style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">
                                    <i class="fa-solid fa-phone"></i> <?= htmlspecialchars($pedido['op_tel']) ?>
                                </div>
                                <!-- Botones de contacto -->
                                <div class="op-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <!-- WhatsApp simple -->
                                    <a href="https://wa.me/52<?= preg_replace('/\D/','',$pedido['op_tel']) ?>?text=<?= urlencode('Hola, soy '.$u['nombre'].'. Te escribo sobre mi pedido #'.$pedido['id']) ?>"
                                       target="_blank"
                                       style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:#25d366;color:#fff;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;transition:all .2s;"
                                       onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform=''">
                                        <i class="fa-brands fa-whatsapp" style="font-size:15px;"></i> WhatsApp
                                    </a>
                                    <!-- Compartir ubicacion por WhatsApp -->
                                    <button onclick="compartirUbicacionWA(this, '52<?= preg_replace('/\D/','',$pedido['op_tel']) ?>', <?= (int)$pedido['id'] ?>)"
                                       style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:linear-gradient(135deg,#128c7e,#075e54);color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;transition:all .2s;"
                                       onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform=''">
                                        <i class="fa-solid fa-location-arrow" style="font-size:13px;"></i> Mi ubicacion
                                    </button>
                                    <button onclick="compartirViajeWA(this, <?= (int)$pedido['id'] ?>)"
                                       style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:linear-gradient(135deg,#0ea5e9,#2563eb);color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;transition:all .2s;"
                                       onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform=''">
                                        <i class="fa-solid fa-share-nodes" style="font-size:13px;"></i> Compartir viaje
                                    </button>
                                    <button id="btnRevocarViaje" onclick="revocarLinkViaje(this, <?= (int)$pedido['id'] ?>)"
                                       style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;transition:all .2s;"
                                       onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform=''">
                                        <i class="fa-solid fa-link-slash" style="font-size:13px;"></i> Revocar enlace
                                    </button>
                                    <!-- SMS -->
                                    <a href="sms:<?= htmlspecialchars($pedido['op_tel']) ?>?body=<?= urlencode('Hola, soy '.$u['nombre'].'. Te escribo sobre mi pedido #'.$pedido['id']) ?>"
                                       style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:rgba(0,212,255,0.12);color:var(--primary);border:1px solid rgba(0,212,255,0.25);border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;transition:all .2s;"
                                       onmouseover="this.style.background='rgba(0,212,255,0.2)'" onmouseout="this.style.background='rgba(0,212,255,0.12)'">
                                        <i class="fa-solid fa-message" style="font-size:13px;"></i> SMS
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Mapa tracking -->
                <?php if (in_array($pedido['estado'], ['aceptado','en_camino'])): ?>
                <div class="mapa-wrap">
                    <div id="trackingMapa"></div>
                    <div class="mapa-legend">
                        <span id="trackingStatus">Actualizando ubicacion...</span>
                        <div class="live-badge"><span class="blink-dot"></span> EN VIVO</div>
                    </div>
                    <?php if ($pedido['operador_id']): ?>
                    <button class="chat-fab" onclick="toggleChatModal()" id="chatFab">
                        <i class="fa-solid fa-comments"></i>
                        <span>Chat</span>
                        <span class="fab-badge hidden" id="chatBadge">0</span>
                    </button>
                    <?php endif; ?>
                </div>
                <?php elseif ($pedido['operador_id']): ?>
                <!-- Sin mapa pero con operador - boton chat standalone -->
                <div class="chat-fab-standalone">
                    <button class="chat-fab-inline" onclick="toggleChatModal()">
                        <i class="fa-solid fa-comments"></i> Abrir chat con operador
                        <span class="fab-badge hidden" id="chatBadge">0</span>
                    </button>
                </div>
                <?php endif; ?>

                <!-- Modal chat flotante -->
                <?php if ($pedido['operador_id']): ?>
                <div class="chat-modal hidden" id="chatModal">
                    <div class="chat-modal-header">
                        <div class="chat-modal-info">
                            <div class="chat-modal-dot"><?= strtoupper(substr($pedido['op_nombre']??'O',0,1)) ?></div>
                            <div>
                                <div class="chat-modal-name"><?= htmlspecialchars($pedido['op_nombre']??'Operador') ?></div>
                                <div class="chat-modal-status">Tu operador</div>
                            </div>
                        </div>
                        <button class="chat-modal-close" onclick="toggleChatModal()">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="wc-messages" id="chatMensajes"></div>
                    <div class="wc-input-bar">
                        <input type="text" id="chatInput" class="wc-input"
                               placeholder="Escribe un mensaje..."
                               onkeydown="if(event.key==='Enter')enviarMensaje()">
                        <button class="wc-send" onclick="enviarMensaje()">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php elseif (empty($todosPedidos)): ?>
        <div style="flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:16px;color:var(--text-muted);">
            <i class="fa-solid fa-box-open" style="font-size:60px;opacity:0.2;"></i>
            <p style="font-size:16px;">Selecciona un pedido para ver el detalle</p>
        </div>
        <?php endif; ?>
    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const PEDIDO_ID = <?= $pedidoId ?>;
const PEDIDO_ESTADO = '<?= $pedido['estado'] ?? '' ?>';
const CLI_LAT = <?= $pedido['lat_entrega'] ?? 0 ?>;
const CLI_LNG = <?= $pedido['lng_entrega'] ?? 0 ?>;
const CLIENTE_NOMBRE = <?= json_encode($u['nombre'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
let map = null, opMarker = null, cliMarker = null;
let lastChatId = 0;

function scheduleTrackingMapResize(delay = 180) {
    if (!map) return;
    setTimeout(() => {
        try { map.invalidateSize(true); } catch (_) {}
    }, delay);
}

document.addEventListener('DOMContentLoaded', () => {
    updateClock(); setInterval(updateClock, 1000);
    initPedidosSearch();
    const mobileMenuBtn = document.getElementById('mobileMenu');
    const side = document.getElementById('sidebar');
    mobileMenuBtn?.addEventListener('click', () => {
        side.classList.toggle('open');
        document.body.classList.toggle('sidebar-open', side.classList.contains('open'));
        scheduleTrackingMapResize(200);
    });
    document.addEventListener('click', (e) => {
        if (
            window.matchMedia('(max-width: 768px)').matches &&
            side?.classList.contains('open') &&
            !side.contains(e.target) &&
            !mobileMenuBtn?.contains(e.target)
        ) {
            side.classList.remove('open');
            document.body.classList.remove('sidebar-open');
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && side?.classList.contains('open')) {
            side.classList.remove('open');
            document.body.classList.remove('sidebar-open');
        }
    });
    window.addEventListener('resize', () => scheduleTrackingMapResize(120));
    window.addEventListener('orientationchange', () => scheduleTrackingMapResize(280));

    if (PEDIDO_ID && ['aceptado','en_camino'].includes(PEDIDO_ESTADO)) {
        initTrackingMap();
        scheduleTrackingMapResize(260);
        setInterval(pollTracking, 3000);
    }
    if (PEDIDO_ID && document.getElementById('chatMensajes')) {
        pollChat();
        setInterval(pollChat, 3000);
    }
    if (PEDIDO_ID && document.getElementById('btnRevocarViaje')) {
        sincronizarEstadoLinkTracking(PEDIDO_ID);
    }
});

function normalizarBusqueda(txt) {
    const raw = String(txt || '').toLowerCase().trim();
    if (!raw) return '';
    try {
        return raw.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    } catch (_) {
        return raw;
    }
}

function initPedidosSearch() {
    const input = document.getElementById('pedidosSearchInput');
    if (!input) return;
    const cards = Array.from(document.querySelectorAll('.pedido-item'));
    input.addEventListener('input', () => {
        const q = normalizarBusqueda(input.value);
        cards.forEach((card) => {
            const hay = normalizarBusqueda(card.dataset.pedidoSearch || '');
            card.style.display = !q || hay.includes(q) ? '' : 'none';
        });
    });
}

// â”€â”€ MAPA TRACKING â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function initTrackingMap() {
    map = L.map('trackingMapa');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OSM',maxZoom:19}).addTo(map);

    // Pin cliente
    if (CLI_LAT && CLI_LNG) {
        const icon = L.divIcon({ html:`<div style="width:28px;height:28px;background:#7c3aed;border-radius:50%;border:3px solid #fff;box-shadow:0 0 10px rgba(124,58,237,0.5);display:flex;align-items:center;justify-content:center;font-size:12px;color:#fff;"><i class="fa fa-house"></i></div>`, iconSize:[28,28],iconAnchor:[14,14],className:'' });
        cliMarker = L.marker([CLI_LAT,CLI_LNG],{icon}).addTo(map).bindPopup('<strong>Tu domicilio</strong>');
        map.setView([CLI_LAT,CLI_LNG],14);
    } else {
        map.setView([19.4326,-99.1332],12);
    }
    scheduleTrackingMapResize(220);
}

async function pollTracking() {
    try {
        const res  = await fetch(`../api/pedido.php?action=tracking&pedido_id=${PEDIDO_ID}`);
        const data = await res.json();
        if (!data.lat || !map) return;
        const lat = parseFloat(data.lat), lng = parseFloat(data.lng);
        const icon = L.divIcon({ html:`<div style="width:40px;height:40px;background:linear-gradient(135deg,#00d4ff,#7c3aed);border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 4px 15px rgba(0,212,255,0.5);"></div>`, iconSize:[40,40],iconAnchor:[20,40],className:'' });
        if (!opMarker) {
            opMarker = L.marker([lat,lng],{icon}).addTo(map).bindPopup('<strong>Tu operador</strong>');
        } else {
            opMarker.setLatLng([lat,lng]);
        }
        // Ajustar bounds
        if (CLI_LAT && CLI_LNG) {
            map.fitBounds([[lat,lng],[CLI_LAT,CLI_LNG]], {padding:[40,40]});
        } else {
            map.setView([lat,lng],15);
        }
        scheduleTrackingMapResize(80);
        document.getElementById('trackingStatus').textContent = `Ultima actualizacion: ${new Date(data.ts).toLocaleTimeString('es-MX')}`;
    } catch(e) {}
}

// â”€â”€ CHAT â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function toggleChatModal() {
    const modal = document.getElementById('chatModal');
    if (!modal) return;
    const opening = modal.classList.contains('hidden');
    modal.classList.toggle('hidden');
    if (opening) {
        const badge = document.getElementById('chatBadge');
        if (badge) { badge.textContent = '0'; badge.classList.add('hidden'); }
        setTimeout(() => {
            document.getElementById('chatInput')?.focus();
            const msgs = document.getElementById('chatMensajes');
            if (msgs) msgs.scrollTop = msgs.scrollHeight;
        }, 50);
    }
}

async function pollChat() {
    try {
        const res  = await fetch(`../api/pedido.php?action=chat_get&pedido_id=${PEDIDO_ID}&desde=${lastChatId}`);
        const msgs = await res.json();
        if (!msgs.length) return;
        const container = document.getElementById('chatMensajes');
        msgs.forEach(m => {
            lastChatId = Math.max(lastChatId, m.id);
            const isMine = m.rol === 'cliente';
            const div = document.createElement('div');
            div.className = `chat-msg ${isMine ? 'mine' : 'theirs'}`;
            div.innerHTML = `
                ${!isMine ? `<div class="msg-sender">${m.nombre}</div>` : ''}
                <div class="msg-bubble">${escapeHtml(m.mensaje)}</div>
                <div class="msg-time">${new Date(m.ts).toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'})}</div>
            `;
            container.appendChild(div);
        });
        container.scrollTop = container.scrollHeight;
    } catch(e) {}
}

async function enviarMensaje() {
    const input = document.getElementById('chatInput');
    const msg = input.value.trim();
    if (!msg) return;
    input.value = '';
    try {
        await fetch(`../api/pedido.php?action=chat_send&pedido_id=${PEDIDO_ID}`, {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({mensaje: msg})
        });
        pollChat();
    } catch(e) {}
}

function escapeHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Compartir ubicacion por WhatsApp
const GEO_OPTIONS = {
    enableHighAccuracy: true,
    timeout: 12000,
    maximumAge: 0
};

function sanitizePhone(phone) {
    return String(phone || '').replace(/\D/g, '');
}

function buildGoogleMapsLink(lat, lng) {
    const url = new URL('https://www.google.com/maps/search/');
    url.searchParams.set('api', '1');
    url.searchParams.set('query', `${lat.toFixed(6)},${lng.toFixed(6)}`);
    return url.toString();
}

function buildWhatsAppUrl({ text, phone = '', universal = false }) {
    const base = universal ? 'https://wa.me/' : `https://wa.me/${sanitizePhone(phone)}`;
    const url = new URL(base);
    url.searchParams.set('text', text);
    return url.toString();
}

function openWhatsApp(url) {
    window.open(url, '_blank', 'noopener,noreferrer');
}

function setButtonLoading(btn, isLoading, label = 'Procesando...') {
    if (!btn) return;
    if (isLoading) {
        if (!btn.dataset.originalHtml) {
            btn.dataset.originalHtml = btn.innerHTML;
        }
        btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${label}`;
        btn.disabled = true;
        return;
    }

    if (btn.dataset.originalHtml) {
        btn.innerHTML = btn.dataset.originalHtml;
    }
    btn.disabled = false;
}

function geolocationAsPromise(options = GEO_OPTIONS) {
    return new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, options);
    });
}

async function readGeoPermissionState() {
    try {
        if (!navigator.permissions || !navigator.permissions.query) return 'unknown';
        const status = await navigator.permissions.query({ name: 'geolocation' });
        return status.state;
    } catch (_) {
        return 'unknown';
    }
}

function canUseGeolocationHere() {
    return window.isSecureContext || ['localhost', '127.0.0.1', '::1'].includes(location.hostname);
}

function buildFallbackLocationMessage(pedidoId, motivo = '') {
    const lines = [
        `Hola, soy ${CLIENTE_NOMBRE} (Pedido #${pedidoId}).`,
        motivo || 'No pude compartir mi ubicacion automaticamente.',
        'Escribeme para coordinar la entrega.'
    ];
    return lines.join('\n');
}

async function compartirUbicacionWA(btn, telefono, pedidoId) {
    setButtonLoading(btn, true, 'Obteniendo...');

    try {
        if (!('geolocation' in navigator)) {
            openWhatsApp(buildWhatsAppUrl({
                phone: telefono,
                text: buildFallbackLocationMessage(pedidoId, 'Tu navegador no soporta GPS.')
            }));
            return;
        }

        if (!canUseGeolocationHere()) {
            openWhatsApp(buildWhatsAppUrl({
                phone: telefono,
                text: buildFallbackLocationMessage(pedidoId, 'Para usar GPS desde celular abre el sitio con HTTPS.')
            }));
            return;
        }

        const permission = await readGeoPermissionState();
        if (permission === 'denied') {
            openWhatsApp(buildWhatsAppUrl({
                phone: telefono,
                text: buildFallbackLocationMessage(pedidoId, 'El permiso de ubicacion esta bloqueado en este navegador (normal en laptop/desktop). Abre este link desde tu celular con GPS activo.')
            }));
            return;
        }

        const position = await geolocationAsPromise();
        const lat = Number(position.coords.latitude);
        const lng = Number(position.coords.longitude);

        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            throw new Error('invalid_coords');
        }

        const precision = position.coords.accuracy ? Math.round(position.coords.accuracy) : null;
        const mapsLink = buildGoogleMapsLink(lat, lng);
        const message = [
            `Hola, soy ${CLIENTE_NOMBRE} (Pedido #${pedidoId}).`,
            `Mi ubicacion: ${mapsLink}`,
            `Coordenadas: ${lat.toFixed(6)}, ${lng.toFixed(6)}`,
            precision ? `Precision GPS aproximada: +/- ${precision}m` : ''
        ].filter(Boolean).join('\n');

        openWhatsApp(buildWhatsAppUrl({ phone: telefono, text: message }));
    } catch (err) {
        let motivo = 'No fue posible leer tu ubicacion GPS.';
        if (err && typeof err.code === 'number') {
            if (err.code === 1) motivo = 'Permiso de ubicacion denegado.';
            if (err.code === 2) motivo = 'Ubicacion no disponible en este momento.';
            if (err.code === 3) motivo = 'Tiempo de espera agotado al leer GPS.';
        }

        openWhatsApp(buildWhatsAppUrl({
            phone: telefono,
            text: buildFallbackLocationMessage(pedidoId, motivo)
        }));
    } finally {
        setButtonLoading(btn, false);
    }
}

async function obtenerLinkTrackingPublico(pedidoId) {
    const res = await fetch(`../api/pedido.php?action=crear_link_tracking&pedido_id=${encodeURIComponent(pedidoId)}`, {
        credentials: 'same-origin'
    });
    const data = await leerJsonSeguro(res);

    if (!res.ok || !data.success || !data.url_publica) {
        throw new Error(data.error || `No se pudo crear el link publico (${res.status})`);
    }

    return data.url_publica;
}

async function compartirViajeWA(btn, pedidoId) {
    setButtonLoading(btn, true, 'Generando link...');
    try {
        const publicLink = await obtenerLinkTrackingPublico(pedidoId);
        const message = [
            `Hola, te comparto mi viaje en tiempo real del Pedido #${pedidoId}.`,
            `Cliente: ${CLIENTE_NOMBRE}`,
            `Seguimiento: ${publicLink}`
        ].join('\n');

        openWhatsApp(buildWhatsAppUrl({
            universal: true,
            text: message
        }));
        sincronizarEstadoLinkTracking(pedidoId);
    } catch (err) {
        alert(err?.message || 'No se pudo generar el link publico de seguimiento. Intenta de nuevo.');
    } finally {
        setButtonLoading(btn, false);
    }
}

async function revocarLinkViaje(btn, pedidoId) {
    if (!confirm('Esto desactivara el enlace compartido actual. Deseas continuar?')) return;
    setButtonLoading(btn, true, 'Revocando...');
    try {
        const res = await fetch(`../api/pedido.php?action=revocar_link_tracking&pedido_id=${encodeURIComponent(pedidoId)}`, {
            method: 'POST',
            credentials: 'same-origin'
        });
        const data = await leerJsonSeguro(res);
        if (!res.ok || !data.success) {
            throw new Error(data.error || `No se pudo revocar el enlace (${res.status})`);
        }
        const revocados = Number(data.revocados || 0);
        alert(revocados > 0 ? 'Enlace revocado correctamente.' : 'No habia un enlace activo para revocar.');
        sincronizarEstadoLinkTracking(pedidoId);
    } catch (err) {
        alert(err?.message || 'No se pudo revocar el enlace en este momento.');
    } finally {
        setButtonLoading(btn, false);
    }
}

async function sincronizarEstadoLinkTracking(pedidoId) {
    const btn = document.getElementById('btnRevocarViaje');
    if (!btn) return;

    try {
        const res = await fetch(`../api/pedido.php?action=estado_link_tracking&pedido_id=${encodeURIComponent(pedidoId)}`, {
            credentials: 'same-origin',
            cache: 'no-store'
        });
        const data = await leerJsonSeguro(res);
        const activo = Boolean(data?.activo);
        btn.disabled = !activo;
        btn.style.opacity = activo ? '1' : '0.55';
        btn.title = activo ? 'Desactivar el enlace de seguimiento activo' : 'No hay enlace activo';
    } catch (_) {
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

async function leerJsonSeguro(response) {
    const raw = await response.text();
    try {
        return JSON.parse(raw);
    } catch (_) {
        const preview = raw.replace(/\s+/g, ' ').slice(0, 120);
        throw new Error(`Respuesta invalida del servidor (${response.status}). ${preview}`);
    }
}

function updateClock() {
    const el = document.getElementById('topbarDate'); if(!el)return;
    const now = new Date();
    el.textContent = now.toLocaleDateString('es-MX',{weekday:'short',day:'2-digit',month:'short'})+' - '+now.toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
}


</script>
</body>
</html>




