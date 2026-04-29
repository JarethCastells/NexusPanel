<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireAuth();

$usuario = usuarioActual();
$vistaCliente = (($_GET['vista'] ?? 'inicio') === 'catalogo') ? 'catalogo' : 'inicio';
$hasFolioHexPedidos = columnExists($pdo, 'pedidos', 'folio_hex');
$folioExprPedidos = $hasFolioHexPedidos ? "p.folio_hex" : "UPPER(HEX(p.id))";

function tiendaSlug(string $valor): string {
    $valor = trim(mb_strtolower($valor, 'UTF-8'));
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);
        if ($ascii !== false) {
            $valor = strtolower($ascii);
        }
    }
    $valor = preg_replace('/[^a-z0-9]+/u', '-', $valor);
    return trim((string)$valor, '-');
}

function tiendaCategoriaLabel(string $slug): string {
    $slug = trim(mb_strtolower($slug, 'UTF-8'));
    if ($slug === 'vaca') return 'Vaca';
    if ($slug === 'cerdo') return 'Cerdo';
    return 'Pollo';
}
// Cargar productos. Si la tabla/columnas nuevas no existen aun en hosting, usar fallback legacy.
$hasCategoriaSlug = function_exists('columnExists') && columnExists($pdo, 'productos', 'categoria_slug');
$hasSubcategoriaCol = function_exists('columnExists') && columnExists($pdo, 'productos', 'subcategoria');
$hasSubcategoriaId = function_exists('columnExists') && columnExists($pdo, 'productos', 'subcategoria_id');
$hasSubcategoriasGlobales = function_exists('tableExists') && tableExists($pdo, 'subcategorias_globales');
$legacyMode = !($hasSubcategoriasGlobales && $hasSubcategoriaId);
$productos = [];

