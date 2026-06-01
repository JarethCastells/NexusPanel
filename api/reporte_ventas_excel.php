<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireGestion();
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$periodo = normalizarPeriodo($_GET['periodo'] ?? 'mes');
$anio = max(2020, min(2100, (int)($_GET['anio'] ?? date('Y'))));
$mes = max(1, min(12, (int)($_GET['mes'] ?? date('n'))));
$semana = max(1, min(53, (int)($_GET['semana'] ?? date('W'))));
$estado = normalizarEstado($_GET['estado'] ?? 'todos');
$productoId = max(0, (int)($_GET['producto_id'] ?? 0));
$linea = trim((string)($_GET['linea'] ?? ''));

[$inicio, $fin, $labelPeriodo] = rangoReporteVentasExcel($periodo, $anio, $mes, $semana);
$prev = rangoAnteriorReporteVentasExcel($periodo, $inicio, $fin);
$ventas = obtenerVentasReporteExcel($pdo, $inicio, $fin, $estado, $productoId, $linea);
$ventasPrevias = obtenerVentasReporteExcel($pdo, $prev[0], $prev[1], $estado, $productoId, $linea);
$resumen = resumirVentasExcel($ventas, $ventasPrevias);
$logistica = datosLogisticaDesdeBdExcel($pdo);
$recomendaciones = recomendacionesReporteExcel($resumen, $logistica);

registrarHistorialReporteExcel($pdo, [
    'tipo' => 'ventas_logistica',
    'periodo' => $periodo,
    'inicio' => $inicio,
    'fin' => $fin,
    'filtros' => compact('anio', 'mes', 'semana', 'estado', 'productoId', 'linea'),
]);
$historialReportes = obtenerHistorialReportesExcel($pdo);

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('NexusPanel')
    ->setTitle('Reporte Gerencial de Ventas y Logistica')
    ->setSubject($labelPeriodo);

$resumenSheet = $spreadsheet->getActiveSheet();
$resumenSheet->setTitle('Resumen Ejecutivo');
crearResumenSheet($resumenSheet, $resumen, $labelPeriodo, compact('periodo', 'anio', 'mes', 'semana', 'estado', 'linea'));

$ventasSheet = $spreadsheet->createSheet();
$ventasSheet->setTitle('Ventas Detalladas');
crearVentasSheet($ventasSheet, $ventas, $labelPeriodo);

$productosSheet = $spreadsheet->createSheet();
$productosSheet->setTitle('Productos');
crearProductosSheet($productosSheet, $resumen['productos']);

$proveedoresSheet = $spreadsheet->createSheet();
$proveedoresSheet->setTitle('Logistica Proveedores');
crearTablaSheet($proveedoresSheet, 'Logistica Proveedores', [
    'Linea', 'Unidad', 'Caja', 'Capacidad kg', 'Peso minimo', 'Mercancia', 'Operacion', 'Horario carga', 'Cita', 'Rastreo', 'Prioridad'
], $logistica['proveedores']);

$rutasSheet = $spreadsheet->createSheet();
$rutasSheet->setTitle('Rutas y Costos');
crearTablaSheet($rutasSheet, 'Rutas y Costos', [
    'Linea', 'Origen', 'Destino', 'Ruta', 'Frecuencia', 'Tipo cobro', 'Tarifa base', 'Recargos', 'SLA', 'Cumplimiento'
], $logistica['rutas']);

$analisisSheet = $spreadsheet->createSheet();
$analisisSheet->setTitle('Analisis Recomendaciones');
crearTablaSheet($analisisSheet, 'Analisis Recomendaciones', [
    'Tipo', 'Hallazgo inteligente', 'Dato base', 'Senal adicional', 'Recomendacion', 'Prioridad', 'Regla aplicada'
], $recomendaciones);

$historialSheet = $spreadsheet->createSheet();
$historialSheet->setTitle('Historial Reportes');
crearTablaSheet($historialSheet, 'Historial Reportes', [
    'Generado', 'Usuario', 'Periodo', 'Inicio', 'Fin', 'Filtros'
], $historialReportes);

$datosGraficasSheet = $spreadsheet->createSheet();
$datosGraficasSheet->setTitle('Datos Graficas');
$datosGraficasSheet->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);

