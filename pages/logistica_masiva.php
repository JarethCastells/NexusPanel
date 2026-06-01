<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireAdmin();

$usuario = usuarioActual();
$esInventario = esInventario();
$esAdmin = esAdmin();
$nombreRol = nombreRolActual();
$msg = '';
$err = '';
$hasFolio = columnExists($pdo, 'pedidos', 'folio');
$hasFolioHexPedidos = columnExists($pdo, 'pedidos', 'folio_hex');
$folioExprPedidos = $hasFolio ? 'p.folio' : ($hasFolioHexPedidos ? 'p.folio_hex' : 'UPPER(HEX(p.id))');
$hasCapacidadPedidos = columnExists($pdo, 'usuarios', 'capacidad_pedidos');
$exprCapacidadPedidos = $hasCapacidadPedidos ? 'COALESCE(capacidad_pedidos,5)' : '5';

function capacidadCajasOperador(array $op): int {
    $base = (int)($op['capacidad_pedidos'] ?? 5);
    return max(1, $base) * 5;
}

function estimarCajasPedido(array $pedido): int {
    return max(1, (int)ceil(((int)($pedido['total_items'] ?? 1)) / 4));
}

function rutaKey(string $domicilio): string {
    $d = mb_strtolower(trim($domicilio));
    if ($d === '') return 'ruta-general';
    $d = preg_replace('/[^a-z0-9 ]+/u', ' ', $d);
    $d = preg_replace('/\s+/u', ' ', $d);
    return mb_substr($d, 0, 36);
}

function checklistPayload(string $modo): array {
    if ($modo === 'salida') {
        return [
            'inventario_confirmado' => isset($_POST['inventario_confirmado']) ? 1 : 0,
            'cajas_verificadas' => isset($_POST['cajas_verificadas']) ? 1 : 0,
            'documentacion_ok' => isset($_POST['documentacion_ok']) ? 1 : 0,
            'combustible_ok' => isset($_POST['combustible_ok']) ? 1 : 0,
            'nota' => mb_substr(trim((string)($_POST['nota_salida'] ?? '')), 0, 300),
        ];
    }
    return [
        'evidencias_subidas' => isset($_POST['evidencias_subidas']) ? 1 : 0,
        'entregas_confirmadas' => isset($_POST['entregas_confirmadas']) ? 1 : 0,
        'devoluciones_registradas' => isset($_POST['devoluciones_registradas']) ? 1 : 0,
        'nota' => mb_substr(trim((string)($_POST['nota_cierre'] ?? '')), 0, 300),
    ];
}

function checklistCompleto(array $payload, string $modo): bool {
    if ($modo === 'salida') {
        return (int)($payload['inventario_confirmado'] ?? 0) === 1
            && (int)($payload['cajas_verificadas'] ?? 0) === 1
            && (int)($payload['documentacion_ok'] ?? 0) === 1
            && (int)($payload['combustible_ok'] ?? 0) === 1;
    }
    return (int)($payload['evidencias_subidas'] ?? 0) === 1
        && (int)($payload['entregas_confirmadas'] ?? 0) === 1
        && (int)($payload['devoluciones_registradas'] ?? 0) === 1;
}

function loteEditable(PDO $pdo, int $loteId): bool {
    if ($loteId <= 0) return false;
    $st = $pdo->prepare("SELECT estado FROM logistica_lotes WHERE id=? LIMIT 1");
    $st->execute([$loteId]);
    $estado = (string)$st->fetchColumn();
    return !in_array($estado, ['cerrado', 'cancelado'], true);
}

