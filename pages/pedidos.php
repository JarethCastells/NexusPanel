<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireGestion();

$usuario = usuarioActual();
$esAdmin = esAdmin();
$esInventario = esInventario();
$nombreRol = nombreRolActual();
$inicioHref = $esInventario ? 'inventario.php?vista=inicio' : 'dashboard.php';
$inicioPendHref = $inicioHref . (strpos($inicioHref, '?') !== false ? '&' : '?') . 'focus_pending=1';
$msg = '';
$err = '';
$pedidoAsignadoReciente = 0;
$pedidoAbrirAsignacion = (int)($_GET['assign'] ?? 0);
$focusPending = isset($_GET['focus_pending']) ? 1 : 0;

function distanciaPedidoKm(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?float {
    if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
        return null;
    }
    $r = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $r * 2 * asin(sqrt($a));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pedidoId = (int)($_POST['pedido_id'] ?? 0);

    try {
        if ($pedidoId <= 0) {
            throw new RuntimeException('Pedido invalido.');
        }

        if ($action === 'asignar_operador') {
            $operadorId = (int)($_POST['operador_id'] ?? 0);
            $allowOutOfRange = (int)($_POST['allow_out_of_range'] ?? 0) === 1;
            if ($operadorId <= 0) {
                throw new RuntimeException('Operador invalido.');
            }

            $pdo->beginTransaction();
            $stPedido = $pdo->prepare("
                SELECT p.id, p.cliente_id, p.estado, p.lat_entrega, p.lng_entrega, c.lat AS cliente_lat, c.lng AS cliente_lng
                FROM pedidos p
                LEFT JOIN usuarios c ON c.id = p.cliente_id
                WHERE p.id = ?
                FOR UPDATE
            ");
            $stPedido->execute([$pedidoId]);
            $pedido = $stPedido->fetch();
            if (!$pedido) {
                throw new RuntimeException('Pedido no encontrado.');
            }
            if (($pedido['estado'] ?? '') === 'cancelado') {
                throw new RuntimeException('No puedes asignar un pedido cancelado.');
            }

            $stOp = $pdo->prepare("
                SELECT id, nombre, lat, lng, activo
                FROM usuarios
                WHERE id = ? AND rol = 'operador'
                LIMIT 1
            ");
            $stOp->execute([$operadorId]);
            $op = $stOp->fetch();
            if (!$op || (int)($op['activo'] ?? 0) !== 1) {
                throw new RuntimeException('El operador seleccionado no esta disponible.');
            }

            $stCarga = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE operador_id = ? AND estado IN ('pendiente','aceptado','en_camino')");
            $stCarga->execute([$operadorId]);
            $cargaActiva = (int)$stCarga->fetchColumn();
            if ($cargaActiva >= 5) {
                throw new RuntimeException('Operador no disponible: ya tiene 5 pedidos activos.');
            }

            $dist = distanciaPedidoKm(
                ($pedido['lat_entrega'] !== null ? (float)$pedido['lat_entrega'] : ($pedido['cliente_lat'] !== null ? (float)$pedido['cliente_lat'] : null)),
                ($pedido['lng_entrega'] !== null ? (float)$pedido['lng_entrega'] : ($pedido['cliente_lng'] !== null ? (float)$pedido['cliente_lng'] : null)),
                isset($op['lat']) ? (float)$op['lat'] : null,
                isset($op['lng']) ? (float)$op['lng'] : null
            );
            if ($dist !== null && $dist > 6.0 && !$allowOutOfRange) {
                throw new RuntimeException('El operador esta fuera del rango de 6 km. Activa la asignacion fuera de rango para continuar.');
            }

            $pdo->prepare("
                UPDATE pedidos
                SET operador_id = ?, estado = CASE WHEN estado='pendiente' THEN 'aceptado' ELSE estado END, updated_at = NOW()
                WHERE id = ?
            ")->execute([$operadorId, $pedidoId]);

            $pdo->prepare("INSERT INTO chat (pedido_id,usuario_id,mensaje) VALUES (?,?,?)")
                ->execute([$pedidoId, $operadorId, 'Pedido asignado por coordinacion. Confirmo recepcion.']);
            $notaDist = $dist !== null ? (' (distancia: ' . number_format($dist, 2) . ' km)') : '';
            if ($dist !== null && $dist > 6.0 && $allowOutOfRange) {
                $notaDist .= ' [fuera de rango autorizado]';
            }
            registrarHistorialPedido($pdo, $pedidoId, 'aceptado', (int)$usuario['usuario_id'], 'Asignado manualmente a operador: ' . ($op['nombre'] ?? '') . $notaDist);
            registrarNotificacionesEstandarPedido(
                $pdo,
                $pedidoId,
                (int)$pedido['cliente_id'],
                'pedido_asignado_manual',
                'Tu pedido fue asignado a operador y esta en proceso.'
            );
            registrarAuditoria(
                $pdo,
                (int)$usuario['usuario_id'],
                (string)($usuario['rol'] ?? ''),
                'pedidos',
                'asignar_operador_manual',
                'pedido',
                $pedidoId,
                'Asignado a operador #' . $operadorId
            );
            $pdo->commit();

            $pedidoAsignadoReciente = $pedidoId;
            $msg = 'Operador asignado correctamente.';
        }

        if ($action === 'cancelar_pedido') {
            $motivoCancelacion = trim((string)($_POST['motivo_cancelacion'] ?? 'Cancelado por coordinacion'));
            $pdo->beginTransaction();
            $stPedido = $pdo->prepare("SELECT id, cliente_id, estado FROM pedidos WHERE id = ? FOR UPDATE");
            $stPedido->execute([$pedidoId]);
            $pedido = $stPedido->fetch();
            if (!$pedido) {
                throw new RuntimeException('Pedido no encontrado.');
            }
            if (($pedido['estado'] ?? '') === 'cancelado') {
                throw new RuntimeException('El pedido ya esta cancelado.');
            }

            $pdo->prepare("UPDATE pedidos SET estado='cancelado', updated_at=NOW() WHERE id = ?")->execute([$pedidoId]);
            registrarHistorialPedido($pdo, $pedidoId, 'cancelado', (int)$usuario['usuario_id'], $motivoCancelacion);
            registrarNotificacionesEstandarPedido(
                $pdo,
                $pedidoId,
                (int)$pedido['cliente_id'],
                'pedido_cancelado',
                'Tu pedido fue cancelado. Contacta a soporte para mas informacion.'
            );
            registrarAuditoria($pdo, (int)$usuario['usuario_id'], (string)($usuario['rol'] ?? ''), 'pedidos', 'cancelar', 'pedido', $pedidoId, $motivoCancelacion);
            $pdo->commit();

            $msg = 'Pedido cancelado correctamente.';
        }

        if ($action === 'resolver_cancelacion_solicitada') {
            $solicitudId = (int)($_POST['solicitud_id'] ?? 0);
            $decision = trim((string)($_POST['decision'] ?? ''));
            $nota = trim((string)($_POST['nota'] ?? ''));
            if ($solicitudId <= 0 || !in_array($decision, ['aprobar', 'rechazar'], true)) {
                throw new RuntimeException('Solicitud invalida.');
            }
            if (!tableExists($pdo, 'pedido_cancelaciones')) {
                throw new RuntimeException('No existe la tabla de solicitudes de cancelacion.');
            }
            $pdo->beginTransaction();
            $stSol = $pdo->prepare("SELECT * FROM pedido_cancelaciones WHERE id=? FOR UPDATE");
            $stSol->execute([$solicitudId]);
            $sol = $stSol->fetch();
            if (!$sol) {
                throw new RuntimeException('Solicitud no encontrada.');
            }
            if (($sol['estado_solicitud'] ?? '') !== 'pendiente') {
                throw new RuntimeException('La solicitud ya fue procesada.');
            }
            $estadoSol = $decision === 'aprobar' ? 'aprobada' : 'rechazada';
            $pdo->prepare("UPDATE pedido_cancelaciones SET estado_solicitud=?, revisado_por=?, nota_revision=?, updated_at=NOW() WHERE id=?")
                ->execute([$estadoSol, (int)$usuario['usuario_id'], mb_substr($nota, 0, 400), $solicitudId]);

            if ($decision === 'aprobar') {
                $stPed = $pdo->prepare("SELECT id, cliente_id, estado FROM pedidos WHERE id=? FOR UPDATE");
                $stPed->execute([(int)$sol['pedido_id']]);
                $ped = $stPed->fetch();
                if (!$ped) throw new RuntimeException('Pedido no encontrado.');
                if (($ped['estado'] ?? '') !== 'cancelado') {
                    $pdo->prepare("UPDATE pedidos SET estado='cancelado', updated_at=NOW() WHERE id=?")->execute([(int)$sol['pedido_id']]);
                    registrarHistorialPedido($pdo, (int)$sol['pedido_id'], 'cancelado', (int)$usuario['usuario_id'], 'Cancelado por solicitud (' . ($sol['solicitado_por'] ?? 'n/a') . ')');
                    registrarNotificacionesEstandarPedido(
                        $pdo,
                        (int)$sol['pedido_id'],
                        (int)$ped['cliente_id'],
                        'pedido_cancelado',
                        'Tu pedido fue cancelado tras revision de coordinacion.'
                    );
                }
            } else {
                registrarHistorialPedido($pdo, (int)$sol['pedido_id'], 'cancelacion_rechazada', (int)$usuario['usuario_id'], $nota !== '' ? $nota : 'Solicitud de cancelacion rechazada.');
            }
            $pdo->commit();
            $msg = $decision === 'aprobar' ? 'Solicitud aprobada y pedido cancelado.' : 'Solicitud de cancelacion rechazada.';
        }

        if ($action === 'programar') {
            $fechaProgramada = trim((string)($_POST['fecha_programada'] ?? ''));
            $fechaProgramada = $fechaProgramada !== '' ? str_replace('T', ' ', $fechaProgramada) . ':00' : null;
            $transporte = trim((string)($_POST['transporte_linea'] ?? ''));
            $pl = trim((string)($_POST['pl_documento'] ?? ''));
            $nota = trim((string)($_POST['nota'] ?? ''));

            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT cliente_id FROM pedidos WHERE id=? FOR UPDATE");
            $st->execute([$pedidoId]);
            $ped = $st->fetch();
            if (!$ped) throw new RuntimeException('Pedido no encontrado.');

            $pdo->prepare("
                UPDATE pedidos
                SET fecha_programada = ?, transporte_linea = ?, pl_documento = ?, area_flujo = 'logistica', updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $fechaProgramada,
                $transporte !== '' ? mb_substr($transporte, 0, 120) : null,
                $pl !== '' ? mb_substr($pl, 0, 140) : null,
                $pedidoId
            ]);
            registrarHistorialPedido($pdo, $pedidoId, 'programado', (int)$usuario['usuario_id'], $nota !== '' ? $nota : 'Pedido programado');
            registrarNotificacionesEstandarPedido($pdo, $pedidoId, (int)$ped['cliente_id'], 'entrega_programada', 'Tu pedido fue programado para entrega.');
            registrarAuditoria($pdo, (int)$usuario['usuario_id'], (string)($usuario['rol'] ?? ''), 'logistica', 'programar_entrega', 'pedido', $pedidoId, 'Programacion desde modulo de pedidos');
            $pdo->commit();
            $msg = 'Pedido programado correctamente.';
        }

        if ($action === 'flujo') {
            $flujo = $_POST['area_flujo'] ?? 'ventas';
            if (!in_array($flujo, ['ventas', 'embarque', 'logistica', 'entrega'], true)) {
                throw new RuntimeException('Area de flujo invalida.');
            }
            $pdo->prepare("UPDATE pedidos SET area_flujo = ?, updated_at = NOW() WHERE id = ?")->execute([$flujo, $pedidoId]);
            registrarHistorialPedido($pdo, $pedidoId, 'flujo_' . $flujo, (int)$usuario['usuario_id'], 'Cambio de flujo operativo');
            registrarAuditoria($pdo, (int)$usuario['usuario_id'], (string)($usuario['rol'] ?? ''), 'pedidos', 'cambiar_flujo', 'pedido', $pedidoId, 'Flujo -> ' . $flujo);
            $msg = 'Flujo actualizado.';
        }

        if ($action === 'estado') {
            $estado = $_POST['estado'] ?? 'pendiente';
            if (!in_array($estado, ['pendiente', 'aceptado', 'en_camino', 'entregado', 'cancelado'], true)) {
                throw new RuntimeException('Estado invalido.');
            }
            $pdo->prepare("UPDATE pedidos SET estado = ?, updated_at = NOW() WHERE id = ?")->execute([$estado, $pedidoId]);
            registrarHistorialPedido($pdo, $pedidoId, $estado, (int)$usuario['usuario_id'], 'Cambio de estado desde panel admin');
            registrarAuditoria($pdo, (int)$usuario['usuario_id'], (string)($usuario['rol'] ?? ''), 'pedidos', 'cambiar_estado', 'pedido', $pedidoId, 'Estado -> ' . $estado);
            $msg = 'Estado actualizado.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = $e->getMessage();
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$estado = trim((string)($_GET['estado'] ?? ''));
$clienteId = (int)($_GET['cliente_id'] ?? 0);
$fechaDesde = trim((string)($_GET['fecha_desde'] ?? ''));
$fechaHasta = trim((string)($_GET['fecha_hasta'] ?? ''));
$histPedidoId = (int)($_GET['historial'] ?? 0);

$where = ["1=1"];
$params = [];

if ($q !== '') {
    $where[] = "(p.id = ? OR p.folio_hex LIKE ? OR c.nombre LIKE ? OR c.email LIKE ?)";
    $params[] = ctype_digit($q) ? (int)$q : -1;
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($estado !== '') {
    $where[] = "p.estado = ?";
    $params[] = $estado;
}
if ($clienteId > 0) {
    $where[] = "p.cliente_id = ?";
    $params[] = $clienteId;
}
if ($fechaDesde !== '') {
    $where[] = "p.created_at >= ?";
    $params[] = $fechaDesde . ' 00:00:00';
}
if ($fechaHasta !== '') {
    $where[] = "p.created_at <= ?";
    $params[] = $fechaHasta . ' 23:59:59';
}

$sql = "
    SELECT
        p.*,
        c.nombre AS cliente_nombre,
        c.email AS cliente_email,
        c.lat AS cliente_lat,
        c.lng AS cliente_lng,
        o.nombre AS operador_nombre,
        COALESCE(ev.evidencias_count, 0) AS evidencias_count,
        COUNT(pi.id) AS total_items,
        GROUP_CONCAT(CONCAT(pi.cantidad, 'x ', pr.nombre) ORDER BY pr.nombre SEPARATOR ' | ') AS resumen_productos
    FROM pedidos p
    JOIN usuarios c ON c.id = p.cliente_id
    LEFT JOIN usuarios o ON o.id = p.operador_id
    LEFT JOIN (
        SELECT pedido_id, COUNT(*) AS evidencias_count
        FROM pedido_evidencias
        GROUP BY pedido_id
    ) ev ON ev.pedido_id = p.id
    LEFT JOIN pedido_items pi ON pi.pedido_id = p.id
    LEFT JOIN productos pr ON pr.id = pi.producto_id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY p.id
    ORDER BY
        CASE
            WHEN p.estado = 'en_camino' THEN 0
            WHEN p.estado = 'aceptado' THEN 1
            WHEN p.estado = 'pendiente' THEN 2
            WHEN p.estado = 'entregado' THEN 3
            WHEN p.estado = 'cancelado' THEN 4
            ELSE 5
        END ASC,
        p.updated_at DESC,
        p.created_at DESC
    LIMIT 250
";
$st = $pdo->prepare($sql);
$st->execute($params);
$pedidos = $st->fetchAll();

$cancelacionesPendientes = [];
if (tableExists($pdo, 'pedido_cancelaciones')) {
    $stCan = $pdo->query("
        SELECT
            pc.id,
            pc.pedido_id,
            pc.solicitado_por,
            pc.motivo,
            pc.created_at,
            c.nombre AS cliente_nombre,
            o.nombre AS operador_nombre
        FROM pedido_cancelaciones pc
        JOIN pedidos p ON p.id = pc.pedido_id
        LEFT JOIN usuarios c ON c.id = p.cliente_id
        LEFT JOIN usuarios o ON o.id = p.operador_id
        WHERE pc.estado_solicitud = 'pendiente'
        ORDER BY pc.id DESC
        LIMIT 30
    ");
    $cancelacionesPendientes = $stCan->fetchAll();
}

$calificacionMap = [];
if (tableExists($pdo, 'pedido_calificaciones') && !empty($pedidos)) {
    $ids = array_map(static fn($x) => (int)$x['id'], $pedidos);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stCalMap = $pdo->prepare("SELECT pedido_id, estrellas, comentario FROM pedido_calificaciones WHERE pedido_id IN ($placeholders)");
    $stCalMap->execute($ids);
    foreach ($stCalMap->fetchAll() as $rowCal) {
        $calificacionMap[(int)$rowCal['pedido_id']] = $rowCal;
    }
}

$clientes = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol='cliente' AND activo=1 ORDER BY nombre ASC")->fetchAll();
$transportes = $pdo->query("SELECT nombre FROM transporte_lineas WHERE activo=1 ORDER BY nombre ASC")->fetchAll();
$operadoresDisponibles = $pdo->query("
    SELECT
        u.id,
        u.nombre,
        u.telefono,
        u.lat,
        u.lng,
        u.activo,
        COALESCE(SUM(CASE WHEN p.estado IN ('pendiente','aceptado','en_camino') THEN 1 ELSE 0 END), 0) AS pedidos_activos
    FROM usuarios u
    LEFT JOIN pedidos p ON p.operador_id = u.id
    WHERE u.rol='operador' AND u.activo=1
    GROUP BY u.id, u.nombre, u.telefono, u.lat, u.lng, u.activo
    ORDER BY u.nombre ASC
")->fetchAll();

$historial = [];
$detallePedido = null;
$detallePedidoItems = [];
$detalleEvidencias = [];
if ($histPedidoId > 0) {
    $stDetalle = $pdo->prepare("
        SELECT
            p.id,
            p.folio_hex,
            p.total,
            p.created_at,
            p.persona_recibe,
            p.estado_paquete,
            p.comentario_entrega,
            c.nombre AS cliente_nombre,
            c.email AS cliente_email,
            o.nombre AS operador_nombre
        FROM pedidos p
        JOIN usuarios c ON c.id = p.cliente_id
        LEFT JOIN usuarios o ON o.id = p.operador_id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stDetalle->execute([$histPedidoId]);
    $detallePedido = $stDetalle->fetch();

    $stDetalleItems = $pdo->prepare("
        SELECT
            pi.id,
            pi.producto_id,
            pi.cantidad,
            pi.precio_unit,
            COALESCE(pr.nombre, CONCAT('Producto #', pi.producto_id)) AS nombre,
            COALESCE(pr.imagen, '') AS imagen,
            COALESCE(pr.unidad_medida, 'piezas') AS unidad_medida
        FROM pedido_items pi
        LEFT JOIN productos pr ON pr.id = pi.producto_id
        WHERE pi.pedido_id = ?
        ORDER BY pi.id ASC
    ");
    $stDetalleItems->execute([$histPedidoId]);
    $detallePedidoItems = $stDetalleItems->fetchAll();

    if (tableExists($pdo, 'pedido_evidencias')) {
        $stEvid = $pdo->prepare("
            SELECT foto_url, created_at
            FROM pedido_evidencias
            WHERE pedido_id = ?
            ORDER BY id ASC
        ");
        $stEvid->execute([$histPedidoId]);
        $detalleEvidencias = $stEvid->fetchAll();
    }

    $stHist = $pdo->prepare("
        SELECT h.*, u.nombre AS usuario_nombre
        FROM pedido_historial_estados h
        LEFT JOIN usuarios u ON u.id = h.usuario_id
        WHERE h.pedido_id = ?
        ORDER BY h.id DESC
        LIMIT 100
    ");
    $stHist->execute([$histPedidoId]);
    $historial = $stHist->fetchAll();
}

$asignablesPorPedido = [];
foreach ($pedidos as $pp) {
    $pid = (int)$pp['id'];
    $cliLat = isset($pp['lat_entrega']) && $pp['lat_entrega'] !== null ? (float)$pp['lat_entrega'] : null;
    $cliLng = isset($pp['lng_entrega']) && $pp['lng_entrega'] !== null ? (float)$pp['lng_entrega'] : null;
    if ($cliLat === null || $cliLng === null) {
        $cliLat = isset($pp['cliente_lat']) && $pp['cliente_lat'] !== null ? (float)$pp['cliente_lat'] : null;
        $cliLng = isset($pp['cliente_lng']) && $pp['cliente_lng'] !== null ? (float)$pp['cliente_lng'] : null;
    }
    $tmp = [];
    foreach ($operadoresDisponibles as $op) {
        $dist = distanciaPedidoKm(
            $cliLat,
            $cliLng,
            isset($op['lat']) && $op['lat'] !== null ? (float)$op['lat'] : null,
            isset($op['lng']) && $op['lng'] !== null ? (float)$op['lng'] : null
        );
        $withinRange = $dist !== null && $dist <= 6.0;
        $activos = (int)($op['pedidos_activos'] ?? 0);
        $disponible = $activos < 5;
        $tmp[] = [
            'id' => (int)$op['id'],
            'nombre' => (string)$op['nombre'],
            'telefono' => (string)($op['telefono'] ?? ''),
            'distancia_km' => $dist !== null ? round($dist, 2) : null,
            'within_range' => $withinRange,
            'pedidos_activos' => $activos,
            'disponible' => $disponible
        ];
    }
    usort($tmp, static function($a, $b) {
        if (($a['disponible'] ? 1 : 0) !== ($b['disponible'] ? 1 : 0)) {
            return ($a['disponible'] ? -1 : 1);
        }
        if (($a['within_range'] ? 1 : 0) !== ($b['within_range'] ? 1 : 0)) {
            return ($a['within_range'] ? -1 : 1);
        }
        $ad = $a['distancia_km'] ?? 999999;
        $bd = $b['distancia_km'] ?? 999999;
        return $ad <=> $bd;
    });
    $asignablesPorPedido[$pid] = $tmp;
}

$pedidosById = [];
foreach ($pedidos as $pp) {
    $pedidosById[(int)$pp['id']] = $pp;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Pedidos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .toolbar-grid { display:grid; gap:10px; grid-template-columns: minmax(220px,2fr) repeat(3,minmax(150px,1fr)) auto; align-items:center; }
        .toolbar-grid .modal-input, .toolbar-grid .modal-select { height:42px; width:100%; background:rgba(8,14,30,.72); border:1px solid rgba(86,113,162,.35); color:#dbeafe; border-radius:10px; padding:0 12px; }
        .toolbar-grid .modal-input::placeholder { color:#8da7cc; }
        .toolbar-grid input[type="date"] { position: relative; z-index: 3; pointer-events: auto; color-scheme: dark; }
        .table-wrapper { overflow-x:auto; }
        .data-table { min-width: 980px; }
        .status-badge { padding:4px 10px; border-radius:999px; font-size:12px; border:1px solid var(--border); }
        .badge-pendiente { color:#f59e0b; border-color:rgba(245,158,11,.35); background:rgba(245,158,11,.12); }
        .badge-aceptado, .badge-entregado { color:#10b981; border-color:rgba(16,185,129,.35); background:rgba(16,185,129,.12); }
        .badge-en_camino { color:#00d4ff; border-color:rgba(0,212,255,.35); background:rgba(0,212,255,.12); }
        .badge-cancelado { color:#ef4444; border-color:rgba(239,68,68,.35); background:rgba(239,68,68,.12); }
        .mini-form { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
        .mini-form select.modal-select {
            min-width: 132px;
            height:34px;
            padding:0 10px;
            border-radius:9px;
            background:rgba(8,14,30,.72);
            color:#dbeafe;
            border:1px solid rgba(86,113,162,.35);
            font-size:12px;
        }
        .btn-table { height:36px; padding:0 12px; border-radius:10px; font-size:12px; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; font-weight:600; }
        .btn-primary-custom { background: linear-gradient(135deg,#1d9bff,#2b7bff) !important; border:1px solid rgba(66,153,255,.55) !important; color:#eaf4ff !important; box-shadow: 0 10px 24px rgba(29,155,255,.28) !important; }
        .btn-secondary-custom { background: linear-gradient(135deg,#0f3d78,#1453a3) !important; border:1px solid rgba(80,143,231,.55) !important; color:#eaf4ff !important; }
        .btn-secondary-custom:hover,.btn-primary-custom:hover{ transform:translateY(-1px); }
        .op-status-ok { color:#10b981; border:1px solid rgba(16,185,129,.38); background:rgba(16,185,129,.12); border-radius:999px; padding:2px 8px; font-size:11px; font-weight:700; }
        .op-status-busy { color:#f59e0b; border:1px solid rgba(245,158,11,.38); background:rgba(245,158,11,.12); border-radius:999px; padding:2px 8px; font-size:11px; font-weight:700; }
        .btn-assign { min-width: 152px; justify-content:center; }
        .pedido-detalle-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:14px; }
        .pedido-detalle-item { display:flex; gap:12px; align-items:center; padding:14px; border:1px solid var(--border); border-radius:14px; background:rgba(255,255,255,0.02); }
        .pedido-detalle-thumb { width:58px; height:58px; border-radius:12px; overflow:hidden; background:rgba(255,255,255,0.04); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .pedido-detalle-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
        .pedido-detalle-thumb i { color:var(--primary); font-size:18px; }
        .pedido-detalle-meta { min-width:0; flex:1; }
        .pedido-detalle-nombre { color:var(--text-light); font-weight:700; line-height:1.3; margin-bottom:4px; }
        .pedido-detalle-sub { color:var(--text-muted); font-size:12px; }
        .pedido-detalle-total { color:var(--primary); font-family:var(--font-mono); font-weight:700; font-size:13px; white-space:nowrap; }
        .entrega-meta-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:10px; margin-top:12px; }
        .entrega-meta-item { border:1px solid var(--border); border-radius:12px; padding:10px 12px; background:rgba(255,255,255,0.02); }
        .entrega-meta-item strong { display:block; color:var(--text-light); font-size:12px; margin-bottom:4px; }
        .entrega-meta-item span { color:var(--text-muted); font-size:13px; }
        .evidencias-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:12px; margin-top:14px; }
        .evidencia-card { border:1px solid var(--border); border-radius:12px; overflow:hidden; background:rgba(255,255,255,0.02); }
        .evidencia-card img { width:100%; height:130px; object-fit:cover; display:block; background:#0a1123; }
        .evidencia-card small { display:block; padding:8px 10px; color:var(--text-muted); font-size:11px; }
        .operador-asignado { background: rgba(0,212,255,0.14); border: 1px solid rgba(0,212,255,0.35); color: #9cecff; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; display:inline-flex; gap:6px; align-items:center; }
        .pending-card { border:1px solid rgba(245,158,11,.25); background:rgba(245,158,11,.06); border-radius:14px; padding:14px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
        .op-item { border:1px solid var(--border); border-radius:12px; padding:10px 12px; display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:8px; background:rgba(255,255,255,.02); flex-wrap:wrap; }
        .op-item.selected { border-color:rgba(0,212,255,.45); background:rgba(0,212,255,.08); }
        .op-meta small { color:var(--text-muted); display:block; }
        .op-dist { font-family:var(--font-mono); color:#9cecff; font-size:12px; }
        .op-out { color:#f59e0b; font-size:11px; font-weight:700; border:1px solid rgba(245,158,11,.4); padding:2px 8px; border-radius:999px; }
        .op-list-empty { color:var(--text-muted); font-size:13px; padding:10px; border:1px dashed var(--border); border-radius:10px; }
        .op-help { color:var(--text-muted); font-size:12px; margin-bottom:10px; }
        .modal-dark .modal-content {
            background: #0b1426;
            color: #dbeafe;
            border: 1px solid rgba(86, 113, 162, 0.4);
        }
        .modal-dark .modal-header,
        .modal-dark .modal-footer { border-color: rgba(86, 113, 162, 0.35); }
        .modal-dark .modal-title,
        .modal-dark .modal-body,
        .modal-dark .modal-footer { color: #dbeafe; }
        .modal-dark .btn-close { filter: invert(1) opacity(0.8); }
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
        @media (max-width: 1280px) { .toolbar-grid { grid-template-columns: repeat(3,minmax(150px,1fr)); } }
        @media (max-width: 900px) { .toolbar-grid { grid-template-columns: 1fr 1fr; } .data-table{min-width:920px;} }
        @media (max-width: 640px) { .toolbar-grid { grid-template-columns: 1fr; } }
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
            <span class="user-role role-admin"><i class="fa-solid fa-boxes-stacked"></i> <?= htmlspecialchars($nombreRol) ?></span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="<?= htmlspecialchars($inicioHref) ?>" class="nav-item"><i class="fa-solid fa-gauge-high"></i><span>Inicio</span></a>
        <?php if ($esAdmin): ?>
        <a href="usuarios.php" class="nav-item"><i class="fa-solid fa-users"></i><span>Usuarios</span></a>
        <?php endif; ?>
        <a href="productos.php" class="nav-item"><i class="fa-solid fa-pills"></i><span>Productos e inventario</span></a>
        <a href="pedidos.php" class="nav-item active"><i class="fa-solid fa-clipboard-check"></i><span>Pedidos</span><div class="nav-indicator"></div></a>
        <?php if ($esAdmin): ?><?php endif; ?>
        <a href="mensajes.php" class="nav-item"><i class="fa-solid fa-comments"></i><span>Mensajes</span></a>
        <?php if ($esAdmin): ?>
        <div class="nav-section-label">Operaciones</div>
        <a href="mapa.php" class="nav-item"><i class="fa-solid fa-map-location-dot"></i><span>Mapa de Usuarios</span></a>
        <?php endif; ?>
        <div class="nav-section-label">Cuenta</div>
        <a href="../logout.php" class="nav-item nav-logout"><i class="fa-solid fa-right-from-bracket"></i><span>Cerrar sesion</span></a>
    </nav>
</aside>

<main class="main-content" id="mainContent">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenu"><i class="fa-solid fa-bars"></i></button>
            <div class="breadcrumb-custom"><span>NexusPanel</span><i class="fa-solid fa-chevron-right"></i><span class="active">Pedidos</span></div>
        </div>
        <div class="topbar-right">
            <div class="topbar-date" id="topbarDate"></div><div class="dropdown">
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
        <div class="card-panel mb-3">
            <div class="panel-header mb-2">
                <div>
                    <h3 class="panel-title">Gestion de pedidos</h3>
                    <p class="panel-subtitle">Programacion de entrega y asignacion operativa desde manager.</p>
                </div>
            </div>
            <p class="panel-subtitle" style="margin-bottom:10px;">Aqui puedes filtrar el pedido del cliente que quieres visualizar.</p>
            <div class="toolbar-grid" id="pedidosToolbar">
                <input class="modal-input" type="text" id="pedidoFilterQ" placeholder="Buscar" value="<?= htmlspecialchars($q) ?>">
                <select class="modal-select" id="pedidoFilterEstado">
                    <option value="">Todos los estados</option>
                    <?php foreach (['pendiente','aceptado','en_camino','entregado','cancelado'] as $stt): ?>
                        <option value="<?= $stt ?>" <?= $estado === $stt ? 'selected' : '' ?>><?= ucfirst($stt) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="modal-select" id="pedidoFilterCliente">
                    <option value="">Todos los clientes</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= htmlspecialchars(mb_strtolower((string)$c['nombre'])) ?>" <?= $clienteId === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="modal-input" type="date" id="pedidoFilterFecha" value="<?= htmlspecialchars($fechaDesde !== '' ? $fechaDesde : $fechaHasta) ?>">
                <button type="button" class="btn-secondary-custom btn-table" id="pedidoFilterClear"><i class="fa-solid fa-broom"></i> Limpiar</button>
            </div>
        </div>

        <div class="card-panel">
            <?php if (!empty($cancelacionesPendientes)): ?>
            <div style="padding:12px;border-bottom:1px solid var(--border);display:grid;gap:10px;">
                <div class="panel-subtitle" style="margin:0;color:#fda4af;">Solicitudes de cancelacion pendientes</div>
                <?php foreach ($cancelacionesPendientes as $sc): ?>
                <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:10px;border:1px solid rgba(239,68,68,.3);border-radius:10px;background:rgba(239,68,68,.08);">
                    <input type="hidden" name="action" value="resolver_cancelacion_solicitada">
                    <input type="hidden" name="solicitud_id" value="<?= (int)$sc['id'] ?>">
                    <div style="font-size:12px;color:#fecaca;min-width:320px;">
                        <strong>#<?= (int)$sc['pedido_id'] ?></strong> ·
                        <?= $sc['solicitado_por'] === 'cliente' ? 'Cliente' : 'Operador' ?>:
                        <?= htmlspecialchars((string)($sc['solicitado_por'] === 'cliente' ? $sc['cliente_nombre'] : $sc['operador_nombre'])) ?><br>
                        Motivo: <?= htmlspecialchars((string)$sc['motivo']) ?>
                    </div>
                    <input class="modal-input" type="text" name="nota" placeholder="Nota de decision (opcional)" style="max-width:280px;">
                    <button class="btn-primary-custom btn-table" type="submit" name="decision" value="aprobar">Aprobar y cancelar</button>
                    <button class="btn-secondary-custom btn-table" type="submit" name="decision" value="rechazar">Rechazar</button>
                </form>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Fecha</th>
                        <th>Resumen productos</th>
                        <th>Cliente</th>
                        <th>Estatus</th>
                        <th>Operador</th>
                        <th>Calificacion</th>
                        <th>Asignar</th>
                        <th>Estado</th>
                        <th>Historial</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pedidos as $p): ?>
                        <tr class="pedido-row">
                            <td>
                                <strong>#<?= htmlspecialchars($p['folio_hex'] ?: strtoupper(dechex((int)$p['id']))) ?></strong><br>
                                <small style="color:var(--text-muted);"><?= (int)$p['total_items'] ?> unidad(es)</small>
                            </td>
                            <td><?= htmlspecialchars($p['created_at']) ?></td>
                            <td><small style="color:#d8e6ff;"><?= htmlspecialchars($p['resumen_productos'] ?: 'Sin productos') ?></small></td>
                            <td>
                                <strong><?= htmlspecialchars($p['cliente_nombre']) ?></strong><br>
                                <small><?= htmlspecialchars($p['cliente_email']) ?></small>
                            </td>
                            <td><span class="status-badge badge-<?= htmlspecialchars($p['estado']) ?>"><?= htmlspecialchars($p['estado']) ?></span></td>
                            <td>
                                <?php if (!empty($p['operador_nombre'])): ?>
                                    <span class="operador-asignado"><i class="fa-solid fa-user-check"></i> <?= htmlspecialchars((string)$p['operador_nombre']) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:12px;">Sin asignar</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $cal = $calificacionMap[(int)$p['id']] ?? null; ?>
                                <?php if ($cal): ?>
                                    <span style="color:#fbbf24;font-weight:700;"><?= str_repeat('★', max(1, min(5, (int)$cal['estrellas']))) ?></span>
                                    <?php if (!empty($cal['comentario'])): ?>
                                    <div style="font-size:11px;color:var(--text-muted);max-width:180px;white-space:normal;"><?= htmlspecialchars((string)$cal['comentario']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:12px;">Sin calificar</span>
                                <?php endif; ?>
                            </td>
<td>
                                <?php if (($p['estado'] ?? '') === 'pendiente'): ?>
                                    <button
                                        type="button"
                                        class="btn-primary-custom btn-seleccionar-operador btn-table btn-assign"
                                        data-pedido-id="<?= (int)$p['id'] ?>"
                                    >
                                        <i class="fa-solid fa-user-check"></i> Seleccionar operador
                                    </button>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:12px;">No aplica</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" class="mini-form js-form-estado">
                                    <input type="hidden" name="action" value="estado">
                                    <input type="hidden" name="pedido_id" value="<?= (int)$p['id'] ?>">
                                    <select name="estado" class="modal-select">
                                        <?php foreach (['pendiente','aceptado','en_camino','entregado','cancelado'] as $stt): ?>
                                            <option value="<?= $stt ?>" <?= ($p['estado'] === $stt) ? 'selected' : '' ?>><?= $stt ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn-primary-custom btn-table" type="submit">Aplicar</button>
                                </form>
                            </td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                <a href="pedidos.php?historial=<?= (int)$p['id'] ?>#detalle-historial" class="btn-secondary-custom btn-table">
                                    <i class="fa-solid fa-clock-rotate-left"></i> Ver
                                </a>
                                <button type="button" class="btn-primary-custom btn-table btn-ver-pedido" data-pedido-id="<?= (int)$p['id'] ?>">
                                    <i class="fa-solid fa-route"></i> Ver pedido
                                </button>
                                <?php if ((int)($p['evidencias_count'] ?? 0) > 0): ?>
                                <a href="pedidos.php?historial=<?= (int)$p['id'] ?>#detalle-historial" class="btn-secondary-custom btn-table" style="border-color:rgba(16,185,129,.45);color:#86efac;">
                                    <i class="fa-solid fa-camera"></i> Fotos (<?= (int)$p['evidencias_count'] ?>)
                                </a>
                                <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($histPedidoId > 0): ?>
            <div class="card-panel mt-3" id="detalle-historial">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">Detalle visual del pedido #<?= $histPedidoId ?></h3>
                        <p class="panel-subtitle">Productos, imagenes y unidades registradas en este pedido.</p>
                    </div>
                </div>
                <?php if (!empty($detallePedidoItems)): ?>
                    <div class="pedido-detalle-grid">
                        <?php foreach ($detallePedidoItems as $item): ?>
                            <div class="pedido-detalle-item">
                                <div class="pedido-detalle-thumb">
                                    <?php if (!empty($item['imagen'])): ?>
                                        <img src="../uploads/productos/<?= htmlspecialchars($item['imagen']) ?>" alt="<?= htmlspecialchars($item['nombre']) ?>" loading="lazy" onerror="this.style.display='none';this.parentNode.innerHTML='<i class=&quot;fa-solid fa-box-open&quot;></i>';">
                                    <?php else: ?>
                                        <i class="fa-solid fa-box-open"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="pedido-detalle-meta">
                                    <div class="pedido-detalle-nombre"><?= htmlspecialchars($item['nombre']) ?></div>
                                    <div class="pedido-detalle-sub">
                                        <?= (int)$item['cantidad'] ?> unidades
                                        Â· <?= htmlspecialchars($item['unidad_medida']) ?>
                                    </div>
                                </div>
                                
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Este pedido no tiene productos visibles.</p>
                <?php endif; ?>

                <div class="entrega-meta-grid">
                    <div class="entrega-meta-item">
                        <strong>Persona que recibio</strong>
                        <span><?= htmlspecialchars((string)($detallePedido['persona_recibe'] ?? 'Sin registro')) ?></span>
                    </div>
                    <div class="entrega-meta-item">
                        <strong>Estado del paquete</strong>
                        <span><?= htmlspecialchars((string)($detallePedido['estado_paquete'] ?? 'Sin registro')) ?></span>
                    </div>
                    <div class="entrega-meta-item">
                        <strong>Comentario de entrega</strong>
                        <span><?= htmlspecialchars((string)($detallePedido['comentario_entrega'] ?? 'Sin comentario')) ?></span>
                    </div>
                </div>

                <?php if (!empty($detalleEvidencias)): ?>
                    <div class="evidencias-grid">
                        <?php foreach ($detalleEvidencias as $ev): ?>
                            <?php $fotoEv = (string)($ev['foto_url'] ?? ''); $fotoEvSrc = $fotoEv !== '' && $fotoEv[0] === '/' ? ('..' . $fotoEv) : $fotoEv; ?>
                            <div class="evidencia-card">
                                <img src="<?= htmlspecialchars($fotoEvSrc) ?>" alt="Evidencia de entrega" loading="lazy">
                                <small><?= htmlspecialchars((string)($ev['created_at'] ?? '')) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mt-3 mb-0">Sin evidencias fotograficas registradas para este pedido.</p>
                <?php endif; ?>
            </div>

            <div class="card-panel mt-3">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">Historial del pedido #<?= $histPedidoId ?></h3>
                        <p class="panel-subtitle">Trazabilidad completa de cambios y estatus.</p>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table class="data-table" style="min-width:900px;">
                        <thead><tr><th>Fecha</th><th>Estado</th><th>Nota</th><th>Usuario</th></tr></thead>
                        <tbody>
                        <?php foreach ($historial as $h): ?>
                            <tr>
                                <td><?= htmlspecialchars($h['created_at']) ?></td>
                                <td><?= htmlspecialchars($h['estado']) ?></td>
                                <td><?= htmlspecialchars($h['nota'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($h['usuario_nombre'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<div class="modal fade modal-dark" id="modalSeleccionarOperador" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-user-check"></i> Seleccionar operador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;"><input class="modal-input" id="buscarOperadorInput" placeholder="Buscar operador por nombre..." style="flex:1 1 260px;"><button type="button" class="btn-secondary-custom btn-table" id="btnFiltroSoloDisponibles"><i class="fa-solid fa-user-check"></i> Solo disponibles</button></div>
                <div class="op-help">Se priorizan operadores en rango de 6 km. Si no hay en rango, puedes asignar fuera de rango con confirmacion.</div>
                <div id="listaOperadoresContainer"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modal-dark" id="modalConfirmarAsignacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content" id="formAsignarOperador">
            <input type="hidden" name="action" value="asignar_operador">
            <input type="hidden" name="pedido_id" id="asignarPedidoId">
            <input type="hidden" name="operador_id" id="asignarOperadorId">
            <input type="hidden" name="allow_out_of_range" id="asignarAllowOutOfRange" value="0">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-circle-question"></i> Confirmar asignacion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="confirmarAsignacionText" style="margin:0;">Â¿Estas seguro de que deseas asignar este pedido?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">No</button>
                <button type="submit" class="btn-primary-custom">Si, asignar</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade modal-dark" id="modalResumenPedido" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-file-lines"></i> Resumen del pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="resumenPedidoContainer"></div>
            </div>
            <div class="modal-footer" style="justify-content:space-between;">
                <form method="post" id="formCancelarPedido">
                    <input type="hidden" name="action" value="cancelar_pedido">
                    <input type="hidden" name="pedido_id" id="cancelarPedidoId">
                    <input type="hidden" name="motivo_cancelacion" value="Cancelado por coordinacion desde resumen de pedido">
                    <button type="button" class="btn-secondary-custom" id="btnCancelarPedidoAccion" style="border-color:rgba(239,68,68,.4);color:#fca5a5;">
                        <i class="fa-solid fa-ban"></i> Cancelar pedido
                    </button>
                </form>
                <button type="button" class="btn-primary-custom" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
<script>
const ASIGNABLES_POR_PEDIDO = <?= json_encode($asignablesPorPedido, JSON_UNESCAPED_UNICODE) ?>;
const PEDIDOS_BY_ID = <?= json_encode($pedidosById, JSON_UNESCAPED_UNICODE) ?>;
const PEDIDO_ASIGNADO_RECIENTE = <?= (int)$pedidoAsignadoReciente ?>;
const PEDIDO_ABRIR_ASIGNACION = <?= (int)$pedidoAbrirAsignacion ?>;
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

const modalSeleccionarOperador = new bootstrap.Modal(document.getElementById('modalSeleccionarOperador'));
const modalConfirmarAsignacion = new bootstrap.Modal(document.getElementById('modalConfirmarAsignacion'));
const modalResumenPedido = new bootstrap.Modal(document.getElementById('modalResumenPedido'));

let pedidoActualModal = 0;
let operadorSeleccionado = null;
let filtroSoloDisponibles = true;

function renderListaOperadores(pedidoId, filtro = '') {
    const cont = document.getElementById('listaOperadoresContainer');
    const baseList = ASIGNABLES_POR_PEDIDO[String(pedidoId)] || [];
    const list = baseList.filter(op => {
        const byName = !filtro || String(op.nombre || '').toLowerCase().includes(filtro.toLowerCase());
        const byDisponible = !filtroSoloDisponibles || !!op.disponible;
        return byName && byDisponible;
    });

    if (!list.length) {
        cont.innerHTML = '<div class="op-list-empty">No hay operadores con esos filtros.</div>';
        return;
    }

    cont.innerHTML = list.map(op => `
        <div class="op-item" data-op-id="${op.id}">
            <div class="op-meta">
                <strong>${op.nombre}</strong>
                <small>${op.telefono || 'Sin telefono'}</small>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <span class="op-dist">${op.distancia_km !== null ? Number(op.distancia_km).toFixed(2) + ' km' : 'Sin ubicacion'}</span>
                ${op.disponible ? '<span class="op-status-ok">Disponible</span>' : '<span class="op-status-busy">No disponible (5 activos)</span>'}
                ${op.within_range ? '' : '<span class="op-out">Fuera de 6 km</span>'}
                <button type="button" class="btn-primary-custom btn-op-select" data-op-id="${op.id}" ${op.disponible ? '' : 'disabled style="opacity:.45;cursor:not-allowed;"'}>Seleccionar</button>
            </div>
        </div>
    `).join('');

    cont.querySelectorAll('.btn-op-select').forEach(btn => {
        btn.addEventListener('click', () => {
            const opId = Number(btn.dataset.opId || 0);
            const op = list.find(x => Number(x.id) === opId);
            if (!op || !op.disponible) return;
            operadorSeleccionado = op;
            document.getElementById('asignarPedidoId').value = String(pedidoId);
            document.getElementById('asignarOperadorId').value = String(op.id);
            const allowOutRange = op.within_range ? 0 : 1;
            document.getElementById('asignarAllowOutOfRange').value = String(allowOutRange);
            document.getElementById('confirmarAsignacionText').textContent =
                allowOutRange
                    ? `¿Asignar este pedido a ${op.nombre} fuera del rango de 6 km?`
                    : `¿Estas seguro de que deseas asignar este pedido a ${op.nombre}?`;
            modalSeleccionarOperador.hide();
            setTimeout(() => modalConfirmarAsignacion.show(), 120);
        });
    });
}

function abrirModalSeleccionOperador(pedidoId) {
    pedidoActualModal = Number(pedidoId || 0);
    if (!pedidoActualModal) return;
    document.getElementById('buscarOperadorInput').value = '';
    renderListaOperadores(pedidoActualModal, '');
    modalSeleccionarOperador.show();
}

document.querySelectorAll('.btn-seleccionar-operador').forEach(btn => {
    btn.addEventListener('click', () => {
        abrirModalSeleccionOperador(Number(btn.dataset.pedidoId || 0));
    });
});

document.getElementById('buscarOperadorInput')?.addEventListener('input', (e) => {
    renderListaOperadores(pedidoActualModal, e.target.value || '');
});

document.getElementById('btnFiltroSoloDisponibles')?.addEventListener('click', (e) => {
    filtroSoloDisponibles = !filtroSoloDisponibles;
    e.currentTarget.classList.toggle('active', filtroSoloDisponibles);
    e.currentTarget.innerHTML = filtroSoloDisponibles
        ? '<i class="fa-solid fa-user-check"></i> Solo disponibles'
        : '<i class="fa-solid fa-users"></i> Mostrar todos';
    renderListaOperadores(pedidoActualModal, document.getElementById('buscarOperadorInput')?.value || '');
});

function abrirResumenPedido(pedidoId) {
    const pedido = PEDIDOS_BY_ID[String(pedidoId)];
    if (!pedido) return;
    const resumen = document.getElementById('resumenPedidoContainer');
    resumen.innerHTML = `
        <div class="pedido-detalle-grid">
            <div class="pedido-detalle-item">
                <div class="pedido-detalle-meta">
                    <div class="pedido-detalle-nombre">Pedido #${pedido.folio_hex || Number(pedido.id).toString(16).toUpperCase()}</div>
                    <div class="pedido-detalle-sub">Cliente: ${pedido.cliente_nombre || '-'} Â· ${pedido.cliente_email || '-'}</div>
                    <div class="pedido-detalle-sub">Estado: ${pedido.estado || '-'} Â· Items: ${pedido.total_items || 0}</div>
                    <div class="pedido-detalle-sub">Productos: ${pedido.resumen_productos || 'Sin productos'}</div>
                </div>
                
            </div>
        </div>
    `;
    document.getElementById('cancelarPedidoId').value = String(pedidoId);
    modalResumenPedido.show();
}

document.getElementById('btnCancelarPedidoAccion')?.addEventListener('click', () => {
    const ok = window.confirm('Â¿Estas seguro de que deseas cancelar este pedido?');
    if (ok) {
        document.getElementById('formCancelarPedido').submit();
    }
});

document.querySelectorAll('.btn-ver-pedido').forEach((btn) => {
    btn.addEventListener('click', () => {
        const pedidoId = Number(btn.dataset.pedidoId || 0);
        if (!pedidoId) return;
        abrirResumenPedido(pedidoId);
    });
});

function filtrarPedidosTabla() {
    const q = (document.getElementById('pedidoFilterQ')?.value || '').toLowerCase().trim();
    const estado = (document.getElementById('pedidoFilterEstado')?.value || '').toLowerCase().trim();
    const cliente = (document.getElementById('pedidoFilterCliente')?.value || '').toLowerCase().trim();
    const fecha = (document.getElementById('pedidoFilterFecha')?.value || '').trim();

    document.querySelectorAll('.pedido-row').forEach((row) => {
        const tds = row.querySelectorAll('td');
        if (!tds.length) return;
        const texto = (row.textContent || '').toLowerCase();
        const estadoTxt = (row.querySelector('.status-badge')?.textContent || '').toLowerCase();
        const clienteTxt = (tds[3]?.textContent || '').toLowerCase();
        const fechaTxt = (tds[1]?.textContent || '').trim().slice(0, 10);

        const okQ = !q || texto.includes(q);
        const okEstado = !estado || estadoTxt.includes(estado);
        const okCliente = !cliente || clienteTxt.includes(cliente);
        const okFecha = !fecha || fechaTxt === fecha;
        row.style.display = (okQ && okEstado && okCliente && okFecha) ? '' : 'none';
    });
}

['pedidoFilterQ','pedidoFilterEstado','pedidoFilterCliente','pedidoFilterFecha'].forEach((id) => {
    const el = document.getElementById(id);
    el?.addEventListener('input', filtrarPedidosTabla);
    el?.addEventListener('change', filtrarPedidosTabla);
});

document.getElementById('pedidoFilterClear')?.addEventListener('click', () => {
    const q = document.getElementById('pedidoFilterQ');
    const e = document.getElementById('pedidoFilterEstado');
    const c = document.getElementById('pedidoFilterCliente');
    const f = document.getElementById('pedidoFilterFecha');
    if (q) q.value = '';
    if (e) e.value = '';
    if (c) c.value = '';
    if (f) f.value = '';
    filtrarPedidosTabla();
});

filtrarPedidosTabla();

document.querySelectorAll('.js-form-estado').forEach((form) => {
    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const fd = new FormData(form);
        const estadoNuevo = String(fd.get('estado') || '');
        const btn = form.querySelector('button[type="submit"]');
        if (btn) { btn.disabled = true; btn.textContent = 'Guardando...'; }
        try {
            const res = await fetch(window.location.pathname, { method: 'POST', body: fd, credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const row = form.closest('tr');
            const badge = row?.querySelector('.status-badge');
            if (badge && estadoNuevo) {
                badge.textContent = estadoNuevo;
                badge.className = 'status-badge badge-' + estadoNuevo;
            }
            if (btn) btn.textContent = 'Aplicado';
            setTimeout(() => { if (btn) { btn.disabled = false; btn.textContent = 'Aplicar'; } }, 700);
        } catch (_) {
            if (btn) { btn.disabled = false; btn.textContent = 'Aplicar'; }
            alert('No se pudo actualizar el estado.');
        }
    });
});
initPanelNotis();
if (PEDIDO_ASIGNADO_RECIENTE > 0) {
    abrirResumenPedido(PEDIDO_ASIGNADO_RECIENTE);
}
if (PEDIDO_ABRIR_ASIGNACION > 0) {
    abrirModalSeleccionOperador(PEDIDO_ABRIR_ASIGNACION);
}
</script>
</body>
</html>