agregarGraficas($resumenSheet, $productosSheet, $datosGraficasSheet, $ventas, $resumen['productos']);
prepararVistaFinalExcel($spreadsheet);

$spreadsheet->setActiveSheetIndex(0);
$spreadsheet->getActiveSheet()->setSelectedCell('A1');
$filename = 'reporte_ventas_logistica_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true);
$writer->save('php://output');
exit;

function normalizarPeriodo(string $periodo): string {
    return in_array($periodo, ['semana', 'mes', 'anio'], true) ? $periodo : 'mes';
}

function normalizarEstado(string $estado): string {
    $validos = ['todos', 'pendiente', 'aceptado', 'en_camino', 'entregado', 'cancelado'];
    return in_array($estado, $validos, true) ? $estado : 'todos';
}

function rangoReporteVentasExcel(string $periodo, int $anio, int $mes, int $semana): array {
    if ($periodo === 'semana') {
        $dt = new DateTime();
        $dt->setISODate($anio, $semana, 1)->setTime(0, 0, 0);
        $fin = (clone $dt)->modify('+6 days')->setTime(23, 59, 59);
        return [$dt->format('Y-m-d H:i:s'), $fin->format('Y-m-d H:i:s'), 'Semana ' . $semana . ' de ' . $anio];
    }
    if ($periodo === 'anio') {
        return [$anio . '-01-01 00:00:00', $anio . '-12-31 23:59:59', 'Anio ' . $anio];
    }
    $inicio = sprintf('%04d-%02d-01 00:00:00', $anio, $mes);
    return [$inicio, date('Y-m-t 23:59:59', strtotime($inicio)), 'Mes ' . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . '/' . $anio];
}

function rangoAnteriorReporteVentasExcel(string $periodo, string $inicio, string $fin): array {
    $i = new DateTime($inicio);
    $f = new DateTime($fin);
    $delta = $periodo === 'semana' ? '-7 days' : ($periodo === 'anio' ? '-1 year' : '-1 month');
    $i->modify($delta);
    $f->modify($delta);
    return [$i->format('Y-m-d H:i:s'), $f->format('Y-m-d H:i:s')];
}

