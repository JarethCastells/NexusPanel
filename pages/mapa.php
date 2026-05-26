<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireAuth();

// Obtener usuarios con coordenadas
$usuarios = $pdo->query("SELECT id, nombre, email, telefono, domicilio, edad, rol, lat, lng, activo FROM usuarios WHERE lat IS NOT NULL AND lng IS NOT NULL AND activo = 1")->fetchAll();
$sinUbicacion = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE (lat IS NULL OR lng IS NULL) AND activo = 1")->fetchColumn();
$totalConUbic = count($usuarios);

$usuario = usuarioActual();
$isAdmin = $usuario['rol'] === 'administrador';

// Pasar datos al JS como JSON
$usuariosJson = json_encode(array_map(fn($u) => [
    'id'        => $u['id'],
    'nombre'    => $u['nombre'],
    'email'     => $u['email'],
    'telefono'  => $u['telefono'] ?? '-',
    'domicilio' => $u['domicilio'] ?? '-',
    'edad'      => $u['edad'] ?? '-',
    'rol'       => $u['rol'],
    'lat'       => (float)$u['lat'],
    'lng'       => (float)$u['lng'],
], $usuarios));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Mapa de Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <script src="../assets/js/theme.js"></script>
    <style>
    /* ---- MAPA PAGE ---- */
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

    .mapa-layout {
        display: flex;
        height: calc(100vh - 64px);
        overflow: hidden;
    }

    /* Panel lateral */
    .mapa-sidebar {
        width: 320px;
        flex-shrink: 0;
        background: var(--bg-card);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .mapa-sidebar-header {
        padding: 20px;
        border-bottom: 1px solid var(--border);
        flex-shrink: 0;
    }
    .mapa-sidebar-header h3 { font-size: 16px; font-weight: 600; margin-bottom: 4px; }
    .mapa-sidebar-header p  { font-size: 12px; color: var(--text-muted); }

    .mapa-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        padding: 16px 20px;
        border-bottom: 1px solid var(--border);
        flex-shrink: 0;
    }
    .mapa-stat {
        background: var(--bg-input);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 12px;
        text-align: center;
    }
    .mapa-stat-num  { font-family: var(--font-mono); font-size: 24px; font-weight: 600; color: var(--primary); }
    .mapa-stat-label{ font-size: 11px; color: var(--text-muted); margin-top: 2px; }

    /* Lista de usuarios */
    .mapa-user-list {
        flex: 1;
        overflow-y: auto;
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .mapa-user-list::-webkit-scrollbar { width: 4px; }
    .mapa-user-list::-webkit-scrollbar-track { background: transparent; }
    .mapa-user-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

    .mapa-user-item {
        padding: 12px 14px;
        background: var(--bg-input);
        border: 1px solid var(--border);
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: fadeInUp 0.3s ease both;
    }
    .mapa-user-item:hover  { border-color: var(--border-hover); background: rgba(0,212,255,0.04); transform: translateX(3px); }
    .mapa-user-item.active { border-color: var(--primary); background: var(--primary-dim); }

    .mapa-user-avatar {
        width: 36px; height: 36px;
        background: linear-gradient(135deg, var(--primary), var(--accent));
        border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        font-size: 14px; font-weight: 700;
        flex-shrink: 0;
    }
    .mapa-user-info { flex: 1; min-width: 0; }
    .mapa-user-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mapa-user-addr { font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
    .mapa-user-pin  { color: var(--primary); font-size: 14px; flex-shrink: 0; }

    /* Mapa principal */
    #mapaOperador { flex: 1; z-index: 1; }

    /* Info panel flotante */
    .mapa-info-panel {
        position: absolute;
        top: 80px; right: 20px;
        width: 300px;
        background: rgba(13,20,34,0.95);
        border: 1px solid rgba(0,212,255,0.2);
        border-radius: 16px;
        padding: 20px;
        z-index: 1000;
        backdrop-filter: blur(20px);
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        display: none;
        animation: panelIn 0.3s cubic-bezier(0.22,1,0.36,1);
    }
    @keyframes panelIn { from{opacity:0;transform:translateY(-10px) scale(0.97)} to{opacity:1;transform:translateY(0) scale(1)} }

    .info-close {
        position: absolute; top: 12px; right: 12px;
        background: none; border: none;
        color: var(--text-muted); cursor: pointer;
        font-size: 14px; transition: color 0.2s;
    }
    .info-close:hover { color: var(--text-primary); }

    .info-avatar {
        width: 48px; height: 48px;
        background: linear-gradient(135deg, var(--primary), var(--accent));
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 20px; font-weight: 700;
        margin-bottom: 14px;
    }
    .info-name { font-size: 17px; font-weight: 700; margin-bottom: 4px; }
    .info-role { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; padding: 3px 10px; border-radius: 100px; margin-bottom: 16px; }
    .info-role.admin    { background: rgba(245,158,11,0.1); color: #f59e0b; border: 1px solid rgba(245,158,11,0.2); }
    .info-role.operator { background: rgba(0,212,255,0.1); color: var(--primary); border: 1px solid rgba(0,212,255,0.2); }

    .info-details { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; }
    .info-row { display: flex; align-items: flex-start; gap: 10px; font-size: 13px; }
    .info-row i { width: 16px; color: var(--primary); margin-top: 2px; flex-shrink: 0; }
    .info-row span { color: var(--text-muted); }
    .info-row strong { color: var(--text-primary); }

    .btn-ruta {
        display: flex; align-items: center; justify-content: center; gap: 8px;
        width: 100%; padding: 12px;
        background: linear-gradient(135deg, #10b981, #059669);
        border: none; border-radius: 10px;
        color: #fff; font-family: var(--font-main); font-size: 14px; font-weight: 600;
        cursor: pointer; text-decoration: none;
        transition: all 0.2s;
    }
    .btn-ruta:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16,185,129,0.3); color: #fff; }

    .info-coords {
        margin-top: 12px;
        font-family: var(--font-mono);
        font-size: 10px;
        color: var(--text-dim);
        text-align: center;
        padding-top: 12px;
        border-top: 1px solid var(--border);
    }

    /* Sin ubicacion */
    .sin-ubicacion-banner {
        position: absolute;
        bottom: 20px; left: 50%; transform: translateX(-50%);
        background: rgba(245,158,11,0.1);
        border: 1px solid rgba(245,158,11,0.3);
        border-radius: 100px;
        padding: 8px 20px;
        font-size: 12px;
        color: #f59e0b;
        z-index: 1000;
        display: flex; align-items: center; gap: 8px;
        backdrop-filter: blur(10px);
        white-space: nowrap;
    }

    /* Leaflet theme-aware */
    .leaflet-container { background: var(--bg-primary) !important; }
    [data-theme="dark"] .leaflet-tile { filter: brightness(0.7) saturate(0.8) hue-rotate(190deg) contrast(1.1); }
    [data-theme="light"] .leaflet-tile { filter: none; }
    .leaflet-popup-content-wrapper {
        background: #0d1422 !important; color: #f0f6ff !important;
        border: 1px solid rgba(0,212,255,0.25) !important;
        border-radius: 12px !important;
        box-shadow: 0 10px 40px rgba(0,0,0,0.6) !important;
        font-family: 'Sora', sans-serif !important;
    }
    .leaflet-popup-tip { background: #0d1422 !important; }
    .leaflet-popup-content { margin: 14px 16px !important; font-size: 13px !important; }
    .popup-name { font-weight: 700; font-size: 15px; margin-bottom: 6px; }
    .popup-detail { color: #5a7090; margin-bottom: 3px; }
    .popup-detail strong { color: #f0f6ff; }

    @media (max-width: 768px) {
        .mapa-layout {
            flex-direction: column;
            height: auto;
            min-height: calc(100dvh - 64px);
        }
        .mapa-sidebar {
            width: 100%;
            height: auto;
            max-height: none;
            flex-shrink: 0;
            overflow: visible;
        }
        .mapa-user-list {
            max-height: 150px;
        }
        #mapaOperador {
            width: 100%;
            height: 52dvh;
            min-height: 300px;
            max-height: 420px;
            position: relative;
            z-index: 1;
        }
        .mapa-info-panel { width: calc(100% - 24px); right: 12px; top: 76px; }
        .mapa-user-item { padding: 10px 12px; }
        .mapa-user-name { font-size: 12px; }
        .mapa-user-addr { font-size: 10px; }
        .sin-ubicacion-banner {
            left: 12px;
            right: 12px;
            transform: none;
            justify-content: center;
            text-align: center;
            white-space: normal;
        }
        .leaflet-tile { filter: none !important; }
        .leaflet-container { background: #dfe7ef !important; }
    }

    @media (max-width: 480px) {
        .mapa-stats {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding: 12px;
        }
        .mapa-stat { padding: 8px; }
        .mapa-stat-num { font-size: 22px; }
        .mapa-stat-label { font-size: 10px; }
        #mapaOperador {
            height: 300px;
            min-height: 300px;
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
        <div class="user-avatar"><?= strtoupper(substr($usuario['nombre'],0,1)) ?></div>
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($usuario['nombre']) ?></span>
            <span class="user-role <?= $isAdmin?'role-admin':'role-operator' ?>">
                <i class="fa-solid <?= $isAdmin?'fa-shield-halved':'fa-user-gear' ?>"></i>
                <?= ucfirst($usuario['rol']) ?>
            </span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="dashboard.php" class="nav-item"><i class="fa-solid fa-chart-line"></i><span>Inicio</span></a>
        <?php if ($isAdmin): ?>
        <a href="usuarios.php" class="nav-item"><i class="fa-solid fa-users"></i><span>Usuarios</span></a>
        <?php endif; ?>
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
            <div class="breadcrumb-custom">
                <span>NexusPanel</span>
                <i class="fa-solid fa-chevron-right"></i>
                <span class="active">Mapa de Usuarios</span>
            </div>
        </div>
        <div class="topbar-right">
            <button class="topbar-btn theme-toggle" onclick="toggleTheme()" title="Cambiar tema">
                <i class="fa-solid fa-moon theme-toggle-icon"></i>
            </button>
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

    <!-- Mapa layout -->
    <div class="mapa-layout" style="position:relative;">

        <!-- Sidebar del mapa -->
        <div class="mapa-sidebar">
            <div class="mapa-sidebar-header">
                <h3><i class="fa-solid fa-map-location-dot" style="color:var(--primary);margin-right:8px;"></i>Usuarios en el mapa</h3>
                <p>Haz clic en un usuario para centrar el mapa</p>
            </div>

            <div class="mapa-stats">
                <div class="mapa-stat">
                    <div class="mapa-stat-num"><?= $totalConUbic ?></div>
                    <div class="mapa-stat-label">Con ubicacion</div>
                </div>
                <div class="mapa-stat">
                    <div class="mapa-stat-num" style="color:var(--warning);"><?= $sinUbicacion ?></div>
                    <div class="mapa-stat-label">Sin ubicacion</div>
                </div>
            </div>

            <div class="mapa-user-list" id="listaUsuarios">
                <?php if (empty($usuarios)): ?>
                <div style="text-align:center;padding:40px 20px;color:var(--text-muted);font-size:13px;">
                    <i class="fa-solid fa-map-location-dot" style="font-size:32px;color:var(--text-dim);display:block;margin-bottom:12px;"></i>
                    Ningun usuario tiene ubicacion registrada aun.
                </div>
                <?php else: ?>
                <?php foreach ($usuarios as $i => $u): ?>
                <div class="mapa-user-item" id="user-item-<?= $u['id'] ?>"
                     onclick="focusUser(<?= $u['id'] ?>, <?= $u['lat'] ?>, <?= $u['lng'] ?>)"
                     style="animation-delay:<?= $i * 0.05 ?>s">
                    <div class="mapa-user-avatar"><?= strtoupper(substr($u['nombre'],0,1)) ?></div>
                    <div class="mapa-user-info">
                        <div class="mapa-user-name"><?= htmlspecialchars($u['nombre']) ?></div>
                        <div class="mapa-user-addr">
                            <i class="fa-solid fa-location-dot" style="font-size:10px;"></i>
                            <?= htmlspecialchars(mb_strimwidth($u['domicilio']??'Sin direccion',0,35,'...')) ?>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right mapa-user-pin"></i>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mapa Leaflet -->
        <div id="mapaOperador"></div>

        <!-- Info panel flotante -->
        <div class="mapa-info-panel" id="infoPanel">
            <button class="info-close" onclick="closeInfo()"><i class="fa-solid fa-xmark"></i></button>
            <div class="info-avatar" id="infoAvatar">U</div>
            <div class="info-name" id="infoNombre">-</div>
            <div class="info-role" id="infoRol"></div>
            <div class="info-details">
                <div class="info-row">
                    <i class="fa-solid fa-location-dot"></i>
                    <div><div style="font-size:10px;color:var(--text-dim);margin-bottom:2px;">DOMICILIO</div><strong id="infoDomicilio">-</strong></div>
                </div>
                <div class="info-row">
                    <i class="fa-solid fa-phone"></i>
                    <div><div style="font-size:10px;color:var(--text-dim);margin-bottom:2px;">TELEFONO</div><strong id="infoTelefono">-</strong></div>
                </div>
                <div class="info-row">
                    <i class="fa-solid fa-envelope"></i>
                    <div><div style="font-size:10px;color:var(--text-dim);margin-bottom:2px;">CORREO</div><strong id="infoEmail">-</strong></div>
                </div>
                <div class="info-row">
                    <i class="fa-solid fa-cake-candles"></i>
                    <div><div style="font-size:10px;color:var(--text-dim);margin-bottom:2px;">EDAD</div><strong id="infoEdad">-</strong></div>
                </div>
            </div>
            <a href="#" target="_blank" class="btn-ruta" id="btnRuta">
                <i class="fa-solid fa-diamond-turn-right"></i>
                Como llegar - Google Maps
            </a>
            <div class="info-coords" id="infoCoords"></div>
        </div>

        <?php if ($sinUbicacion > 0): ?>
        <div class="sin-ubicacion-banner">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <?= $sinUbicacion ?> usuario<?= $sinUbicacion > 1 ? 's' : '' ?> sin ubicacion registrada
        </div>
        <?php endif; ?>
    </div>

</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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

// Datos de usuarios desde PHP
const USUARIOS = <?= $usuariosJson ?>;

// ---- MAPA ----
const map = L.map('mapaOperador', { zoomControl: true });
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
}).addTo(map);

// Ajustar vista inicial
if (USUARIOS.length > 0) {
    const isMobile = window.matchMedia('(max-width: 768px)').matches;
    if (isMobile) {
        const first = USUARIOS[0];
        map.setView([first.lat, first.lng], 11);
        setTimeout(() => focusUser(first.id, first.lat, first.lng), 220);
    } else {
        const bounds = L.latLngBounds(USUARIOS.map(u => [u.lat, u.lng]));
        map.fitBounds(bounds, { padding: [40, 40] });
    }
} else {
    map.setView([19.4326, -99.1332], 5); // Mexico
}
setTimeout(() => map.invalidateSize(true), 220);
window.addEventListener('resize', () => map.invalidateSize(true));
window.addEventListener('orientationchange', () => {
    setTimeout(() => map.invalidateSize(true), 280);
});

// Marcadores
const markers = {};

function makeIcon(rol) {
    const color = rol === 'administrador' ? '#f59e0b' : '#00d4ff';
    const shadow = rol === 'administrador' ? 'rgba(245,158,11,0.5)' : 'rgba(0,212,255,0.5)';
    return L.divIcon({
        html: `<div style="
            width:32px;height:32px;
            background:${color};
            border-radius:50% 50% 50% 0;
            transform:rotate(-45deg);
            border:3px solid #fff;
            box-shadow:0 4px 15px ${shadow};
            transition:transform 0.2s;
        "></div>`,
        iconSize: [32,32], iconAnchor: [16,32], popupAnchor: [0,-34], className: '',
    });
}

USUARIOS.forEach(u => {
    const m = L.marker([u.lat, u.lng], { icon: makeIcon(u.rol) }).addTo(map);

    m.bindPopup(`
        <div class="popup-name">${u.nombre}</div>
        <div class="popup-detail"><strong>${u.domicilio}</strong></div>
        <div class="popup-detail"><strong>Tel:</strong> ${u.telefono}</div>
        <div class="popup-detail"><strong>Email:</strong> ${u.email}</div>
    `);

    m.on('click', () => showInfoPanel(u));
    markers[u.id] = m;
});

// ---- INFO PANEL ----
function showInfoPanel(u) {
    document.getElementById('infoAvatar').textContent  = u.nombre.charAt(0).toUpperCase();
    document.getElementById('infoNombre').textContent  = u.nombre;
    document.getElementById('infoDomicilio').textContent = u.domicilio;
    document.getElementById('infoTelefono').textContent = u.telefono;
    document.getElementById('infoEmail').textContent    = u.email;
    document.getElementById('infoEdad').textContent     = u.edad !== '-' ? u.edad + ' anios' : '-';
    document.getElementById('infoCoords').textContent   = `${u.lat.toFixed(5)}, ${u.lng.toFixed(5)}`;

    const rolEl = document.getElementById('infoRol');
    rolEl.className = `info-role ${u.rol === 'administrador' ? 'admin' : 'operator'}`;
    rolEl.innerHTML = `<i class="fa-solid ${u.rol==='administrador'?'fa-shield-halved':'fa-user-gear'}"></i> ${u.rol.charAt(0).toUpperCase()+u.rol.slice(1)}`;

    // Link a Google Maps con ruta
    document.getElementById('btnRuta').href = `https://www.google.com/maps/dir/?api=1&destination=${u.lat},${u.lng}&travelmode=driving`;

    document.getElementById('infoPanel').style.display = 'block';

    // Highlight en lista
    document.querySelectorAll('.mapa-user-item').forEach(el => el.classList.remove('active'));
    const item = document.getElementById(`user-item-${u.id}`);
    if (item) { item.classList.add('active'); item.scrollIntoView({ behavior:'smooth', block:'nearest' }); }
}

function closeInfo() {
    document.getElementById('infoPanel').style.display = 'none';
    document.querySelectorAll('.mapa-user-item').forEach(el => el.classList.remove('active'));
}

// ---- FOCUS USER desde lista ----
function focusUser(id, lat, lng) {
    map.flyTo([lat, lng], 16, { animate: true, duration: 1.2 });
    const u = USUARIOS.find(u => u.id === id);
    if (u) {
        setTimeout(() => {
            markers[id].openPopup();
            showInfoPanel(u);
        }, 800);
    }
}

// ---- CLOCK ----
function updateClock() {
    const el = document.getElementById('topbarDate'); if(!el)return;
    const now = new Date();
    el.textContent = now.toLocaleDateString('es-MX',{weekday:'short',day:'2-digit',month:'short'})+' - '+now.toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
}
updateClock(); setInterval(updateClock, 1000);

// Mobile sidebar
const mobileBtn = document.getElementById('mobileMenu');
const sidebar   = document.getElementById('sidebar');
if (mobileBtn && sidebar) {
    mobileBtn.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        document.body.classList.toggle('sidebar-open', sidebar.classList.contains('open'));
        setTimeout(() => map.invalidateSize(true), 220);
    });

    document.addEventListener('click', (e) => {
        if (
            window.matchMedia('(max-width: 768px)').matches &&
            sidebar.classList.contains('open') &&
            !sidebar.contains(e.target) &&
            !mobileBtn.contains(e.target)
        ) {
            sidebar.classList.remove('open');
            document.body.classList.remove('sidebar-open');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            document.body.classList.remove('sidebar-open');
        }
    });

    window.addEventListener('resize', () => {
        if (!window.matchMedia('(max-width: 768px)').matches && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            document.body.classList.remove('sidebar-open');
        }
    });
}
initPanelNotis();
</script>
</body>
</html>