function obtenerReporteLote(PDO $pdo, int $loteId, string $folioExprPedidos): array {
    $st = $pdo->prepare("
        SELECT
            ll.folio,
            ll.estado AS estado_lote,
            lp.prioridad_ruta,
            p.id AS pedido_id,
            {$folioExprPedidos} AS folio_pedido,
            c.nombre AS cliente,
            o.nombre AS operador,
            lp.ruta_grupo,
            lp.cajas_asignadas,
            lp.estado AS estado_item
        FROM logistica_lote_pedidos lp
        JOIN logistica_lotes ll ON ll.id = lp.lote_id
        JOIN pedidos p ON p.id = lp.pedido_id
        JOIN usuarios c ON c.id = p.cliente_id
        LEFT JOIN usuarios o ON o.id = lp.operador_id
        WHERE lp.lote_id = ?
        ORDER BY lp.prioridad_ruta ASC, lp.id ASC
    ");
    $st->execute([$loteId]);
    return $st->fetchAll();
}

if (isset($_GET['csv']) && (int)$_GET['csv'] > 0) {
    $csvLote = (int)$_GET['csv'];
    $rowsCsv = obtenerReporteLote($pdo, $csvLote, $folioExprPedidos);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="logistica_lote_' . $csvLote . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['lote', 'estado_lote', 'prioridad', 'pedido_id', 'folio_pedido', 'cliente', 'operador', 'ruta', 'cajas', 'estado_item']);
    foreach ($rowsCsv as $r) {
        fputcsv($out, [
            $r['folio'],
            $r['estado_lote'],
            $r['prioridad_ruta'],
            $r['pedido_id'],
            $r['folio_pedido'],
            $r['cliente'],
            $r['operador'],
            $r['ruta_grupo'],
            $r['cajas_asignadas'],
            $r['estado_item'],
        ]);
    }
    fclose($out);
    exit;
}

if (isset($_GET['json']) && (int)$_GET['json'] > 0) {
    $jsonLote = (int)$_GET['json'];
    $rowsJson = obtenerReporteLote($pdo, $jsonLote, $folioExprPedidos);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'lote_id' => $jsonLote,
        'generated_at' => date('c'),
        'rows' => $rowsJson,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'crear_lote') {
            $pedidoIds = array_map('intval', (array)($_POST['pedido_ids'] ?? []));
            $pedidoIds = array_values(array_filter($pedidoIds, static fn($id) => $id > 0));
            if (empty($pedidoIds)) throw new RuntimeException('Selecciona al menos un pedido.');

            $modo = ($_POST['modo_envio'] ?? 'flotilla') === 'paqueteria' ? 'paqueteria' : 'flotilla';
            $paqueteria = trim((string)($_POST['paqueteria'] ?? ''));
            $costoPct = max(0, (float)($_POST['costo_pct'] ?? 0));
            $costoCaja = max(0, (float)($_POST['costo_por_caja'] ?? 0));
            $fechaSalida = trim((string)($_POST['fecha_salida_programada'] ?? ''));
            $fechaEntrega = trim((string)($_POST['fecha_entrega_estimada'] ?? ''));
            $obs = mb_substr(trim((string)($_POST['observaciones'] ?? '')), 0, 500);

            $ops = $pdo->query("
                SELECT id, nombre, {$exprCapacidadPedidos} AS capacidad_pedidos
                FROM usuarios
                WHERE rol='operador' AND activo=1
                ORDER BY nombre ASC
            ")->fetchAll();
            if (empty($ops)) throw new RuntimeException('No hay operadores activos disponibles.');

            $place = implode(',', array_fill(0, count($pedidoIds), '?'));
            $stPedidos = $pdo->prepare("
                SELECT
                    p.id,
                    {$folioExprPedidos} AS folio_hex,
                    p.total,
                    p.estado,
                    p.cliente_id,
                    c.nombre AS cliente_nombre,
                    c.domicilio AS cliente_domicilio,
                    COUNT(pi.id) AS total_items
                FROM pedidos p
                JOIN usuarios c ON c.id = p.cliente_id
                LEFT JOIN pedido_items pi ON pi.pedido_id = p.id
                WHERE p.id IN ($place)
                  AND p.estado IN ('pendiente','aceptado','en_camino')
                GROUP BY p.id, p.total, p.estado, p.cliente_id, c.nombre, c.domicilio
                ORDER BY p.id ASC
            ");
            $stPedidos->execute($pedidoIds);
            $pedidos = $stPedidos->fetchAll();
            if (empty($pedidos)) throw new RuntimeException('No hay pedidos validos para lote.');

            usort($pedidos, static function ($a, $b) {
                return strcmp(rutaKey((string)($a['cliente_domicilio'] ?? '')), rutaKey((string)($b['cliente_domicilio'] ?? '')));
            });

            $pdo->beginTransaction();
            $folio = 'LM-' . date('Ymd-His');
            $stLote = $pdo->prepare("
                INSERT INTO logistica_lotes
                    (folio, manager_id, modo_envio, paqueteria, costo_pct, costo_por_caja, estado, fecha_salida_programada, fecha_entrega_estimada, observaciones)
                VALUES (?,?,?,?,?,?, 'borrador', ?, ?, ?)
            ");
            $stLote->execute([
                $folio, (int)$usuario['usuario_id'], $modo,
                $paqueteria !== '' ? $paqueteria : null,
                $costoPct, $costoCaja,
                $fechaSalida !== '' ? $fechaSalida : null,
                $fechaEntrega !== '' ? $fechaEntrega : null,
                $obs !== '' ? $obs : null,
            ]);
            $loteId = (int)$pdo->lastInsertId();

            $capacityByOp = [];
            foreach ($ops as $op) {
                $cap = capacidadCajasOperador($op);
                $capacityByOp[(int)$op['id']] = ['cap' => $cap, 'used' => 0];
                $pdo->prepare("
                    INSERT INTO logistica_lote_operadores (lote_id, operador_id, camion_alias, capacidad_cajas, cajas_asignadas, fecha_operacion)
                    VALUES (?,?,?,?,0,?)
                ")->execute([$loteId, (int)$op['id'], 'Camion ' . $op['nombre'], $cap, $fechaSalida !== '' ? $fechaSalida : null]);
            }

            $opIds = array_keys($capacityByOp);
            $opIdx = 0;
            $totalEstimado = 0.0;
            $prioridad = 1;
            foreach ($pedidos as $p) {
                $cajas = estimarCajasPedido($p);
                $totalEstimado += ((float)($p['total'] ?? 0) * ($costoPct / 100.0)) + ($cajas * $costoCaja);

                $tries = 0;
                $asignadoOp = $opIds[0];
                while ($tries < count($opIds)) {
                    $candidate = $opIds[$opIdx % count($opIds)];
                    $opIdx++;
                    $tries++;
                    if (($capacityByOp[$candidate]['used'] + $cajas) <= $capacityByOp[$candidate]['cap']) {
                        $asignadoOp = $candidate;
                        break;
                    }
                }
                $capacityByOp[$asignadoOp]['used'] += $cajas;
                $ruta = rutaKey((string)($p['cliente_domicilio'] ?? ''));
                $pdo->prepare("
                    INSERT INTO logistica_lote_pedidos
                        (lote_id, pedido_id, ruta_grupo, prioridad_ruta, cajas_sugeridas, cajas_asignadas, operador_id, estado)
                    VALUES (?,?,?,?,?,?,?, 'sugerido')
                ")->execute([$loteId, (int)$p['id'], $ruta, $prioridad, $cajas, $cajas, $asignadoOp]);
                $prioridad++;
            }

            foreach ($capacityByOp as $opId => $info) {
                $pdo->prepare("UPDATE logistica_lote_operadores SET cajas_asignadas=? WHERE lote_id=? AND operador_id=?")
                    ->execute([(int)$info['used'], $loteId, (int)$opId]);
            }

            $pdo->prepare("UPDATE logistica_lotes SET costo_estimado_total=? WHERE id=?")->execute([$totalEstimado, $loteId]);
            $pdo->prepare("INSERT INTO logistica_eventos (lote_id, usuario_id, tipo_evento, detalle) VALUES (?,?,?,?)")
                ->execute([$loteId, (int)$usuario['usuario_id'], 'lote_creado', 'Lote creado con asignacion sugerida automatica']);
            $pdo->commit();
            $msg = "Lote $folio creado correctamente.";
        }

        if (in_array($action, ['auto_rebalancear', 'guardar_asignaciones', 'iniciar_ruta_operador', 'cerrar_ruta_operador', 'actualizar_estado_lote'], true)) {
            $loteId = (int)($_POST['lote_id'] ?? 0);
            if ($loteId <= 0) throw new RuntimeException('Lote invalido.');
            if (!loteEditable($pdo, $loteId) && $action !== 'actualizar_estado_lote') {
                throw new RuntimeException('El lote esta cerrado/cancelado y ya no permite cambios.');
            }
        }

        if ($action === 'auto_rebalancear') {
            $loteId = (int)$_POST['lote_id'];
            $pdo->beginTransaction();

            $ops = $pdo->prepare("
                SELECT lo.operador_id, lo.capacidad_cajas
                FROM logistica_lote_operadores lo
                WHERE lo.lote_id = ?
                ORDER BY lo.capacidad_cajas DESC, lo.operador_id ASC
            ");
            $ops->execute([$loteId]);
            $opsRows = $ops->fetchAll();
            if (empty($opsRows)) throw new RuntimeException('No hay operadores ligados al lote.');

            $items = $pdo->prepare("
                SELECT id, cajas_asignadas
                FROM logistica_lote_pedidos
                WHERE lote_id = ?
                ORDER BY prioridad_ruta ASC, id ASC
            ");
            $items->execute([$loteId]);
            $rows = $items->fetchAll();

            $caps = [];
            foreach ($opsRows as $op) {
                $caps[(int)$op['operador_id']] = ['cap' => (int)$op['capacidad_cajas'], 'used' => 0];
            }
            foreach ($rows as $r) {
                $bestId = null;
                $bestScore = 999999.0;
                foreach ($caps as $oid => $c) {
                    $score = $c['used'] / max(1, $c['cap']);
                    if ($score < $bestScore) {
                        $bestScore = $score;
                        $bestId = $oid;
                    }
                }
                $caps[$bestId]['used'] += max(1, (int)$r['cajas_asignadas']);
                $pdo->prepare("UPDATE logistica_lote_pedidos SET operador_id=?, estado='asignado' WHERE id=?")
                    ->execute([$bestId, (int)$r['id']]);
            }

            $pdo->prepare("UPDATE logistica_lote_operadores SET cajas_asignadas=0 WHERE lote_id=?")->execute([$loteId]);
            foreach ($caps as $oid => $c) {
                $pdo->prepare("UPDATE logistica_lote_operadores SET cajas_asignadas=? WHERE lote_id=? AND operador_id=?")
                    ->execute([(int)$c['used'], $loteId, (int)$oid]);
            }
            $pdo->prepare("INSERT INTO logistica_eventos (lote_id, usuario_id, tipo_evento, detalle) VALUES (?,?,?,?)")
                ->execute([$loteId, (int)$usuario['usuario_id'], 'auto_rebalanceo', 'Rebalanceo automatico aplicado']);
            $pdo->commit();
            $msg = 'Rebalanceo automatico aplicado.';
        }

        if ($action === 'guardar_asignaciones') {
            $loteId = (int)$_POST['lote_id'];
            $rows = (array)($_POST['fila'] ?? []);
            $pdo->beginTransaction();
            foreach ($rows as $idFila => $data) {
                $idFila = (int)$idFila;
                $opId = (int)($data['operador_id'] ?? 0);
                $cajas = max(1, (int)($data['cajas_asignadas'] ?? 1));
                $ruta = mb_substr(trim((string)($data['ruta_grupo'] ?? 'ruta-general')), 0, 80);
                $prio = max(1, (int)($data['prioridad_ruta'] ?? 100));
                $pdo->prepare("
                    UPDATE logistica_lote_pedidos
                    SET operador_id=?, cajas_asignadas=?, ruta_grupo=?, prioridad_ruta=?, estado='asignado'
                    WHERE id=? AND lote_id=?
                ")->execute([$opId > 0 ? $opId : null, $cajas, $ruta, $prio, $idFila, $loteId]);
            }
            $pdo->prepare("UPDATE logistica_lote_operadores SET cajas_asignadas=0 WHERE lote_id=?")->execute([$loteId]);
            $pdo->prepare("
                UPDATE logistica_lote_operadores lo
                JOIN (
                    SELECT lote_id, operador_id, COALESCE(SUM(cajas_asignadas),0) AS cajas
                    FROM logistica_lote_pedidos
                    WHERE lote_id = ? AND operador_id IS NOT NULL
                    GROUP BY lote_id, operador_id
                ) x ON x.lote_id = lo.lote_id AND x.operador_id = lo.operador_id
                SET lo.cajas_asignadas = x.cajas
                WHERE lo.lote_id = ?
            ")->execute([$loteId, $loteId]);
            $pdo->prepare("INSERT INTO logistica_eventos (lote_id, usuario_id, tipo_evento, detalle) VALUES (?,?,?,?)")
                ->execute([$loteId, (int)$usuario['usuario_id'], 'asignacion_manual', 'Manager ajusto asignaciones']);
            $pdo->commit();
            $msg = 'Asignaciones actualizadas.';
        }

        if ($action === 'iniciar_ruta_operador') {
            $loteId = (int)$_POST['lote_id'];
            $operadorId = (int)($_POST['operador_id'] ?? 0);
            if ($operadorId <= 0) throw new RuntimeException('Operador invalido.');
            $payload = checklistPayload('salida');
            if (!checklistCompleto($payload, 'salida')) {
                throw new RuntimeException('Checklist de salida incompleto. Debes confirmar todos los puntos para iniciar ruta.');
            }
            $pdo->beginTransaction();
            $pdo->prepare("
                UPDATE logistica_lote_operadores
                SET estado='en_ruta', hora_salida=NOW(), checklist_salida_json=?
                WHERE lote_id=? AND operador_id=?
            ")->execute([json_encode($payload, JSON_UNESCAPED_UNICODE), $loteId, $operadorId]);
            $pdo->prepare("
                UPDATE logistica_lote_pedidos
                SET estado='en_ruta'
                WHERE lote_id=? AND operador_id=? AND estado IN ('sugerido','asignado')
            ")->execute([$loteId, $operadorId]);
            $pdo->prepare("UPDATE logistica_lotes SET estado='en_embarque', hora_salida=COALESCE(hora_salida,NOW()) WHERE id=?")->execute([$loteId]);
            $pdo->prepare("INSERT INTO logistica_eventos (lote_id, usuario_id, tipo_evento, detalle) VALUES (?,?,?,?)")
                ->execute([$loteId, (int)$usuario['usuario_id'], 'inicio_ruta', 'Operador #' . $operadorId . ' inicio ruta']);
            $pdo->commit();
            $msg = 'Ruta iniciada para operador.';
        }

        if ($action === 'cerrar_ruta_operador') {
            $loteId = (int)$_POST['lote_id'];
            $operadorId = (int)($_POST['operador_id'] ?? 0);
            if ($operadorId <= 0) throw new RuntimeException('Operador invalido.');
            $payload = checklistPayload('cierre');
            if (!checklistCompleto($payload, 'cierre')) {
                throw new RuntimeException('Checklist de cierre incompleto. Debes confirmar todos los puntos para cerrar ruta.');
            }
            $pdo->beginTransaction();
            $pdo->prepare("
                UPDATE logistica_lote_operadores
                SET estado='cerrado', hora_entrega=NOW(), checklist_cierre_json=?
                WHERE lote_id=? AND operador_id=?
            ")->execute([json_encode($payload, JSON_UNESCAPED_UNICODE), $loteId, $operadorId]);
            $pdo->prepare("
                UPDATE logistica_lote_pedidos
                SET estado='entregado'
                WHERE lote_id=? AND operador_id=? AND estado IN ('en_ruta','entregado_parcial')
            ")->execute([$loteId, $operadorId]);
            $stPend = $pdo->prepare("SELECT COUNT(*) FROM logistica_lote_operadores WHERE lote_id=? AND estado <> 'cerrado'");
            $stPend->execute([$loteId]);
            if ((int)$stPend->fetchColumn() === 0) {
                $pdo->prepare("UPDATE logistica_lotes SET estado='cerrado', hora_cierre=NOW() WHERE id=?")->execute([$loteId]);
            }
            $pdo->prepare("INSERT INTO logistica_eventos (lote_id, usuario_id, tipo_evento, detalle) VALUES (?,?,?,?)")
                ->execute([$loteId, (int)$usuario['usuario_id'], 'cierre_ruta', 'Operador #' . $operadorId . ' cerro ruta']);
            $pdo->commit();
            $msg = 'Ruta cerrada para operador.';
        }

        if ($action === 'actualizar_estado_lote') {
            $loteId = (int)$_POST['lote_id'];
            $estado = (string)($_POST['estado'] ?? 'borrador');
            $valid = ['borrador','pendiente_aceptacion','aceptado','en_embarque','cerrado','cancelado'];
            if (!in_array($estado, $valid, true)) throw new RuntimeException('Estado invalido.');
            $pdo->prepare("UPDATE logistica_lotes SET estado=? WHERE id=?")->execute([$estado, $loteId]);
            $pdo->prepare("INSERT INTO logistica_eventos (lote_id, usuario_id, tipo_evento, detalle) VALUES (?,?,?,?)")
                ->execute([$loteId, (int)$usuario['usuario_id'], 'estado_lote', 'Estado actualizado a ' . $estado]);
            $msg = 'Estado del lote actualizado.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = $e->getMessage();
    }
}

$ops = $pdo->query("
    SELECT id, nombre, telefono, {$exprCapacidadPedidos} AS capacidad_pedidos
    FROM usuarios
    WHERE rol='operador' AND activo=1
    ORDER BY nombre ASC
")->fetchAll();

$pedidosDisponibles = $pdo->query("
    SELECT
        p.id, {$folioExprPedidos} AS folio_hex, p.total, p.estado, p.created_at,
        c.nombre AS cliente_nombre, COUNT(pi.id) AS total_items
    FROM pedidos p
    JOIN usuarios c ON c.id = p.cliente_id
    LEFT JOIN pedido_items pi ON pi.pedido_id = p.id
    WHERE p.estado IN ('pendiente','aceptado','en_camino')
    GROUP BY p.id, p.total, p.estado, p.created_at, c.nombre
    ORDER BY p.created_at DESC
    LIMIT 200
")->fetchAll();

$lotes = $pdo->query("
    SELECT ll.*, u.nombre AS manager_nombre
    FROM logistica_lotes ll
    JOIN usuarios u ON u.id = ll.manager_id
    ORDER BY ll.id DESC
    LIMIT 30
")->fetchAll();

$loteActivoId = (int)($_GET['lote_id'] ?? ($lotes[0]['id'] ?? 0));
$loteActivo = null;
$lotePedidos = [];
$loteOperadores = [];
$loteEventos = [];
$kpi = ['pedidos' => 0, 'entregados' => 0, 'en_ruta' => 0, 'cajas' => 0, 'cumplimiento' => 0];
$rutaResumen = [];
$operadorResumen = [];
$filtroEventoTipo = trim((string)($_GET['ev_tipo'] ?? ''));
$filtroEventoDesde = trim((string)($_GET['ev_desde'] ?? ''));
$filtroEventoHasta = trim((string)($_GET['ev_hasta'] ?? ''));
if ($loteActivoId > 0) {
    $stLA = $pdo->prepare("SELECT * FROM logistica_lotes WHERE id=? LIMIT 1");
    $stLA->execute([$loteActivoId]);
    $loteActivo = $stLA->fetch() ?: null;

    $stLP = $pdo->prepare("
        SELECT lp.*, {$folioExprPedidos} AS folio_hex, p.estado AS pedido_estado, c.nombre AS cliente_nombre, o.nombre AS operador_nombre
        FROM logistica_lote_pedidos lp
        JOIN pedidos p ON p.id = lp.pedido_id
        JOIN usuarios c ON c.id = p.cliente_id
        LEFT JOIN usuarios o ON o.id = lp.operador_id
        WHERE lp.lote_id = ?
        ORDER BY lp.prioridad_ruta ASC, lp.id ASC
    ");
    $stLP->execute([$loteActivoId]);
    $lotePedidos = $stLP->fetchAll();

    $stLO = $pdo->prepare("
        SELECT lo.*, u.nombre AS operador_nombre
        FROM logistica_lote_operadores lo
        JOIN usuarios u ON u.id = lo.operador_id
        WHERE lo.lote_id = ?
        ORDER BY u.nombre ASC
    ");
    $stLO->execute([$loteActivoId]);
    $loteOperadores = $stLO->fetchAll();

    $whereEv = ["lote_id = ?"];
    $paramsEv = [$loteActivoId];
    if ($filtroEventoTipo !== '') {
        $whereEv[] = "tipo_evento = ?";
        $paramsEv[] = $filtroEventoTipo;
    }
    if ($filtroEventoDesde !== '') {
        $whereEv[] = "created_at >= ?";
        $paramsEv[] = $filtroEventoDesde . ' 00:00:00';
    }
    if ($filtroEventoHasta !== '') {
        $whereEv[] = "created_at <= ?";
        $paramsEv[] = $filtroEventoHasta . ' 23:59:59';
    }
    $sqlEv = "SELECT * FROM logistica_eventos WHERE " . implode(' AND ', $whereEv) . " ORDER BY id DESC LIMIT 120";
    $stLE = $pdo->prepare($sqlEv);
    $stLE->execute($paramsEv);
    $loteEventos = $stLE->fetchAll();

    $kpi['pedidos'] = count($lotePedidos);
    foreach ($lotePedidos as $row) {
        $kpi['cajas'] += (int)$row['cajas_asignadas'];
        if ($row['estado'] === 'entregado') $kpi['entregados']++;
        if ($row['estado'] === 'en_ruta') $kpi['en_ruta']++;
        $rk = (string)($row['ruta_grupo'] ?: 'ruta-general');
        if (!isset($rutaResumen[$rk])) $rutaResumen[$rk] = ['total' => 0, 'entregados' => 0];
        $rutaResumen[$rk]['total']++;
        if ($row['estado'] === 'entregado') $rutaResumen[$rk]['entregados']++;
        $okey = (int)($row['operador_id'] ?? 0);
        if ($okey > 0) {
            if (!isset($operadorResumen[$okey])) {
                $operadorResumen[$okey] = [
                    'nombre' => (string)($row['operador_nombre'] ?? ('Operador #' . $okey)),
                    'pedidos' => 0,
                    'entregados' => 0,
                    'cajas' => 0,
                    'en_ruta' => 0,
                ];
            }
            $operadorResumen[$okey]['pedidos']++;
            $operadorResumen[$okey]['cajas'] += (int)$row['cajas_asignadas'];
            if ($row['estado'] === 'entregado') $operadorResumen[$okey]['entregados']++;
            if ($row['estado'] === 'en_ruta') $operadorResumen[$okey]['en_ruta']++;
        }
    }
    $kpi['cumplimiento'] = $kpi['pedidos'] > 0 ? (int)round(($kpi['entregados'] / $kpi['pedidos']) * 100) : 0;
    uasort($operadorResumen, static function ($a, $b) {
        return ($b['entregados'] <=> $a['entregados']) ?: ($b['pedidos'] <=> $a['pedidos']);
    });
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Logistica Masiva</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <script src="../assets/js/theme.js"></script>
    <style>
        .lm-grid{display:grid;grid-template-columns:350px 1fr;gap:14px}
        .lm-card{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:14px}
        .lm-title{font-weight:700;font-size:16px;margin:0 0 8px}
        .lm-muted{color:var(--text-muted);font-size:12px}
        .lm-list{max-height:320px;overflow:auto;border:1px solid var(--border);border-radius:10px;padding:8px}
        .lm-row{display:flex;justify-content:space-between;gap:8px;padding:9px 8px;border-bottom:1px solid var(--border);align-items:center}
        .lm-row:last-child{border-bottom:0}
        .lm-row-main{display:flex;align-items:center;gap:10px;min-width:0}
        .lm-row-main input[type="checkbox"]{margin:0}
        .lm-row-text{display:flex;flex-direction:column;min-width:0}
        .lm-row-folio{font-weight:700;color:var(--text-primary);line-height:1.1}
        .lm-row-client{font-size:12px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:170px}
        .lm-badge{font-size:11px;border:1px solid var(--border-focus);padding:2px 8px;border-radius:999px;color:var(--accent)}
        .btn-lm{
            height:40px;
            border-radius:10px;
            border:1px solid rgba(66,153,255,.55);
            background:linear-gradient(135deg,#1d9bff,#5b5cf0);
            color:#eaf4ff;
            font-weight:700;
            padding:0 14px;
            display:inline-flex;
            align-items:center;
            gap:8px;
            text-decoration:none;
            cursor:pointer;
        }
        .btn-lm:hover{filter:brightness(1.05);transform:translateY(-1px)}
        .btn-lm-secondary{
            height:40px;
            border-radius:10px;
            border:1px solid rgba(125,211,252,.4);
            background:rgba(9,23,45,.9);
            color:#cfe9ff;
            font-weight:600;
            padding:0 14px;
            display:inline-flex;
            align-items:center;
            gap:8px;
            text-decoration:none;
            cursor:pointer;
        }
        .lm-table{width:100%;border-collapse:collapse}
        .lm-table th,.lm-table td{padding:8px;border-bottom:1px solid var(--border);font-size:12px;vertical-align:top}
        .lm-table th{color:var(--text-dim);font-size:11px;text-transform:uppercase;letter-spacing:.06em}
        .lm-input{width:100%;background:var(--bg-input);border:1px solid var(--border);color:var(--text-primary);border-radius:8px;padding:8px;font-size:12px}
        .lm-kpi{display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:10px;margin-bottom:10px}
        .lm-kpi-card{border:1px solid var(--border);border-radius:12px;padding:10px;background:var(--card-bg)}
        .lm-kpi-card b{display:block;font-size:22px}
        .lm-kpi-card span{font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em}
        .lm-progress{height:10px;border-radius:999px;background:rgba(255,255,255,.08);overflow:hidden}
        .lm-progress > i{display:block;height:100%;background:linear-gradient(90deg,#22d3ee,#22c55e)}
        .lm-ops{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:10px}
        .lm-op{border:1px solid var(--border);border-radius:12px;padding:10px;background:rgba(255,255,255,.02)}
        .lm-op.ok{border-color:rgba(34,197,94,.45)}
        .lm-op.warn{border-color:rgba(245,158,11,.45)}
        .lm-op.danger{border-color:rgba(239,68,68,.45)}
        .lm-op small{display:block;color:var(--text-muted)}
        .lm-route{display:grid;grid-template-columns:1fr auto auto;gap:8px;align-items:center;padding:8px 0;border-bottom:1px dashed rgba(255,255,255,.08)}
        .lm-route:last-child{border-bottom:0}
        .lm-ev{font-size:12px;padding:6px 0;border-bottom:1px dashed rgba(255,255,255,.08)}
        .lm-ev:last-child{border-bottom:0}
        .lm-mini-table{width:100%;border-collapse:collapse}
        .lm-mini-table th,.lm-mini-table td{font-size:12px;padding:7px;border-bottom:1px solid rgba(255,255,255,.08)}
        .lm-mini-table th{color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;font-size:10px}
        @media (max-width:1100px){.lm-grid{grid-template-columns:1fr}.lm-kpi{grid-template-columns:repeat(2,minmax(140px,1fr))}}
    </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header"><div class="sidebar-logo"><div class="logo-icon-sm"><i class="fa-solid fa-hexagon-nodes"></i></div><span class="logo-text-sm">Nexus<strong>Panel</strong></span></div></div>
    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr((string)($usuario['nombre'] ?? 'M'),0,1)) ?></div>
        <div class="user-info"><span class="user-name"><?= htmlspecialchars((string)($usuario['nombre'] ?? 'Manager')) ?></span><span class="user-role role-admin"><i class="fa-solid fa-user-tie"></i> <?= htmlspecialchars($nombreRol) ?></span></div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="<?= $esInventario ? 'inventario.php?vista=inicio' : 'dashboard.php' ?>" class="nav-item"><i class="fa-solid fa-chart-line"></i><span>Inicio</span></a>
        <?php if ($esAdmin): ?><a href="usuarios.php" class="nav-item"><i class="fa-solid fa-users"></i><span>Usuarios</span></a><?php endif; ?>
        <a href="productos.php" class="nav-item"><i class="fa-solid fa-flask-vial"></i><span>Productos e inventario</span></a>
        <a href="pedidos.php" class="nav-item"><i class="fa-solid fa-receipt"></i><span>Pedidos</span></a>
        <a href="reportes_ventas.php" class="nav-item"><i class="fa-solid fa-file-excel"></i><span>Reportes</span></a>
        
        <a href="mensajes.php" class="nav-item"><i class="fa-solid fa-comments"></i><span>Mensajes internos</span></a>
        <a href="../whatsapp.php" class="nav-item"><i class="fa-brands fa-whatsapp"></i><span>WhatsApp</span></a>

        <div class="nav-section-label">Cuenta</div>
        <a href="../logout.php" class="nav-item nav-logout"><i class="fa-solid fa-right-from-bracket"></i><span>Cerrar sesion</span></a>
    </nav>
</aside>

<main class="main-content">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenu"><i class="fa-solid fa-bars"></i></button>
            <div class="breadcrumb-custom"><span>NexusPanel</span><i class="fa-solid fa-chevron-right"></i><span class="active">Logistica Masiva</span></div>
        </div>
        <div class="topbar-right">
            <button class="topbar-btn theme-toggle" onclick="toggleTheme()" title="Cambiar tema">
                <i class="fa-solid fa-moon theme-toggle-icon"></i>
            </button>
            <div class="topbar-date" id="topbarDate"></div>
        </div>
    </header>

    <div class="content-area">
        <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

        <div class="lm-grid">
            <section class="lm-card">
                <h3 class="lm-title">Nuevo embarque masivo</h3>
                <p class="lm-muted">Planea embarques grandes, sugiere rutas y asigna operadores.</p>
                <form method="post">
                    <input type="hidden" name="action" value="crear_lote">
                    <div style="display:grid;gap:8px;margin-bottom:10px;">
                        <select class="lm-input" name="modo_envio">
                            <option value="flotilla" selected>Flotilla propia</option>
                            <option value="paqueteria">Paqueteria</option>
                        </select>
                        <input class="lm-input" name="paqueteria" placeholder="Paqueteria (opcional)">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                            <input class="lm-input" type="number" step="0.01" min="0" name="costo_pct" placeholder="% costo">
                            <input class="lm-input" type="number" step="0.01" min="0" name="costo_por_caja" placeholder="Costo por caja">
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                            <input class="lm-input" type="date" name="fecha_salida_programada">
                            <input class="lm-input" type="date" name="fecha_entrega_estimada">
                        </div>
                        <textarea class="lm-input" name="observaciones" rows="2" placeholder="Observaciones logisticas"></textarea>
                    </div>
                    <div class="lm-list">
                        <?php foreach ($pedidosDisponibles as $p): ?>
                            <label class="lm-row">
                                <span class="lm-row-main">
                                    <input type="checkbox" name="pedido_ids[]" value="<?= (int)$p['id'] ?>">
                                    <span class="lm-row-text">
                                        <span class="lm-row-folio">#<?= htmlspecialchars((string)($p['folio_hex'] ?: dechex((int)$p['id']))) ?></span>
                                        <span class="lm-row-client"><?= htmlspecialchars((string)$p['cliente_nombre']) ?></span>
                                    </span>
                                </span>
                                <span class="lm-badge"><?= (int)$p['total_items'] ?> und</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn-lm mt-2" type="submit"><i class="fa-solid fa-wand-magic-sparkles"></i> Crear lote sugerido</button>
                </form>
            </section>

            <section class="lm-card">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                    <h3 class="lm-title" style="margin:0;">Lotes y operacion</h3>
                    <?php if (!empty($lotes)): ?>
                        <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;">
                            <select class="lm-input" name="lote_id" onchange="this.form.submit()" style="min-width:260px;">
                                <?php foreach ($lotes as $l): ?>
                                    <option value="<?= (int)$l['id'] ?>" <?= (int)$l['id'] === $loteActivoId ? 'selected' : '' ?>><?= htmlspecialchars((string)$l['folio']) ?> · <?= htmlspecialchars((string)$l['estado']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($loteActivoId > 0): ?>
                                <a class="btn-lm-secondary" href="logistica_masiva.php?lote_id=<?= $loteActivoId ?>&csv=<?= $loteActivoId ?>"><i class="fa-solid fa-file-csv"></i> CSV</a>
                                <a class="btn-lm-secondary" href="logistica_masiva.php?lote_id=<?= $loteActivoId ?>&json=<?= $loteActivoId ?>"><i class="fa-solid fa-code"></i> JSON</a>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($loteActivo): ?>
                    <div class="lm-kpi">
                        <div class="lm-kpi-card"><span>Pedidos</span><b><?= $kpi['pedidos'] ?></b></div>
                        <div class="lm-kpi-card"><span>En ruta</span><b><?= $kpi['en_ruta'] ?></b></div>
                        <div class="lm-kpi-card"><span>Entregados</span><b><?= $kpi['entregados'] ?></b></div>
                        <div class="lm-kpi-card"><span>Cajas</span><b><?= $kpi['cajas'] ?></b></div>
                    </div>
                    <div style="margin-bottom:12px;">
                        <div style="display:flex;justify-content:space-between;"><span class="lm-muted">Cumplimiento del lote</span><span class="lm-muted"><?= $kpi['cumplimiento'] ?>%</span></div>
                        <div class="lm-progress"><i style="width:<?= $kpi['cumplimiento'] ?>%"></i></div>
                    </div>

                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
                        <form method="post" style="display:flex;gap:8px;">
                            <input type="hidden" name="action" value="actualizar_estado_lote">
                            <input type="hidden" name="lote_id" value="<?= (int)$loteActivo['id'] ?>">
                            <select name="estado" class="lm-input">
                                <?php foreach (['borrador','pendiente_aceptacion','aceptado','en_embarque','cerrado','cancelado'] as $st): ?>
                                    <option value="<?= $st ?>" <?= $st === $loteActivo['estado'] ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn-lm-secondary" onclick="return confirm('Confirmar cambio de estado del lote?');">Actualizar estado</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="action" value="auto_rebalancear">
                            <input type="hidden" name="lote_id" value="<?= (int)$loteActivo['id'] ?>">
                            <button class="btn-lm" onclick="return confirm('Aplicar rebalanceo automatico?');"><i class="fa-solid fa-shuffle"></i> Rebalancear</button>
                        </form>
                    </div>

                    <h4 class="lm-title">Resumen por ruta</h4>
                    <div class="lm-card" style="padding-top:6px;">
                        <?php foreach ($rutaResumen as $ruta => $rz): ?>
                            <div class="lm-route">
                                <strong><?= htmlspecialchars($ruta) ?></strong>
                                <span class="lm-badge"><?= (int)$rz['entregados'] ?>/<?= (int)$rz['total'] ?> entregados</span>
                                <span class="lm-muted"><?= (int)round(((int)$rz['entregados'] / max(1, (int)$rz['total'])) * 100) ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h4 class="lm-title mt-3">Operadores del lote</h4>
                    <div class="lm-ops mb-3">
                        <?php foreach ($loteOperadores as $lo): ?>
                            <?php
                                $cap = max(1, (int)$lo['capacidad_cajas']);
                                $used = (int)$lo['cajas_asignadas'];
                                $pct = (int)round(($used / $cap) * 100);
                                $cls = $pct >= 95 ? 'danger' : ($pct >= 75 ? 'warn' : 'ok');
                                $editable = !in_array((string)$loteActivo['estado'], ['cerrado','cancelado'], true);
                            ?>
                            <div class="lm-op <?= $cls ?>">
                                <h4 style="margin:0 0 6px;"><?= htmlspecialchars((string)$lo['operador_nombre']) ?></h4>
                                <small>Estado: <?= htmlspecialchars((string)$lo['estado']) ?></small>
                                <small>Carga: <?= $used ?>/<?= $cap ?> cajas (<?= $pct ?>%)</small>
                                <small>Salida: <?= htmlspecialchars((string)($lo['hora_salida'] ?? '-')) ?></small>
                                <small>Cierre: <?= htmlspecialchars((string)($lo['hora_entrega'] ?? '-')) ?></small>
                                <?php if ($editable): ?>
                                <details style="margin-top:8px;">
                                    <summary style="cursor:pointer;font-size:12px;color:#7dd3fc;">Checklist salida</summary>
                                    <form method="post" style="display:grid;gap:6px;margin-top:6px;">
                                        <input type="hidden" name="action" value="iniciar_ruta_operador">
                                        <input type="hidden" name="lote_id" value="<?= (int)$loteActivo['id'] ?>">
                                        <input type="hidden" name="operador_id" value="<?= (int)$lo['operador_id'] ?>">
                                        <label><input type="checkbox" name="inventario_confirmado"> Inventario confirmado</label>
                                        <label><input type="checkbox" name="cajas_verificadas"> Cajas verificadas</label>
                                        <label><input type="checkbox" name="documentacion_ok"> Documentacion ok</label>
                                        <label><input type="checkbox" name="combustible_ok"> Combustible ok</label>
                                        <input class="lm-input" name="nota_salida" placeholder="Nota salida">
                                        <button class="btn-lm" onclick="return confirm('Iniciar ruta para este operador?');">Iniciar ruta</button>
                                    </form>
                                </details>
                                <details style="margin-top:8px;">
                                    <summary style="cursor:pointer;font-size:12px;color:#7dd3fc;">Checklist cierre</summary>
                                    <form method="post" style="display:grid;gap:6px;margin-top:6px;">
                                        <input type="hidden" name="action" value="cerrar_ruta_operador">
                                        <input type="hidden" name="lote_id" value="<?= (int)$loteActivo['id'] ?>">
                                        <input type="hidden" name="operador_id" value="<?= (int)$lo['operador_id'] ?>">
                                        <label><input type="checkbox" name="evidencias_subidas"> Evidencias subidas</label>
                                        <label><input type="checkbox" name="entregas_confirmadas"> Entregas confirmadas</label>
                                        <label><input type="checkbox" name="devoluciones_registradas"> Devoluciones registradas</label>
                                        <input class="lm-input" name="nota_cierre" placeholder="Nota cierre">
                                        <button class="btn-lm-secondary" onclick="return confirm('Cerrar ruta para este operador?');">Cerrar ruta</button>
                                    </form>
                                </details>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h4 class="lm-title">Rendimiento por operador (Fase 3)</h4>
                    <div class="lm-card" style="padding-top:6px;margin-bottom:12px;">
                        <table class="lm-mini-table">
                            <thead>
                            <tr><th>Operador</th><th>Pedidos</th><th>En ruta</th><th>Entregados</th><th>Cajas</th><th>Cumplimiento</th></tr>
                            </thead>
                            <tbody>
                            <?php if (empty($operadorResumen)): ?>
                                <tr><td colspan="6" class="lm-muted">Sin datos de operadores para este lote.</td></tr>
                            <?php else: ?>
                                <?php foreach ($operadorResumen as $or): ?>
                                    <?php $pctOp = (int)round(((int)$or['entregados'] / max(1, (int)$or['pedidos'])) * 100); ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)$or['nombre']) ?></td>
                                        <td><?= (int)$or['pedidos'] ?></td>
                                        <td><?= (int)$or['en_ruta'] ?></td>
                                        <td><?= (int)$or['entregados'] ?></td>
                                        <td><?= (int)$or['cajas'] ?></td>
                                        <td><?= $pctOp ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <h4 class="lm-title">Pedidos del lote</h4>
                    <form method="post">
                        <input type="hidden" name="action" value="guardar_asignaciones">
                        <input type="hidden" name="lote_id" value="<?= (int)$loteActivo['id'] ?>">
                        <div style="overflow:auto;">
                            <table class="lm-table">
                                <thead>
                                <tr><th>Prio</th><th>Pedido</th><th>Cliente</th><th>Cajas</th><th>Asignadas</th><th>Operador</th><th>Ruta</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($lotePedidos as $lp): ?>
                                    <tr>
                                        <td><input class="lm-input" type="number" min="1" name="fila[<?= (int)$lp['id'] ?>][prioridad_ruta]" value="<?= (int)$lp['prioridad_ruta'] ?>"></td>
                                        <td>#<?= htmlspecialchars((string)($lp['folio_hex'] ?: dechex((int)$lp['pedido_id']))) ?><br><span class="lm-muted"><?= htmlspecialchars((string)$lp['estado']) ?></span></td>
                                        <td><?= htmlspecialchars((string)$lp['cliente_nombre']) ?></td>
                                        <td><?= (int)$lp['cajas_sugeridas'] ?></td>
                                        <td><input class="lm-input" type="number" min="1" name="fila[<?= (int)$lp['id'] ?>][cajas_asignadas]" value="<?= (int)$lp['cajas_asignadas'] ?>"></td>
                                        <td>
                                            <select class="lm-input" name="fila[<?= (int)$lp['id'] ?>][operador_id]">
                                                <option value="0">Sin asignar</option>
                                                <?php foreach ($ops as $op): ?>
                                                    <option value="<?= (int)$op['id'] ?>" <?= (int)$op['id'] === (int)$lp['operador_id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$op['nombre']) ?> (cap <?= capacidadCajasOperador($op) ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input class="lm-input" name="fila[<?= (int)$lp['id'] ?>][ruta_grupo]" value="<?= htmlspecialchars((string)($lp['ruta_grupo'] ?? 'ruta-general')) ?>"></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button class="btn-lm mt-2" onclick="return confirm('Guardar ajustes manuales del lote?');"><i class="fa-solid fa-floppy-disk"></i> Guardar ajustes</button>
                    </form>

                    <h4 class="lm-title mt-3">Auditoria del lote</h4>
                    <form method="get" style="display:grid;grid-template-columns:1fr 140px 140px 110px;gap:8px;margin-bottom:10px;">
                        <input type="hidden" name="lote_id" value="<?= (int)$loteActivoId ?>">
                        <input class="lm-input" name="ev_tipo" placeholder="tipo_evento (ej. inicio_ruta)" value="<?= htmlspecialchars($filtroEventoTipo) ?>">
                        <input class="lm-input" type="date" name="ev_desde" value="<?= htmlspecialchars($filtroEventoDesde) ?>">
                        <input class="lm-input" type="date" name="ev_hasta" value="<?= htmlspecialchars($filtroEventoHasta) ?>">
                        <button class="btn-secondary-custom" type="submit">Filtrar</button>
                    </form>
                    <?php foreach ($loteEventos as $ev): ?>
                        <div class="lm-ev"><strong><?= htmlspecialchars((string)$ev['tipo_evento']) ?></strong> · <?= htmlspecialchars((string)$ev['detalle']) ?><br><span class="lm-muted"><?= htmlspecialchars((string)$ev['created_at']) ?> · user #<?= (int)($ev['usuario_id'] ?? 0) ?></span></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="lm-card" style="padding:18px;">
                        <h4 class="lm-title" style="margin-bottom:6px;">Aun no hay lotes creados</h4>
                        <p class="lm-muted" style="margin:0;">Selecciona pedidos en el panel izquierdo y presiona <strong>Crear lote sugerido</strong>.</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>
<script src="../assets/js/dashboard.js"></script>
</body>
</html>