function obtenerVentasReporteExcel(PDO $pdo, string $inicio, string $fin, string $estado, int $productoId, string $linea): array {
    $folioExpr = columnExists($pdo, 'pedidos', 'folio_hex')
        ? "COALESCE(p.folio_hex, LPAD(UPPER(HEX(p.id)), 8, '0'))"
        : "LPAD(UPPER(HEX(p.id)), 8, '0')";
    $transporteExpr = columnExists($pdo, 'pedidos', 'transporte_linea')
        ? "COALESCE(NULLIF(p.transporte_linea, ''), 'Sin asignar')"
        : "'Sin asignar'";
    $rutaExpr = columnExists($pdo, 'pedidos', 'ruta_origen') && columnExists($pdo, 'pedidos', 'ruta_destino')
        ? "COALESCE(NULLIF(CONCAT_WS(' - ', NULLIF(p.ruta_origen, ''), NULLIF(p.ruta_destino, '')), ''), NULLIF(p.domicilio_entrega, ''), 'Ruta no definida')"
        : "COALESCE(NULLIF(p.domicilio_entrega, ''), 'Ruta no definida')";
    $where = ['p.created_at BETWEEN ? AND ?'];
    $params = [$inicio, $fin];
    if ($estado !== 'todos') { $where[] = 'p.estado = ?'; $params[] = $estado; }
    if ($productoId > 0) { $where[] = 'pi.producto_id = ?'; $params[] = $productoId; }
    if ($linea !== '' && columnExists($pdo, 'pedidos', 'transporte_linea')) { $where[] = 'p.transporte_linea = ?'; $params[] = $linea; }

    $sql = "
        SELECT p.id AS pedido_id, {$folioExpr} AS folio,
               p.created_at, p.estado, p.total AS total_pedido, COALESCE(c.nombre, 'Cliente') AS cliente,
               {$transporteExpr} AS transporte_linea,
               {$rutaExpr} AS ruta,
               COALESCE(pr.nombre, 'Sin producto') AS producto, COALESCE(pr.stock, 0) AS stock_actual,
               COALESCE(pi.cantidad, 0) AS cantidad, COALESCE(pi.precio_unit, pr.precio, 0) AS precio_unit,
               COALESCE(pi.cantidad, 0) * COALESCE(pi.precio_unit, pr.precio, 0) AS subtotal
        FROM pedidos p
        LEFT JOIN usuarios c ON c.id = p.cliente_id
        LEFT JOIN pedido_items pi ON pi.pedido_id = p.id
        LEFT JOIN productos pr ON pr.id = pi.producto_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.created_at DESC, p.id DESC";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function resumirVentasExcel(array $rows, array $previousRows): array {
    $pedidos = [];
    $productos = [];
    $prevProductos = [];
    foreach ($previousRows as $r) {
        $producto = (string)$r['producto'];
        $prevProductos[$producto] = ($prevProductos[$producto] ?? 0) + (float)$r['subtotal'];
    }
    foreach ($rows as $r) {
        if (($r['estado'] ?? '') !== 'cancelado') {
            $pedidos[(int)$r['pedido_id']] = (float)$r['total_pedido'];
        }
        $producto = (string)$r['producto'];
        $productos[$producto] ??= ['unidades' => 0, 'total' => 0.0, 'anterior' => $prevProductos[$producto] ?? 0.0, 'variacion' => 0.0, 'decision' => 'Monitorear'];
        $productos[$producto]['unidades'] += (int)$r['cantidad'];
        $productos[$producto]['total'] += (float)$r['subtotal'];
    }
    foreach ($productos as &$p) {
        $p['variacion'] = $p['anterior'] > 0 ? ($p['total'] - $p['anterior']) / $p['anterior'] : ($p['total'] > 0 ? 1 : 0);
        $p['decision'] = $p['variacion'] <= -0.25 ? 'Revisar precio/promocion/disponibilidad' : ($p['variacion'] >= 0.30 ? 'Aumentar stock' : 'Mantener monitoreo');
    }
    unset($p);
    uasort($productos, static fn($a, $b) => $b['total'] <=> $a['total']);
    $totales = array_values($pedidos);
    $total = array_sum($totales);
    $prevTotal = array_sum(array_map(static fn($r) => (float)$r['subtotal'], $previousRows));
    return [
        'total' => $total,
        'pedidos' => count($pedidos),
        'ticket' => count($pedidos) ? $total / count($pedidos) : 0,
        'mayor' => $totales ? max($totales) : 0,
        'menor' => $totales ? min($totales) : 0,
        'variacion' => $prevTotal > 0 ? ($total - $prevTotal) / $prevTotal : 0,
        'productos' => $productos,
    ];
}