try {
    if ($hasSubcategoriasGlobales && $hasSubcategoriaId) {
        $productos = $pdo->query(" 
            SELECT
                p.*,
                LOWER(COALESCE(NULLIF(p.categoria_slug, ''), 'pollo')) AS categoria_slug_ui,
                COALESCE(sg.nombre, NULLIF(TRIM(COALESCE(p.subcategoria, '')), ''), 'Alimento') AS subcategoria_ui,
                COALESCE(sg.slug, 'alimento') AS subcategoria_slug_ui
            FROM productos p
            LEFT JOIN subcategorias_globales sg ON sg.id = p.subcategoria_id
            WHERE p.activo = 1
            ORDER BY p.nombre
        ")->fetchAll();
    } else {
        $catExpr = $hasCategoriaSlug
            ? "LOWER(COALESCE(NULLIF(p.categoria_slug, ''), 'pollo'))"
            : "'pollo'";
        $subExpr = $hasSubcategoriaCol
            ? "COALESCE(NULLIF(TRIM(COALESCE(p.subcategoria, '')), ''), 'Alimento')"
            : "'Alimento'";

        $productos = $pdo->query(" 
            SELECT
                p.*,
                {$catExpr} AS categoria_slug_ui,
                {$subExpr} AS subcategoria_ui,
                {$subExpr} AS subcategoria_slug_ui
            FROM productos p
            WHERE p.activo = 1
            ORDER BY p.nombre
        ")->fetchAll();
    }
} catch (Throwable $e) {
    // Fallback duro para evitar pantalla blanca en hostings con esquema parcial.
    try {
        $productos = $pdo->query("SELECT p.* FROM productos p WHERE p.activo = 1 ORDER BY p.nombre")->fetchAll();
    } catch (Throwable $inner) {
        $productos = [];
    }
}

// Opciones fijas de categoria.
$categoriasFijas = [
    'pollo' => 'Pollo',
    'vaca' => 'Vaca',
    'cerdo' => 'Cerdo',
];
$subcategoriasDefault = [
    'alimento' => 'Alimento',
    'comida' => 'Comida',
    'proteina' => 'Proteina',
    'engorda' => 'Engorda',
    'lacteo' => 'Lacteo',
    'suplemento' => 'Suplemento'
];

// Normalizar productos por si hay legacy.
foreach ($productos as $idx => $producto) {
    $cat = strtolower(trim((string)($producto['categoria_slug_ui'] ?? '')));
    if (!isset($categoriasFijas[$cat])) {
        $pid = (int)($producto['id'] ?? 0);
        $slot = $pid > 0 ? ($pid % 3) : ($idx % 3);
        $cat = $slot === 0 ? 'pollo' : ($slot === 1 ? 'vaca' : 'cerdo');
    }
    $productos[$idx]['categoria_slug_ui'] = $cat;
    $productos[$idx]['categoria_nombre_ui'] = tiendaCategoriaLabel($cat);

    $subNom = trim((string)($producto['subcategoria_ui'] ?? 'Alimento'));
    $subSlugActual = tiendaSlug((string)($producto['subcategoria_slug_ui'] ?? $subNom));
    if ($subNom === '' || !$subSlugActual || ($legacyMode && $subSlugActual === 'alimento')) {
        $pid = (int)($producto['id'] ?? 0);
        $subKeys = array_keys($subcategoriasDefault);
        $subKey = $subKeys[($pid > 0 ? $pid : $idx) % count($subKeys)];
        $subNom = (string)$subcategoriasDefault[$subKey];
        $subSlugActual = $subKey;
    }
    $productos[$idx]['subcategoria_ui'] = $subNom;
    $productos[$idx]['subcategoria_slug_ui'] = $subSlugActual ?: 'alimento';
}

// Subcategorias globales activas.
$subcategorias = [];
if (tableExists($pdo, 'subcategorias_globales')) {
    try {
        $subRows = $pdo->query("SELECT nombre, slug FROM subcategorias_globales WHERE activa = 1 ORDER BY nombre ASC")->fetchAll();
        foreach ($subRows as $sr) {
            $slug = tiendaSlug((string)($sr['slug'] ?? ''));
            if ($slug === '') $slug = tiendaSlug((string)($sr['nombre'] ?? ''));
            if ($slug === '') $slug = 'alimento';
            if (in_array($slug, ['pollo', 'vaca', 'cerdo', 'acuacultura'], true)) {
                continue;
            }
            $subcategorias[$slug] = (string)($sr['nombre'] ?? 'Alimento');
        }
    } catch (Throwable $e) {
        $subcategorias = [];
    }
}
if (empty($subcategorias)) {
    $subcategorias = $subcategoriasDefault;
}

$productosPorCategoria = [];
foreach ($productos as $prodTmp) {
    $catKey = trim((string)($prodTmp['categoria_slug_ui'] ?? 'pollo'));
    if (!isset($productosPorCategoria[$catKey])) $productosPorCategoria[$catKey] = [];
    $productosPorCategoria[$catKey][] = $prodTmp;
}
$ordenPreferido = ['pollo', 'vaca', 'cerdo'];
$productosInicio = [];
$maxInicio = 12;
while (count($productosInicio) < $maxInicio) {
    $agrego = false;
    foreach ($ordenPreferido as $cat) {
        if (!empty($productosPorCategoria[$cat])) {
            $productosInicio[] = array_shift($productosPorCategoria[$cat]);
            $agrego = true;
            if (count($productosInicio) >= $maxInicio) break;
        }
    }
    if (!$agrego) break;
}
if (count($productosInicio) < $maxInicio) {
    foreach ($productosPorCategoria as $resto) {
        foreach ($resto as $prodResto) {
            $productosInicio[] = $prodResto;
            if (count($productosInicio) >= $maxInicio) break 2;
        }
    }
}

// Pedido activo del cliente
$pedidoActivo = null;
$notificacionesPedidos = [];
$notificacionPedidosState = 'sin-pedidos';
if (esCliente()) {
    $pedidoActivo = $pdo->prepare("
        SELECT p.*, {$folioExprPedidos} AS folio_hex_ui
        FROM pedidos p
        WHERE p.cliente_id=? AND p.estado NOT IN ('entregado','cancelado')
        ORDER BY p.id DESC
        LIMIT 1
    ");
    $pedidoActivo->execute([$usuario['usuario_id']]);
    $pedidoActivo = $pedidoActivo->fetch();

    try {
        $stNotis = $pdo->prepare("
            SELECT
                p.id,
                p.estado,
                p.updated_at,
                p.created_at,
                {$folioExprPedidos} AS folio_hex_ui
            FROM pedidos p
            WHERE p.cliente_id = ?
              AND p.estado IN ('aceptado', 'en_camino')
            ORDER BY p.updated_at DESC, p.id DESC
            LIMIT 2
        ");
        $stNotis->execute([$usuario['usuario_id']]);
        $notificacionesPedidos = $stNotis->fetchAll() ?: [];
    } catch (Throwable $e) {
        $notificacionesPedidos = [];
    }

    if (!empty($notificacionesPedidos)) {
        $stateParts = [];
        foreach ($notificacionesPedidos as $np) {
            $stateParts[] = implode(':', [
                (int)($np['id'] ?? 0),
                (string)($np['estado'] ?? ''),
                (string)($np['updated_at'] ?? $np['created_at'] ?? ''),
            ]);
        }
        $notificacionPedidosState = sha1(implode('|', $stateParts));
    }
}

$productosDestacados = array_slice($productosInicio, 0, min(6, count($productosInicio)));
$promocionesDisponibles = [];
$promoTitulos = [
    'Combo recomendado',
    'Oferta de temporada',
    'Entrega prioritaria',
];
$promoFondos = [
    'promo-card-cyan',
    'promo-card-amber',
    'promo-card-emerald',
];
foreach (array_slice($productosInicio, 0, min(3, count($productosInicio))) as $promoIdx => $promoProducto) {
    $promocionesDisponibles[] = [
        'titulo' => $promoTitulos[$promoIdx] ?? 'Promocion disponible',
        'fondo' => $promoFondos[$promoIdx] ?? 'promo-card-cyan',
        'producto' => (string)($promoProducto['nombre'] ?? 'Producto destacado'),
        'mensaje' => 'Disponible para pedido inmediato en ' . strtolower((string)($promoProducto['categoria_nombre_ui'] ?? 'catalogo')),
        'producto_id' => (int)($promoProducto['id'] ?? 0),
    ];
}
$productoSolicitado = (int)($_GET['producto'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Tienda Cliente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php
    $dashboardCssVer = @filemtime(__DIR__ . '/../assets/css/dashboard.css') ?: time();
    $tiendaCssVer = @filemtime(__DIR__ . '/../assets/css/tienda.css') ?: time();
    $dashboardJsVer = @filemtime(__DIR__ . '/../assets/js/dashboard.js') ?: time();
    $tiendaJsVer = @filemtime(__DIR__ . '/../assets/js/tienda.js') ?: time();
    ?>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?= (int)$dashboardCssVer ?>">
    <link rel="stylesheet" href="../assets/css/tienda.css?v=<?= (int)$tiendaCssVer ?>">
    <style>
    /* â•â•â•â• TIENDA RESPONSIVE â•â•â•â• */
    @media (max-width: 1024px) {
        .ml-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
    }
    @media (max-width: 768px) {
        /* Header tienda apilado */
        .tienda-header {
            flex-direction: column !important;
            gap: 12px !important;
            align-items: stretch !important;
        }
        .tienda-search-wrap { width: 100% !important; }
        .tienda-search { width: 100% !important; }
        /* Grid 2 columnas en movil */
        .ml-grid {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 10px !important;
            padding: 0 12px 100px !important;
        }
        /* Cards mas compactas */
        .ml-img-wrap { aspect-ratio: 1 !important; }
        .ml-nombre { font-size: 12px !important; }
        .ml-btn-agregar { font-size: 11px !important; padding: 7px 6px !important; }
        /* Carrito lateral full-width en movil */
        .carrito-panel {
            width: 100% !important;
            max-width: 100% !important;
        }
        /* Banner pedido activo */
        .pedido-activo-banner { font-size: 13px !important; padding: 12px !important; }
    }
    @media (max-width: 480px) {
        .ml-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 8px !important; padding: 0 8px 100px !important; }
        .ml-info { padding: 8px 10px 10px !important; }
        .ml-envio { display: none !important; }
    }
    /* Override final movil: filtros de catalogo sin recortes */
    @media (max-width: 768px) {
        .tienda-filter-shell {
            grid-template-columns: 1fr !important;
            border-radius: 14px !important;
            overflow: hidden !important;
        }
        .tienda-filter-block {
            width: 100% !important;
            border-left: none !important;
            border-top: 1px solid #e6edf7 !important;
            padding: 10px 12px !important;
        }
        .tienda-filter-block:first-child {
            border-top: none !important;
        }
        .tienda-filter-dropdown-block {
            z-index: 2 !important;
        }
        .filter-dropdown-trigger {
            min-height: 48px !important;
            border-radius: 12px !important;
            width: 100% !important;
        }
        .filter-dropdown-menu {
            position: static !important;
            width: 100% !important;
            max-width: 100% !important;
            margin-top: 8px !important;
            box-shadow: none !important;
        }
        .tienda-pill-group {
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            padding-bottom: 4px !important;
            scrollbar-width: thin;
        }
    }
    </style>
</head>
<body data-theme="<?= function_exists('temaActual') ? htmlspecialchars(temaActual()) : 'dark' ?>">

<!-- SIDEBAR -->
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
            <span class="user-role role-operator">
                <i class="fa-solid fa-user"></i> Rol: Cliente
            </span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Mi cuenta</div>
        <a href="tienda.php?vista=inicio" class="nav-item <?= $vistaCliente === 'inicio' ? 'active' : '' ?>">
            <i class="fa-solid fa-house"></i><span>Inicio</span><div class="nav-indicator"></div>
        </a>
        <a href="tienda.php?vista=catalogo" class="nav-item <?= $vistaCliente === 'catalogo' ? 'active' : '' ?>">
            <i class="fa-solid fa-store"></i><span>Catalogo</span><div class="nav-indicator"></div>
        </a>
        <a href="mis_pedidos.php" class="nav-item">
            <i class="fa-solid fa-box"></i><span>Mis Pedidos</span>
            <?php
            $pend = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE cliente_id=? AND estado NOT IN ('entregado','cancelado')");
            $pend->execute([$usuario['usuario_id']]);
            $cnt = $pend->fetchColumn();
            if ($cnt > 0) echo "<span class='nav-badge'>$cnt</span>";
            ?>
        </a>
        <div class="nav-section-label">Cuenta</div>
        
        <a href="../logout.php" class="nav-item nav-logout">
            <i class="fa-solid fa-right-from-bracket"></i><span>Cerrar sesion</span>
        </a>
    </nav>
</aside>

<!-- MAIN -->
<main class="main-content">
    <header class="topbar topbar-cliente">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenu"><i class="fa-solid fa-bars"></i></button>
            <div class="breadcrumb-custom">
                <a href="tienda.php?vista=inicio" style="color:inherit;text-decoration:none;">Tienda</a>
                <i class="fa-solid fa-chevron-right"></i>
                <span class="active"><?= $vistaCliente === 'inicio' ? 'Inicio' : 'Catalogo' ?></span>
            </div>
        </div>
        <div class="topbar-right">
            <button id="btnToggleTheme" class="topbar-btn" title="Cambiar Paleta" onclick="toggleTheme()"><i class="fa-solid fa-palette"></i></button>
            <div class="topbar-date" id="topbarDate"></div>
            <div class="pedido-noti-wrap" id="pedidoNotiWrap">
                <button
                    class="topbar-btn pedido-noti-btn"
                    id="pedidoNotiBtn"
                    type="button"
                    title="Notificaciones de pedidos"
                    data-state-key="<?= htmlspecialchars($notificacionPedidosState, ENT_QUOTES) ?>"
                    data-count="<?= (int)count($notificacionesPedidos) ?>"
                >
                    <i class="fa-solid fa-bell"></i>
                    <span class="pedido-noti-badge" id="pedidoNotiBadge" style="display:none;"><?= (int)count($notificacionesPedidos) ?></span>
                </button>
                <div class="pedido-noti-panel" id="pedidoNotiPanel" style="display:none;">
                    <div class="pedido-noti-head">
                        <strong>Notificaciones</strong>
                        <span>Pedidos activos</span>
                    </div>
                    <div class="pedido-noti-list">
                        <?php if (empty($notificacionesPedidos)): ?>
                        <div class="pedido-noti-empty">
                            <i class="fa-solid fa-check-double"></i>
                            <p>No tienes pedidos aceptados ni en camino.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($notificacionesPedidos as $np): ?>
                        <?php
                            $estadoNoti = (string)($np['estado'] ?? 'pendiente');
                            $estadoLabel = ucfirst(str_replace('_', ' ', $estadoNoti));
                            $estadoIcon = $estadoNoti === 'en_camino' ? 'fa-truck-fast' : 'fa-badge-check';
                            $estadoClass = $estadoNoti === 'en_camino' ? 'is-route' : 'is-accepted';
                        ?>
                        <a class="pedido-noti-item <?= $estadoClass ?>" href="mis_pedidos.php">
                            <div class="pedido-noti-icon">
                                <i class="fa-solid <?= $estadoIcon ?>"></i>
                            </div>
                            <div class="pedido-noti-copy">
                                <strong>Pedido <?= htmlspecialchars($estadoLabel) ?></strong>
                                <p>#<?= htmlspecialchars((string)($np['folio_hex_ui'] ?? strtoupper(dechex((int)($np['id'] ?? 0))))) ?> listo para seguimiento</p>
                            </div>
                        </a>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="content-area">

        <?php if ($vistaCliente === 'inicio'): ?>
        <section class="cliente-home">
            <div class="cliente-home-hero cliente-home-hero-ml">
                <div class="cliente-home-copy">
                    <h1 class="tienda-title">
                        <i class="fa-solid fa-basket-shopping" style="color:var(--primary)"></i>
                        Descubre ofertas para tu granja
                    </h1>
                    <p class="tienda-subtitle">Explora productos destacados, promociones activas y agrega al carrito con una experiencia mas rapida y visual.</p>
                    <div class="hero-mini-stats">
                        <span><i class="fa-solid fa-bolt"></i> Pedido agil</span>
                        <span><i class="fa-solid fa-image"></i> Catalogo visual</span>
                        <span><i class="fa-solid fa-truck-fast"></i> Seguimiento inmediato</span>
                    </div>
                </div>
                <div class="cliente-home-cta">
                    <a href="tienda.php?vista=catalogo" class="btn-home-primary">
                        <i class="fa-solid fa-store"></i> Ver catalogo
                    </a>
                    <button type="button" class="btn-home-secondary" onclick="toggleCarrito()">
                        <i class="fa-solid fa-cart-shopping"></i> Ir al carrito
                    </button>
                </div>
            </div>

            <div class="home-section-head">
                <h2>Productos destacados</h2>
                <span><?= count($productosDestacados) ?> sugerencias activas</span>
            </div>
            <div class="featured-carousel-shell">
                <button type="button" class="featured-nav featured-nav-prev" id="featuredPrevBtn" aria-label="Anterior">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <div class="featured-carousel" id="featuredCarousel">
                    <?php foreach ($productosDestacados as $featured): ?>
                    <a
                        href="tienda.php?vista=catalogo&producto=<?= (int)$featured['id'] ?>#producto-card-<?= (int)$featured['id'] ?>"
                        class="featured-item"
                    >
                        <span class="featured-item-tag">Destacado</span>
                        <strong><?= htmlspecialchars((string)$featured['nombre']) ?></strong>
                    </a>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="featured-nav featured-nav-next" id="featuredNextBtn" aria-label="Siguiente">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>

            <div class="home-section-head">
                <h2>Promociones disponibles</h2>
                <span>Selecciona una y te llevamos directo al catalogo</span>
            </div>
            <div class="promo-grid">
                <?php foreach ($promocionesDisponibles as $promo): ?>
                <a
                    href="tienda.php?vista=catalogo&producto=<?= (int)$promo['producto_id'] ?>#producto-card-<?= (int)$promo['producto_id'] ?>"
                    class="promo-card <?= htmlspecialchars($promo['fondo']) ?>"
                >
                    <span class="promo-chip"><?= htmlspecialchars($promo['titulo']) ?></span>
                    <strong><?= htmlspecialchars($promo['producto']) ?></strong>
                    <p><?= htmlspecialchars($promo['mensaje']) ?></p>
                    <span class="promo-link">Ver producto <i class="fa-solid fa-arrow-right"></i></span>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="ml-grid" id="productosGrid">
                <?php foreach ($productosInicio as $i => $p): ?>
                <?php
                    $categoriaSlug = trim((string)($p['categoria_slug_ui'] ?? 'sin-categoria'));
                    $subcategoriaSlug = trim((string)($p['subcategoria_slug_ui'] ?? tiendaSlug((string)($p['subcategoria_ui'] ?? 'General'))));
                    if ($subcategoriaSlug === '') $subcategoriaSlug = 'general';
                    $imagenUrl = !empty($p['imagen']) ? ('../uploads/productos/' . $p['imagen']) : '';
                    $categoriaVisible = (string)($p['categoria_nombre_ui'] ?? 'Pollo');
                    $subcategoriaVisible = (string)($p['subcategoria_ui'] ?? 'General');
                ?>
                <div class="ml-card"
                     id="producto-card-<?= (int)$p['id'] ?>"
                     data-id="<?= (int)$p['id'] ?>"
                     data-nombre="<?= htmlspecialchars(strtolower($p['nombre']), ENT_QUOTES) ?>"
                     data-nombre-raw="<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>"
                     data-imagen="<?= htmlspecialchars($imagenUrl, ENT_QUOTES) ?>"
                     data-categoria="<?= htmlspecialchars($categoriaSlug) ?>"
                     data-subcategoria="<?= htmlspecialchars($subcategoriaSlug) ?>"
                     style="animation-delay:<?= $i*0.04 ?>s">
                    <div class="ml-img-wrap">
                        <?php if (!empty($p['imagen'])): ?>
                        <img src="../uploads/productos/<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>" class="ml-img" loading="lazy">
                        <?php else: ?>
                        <div class="ml-img-placeholder"><i class="fa-solid fa-box-open"></i></div>
                        <?php endif; ?>
                        <span class="ml-badge-stock">Disponible</span>
                    </div>
                    <div class="ml-info">
                        <div class="ml-codigo"><?= htmlspecialchars($p['codigo']) ?></div>
                        <div class="ml-envio" style="margin-top:-2px;"><i class="fa-solid fa-tags"></i> <?= htmlspecialchars($categoriaVisible) ?></div>
                        <div class="ml-envio" style="margin-top:-4px;"><i class="fa-solid fa-layer-group"></i> <?= htmlspecialchars($subcategoriaVisible) ?></div>
                        <div class="ml-nombre"><?= htmlspecialchars($p['nombre']) ?></div>
                        <div class="ml-actions">
                            <button type="button" class="ml-btn-agregar" onclick="abrirModalCantidad(this)">
                                <i class="fa-solid fa-cart-plus"></i> Agregar producto
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php else: ?>
        <div class="tienda-header">
            <div>
                <h1 class="tienda-title">
                    <i class="fa-solid fa-store" style="color:var(--primary)"></i>
                    Catalogo de productos
                </h1>
                <p class="tienda-subtitle"><?= count($productos) ?> productos disponibles para pedido</p>
            </div>
            <div class="tienda-filter-shell" role="search" aria-label="Busqueda y filtros de catalogo">
                <div class="tienda-filter-block tienda-filter-search-block">
                    <div class="tienda-search-wrap tienda-search-wrap-modern">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="searchInput" placeholder="Buscar por nombre o codigo" class="tienda-search tienda-search-modern">
                    </div>
                </div>
                <div class="tienda-filter-block tienda-filter-dropdown-block">
                    <div class="filter-label">Categoria</div>
                    <div class="filter-dropdown" data-filter-dropdown>
                        <button type="button" class="filter-dropdown-trigger" data-filter-trigger>
                            <span class="filter-trigger-copy">
                                <strong>Categoria</strong>
                                <small id="categoriaSelectedLabel">Todas</small>
                            </span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <div class="filter-dropdown-menu" data-filter-menu>
                            <div class="tienda-pill-group" id="filtroCategoriaPills">
                                <button type="button" class="filtro-pill active" data-filter="categoria" data-value="">Todas</button>
                                <?php foreach ($categoriasFijas as $slugCat => $nombreCat): ?>
                                <button type="button" class="filtro-pill" data-filter="categoria" data-value="<?= htmlspecialchars($slugCat, ENT_QUOTES) ?>"><?= htmlspecialchars($nombreCat) ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tienda-filter-block tienda-filter-dropdown-block">
                    <div class="filter-label">Subcategoria</div>
                    <div class="filter-dropdown" data-filter-dropdown>
                        <button type="button" class="filter-dropdown-trigger" data-filter-trigger>
                            <span class="filter-trigger-copy">
                                <strong>Subcategoria</strong>
                                <small id="subcategoriaSelectedLabel">Todas</small>
                            </span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <div class="filter-dropdown-menu" data-filter-menu>
                            <div class="tienda-pill-group tienda-pill-group-scroll" id="filtroSubcategoriaPills">
                                <button type="button" class="filtro-pill active" data-filter="subcategoria" data-value="">Todas</button>
                                <?php foreach ($subcategorias as $subcatSlug => $subcatNombre): ?>
                                <button type="button" class="filtro-pill" data-filter="subcategoria" data-value="<?= htmlspecialchars($subcatSlug, ENT_QUOTES) ?>"><?= htmlspecialchars($subcatNombre) ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ml-grid" id="productosGrid">
            <?php foreach ($productos as $i => $p): ?>
            <?php
                $categoriaSlug = trim((string)($p['categoria_slug_ui'] ?? 'sin-categoria'));
                $subcategoriaSlug = trim((string)($p['subcategoria_slug_ui'] ?? tiendaSlug((string)($p['subcategoria_ui'] ?? 'General'))));
                if ($subcategoriaSlug === '') $subcategoriaSlug = 'general';
                $imagenUrl = !empty($p['imagen']) ? ('../uploads/productos/' . $p['imagen']) : '';
                $categoriaVisible = (string)($p['categoria_nombre_ui'] ?? 'Pollo');
                $subcategoriaVisible = (string)($p['subcategoria_ui'] ?? 'General');
            ?>
            <div class="ml-card"
                 id="producto-card-<?= (int)$p['id'] ?>"
                 data-id="<?= (int)$p['id'] ?>"
                 data-nombre="<?= htmlspecialchars(strtolower($p['nombre']), ENT_QUOTES) ?>"
                 data-nombre-raw="<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>"
                 data-imagen="<?= htmlspecialchars($imagenUrl, ENT_QUOTES) ?>"
                 data-categoria="<?= htmlspecialchars($categoriaSlug) ?>"
                 data-subcategoria="<?= htmlspecialchars($subcategoriaSlug) ?>"
                 style="animation-delay:<?= $i*0.04 ?>s">
                <div class="ml-img-wrap">
                    <?php if (!empty($p['imagen'])): ?>
                    <img src="../uploads/productos/<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>" class="ml-img" loading="lazy">
                    <?php else: ?>
                    <div class="ml-img-placeholder"><i class="fa-solid fa-box-open"></i></div>
                    <?php endif; ?>
                    <span class="ml-badge-stock">Disponible</span>
                </div>
                <div class="ml-info">
                    <div class="ml-codigo"><?= htmlspecialchars($p['codigo']) ?></div>
                    <div class="ml-envio" style="margin-top:-2px;"><i class="fa-solid fa-tags"></i> <?= htmlspecialchars($categoriaVisible) ?></div>
                    <div class="ml-envio" style="margin-top:-4px;"><i class="fa-solid fa-layer-group"></i> <?= htmlspecialchars($subcategoriaVisible) ?></div>
                    <div class="ml-nombre"><?= htmlspecialchars($p['nombre']) ?></div>
                    <div class="ml-actions">
                        <button type="button" class="ml-btn-agregar" onclick="abrirModalCantidad(this)">
                            <i class="fa-solid fa-cart-plus"></i> Agregar producto
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<button class="floating-cart-btn" id="floatingCartBtn" type="button" onclick="toggleCarrito()" aria-label="Abrir carrito">
    <i class="fa-solid fa-cart-shopping"></i>
    <span class="floating-cart-badge" id="floatingCartBadge" style="display:none;">0</span>
</button>

<div class="pedido-success-toast" id="pedidoSuccessToast" role="status" aria-live="polite">
    <div class="pedido-success-icon">
        <i class="fa-solid fa-circle-check"></i>
    </div>
    <div class="pedido-success-copy">
        <strong>Tu pedido se ha realizado exitosamente</strong>
        <p>Le avisaremos cuando este de camino.</p>
    </div>
</div>

<!-- CARRITO LATERAL -->
<div class="carrito-overlay" id="carritoOverlay" onclick="toggleCarrito()"></div>
<div class="carrito-panel" id="carritoPanel">
    <div class="carrito-header">
        <h3><i class="fa-solid fa-cart-shopping"></i> Mi Carrito</h3>
        <button class="carrito-close" onclick="toggleCarrito()"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <div class="carrito-items" id="carritoItems">
        <div class="carrito-empty" id="carritoEmpty">
            <i class="fa-solid fa-cart-shopping"></i>
            <p>Tu carrito esta vacio</p>
        </div>
    </div>

    <div class="carrito-footer" id="carritoFooter" style="display:none;">
        <div class="carrito-total">
            <span>Productos:</span>
            <strong id="carritoTotal">0 producto(s)</strong>
        </div>
        <button class="btn-checkout" onclick="abrirCheckout()">
            <i class="fa-solid fa-money-bill-wave"></i>
            Finalizar pedido
        </button>
    </div>
</div>

<!-- MODAL CANTIDAD -->
<div class="modal fade" id="modalCantidadProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content checkout-modal">
            <div class="checkout-header">
                <h4><i class="fa-solid fa-cart-plus" style="color:var(--primary)"></i> Agregar producto</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1)opacity(.5)"></button>
            </div>
            <div class="checkout-body">
                <div class="producto-modal-visual">
                    <button type="button" class="producto-modal-zoom-btn" id="cantidadProductoZoomBtn" onclick="abrirZoomProducto()" style="display:none;">
                        <i class="fa-solid fa-magnifying-glass-plus"></i> Zoom
                    </button>
                    <div class="producto-modal-visual-frame" id="cantidadProductoVisualFrame">
                        <img src="" alt="" id="cantidadProductoImagen" style="display:none;">
                        <div class="producto-modal-visual-placeholder" id="cantidadProductoPlaceholder">
                            <i class="fa-solid fa-image"></i>
                            <span>Imagen no disponible</span>
                        </div>
                    </div>
                </div>
                <div class="checkout-section">
                    <div class="checkout-section-title"><i class="fa-solid fa-box"></i> Producto</div>
                    <strong id="cantidadProductoNombre">Producto</strong>
                    <p class="producto-modal-copy">Desea agregar este producto al carrito?</p>
                </div>
                <div class="checkout-section">
                    <div class="checkout-section-title"><i class="fa-solid fa-hashtag"></i> Cantidad</div>
                    <div class="cantidad-modal-ctrl">
                        <button type="button" onclick="cambiarCantidadModal(-1)">-</button>
                        <input type="number" id="cantidadProductoInput" value="1" min="1" class="checkout-input">
                        <button type="button" onclick="cambiarCantidadModal(1)">+</button>
                    </div>
                </div>
            </div>
            <div class="checkout-footer">
                <button class="btn-cancel-checkout" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn-confirm-checkout" onclick="confirmarCantidadProducto()">
                    <i class="fa-solid fa-check"></i> Agregar al carrito
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL ZOOM PRODUCTO -->
<div class="modal fade" id="modalZoomProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content checkout-modal zoom-producto-modal">
            <div class="checkout-header">
                <h4><i class="fa-solid fa-image" style="color:var(--primary)"></i> Vista del producto</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1)opacity(.5)"></button>
            </div>
            <div class="zoom-producto-body">
                <img src="" alt="" id="zoomProductoImagen">
            </div>
        </div>
    </div>
</div>

<!-- MODAL CHECKOUT -->
<div class="modal fade" id="modalCheckout" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content checkout-modal">
            <div class="checkout-header">
                <h4><i class="fa-solid fa-money-bill-wave" style="color:var(--primary)"></i> Finalizar Pedido</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1)opacity(.5)"></button>
            </div>
            <div class="checkout-body">
                <!-- Resumen -->
                <div class="checkout-section">
                    <div class="checkout-section-title"><i class="fa-solid fa-list"></i> Resumen del pedido</div>
                    <div id="checkoutResumen"></div>
                    <div class="checkout-total-row">
                        <span>Total de productos:</span>
                        <strong id="checkoutTotal" style="color:var(--primary);font-size:20px;"></strong>
                    </div>
                </div>
                <!-- Entrega -->
                <div class="checkout-section">
                    <div class="checkout-section-title"><i class="fa-solid fa-location-dot"></i> Direccion de entrega</div>
                    <div class="checkout-addr-options">
                        <label class="addr-option active" id="addrMiDir">
                            <input type="radio" name="addr" value="perfil" checked onchange="toggleAddrMode('perfil')">
                            <div>
                                <strong>Mi direccion registrada</strong>
                                <span><?= htmlspecialchars($usuario['domicilio'] ?? 'Sin direccion guardada') ?></span>
                            </div>
                        </label>
                        <label class="addr-option" id="addrOtraDir">
                            <input type="radio" name="addr" value="otra" onchange="toggleAddrMode('otra')">
                            <div>
                                <strong>Otra direccion</strong>
                                <span>Ingresar direccion diferente</span>
                            </div>
                        </label>
                    </div>
                    <input type="text" id="otraDireccion" class="checkout-input" placeholder="Escribe la direccion de entrega..." style="display:none;margin-top:12px;">
                </div>
                <!-- Tipo de pedido -->
                <div class="checkout-section">
                    <div class="checkout-section-title"><i class="fa-solid fa-file-signature"></i> Pedido</div>
                    <input type="hidden" name="tipo_pedido" value="formal">
                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;">El pedido se registra en modo estandar.</div>
                    <input type="text" id="notaPedido" class="checkout-input" style="margin-top:10px;" placeholder="Nota o ajuste general (opcional)">
                </div>
                <!-- Pago -->
                <div class="checkout-section">
                    <div class="checkout-section-title"><i class="fa-solid fa-wallet"></i> Metodo de pago</div>
                    <div class="pago-options">
                        <label class="pago-option active">
                            <input type="radio" name="pago" value="efectivo" checked>
                            <i class="fa-solid fa-money-bill-wave"></i>
                            <span>Efectivo</span>
                        </label>
                        
                    </div>
                </div>
            </div>
            <div class="checkout-footer">
                <button class="btn-cancel-checkout" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn-confirm-checkout" onclick="confirmarPedido()">
                    <span id="confirmText"><i class="fa-solid fa-check"></i> Confirmar Pedido</span>
                    <span id="confirmLoading" style="display:none;"><i class="fa-solid fa-spinner fa-spin"></i> Procesando...</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js?v=<?= (int)$dashboardJsVer ?>"></script>
<script>
window.tiendaConfig = {
    openProductId: <?= (int)$productoSolicitado ?>,
    currentView: <?= json_encode($vistaCliente, JSON_UNESCAPED_UNICODE) ?>,
    notificationStateKey: <?= json_encode($notificacionPedidosState, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="../assets/js/tienda.js?v=<?= (int)$tiendaJsVer ?>"></script>
</body>
</html>







