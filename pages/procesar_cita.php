<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireAdmin();

header('Content-Type: application/json');

try {
    $pl_folio    = $_POST['pl_folio'] ?? '';
    $linea_id    = $_POST['linea_id'] ?? 0;
    $fecha_cita  = $_POST['fecha_cita'] ?? '';
    $operador_id = $_POST['operador_id'] ?? null;
    $pedidos_ids = $_POST['pedidos_ids'] ?? ''; // String separado por comas
    $carga_manual = (int)($_POST['carga_kg'] ?? 0);

    if (empty($pl_folio) || empty($linea_id)) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }

    $pdo->beginTransaction();

    $total_carga = $carga_manual;
    if ($total_carga <= 0 && !empty($pedidos_ids)) {
        $idsArr = array_map('intval', explode(',', $pedidos_ids));
        $idsArr = array_filter($idsArr); // Eliminar ceros o nulos
        if (!empty($idsArr)) {
            $placeholders = str_repeat('?,', count($idsArr) - 1) . '?';
            $sqlWeight = "SELECT SUM(pi.cantidad * COALESCE(pr.peso_kg, 25)) FROM pedido_items pi JOIN productos pr ON pr.id = pi.producto_id WHERE pi.pedido_id IN ($placeholders)";
            $stmtWeight = $pdo->prepare($sqlWeight);
            $stmtWeight->execute($idsArr);
            $total_carga = (int)$stmtWeight->fetchColumn();
        }
    } // 1. Insertar la cita
    $stmt = $pdo->prepare("INSERT INTO logistica_citas (pl_folio, linea_id, operador_id, fecha_cita, carga_kg, estado) VALUES (?, ?, ?, ?, ?, 'programada')");
    $stmt->execute([$pl_folio, $linea_id, $operador_id, $fecha_cita, $total_carga]);
    
    // 2. Si hay pedidos vinculados, actualizarlos
    if (!empty($pedidos_ids)) {
        $idsArr = explode(',', $pedidos_ids);
        foreach ($idsArr as $pid) {
            $pid = (int)trim($pid);
            if ($pid > 0) {
                // Cambiar estado a 'aceptado' y asignar operador
                $stmtUpd = $pdo->prepare("UPDATE pedidos SET estado = 'aceptado', operador_id = ?, area_flujo = 'embarque' WHERE id = ?");
                $stmtUpd->execute([$operador_id, $pid]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