function crearResumenSheet($sheet, array $resumen, string $labelPeriodo, array $filtros): void {
    $topProducto = array_key_first($resumen['productos']) ?: 'Sin datos';
    $sheet->fromArray([
        ['NexusPanel | Reporte Gerencial de Ventas y Logistica', null, null, null, null, 'Filtros del reporte'],
        ['Indicador', 'Valor', 'Lectura', 'Accion sugerida', null, 'Filtro', 'Valor'],
        ['Total vendido', $resumen['total'], $labelPeriodo, $resumen['variacion'] < 0 ? 'Revisar causa de baja' : 'Mantener ritmo comercial', null, 'Periodo', $filtros['periodo']],
        ['Numero de ventas', $resumen['pedidos'], 'Pedidos no cancelados', 'Evaluar demanda por producto', null, 'Semana', $filtros['semana']],
        ['Ticket promedio', $resumen['ticket'], 'Venta promedio', 'Comparar por producto', null, 'Mes', $filtros['mes']],
        ['Mayor venta', $resumen['mayor'], 'Pedido mas alto', 'Identificar producto ganador', null, 'Anio', $filtros['anio']],
        ['Menor venta', $resumen['menor'], 'Pedido mas bajo', 'Revisar venta minima viable', null, 'Estado', $filtros['estado']],
        ['Comparacion periodo anterior', $resumen['variacion'], 'Vs periodo anterior', $resumen['variacion'] < 0 ? 'Activar plan de recuperacion' : 'Crecimiento positivo', null, 'Linea', $filtros['linea'] ?: 'Todas'],
        ['Producto mas vendido', $topProducto, '', 'Aumentar stock si mantiene tendencia'],
    ], null, 'A1');
    aplicarFormatoBase($sheet, 'A1:G9');
    $sheet->mergeCells('A1:D1');
    $sheet->mergeCells('F1:G1');
    $sheet->freezePane('A12');
    $sheet->getTabColor()->setARGB('FF002060');
    $sheet->getRowDimension(1)->setRowHeight(28);
    $sheet->getRowDimension(2)->setRowHeight(24);
    aplicarFormatoEncabezado($sheet, 'A2:D2');
    aplicarFormatoEncabezado($sheet, 'F2:G2');
    $sheet->getStyle('A3:D9')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
    $sheet->getStyle('A3:D9')->getFont()->getColor()->setARGB('FF000000');
    $sheet->getStyle('A3:A9')->getFont()->setBold(true)->getColor()->setARGB('FF002060');
    $sheet->getStyle('D3:D9')->getAlignment()->setWrapText(true);
    $sheet->getStyle('F3:G8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFFFF');
    $sheet->getStyle('F3:G8')->getFont()->getColor()->setARGB('FF000000');
    $sheet->getStyle('F3:F8')->getFont()->setBold(true)->getColor()->setARGB('FF002060');
    $sheet->getStyle('A11:Q11')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFF6FF');
    $sheet->setCellValue('A11', 'Dashboard visual');
    $sheet->setCellValue('J11', 'Tendencias y variacion');
    $sheet->getStyle('A11:Q11')->getFont()->setBold(true)->getColor()->setARGB('FF002060');
    $sheet->getStyle('A11:Q11')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK)->getColor()->setARGB('FF4472C4');
    $sheet->getStyle('B3:B7')->getNumberFormat()->setFormatCode('$#,##0.00');
    $sheet->getStyle('B8')->getNumberFormat()->setFormatCode('0.0%');
    $sheet->getStyle('B8')->getFont()->getColor()->setARGB($resumen['variacion'] < 0 ? 'FFB91C1C' : 'FF15803D');
    $widths = [
        'A' => 24, 'B' => 18, 'C' => 24, 'D' => 34, 'E' => 4, 'F' => 18, 'G' => 18,
        'H' => 3, 'I' => 3, 'J' => 18, 'K' => 18, 'L' => 18, 'M' => 18, 'N' => 18, 'O' => 18, 'P' => 18, 'Q' => 18,
    ];
    foreach ($widths as $col => $width) {
        $sheet->getColumnDimension($col)->setWidth($width);
    }
}

function crearVentasSheet($sheet, array $ventas, string $labelPeriodo): void {
    $data = [['Pedido', 'Fecha', 'Cliente', 'Producto', 'Cantidad', 'Precio unitario', 'Subtotal', 'Total pedido', 'Estado', 'Transporte', 'Ruta', 'Stock actual', 'Periodo']];
    foreach ($ventas as $v) {
        $data[] = [$v['folio'], $v['created_at'], $v['cliente'], $v['producto'], (int)$v['cantidad'], (float)$v['precio_unit'], (float)$v['subtotal'], (float)$v['total_pedido'], $v['estado'], $v['transporte_linea'], $v['ruta'], (int)$v['stock_actual'], $labelPeriodo];
    }
    crearTablaSheet($sheet, 'Ventas Detalladas', $data[0], array_slice($data, 1));
    $sheet->getStyle('F:H')->getNumberFormat()->setFormatCode('$#,##0.00');
}

function crearProductosSheet($sheet, array $productos): void {
    $rows = [];
    foreach ($productos as $producto => $p) {
        $rows[] = [$producto, (int)$p['unidades'], (float)$p['total'], (float)$p['anterior'], (float)$p['variacion'], $p['decision']];
    }
    crearTablaSheet($sheet, 'Productos', ['Producto', 'Unidades', 'Venta total', 'Venta anterior', 'Variacion', 'Decision sugerida'], $rows);
    $sheet->getStyle('C:D')->getNumberFormat()->setFormatCode('$#,##0.00');
    $sheet->getStyle('E:E')->getNumberFormat()->setFormatCode('0.0%');
}

