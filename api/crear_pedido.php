<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!estaLogueado()) {
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['error' => 'Datos invalidos']);
    exit;
}

$itemsRaw = $data['items'] ?? [];
$addrMode = $data['addr_mode'] ?? 'perfil';
$otraDireccion = $data['otra_direccion'] ?? '';
$tipoPedido = 'formal';
$fechaRequeridaRaw = '';
$notas = trim((string)($data['notas'] ?? ''));
$u = usuarioActual();

if (empty($itemsRaw) || !is_array($itemsRaw)) {
    echo json_encode(['error' => 'Carrito vacio']);
    exit;
}

$items = [];
foreach ($itemsRaw as $row) {
    $pid = (int)($row['id'] ?? 0);
    $qty = (int)($row['qty'] ?? 0);
    if ($pid <= 0 || $qty <= 0) continue;

    $ajuste = trim((string)($row['ajuste'] ?? ''));
    if ($ajuste !== '') {
        $ajuste = mb_substr($ajuste, 0, 255);
    } else {
        $ajuste = null;
    }

    if (!isset($items[$pid])) {
        $items[$pid] = [
            'id' => $pid,
            'qty' => 0,
            'ajuste' => $ajuste,
        ];
    }
    $items[$pid]['qty'] += $qty;
    if ($ajuste !== null) {
        $items[$pid]['ajuste'] = $ajuste;
    }
}

if (empty($items)) {
    echo json_encode(['error' => 'No hay productos validos en el pedido']);
    exit;
}

$fechaRequerida = null;

if ($notas !== '') {
    $notas = mb_substr($notas, 0, 1000);
} else {
    $notas = null;
}

$domicilio = ($addrMode === 'otra' && $otraDireccion) ? $otraDireccion : ($u['domicilio'] ?? '');
$lat = $u['lat'] ?? null;
$lng = $u['lng'] ?? null;

