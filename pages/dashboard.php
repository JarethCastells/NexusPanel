<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireAuth();

$usuario = usuarioActual();
$isAdmin = $usuario['rol'] === 'administrador';
$isInventario = esInventario();

if ($usuario['rol'] === 'operador') {
    header('Location: operador_pedidos.php?tab=pendientes');
    exit;
}

if ($isInventario) {
    header('Location: pedidos.php');
    exit;
}

$inicioMes = date('Y-m-01 00:00:00');
$finMes = date('Y-m-t 23:59:59');
$diasMes = (int)date('t');

$pedidosPendientes = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado='pendiente'")->fetchColumn();

$stUsuariosMes = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE created_at BETWEEN ? AND ?");
$stUsuariosMes->execute([$inicioMes, $finMes]);
$usuariosMes = (int)$stUsuariosMes->fetchColumn();

$stVentasMes = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE estado <> 'cancelado' AND created_at BETWEEN ? AND ?");
$stVentasMes->execute([$inicioMes, $finMes]);
$ventasMes = (float)$stVentasMes->fetchColumn();

$stComprasMes = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE estado <> 'cancelado' AND created_at BETWEEN ? AND ?");
$stComprasMes->execute([$inicioMes, $finMes]);
$comprasMes = (int)$stComprasMes->fetchColumn();

$stockTotal = (int)$pdo->query("SELECT COALESCE(SUM(stock),0) FROM productos WHERE activo = 1")->fetchColumn();

if (!isset($_SESSION['admin_stock_threshold'])) {
    $_SESSION['admin_stock_threshold'] = 20;
}
if (isset($_GET['stock_umbral']) && $_GET['stock_umbral'] !== '') {
    $_SESSION['admin_stock_threshold'] = max(1, min(5000, (int)$_GET['stock_umbral']));
}
$stockUmbral = (int)$_SESSION['admin_stock_threshold'];

$stAlertas = $pdo->prepare("\n    SELECT\n        COALESCE(e.nombre, 'Sin categoria') AS categoria,\n        SUM(COALESCE(p.stock, 0)) AS stock_total,\n        SUM(CASE WHEN p.stock <= ? THEN 1 ELSE 0 END) AS productos_bajos\n    FROM productos p\n    LEFT JOIN especies e ON e.id = p.especie_id\n    WHERE p.activo = 1\n    GROUP BY COALESCE(e.nombre, 'Sin categoria')\n    ORDER BY productos_bajos DESC, stock_total ASC\n");
$stAlertas->execute([$stockUmbral]);
$alertasCategorias = $stAlertas->fetchAll();
$categoriasCriticas = array_values(array_filter($alertasCategorias, static function ($row) {
    return (int)($row['productos_bajos'] ?? 0) > 0;
}));
$categoriaCriticaPrincipal = $categoriasCriticas[0] ?? null;