function crearTablaSheet($sheet, string $titulo, array $headers, array $rows): void {
    $sheet->setCellValue('A1', $titulo);
    $sheet->fromArray($headers, null, 'A3');
    if ($rows) {
        $sheet->fromArray($rows, null, 'A4');
    }
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
    $lastRow = max(4, count($rows) + 3);
    aplicarFormatoBase($sheet, "A1:{$lastCol}{$lastRow}");
    $sheet->mergeCells("A1:{$lastCol}1");
    $sheet->freezePane('A4');
    $sheet->setAutoFilter("A3:{$lastCol}{$lastRow}");
    $sheet->getTabColor()->setARGB('FF4472C4');
    aplicarFormatoEncabezado($sheet, "A3:{$lastCol}3");
    $sheet->getStyle("A4:{$lastCol}{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFFFF');
    ajustarAnchosTabla($sheet, $headers);
    for ($row = 4; $row <= $lastRow; $row++) {
        if ($row % 2 === 0) {
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
        }
    }
}

function aplicarFormatoBase($sheet, string $range): void {
    $sheet->setShowGridlines(false);
    $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle($range)->getAlignment()->setWrapText(true);
    $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFC7D3E0');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF002060');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
}

function aplicarFormatoEncabezado($sheet, string $range): void {
    $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4472C4');
    $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

function ajustarAnchosTabla($sheet, array $headers): void {
    $defaults = [
        'Pedido' => 14, 'Fecha' => 20, 'Cliente' => 24, 'Producto' => 26, 'Cantidad' => 12,
        'Precio unitario' => 16, 'Subtotal' => 16, 'Total pedido' => 16, 'Estado' => 14,
        'Transporte' => 18, 'Ruta' => 26, 'Stock actual' => 14, 'Periodo' => 18,
        'Hallazgo inteligente' => 42, 'Senal adicional' => 28, 'Recomendacion' => 42,
        'Regla aplicada' => 22, 'Filtros' => 44, 'Linea' => 18, 'Unidad' => 16,
        'Mercancia' => 18, 'Operacion' => 20, 'Tipo cobro' => 22,
    ];
    foreach ($headers as $i => $header) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
        $sheet->getColumnDimension($col)->setAutoSize(false);
        $sheet->getColumnDimension($col)->setWidth($defaults[$header] ?? min(28, max(14, strlen((string)$header) + 5)));
    }
}

function prepararVistaFinalExcel(Spreadsheet $spreadsheet): void {
    foreach ($spreadsheet->getAllSheets() as $sheet) {
        if ($sheet->getSheetState() === Worksheet::SHEETSTATE_VISIBLE) {
            $sheet->setSelectedCell('A1');
            $sheet->getSheetView()->setZoomScale($sheet->getTitle() === 'Resumen Ejecutivo' ? 90 : 100);
            $sheet->getPageSetup()->setFitToWidth(1);
            $sheet->getPageSetup()->setFitToHeight(0);
            $sheet->getPageMargins()->setTop(0.35);
            $sheet->getPageMargins()->setRight(0.25);
            $sheet->getPageMargins()->setLeft(0.25);
            $sheet->getPageMargins()->setBottom(0.35);
        }
    }
}

