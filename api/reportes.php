<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireAuth();

$action = $_GET['action'] ?? 'resumen';
$u = usuarioActual();

if (!esGestion() && !esOperador()) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

switch ($action) {
    case 'excel_inventario':
        $rows = $pdo->query("
            SELECT
                p.id,
                p.nombre,
                p.precio,
                p.stock AS stock_disponible,
                p.unidad_medida,
                p.fecha_ingreso,
                p.fecha_caducidad
            FROM productos p
            ORDER BY p.nombre ASC
        ")->fetchAll();

        $filename = 'inventario_' . date('Ymd_His') . '.csv';
        $headers = ['ID', 'Producto', 'Precio', 'Stock disponible', 'Unidad', 'Fecha ingreso', 'Fecha caducidad'];
        descargarCsv($filename, $headers, $rows, function ($r) {
            return [
                $r['id'],
                $r['nombre'],
                $r['precio'],
                $r['stock_disponible'],
                $r['unidad_medida'],
                $r['fecha_ingreso'] ?? '',
                $r['fecha_caducidad'] ?? ''
            ];
        });

    case 'excel_pedidos':
        $rows = $pdo->query("
            SELECT
                p.id,
                p.folio_hex,
                p.created_at,
                p.estado,
                p.total,
                c.nombre AS cliente,
                o.nombre AS operador,
                p.fecha_requerida,
                p.fecha_programada,
                p.transporte_linea,
                GROUP_CONCAT(CONCAT(pi.cantidad, 'x ', pr.nombre) ORDER BY pr.nombre SEPARATOR ' | ') AS productos
            FROM pedidos p
            JOIN usuarios c ON c.id = p.cliente_id
            LEFT JOIN usuarios o ON o.id = p.operador_id
            LEFT JOIN pedido_items pi ON pi.pedido_id = p.id
            LEFT JOIN productos pr ON pr.id = pi.producto_id
            GROUP BY p.id
            ORDER BY p.created_at DESC
        ")->fetchAll();

        $filename = 'pedidos_' . date('Ymd_His') . '.csv';
        $headers = ['Pedido', 'Fecha', 'Productos', 'Cliente', 'Estatus', 'Operador', 'Total', 'Fecha requerida', 'Fecha programada', 'Transporte'];
        descargarCsv($filename, $headers, $rows, function ($r) {
            return [
                $r['folio_hex'] ?: strtoupper(dechex((int)$r['id'])),
                $r['created_at'],
                $r['productos'] ?? '',
                $r['cliente'],
                $r['estado'],
                $r['operador'] ?? '',
                $r['total'],
                $r['fecha_requerida'] ?? '',
                $r['fecha_programada'] ?? '',
                $r['transporte_linea'] ?? ''
            ];
        });

    case 'excel_ventas':
        $rows = $pdo->query("
            SELECT
                DATE(p.created_at) AS fecha,
                COUNT(*) AS pedidos,
                SUM(p.total) AS total_ventas,
                SUM(CASE WHEN p.estado = 'entregado' THEN p.total ELSE 0 END) AS total_entregado
            FROM pedidos p
            GROUP BY DATE(p.created_at)
            ORDER BY fecha DESC
        ")->fetchAll();

        $filename = 'ventas_' . date('Ymd_His') . '.csv';
        $headers = ['Fecha', 'Pedidos', 'Total ventas', 'Total entregado'];
        descargarCsv($filename, $headers, $rows, function ($r) {
            return [$r['fecha'], $r['pedidos'], $r['total_ventas'], $r['total_entregado']];
        });

    case 'resumen':
    default:
        $resumen = [
            'usuarios' => (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn(),
            'clientes' => (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='cliente'")->fetchColumn(),
            'productos' => (int)$pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn(),
            'pedidos_total' => (int)$pdo->query("SELECT COUNT(*) FROM pedidos")->fetchColumn(),
            'pedidos_activos' => (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado IN ('pendiente','aceptado','en_camino')")->fetchColumn(),
            'ventas_total' => (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM pedidos")->fetchColumn(),
            'entregas_completas' => (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado='entregado'")->fetchColumn(),
            'inventario_bajo' => (int)$pdo->query("SELECT COUNT(*) FROM productos WHERE activo=1 AND stock <= 20")->fetchColumn(),
        ];
        jsonResponse($resumen);
}

function descargarCsv(string $filename, array $headers, array $rows, callable $mapper): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $mapper($row));
    }
    fclose($out);
    exit;
}