$stSerie = $pdo->prepare("\n    SELECT DATE(created_at) AS fecha, COUNT(*) AS compras, COALESCE(SUM(total),0) AS ventas\n    FROM pedidos\n    WHERE estado <> 'cancelado' AND created_at BETWEEN ? AND ?\n    GROUP BY DATE(created_at)\n    ORDER BY DATE(created_at) ASC\n");
$stSerie->execute([$inicioMes, $finMes]);
$serieRaw = $stSerie->fetchAll();
$serieMap = [];
foreach ($serieRaw as $row) {
    $serieMap[$row['fecha']] = [
        'compras' => (int)$row['compras'],
        'ventas' => (float)$row['ventas'],
    ];
}
$chartLabels = [];
$chartCompras = [];
$chartVentas = [];
for ($d = 1; $d <= $diasMes; $d++) {
    $fecha = date('Y-m-') . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
    $chartLabels[] = $d;
    $chartCompras[] = (int)($serieMap[$fecha]['compras'] ?? 0);
    $chartVentas[] = (float)($serieMap[$fecha]['ventas'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <script src="../assets/js/theme.js"></script>
    <style>
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
    </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="logo-icon-sm"><i class="fa-solid fa-hexagon-nodes"></i></div>
            <span class="logo-text-sm">Nexus<strong>Panel</strong></span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($usuario['nombre'], 0, 1)) ?></div>
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($usuario['nombre']) ?></span>
            <span class="user-role <?= $isAdmin ? 'role-admin' : 'role-operator' ?>">
                <i class="fa-solid <?= $isAdmin ? 'fa-shield-halved' : 'fa-user-gear' ?>"></i>
                <?= htmlspecialchars(nombreRolActual()) ?>
            </span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="dashboard.php" class="nav-item active"><i class="fa-solid fa-chart-line"></i><span>Inicio</span><div class="nav-indicator"></div></a>
        <a href="usuarios.php" class="nav-item"><i class="fa-solid fa-users"></i><span>Usuarios</span></a>
        <a href="productos.php" class="nav-item"><i class="fa-solid fa-flask-vial"></i><span>Productos e inventario</span></a>
        <a href="pedidos.php" class="nav-item"><i class="fa-solid fa-receipt"></i><span>Pedidos</span></a>
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
            <div class="breadcrumb-custom"><span>NexusPanel</span><i class="fa-solid fa-chevron-right"></i><span class="active">Dashboard</span></div>
        </div>
        <div class="topbar-right">
            <div class="topbar-date" id="topbarDate"></div>
            
            <button class="topbar-btn theme-toggle" onclick="toggleTheme()" title="Cambiar tema">
                <i class="fa-solid fa-moon theme-toggle-icon"></i>
            </button>

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
        <div class="welcome-banner">
            <div class="welcome-text">
                <h1>Bienvenido, <?= htmlspecialchars(explode(' ', $usuario['nombre'])[0]) ?></h1>
                <p>Dashboard administrativo con metrica operativa mensual.</p>
            </div>
            <div class="welcome-badge badge-admin"><i class="fa-solid fa-shield-halved"></i> Administrador</div>
        </div>

        <div class="card-panel" style="margin-bottom:20px;">
            <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <h3 class="panel-title">Resumen mensual administrativo</h3>
                    <p class="panel-subtitle">Indicadores de usuarios, compras y stock.</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="pedidos.php" class="btn-panel"><i class="fa-solid fa-receipt"></i> Pedidos</a>
                    <a href="inventario.php" class="btn-panel"><i class="fa-solid fa-boxes-stacked"></i> Inventario</a>
                    <a href="usuarios.php" class="btn-panel"><i class="fa-solid fa-users"></i> Usuarios</a>
                    <a href="../api/reportes.php?action=excel_pedidos" class="btn-panel"><i class="fa-solid fa-file-export"></i> Excel pedidos</a>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card" style="--card-accent: #00d4ff;">
                <div class="stat-card-icon" style="background: rgba(0,212,255,0.1); color: #00d4ff;"><i class="fa-solid fa-user-plus"></i></div>
                <div class="stat-card-data"><span class="stat-card-number"><?= $usuariosMes ?></span><span class="stat-card-label">Perfiles registrados este mes</span></div>
            </div>
            <div class="stat-card" style="--card-accent: #f59e0b;">
                <div class="stat-card-icon" style="background: rgba(245,158,11,0.12); color: #f59e0b;"><i class="fa-solid fa-boxes-stacked"></i></div>
                <div class="stat-card-data"><span class="stat-card-number"><?= number_format($stockTotal) ?></span><span class="stat-card-label">Total de productos en stock</span></div>
            </div>
            <div class="stat-card" style="--card-accent: #7c3aed;">
                <div class="stat-card-icon" style="background: rgba(124,58,237,0.12); color: #a78bfa;"><i class="fa-solid fa-cart-flatbed"></i></div>
                <div class="stat-card-data"><span class="stat-card-number"><?= $comprasMes ?></span><span class="stat-card-label">Compras realizadas este mes</span></div>
            </div>
        </div>

        <div class="card-panel" style="margin-top:18px;margin-bottom:18px;border-color: <?= $categoriaCriticaPrincipal ? 'rgba(239,68,68,.45)' : 'var(--border)' ?>;">
            <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <h3 class="panel-title"><i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;margin-right:8px;"></i>Alerta de stock por categoria</h3>
                    <?php if ($categoriaCriticaPrincipal): ?>
                    <p class="panel-subtitle" style="color:#fca5a5;">
                        La categoria <?= htmlspecialchars((string)$categoriaCriticaPrincipal['categoria']) ?> esta por agotarse (<?= (int)$categoriaCriticaPrincipal['productos_bajos'] ?> producto(s) en o por debajo de <?= $stockUmbral ?> unidades).
                    </p>
                    <?php else: ?>
                    <p class="panel-subtitle">Sin categorias criticas para el umbral configurado.</p>
                    <?php endif; ?>
                </div>
                <form method="GET" style="display:flex;gap:8px;align-items:center;">
                    <label for="stock_umbral" style="font-size:12px;color:var(--text-muted);">Umbral</label>
                    <input id="stock_umbral" name="stock_umbral" type="number" min="1" max="5000" value="<?= $stockUmbral ?>" class="modal-input" style="width:92px;padding:8px 10px;">
                    <button type="submit" class="btn-panel"><i class="fa-solid fa-sliders"></i> Aplicar</button>
                </form>
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Categoria</th><th>Stock total</th><th>Productos por debajo del umbral</th></tr></thead>
                    <tbody>
                    <?php foreach ($alertasCategorias as $cat): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$cat['categoria']) ?></td>
                            <td><?= number_format((int)$cat['stock_total']) ?></td>
                            <td><?= (int)$cat['productos_bajos'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>


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
initPanelNotis();
</script>
</body>
</html>