function agregarGraficas($resumenSheet, $productosSheet, $helperSheet, array $ventas, array $productos): void {
    $productCount = count($productos);
    if ($productCount <= 0) {
        return;
    }
    $end = $productCount + 3;
    crearGraficaBarras(
        $resumenSheet,
        'ventas_productos',
        'Ventas por producto',
        "'Productos'!\$C\$3",
        "'Productos'!\$A\$4:\$A\$$end",
        "'Productos'!\$C\$4:\$C\$$end",
        $productCount,
        'A12',
        'H28'
    );

    $trend = agruparVentasPorCampo($ventas, 'created_at', true);
    escribirSerieDashboard($helperSheet, 'A1', ['Fecha', 'Ventas'], $trend);
    if (count($trend) > 0) {
        $trendEnd = count($trend) + 1;
        crearGraficaLinea(
            $resumenSheet,
            'evolucion_ventas',
            'Evolucion de ventas',
            "'Datos Graficas'!\$B\$1",
            "'Datos Graficas'!\$A\$2:\$A\$$trendEnd",
            "'Datos Graficas'!\$B\$2:\$B\$$trendEnd",
            count($trend),
            'J12',
            'Q28'
        );
    }

    $transportes = agruparVentasPorCampo($ventas, 'transporte_linea');
    escribirSerieDashboard($helperSheet, 'D1', ['Transportista', 'Ventas'], $transportes);
    if (count($transportes) > 0) {
        $transportEnd = count($transportes) + 1;
        crearGraficaBarras(
            $resumenSheet,
            'ventas_transportista',
            'Ventas por transportista',
            "'Datos Graficas'!\$E\$1",
            "'Datos Graficas'!\$D\$2:\$D\$$transportEnd",
            "'Datos Graficas'!\$E\$2:\$E\$$transportEnd",
            count($transportes),
            'A31',
            'H47'
        );
    }

    $variaciones = [];
    foreach ($productos as $producto => $p) {
        $variaciones[] = [$producto, round(((float)$p['variacion']) * 100, 1)];
    }
    escribirSerieDashboard($helperSheet, 'G1', ['Producto', 'Variacion %'], $variaciones);
    if (count($variaciones) > 0) {
        $varEnd = count($variaciones) + 1;
        crearGraficaBarras(
            $resumenSheet,
            'variacion_producto',
            'Variacion vs periodo anterior',
            "'Datos Graficas'!\$H\$1",
            "'Datos Graficas'!\$G\$2:\$G\$$varEnd",
            "'Datos Graficas'!\$H\$2:\$H\$$varEnd",
            count($variaciones),
            'J31',
            'Q47'
        );
    }
}

function agruparVentasPorCampo(array $ventas, string $campo, bool $esFecha = false): array {
    $map = [];
    foreach ($ventas as $venta) {
        if (($venta['estado'] ?? '') === 'cancelado') {
            continue;
        }
        $key = (string)($venta[$campo] ?? 'Sin dato');
        if ($esFecha) {
            $key = substr($key, 0, 10);
        }
        if ($key === '') {
            $key = 'Sin dato';
        }
        $map[$key] = ($map[$key] ?? 0) + (float)($venta['subtotal'] ?? 0);
    }
    if ($esFecha) {
        ksort($map);
    } else {
        arsort($map);
    }
    $rows = [];
    foreach ($map as $key => $total) {
        $rows[] = [$key, $total];
    }
    return $rows;
}

function escribirSerieDashboard($sheet, string $startCell, array $headers, array $rows): void {
    $sheet->fromArray([$headers], null, $startCell);
    if ($rows) {
        [$col, $row] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($startCell);
        $sheet->fromArray($rows, null, $col . ($row + 1));
    }
}

function crearGraficaBarras($sheet, string $name, string $title, string $labelRange, string $categoryRange, string $valueRange, int $count, string $topLeft, string $bottomRight): void {
    $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $labelRange, null, 1)];
    $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $categoryRange, null, $count)];
    $values = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $valueRange, null, $count)];
    $series = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, [0], $labels, $categories, $values);
    $series->setPlotDirection(DataSeries::DIRECTION_COL);
    $chart = new Chart($name, new Title($title), new Legend(Legend::POSITION_RIGHT, null, false), new PlotArea(null, [$series]));
    $chart->setTopLeftPosition($topLeft);
    $chart->setBottomRightPosition($bottomRight);
    $sheet->addChart($chart);
}

function crearGraficaLinea($sheet, string $name, string $title, string $labelRange, string $categoryRange, string $valueRange, int $count, string $topLeft, string $bottomRight): void {
    $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $labelRange, null, 1)];
    $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $categoryRange, null, $count)];
    $values = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $valueRange, null, $count)];
    $series = new DataSeries(DataSeries::TYPE_LINECHART, DataSeries::GROUPING_STANDARD, [0], $labels, $categories, $values);
    $chart = new Chart($name, new Title($title), new Legend(Legend::POSITION_RIGHT, null, false), new PlotArea(null, [$series]));
    $chart->setTopLeftPosition($topLeft);
    $chart->setBottomRightPosition($bottomRight);
    $sheet->addChart($chart);
}