try {
    $pdo->beginTransaction();

    $productIds = array_keys($items);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stProd = $pdo->prepare("\n        SELECT id, nombre, precio, stock, activo, cliente_ajustable\n        FROM productos\n        WHERE id IN ($placeholders)\n        FOR UPDATE\n    ");
    $stProd->execute($productIds);
    $rows = $stProd->fetchAll();

    $productos = [];
    foreach ($rows as $r) {
        $productos[(int)$r['id']] = $r;
    }

    $total = 0.0;
    $erroresStock = [];

    foreach ($items as $pid => $item) {
        if (!isset($productos[$pid])) {
            $erroresStock[] = "Producto #$pid no encontrado";
            continue;
        }

        $pr = $productos[$pid];
        if ((int)$pr['activo'] !== 1) {
            $erroresStock[] = $pr['nombre'] . ' no esta activo';
            continue;
        }

        if ((int)$pr['stock'] < (int)$item['qty']) {
            $erroresStock[] = $pr['nombre'] . ' sin stock suficiente (disponible: ' . (int)$pr['stock'] . ')';
            continue;
        }

        $total += ((float)$pr['precio'] * (int)$item['qty']);
    }

    if (!empty($erroresStock)) {
        throw new RuntimeException('Inventario insuficiente: ' . implode(' | ', $erroresStock));
    }

    $operadorAsignado = buscarOperadorCercano(
        $pdo,
        $lat !== null ? (float)$lat : null,
        $lng !== null ? (float)$lng : null
    );
    $estadoInicial = $operadorAsignado ? 'aceptado' : 'pendiente';
    $operadorId = $operadorAsignado ? (int)$operadorAsignado['id'] : null;
    $hasFolioHex = columnExists($pdo, 'pedidos', 'folio_hex');
    $folioHex = generarFolioHex($pdo);
    $fechaPedido = fechaMysqlAhora();

    if ($hasFolioHex) {
        $pdo->prepare("\n            INSERT INTO pedidos (\n                folio_hex, cliente_id, operador_id, estado, tipo_pedido, total, lat_entrega, lng_entrega, domicilio_entrega, fecha_requerida, notas, created_at, updated_at\n            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n        ")->execute([
            $folioHex,
            (int)$u['usuario_id'],
            $operadorId,
            $estadoInicial,
            $tipoPedido,
            $total,
            $lat,
            $lng,
            $domicilio,
            $fechaRequerida,
            $notas,
            $fechaPedido,
            $fechaPedido,
        ]);
    } else {
        $pdo->prepare("\n            INSERT INTO pedidos (\n                cliente_id, operador_id, estado, tipo_pedido, total, lat_entrega, lng_entrega, domicilio_entrega, fecha_requerida, notas, created_at, updated_at\n            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n        ")->execute([
            (int)$u['usuario_id'],
            $operadorId,
            $estadoInicial,
            $tipoPedido,
            $total,
            $lat,
            $lng,
            $domicilio,
            $fechaRequerida,
            $notas,
            $fechaPedido,
            $fechaPedido,
        ]);
    }

    $pedidoId = (int)$pdo->lastInsertId();
    if (!$hasFolioHex) {
        $folioHex = strtoupper(dechex($pedidoId));
    }

    $stItem = $pdo->prepare("\n        INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unit, ajuste_cliente)\n        VALUES (?, ?, ?, ?, ?)\n    ");
    $stStock = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");

    foreach ($items as $item) {
        $pid = (int)$item['id'];
        $pr = $productos[$pid];
        $stockAntes = (int)$pr['stock'];
        $stockNuevo = $stockAntes - (int)$item['qty'];
        $ajuste = ((int)$pr['cliente_ajustable'] === 1) ? $item['ajuste'] : null;

        $stItem->execute([
            $pedidoId,
            $pid,
            (int)$item['qty'],
            (float)$pr['precio'],
            $ajuste,
        ]);

        $stStock->execute([(int)$item['qty'], $pid]);
        registrarMovimientoInventario(
            $pdo,
            $pid,
            'salida',
            (int)$item['qty'],
            $stockAntes,
            $stockNuevo,
            'pedido',
            $pedidoId,
            'Descuento por creacion de pedido',
            (int)$u['usuario_id']
        );
        $productos[$pid]['stock'] = $stockNuevo;
    }

    recalcularHistorialCliente($pdo, (int)$u['usuario_id']);
    registrarHistorialPedido($pdo, $pedidoId, 'pendiente', (int)$u['usuario_id'], 'Pedido creado por cliente');
    if ($estadoInicial === 'aceptado' && $operadorId !== null) {
        registrarHistorialPedido(
            $pdo,
            $pedidoId,
            'aceptado',
            $operadorId,
            'Asignacion automatica por cercania en km'
        );
        $pdo->prepare("INSERT INTO chat (pedido_id,usuario_id,mensaje) VALUES (?,?,?)")
            ->execute([$pedidoId, $operadorId, 'He aceptado tu pedido. En breve estare en camino.']);
    }
    registrarNotificacionesEstandarPedido(
        $pdo,
        $pedidoId,
        (int)$u['usuario_id'],
        'pedido_creado',
        'Tu pedido fue creado y esta en revision.',
        [
            'tipo_pedido' => $tipoPedido,
            'fecha_requerida' => $fechaRequerida,
            'total' => $total
        ]
    );
    registrarAuditoria(
        $pdo,
        (int)$u['usuario_id'],
        (string)($u['rol'] ?? 'cliente'),
        'pedidos',
        'crear_pedido',
        'pedido',
        $pedidoId,
        'Pedido creado con ' . count($items) . ' productos'
    );

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'pedido_id' => $pedidoId,
        'folio_hex' => $folioHex,
        'tipo_pedido' => $tipoPedido,
        'estado' => $estadoInicial,
        'operador_id' => $operadorId,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}

