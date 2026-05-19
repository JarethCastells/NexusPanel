<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireAuth();
if (!esOperador() && !esAdmin()) { header('Location: dashboard.php'); exit; }

$u = usuarioActual();
$hasFolioHex = columnExists($pdo, 'pedidos', 'folio_hex');
$folioExpr = $hasFolioHex ? "p.folio_hex" : "UPPER(HEX(p.id))";
$hasSinProductos = columnExists($pdo, 'pedidos', 'operador_sin_productos');
$hasSinProductosAt = columnExists($pdo, 'pedidos', 'sin_productos_at');
$sinProductosExpr = $hasSinProductos ? "IFNULL(p.operador_sin_productos,0)" : "0";
$sinProductosAtExpr = $hasSinProductosAt ? "p.sin_productos_at" : "NULL";
$tab = $_GET['tab'] ?? 'inicio';
if (!in_array($tab, ['inicio', 'activos', 'mensajes', 'ayuda'], true)) {
    $tab = 'inicio';
}

// Mis pedidos activos como operador
$misActivos = $pdo->prepare("
    SELECT p.*, p.ruta_punto, {$folioExpr} AS folio_hex, {$sinProductosExpr} AS operador_sin_productos, {$sinProductosAtExpr} AS sin_productos_at, u.nombre AS cli_nombre, u.telefono AS cli_tel, u.domicilio AS cli_dom, u.lat AS cli_lat, u.lng AS cli_lng
    FROM pedidos p JOIN usuarios u ON u.id=p.cliente_id
    WHERE p.operador_id=? AND p.estado IN ('aceptado','en_camino')
    ORDER BY
        p.ruta_punto ASC,
        CASE WHEN p.estado='en_camino' THEN 0 WHEN {$sinProductosExpr}=1 THEN 2 ELSE 1 END ASC,
        CASE WHEN {$sinProductosExpr}=1 THEN {$sinProductosAtExpr} ELSE p.updated_at END ASC
");
$misActivos->execute([$u['usuario_id']]);
$misActivos = $misActivos->fetchAll();
$viajeEnProceso = null;
$viajesPorIniciar = [];
foreach ($misActivos as $ma) {
    if ($ma['estado'] === 'en_camino' && $viajeEnProceso === null) {
        $viajeEnProceso = $ma;
    } elseif ($ma['estado'] === 'aceptado') {
        $viajesPorIniciar[] = $ma;
    }
}

$mensajesInicio = $pdo->prepare("
    SELECT
        c.id,
        c.pedido_id,
        c.mensaje,
        c.ts,
        {$folioExpr} AS folio_hex,
        ru.nombre AS remitente_nombre
    FROM chat c
    JOIN pedidos p ON p.id = c.pedido_id
    JOIN usuarios ru ON ru.id = c.usuario_id
    WHERE p.operador_id = ?
      AND p.estado = 'en_camino'
      AND ru.rol = 'cliente'
    ORDER BY c.id DESC
    LIMIT 80
");
$mensajesInicio->execute([(int)$u['usuario_id']]);
$mensajesInicio = array_reverse($mensajesInicio->fetchAll());

$chatMetaByPedido = [];
foreach ($misActivos as $ma) {
    $chatMetaByPedido[(int)$ma['id']] = [
        'cliente' => (string)($ma['cli_nombre'] ?? 'Cliente'),
        'estado' => (string)($ma['estado'] ?? 'aceptado'),
        'folio' => (string)($ma['folio_hex'] ?: strtoupper(dechex((int)$ma['id']))),
    ];
}
$conversacionesOperador = [];
for ($i = count($mensajesInicio) - 1; $i >= 0; $i--) {
    $m = $mensajesInicio[$i];
    $pid = (int)($m['pedido_id'] ?? 0);
    if ($pid <= 0 || isset($conversacionesOperador[$pid])) continue;
    $meta = $chatMetaByPedido[$pid] ?? ['cliente' => 'Cliente', 'estado' => 'aceptado', 'folio' => (string)($m['folio_hex'] ?: strtoupper(dechex($pid)))];
    $conversacionesOperador[$pid] = [
        'pedido_id' => $pid,
        'cliente_nombre' => $meta['cliente'],
        'estado' => $meta['estado'],
        'folio_hex' => $meta['folio'],
        'ultimo_mensaje' => (string)($m['mensaje'] ?? ''),
        'ultima_ts' => (string)($m['ts'] ?? ''),
    ];
}
$conversacionesOperador = array_values($conversacionesOperador);
$chatPedidoId = (int)($_GET['chat_pedido_id'] ?? ($conversacionesOperador[0]['pedido_id'] ?? 0));

$adminsAyuda = [];
try {
    $sqlAyuda = "
        SELECT id, nombre, rol, telefono
        FROM usuarios
        WHERE rol IN ('administrador','inventario')
        " . (columnExists($pdo, 'usuarios', 'activo') ? "AND activo = 1" : "") . "
        ORDER BY CASE WHEN rol='inventario' THEN 0 ELSE 1 END, nombre ASC
        LIMIT 80
    ";
    $adminsAyuda = $pdo->query($sqlAyuda)->fetchAll();
} catch (Throwable $e) {
    $adminsAyuda = [];
}

// Pedido seleccionado
$pedidoId = (int)($_GET['pedido_id'] ?? ($misActivos[0]['id'] ?? 0));
$pedidoActivo = null;
$itemsPedido  = [];
if ($pedidoId) {
    $st = $pdo->prepare("SELECT p.*, p.ruta_punto, {$folioExpr} AS folio_hex, u.nombre AS cli_nombre,u.telefono AS cli_tel,u.domicilio AS cli_dom,u.lat AS cli_lat,u.lng AS cli_lng FROM pedidos p JOIN usuarios u ON u.id=p.cliente_id WHERE p.id=?");
    $st->execute([$pedidoId]);
    $pedidoActivo = $st->fetch();
    if ($pedidoActivo) {
        $stI = $pdo->prepare("SELECT pi.*,pr.nombre,pr.codigo,COALESCE(pr.imagen,'') AS imagen FROM pedido_items pi JOIN productos pr ON pr.id=pi.producto_id WHERE pi.pedido_id=?");
        $stI->execute([$pedidoId]);
        $itemsPedido = $stI->fetchAll();
    }
}

$viajesMesSt = $pdo->prepare("
    SELECT
        COUNT(*) AS viajes_mes,
        SUM(CASE WHEN estado = 'entregado' THEN 1 ELSE 0 END) AS entregados_mes,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes_asignados
    FROM pedidos
    WHERE operador_id = ?
      AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
");
$viajesMesSt->execute([(int)$u['usuario_id']]);
$resumenMes = $viajesMesSt->fetch() ?: [];
$viajesMes = (int)($resumenMes['viajes_mes'] ?? 0);
$entregadosMes = (int)($resumenMes['entregados_mes'] ?? 0);
$pendientesAsignados = (int)($resumenMes['pendientes_asignados'] ?? 0);

$recentesSt = $pdo->prepare("
    SELECT p.id, {$folioExpr} AS folio_hex, p.estado, p.total, p.updated_at, p.domicilio_entrega,
           p.persona_recibe, p.estado_paquete, c.nombre AS cli_nombre, c.telefono AS cli_telefono
    FROM pedidos p
    JOIN usuarios c ON c.id = p.cliente_id
    WHERE p.operador_id = ?
    ORDER BY p.updated_at DESC
    LIMIT 150
");
$recentesSt->execute([(int)$u['usuario_id']]);
$viajesRecientes = $recentesSt->fetchAll();

$evidenciasPorPedido = [];
$allIds = array_column($viajesRecientes, 'id');

if (!empty($allIds)) {
    $inQuery = implode(',', array_fill(0, count($allIds), '?'));
    $evSt = $pdo->prepare("SELECT pedido_id, foto_url FROM pedido_evidencias WHERE pedido_id IN ($inQuery)");
    $evSt->execute($allIds);
    foreach ($evSt->fetchAll() as $ev) {
        $evidenciasPorPedido[$ev['pedido_id']][] = $ev['foto_url'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Operador</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/pedidos.css">
    <link rel="stylesheet" href="../assets/css/operador.css">
    <style>
    /* Ã¢â€¢ÂÃ¢â€¢Â LAYOUT ENTREGA ESTILO UBER Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â */
    .delivery-view {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 112px);
        background: var(--bg-primary);
        width: 100%;
        overflow: hidden;
    }
    /* Full height sin padding */
    .main-content .content-area { padding: 0 !important; }

    /* La tab de activos ocupa toda la altura */
    #tab-activos { height: calc(100vh - 112px); }

    /* Layout con lista lateral + panel principal */
    .activos-layout {
        display: flex !important;
        height: 100%;
        overflow: hidden;
    }
    .activos-lista {
        width: 220px;
        flex-shrink: 0;
        overflow-y: auto;
        border-right: 1px solid var(--border);
        background: var(--bg-card);
    }
    .activo-panel {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    /* Ã¢â€â‚¬Ã¢â€â‚¬ Mapa Ã¢â€â‚¬Ã¢â€â‚¬ */
    .delivery-map {
        height: 340px;
        flex-shrink: 0;
        position: relative;
        overflow: hidden;
    }
    .delivery-map #mapaOperador {
        width: 100%;
        height: 100%;
    }

    /* Pill estado flotante */
    .delivery-status-pill {
        position: absolute; top: 14px; left: 14px; z-index: 1000;
        display: flex; align-items: center; gap: 8px;
        background: rgba(6,10,18,0.88); backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.12);
        border-radius: 100px; padding: 7px 16px;
        font-size: 12px; font-weight: 600; color: #fff;
    }
    .pill-dot {
        width: 8px; height: 8px; border-radius: 50%;
        background: #10b981; flex-shrink: 0;
        box-shadow: 0 0 6px #10b981;
    }
    .pill-dot.blink { animation: blink 1.2s ease-in-out infinite; }

    /* Leyenda mapa */
    .map-legend {
        position: absolute; bottom: 12px; left: 50%;
        transform: translateX(-50%); z-index: 1000;
        display: flex; gap: 14px; align-items: center;
        background: rgba(6,10,18,0.82); backdrop-filter: blur(8px);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 100px; padding: 6px 18px;
        font-size: 12px; color: #fff; pointer-events: none;
        white-space: nowrap;
    }

    /* FAB chat */
    .chat-fab {
        position: absolute; bottom: 90px; right: 18px; z-index: 1000;
        width: 52px; height: 52px;
        background: linear-gradient(135deg, #10b981, #047857);
        border: none; border-radius: 50%;
        color: #fff; font-size: 20px; cursor: pointer;
        box-shadow: 0 4px 20px rgba(16,185,129,0.5);
        display: flex; align-items: center; justify-content: center;
        transition: all .2s;
    }
    .chat-fab:hover { transform: scale(1.1); box-shadow: 0 6px 28px rgba(16,185,129,0.6); }
    .fab-badge {
        position: absolute; top: -4px; right: -4px;
        background: #ef4444; color: #fff;
        font-size: 10px; font-weight: 800;
        min-width: 18px; height: 18px; border-radius: 100px;
        display: flex; align-items: center; justify-content: center; padding: 0 4px;
        animation: pop .3s cubic-bezier(0.34,1.56,0.64,1);
    }
    .fab-badge.hidden { display: none; }
    @keyframes pop { from{transform:scale(0)} to{transform:scale(1)} }
    .notif-count {
        position: absolute; top: -2px; right: -2px; min-width: 18px; height: 18px; padding: 0 5px;
        border-radius: 999px; background: #ef4444; color: #fff; font-size: 11px; font-weight: 800;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 2px 10px rgba(239,68,68,0.45);
    }
    .notif-count.hidden { display: none; }
    .op-noti-dropdown { width: 360px; max-width: calc(100vw - 24px); border: 1px solid var(--border); background: var(--bg-card); z-index: 8000 !important; }
    .topbar { position: relative; z-index: 12000 !important; }
    .topbar .dropdown-menu { z-index: 13000 !important; }
    .delivery-map,
    #mapaOperador,
    .leaflet-container,
    .leaflet-pane,
    .leaflet-control-container { z-index: 1 !important; }
    .op-noti-header { padding: 10px 12px; border-bottom: 1px solid var(--border); display:flex; align-items:center; justify-content:space-between; color: var(--text-primary); }
    .op-noti-list { max-height: 320px; overflow: auto; padding: 8px; display: grid; gap: 8px; }
    .op-noti-item { display:block; border:1px solid var(--border); border-radius:10px; background: var(--bg-tertiary); color:inherit; text-decoration:none; padding:10px; }
    .op-noti-item strong { display:block; font-size:13px; color: var(--text-primary); }
    .op-noti-item small { display:block; color:var(--text-muted); margin-top:2px; font-size:11px; }
    .op-noti-empty { color:var(--text-muted); font-size:12px; padding:8px; }
    .queue-grid { display:grid; gap:12px; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); }
    .queue-card { border:1px solid rgba(86,113,162,.34); border-radius:14px; background:linear-gradient(165deg,rgba(11,24,48,.85),rgba(7,18,36,.92)); padding:12px; }
    .queue-card-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:6px; }
    .queue-folio { font-family: var(--font-mono); font-size:12px; color:#93c5fd; }
    .queue-card-title { font-size:28px; font-weight:700; line-height:1; margin-bottom:8px; }
    .queue-card-row { display:flex; gap:8px; align-items:center; color:var(--text-muted); font-size:13px; margin-bottom:6px; }
    .btn-chip { border-radius: 999px; padding: 8px 12px; font-weight: 700; font-size: 12px; border: 1px solid transparent; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
    .btn-chip-primary { color:#dff5ff; background: linear-gradient(135deg,#0ea5e9,#2563eb); box-shadow: 0 8px 20px rgba(37,99,235,.25); }
    .btn-chip-wa { color:#d1fae5; background: linear-gradient(135deg,#16a34a,#059669); box-shadow: 0 8px 20px rgba(5,150,105,.25); }
    .dbar-btn-warning-tech {
        background: linear-gradient(135deg, rgba(245,158,11,.22), rgba(217,119,6,.3));
        border: 1px solid rgba(245,158,11,.55); color: #fde68a;
        box-shadow: 0 0 0 1px rgba(245,158,11,.2) inset, 0 10px 24px rgba(217,119,6,.2);
    }
    .dbar-btn-warning-tech:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(217,119,6,.28); }
    .label-tu,
    .label-cliente {
        background: var(--bg-card);
        border: 1px solid var(--border);
        color: var(--text-primary);
        border-radius: 999px;
        padding: 2px 8px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .02em;
    }
    .label-tu {
        border-color: rgba(52, 211, 153, .6);
        color: var(--success);
    }
    .label-cliente {
        border-color: rgba(96, 165, 250, .6);
        color: var(--accent);
    }
    .help-user-item.active { border-color: rgba(0,212,255,.55) !important; box-shadow: 0 0 0 2px rgba(0,212,255,.14) inset; background: rgba(8,34,58,.85) !important; }
    .op-msg-layout {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 12px;
        min-height: 68vh;
    }
    .op-msg-convos {
        border: 1px solid var(--border);
        border-radius: 14px;
        background: var(--bg-card);
        overflow: auto;
        padding: 8px;
    }
    .op-msg-card {
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 10px;
        margin-bottom: 8px;
        background: var(--bg-tertiary);
        cursor: pointer;
        transition: .2s ease;
    }
    .op-msg-card:hover { border-color: var(--accent); transform: translateY(-1px); }
    .op-msg-card.active { border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent-soft) inset; }
    .op-msg-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .op-msg-user { display: flex; align-items: center; gap: 8px; min-width: 0; }
    .op-msg-avatar {
        width: 34px; height: 34px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, #06b6d4, #2563eb);
        color: #eaf2ff; font-weight: 800; font-size: 13px; flex-shrink: 0;
    }
    .op-msg-name { font-size: 13px; font-weight: 700; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .op-msg-folio { font-size: 11px; color: var(--text-muted); }
    .op-msg-status { font-size: 10px; font-weight: 700; text-transform: uppercase; border-radius: 999px; padding: 2px 8px; border: 1px solid transparent; }
    .op-msg-status.s-en_camino { color: var(--success); border-color: rgba(16,185,129,.45); background: rgba(16,185,129,.14); }
    .op-msg-status.s-aceptado { color: var(--warning); border-color: rgba(245,158,11,.45); background: rgba(245,158,11,.14); }
    .op-msg-last { font-size: 12px; color: var(--text-muted); margin-top: 7px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .op-msg-time { font-size: 11px; color: var(--text-muted); margin-top: 5px; }
    .op-msg-thread {
        border: 1px solid var(--border);
        border-radius: 14px;
        background: var(--bg-card);
        display: grid;
        grid-template-rows: auto 1fr auto;
        min-height: 68vh;
        overflow: hidden;
    }
    .op-thread-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 14px; border-bottom: 1px solid var(--border); }
    .op-thread-title { font-weight: 700; color: var(--text-primary); font-size: 15px; }
    .op-thread-sub { color: var(--text-muted); font-size: 12px; margin-top: 2px; }
    .op-thread-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .op-thread-body {
        padding: 12px;
        overflow: auto;
        display: grid;
        align-content: start;
        gap: 10px;
        background: var(--bg-primary);
    }
    .op-bubble { max-width: min(78%, 560px); border-radius: 14px; padding: 9px 11px; border: 1px solid var(--border); }
    .op-bubble.mine { margin-left: auto; background: var(--accent-soft); border-color: var(--accent); }
    .op-bubble.theirs { margin-right: auto; background: var(--bg-tertiary); }
    .op-bubble .sender { font-size: 11px; font-weight: 700; color: var(--accent); margin-bottom: 4px; }
    .op-bubble.theirs .sender { color: var(--text-muted); }
    .op-bubble .text { color: var(--text-primary); font-size: 13px; line-height: 1.35; white-space: pre-wrap; }
    .op-bubble .time { color: var(--text-muted); font-size: 11px; margin-top: 4px; }
    .op-thread-foot {
        border-top: 1px solid var(--border);
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 8px;
        padding: 10px;
        background: var(--bg-card);
    }

    /* Ã¢â€â‚¬Ã¢â€â‚¬ Barra inferior Ã¢â€â‚¬Ã¢â€â‚¬ */
    .delivery-bar {
        background: #0d1422;
        border-top: 1px solid rgba(255,255,255,0.08);
        padding: 12px 16px 14px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        flex-shrink: 0;
    }

    /* Info cliente */
    .dbar-client {
        display: flex; align-items: center; gap: 10px;
    }
    .dbar-avatar {
        width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
        background: linear-gradient(135deg, #00d4ff22, #7c3aed22);
        border: 2px solid rgba(0,212,255,0.3);
        display: flex; align-items: center; justify-content: center;
        font-size: 14px; font-weight: 700; color: #00d4ff;
    }
    .dbar-info { flex: 1; min-width: 0; }
    .dbar-name { font-size: 14px; font-weight: 700; color: #f0f6ff; margin-bottom: 1px; }
    .dbar-addr { font-size: 11px; color: #64748b; display: flex; align-items: center; gap: 4px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
    .dbar-addr i { color: #00d4ff; font-size: 10px; flex-shrink: 0; }
    .dbar-total {
        font-family: 'JetBrains Mono', monospace;
        font-size: 14px; font-weight: 700; color: #10b981;
        flex-shrink: 0;
    }

    /* Pills de productos Ã¢â‚¬â€ scroll horizontal */
    .dbar-products {
        display: flex; gap: 6px;
        overflow-x: auto; padding-bottom: 2px;
    }
    .dbar-products::-webkit-scrollbar { height: 2px; }
    .dbar-products::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }
    .dbar-pill {
        padding: 3px 10px;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 100px;
        font-size: 11px; color: #94a3b8;
        white-space: nowrap; flex-shrink: 0;
    }
    .dbar-pill-product {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 4px 10px 4px 4px;
    }
    .dbar-pill-thumb,
    .dbar-pill-fallback {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .dbar-pill-thumb {
        object-fit: cover;
        border: 1px solid rgba(255,255,255,0.18);
    }
    .dbar-pill-fallback {
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0,212,255,0.12);
        border: 1px solid rgba(0,212,255,0.24);
        color: #7dd3fc;
        font-size: 11px;
    }
    .dbar-pill-text {
        color: #cbd5e1;
        font-weight: 600;
    }
    .dbar-more { color: #00d4ff; border-color: rgba(0,212,255,0.2); background: rgba(0,212,255,0.06); }
    .btn-view-order {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 10px;
        border: 1px solid rgba(0,212,255,.35);
        background: rgba(0,212,255,.12);
        color: #7dd3fc;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-view-order:hover { background: rgba(0,212,255,.2); color: #e0f2fe; }
    .order-detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
    }
    .order-detail-card {
        border: 1px solid rgba(148,163,184,.25);
        background: rgba(15,23,42,.7);
        border-radius: 12px;
        overflow: hidden;
    }
    .order-detail-thumb {
        width: 100%;
        height: 170px;
        background: #0b1220;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .order-detail-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        cursor: zoom-in;
    }
    .order-detail-fallback { color: #64748b; font-size: 28px; }
    .order-detail-body { padding: 10px 12px; }
    .order-detail-name { color: #e2e8f0; font-size: 14px; font-weight: 700; line-height: 1.25; }
    .order-detail-meta { color: #93c5fd; font-size: 12px; margin-top: 4px; }

    /* Botones de acciÃƒÂ³n */
    .dbar-actions { display: flex; flex-direction: column; gap: 10px; }
    .dbar-btn {
        width: 100%; padding: 12px;
        border: none; border-radius: 12px;
        font-family: 'Sora', sans-serif;
        font-size: 13px; font-weight: 700;
        cursor: pointer; display: flex;
        align-items: center; justify-content: center; gap: 10px;
        transition: all .2s; letter-spacing: 0.3px;
    }
    .dbar-btn:disabled { opacity: .6; cursor: not-allowed; transform: none !important; }
    .dbar-btn-primary {
        background: linear-gradient(135deg, #00d4ff, #0ea5e9);
        color: #fff;
        box-shadow: 0 4px 20px rgba(0,212,255,0.3);
    }
    .dbar-btn-primary:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,212,255,0.4); }
    .dbar-btn-success {
        background: linear-gradient(135deg, #10b981, #047857);
        color: #fff;
        box-shadow: 0 4px 20px rgba(16,185,129,0.25);
    }
    .dbar-btn-success:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(16,185,129,0.4); }

    .dbar-live {
        display: flex; align-items: center; justify-content: center; gap: 10px;
        padding: 12px; border-radius: 14px;
        background: rgba(16,185,129,0.08);
        border: 1px solid rgba(16,185,129,0.2);
        font-size: 13px; font-weight: 600; color: #10b981;
    }

    /* MODAL ENTREGA FLOTANTE */
    .entrega-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.8); z-index: 10000;
        display: flex; align-items: center; justify-content: center;
        opacity: 0; pointer-events: none; transition: opacity 0.3s;
        padding: 16px;
    }
    .entrega-overlay.active { opacity: 1; pointer-events: auto; }
    .entrega-modal {
        background: #0d1422; border: 1px solid rgba(255,255,255,0.1);
        border-radius: 20px; width: 100%; max-width: 400px;
        padding: 24px; box-shadow: 0 24px 64px rgba(0,0,0,0.7);
        transform: translateY(20px); transition: transform 0.3s;
        max-height: 90vh; overflow-y: auto;
    }
    .entrega-overlay.active .entrega-modal { transform: translateY(0); }
    .em-title { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 4px; }
    .em-subtitle { font-size: 13px; color: #94a3b8; margin-bottom: 20px; }
    .em-label { display: block; font-size: 12px; color: #94a3b8; margin-bottom: 6px; font-weight: 600; }
    .em-input { width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: #fff; font-size: 14px; margin-bottom: 16px; outline: none; }
    .em-input:focus { border-color: #10b981; }
    .em-input option { background: #0d1422; }
    .em-preview-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 16px; }
    .em-preview-box { width: 100%; aspect-ratio: 1; background: rgba(255,255,255,0.04); border: 1px dashed rgba(255,255,255,0.2); border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center; cursor: pointer; position: relative; }
    .em-preview-box img { width: 100%; height: 100%; object-fit: cover; }
    .em-preview-box i { font-size: 20px; color: rgba(255,255,255,0.3); }
    .em-actions { display: flex; gap: 12px; margin-top: 10px; }
    .em-btn { flex: 1; padding: 12px; border: none; border-radius: 12px; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.2s; }
    .em-btn-cancel { background: rgba(255,255,255,0.05); color: #fff; }
    .em-btn-cancel:hover { background: rgba(255,255,255,0.1); }
    .em-btn-submit { background: linear-gradient(135deg, #10b981, #047857); color: #fff; box-shadow: 0 4px 15px rgba(16,185,129,0.3); }
    .em-btn-submit:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16,185,129,0.4); }
    .em-btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

    /* Ã¢â€¢ÂÃ¢â€¢Â MODAL CHAT FLOTANTE Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â */
    .chat-modal {
        position: fixed; bottom: 24px; right: 24px; z-index: 9999;
        width: 360px; height: 500px;
        background: #0d1422;
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 20px; overflow: hidden;
        box-shadow: 0 24px 64px rgba(0,0,0,0.7), 0 0 0 1px rgba(16,185,129,0.1);
        display: flex; flex-direction: column;
        animation: chatIn .3s cubic-bezier(0.34,1.56,0.64,1);
    }
    .chat-modal.hidden { display: none; }
    @keyframes chatIn { from{opacity:0;transform:scale(0.85) translateY(20px)} to{opacity:1;transform:scale(1) translateY(0)} }

    .chat-modal-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 16px; flex-shrink: 0;
        background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(4,120,87,0.08));
        border-bottom: 1px solid rgba(16,185,129,0.15);
    }
    .chat-modal-info { display: flex; align-items: center; gap: 10px; }
    .cmi-avatar {
        width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
        background: linear-gradient(135deg, #10b981, #047857);
        display: flex; align-items: center; justify-content: center;
        font-size: 15px; font-weight: 800; color: #fff;
    }
    .chat-modal-name { font-size: 14px; font-weight: 700; color: #fff; }
    .chat-modal-status {
        font-size: 11px; color: #10b981;
        display: flex; align-items: center; gap: 4px;
    }
    .chat-modal-status::before {
        content: ''; width: 6px; height: 6px;
        background: #10b981; border-radius: 50%;
        display: inline-block; animation: blink 2s infinite;
    }
    .chat-modal-close {
        width: 30px; height: 30px; border-radius: 50%;
        background: rgba(255,255,255,0.08); border: none;
        color: #94a3b8; cursor: pointer; font-size: 14px;
        display: flex; align-items: center; justify-content: center;
        transition: all .2s;
    }
    .chat-modal-close:hover { background: rgba(239,68,68,0.2); color: #fca5a5; }

    /* Mensajes */
    .wc-messages {
        flex: 1; overflow-y: auto; padding: 14px 12px;
        display: flex; flex-direction: column; gap: 8px;
        background: #090e1a; scroll-behavior: smooth;
    }
    .wc-messages::-webkit-scrollbar { width: 3px; }
    .wc-messages::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 4px; }
    .wc-empty {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; gap: 12px; height: 100%;
        text-align: center; opacity: .4;
    }
    .wc-empty i { font-size: 38px; color: #10b981; }
    .wc-empty p { font-size: 12px; color: #64748b; line-height: 1.6; }
    .wc-msg { display: flex; animation: wc-in .2s ease; }
    @keyframes wc-in { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
    .wc-mine   { justify-content: flex-end; }
    .wc-theirs { justify-content: flex-start; }
    .wc-bubble {
        max-width: 78%; padding: 9px 13px 6px;
        border-radius: 16px; word-break: break-word;
    }
    .wc-mine .wc-bubble {
        background: linear-gradient(135deg, #10b981, #047857);
        color: #fff; border-radius: 16px 16px 4px 16px;
        box-shadow: 0 3px 12px rgba(16,185,129,0.25);
    }
    .wc-theirs .wc-bubble {
        background: rgba(255,255,255,0.07);
        border: 1px solid rgba(255,255,255,0.09);
        color: #f0f6ff; border-radius: 16px 16px 16px 4px;
    }
    .wc-sender { font-size: 10px; font-weight: 700; color: #00d4ff; margin-bottom: 3px; opacity: .85; }
    .wc-text   { font-size: 13.5px; line-height: 1.5; }
    .wc-time   { font-size: 10px; opacity: .55; text-align: right; margin-top: 4px; display: flex; align-items: center; justify-content: flex-end; gap: 3px; font-family: 'JetBrains Mono', monospace; }

    /* Input */
    .wc-input-bar {
        display: flex; align-items: center; gap: 8px;
        padding: 10px 12px; flex-shrink: 0;
        background: rgba(255,255,255,0.02);
        border-top: 1px solid rgba(255,255,255,0.06);
    }
    .wc-input {
        flex: 1; padding: 10px 16px;
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 22px; color: #f0f6ff;
        font-family: 'Sora', sans-serif; font-size: 13px; outline: none;
        transition: all .2s;
    }
    .wc-input:focus { border-color: #10b981; background: rgba(16,185,129,0.06); box-shadow: 0 0 0 3px rgba(16,185,129,0.07); }
    .wc-input::placeholder { color: #334155; }
    .wc-send {
        width: 40px; height: 40px; flex-shrink: 0;
        background: linear-gradient(135deg, #10b981, #047857);
        border: none; border-radius: 50%; color: #fff;
        cursor: pointer; font-size: 14px;
        display: flex; align-items: center; justify-content: center;
        transition: all .2s;
    }
    .wc-send:hover { transform: scale(1.1); box-shadow: 0 4px 16px rgba(16,185,129,0.5); }
    .wc-send:active { transform: scale(0.92); }

    @media (max-width: 640px) {
        .chat-modal { width: calc(100vw - 24px); right: 12px; bottom: 12px; height: 420px; }
        .delivery-bar { padding: 12px 14px 16px; }
    }
    /* Ocultar atribuciÃƒÂ³n Leaflet */
    .leaflet-control-attribution { display: none !important; }

    /* Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â
       RESPONSIVE FIXES Ã¢â‚¬â€ OPERADOR
       Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â */

    /* Topbar compacto en tablet */
    @media (max-width: 1024px) {
        .zona-badge { display: none; }
        .activos-lista { width: 180px; }
    }

    /* Tablet: lista mas estrecha, barra inferior compacta */
    @media (max-width: 900px) {
        #tab-activos { height: calc(100vh - 150px); }
        .activos-lista { width: 160px; }
        .dbar-client { gap: 8px; }
        .dbar-name { font-size: 13px; }
        .dbar-total { font-size: 12px; }
        .dbar-btn { padding: 10px; font-size: 12px; }
    }

    /* MÃƒÂ³vil: layout vertical completo */
    @media (max-width: 768px) {
        /* Tab activos scroll vertical */
        #tab-activos {
            height: auto !important;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Layout apilado */
        .activos-layout {
            flex-direction: column !important;
            height: auto !important;
            overflow: visible !important;
        }

        /* Lista horizontal deslizable */
        .activos-lista {
            width: 100% !important;
            height: auto !important;
            display: flex !important;
            flex-direction: row !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            border-right: none !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
            padding: 8px !important;
            gap: 8px !important;
            -webkit-overflow-scrolling: touch;
        }
        .activos-lista .pedido-item {
            min-width: 200px !important;
            flex-shrink: 0 !important;
            border-radius: 10px !important;
            border-bottom: none !important;
        }

        /* Panel full width */
        .activo-panel {
            width: 100% !important;
            overflow: visible !important;
        }

        /* delivery-view apilado */
        .delivery-view {
            height: auto !important;
            min-height: unset !important;
        }

        /* Mapa altura fija en mÃƒÂ³vil */
        .delivery-map {
            height: 300px !important;
            min-height: 300px !important;
            flex: none !important;
        }

        /* FAB chat mas accesible */
        .chat-fab {
            bottom: 12px !important;
            right: 12px !important;
            width: 48px !important;
            height: 48px !important;
            font-size: 18px !important;
        }

        /* Leyenda mas pequeÃƒÂ±a */
        .map-legend {
            font-size: 10px !important;
            padding: 4px 12px !important;
            bottom: 8px !important;
        }

        /* Barra inferior compacta */
        .delivery-bar {
            padding: 10px 12px 12px !important;
            gap: 8px !important;
        }
        .dbar-client { gap: 8px; }
        .dbar-avatar { width: 30px; height: 30px; font-size: 12px; }
        .dbar-name { font-size: 13px; }
        .dbar-addr { font-size: 10px; }
        .dbar-total { font-size: 13px; }
        .dbar-btn { padding: 11px; font-size: 13px; border-radius: 10px; }

        /* Tabs mas compactos */
        .op-tab { padding: 12px 14px; font-size: 12px; }

        /* Pendientes grid 1 columna */
        .pendientes-grid {
            grid-template-columns: 1fr !important;
        }
        .tab-content { padding: 12px !important; }
    }

    /* MÃƒÂ³vil pequeÃƒÂ±o */
    @media (max-width: 480px) {
        .delivery-map { height: 260px !important; min-height: 260px !important; }
        .dbar-products {
            display: flex !important;
            flex-wrap: wrap;
            gap: 8px;
        }
        .dbar-pill-product,
        .dbar-more {
            display: none !important; /* Ocultar solo pills, no el boton */
        }
        .btn-view-order {
            display: inline-flex !important;
            width: 100%;
            justify-content: center;
            min-height: 40px;
        }
        .op-tab span:not(.tab-badge) { display: none; }
        .op-tab { padding: 12px 16px; }
    }

    /* Ã¢â€â‚¬Ã¢â€â‚¬ MAGIA PARA ELIMINAR EL ESPACIO NEGRO (CORREGIDO) Ã¢â€â‚¬Ã¢â€â‚¬ */
    
    /* 1. Reglas SOLO para la computadora (El mapa actÃƒÂºa como esponja) */
    @media (min-width: 769px) {
        .activo-panel { display: flex !important; flex-direction: column !important; flex: 1 !important; }
        .delivery-view { display: flex !important; flex-direction: column !important; flex: 1 !important; height: 100% !important; }
        .delivery-map { flex: 1 1 auto !important; min-height: 400px !important; height: auto !important; }
        .delivery-bar { flex-shrink: 0 !important; }
    }
    
    /* 2. Reglas SOLO para el celular (El mapa exige una altura estricta o se pone negro) */
    @media (max-width: 768px) {
        .delivery-map { 
            height: 300px !important; /* Altura fija moderada para moviles */
            min-height: 300px !important; 
            flex: none !important; 
        }
        #mapaOperador { 
            height: 100% !important; 
            width: 100% !important; 
            display: block !important;
        }
    }

/* =======================================================
       Ã°Å¸â€Â¥ CHAT A PANTALLA COMPLETA EN MÃƒâ€œVIL (CORREGIDO) Ã°Å¸â€Â¥
       ======================================================= */
    @media (max-width: 768px) {
        /* SOLO aplicamos el modo gigante cuando NO estÃƒÂ¡ oculto */
        .chat-modal:not(.hidden) {
            position: fixed !important;
            top: 0 !important; 
            left: 0 !important; 
            right: 0 !important; 
            bottom: 0 !important;
            width: 100vw !important; 
            max-width: 100vw !important;
            height: 100dvh !important; 
            max-height: 100dvh !important;
            margin: 0 !important; 
            border-radius: 0 !important;
            z-index: 2147483647 !important; /* Por encima del mapa y botones */
            background: #0d1422 !important;
            border: none !important;
            display: flex !important;
            flex-direction: column !important;
        }

        /* Obligamos al navegador a esconderlo cuando le damos a la X */
        .chat-modal.hidden {
            display: none !important;
        }
        
        /* Protege contra el Notch/CÃƒÂ¡mara del iPhone */
        .chat-modal-header { 
            padding-top: max(16px, env(safe-area-inset-top)) !important; 
            border-radius: 0 !important; 
        }
        
        /* BotÃƒÂ³n de cerrar mas grande para el dedo */
        .chat-modal-close { 
            width: 44px !important; 
            height: 44px !important; 
            font-size: 20px !important; 
        }
        
        /* Protege el input contra la barra inferior de iOS/Android */
        .wc-input-bar { 
            padding-bottom: max(16px, env(safe-area-inset-bottom)) !important; 
        }
    }

    /* Hard-fix movil: evitar cortes de scroll en entregas */
    @media (max-width: 768px) {
        .main-content,
        .content-area {
            min-height: 100dvh;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch;
        }

        #tab-activos,
        .activos-layout,
        .activo-panel,
        .delivery-view {
            height: auto !important;
            min-height: 0 !important;
            overflow: visible !important;
        }

        .delivery-map {
            height: 320px !important;
            min-height: 320px !important;
        }

        .delivery-bar {
            margin-top: 0 !important;
            padding-bottom: 20px !important;
        }

        .op-tabs {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
    }

    /* Reglas finales para evitar solapes y cortes en movil */
    @media (max-width: 900px) {
        #tab-activos {
            height: auto !important;
            overflow: visible !important;
        }
        .activos-layout {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
            height: auto !important;
            min-height: 0 !important;
            overflow: visible !important;
        }
        .activos-lista {
            width: 100% !important;
            max-width: 100% !important;
            display: flex !important;
            flex-direction: row !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            border-right: none !important;
            border-bottom: 1px solid var(--border) !important;
            gap: 10px !important;
            padding: 10px !important;
            -webkit-overflow-scrolling: touch;
        }
        .activos-lista .pedido-item {
            min-width: 240px !important;
            max-width: 85vw !important;
            border-bottom: none !important;
            border-radius: 12px !important;
        }
        .activo-panel {
            width: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: visible !important;
        }
        .delivery-view {
            display: flex !important;
            flex-direction: column !important;
            height: auto !important;
            min-height: 0 !important;
            overflow: visible !important;
        }
        .delivery-map {
            width: 100% !important;
            height: 44dvh !important;
            min-height: 280px !important;
            max-height: 420px !important;
            flex: none !important;
        }
        .delivery-map #mapaOperador {
            width: 100% !important;
            height: 100% !important;
        }
        .delivery-bar {
            position: relative !important;
            z-index: 2 !important;
        }
    }

    @media (max-width: 480px) {
        .delivery-map {
            height: 300px !important;
            min-height: 300px !important;
        }
    }

    /* Override final movil: layout 100% telefono */
    @media (max-width: 768px) {
        .main-content,
        .content-area,
        .op-tabs,
        .tab-content,
        #tab-activos,
        .activos-layout,
        .activo-panel,
        .delivery-view {
            width: 100% !important;
            max-width: 100% !important;
        }

        .content-area {
            padding: 0 !important;
        }

        .activos-lista .pedido-item {
            min-width: 86vw !important;
            max-width: 86vw !important;
        }

        .delivery-map {
            height: 42dvh !important;
            min-height: 260px !important;
            max-height: 360px !important;
        }

        .map-legend {
            bottom: 10px !important;
        }

        .sidebar {
            width: 100vw !important;
            max-width: 100vw !important;
            z-index: 4000 !important;
        }

        body.sidebar-open .delivery-map,
        body.sidebar-open #mapaOperador,
        body.sidebar-open .leaflet-container,
        body.sidebar-open .leaflet-pane,
        body.sidebar-open .leaflet-control-container,
        body.sidebar-open .map-legend,
        body.sidebar-open .chat-fab {
            visibility: hidden !important;
            opacity: 0 !important;
        }
    }
    
    /* Fix responsive: Mensajes/Ayuda operador en movil */
    .op-msg-layout > *,
    .help-layout > * { min-width: 0; }

    @media (max-width: 900px) {
        .op-msg-layout {
            grid-template-columns: 1fr !important;
            min-height: auto !important;
        }
        .op-msg-convos {
            max-height: 42vh;
        }
        .op-msg-thread {
            min-height: 56vh !important;
        }
        .help-layout {
            grid-template-columns: 1fr !important;
        }
        .help-layout > div:first-child {
            max-height: 34vh;
            overflow: auto !important;
        }
    }

    @media (max-width: 768px) {
        .op-msg-layout {
            gap: 10px !important;
        }
        .op-msg-card {
            padding: 10px 9px;
        }
        .op-msg-top {
            align-items: flex-start;
        }
        .op-msg-status {
            white-space: nowrap;
        }
        .op-msg-last,
        .op-msg-name {
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        .op-msg-thread {
            min-height: 60vh !important;
        }
        .op-thread-head {
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }
        .op-thread-actions {
            width: 100%;
        }
        .op-thread-actions .btn-chip {
            width: 100%;
            justify-content: center;
        }
        .op-thread-foot,
        #helpChatForm {
            grid-template-columns: 1fr !important;
        }
        .op-thread-foot .btn-primary-custom,
        #helpChatSendBtn {
            width: 100%;
            justify-content: center;
        }
        .help-layout {
            gap: 10px !important;
        }
        .help-layout > div,
        .op-msg-convos,
        .op-msg-thread {
            border-radius: 12px;
        }
        .help-layout > div:last-child {
            min-height: 60vh !important;
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
            <span class="user-role role-operator"><i class="fa-solid fa-user-gear"></i> Operador</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="operador_pedidos.php?tab=inicio" class="nav-item <?= $tab === 'inicio' ? 'active' : '' ?>"><i class="fa-solid fa-chart-line"></i><span>Inicio</span><?= $tab === 'inicio' ? '<div class="nav-indicator"></div>' : '' ?></a>
        <a href="operador_pedidos.php?tab=activos" class="nav-item <?= $tab === 'activos' ? 'active' : '' ?>">
            <i class="fa-solid fa-truck"></i><span>Mis Entregas</span><?= $tab === 'activos' ? '<div class="nav-indicator"></div>' : '' ?>
        </a>
        <a href="operador_pedidos.php?tab=ayuda" class="nav-item <?= $tab === 'ayuda' ? 'active' : '' ?>">
            <i class="fa-solid fa-circle-info"></i><span>Ayuda</span><?= $tab === 'ayuda' ? '<div class="nav-indicator"></div>' : '' ?>
        </a>
        <div class="nav-section-label">Cuenta</div>
        <a href="../logout.php" class="nav-item nav-logout"><i class="fa-solid fa-right-from-bracket"></i><span>Cerrar sesion</span></a>
    </nav>
</aside>

<main class="main-content">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenu"><i class="fa-solid fa-bars"></i></button>
            <div class="breadcrumb-custom">
                <span>NexusPanel</span><i class="fa-solid fa-chevron-right"></i>
                <span class="active"><?= $tab === 'inicio' ? 'Inicio' : ($tab === 'mensajes' ? 'Mensajes' : ($tab === 'ayuda' ? 'Ayuda' : 'Mis Entregas')) ?></span>
            </div>
        </div>
        <div class="topbar-right">
            <button class="topbar-btn theme-toggle" onclick="toggleTheme()" title="Cambiar tema">
                <i class="fa-solid fa-moon theme-toggle-icon"></i>
            </button>
            <div class="topbar-date" id="topbarDate"></div>
            <div class="zona-badge"><i class="fa-solid fa-location-dot"></i> Radio: <?= $u['zona_radio'] ?? 50 ?> km</div>
            <div class="dropdown">
                <button class="topbar-btn" type="button" id="opNotiBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Notificaciones">
                    <i class="fa-solid fa-bell"></i>
                    <span class="notif-count hidden" id="opNotiCount">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0 op-noti-dropdown" aria-labelledby="opNotiBtn">
                    <div class="op-noti-header">
                        <strong>Notificaciones</strong>
                        <button type="button" class="btn btn-sm btn-link p-0" id="btnLimpiarNotis">Limpiar</button>
                    </div>
                    <div id="opNotiList" class="op-noti-list">
                        <div class="op-noti-empty">Sin notificaciones nuevas.</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="content-area" style="padding:0;">

        <!-- TABS -->
        <div class="op-tabs">
            <button class="op-tab <?= $tab==='inicio'?'active':'' ?>" onclick="switchTab('inicio')">
                <i class="fa-solid fa-gauge-high"></i> Inicio
            </button>
            <button class="op-tab <?= $tab==='activos'?'active':'' ?>" onclick="switchTab('activos')">
                <i class="fa-solid fa-truck"></i> Mis entregas activas
                <span class="tab-badge" id="tabBadgeActivos" style="background:rgba(16,185,129,0.2);color:var(--success);"><?= count($misActivos) ?></span>
            </button>
            <button class="op-tab <?= $tab==='mensajes'?'active':'' ?>" onclick="switchTab('mensajes')">
                <i class="fa-solid fa-envelope-open-text"></i> Mensajes
                <span class="tab-badge" id="tabBadgeMensajes" style="background:rgba(0,212,255,0.2);color:var(--primary);"><?= count($mensajesInicio) ?></span>
            </button>
        </div>

        <!-- TAB: INICIO -->
        <div id="tab-inicio" class="tab-content <?= $tab==='inicio'?'':'hidden' ?>">
            <div class="card-panel" style="margin:12px;">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">Centro operativo del operador</h3>
                        <p class="panel-subtitle">Panel de control de entregas, ritmo semanal y estado operativo.</p>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                    <div style="padding:14px;border:1px solid var(--border);border-radius:12px;background:rgba(255,255,255,.02);">
                        <div style="font-size:12px;color:var(--text-muted);">Viajes este mes</div>
                        <div style="font-size:34px;font-weight:700;color:var(--primary);line-height:1;"><?= $viajesMes ?></div>
                    </div>
                    <div style="padding:14px;border:1px solid var(--border);border-radius:12px;background:rgba(255,255,255,.02);">
                        <div style="font-size:12px;color:var(--text-muted);">Entregados este mes</div>
                        <div style="font-size:34px;font-weight:700;color:var(--success);line-height:1;"><?= $entregadosMes ?></div>
                    </div>
                    <div style="padding:14px;border:1px solid var(--border);border-radius:12px;background:rgba(255,255,255,.02);">
                        <div style="font-size:12px;color:var(--text-muted);">Pendientes por iniciar</div>
                        <div style="font-size:34px;font-weight:700;color:#f59e0b;line-height:1;"><?= count($viajesPorIniciar) ?></div>
                    </div>
                    <div style="padding:14px;border:1px solid var(--border);border-radius:12px;background:rgba(255,255,255,.02);">
                        <div style="font-size:12px;color:var(--text-muted);">Entregas activas</div>
                        <div style="font-size:34px;font-weight:700;color:#a78bfa;line-height:1;"><?= count($misActivos) ?></div>
                    </div>
                </div>

                <div style="margin-top:14px;padding:14px;border:1px solid var(--border);border-radius:12px;background:rgba(255,255,255,.01);">
                    <div style="font-size:24px;font-weight:700;margin-bottom:6px;">Viaje en proceso</div>
                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">Vista rapida del pedido activo y datos de contacto.</div>
                    <?php if ($viajeEnProceso): ?>
                        <div class="queue-card">
                            <div class="queue-card-head">
                                <span class="queue-folio">#<?= htmlspecialchars((string)($viajeEnProceso['folio_hex'] ?: strtoupper(dechex((int)$viajeEnProceso['id'])))) ?></span>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (isset($viajeEnProceso['ruta_punto']) && $viajeEnProceso['ruta_punto'] > 0): ?>
                                        <span class="badge bg-primary px-2" style="font-size: 10px;">PUNTO <?= $viajeEnProceso['ruta_punto'] ?></span>
                                    <?php endif; ?>
                                    <span class="estado-badge en_camino">En camino</span>
                                </div>
                            </div>
                            <div class="queue-card-title"><?= htmlspecialchars((string)$viajeEnProceso['cli_nombre']) ?></div>
                            <div class="queue-card-row"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars((string)($viajeEnProceso['cli_tel'] ?: 'Sin telefono')) ?></div>
                            <div class="queue-card-row"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars((string)($viajeEnProceso['domicilio_entrega'] ?: $viajeEnProceso['cli_dom'] ?: 'Sin direccion')) ?></div>
                            <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">
                                <a class="btn-chip btn-chip-primary" href="operador_pedidos.php?tab=activos&pedido_id=<?= (int)$viajeEnProceso['id'] ?>"><i class="fa-solid fa-route"></i> Ir al viaje</a>
                                <?php
                                    $telWa = preg_replace('/\D+/', '', (string)($viajeEnProceso['cli_tel'] ?? ''));
                                    if (strlen($telWa) === 10) { $telWa = '52' . $telWa; }
                                ?>
                                <a class="btn-chip btn-chip-wa" target="_blank" href="https://wa.me/<?= htmlspecialchars($telWa) ?>"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="padding:14px;border:1px dashed var(--border);border-radius:10px;color:var(--text-muted);">No hay viaje en camino en este momento.</div>
                    <?php endif; ?>
                </div>

                <div style="margin-top:14px;padding:14px;border:1px solid var(--border);border-radius:12px;background:rgba(255,255,255,.01);">
                    <div style="font-size:24px;font-weight:700;margin-bottom:6px;">Viajes por iniciar</div>
                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">Pedidos aceptados que siguen en tu cola de reparto.</div>
                    <div class="queue-grid">
                        <?php if (empty($viajesPorIniciar)): ?>
                            <div style="padding:14px;border:1px dashed var(--border);border-radius:10px;color:var(--text-muted);">No hay viajes pendientes por iniciar.</div>
                        <?php else: ?>
                            <?php foreach ($viajesPorIniciar as $vpi): ?>
                                <div class="queue-card">
                                    <div class="queue-card-head">
                                        <span class="queue-folio">#<?= htmlspecialchars((string)($vpi['folio_hex'] ?: strtoupper(dechex((int)$vpi['id'])))) ?></span>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (isset($vpi['ruta_punto']) && $vpi['ruta_punto'] > 0): ?>
                                                <span class="badge bg-primary px-2" style="font-size: 10px;">PUNTO <?= $vpi['ruta_punto'] ?></span>
                                            <?php endif; ?>
                                            <span class="estado-badge aceptado">Aceptado</span>
                                        </div>
                                    </div>
                                    <div class="queue-card-title"><?= htmlspecialchars((string)$vpi['cli_nombre']) ?></div>
                                    <div class="queue-card-row"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars((string)($vpi['domicilio_entrega'] ?: $vpi['cli_dom'] ?: 'Sin direccion')) ?></div>
                                    <div class="queue-card-row"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars((string)($vpi['cli_tel'] ?: 'Sin telefono')) ?></div>
                                    <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">
                                        <a class="btn-chip btn-chip-primary" href="operador_pedidos.php?tab=activos&pedido_id=<?= (int)$vpi['id'] ?>"><i class="fa-solid fa-eye"></i> Ver detalle</a>
                                        <?php
                                            $telWa2 = preg_replace('/\D+/', '', (string)($vpi['cli_tel'] ?? ''));
                                            if (strlen($telWa2) === 10) { $telWa2 = '52' . $telWa2; }
                                        ?>
                                        <a class="btn-chip btn-chip-wa" target="_blank" href="https://wa.me/<?= htmlspecialchars($telWa2) ?>"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- TAB: MENSAJES -->
        <div id="tab-mensajes" class="tab-content <?= $tab==='mensajes'?'':'hidden' ?>">
            <div class="card-panel" style="margin:12px;">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">Mensajes de clientes</h3>
                        <p class="panel-subtitle">Conversaciones activas entre operador y cliente.</p>
                    </div>
                </div>
                <div class="op-msg-layout">
                    <div class="op-msg-convos" id="operadorConvList">
                        <?php if (empty($conversacionesOperador)): ?>
                            <div style="padding:16px;border:1px dashed var(--border);border-radius:10px;color:var(--text-muted);font-size:13px;">
                                No hay mensajes de clientes por ahora.
                            </div>
                        <?php else: ?>
                            <?php foreach ($conversacionesOperador as $conv): ?>
                                <?php
                                    $estadoSlug = $conv['estado'] === 'en_camino' ? 'en_camino' : 'aceptado';
                                    $inicial = strtoupper(mb_substr((string)$conv['cliente_nombre'], 0, 1, 'UTF-8'));
                                    $isActiveConv = (int)$conv['pedido_id'] === $chatPedidoId;
                                ?>
                                <div class="op-msg-card <?= $isActiveConv ? 'active' : '' ?>"
                                     data-conv-pedido-id="<?= (int)$conv['pedido_id'] ?>"
                                     data-conv-cliente="<?= htmlspecialchars((string)$conv['cliente_nombre']) ?>"
                                     data-conv-folio="<?= htmlspecialchars((string)$conv['folio_hex']) ?>"
                                     data-conv-estado="<?= htmlspecialchars((string)$conv['estado']) ?>">
                                    <div class="op-msg-top">
                                        <div class="op-msg-user">
                                            <div class="op-msg-avatar"><?= htmlspecialchars($inicial) ?></div>
                                            <div style="min-width:0;">
                                                <div class="op-msg-name"><?= htmlspecialchars((string)$conv['cliente_nombre']) ?></div>
                                                <div class="op-msg-folio">#<?= htmlspecialchars((string)$conv['folio_hex']) ?></div>
                                            </div>
                                        </div>
                                        <span class="op-msg-status s-<?= htmlspecialchars($estadoSlug) ?>"><?= htmlspecialchars(str_replace('_', ' ', (string)$conv['estado'])) ?></span>
                                    </div>
                                    <div class="op-msg-last"><?= htmlspecialchars((string)$conv['ultimo_mensaje']) ?></div>
                                    <div class="op-msg-time"><?= htmlspecialchars((string)$conv['ultima_ts']) ?></div>
                                    <div style="display:flex;gap:8px;margin-top:8px;">
                                        <a class="btn-chip btn-chip-primary" href="operador_pedidos.php?tab=activos&pedido_id=<?= (int)$conv['pedido_id'] ?>">
                                            <i class="fa-solid fa-eye"></i> Ver pedido
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="op-msg-thread">
                        <div class="op-thread-head">
                            <div>
                                <div class="op-thread-title" id="msgThreadTitle">Selecciona una conversación</div>
                                <div class="op-thread-sub" id="msgThreadSub">Abre una carta para ver el chat.</div>
                            </div>
                            <div class="op-thread-actions">
                                <a class="btn-chip btn-chip-primary" id="msgVerPedidoBtn" href="#" style="display:none;">
                                    <i class="fa-solid fa-box-open"></i> Ver pedido
                                </a>
                            </div>
                        </div>
                        <div class="op-thread-body" id="msgThreadBody">
                            <div style="color:var(--text-muted);font-size:13px;">No hay conversación seleccionada.</div>
                        </div>
                        <form class="op-thread-foot" id="msgThreadForm">
                            <input type="text" id="msgThreadInput" class="modal-input" placeholder="Escribe un mensaje al cliente..." disabled>
                            <button type="submit" class="btn-primary-custom btn-table" id="msgThreadSend" disabled>
                                <i class="fa-solid fa-paper-plane"></i> Enviar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: AYUDA (SIDEBAR) -->
        <div id="tab-ayuda" class="tab-content <?= $tab==='ayuda'?'':'hidden' ?>">
            <div class="card-panel" style="margin:12px;">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">Ayuda con Admin / Manager</h3>
                        <p class="panel-subtitle">Canal directo para soporte operativo del viaje.</p>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:300px 1fr;gap:12px;" class="help-layout">
                    <div style="border:1px solid var(--border);border-radius:12px;background:rgba(255,255,255,.02);padding:8px;max-height:65vh;overflow:auto;">
                        <?php if (empty($adminsAyuda)): ?>
                            <div style="padding:12px;color:var(--text-muted);font-size:13px;">Sin usuarios de soporte disponibles.</div>
                        <?php else: ?>
                            <?php foreach ($adminsAyuda as $ay): ?>
                                <button type="button" class="help-user-item" data-ayuda-id="<?= (int)$ay['id'] ?>" data-ayuda-nombre="<?= htmlspecialchars((string)$ay['nombre']) ?>" style="width:100%;text-align:left;border:1px solid var(--border);border-radius:10px;background:rgba(9,19,38,.8);color:var(--text-light);padding:10px;margin-bottom:8px;">
                                    <div style="font-weight:700;"><?= htmlspecialchars((string)$ay['nombre']) ?></div>
                                    <div style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars((string)ucfirst($ay['rol'])) ?></div>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div style="border:1px solid var(--border);border-radius:12px;background:rgba(255,255,255,.02);display:grid;grid-template-rows:auto 1fr auto;min-height:65vh;">
                        <div style="padding:10px 12px;border-bottom:1px solid var(--border);font-weight:700;" id="helpChatHead">Selecciona un contacto de soporte</div>
                        <div id="helpChatBody" style="padding:12px;overflow:auto;display:grid;gap:10px;align-content:start;">
                            <div style="padding:12px;color:var(--text-muted);font-size:13px;">Sin conversacion seleccionada.</div>
                        </div>
                        <form id="helpChatForm" style="display:grid;grid-template-columns:1fr auto;gap:8px;padding:10px;border-top:1px solid var(--border);">
                            <input id="helpChatInput" class="modal-input" placeholder="Escribe tu mensaje..." disabled>
                            <button id="helpChatSendBtn" class="btn-primary-custom btn-table" type="submit" disabled><i class="fa-solid fa-paper-plane"></i> Enviar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: MENSAJES -->
        <div class="entrega-overlay" id="detalleRealizadoModal">
            <div class="entrega-modal">
                <div class="em-title" id="drmFolio">Folio</div>
                
                <div style="margin-bottom:16px;font-size:14px;color:var(--text-light);line-height:1.6;">
                    <strong>Cliente:</strong> <span id="drmNombre"></span><br>
                    <strong>Direccion:</strong> <span id="drmDomicilio"></span><br>
                    <strong>Recibio:</strong> <span id="drmPersona"></span><br>
                    <strong>Estado de paquete:</strong> <span id="drmEstado"></span>
                </div>

                <label class="em-label">Evidencias Fotograficas</label>
                <div class="em-preview-grid" id="drmFotos" style="margin-bottom:20px;"></div>

                <div class="em-actions">
                    <button class="em-btn em-btn-cancel" onclick="document.getElementById('detalleRealizadoModal').classList.remove('active')" style="width:100%;">Cerrar</button>
                </div>
            </div>
        </div>
        
        <!-- MODAL FOTO FULLSCREEN -->
        <div id="fotoFullscreenModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);z-index:99999;justify-content:center;align-items:center;padding:20px;">
            <button onclick="document.getElementById('fotoFullscreenModal').style.display='none'" style="position:absolute;top:20px;right:20px;background:none;border:none;color:#fff;font-size:30px;cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
            <img id="fotoFullscreenImg" src="" style="max-width:100%;max-height:100%;object-fit:contain;border-radius:8px;">
        </div>

        <!-- TAB: ACTIVOS -->
        <div id="tab-activos" class="tab-content <?= $tab==='activos'?'':'hidden' ?>">
            <?php if (empty($misActivos)): ?>
            <div style="text-align:center;padding:60px;color:var(--text-muted);">
                <i class="fa-solid fa-truck" style="font-size:50px;opacity:0.15;display:block;margin-bottom:16px;"></i>
                <p>No tienes entregas activas en este momento.</p>
                <p style="font-size:13px;margin-top:8px;">Cuando manager te asigne una entrega aparecera aqui automaticamente.</p>
            </div>
            <?php else: ?>
            <div class="activos-layout">
                <!-- Lista mis pedidos activos -->
                <div class="activos-lista">
                    <?php foreach ($misActivos as $p): ?>
                    <a href="operador_pedidos.php?tab=activos&pedido_id=<?= $p['id'] ?>" class="pedido-item <?= $p['id']==$pedidoId?'active':'' ?>">
                        <div class="pedido-item-header">
                            <span class="pedido-num">#<?= htmlspecialchars((string)($p['folio_hex'] ?: strtoupper(dechex((int)$p['id'])))) ?></span>
                            <?php if (isset($p['ruta_punto']) && $p['ruta_punto'] > 0): ?>
                                <span class="badge bg-primary px-2" style="font-size: 9px; letter-spacing: 0.5px;">PUNTO <?= $p['ruta_punto'] ?></span>
                            <?php endif; ?>
                            <span class="pedido-estado-pill" style="background:<?= $p['estado']==='en_camino'?'rgba(0,212,255,0.15)':($p['estado']==='aceptado'?'rgba(16,185,129,0.15)':'rgba(245,158,11,0.15)') ?>;color:<?= $p['estado']==='en_camino'?'var(--primary)':($p['estado']==='aceptado'?'var(--success)':'#f59e0b') ?>;border:1px solid <?= $p['estado']==='en_camino'?'rgba(0,212,255,0.3)':($p['estado']==='aceptado'?'rgba(16,185,129,0.3)':'rgba(245,158,11,0.3)') ?>">
                                <?= $p['estado']==='en_camino'?'En camino':'Aceptado' ?>
                            </span>
                        </div>
                        <div class="pedido-item-total"><?= htmlspecialchars($p['cli_nombre']) ?></div>
                        <div class="pedido-item-fecha">
                            <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars(mb_strimwidth($p['cli_dom']??'',0,35,'...')) ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- Ã¢â€â‚¬Ã¢â€â‚¬ VISTA ENTREGA ESTILO UBER Ã¢â€â‚¬Ã¢â€â‚¬ -->
                <?php if ($pedidoActivo): ?>
                <div class="delivery-view">

                    <!-- MAPA Ã¢â‚¬â€ ocupa todo el ancho, altura generosa -->
                    <div class="delivery-map">
                        <div id="mapaOperador"></div>

                        <!-- Pill de estado flotante arriba izquierda -->
                        <div class="delivery-status-pill" id="statusPill">
                            <?php if (isset($pedidoActivo['ruta_punto']) && $pedidoActivo['ruta_punto'] > 0): ?>
                                <span class="badge bg-primary me-2">PUNTO <?= $pedidoActivo['ruta_punto'] ?></span>
                            <?php endif; ?>
                            <?php if ($pedidoActivo['estado'] === 'en_camino'): ?>
                            <span class="pill-dot blink"></span> Compartiendo ubicacion
                            <?php else: ?>
                            <span class="pill-dot"></span> Pedido #<?= htmlspecialchars((string)($pedidoActivo['folio_hex'] ?: strtoupper(dechex((int)$pedidoActivo['id'])))) ?> aceptado
                            <?php endif; ?>
                        </div>

                        <!-- Leyenda mapa -->
                        <div class="map-legend">
                            <span>Cliente</span><span>Tu</span>
                        </div>

                        <!-- FAB Chat -->
                        <?php if (($pedidoActivo['estado'] ?? '') === 'en_camino'): ?>
                        <button class="chat-fab" onclick="toggleChatModal()">
                            <i class="fa-solid fa-comments"></i>
                            <span class="fab-badge hidden" id="chatBadge"></span>
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- BARRA INFERIOR: info + botones -->
                    <div class="delivery-bar">

                        <!-- Info cliente -->
                        <div class="dbar-client">
                            <div class="dbar-avatar"><?= strtoupper(substr($pedidoActivo['cli_nombre'],0,1)) ?></div>
                            <div class="dbar-info">
                                <div class="dbar-name"><?= htmlspecialchars($pedidoActivo['cli_nombre']) ?></div>
                                <div class="dbar-addr">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <?= htmlspecialchars(mb_strimwidth($pedidoActivo['domicilio_entrega']??$pedidoActivo['cli_dom']??'-', 0, 45, '...')) ?>
                                </div>
                            </div>
                            <?php
                                $telMap = preg_replace('/\D+/', '', (string)($pedidoActivo['cli_tel'] ?? ''));
                                if (strlen($telMap) === 10) { $telMap = '52' . $telMap; }
                            ?>
                            <a class="btn-chip btn-chip-wa" target="_blank" href="https://wa.me/<?= htmlspecialchars($telMap) ?>">
                                <i class="fa-brands fa-whatsapp"></i> WhatsApp
                            </a>
                        </div>

                        <!-- Productos colapsables -->
                        <div class="dbar-products">
                            <?php foreach (array_slice($itemsPedido,0,3) as $item): ?>
                            <?php $prodImg = !empty($item['imagen']) ? ('../uploads/productos/' . $item['imagen']) : ''; ?>
                            <span class="dbar-pill dbar-pill-product" role="button" onclick="abrirDetallePedidoActual()" title="Ver detalle del pedido">
                                <?php if ($prodImg !== ''): ?>
                                <img src="<?= htmlspecialchars($prodImg) ?>" class="dbar-pill-thumb" alt="<?= htmlspecialchars($item['nombre']) ?>" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <?php endif; ?>
                                <span class="dbar-pill-fallback" style="<?= $prodImg !== '' ? 'display:none;' : '' ?>"><i class="fa-solid fa-box-open"></i></span>
                                <span class="dbar-pill-text"><?= (int)$item['cantidad'] ?>x <?= htmlspecialchars(mb_strimwidth($item['nombre'],0,20,'...')) ?></span>
                            </span>
                            <?php endforeach; ?>
                            <?php if (count($itemsPedido) > 3): ?>
                            <span class="dbar-pill dbar-more" role="button" onclick="abrirDetallePedidoActual()" title="Ver todos los productos">+<?= count($itemsPedido)-3 ?> mas</span>
                            <?php endif; ?>
                            <button type="button" class="btn-view-order" onclick="abrirDetallePedidoActual()">
                                <i class="fa-solid fa-eye"></i> Ver pedido
                            </button>
                        </div>

                        <!-- Botones de acciÃƒÂ³n -->
                        <div class="dbar-actions">
                            <?php if ($pedidoActivo['estado'] === 'aceptado'): ?>
                            <div class="dbar-live" style="background:rgba(59,130,246,.10);border:1px solid rgba(59,130,246,.35);color:#93c5fd;">
                                <span class="pill-dot"></span>
                                Esperando inicio de viaje
                            </div>
                            <?php else: ?>
                            <div class="dbar-live">
                                <span class="pill-dot blink"></span>
                                Compartiendo ubicacion en tiempo real
                            </div>
                            <?php endif; ?>
                            <button class="dbar-btn dbar-btn-success" onclick="abrirModalEntrega()" id="btnEntregar" style="<?= $pedidoActivo['estado'] === 'en_camino' ? '' : 'display:none;' ?>" disabled>
                                <i class="fa-solid fa-camera"></i>
                                Entrega habilitada al estar cerca
                            </button>
                            <button class="dbar-btn" style="background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff;" onclick="solicitarCancelacionOperador(<?= (int)$pedidoActivo['id'] ?>)">
                                <i class="fa-solid fa-ban"></i>
                                Solicitar cancelacion
                            </button>
                        </div>

                        <div id="geoEntregaNotice" style="display:none;width:100%;margin-top:10px;padding:12px 14px;border-radius:12px;border:1px solid rgba(16,185,129,.45);background:rgba(16,185,129,.12);color:#a7f3d0;font-weight:700;align-items:center;justify-content:space-between;gap:10px;">
                            <span><i class="fa-solid fa-location-check"></i> Puedes entregar este pedido · <span id="geoEntregaPhone"></span></span>
                            <button type="button" class="btn-primary-custom" style="padding:8px 12px;" onclick="abrirModalEntrega()">Subir evidencias</button>
                        </div>
                        <div id="geoEntregaHint" style="width:100%;margin-top:8px;color:var(--text-muted);font-size:12px;">
                            Comparte ubicacion para habilitar finalizacion por cercania (300m).
                        </div>

                    </div>

                    <!-- MODAL CONFIRMAR ENTREGA -->
                    <div class="entrega-overlay" id="entregaOverlay">
                        <div class="entrega-modal">
                            <div class="em-title">Finalizar Entrega</div>
                            <div class="em-subtitle">No. de Folio: #<?= htmlspecialchars((string)($pedidoActivo['folio_hex'] ?: strtoupper(dechex((int)$pedidoActivo['id'])))) ?></div>

                            <label class="em-label">Evidencias Fotograficas (Min 3)</label>
                            <input type="file" id="evidenciaFotos" multiple accept="image/*" style="display:none;" onchange="renderPreviews(event)">
                            <div class="em-preview-grid" id="emPreviewGrid">
                                <div class="em-preview-box" onclick="document.getElementById('evidenciaFotos').click()"><i class="fa-solid fa-plus"></i></div>
                                <div class="em-preview-box" onclick="document.getElementById('evidenciaFotos').click()"><i class="fa-solid fa-camera"></i></div>
                                <div class="em-preview-box" onclick="document.getElementById('evidenciaFotos').click()"><i class="fa-solid fa-camera"></i></div>
                            </div>

                            <label class="em-label">Persona que lo recibe</label>
                            <input type="text" id="personaRecibe" class="em-input" placeholder="Nombre completo o 'El mismo'">

                            <label class="em-label">Estado del paquete</label>
                            <select id="estadoPaquete" class="em-input">
                                <option value="Excelente (Sin danos)">Excelente (Sin danos)</option>
                                <option value="Bueno (Ligeros detalles)">Bueno (Ligeros detalles)</option>
                                <option value="Regular (Empaque maltratado)">Regular (Empaque maltratado)</option>
                                <option value="Malo (Danos severos)">Malo (Danos severos)</option>
                            </select>

                            <label class="em-label">Comentario de entrega (Obligatorio)</label>
                            <textarea id="comentarioEntrega" class="em-input" rows="3" placeholder="Ej. Se entrego completo en puerta principal."></textarea>

                            <div class="em-actions">
                                <button class="em-btn em-btn-cancel" onclick="cerrarModalEntrega()">Cancelar</button>
                                <button class="em-btn em-btn-submit" onclick="procesarEntrega(<?= $pedidoActivo['id'] ?>)" id="btnConfirmarEntrega">Subir y Finalizar</button>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL DETALLE PEDIDO ACTUAL -->
                    <div class="entrega-overlay" id="detallePedidoActualModal">
                        <div class="entrega-modal" style="max-width:980px;">
                            <div class="em-title">Detalle del pedido #<?= htmlspecialchars((string)($pedidoActivo['folio_hex'] ?: strtoupper(dechex((int)$pedidoActivo['id'])))) ?></div>
                            <div class="em-subtitle">Cliente: <?= htmlspecialchars((string)$pedidoActivo['cli_nombre']) ?> · <?= count($itemsPedido) ?> producto(s)</div>
                            <div class="order-detail-grid" style="margin-top:12px;">
                                <?php foreach ($itemsPedido as $itd): ?>
                                <?php $imgDet = !empty($itd['imagen']) ? ('../uploads/productos/' . $itd['imagen']) : ''; ?>
                                <div class="order-detail-card">
                                    <div class="order-detail-thumb">
                                        <?php if ($imgDet !== ''): ?>
                                        <img src="<?= htmlspecialchars($imgDet) ?>" alt="<?= htmlspecialchars((string)$itd['nombre']) ?>" loading="lazy" onclick="abrirFotoFullscreen('<?= htmlspecialchars($imgDet, ENT_QUOTES) ?>')">
                                        <?php else: ?>
                                        <i class="fa-solid fa-box-open order-detail-fallback"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="order-detail-body">
                                        <div class="order-detail-name"><?= htmlspecialchars((string)$itd['nombre']) ?></div>
                                        <div class="order-detail-meta">SKU: <?= htmlspecialchars((string)($itd['codigo'] ?? '-')) ?></div>
                                        <div class="order-detail-meta">Unidades: <?= (int)$itd['cantidad'] ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="em-actions" style="margin-top:14px;">
                                <button class="em-btn em-btn-cancel" onclick="cerrarDetallePedidoActual()" style="width:100%;">Cerrar</button>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL CHAT FLOTANTE -->
                    <?php if (($pedidoActivo['estado'] ?? '') === 'en_camino'): ?>
                    <div class="chat-modal hidden" id="chatModal">
                        <div class="chat-modal-header">
                            <div class="chat-modal-info">
                                <div class="cmi-avatar"><?= strtoupper(substr($pedidoActivo['cli_nombre'],0,1)) ?></div>
                                <div>
                                    <div class="chat-modal-name"><?= htmlspecialchars($pedidoActivo['cli_nombre']) ?></div>
                                    <div class="chat-modal-status">En linea</div>
                                </div>
                            </div>
                            <button class="chat-modal-close" onclick="toggleChatModal()">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                        <div class="wc-messages" id="chatMensajes">
                            <div class="wc-empty" id="chatEmpty">
                                <i class="fa-solid fa-comments"></i>
                                <p>Sin mensajes aun.<br>Saluda a tu cliente!</p>
                            </div>
                        </div>
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
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const ACTIVE_ORDERS_INIT = <?= json_encode($misActivos, JSON_UNESCAPED_UNICODE) ?>;
const AYUDA_CONTACTS_INIT = <?= json_encode($adminsAyuda, JSON_UNESCAPED_UNICODE) ?>;
const MENSAJES_CONVS_INIT = <?= json_encode($conversacionesOperador, JSON_UNESCAPED_UNICODE) ?>;
const MENSAJES_CHAT_PEDIDO_INIT = <?= (int)$chatPedidoId ?>;
const CURRENT_TAB   = '<?= htmlspecialchars($tab, ENT_QUOTES) ?>';
const OP_USER_ID    = <?= (int)($u['usuario_id'] ?? 0) ?>;
const PEDIDO_ID     = <?= (int)$pedidoId ?>;
const PEDIDO_ESTADO = '<?= htmlspecialchars($pedidoActivo['estado'] ?? '', ENT_QUOTES) ?>';
const CLI_LAT       = <?= isset($pedidoActivo['cli_lat']) && $pedidoActivo['cli_lat'] ? (float)$pedidoActivo['cli_lat'] : 0 ?>;
const CLI_LNG       = <?= isset($pedidoActivo['cli_lng']) && $pedidoActivo['cli_lng'] ? (float)$pedidoActivo['cli_lng'] : 0 ?>;
const CLI_TEL       = '<?= htmlspecialchars((string)($pedidoActivo['cli_tel'] ?? ''), ENT_QUOTES) ?>';
const OP_ZONE_RADIUS_KM = <?= (float)($u['zona_radio'] ?? 50) ?>;
const DELIVERY_RADIUS_METERS = 300;
const LOCAL_DELIVERY_TEST_MODE = ['localhost', '127.0.0.1', '::1'].includes(String(window.location.hostname || '').toLowerCase());
let map = null, opMarker = null, cliMarker = null;
let routeLayers = [];
let stopMarkers = [];
let activeOrdersCache = Array.isArray(ACTIVE_ORDERS_INIT) ? ACTIVE_ORDERS_INIT : [];
let operatorPosition = null;
let canDeliverNow = false;
let currentPedidoEstado = PEDIDO_ESTADO;
let lastChatId = 0;
let trackingInterval = null;
let activeOrdersInterval = null;
let inicioMensajesInterval = null;
let mensajesConvCache = Array.isArray(MENSAJES_CONVS_INIT) ? MENSAJES_CONVS_INIT : [];
let mensajesPedidoIdActual = MENSAJES_CHAT_PEDIDO_INIT || 0;
let mensajesLastId = 0;
let mensajesInterval = null;
let gpsErrorShown = false;
let opNotiInterval = null;
let opNotiCache = [];
let ayudaOperadorId = 0;
let ayudaLastId = 0;
let ayudaTimer = null;

function scheduleOperatorMapResize(delay = 180) {
    if (!map) return;
    setTimeout(() => {
        try { map.invalidateSize(true); } catch (_) {}
    }, delay);
}

document.addEventListener('DOMContentLoaded', () => {
    updateClock(); setInterval(updateClock, 1000);
    const mobileMenuBtn = document.getElementById('mobileMenu');
    const side = document.getElementById('sidebar');
    mobileMenuBtn?.addEventListener('click', () => {
        side.classList.toggle('open');
        document.body.classList.toggle('sidebar-open', side.classList.contains('open'));
        scheduleOperatorMapResize(200);
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
    window.addEventListener('resize', () => scheduleOperatorMapResize(120));
    window.addEventListener('orientationchange', () => scheduleOperatorMapResize(280));
    startActiveOrdersPolling();
    startInicioMensajesPolling();
    initOperatorNotifications();
    initAyudaChat();
    initMensajesClienteOperador();
    if (PEDIDO_ID) {
        initMapaOperador();
        scheduleOperatorMapResize(260);
        if (PEDIDO_ESTADO === 'en_camino') {
            pollChat();
            setInterval(pollChat, 3000);
            iniciarGPS();
        }
        updateDeliveryControls();
    } else if (activeOrdersCache.length) {
        initMapaOperador();
    }
});

// Ã¢â€â‚¬Ã¢â€â‚¬ TABS Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// Ã¢â€â‚¬Ã¢â€â‚¬ CHAT MODAL FLOTANTE Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
function toggleChatModal() {
    const modal = document.getElementById('chatModal');
    const isHidden = modal.classList.contains('hidden');
    modal.classList.toggle('hidden');
    if (isHidden) {
        // Abrir: limpiar badge y enfocar input
        const badge = document.getElementById('chatBadge');
        if (badge) { badge.textContent = '0'; badge.classList.add('hidden'); }
        setTimeout(() => {
            document.getElementById('chatInput')?.focus();
            const msgs = document.getElementById('chatMensajes');
            if (msgs) msgs.scrollTop = msgs.scrollHeight;
        }, 50);
    }
}

function switchTab(name) {
    const next = encodeURIComponent(name);
    if (name === 'activos' && PEDIDO_ID) {
        window.location.href = `operador_pedidos.php?tab=activos&pedido_id=${PEDIDO_ID}`;
        return;
    }
    window.location.href = `operador_pedidos.php?tab=${next}`;
}

function pedidosSignature(items) {
    const list = Array.isArray(items) ? items : [];
    return list.map(x => `${x.id}:${x.estado}:${x.updated_at || ''}`).join('|');
}

function renderActivosSidebar(items) {
    const wrap = document.querySelector('.activos-lista');
    if (!wrap) return;
    const list = orderActivosSmart(items);
    if (!list.length) {
        wrap.innerHTML = '<div style="padding:14px;color:var(--text-muted);font-size:12px;">Sin entregas activas.</div>';
        return;
    }
    wrap.innerHTML = list.map(p => {
        const active = Number(p.id) === Number(PEDIDO_ID);
        const estadoTxt = p.estado === 'en_camino' ? 'En camino' : 'Aceptado';
        const bg = p.estado === 'en_camino' ? 'rgba(0,212,255,0.15)' : 'rgba(16,185,129,0.15)';
        const color = p.estado === 'en_camino' ? 'var(--primary)' : 'var(--success)';
        const border = p.estado === 'en_camino' ? 'rgba(0,212,255,0.3)' : 'rgba(16,185,129,0.3)';
        const dom = String(p.domicilio_entrega || p.cli_dom || '');
        const domShort = dom.length > 35 ? (dom.slice(0, 35) + '...') : dom;
        const folio = String(p.folio_hex || Number(p.id).toString(16).toUpperCase());
        return `
            <a href="operador_pedidos.php?tab=activos&pedido_id=${Number(p.id)}" class="pedido-item ${active ? 'active' : ''}">
                <div class="pedido-item-header">
                    <span class="pedido-num">#${folio}</span>
                    <span class="pedido-estado-pill" style="background:${bg};color:${color};border:1px solid ${border}">${estadoTxt}</span>
                </div>
                <div class="pedido-item-total">${String(p.cli_nombre || '')}</div>
                <div class="pedido-item-fecha"><i class="fa-solid fa-location-dot"></i> ${domShort}</div>
            </a>
        `;
    }).join('');
}

async function pollOperadorActivos() {
    try {
        const res = await fetch('../api/pedido.php?action=operador_activos', { cache: 'no-store' });
        if (!res.ok) return;
        const data = await res.json();
        if (!Array.isArray(data)) return;

        const oldSig = pedidosSignature(activeOrdersCache);
        const newSig = pedidosSignature(data);
        activeOrdersCache = data;
        const orderedData = orderActivosSmart(data);
        renderActivosSidebar(orderedData);

        const badge = document.getElementById('tabBadgeActivos');
        if (badge) badge.textContent = String(data.length);

        if (oldSig !== newSig) {
            // Evitar recargas bruscas por polling:
            // mantenemos la vista actual y solo refrescamos lista/mapa en vivo.
            if (map) {
                drawAutomaticRoutes();
            }
        }
    } catch (_) {}
}

function startActiveOrdersPolling() {
    pollOperadorActivos();
    if (activeOrdersInterval) clearInterval(activeOrdersInterval);
    activeOrdersInterval = setInterval(pollOperadorActivos, 7000);
}

async function pollInicioMensajes() {
    try {
        const res = await fetch('../api/pedido.php?action=operador_mensajes&limit=80', { cache: 'no-store' });
        if (!res.ok) return;
        const msgs = await res.json();
        if (!Array.isArray(msgs)) return;

        const badge = document.getElementById('tabBadgeMensajes');
        if (badge) badge.textContent = String(msgs.length);
        const latestByPedido = new Map();
        msgs.forEach((mi) => {
            const pid = Number(mi.pedido_id || 0);
            if (!pid) return;
            latestByPedido.set(pid, mi);
        });
        mensajesConvCache = mensajesConvCache.map((c) => {
            const last = latestByPedido.get(Number(c.pedido_id || 0));
            if (!last) return c;
            return {
                ...c,
                ultimo_mensaje: String(last.mensaje || c.ultimo_mensaje || ''),
                ultima_ts: String(last.ts || c.ultima_ts || ''),
                folio_hex: String(last.folio_hex || c.folio_hex || '')
            };
        });
        renderMensajesConvs();
    } catch (_) {}
}

function startInicioMensajesPolling() {
    pollInicioMensajes();
    if (inicioMensajesInterval) clearInterval(inicioMensajesInterval);
    inicioMensajesInterval = setInterval(pollInicioMensajes, 9000);
}

function escapeHtml(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
}

function renderMensajesConvs() {
    const wrap = document.getElementById('operadorConvList');
    if (!wrap) return;
    if (!mensajesConvCache.length) {
        wrap.innerHTML = '<div style="padding:16px;border:1px dashed var(--border);border-radius:10px;color:var(--text-muted);font-size:13px;">No hay mensajes de clientes por ahora.</div>';
        return;
    }
    wrap.innerHTML = mensajesConvCache.map((c) => {
        const pid = Number(c.pedido_id || 0);
        const initial = escapeHtml(String(c.cliente_nombre || 'C').trim().charAt(0).toUpperCase());
        const active = pid === Number(mensajesPedidoIdActual) ? 'active' : '';
        const estado = String(c.estado || 'aceptado');
        const estadoSlug = estado === 'en_camino' ? 'en_camino' : 'aceptado';
        const estadoLabel = estado.replace('_', ' ');
        return `
            <div class="op-msg-card ${active}" data-conv-pedido-id="${pid}" data-conv-cliente="${escapeHtml(c.cliente_nombre)}" data-conv-folio="${escapeHtml(c.folio_hex)}" data-conv-estado="${escapeHtml(estado)}">
                <div class="op-msg-top">
                    <div class="op-msg-user">
                        <div class="op-msg-avatar">${initial}</div>
                        <div style="min-width:0;">
                            <div class="op-msg-name">${escapeHtml(c.cliente_nombre)}</div>
                            <div class="op-msg-folio">#${escapeHtml(c.folio_hex)}</div>
                        </div>
                    </div>
                    <span class="op-msg-status s-${estadoSlug}">${escapeHtml(estadoLabel)}</span>
                </div>
                <div class="op-msg-last">${escapeHtml(c.ultimo_mensaje)}</div>
                <div class="op-msg-time">${escapeHtml(c.ultima_ts)}</div>
                <div style="display:flex;gap:8px;margin-top:8px;">
                    <a class="btn-chip btn-chip-primary" href="operador_pedidos.php?tab=activos&pedido_id=${pid}"><i class="fa-solid fa-eye"></i> Ver pedido</a>
                </div>
            </div>
        `;
    }).join('');
    bindMensajesConvsEvents();
}

function bindMensajesConvsEvents() {
    document.querySelectorAll('#operadorConvList .op-msg-card').forEach((el) => {
        el.addEventListener('click', (ev) => {
            if (ev.target.closest('a')) return;
            const pid = Number(el.dataset.convPedidoId || 0);
            if (!pid) return;
            mensajesPedidoIdActual = pid;
            mensajesLastId = 0;
            renderMensajesConvs();
            updateMensajesThreadHeader();
            loadMensajesThread(true);
            if (mensajesInterval) clearInterval(mensajesInterval);
            mensajesInterval = setInterval(() => loadMensajesThread(false), 3000);
        });
    });
}

function updateMensajesThreadHeader() {
    const conv = mensajesConvCache.find((c) => Number(c.pedido_id || 0) === Number(mensajesPedidoIdActual));
    const title = document.getElementById('msgThreadTitle');
    const sub = document.getElementById('msgThreadSub');
    const btn = document.getElementById('msgVerPedidoBtn');
    const input = document.getElementById('msgThreadInput');
    const send = document.getElementById('msgThreadSend');
    if (!title || !sub || !btn || !input || !send) return;
    if (!conv) {
        title.textContent = 'Selecciona una conversación';
        sub.textContent = 'Abre una carta para ver el chat.';
        btn.style.display = 'none';
        input.disabled = true;
        send.disabled = true;
        return;
    }
    title.textContent = `${conv.cliente_nombre} · #${conv.folio_hex}`;
    sub.textContent = conv.estado === 'en_camino' ? 'Pedido en camino' : 'Pedido aceptado';
    btn.href = `operador_pedidos.php?tab=activos&pedido_id=${Number(conv.pedido_id)}`;
    btn.style.display = 'inline-flex';
    input.disabled = false;
    send.disabled = false;
}

async function loadMensajesThread(reset) {
    const body = document.getElementById('msgThreadBody');
    if (!body || !mensajesPedidoIdActual) return;
    try {
        const desde = reset ? 0 : mensajesLastId;
        const res = await fetch(`../api/pedido.php?action=chat_get&pedido_id=${mensajesPedidoIdActual}&desde=${desde}`, { cache: 'no-store' });
        if (!res.ok) return;
        const rows = await res.json();
        if (!Array.isArray(rows)) return;
        if (reset) {
            body.innerHTML = '';
            mensajesLastId = 0;
        }
        if (!rows.length && reset) {
            body.innerHTML = '<div style="color:var(--text-muted);font-size:13px;">Sin mensajes en esta conversaciÃ³n.</div>';
            return;
        }
        rows.forEach((m) => {
            const id = Number(m.id || 0);
            if (id <= mensajesLastId) return;
            mensajesLastId = id;
            const mine = String(m.rol || '') !== 'cliente';
            const bubble = document.createElement('div');
            bubble.className = `op-bubble ${mine ? 'mine' : 'theirs'}`;
            bubble.innerHTML = `
                <div class="sender">${mine ? 'Tu' : escapeHtml(m.nombre || 'Cliente')}</div>
                <div class="text">${escapeHtml(m.mensaje || '')}</div>
                <div class="time">${escapeHtml(m.ts || '')}</div>
            `;
            body.appendChild(bubble);
        });
        body.scrollTop = body.scrollHeight;
    } catch (_) {}
}

async function sendMensajeThread(ev) {
    ev.preventDefault();
    const input = document.getElementById('msgThreadInput');
    if (!input || !mensajesPedidoIdActual) return;
    const msg = input.value.trim();
    if (!msg) return;
    try {
        await fetch(`../api/pedido.php?action=chat_send&pedido_id=${mensajesPedidoIdActual}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mensaje: msg })
        });
        input.value = '';
        await loadMensajesThread(false);
        await pollInicioMensajes();
    } catch (_) {}
}

function initMensajesClienteOperador() {
    renderMensajesConvs();
    updateMensajesThreadHeader();
    const form = document.getElementById('msgThreadForm');
    form?.addEventListener('submit', sendMensajeThread);
    if (mensajesPedidoIdActual) {
        loadMensajesThread(true);
        if (mensajesInterval) clearInterval(mensajesInterval);
        mensajesInterval = setInterval(() => loadMensajesThread(false), 3000);
    }
}

function opNotiSeenKey() {
    return `op_noti_seen_${OP_USER_ID}`;
}

function getSeenNotiSet() {
    try {
        const raw = localStorage.getItem(opNotiSeenKey());
        const arr = raw ? JSON.parse(raw) : [];
        return new Set(Array.isArray(arr) ? arr : []);
    } catch (_) {
        return new Set();
    }
}

function setSeenNotiSet(setObj) {
    try {
        const arr = Array.from(setObj).slice(-400);
        localStorage.setItem(opNotiSeenKey(), JSON.stringify(arr));
    } catch (_) {}
}

function renderOperatorNotifications(items) {
    const list = document.getElementById('opNotiList');
    const count = document.getElementById('opNotiCount');
    if (!list || !count) return;
    const seen = getSeenNotiSet();
    const rows = Array.isArray(items) ? items : [];
    opNotiCache = rows;
    const unseenRows = rows.filter(r => !seen.has(String(r.uid || '')));
    count.textContent = String(unseenRows.length);
    count.classList.toggle('hidden', unseenRows.length < 1);

    if (!rows.length) {
        list.innerHTML = '<div class="op-noti-empty">Sin notificaciones nuevas.</div>';
        return;
    }
    list.innerHTML = rows.map(r => {
        const unread = !seen.has(String(r.uid || ''));
        const title = String(r.title || 'Notificacion');
        const body = String(r.body || '');
        const ts = String(r.ts || '');
        const from = String(r.from || '');
        const href = String(r.goto || '#');
        return `
            <a href="${href}" class="op-noti-item" style="${unread ? 'border-color:rgba(0,212,255,.5);box-shadow:0 0 0 1px rgba(0,212,255,.18) inset;' : ''}">
                <strong>${title.replace(/&/g,'&amp;').replace(/</g,'&lt;')}</strong>
                <small>${from.replace(/&/g,'&amp;').replace(/</g,'&lt;')}</small>
                <div style="font-size:12px;color:var(--text-light);margin-top:4px;">${body.replace(/&/g,'&amp;').replace(/</g,'&lt;')}</div>
                <small>${ts.replace(/&/g,'&amp;').replace(/</g,'&lt;')}</small>
            </a>
        `;
    }).join('');
}

async function pollOperatorNotifications() {
    try {
        const res = await fetch('../api/pedido.php?action=operador_notificaciones&limit=80', { cache: 'no-store' });
        if (!res.ok) return;
        const rows = await res.json();
        if (!Array.isArray(rows)) return;
        renderOperatorNotifications(rows);
    } catch (_) {}
}

function markAllOperatorNotiSeen() {
    const seen = getSeenNotiSet();
    (opNotiCache || []).forEach(r => seen.add(String(r.uid || '')));
    setSeenNotiSet(seen);
    renderOperatorNotifications(opNotiCache);
}

function clearOperatorNotifications() {
    const seen = getSeenNotiSet();
    (opNotiCache || []).forEach(r => seen.add(String(r.uid || '')));
    setSeenNotiSet(seen);
    const badge = document.getElementById('opNotiCount');
    if (badge) {
        badge.textContent = '0';
        badge.classList.add('hidden');
    }
    renderOperatorNotifications(opNotiCache);
}

function initOperatorNotifications() {
    const btn = document.getElementById('opNotiBtn');
    const clearBtn = document.getElementById('btnLimpiarNotis');
    const dropdown = document.getElementById('opNotiBtn');
    clearBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        clearOperatorNotifications();
    });
    if (dropdown) {
        dropdown.addEventListener('show.bs.dropdown', () => {
            markAllOperatorNotiSeen();
        });
    }
    pollOperatorNotifications();
    if (opNotiInterval) clearInterval(opNotiInterval);
    opNotiInterval = setInterval(pollOperatorNotifications, 6000);
}

function initAyudaChat() {
    const users = Array.from(document.querySelectorAll('.help-user-item'));
    const head = document.getElementById('helpChatHead');
    const body = document.getElementById('helpChatBody');
    const input = document.getElementById('helpChatInput');
    const sendBtn = document.getElementById('helpChatSendBtn');
    const form = document.getElementById('helpChatForm');
    if (!users.length || !head || !body || !input || !sendBtn || !form) return;

    async function pollAyuda(reset = false) {
        if (!ayudaOperadorId) return;
        try {
            const fromId = reset ? 0 : ayudaLastId;
            const res = await fetch(`../api/pedido.php?action=operador_ayuda_chat_get&operador_id=${ayudaOperadorId}&desde=${fromId}`, { cache: 'no-store' });
            const rows = await res.json();
            if (!Array.isArray(rows)) return;
            if (reset) {
                ayudaLastId = 0;
                body.innerHTML = '';
            }
            if (!rows.length && reset) {
                body.innerHTML = '<div style="padding:10px;color:var(--text-muted);font-size:13px;">Sin mensajes en esta conversacion.</div>';
                return;
            }
            rows.forEach((m) => {
                ayudaLastId = Math.max(ayudaLastId, Number(m.id || 0));
                const mine = Number(m.remitente_id || 0) === OP_USER_ID;
                const role = mine ? 'Tu' : (String(m.remitente_rol || '') === 'inventario' ? 'Manager' : 'Admin');
                const wrap = document.createElement('div');
                wrap.style.maxWidth = '78%';
                wrap.style.marginLeft = mine ? 'auto' : '0';
                wrap.style.border = '1px solid rgba(86,113,162,.28)';
                wrap.style.borderRadius = '12px';
                wrap.style.padding = '9px 11px';
                wrap.style.background = mine ? 'rgba(0,212,255,.14)' : 'rgba(12,24,48,.78)';
                wrap.innerHTML = `<div style="font-weight:700;font-size:12px;color:${mine ? '#67e8f9' : '#c7d2fe'};">${role}</div>
                    <div style="font-size:13px;color:#e5e7eb;">${String(m.mensaje || '').replace(/&/g,'&amp;').replace(/</g,'&lt;')}</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">${String(m.ts || '')}</div>`;
                body.appendChild(wrap);
            });
            body.scrollTop = body.scrollHeight;
        } catch (_) {}
    }

    function selectAyudaUser(id, name) {
        ayudaOperadorId = Number(id || 0);
        ayudaLastId = 0;
        users.forEach((u) => u.classList.toggle('active', Number(u.dataset.ayudaId || 0) === ayudaOperadorId));
        head.textContent = ayudaOperadorId ? `Conversacion con ${name}` : 'Selecciona un contacto de soporte';
        input.disabled = !ayudaOperadorId;
        sendBtn.disabled = !ayudaOperadorId;
        if (ayudaTimer) clearInterval(ayudaTimer);
        if (ayudaOperadorId) {
            pollAyuda(true);
            ayudaTimer = setInterval(() => pollAyuda(false), 4000);
        }
    }

    users.forEach((u) => {
        u.addEventListener('click', () => selectAyudaUser(u.dataset.ayudaId, u.dataset.ayudaNombre || 'Soporte'));
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const msg = String(input.value || '').trim();
        if (!ayudaOperadorId || !msg) return;
        sendBtn.disabled = true;
        try {
            const res = await fetch(`../api/pedido.php?action=operador_ayuda_chat_send&operador_id=${ayudaOperadorId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mensaje: msg })
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) throw new Error(data.error || 'No se pudo enviar');
            input.value = '';
            await pollAyuda(false);
        } catch (err) {
            alert(err.message || 'No se pudo enviar el mensaje.');
        } finally {
            sendBtn.disabled = false;
        }
    });

    const first = users[0];
    if (first) selectAyudaUser(first.dataset.ayudaId, first.dataset.ayudaNombre || 'Soporte');
}

// Ã¢â€â‚¬Ã¢â€â‚¬ MAPA OPERADOR Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
function parseHexSequence(order) {
    const raw = String(order?.folio_hex || '').trim();
    if (raw) {
        const n = Number.parseInt(raw, 16);
        if (!Number.isNaN(n)) return n;
    }
    return Number(order?.id || 0);
}

function hasCoords(order) {
    const lat = Number(order?.cli_lat || 0);
    const lng = Number(order?.cli_lng || 0);
    return Number.isFinite(lat) && Number.isFinite(lng) && lat !== 0 && lng !== 0;
}

function isNoProductOrder(order) {
    return Number(order?.operador_sin_productos || 0) === 1;
}

function coordsOf(order) {
    return [Number(order?.cli_lat || 0), Number(order?.cli_lng || 0)];
}

function isFarOrder(order) {
    if (!operatorPosition || !hasCoords(order)) return false;
    const zoneMeters = Math.max(1, Number(OP_ZONE_RADIUS_KM || 50)) * 1000;
    const d = haversineMeters(
        Number(operatorPosition.lat),
        Number(operatorPosition.lng),
        Number(order.cli_lat || 0),
        Number(order.cli_lng || 0)
    );
    return Number.isFinite(d) && d > zoneMeters;
}

function nearestChain(items, startPoint, preferredId = 0) {
    const withCoords = [];
    const withoutCoords = [];
    (Array.isArray(items) ? items : []).forEach((it) => {
        if (hasCoords(it)) withCoords.push(it);
        else withoutCoords.push(it);
    });
    const ordered = [];
    let currentPoint = Array.isArray(startPoint) ? startPoint.slice() : null;

    const pref = Number(preferredId || 0);
    if (pref > 0) {
        const idx = withCoords.findIndex((x) => Number(x.id || 0) === pref);
        if (idx >= 0) {
            const picked = withCoords.splice(idx, 1)[0];
            ordered.push(picked);
            currentPoint = coordsOf(picked);
        }
    }

    while (withCoords.length) {
        let idxBest = 0;
        if (currentPoint) {
            let best = Infinity;
            for (let i = 0; i < withCoords.length; i++) {
                const d = haversineMeters(
                    Number(currentPoint[0]),
                    Number(currentPoint[1]),
                    Number(withCoords[i].cli_lat || 0),
                    Number(withCoords[i].cli_lng || 0)
                );
                if (Number.isFinite(d) && d < best) {
                    best = d;
                    idxBest = i;
                }
            }
        }
        const next = withCoords.splice(idxBest, 1)[0];
        ordered.push(next);
        currentPoint = coordsOf(next);
    }

    return [...ordered, ...withoutCoords];
}

function orderActivosSmart(orders) {
    const raw = Array.isArray(orders) ? orders.slice() : [];
    if (!raw.length) return [];

    const selectedId = Number(PEDIDO_ID || 0);
    let selected = null;
    if (selectedId > 0) {
        const idx = raw.findIndex((x) => Number(x.id || 0) === selectedId);
        if (idx >= 0) selected = raw.splice(idx, 1)[0];
    }

    const normal = [];
    const far = [];
    const noStock = [];
    raw.forEach((o) => {
        if (isNoProductOrder(o)) {
            noStock.push(o);
        } else if (isFarOrder(o)) {
            far.push(o);
        } else {
            normal.push(o);
        }
    });

    const start = operatorPosition ? [Number(operatorPosition.lat), Number(operatorPosition.lng)] : null;
    let out = [];
    out = out.concat(nearestChain(normal, start));
    const lastOut = out.length ? out[out.length - 1] : null;
    const fromFar = lastOut && hasCoords(lastOut) ? coordsOf(lastOut) : start;
    out = out.concat(nearestChain(far, fromFar));
    const lastFar = out.length ? out[out.length - 1] : null;
    const fromNoStock = lastFar && hasCoords(lastFar) ? coordsOf(lastFar) : start;
    out = out.concat(nearestChain(noStock, fromNoStock));

    if (selected) {
        out = [selected, ...out];
    }
    return out;
}

function clearRouteLayers() {
    routeLayers.forEach(l => { try { map.removeLayer(l); } catch (_) {} });
    routeLayers = [];
    stopMarkers.forEach(m => { try { map.removeLayer(m); } catch (_) {} });
    stopMarkers = [];
    if (cliMarker) {
        try { map.removeLayer(cliMarker); } catch (_) {}
        cliMarker = null;
    }
}

async function drawRouteSegment(from, to, color = '#00d4ff') {
    const fallbackLine = L.polyline([from, to], { color, weight: 3, opacity: 0.55, dashArray: '6 6' }).addTo(map);
    routeLayers.push(fallbackLine);
    try {
        const url = `https://router.project-osrm.org/route/v1/driving/${from[1]},${from[0]};${to[1]},${to[0]}?overview=full&geometries=geojson`;
        const res = await fetch(url);
        if (!res.ok) return;
        const data = await res.json();
        const coords = data?.routes?.[0]?.geometry?.coordinates;
        if (!Array.isArray(coords) || !coords.length) return;
        map.removeLayer(fallbackLine);
        routeLayers = routeLayers.filter(x => x !== fallbackLine);
        const latlngs = coords.map(c => [c[1], c[0]]);
        const line = L.polyline(latlngs, { color, weight: 4, opacity: 0.9 }).addTo(map);
        routeLayers.push(line);
    } catch (_) {}
}

async function drawAutomaticRoutes() {
    if (!map) return;
    clearRouteLayers();
    const stops = orderActivosSmart(activeOrdersCache).filter(hasCoords);
    if (!stops.length) return;

    stops.forEach((p, idx) => {
        const n = idx + 1;
        const icon = L.divIcon({
            html: `<div style="width:30px;height:30px;border-radius:50%;background:#7c3aed;border:2px solid #fff;box-shadow:0 0 10px rgba(124,58,237,.5);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:12px;">${n}</div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15],
            className: ''
        });
        const mk = L.marker([Number(p.cli_lat), Number(p.cli_lng)], { icon })
            .addTo(map)
            .bindPopup(`<strong>Entrega ${n}</strong><br>Pedido #${p.folio_hex || Number(p.id).toString(16).toUpperCase()}<br>${p.cli_nombre || ''}`);
        stopMarkers.push(mk);
    });

    if (PEDIDO_ID && CLI_LAT && CLI_LNG) {
        const homeIcon = L.divIcon({html:`<div style="width:32px;height:32px;background:#7c3aed;border-radius:50%;border:3px solid #fff;box-shadow:0 0 10px rgba(124,58,237,0.5);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;"><i class="fa fa-house"></i></div>`,iconSize:[32,32],iconAnchor:[16,16],className:''});
        cliMarker = L.marker([CLI_LAT,CLI_LNG],{icon:homeIcon}).addTo(map).bindPopup('<strong>Cliente</strong>');
        cliMarker.bindTooltip('Cliente', { permanent: true, direction: 'top', className: 'label-cliente' });
    }

    const points = [];
    if (operatorPosition) points.push([operatorPosition.lat, operatorPosition.lng]);
    stops.forEach(s => points.push([Number(s.cli_lat), Number(s.cli_lng)]));
    if (points.length >= 2) {
        for (let i = 0; i < points.length - 1; i++) {
            await drawRouteSegment(points[i], points[i + 1], '#00d4ff');
        }
    }

    const fitTargets = [
        ...stops.map(s => [Number(s.cli_lat), Number(s.cli_lng)]),
        ...(operatorPosition ? [[operatorPosition.lat, operatorPosition.lng]] : [])
    ];
    if (fitTargets.length) {
        map.fitBounds(fitTargets, { padding: [34, 34] });
    }
    scheduleOperatorMapResize(120);
}

function initMapaOperador() {
    const el = document.getElementById('mapaOperador'); if(!el)return;
    map = L.map('mapaOperador');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OSM',maxZoom:19}).addTo(map);

    const stops = orderActivosSmart(activeOrdersCache).filter(hasCoords);
    if (stops.length) {
        map.setView([Number(stops[0].cli_lat), Number(stops[0].cli_lng)], 13);
    } else if (CLI_LAT && CLI_LNG) {
        map.setView([CLI_LAT,CLI_LNG],14);
    } else {
        map.setView([19.4326,-99.1332],12);
    }

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition((pos) => {
            operatorPosition = { lat: pos.coords.latitude, lng: pos.coords.longitude };
            const icon = L.divIcon({html:`<div style="width:36px;height:36px;background:linear-gradient(135deg,#10b981,#059669);border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 4px 15px rgba(16,185,129,0.4);"></div>`,iconSize:[36,36],iconAnchor:[18,36],className:''});
            opMarker = L.marker([operatorPosition.lat, operatorPosition.lng], { icon }).addTo(map).bindPopup('<strong>Tu</strong>');
            opMarker.bindTooltip('Tu', { permanent: true, direction: 'top', className: 'label-tu' });
            updateDeliveryControls();
            drawAutomaticRoutes();
        }, () => {
            drawAutomaticRoutes();
        }, { enableHighAccuracy: true, timeout: 9000, maximumAge: 15000 });
    } else {
        drawAutomaticRoutes();
    }

    scheduleOperatorMapResize(200);
}

// Ã¢â€â‚¬Ã¢â€â‚¬ GPS OPERADOR Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
function haversineMeters(lat1, lon1, lat2, lon2) {
    if (![lat1, lon1, lat2, lon2].every(Number.isFinite)) return null;
    const R = 6371000;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2 +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLon / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(a));
}

function updateDeliveryControls() {
    const btn = document.getElementById('btnEntregar');
    const notice = document.getElementById('geoEntregaNotice');
    const hint = document.getElementById('geoEntregaHint');
    const phone = document.getElementById('geoEntregaPhone');
    if (!btn) return;
    if (phone) phone.textContent = CLI_TEL ? `Tel: ${CLI_TEL}` : 'Cliente sin telefono';

    const inRoute = currentPedidoEstado === 'en_camino';
    if (!inRoute) {
        btn.style.display = 'none';
        btn.disabled = true;
        canDeliverNow = false;
        if (notice) notice.style.display = 'none';
        if (hint) hint.textContent = 'Esperando inicio de viaje para habilitar la entrega.';
        return;
    }

    btn.style.display = '';
    const distanceMeters = haversineMeters(
        Number(operatorPosition?.lat),
        Number(operatorPosition?.lng),
        Number(CLI_LAT || 0),
        Number(CLI_LNG || 0)
    );
    canDeliverNow = LOCAL_DELIVERY_TEST_MODE || (Number.isFinite(distanceMeters) && distanceMeters <= DELIVERY_RADIUS_METERS);

    if (canDeliverNow) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-camera"></i> Adjuntar evidencias y entregar';
        if (notice) notice.style.display = 'flex';
        if (hint) hint.textContent = LOCAL_DELIVERY_TEST_MODE
            ? 'Modo prueba local activo: entrega habilitada sin restriccion de distancia.'
            : `Estas a ${Math.round(distanceMeters)}m del cliente.`;
    } else {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-lock"></i> Entrega habilitada al estar cerca';
        if (notice) notice.style.display = 'none';
        if (hint) {
            hint.textContent = Number.isFinite(distanceMeters)
                ? `Te faltan ${Math.max(0, Math.round(distanceMeters - DELIVERY_RADIUS_METERS))}m para habilitar entrega.`
                : 'Comparte ubicacion para habilitar finalizacion por cercania (300m).';
        }
    }
}

function iniciarGPS() {
    if (!navigator.geolocation) {
        if (!gpsErrorShown) {
            gpsErrorShown = true;
            alert('Este dispositivo no soporta geolocalizacion. No se puede compartir ubicacion en tiempo real.');
        }
        return;
    }

    const enviarPosicion = () => {
        navigator.geolocation.getCurrentPosition(pos => {
            const lat = pos.coords.latitude, lng = pos.coords.longitude;
            operatorPosition = { lat, lng };
            fetch(`../api/pedido.php?action=update_tracking&pedido_id=${PEDIDO_ID}`, {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({lat,lng})
            });
            // Actualizar propio marker en mapa
            if (map) {
                const icon = L.divIcon({html:`<div style="width:36px;height:36px;background:linear-gradient(135deg,#10b981,#059669);border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 4px 15px rgba(16,185,129,0.4);"></div>`,iconSize:[36,36],iconAnchor:[18,36],className:''});
                if (!opMarker) {
                    opMarker = L.marker([lat,lng],{icon}).addTo(map).bindPopup('<strong>Tu</strong>');
                    opMarker.bindTooltip('Tu', { permanent: true, direction: 'top', className: 'label-tu' });
                } else opMarker.setLatLng([lat,lng]);
                updateDeliveryControls();
                drawAutomaticRoutes();
                scheduleOperatorMapResize(80);
            }
        }, err => {
            if (gpsErrorShown) return;
            gpsErrorShown = true;
            let msg = 'No se pudo obtener la ubicacion del repartidor.';
            if (err.code === 1) msg = 'Permiso de ubicacion denegado. Activalo para compartir en tiempo real.';
            if (err.code === 2) msg = 'Ubicacion no disponible. Intenta en una zona con mejor senal.';
            if (err.code === 3) msg = 'Tiempo de espera agotado al leer GPS.';
            alert(msg);
        }, {enableHighAccuracy:true, timeout:10000, maximumAge:0});
    };

    enviarPosicion();
    trackingInterval = setInterval(() => {
        enviarPosicion();
    }, 4000);
}

async function iniciarViaje(id) {
    alert('El operador no puede iniciar viajes. Manager/Admin lo hace de forma operativa.');
}

async function marcarSinProductos(id) {
    if (!confirm('Se movera este pedido al final de la cola. Continuar?')) return;
    const btn = event.currentTarget;
    if (!btn) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Actualizando...';
    try {
        const res = await fetch(`../api/pedido.php?action=operador_no_productos&pedido_id=${id}`, { method: 'POST' });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) {
            throw new Error(data.error || ('HTTP ' + res.status));
        }
        await pollOperadorActivos();
        alert('Pedido enviado al final de tu cola.');
        // Sin redireccion forzada para una experiencia mas fluida.
        drawAutomaticRoutes();
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-ban"></i> Solicitar cancelacion';
        alert('Error: ' + (e.message || 'No se pudo actualizar la cola.'));
    }
}

async function solicitarCancelacionOperador(id) {
    const seguro = window.confirm('¿Seguro que quieres solicitar la cancelacion de este viaje?');
    if (!seguro) return;
    const motivo = window.prompt('Motivo de cancelacion (ej: cliente no estaba, direccion incorrecta, etc.):');
    if (!motivo || !motivo.trim()) {
        alert('Debes escribir un motivo.');
        return;
    }
    try {
        const res = await fetch(`../api/pedido.php?action=solicitar_cancelacion&pedido_id=${id}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ motivo: motivo.trim() })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) throw new Error(data.error || 'No se pudo enviar la solicitud.');
        alert('Solicitud enviada a Admin/Manager para revision.');
    } catch (e) {
        alert(e.message || 'No se pudo enviar la solicitud.');
    }
}

let selectedFotosFiles = [];

function abrirModalEntrega() {
    if (!canDeliverNow) {
        alert('Debes estar a menos de 300 metros del cliente para habilitar la entrega.');
        return;
    }
    document.getElementById('entregaOverlay').classList.add('active');
}

function cerrarModalEntrega() {
    document.getElementById('entregaOverlay').classList.remove('active');
}

function abrirDetallePedidoActual() {
    const modal = document.getElementById('detallePedidoActualModal');
    if (modal) modal.classList.add('active');
}

function cerrarDetallePedidoActual() {
    const modal = document.getElementById('detallePedidoActualModal');
    if (modal) modal.classList.remove('active');
}

function renderPreviews(event) {
    const files = Array.from(event.target.files);
    if (!files.length) return;
    
    selectedFotosFiles = selectedFotosFiles.concat(files);
    
    const grid = document.getElementById('emPreviewGrid');
    grid.innerHTML = '';
    
    selectedFotosFiles.forEach(file => {
        const url = URL.createObjectURL(file);
        grid.innerHTML += `<div class="em-preview-box"><img src="${url}"></div>`;
    });
    
    grid.innerHTML += `<div class="em-preview-box" onclick="document.getElementById('evidenciaFotos').click()"><i class="fa-solid fa-plus"></i></div>`;
    
    // Reset file input so same files can be selected again if needed
    event.target.value = '';
}

async function procesarEntrega(id) {
    if (selectedFotosFiles.length < 3) {
        alert('Debes adjuntar minimo 3 fotos de evidencia para poder finalizar el viaje.');
        return;
    }
    
    const personaRecibe = document.getElementById('personaRecibe').value.trim();
    if (!personaRecibe) {
        alert('Debes ingresar la persona que recibe el paquete.');
        return;
    }
    const estadoPaquete = document.getElementById('estadoPaquete').value;
    const comentarioEntrega = document.getElementById('comentarioEntrega').value.trim();
    if (!comentarioEntrega) {
        alert('Debes agregar un comentario de entrega.');
        return;
    }
    
    if (!confirm('Confirmas que el pedido fue entregado, las fotos cargaron correctamente y los datos son correctos?')) {
        return;
    }
    
    clearInterval(trackingInterval);
    const btn = document.getElementById('btnConfirmarEntrega');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Subiendo...';
    
    try {
        const formData = new FormData();
        for (let i = 0; i < selectedFotosFiles.length; i++) {
            formData.append('evidencias[]', selectedFotosFiles[i]);
        }
        formData.append('persona_recibe', personaRecibe);
        formData.append('estado_paquete', estadoPaquete);
        formData.append('comentario_entrega', comentarioEntrega);
        
        const res = await fetch(`../api/pedido.php?action=entregar&pedido_id=${id}`, {
            method: 'POST',
            body: formData
        });
        
        let data;
        try { data = await res.json(); } catch(e) {}
        
        if (!res.ok) {
            throw new Error(data && data.error ? data.error : 'HTTP ' + res.status);
        }
        
        if (data.success) {
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Listo!';
            btn.style.background = 'var(--success)';
            
            if (data.telefono) {
                let t = data.telefono.replace(/\D/g, '');
                if (t.length === 10) t = '52' + t; // Default to Mexico
                const waUrl = `https://wa.me/${t}?text=${encodeURIComponent(data.mensaje_wa)}`;
                window.open(waUrl, '_blank');
            }
            
            await new Promise(r => setTimeout(r, 800));
            try {
                const nextRes = await fetch('../api/pedido.php?action=operador_activos', { cache: 'no-store' });
                if (nextRes.ok) {
                    const nextData = await nextRes.json();
                    if (Array.isArray(nextData)) {
                        activeOrdersCache = nextData;
                        renderActivosSidebar(orderActivosSmart(nextData));
                    }
                }
            } catch (_) {}
            cerrarModalEntrega();
            drawAutomaticRoutes();
            alert('Entrega registrada. La lista se actualizo sin recargar la pagina.');
        } else {
            throw new Error(data.error || 'No se pudo marcar como entregado');
        }
    } catch(e) {
        btn.disabled = false;
        btn.innerHTML = 'Subir y Finalizar';
        alert('Error: ' + e.message);
    }
}

// Ã¢â€â‚¬Ã¢â€â‚¬ CHAT Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
async function pollChat() {
    if (!PEDIDO_ID || PEDIDO_ESTADO !== 'en_camino' || !document.getElementById('chatMensajes')) return;
    try {
        const res  = await fetch(`../api/pedido.php?action=chat_get&pedido_id=${PEDIDO_ID}&desde=${lastChatId}`);
        if (!res.ok) return;
        const msgs = await res.json();
        if (!Array.isArray(msgs)) return;
        if (!msgs.length) return;
        const cont = document.getElementById('chatMensajes');
        // Remove empty state on first message
        const emptyState = cont.querySelector('.chat-empty-state');
        if (emptyState && msgs.length) emptyState.remove();
        // Remove empty state on first message
        if (msgs.length) {
            const empty = document.getElementById('chatEmpty');
            if (empty) empty.remove();
        }

        msgs.forEach(m => {
            lastChatId = Math.max(lastChatId, m.id);
            const isMine = m.rol === 'operador' || m.rol === 'administrador';
            const div = document.createElement('div');
            div.className = `wc-msg ${isMine ? 'wc-mine' : 'wc-theirs'}`;
            const time = new Date(m.ts).toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
            div.innerHTML = `
                <div class="wc-bubble">
                    ${!isMine ? `<div class="wc-sender">${m.nombre}</div>` : ''}
                    <div class="wc-text">${m.mensaje.replace(/&/g,'&amp;').replace(/</g,'&lt;')}</div>
                    <div class="wc-time">${time} ${isMine ? '<i class="fa-solid fa-check-double" style="font-size:10px;opacity:0.7"></i>' : ''}</div>
                </div>
            `;
            cont.appendChild(div);
        });
        cont.scrollTop = cont.scrollHeight;

        // Badge si el modal esta cerrado, solo contar mensajes del cliente
        const modal = document.getElementById('chatModal');
        const nuevasDeCliente = msgs.filter(m => m.rol !== 'operador' && m.rol !== 'administrador').length;
        if (modal?.classList.contains('hidden') && nuevasDeCliente > 0) {
            const badge = document.getElementById('chatBadge');
            if (badge) {
                badge.textContent = (parseInt(badge.textContent) || 0) + nuevasDeCliente;
                badge.classList.remove('hidden');
            }
        }
    } catch(e) {}
}

async function enviarMensaje() {
    if (PEDIDO_ESTADO !== 'en_camino') return;
    const input = document.getElementById('chatInput');
    const msg = input.value.trim(); if (!msg) return;
    input.value = '';
    await fetch(`../api/pedido.php?action=chat_send&pedido_id=${PEDIDO_ID}`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({mensaje:msg})
    });
    pollChat();
}

function updateClock() {
    const el = document.getElementById('topbarDate'); if(!el)return;
    const now = new Date();
    el.textContent = now.toLocaleDateString('es-MX',{weekday:'short',day:'2-digit',month:'short'})+' - '+now.toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit',hour12:true});
}

// â”€â”€ VIAJES REALIZADOS / HISTORIAL â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
let historialLimit = 4;

function renderHistorial() {
    const query = document.getElementById('searchInputRealizados')?.value.toLowerCase().trim() || '';
    const items = document.querySelectorAll('.inicio-realizado-item');
    let visibleCount = 0;
    let matchCount = 0;

    items.forEach(item => {
        const folio = item.getAttribute('data-folio') || '';
        const nombre = item.getAttribute('data-nombre') || '';
        const matches = folio.includes(query) || nombre.includes(query);

        if (matches) {
            matchCount++;
            // Show all if searching, or respect limit if not searching
            if (query !== '' || visibleCount < historialLimit) {
                item.style.display = 'flex';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        } else {
            item.style.display = 'none';
        }
    });

    const btnMas = document.getElementById('btnCargarMasRealizados');
    const btnMenos = document.getElementById('btnVerMenosRealizados');

    if (query !== '') {
        if (btnMas) btnMas.style.display = 'none';
        if (btnMenos) btnMenos.style.display = 'none';
    } else {
        if (btnMas) btnMas.style.display = (matchCount > historialLimit) ? 'block' : 'none';
        if (btnMenos) btnMenos.style.display = (historialLimit > 4) ? 'block' : 'none';
    }
}

function filtrarRealizados() {
    renderHistorial();
}

function cargarMasRealizados() {
    historialLimit += 4;
    renderHistorial();
}

function cargarMenosRealizados() {
    historialLimit = 4;
    renderHistorial();
}

function verDetalleRealizado(btn) {
    document.getElementById('drmFolio').textContent = 'Folio #' + btn.getAttribute('data-folio');
    document.getElementById('drmNombre').textContent = btn.getAttribute('data-nombre');
    document.getElementById('drmDomicilio').textContent = btn.getAttribute('data-domicilio');
    document.getElementById('drmPersona').textContent = btn.getAttribute('data-persona');
    document.getElementById('drmEstado').textContent = btn.getAttribute('data-estado');
    
    const fotosContainer = document.getElementById('drmFotos');
    fotosContainer.innerHTML = '';
    
    try {
        const fotos = JSON.parse(btn.getAttribute('data-fotos'));
        if (Array.isArray(fotos) && fotos.length > 0) {
            fotos.forEach(url => {
                // Remove trailing slash if necessary
                const imgUrl = url.startsWith('/') ? '..' + url : url;
                fotosContainer.innerHTML += `
                    <div class="em-preview-box" onclick="abrirFotoFullscreen('${imgUrl}')" style="cursor:zoom-in;">
                        <img src="${imgUrl}" style="width:100%;height:100%;object-fit:cover;border-radius:6px;">
                    </div>
                `;
            });
        } else {
            fotosContainer.innerHTML = '<div style="grid-column:1/-1;color:var(--text-muted);font-size:13px;text-align:center;padding:10px;">No hay evidencias fotograficas registradas.</div>';
        }
    } catch (e) {
        fotosContainer.innerHTML = '<div style="grid-column:1/-1;color:#ef4444;font-size:13px;">Error al cargar imagenes.</div>';
    }
    
    document.getElementById('detalleRealizadoModal').classList.add('active');
}

function abrirFotoFullscreen(url) {
    document.getElementById('fotoFullscreenImg').src = url;
    document.getElementById('fotoFullscreenModal').style.display = 'flex';
}
</script>
</body>
</html>