function datosLogisticaDemoExcel(): array {
    return [
        'proveedores' => [
            ['Loxagon', 'Torton', 'Seca', 14000, 500, 'General', 'Lunes a sabado', '08:00-18:00', 'Si', 'Si', 1],
            ['Bisonte', 'Trailer', 'Refrigerada', 28000, 1000, 'Alimentos', 'Lunes a sabado', '08:00-18:00', 'Si', 'Si', 2],
            ['Tres Guerras', 'Rabon', 'Seca', 8000, 300, 'Fragil', 'Lunes a sabado', '08:00-18:00', 'Si', 'No', 3],
            ['Austral', 'Full', 'Plataforma', 45000, 2000, 'General', 'Lunes a sabado', '08:00-18:00', 'Si', 'Si', 4],
        ],
        'rutas' => [
            ['Loxagon', 'CDMX', 'Puebla', 'CDMX - Puebla', 'Diaria', 'Ruta fija + peso', 5200, 750, '24h', '96%'],
            ['Bisonte', 'CDMX', 'Monterrey', 'CDMX - Monterrey', 'Semanal', 'Ruta fija + peso', 23800, 3450, '72h', '89%'],
            ['Tres Guerras', 'CDMX', 'Puebla', 'CDMX - Puebla', 'Diaria', 'Ruta fija', 5050, 710, '24h', '92%'],
            ['Austral', 'CDMX', 'Cancun', 'CDMX - Cancun', 'Bajo demanda', 'Ruta fija + dimension', 35400, 5050, '96h', '87%'],
        ],
    ];
}

