<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireGestion();

$usuario = usuarioActual();
$esAdmin = esAdmin();
$esInventario = esInventario();
$nombreRol = nombreRolActual();
$inicioHref = $esInventario ? 'inventario.php?vista=inicio' : 'dashboard.php';

$operadores = [];
try {
    $sql = "
        SELECT id, nombre, telefono
        FROM usuarios
        WHERE rol = 'operador'
        " . (columnExists($pdo, 'usuarios', 'activo') ? "AND activo = 1" : "") . "
        ORDER BY nombre ASC
        LIMIT 200
    ";
    $operadores = $pdo->query($sql)->fetchAll();
} catch (Throwable $e) {
    $operadores = [];
}
$operadorId = (int)($_GET['operador_id'] ?? ($operadores[0]['id'] ?? 0));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Mensajes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .msg-layout { display:grid; grid-template-columns: 320px 1fr; gap: 12px; min-height: 72vh; }
        .msg-list { border:1px solid var(--border); border-radius:14px; background:rgba(8,18,35,.86); padding:10px; overflow:auto; }
        .msg-card { border:1px solid rgba(86,113,162,.32); border-radius:12px; padding:10px; margin-bottom:8px; cursor:pointer; background:rgba(10,23,42,.78); }
        .msg-card.active { border-color:rgba(0,212,255,.7); box-shadow:0 0 0 1px rgba(0,212,255,.2) inset; }
        .msg-card strong { color:#eef6ff; display:block; }
        .msg-card small { color:var(--text-muted); display:block; margin-top:2px; }
        .msg-chat { border:1px solid var(--border); border-radius:14px; background:rgba(8,18,35,.9); display:grid; grid-template-rows:auto 1fr auto; min-height:72vh; overflow:hidden; }
        .msg-head { padding:12px; border-bottom:1px solid var(--border); }
        .msg-body { padding:12px; overflow:auto; display:grid; gap:10px; align-content:start; background:#070f1f; }
        .msg-bubble { max-width:76%; border-radius:14px; padding:9px 11px; border:1px solid rgba(86,113,162,.35); }
        .msg-mine { margin-left:auto; background:linear-gradient(135deg, rgba(0,212,255,.2), rgba(37,99,235,.25)); border-color:rgba(56,189,248,.55); }
        .msg-theirs { margin-right:auto; background:rgba(15,27,49,.92); }
        .msg-time { font-size:11px; color:var(--text-muted); margin-top:4px; }
        .msg-foot { border-top:1px solid var(--border); padding:10px; display:grid; grid-template-columns:1fr auto; gap:8px; }
        .notif-count {
            position:absolute; top:-2px; right:-2px; min-width:18px; height:18px; border-radius:999px;
            background:#ef4444; color:#fff; font-size:11px; font-weight:800; display:flex; align-items:center; justify-content:center; padding:0 5px;
        }
        .notif-count.hidden { display:none; }
        .panel-noti-dropdown { width:360px; max-width:calc(100vw - 24px); border:1px solid var(--border); background:#0b1528; }
        .panel-noti-header { padding:10px 12px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; color:#fff; }
        .panel-noti-list { max-height:320px; overflow:auto; padding:8px; display:grid; gap:8px; }
        .panel-noti-item { display:block; border:1px solid var(--border); border-radius:10px; background:rgba(255,255,255,.02); color:inherit; text-decoration:none; padding:10px; }
        .panel-noti-item strong { display:block; font-size:13px; color:#fff; }
        .panel-noti-item small { display:block; color:var(--text-muted); margin-top:2px; font-size:11px; }
        @media (max-width: 900px) { .msg-layout { grid-template-columns: 1fr; } .msg-list { max-height:35vh; } }
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
        <div class="user-info"><span class="user-name"><?= htmlspecialchars($usuario['nombre']) ?></span><span class="user-role role-admin"><i class="fa-solid fa-comments"></i> <?= htmlspecialchars($nombreRol) ?></span></div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="<?= htmlspecialchars($inicioHref) ?>" class="nav-item"><i class="fa-solid fa-gauge-high"></i><span>Inicio</span></a>
        <?php if ($esAdmin): ?><a href="usuarios.php" class="nav-item"><i class="fa-solid fa-users"></i><span>Usuarios</span></a><?php endif; ?>
        <a href="productos.php" class="nav-item"><i class="fa-solid fa-pills"></i><span>Productos e inventario</span></a>
        <a href="pedidos.php" class="nav-item"><i class="fa-solid fa-clipboard-check"></i><span>Pedidos</span></a><a href="mensajes.php" class="nav-item active"><i class="fa-solid fa-comments"></i><span>Mensajes</span><div class="nav-indicator"></div></a>
        <?php if ($esAdmin): ?><div class="nav-section-label">Operaciones</div>
        <a href="mapa.php" class="nav-item"><i class="fa-solid fa-map-location-dot"></i><span>Mapa de Usuarios</span></a><?php endif; ?>
        <div class="nav-section-label">Cuenta</div>
        
        <a href="../logout.php" class="nav-item nav-logout"><i class="fa-solid fa-right-from-bracket"></i><span>Cerrar sesion</span></a>
    </nav>
</aside>

<main class="main-content">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenu"><i class="fa-solid fa-bars"></i></button>
            <div class="breadcrumb-custom"><span>NexusPanel</span><i class="fa-solid fa-chevron-right"></i><span class="active">Mensajes</span></div>
        </div>
        <div class="topbar-right">
            <button id="btnToggleTheme" class="topbar-btn" title="Cambiar Paleta" onclick="toggleTheme()"><i class="fa-solid fa-palette"></i></button>
            <div class="topbar-date" id="topbarDate"></div>
            <a class="topbar-btn" href="mensajes.php" title="Mensajes"><i class="fa-solid fa-comments"></i></a>
            <div class="dropdown">
                <button class="topbar-btn" type="button" id="panelNotiBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Notificaciones">
                    <i class="fa-solid fa-bell"></i><span class="notif-count hidden" id="panelNotiCount">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0 panel-noti-dropdown" aria-labelledby="panelNotiBtn">
                    <div class="panel-noti-header"><strong>Notificaciones</strong><button id="btnPanelNotiClear" class="btn btn-sm btn-link p-0" type="button">Limpiar</button></div>
                    <div class="panel-noti-list" id="panelNotiList"><div style="color:var(--text-muted);font-size:12px;padding:8px;">Sin notificaciones.</div></div>
                </div>
            </div>
        </div>
    </header>

    <div class="content-area">
        <div class="card-panel">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Chat con operadores</h3>
                    <p class="panel-subtitle">Canal directo entre Admin/Inventario y operadores.</p>
                </div>
            </div>
            <div class="msg-layout">
                <div class="msg-list" id="operadorList">
                    <?php if (empty($operadores)): ?>
                        <div style="padding:12px;color:var(--text-muted);">Sin operadores activos.</div>
                    <?php else: ?>
                        <?php foreach ($operadores as $op): ?>
                            <div class="msg-card <?= (int)$op['id'] === $operadorId ? 'active' : '' ?>" data-operador-id="<?= (int)$op['id'] ?>" data-operador-nombre="<?= htmlspecialchars((string)$op['nombre']) ?>">
                                <strong><?= htmlspecialchars((string)$op['nombre']) ?></strong>
                                <small><?= htmlspecialchars((string)($op['telefono'] ?: 'Sin telefono')) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="msg-chat">
                    <div class="msg-head">
                        <div style="font-weight:700;color:#f1f5f9;" id="chatHeadTitle">Selecciona un operador</div>
                        <div style="font-size:12px;color:var(--text-muted);" id="chatHeadSub">Mensajes en tiempo real.</div>
                    </div>
                    <div class="msg-body" id="chatBody"><div style="color:var(--text-muted);font-size:13px;">Sin conversacion seleccionada.</div></div>
                    <form class="msg-foot" id="chatForm">
                        <input id="chatInput" class="modal-input" placeholder="Escribe un mensaje..." disabled>
                        <button class="btn-primary-custom btn-table" id="chatSendBtn" type="submit" disabled><i class="fa-solid fa-paper-plane"></i> Enviar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
<script>
const PANEL_USER_ID = <?= (int)($usuario['usuario_id'] ?? 0) ?>;
let panelNotiCache = [];
let chatOperadorId = <?= (int)$operadorId ?>;
let chatLastId = 0;
let chatTimer = null;

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

function selectOperator(id, name){
    chatOperadorId = Number(id || 0);
    chatLastId = 0;
    document.querySelectorAll('#operadorList .msg-card').forEach(el => el.classList.toggle('active', Number(el.dataset.operadorId||0) === chatOperadorId));
    document.getElementById('chatHeadTitle').textContent = chatOperadorId ? `Conversacion con ${name}` : 'Selecciona un operador';
    document.getElementById('chatInput').disabled = !chatOperadorId;
    document.getElementById('chatSendBtn').disabled = !chatOperadorId;
    loadChat(true);
    if(chatTimer) clearInterval(chatTimer);
    if(chatOperadorId) chatTimer = setInterval(() => loadChat(false), 3000);
}
function renderChatError(msg){
    const body = document.getElementById('chatBody');
    if(!body) return;
    body.innerHTML = `<div style="color:#fca5a5;font-size:13px;">${safe(msg || 'No se pudo cargar la conversacion.')}</div>`;
}
async function loadChat(reset){
    if(!chatOperadorId) return;
    const body = document.getElementById('chatBody');
    try{
        const fromId = reset ? 0 : chatLastId;
        const res = await fetch(`../api/pedido.php?action=operador_ayuda_chat_get&operador_id=${chatOperadorId}&desde=${fromId}`, { cache:'no-store' });
        const payload = await res.json().catch(() => []);
        if(!res.ok){
            const errMsg = payload && payload.error ? payload.error : 'No se pudo cargar la conversacion.';
            if(reset) renderChatError(errMsg);
            return;
        }
        if(!Array.isArray(payload)){
            if(reset) renderChatError('Respuesta invalida del servidor.');
            return;
        }
        const rows = payload;
        if(reset){ body.innerHTML = ''; chatLastId = 0; }
        if(!rows.length && reset){ body.innerHTML = '<div style="color:var(--text-muted);font-size:13px;">Sin mensajes en esta conversacion.</div>'; return; }
        rows.forEach(m => {
            const id = Number(m.id || 0);
            if(id <= chatLastId) return;
            chatLastId = id;
            const mine = Number(m.remitente_id || 0) === PANEL_USER_ID;
            const div = document.createElement('div');
            div.className = `msg-bubble ${mine ? 'msg-mine' : 'msg-theirs'}`;
            div.innerHTML = `<div style="font-size:11px;font-weight:700;color:${mine ? '#67e8f9' : '#c7d2fe'};">${mine ? 'Tu' : safe(m.remitente_nombre || 'Operador')}</div><div>${safe(m.mensaje || '')}</div><div class="msg-time">${safe(m.ts || '')}</div>`;
            body.appendChild(div);
        });
        body.scrollTop = body.scrollHeight;
    }catch(_){
        if(reset) renderChatError('No se pudo conectar para cargar mensajes.');
    }
}
async function sendChat(ev){
    ev.preventDefault();
    if(!chatOperadorId) return;
    const input = document.getElementById('chatInput');
    const msg = input.value.trim();
    if(!msg) return;
    try{
        const res = await fetch(`../api/pedido.php?action=operador_ayuda_chat_send&operador_id=${chatOperadorId}`, {
            method:'POST',
            headers:{ 'Content-Type':'application/json' },
            body: JSON.stringify({ mensaje: msg })
        });
        const data = await res.json().catch(() => ({}));
        if(!res.ok || !data.success){
            throw new Error((data && data.error) ? data.error : 'No se pudo enviar el mensaje.');
        }
        input.value = '';
        await loadChat(false);
        await pollPanelNotis();
    }catch(err){
        alert(err.message || 'No se pudo enviar el mensaje.');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initPanelNotis();
    document.querySelectorAll('#operadorList .msg-card').forEach(el => {
        el.addEventListener('click', () => selectOperator(el.dataset.operadorId, el.dataset.operadorNombre || 'Operador'));
    });
    document.getElementById('chatForm')?.addEventListener('submit', sendChat);
    if (chatOperadorId) {
        const el = document.querySelector(`#operadorList .msg-card[data-operador-id="${chatOperadorId}"]`);
        selectOperator(chatOperadorId, el?.dataset.operadorNombre || 'Operador');
    }
});
</script>
</body>
</html>