function datosLogisticaDesdeBdExcel(PDO $pdo): array {
    try {
        $proveedores = $pdo->query("
            SELECT
                l.nombre,
                COALESCE(u.tipo, 'Unidad no definida') AS tipo,
                COALESCE(u.tipo_caja, 'Caja no definida') AS tipo_caja,
                COALESCE(u.capacidad_kg, 0) AS capacidad_kg,
                COALESCE(u.peso_minimo, 0) AS peso_minimo,
                COALESCE(u.mercancia_permitida, 'General') AS mercancia_permitida,
                COALESCE(l.dias_operacion, 'Lunes a sabado') AS dias_operacion,
                COALESCE(l.horario_carga, '08:00-18:00') AS horario_carga,
                CASE WHEN COALESCE(l.requiere_cita, 1) = 1 THEN 'Si' ELSE 'No' END AS requiere_cita,
                CASE WHEN COALESCE(l.rastreo_tiempo_real, 0) = 1 THEN 'Si' ELSE 'No' END AS rastreo,
                COALESCE(l.prioridad, 99) AS prioridad
            FROM logistica_lineas l
            LEFT JOIN logistica_unidades u ON u.linea_id = l.id
            WHERE COALESCE(l.activo, 1) = 1
            ORDER BY COALESCE(l.prioridad, 99) ASC, l.nombre ASC, COALESCE(u.capacidad_kg, 0) DESC
        ")->fetchAll(PDO::FETCH_NUM);

        $rutas = $pdo->query("
            SELECT
                l.nombre,
                r.origen,
                r.destino,
                CONCAT(r.origen, ' - ', r.destino) AS ruta,
                r.frecuencia,
                COALESCE(r.tipo_cobro, 'Ruta fija + peso') AS tipo_cobro,
                COALESCE(r.tarifa_fija, 0) AS tarifa_fija,
                COALESCE(r.recargo_combustible, 0) + COALESCE(r.recargo_maniobras, 0) + COALESCE(r.recargo_zona_extendida, 0) AS recargos,
                CONCAT(COALESCE(r.sla_hrs, r.tiempo_estimado_hrs, 24), 'h') AS sla,
                CONCAT(COALESCE(r.cumplimiento_pct, 0), '%') AS cumplimiento
            FROM logistica_rutas r
            JOIN logistica_lineas l ON l.id = r.linea_id
            WHERE COALESCE(r.activo, 1) = 1
            ORDER BY COALESCE(r.zona, ''), r.destino, l.nombre
        ")->fetchAll(PDO::FETCH_NUM);

        if ($proveedores && $rutas) {
            return ['proveedores' => $proveedores, 'rutas' => $rutas];
        }
    } catch (Throwable $e) {
        // Usar demo si la instalacion aun no tiene las tablas logisticas.
    }
    return datosLogisticaDemoExcel();
}

function recomendacionesReporteExcel(array $resumen, array $logistica): array {
    $out = [];
    foreach ($resumen['productos'] as $producto => $p) {
        if ($p['variacion'] <= -0.25) {
            $out[] = ['Ventas', "$producto tuvo una baja de " . number_format(abs($p['variacion']) * 100, 1) . '% vs periodo anterior.', '$' . number_format($p['total'], 2), 'Posible precio/disponibilidad', 'Revisar precio, promociones o disponibilidad.', 'Alta', 'Variacion <= -25%'];
        } elseif ($p['variacion'] >= 0.30) {
            $out[] = ['Ventas', "$producto tuvo crecimiento sostenido.", '$' . number_format($p['total'], 2), 'Mayor demanda', 'Aumentar stock y asegurar transporte.', 'Alta', 'Variacion >= +30%'];
        }
    }
    $rutaCara = null;
    foreach ($logistica['rutas'] as $ruta) {
        $costo = (float)($ruta[6] ?? 0) + (float)($ruta[7] ?? 0);
        if ($rutaCara === null || $costo > $rutaCara['costo']) {
            $rutaCara = ['linea' => $ruta[0] ?? '', 'ruta' => $ruta[3] ?? '', 'costo' => $costo, 'cumplimiento' => $ruta[9] ?? ''];
        }
    }
    if ($rutaCara) {
        $out[] = ['Logistica', $rutaCara['ruta'] . ' tiene el costo logistico mas alto.', '$' . number_format($rutaCara['costo'], 2), 'Proveedor ' . $rutaCara['linea'] . ' / cumplimiento ' . $rutaCara['cumplimiento'], 'Revisar proveedor alternativo o negociar tarifa.', 'Media', 'Costo maximo en rutas'];
    }
    $out[] = ['Proveedor', 'Prioridad del proveedor basada principalmente en costo.', 'Catalogo logistico en BDD', 'Comparar SLA antes de cambiar proveedor', 'Priorizar menor costo si cumple capacidad y SLA.', 'Media', 'Regla de prioridad'];
    $out[] = ['Operacion', 'Agenda de carga requerida.', 'Lunes a sabado 08:00-18:00', 'Reduce retrasos', 'Agregar agenda de cita de carga.', 'Alta', 'Regla operativa'];
    return $out;
}

function registrarHistorialReporteExcel(PDO $pdo, array $data): void {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS reportes_generados (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tipo VARCHAR(80) NOT NULL,
                periodo VARCHAR(20) NOT NULL,
                fecha_inicio DATETIME NOT NULL,
                fecha_fin DATETIME NOT NULL,
                filtros_json TEXT NULL,
                usuario_id INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_reportes_tipo (tipo),
                KEY idx_reportes_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $st = $pdo->prepare("INSERT INTO reportes_generados (tipo, periodo, fecha_inicio, fecha_fin, filtros_json, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
        $st->execute([$data['tipo'], $data['periodo'], $data['inicio'], $data['fin'], json_encode($data['filtros'], JSON_UNESCAPED_UNICODE), $_SESSION['usuario_id'] ?? null]);
    } catch (Throwable $e) {
        // No bloquear la descarga si el historial falla.
    }
}

function obtenerHistorialReportesExcel(PDO $pdo): array {
    try {
        $rows = $pdo->query("
            SELECT
                rg.created_at,
                COALESCE(u.nombre, 'Usuario') AS usuario,
                rg.periodo,
                rg.fecha_inicio,
                rg.fecha_fin,
                rg.filtros_json
            FROM reportes_generados rg
            LEFT JOIN usuarios u ON u.id = rg.usuario_id
            WHERE rg.tipo = 'ventas_logistica'
            ORDER BY rg.created_at DESC
            LIMIT 25
        ")->fetchAll();

        return array_map(static fn($r) => [
            $r['created_at'],
            $r['usuario'],
            $r['periodo'],
            $r['fecha_inicio'],
            $r['fecha_fin'],
            $r['filtros_json'],
        ], $rows);
    } catch (Throwable $e) {
        return [];
    }
}
