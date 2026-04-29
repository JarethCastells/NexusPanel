<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$action = $_GET['action'] ?? '';
$publicActions = ['tracking_publico'];
if (!in_array($action, $publicActions, true)) {
    requireAuth();
}

$pedidoId = (int)($_GET['pedido_id'] ?? 0);
$u = usuarioActual();
$hasFolioHex = columnExists($pdo, 'pedidos', 'folio_hex');
$folioExpr = $hasFolioHex ? "p.folio_hex" : "UPPER(HEX(p.id))";

switch ($action) {

    case 'estado':
        $p = $pdo->prepare("
            SELECT p.*, {$folioExpr} AS folio_hex, u.nombre AS operador_nombre, u.telefono AS operador_tel, u.lat AS op_lat, u.lng AS op_lng
            FROM pedidos p
            LEFT JOIN usuarios u ON u.id = p.operador_id
            WHERE p.id = ?
        ");
        $p->execute([$pedidoId]);
        jsonResponse($p->fetch() ?: []);

    case 'tracking':
        if (!usuarioPuedeVerPedido($pdo, $pedidoId, $u)) jsonResponse(['error' => 'Sin permiso'], 403);
        $t = $pdo->prepare("SELECT lat, lng, ts FROM tracking WHERE pedido_id=? ORDER BY id DESC LIMIT 1");
        $t->execute([$pedidoId]);
        jsonResponse($t->fetch() ?: ['lat' => null, 'lng' => null, 'ts' => null]);

    case 'crear_link_tracking':
        if (!usuarioPuedeVerPedido($pdo, $pedidoId, $u)) jsonResponse(['error' => 'Sin permiso'], 403);
        $createdBy = (int)($u['usuario_id'] ?? 0);
        if ($createdBy <= 0) jsonResponse(['error' => 'Sesion invalida'], 401);
        $ttl = (int)($_GET['ttl'] ?? 21600);
        $ttl = max(300, min($ttl, 86400));
        $share = crearTrackingShare($pdo, $pedidoId, $createdBy, $ttl);
        jsonResponse([
            'success' => true,
            'url_publica' => construirUrlTrackingPublico($share['token']),
            'expira_en' => $ttl,
            'expira_at' => $share['expires_at']
        ]);

    case 'estado_link_tracking':
        if (!usuarioPuedeVerPedido($pdo, $pedidoId, $u)) jsonResponse(['error' => 'Sin permiso'], 403);
        $active = obtenerTrackingShareActivo($pdo, $pedidoId);
        if (!$active) {
            jsonResponse([
                'success' => true,
                'activo' => false
            ]);
        }

        jsonResponse([
            'success' => true,
            'activo' => true,
            'expira_at' => $active['expires_at']
        ]);

    case 'revocar_link_tracking':
        if (!usuarioPuedeVerPedido($pdo, $pedidoId, $u)) jsonResponse(['error' => 'Sin permiso'], 403);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') jsonResponse(['error' => 'Metodo no permitido'], 405);
        $revocados = revocarTrackingShares($pdo, $pedidoId);
        jsonResponse([
            'success' => true,
            'revocados' => $revocados
        ]);

    case 'tracking_publico':
        $token = trim((string)($_GET['t'] ?? ''));
        $publicPedidoId = 0;

        $share = obtenerTrackingSharePorToken($pdo, $token);
        if ($share) {
            $publicPedidoId = (int)$share['pedido_id'];
        } else {
            // Compatibilidad temporal con links anteriores firmados (sin BD)
            $payload = validarTokenTracking($token);
            if ($payload) {
                $publicPedidoId = (int)($payload['pedido_id'] ?? 0);
            }
        }

        if ($publicPedidoId <= 0) jsonResponse(['error' => 'Token invalido o expirado'], 401);

        $p = $pdo->prepare("
            SELECT p.id, p.estado, p.lat_entrega AS cli_lat, p.lng_entrega AS cli_lng, u.nombre AS operador_nombre
            FROM pedidos p
            LEFT JOIN usuarios u ON u.id = p.operador_id
            WHERE p.id = ?
            LIMIT 1
        ");
        $p->execute([$publicPedidoId]);
        $pedido = $p->fetch();
        if (!$pedido) jsonResponse(['error' => 'Pedido no encontrado'], 404);

        $t = $pdo->prepare("SELECT lat, lng, ts FROM tracking WHERE pedido_id=? ORDER BY id DESC LIMIT 1");
        $t->execute([$publicPedidoId]);
        $track = $t->fetch() ?: ['lat' => null, 'lng' => null, 'ts' => null];

        jsonResponse([
            'ok' => true,
            'pedido_id' => $publicPedidoId,
            'estado' => $pedido['estado'],
            'operador_nombre' => $pedido['operador_nombre'] ?? 'Operador',
            'cli_lat' => $pedido['cli_lat'] !== null ? (float)$pedido['cli_lat'] : null,
            'cli_lng' => $pedido['cli_lng'] !== null ? (float)$pedido['cli_lng'] : null,
            'lat' => $track['lat'] !== null ? (float)$track['lat'] : null,
            'lng' => $track['lng'] !== null ? (float)$track['lng'] : null,
            'ts' => $track['ts']
        ]);

    case 'chat_get':
        if (!usuarioPuedeVerPedido($pdo, $pedidoId, $u)) jsonResponse(['error' => 'Sin permiso'], 403);
        $stChatPedido = $pdo->prepare("SELECT estado FROM pedidos WHERE id = ? LIMIT 1");
        $stChatPedido->execute([$pedidoId]);
        $chatPedido = $stChatPedido->fetch();
        if (!$chatPedido || (string)$chatPedido['estado'] !== 'en_camino') jsonResponse([]);
        $desde = (int)($_GET['desde'] ?? 0);
        $msgs = $pdo->prepare("
            SELECT c.id, c.mensaje, c.ts, u.nombre, u.rol
            FROM chat c JOIN usuarios u ON u.id = c.usuario_id
            WHERE c.pedido_id = ? AND c.id > ?
            ORDER BY c.id ASC
        ");
        $msgs->execute([$pedidoId, $desde]);
        jsonResponse($msgs->fetchAll());

    case 'chat_send':
        if (!usuarioPuedeVerPedido($pdo, $pedidoId, $u)) jsonResponse(['error' => 'Sin permiso'], 403);
        $stChatPedido = $pdo->prepare("SELECT estado FROM pedidos WHERE id = ? LIMIT 1");
        $stChatPedido->execute([$pedidoId]);
        $chatPedido = $stChatPedido->fetch();
        if (!$chatPedido || (string)$chatPedido['estado'] !== 'en_camino') jsonResponse(['error' => 'El chat solo esta disponible cuando el pedido esta en camino'], 409);
        $body = json_decode(file_get_contents('php://input'), true);
        $mensaje = trim($body['mensaje'] ?? '');
        if (!$mensaje) jsonResponse(['error' => 'Mensaje vacio'], 400);
        $pdo->prepare("INSERT INTO chat (pedido_id,usuario_id,mensaje) VALUES (?,?,?)")
            ->execute([$pedidoId, $u['usuario_id'], $mensaje]);
        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);

    case 'solicitar_cancelacion':
        if (!esOperador() && !esCliente()) jsonResponse(['error' => 'Sin permiso'], 403);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') jsonResponse(['error' => 'Metodo no permitido'], 405);
        if ($pedidoId <= 0) jsonResponse(['error' => 'Pedido invalido'], 422);

        $body = json_decode(file_get_contents('php://input'), true);
        $motivo = trim((string)($body['motivo'] ?? ''));
        if ($motivo === '') jsonResponse(['error' => 'Debes indicar el motivo'], 422);

        $stPed = $pdo->prepare("SELECT id, cliente_id, operador_id, estado FROM pedidos WHERE id = ? LIMIT 1");
        $stPed->execute([$pedidoId]);
        $ped = $stPed->fetch();
        if (!$ped) jsonResponse(['error' => 'Pedido no encontrado'], 404);

        $uid = (int)($u['usuario_id'] ?? 0);
        $esPropioCliente = esCliente() && $uid === (int)$ped['cliente_id'];
        $esPropioOperador = esOperador() && $uid === (int)$ped['operador_id'];
        if (!$esPropioCliente && !$esPropioOperador) jsonResponse(['error' => 'Sin permiso sobre este pedido'], 403);
        if (in_array((string)$ped['estado'], ['entregado', 'cancelado'], true)) {
            jsonResponse(['error' => 'Este pedido ya no se puede cancelar'], 409);
        }

        asegurarTablaCancelacionesPedido($pdo);
        $tipoSolicitante = esCliente() ? 'cliente' : 'operador';
        $ins = $pdo->prepare("
            INSERT INTO pedido_cancelaciones (pedido_id, solicitado_por, solicitante_id, motivo, estado_solicitud, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'pendiente', NOW(), NOW())
        ");
        $ins->execute([$pedidoId, $tipoSolicitante, $uid, mb_substr($motivo, 0, 400)]);
        registrarHistorialPedido($pdo, $pedidoId, 'solicitud_cancelacion', $uid, 'Solicitud de cancelacion: ' . mb_substr($motivo, 0, 250));
        jsonResponse(['success' => true, 'id' => (int)$pdo->lastInsertId()]);

    case 'resolver_solicitud_cancelacion':
        if (!esAdmin() && !esInventario()) jsonResponse(['error' => 'Sin permiso'], 403);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') jsonResponse(['error' => 'Metodo no permitido'], 405);
        $body = json_decode(file_get_contents('php://input'), true);
        $solicitudId = (int)($body['solicitud_id'] ?? 0);
        $decision = trim((string)($body['decision'] ?? ''));
        $nota = trim((string)($body['nota'] ?? ''));
        if ($solicitudId <= 0 || !in_array($decision, ['aprobar', 'rechazar'], true)) {
            jsonResponse(['error' => 'Solicitud o decision invalida'], 422);
        }

        asegurarTablaCancelacionesPedido($pdo);
        $pdo->beginTransaction();
        try {
            $stSol = $pdo->prepare("
                SELECT id, pedido_id, solicitado_por, solicitante_id, motivo, estado_solicitud
                FROM pedido_cancelaciones
                WHERE id = ?
                FOR UPDATE
            ");
            $stSol->execute([$solicitudId]);
            $sol = $stSol->fetch();
            if (!$sol) throw new RuntimeException('Solicitud no encontrada');
            if ((string)$sol['estado_solicitud'] !== 'pendiente') throw new RuntimeException('La solicitud ya fue procesada');

            $nuevoEstadoSol = $decision === 'aprobar' ? 'aprobada' : 'rechazada';
            $pdo->prepare("
                UPDATE pedido_cancelaciones
                SET estado_solicitud = ?, revisado_por = ?, nota_revision = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$nuevoEstadoSol, (int)$u['usuario_id'], mb_substr($nota, 0, 400), $solicitudId]);

            if ($decision === 'aprobar') {
                $stPed = $pdo->prepare("SELECT id, cliente_id, estado FROM pedidos WHERE id = ? FOR UPDATE");
                $stPed->execute([(int)$sol['pedido_id']]);
                $ped = $stPed->fetch();
                if (!$ped) throw new RuntimeException('Pedido no encontrado');

                $pedidoEliminarId = (int)$sol['pedido_id'];
                $tablasRelacionadas = [
                    'tracking',
                    'chat',
                    'pedido_items',
                    'pedido_historial_estados',
                    'pedido_cancelaciones',
                    'pedido_calificaciones',
                    'pedido_evidencias',
                    'notificaciones_eventos',
                ];
                foreach ($tablasRelacionadas as $tabla) {
                    if (!tableExists($pdo, $tabla) || !columnExists($pdo, $tabla, 'pedido_id')) continue;
                    $pdo->prepare("DELETE FROM {$tabla} WHERE pedido_id = ?")->execute([$pedidoEliminarId]);
                }

                $pdo->prepare("DELETE FROM pedidos WHERE id = ?")->execute([$pedidoEliminarId]);
            } else {
                registrarHistorialPedido($pdo, (int)$sol['pedido_id'], 'cancelacion_rechazada', (int)$u['usuario_id'], $nota !== '' ? $nota : 'Solicitud rechazada por coordinacion');
            }
            $pdo->commit();
            jsonResponse(['success' => true]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['error' => $e->getMessage()], 409);
        }

    case 'calificar_entrega':
        if (!esCliente()) jsonResponse(['error' => 'Sin permiso'], 403);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') jsonResponse(['error' => 'Metodo no permitido'], 405);
        if ($pedidoId <= 0) jsonResponse(['error' => 'Pedido invalido'], 422);
        $body = json_decode(file_get_contents('php://input'), true);
        $estrellas = (int)($body['estrellas'] ?? 0);
        $comentario = trim((string)($body['comentario'] ?? ''));
        if ($estrellas < 1 || $estrellas > 5) jsonResponse(['error' => 'La calificacion debe ser de 1 a 5 estrellas'], 422);

        $stPed = $pdo->prepare("SELECT id, cliente_id, estado FROM pedidos WHERE id = ? LIMIT 1");
        $stPed->execute([$pedidoId]);
        $ped = $stPed->fetch();
        if (!$ped) jsonResponse(['error' => 'Pedido no encontrado'], 404);
        if ((int)$ped['cliente_id'] !== (int)$u['usuario_id']) jsonResponse(['error' => 'Sin permiso sobre este pedido'], 403);
        if ((string)$ped['estado'] !== 'entregado') jsonResponse(['error' => 'Solo puedes calificar pedidos entregados'], 409);

        asegurarTablaCalificacionesPedido($pdo);
        $stExiste = $pdo->prepare("SELECT id FROM pedido_calificaciones WHERE pedido_id = ? AND cliente_id = ? LIMIT 1");
        $stExiste->execute([$pedidoId, (int)$u['usuario_id']]);
        if ($stExiste->fetch()) {
            jsonResponse(['error' => 'Este pedido ya fue calificado y no se puede editar.'], 409);
        }

        $stUp = $pdo->prepare("
            INSERT INTO pedido_calificaciones (pedido_id, cliente_id, estrellas, comentario, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stUp->execute([$pedidoId, (int)$u['usuario_id'], $estrellas, mb_substr($comentario, 0, 400)]);
        registrarHistorialPedido($pdo, $pedidoId, 'calificado', (int)$u['usuario_id'], 'Cliente califico con ' . $estrellas . ' estrella(s)');
        jsonResponse(['success' => true]);

    case 'update_tracking':
        if (!esOperador() && !esAdmin()) jsonResponse(['error' => 'Sin permiso'], 403);
        $body = json_decode(file_get_contents('php://input'), true);
        $lat = (float)($body['lat'] ?? 0);
        $lng = (float)($body['lng'] ?? 0);
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            jsonResponse(['error' => 'Coordenadas invalidas'], 422);
        }

        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO tracking (pedido_id,operador_id,lat,lng) VALUES (?,?,?,?)")
                ->execute([$pedidoId, $u['usuario_id'], $lat, $lng]);
            $pdo->prepare("UPDATE usuarios SET lat=?,lng=? WHERE id=?")->execute([$lat, $lng, $u['usuario_id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['error' => 'No se pudo actualizar tracking'], 500);
        }
        jsonResponse(['success' => true]);

    case 'aceptar':
        if (!esOperador() && !esAdmin()) jsonResponse(['error' => 'Sin permiso'], 403);
        if (esOperador()) {
            jsonResponse(['error' => 'La asignacion la realiza Inventario/Admin.'], 409);
        }
        try {
            $pdo->beginTransaction();
            $stPed = $pdo->prepare("SELECT cliente_id FROM pedidos WHERE id = ? FOR UPDATE");
            $stPed->execute([$pedidoId]);
            $pedBase = $stPed->fetch();
            if (!$pedBase) {
                throw new RuntimeException('Pedido no encontrado');
            }
            $upd = $pdo->prepare("UPDATE pedidos SET operador_id=?,estado='aceptado',updated_at=NOW() WHERE id=? AND estado='pendiente'");
            $upd->execute([$u['usuario_id'], $pedidoId]);
            if ($upd->rowCount() < 1) {
                throw new RuntimeException('Pedido no disponible');
            }
            $pdo->prepare("INSERT INTO chat (pedido_id,usuario_id,mensaje) VALUES (?,?,?)")
                ->execute([$pedidoId, $u['usuario_id'], 'He aceptado tu pedido. En breve estare en camino.']);
            registrarHistorialPedido($pdo, $pedidoId, 'aceptado', (int)$u['usuario_id'], 'Pedido aceptado por operador');
            registrarNotificacionesEstandarPedido(
                $pdo,
                $pedidoId,
                (int)$pedBase['cliente_id'],
                'pedido_aceptado',
                'Tu pedido fue confirmado y ya esta asignado a operador.'
            );
            registrarAuditoria($pdo, (int)$u['usuario_id'], (string)($u['rol'] ?? ''), 'pedidos', 'aceptar', 'pedido', $pedidoId, 'Pedido aceptado');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['error' => 'No se pudo aceptar el pedido'], 409);
        }
        jsonResponse(['success' => true]);

    case 'iniciar_viaje':
        if (!esAdmin() && !esInventario()) jsonResponse(['error' => 'Sin permiso'], 403);
        try {
            $pdo->beginTransaction();
            $stPed = $pdo->prepare("
                SELECT id, cliente_id, operador_id, estado
                FROM pedidos
                WHERE id = ?
                FOR UPDATE
            ");
            $stPed->execute([$pedidoId]);
            $pedBase = $stPed->fetch();
            if (!$pedBase) {
                throw new RuntimeException('Pedido no encontrado');
            }
            if ((int)($pedBase['operador_id'] ?? 0) <= 0) {
                throw new RuntimeException('El pedido no tiene operador asignado');
            }
            if (($pedBase['estado'] ?? '') === 'en_camino') {
                $pdo->commit();
                jsonResponse(['success' => true, 'already' => true]);
            }
            if (($pedBase['estado'] ?? '') !== 'aceptado') {
                throw new RuntimeException('Solo se puede iniciar viaje en pedidos aceptados');
            }

            $upd = $pdo->prepare("UPDATE pedidos SET estado='en_camino',updated_at=NOW() WHERE id=? AND estado='aceptado'");
            $upd->execute([$pedidoId]);
            if ($upd->rowCount() < 1) {
                throw new RuntimeException('Pedido no editable');
            }

            $pdo->prepare("INSERT INTO chat (pedido_id,usuario_id,mensaje) VALUES (?,?,?)")
                ->execute([$pedidoId, (int)$pedBase['operador_id'], 'Ya voy en camino. Puedes ver mi ubicacion en tiempo real en el mapa.']);
            registrarHistorialPedido($pdo, $pedidoId, 'en_camino', (int)$u['usuario_id'], 'Viaje iniciado por coordinacion');
            registrarNotificacionesEstandarPedido(
                $pdo,
                $pedidoId,
                (int)$pedBase['cliente_id'],
                'pedido_en_camino',
                'Tu pedido salio a ruta y va en camino.'
            );
            registrarAuditoria($pdo, (int)$u['usuario_id'], (string)($u['rol'] ?? ''), 'pedidos', 'iniciar_viaje', 'pedido', $pedidoId, 'Pedido en camino');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['error' => 'No se pudo iniciar el viaje: ' . $e->getMessage()], 409);
        }
        jsonResponse(['success' => true]);

    case 'entregar':
        if (!esOperador() && !esAdmin() && !esInventario()) jsonResponse(['error' => 'Sin permiso'], 403);
        
        $personaRecibe = trim((string)($_POST['persona_recibe'] ?? ''));
        $estadoPaquete = trim((string)($_POST['estado_paquete'] ?? ''));
        $comentarioEntrega = trim((string)($_POST['comentario_entrega'] ?? ''));
        
        if ($personaRecibe === '' || $estadoPaquete === '' || $comentarioEntrega === '') {
            jsonResponse(['error' => 'Debes ingresar quien recibe, el estado del paquete y un comentario de entrega.'], 400);
        }

        // Validar que se hayan subido al menos 3 evidencias
        if (!isset($_FILES['evidencias']) || count($_FILES['evidencias']['tmp_name']) < 3) {
            jsonResponse(['error' => 'Se requieren minimo 3 fotos de evidencia para finalizar el viaje.'], 400);
        }

        $evidenciasPaths = [];
        $uploadDir = __DIR__ . '/../uploads/evidencias/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach ($_FILES['evidencias']['tmp_name'] as $idx => $tmpName) {
            if ($_FILES['evidencias']['error'][$idx] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['evidencias']['name'][$idx], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;
                $filename = uniqid('ev_') . '_' . $pedidoId . '.' . $ext;
                $dest = $uploadDir . $filename;
                if (move_uploaded_file($tmpName, $dest)) {
                    $evidenciasPaths[] = '/uploads/evidencias/' . $filename;
                }
            }
        }

        if (count($evidenciasPaths) < 3) {
            jsonResponse(['error' => 'Se requieren minimo 3 fotos validas (JPG/PNG/WEBP).'], 400);
        }

        // Ejecutar DDL fuera de la transaccion para evitar commits implicitos en MySQL
        if (!columnExists($pdo, 'pedidos', 'persona_recibe')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN persona_recibe VARCHAR(120) NULL AFTER estado");
        }
        if (!columnExists($pdo, 'pedidos', 'estado_paquete')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN estado_paquete VARCHAR(80) NULL AFTER persona_recibe");
        }
        if (!columnExists($pdo, 'pedidos', 'comentario_entrega')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN comentario_entrega TEXT NULL AFTER estado_paquete");
        }
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pedido_evidencias (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT NOT NULL,
                foto_url VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_pe_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        try {
            $pdo->beginTransaction();
            $stPed = $pdo->prepare("
                SELECT
                    p.cliente_id,
                    p.folio_hex,
                    p.lat_entrega,
                    p.operador_id,
                    p.lng_entrega,
                    u.telefono,
                    u.nombre AS cli_nombre,
                    u.lat AS cli_lat,
                    u.lng AS cli_lng
                FROM pedidos p
                JOIN usuarios u ON p.cliente_id = u.id
                WHERE p.id = ? FOR UPDATE
            ");
            $stPed->execute([$pedidoId]);
            $pedBase = $stPed->fetch();
            if (!$pedBase) {
                throw new RuntimeException('Pedido no encontrado');
            }

            if (esOperador()) {
                $trackSt = $pdo->prepare("
                    SELECT lat, lng
                    FROM tracking
                    WHERE pedido_id = ? AND operador_id = ?
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $trackSt->execute([$pedidoId, (int)$u['usuario_id']]);
                $lastTrack = $trackSt->fetch();

                $opLat = $lastTrack && $lastTrack['lat'] !== null ? (float)$lastTrack['lat'] : (isset($u['lat']) ? (float)$u['lat'] : null);
                $opLng = $lastTrack && $lastTrack['lng'] !== null ? (float)$lastTrack['lng'] : (isset($u['lng']) ? (float)$u['lng'] : null);
                $cliLat = $pedBase['lat_entrega'] !== null ? (float)$pedBase['lat_entrega'] : ($pedBase['cli_lat'] !== null ? (float)$pedBase['cli_lat'] : null);
                $cliLng = $pedBase['lng_entrega'] !== null ? (float)$pedBase['lng_entrega'] : ($pedBase['cli_lng'] !== null ? (float)$pedBase['cli_lng'] : null);

                if ($opLat === null || $opLng === null || $cliLat === null || $cliLng === null) {
                    throw new RuntimeException('No se pudo validar cercania. Comparte ubicacion para continuar.');
                }

                $distKm = haversine($opLat, $opLng, $cliLat, $cliLng);
                if ($distKm > 0.3 && !isLocalDevRequest()) {
                    throw new RuntimeException('Aun no estas a menos de 300 metros del cliente. Acercate para habilitar la entrega.');
                }
            }
            
            if (esOperador()) {
                $upd = $pdo->prepare("UPDATE pedidos SET estado='entregado', persona_recibe=?, estado_paquete=?, comentario_entrega=?, updated_at=NOW() WHERE id=? AND operador_id=?");
                $upd->execute([$personaRecibe, $estadoPaquete, $comentarioEntrega, $pedidoId, $u['usuario_id']]);
            } else {
                $upd = $pdo->prepare("UPDATE pedidos SET estado='entregado', persona_recibe=?, estado_paquete=?, comentario_entrega=?, updated_at=NOW() WHERE id=?");
                $upd->execute([$personaRecibe, $estadoPaquete, $comentarioEntrega, $pedidoId]);
            }
            if ($upd->rowCount() < 1) {
                throw new RuntimeException('Pedido no editable');
            }
            $pdo->prepare("INSERT INTO chat (pedido_id,usuario_id,mensaje) VALUES (?,?,?)")
                ->execute([$pedidoId, $u['usuario_id'], 'Pedido entregado. Gracias por tu compra.']);
            registrarHistorialPedido($pdo, $pedidoId, 'entregado', (int)$u['usuario_id'], 'Entrega confirmada');
            registrarNotificacionesEstandarPedido(
                $pdo,
                $pedidoId,
                (int)$pedBase['cliente_id'],
                'pedido_entregado',
                'Tu pedido fue entregado correctamente.'
            );
            registrarAuditoria($pdo, (int)$u['usuario_id'], (string)($u['rol'] ?? ''), 'pedidos', 'entregar', 'pedido', $pedidoId, 'Pedido entregado');

            // Guardar las evidencias en la base de datos
            $stmtEv = $pdo->prepare("INSERT INTO pedido_evidencias (pedido_id, foto_url) VALUES (?, ?)");
            foreach ($evidenciasPaths as $path) {
                $stmtEv->execute([$pedidoId, $path]);
            }

            // Auto-flujo operador: al entregar, activar automaticamente el siguiente pedido aceptado.
            $operadorFlujoId = esOperador() ? (int)$u['usuario_id'] : (int)($pedBase['operador_id'] ?? 0);
            if ($operadorFlujoId > 0) {
                $stNext = $pdo->prepare("
                    SELECT id, cliente_id
                    FROM pedidos
                    WHERE operador_id = ?
                      AND estado = 'aceptado'
                      AND id <> ?
                    ORDER BY updated_at ASC, id ASC
                    LIMIT 1
                    FOR UPDATE
                ");
                $stNext->execute([$operadorFlujoId, $pedidoId]);
                $nextPedido = $stNext->fetch();
                if ($nextPedido) {
                    $updNext = $pdo->prepare("UPDATE pedidos SET estado='en_camino', updated_at=NOW() WHERE id=? AND estado='aceptado'");
                    $updNext->execute([(int)$nextPedido['id']]);
                    if ($updNext->rowCount() > 0) {
                        $pdo->prepare("INSERT INTO chat (pedido_id,usuario_id,mensaje) VALUES (?,?,?)")
                            ->execute([(int)$nextPedido['id'], $operadorFlujoId, 'Ya voy en camino. Puedes ver mi ubicacion en tiempo real en el mapa.']);
                        registrarHistorialPedido($pdo, (int)$nextPedido['id'], 'en_camino', (int)$u['usuario_id'], 'Viaje iniciado automaticamente tras entrega previa');
                        registrarNotificacionesEstandarPedido(
                            $pdo,
                            (int)$nextPedido['id'],
                            (int)$nextPedido['cliente_id'],
                            'pedido_en_camino',
                            'Tu pedido salio a ruta y va en camino.'
                        );
                    }
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['error' => 'No se pudo marcar como entregado: ' . $e->getMessage()], 409);
        }
        
        $folio = $pedBase['folio_hex'] ?: strtoupper(dechex($pedidoId));
        $msj_wa = "Hola {$pedBase['cli_nombre']},\nte informamos que tu pedido con folio #{$folio} ha sido entregado exitosamente a: *{$personaRecibe}*.\nEstado del paquete: {$estadoPaquete}.\n\nÂ¡Gracias por tu preferencia!";

        jsonResponse([
            'success' => true,
            'telefono' => $pedBase['telefono'] ?? '',
            'mensaje_wa' => $msj_wa
        ]);

    case 'pedidos_pendientes':
        if (!esOperador() && !esAdmin()) jsonResponse(['error' => 'Sin permiso'], 403);
        if (esOperador()) {
            jsonResponse([]);
        }
        $opLat = (float)($u['lat'] ?? 0);
        $opLng = (float)($u['lng'] ?? 0);

        $pedidos = $pdo->query("
            SELECT p.*, u.nombre AS cliente_nombre, u.telefono AS cliente_tel,
                   u.lat AS cli_lat, u.lng AS cli_lng, u.domicilio AS cli_dom
            FROM pedidos p
            JOIN usuarios u ON u.id = p.cliente_id
            WHERE p.estado='pendiente'
            ORDER BY p.created_at DESC
        ")->fetchAll();

        $resultado = [];
        foreach ($pedidos as $p) {
            if (esAdmin()) {
                $p['distancia_km'] = null;
                $resultado[] = $p;
                continue;
            }

            $cLat = (float)($p['cli_lat'] ?? 0);
            $cLng = (float)($p['cli_lng'] ?? 0);

            if (!$cLat && !$cLng) {
                $resultado[] = $p;
                continue;
            }

            $dist = haversine($opLat, $opLng, $cLat, $cLng);
            $p['distancia_km'] = round($dist, 1);
            $resultado[] = $p;
        }
        jsonResponse($resultado);

    case 'operador_no_productos':
        if (!esOperador() && !esAdmin() && !esInventario()) jsonResponse(['error' => 'Sin permiso'], 403);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') jsonResponse(['error' => 'Metodo no permitido'], 405);
        if ($pedidoId <= 0) jsonResponse(['error' => 'Pedido invalido'], 422);
        try {
            if (!columnExists($pdo, 'pedidos', 'operador_sin_productos')) {
                $pdo->exec("ALTER TABLE pedidos ADD COLUMN operador_sin_productos TINYINT(1) NOT NULL DEFAULT 0 AFTER estado");
            }
            if (!columnExists($pdo, 'pedidos', 'sin_productos_at')) {
                $pdo->exec("ALTER TABLE pedidos ADD COLUMN sin_productos_at DATETIME NULL AFTER operador_sin_productos");
            }

            $pdo->beginTransaction();
            if (esOperador()) {
                $st = $pdo->prepare("
                    UPDATE pedidos
                    SET operador_sin_productos = 1,
                        sin_productos_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ? AND operador_id = ?
                ");
                $st->execute([$pedidoId, (int)$u['usuario_id']]);
            } else {
                $st = $pdo->prepare("
                    UPDATE pedidos
                    SET operador_sin_productos = 1,
                        sin_productos_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $st->execute([$pedidoId]);
            }
            if ($st->rowCount() < 1) {
                throw new RuntimeException('No se pudo marcar el pedido');
            }
            registrarHistorialPedido($pdo, $pedidoId, 'sin_productos', (int)$u['usuario_id'], 'Operador sin producto, pedido enviado al final de cola');
            $pdo->commit();
            jsonResponse(['success' => true]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['error' => $e->getMessage()], 409);
        }

    case 'operador_activos':
        if (!esOperador() && !esAdmin()) jsonResponse(['error' => 'Sin permiso'], 403);
        $uid = (int)($u['usuario_id'] ?? 0);
        $hasSinProductos = columnExists($pdo, 'pedidos', 'operador_sin_productos');
        $hasSinProductosAt = columnExists($pdo, 'pedidos', 'sin_productos_at');
        $sinProductosExpr = $hasSinProductos ? "IFNULL(p.operador_sin_productos,0)" : "0";
        $sinProductosAtExpr = $hasSinProductosAt ? "p.sin_productos_at" : "NULL";

        // Autocorreccion operativa:
        // Si el operador tiene pedidos aceptados pero ninguno en camino,
        // promovemos automaticamente el siguiente aceptado a en_camino.
        if (esOperador() && $uid > 0) {
            try {
                $pdo->beginTransaction();
                $stLock = $pdo->prepare("
                    SELECT
                        SUM(CASE WHEN estado='en_camino' THEN 1 ELSE 0 END) AS en_camino_count,
                        SUM(CASE WHEN estado='aceptado' THEN 1 ELSE 0 END) AS aceptado_count
                    FROM pedidos
                    WHERE operador_id = ?
                    FOR UPDATE
                ");
                $stLock->execute([$uid]);
                $counts = $stLock->fetch() ?: ['en_camino_count' => 0, 'aceptado_count' => 0];
                $enCaminoCount = (int)($counts['en_camino_count'] ?? 0);
                $aceptadoCount = (int)($counts['aceptado_count'] ?? 0);

                if ($enCaminoCount < 1 && $aceptadoCount > 0) {
                    $stNext = $pdo->prepare("
                        SELECT id, cliente_id
                        FROM pedidos
                        WHERE operador_id = ?
                          AND estado = 'aceptado'
                        ORDER BY updated_at ASC, id ASC
                        LIMIT 1
                        FOR UPDATE
                    ");
                    $stNext->execute([$uid]);
                    $nextPedido = $stNext->fetch();
                    if ($nextPedido) {
                        $updNext = $pdo->prepare("UPDATE pedidos SET estado='en_camino', updated_at=NOW() WHERE id=? AND estado='aceptado'");
                        $updNext->execute([(int)$nextPedido['id']]);
                        if ($updNext->rowCount() > 0) {
                            $pdo->prepare("INSERT INTO chat (pedido_id,usuario_id,mensaje) VALUES (?,?,?)")
                                ->execute([(int)$nextPedido['id'], $uid, 'Ya voy en camino. Puedes ver mi ubicacion en tiempo real en el mapa.']);
                            registrarHistorialPedido($pdo, (int)$nextPedido['id'], 'en_camino', $uid, 'Viaje iniciado automaticamente por autocorreccion operativa');
                            registrarNotificacionesEstandarPedido(
                                $pdo,
                                (int)$nextPedido['id'],
                                (int)$nextPedido['cliente_id'],
                                'pedido_en_camino',
                                'Tu pedido salio a ruta y va en camino.'
                            );
                        }
                    }
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
            }
        }

        $where = "p.estado IN ('aceptado','en_camino')";
        $params = [];
        if (esOperador()) {
            $where .= " AND p.operador_id = ?";
            $params[] = $uid;
        }
        $st = $pdo->prepare("
            SELECT
                p.id, {$folioExpr} AS folio_hex, p.estado, p.total, p.created_at, p.updated_at,
                {$sinProductosExpr} AS operador_sin_productos,
                {$sinProductosAtExpr} AS sin_productos_at,
                p.lat_entrega, p.lng_entrega, p.domicilio_entrega,
                c.nombre AS cli_nombre, c.telefono AS cli_tel, c.domicilio AS cli_dom,
                c.lat AS cli_lat, c.lng AS cli_lng
            FROM pedidos p
            JOIN usuarios c ON c.id = p.cliente_id
            WHERE {$where}
            ORDER BY
                CASE WHEN p.estado = 'en_camino' THEN 0 WHEN {$sinProductosExpr} = 1 THEN 2 ELSE 1 END ASC,
                CASE WHEN {$sinProductosExpr} = 1 THEN {$sinProductosAtExpr} ELSE p.updated_at END ASC
            LIMIT 250
        ");
        $st->execute($params);
        jsonResponse($st->fetchAll());

    case 'operador_ayuda_chat_get':
        if (!esOperador() && !esAdmin() && !esInventario()) jsonResponse(['error' => 'Sin permiso'], 403);
        $operadorId = (int)($_GET['operador_id'] ?? 0);
        $desde = (int)($_GET['desde'] ?? 0);
        if (esOperador()) {
            $operadorId = (int)$u['usuario_id'];
        }
        if ($operadorId <= 0) jsonResponse(['error' => 'Operador invalido'], 422);
        asegurarTablaChatAyudaOperador($pdo);
        $st = $pdo->prepare("
            SELECT
                cao.id,
                cao.operador_id,
                cao.admin_id,
                cao.remitente_id,
                cao.mensaje,
                cao.ts,
                ru.nombre AS remitente_nombre,
                ru.rol AS remitente_rol
            FROM chat_ayuda_operador cao
            JOIN usuarios ru ON ru.id = cao.remitente_id
            WHERE cao.operador_id = ? AND cao.id > ?
            ORDER BY cao.id ASC
        ");
        $st->execute([$operadorId, $desde]);
        jsonResponse($st->fetchAll());

    case 'operador_ayuda_chat_send':
        if (!esOperador() && !esAdmin() && !esInventario()) jsonResponse(['error' => 'Sin permiso'], 403);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') jsonResponse(['error' => 'Metodo no permitido'], 405);
        $operadorId = (int)($_GET['operador_id'] ?? 0);
        if (esOperador()) {
            $operadorId = (int)$u['usuario_id'];
        }
        if ($operadorId <= 0) jsonResponse(['error' => 'Operador invalido'], 422);
        asegurarTablaChatAyudaOperador($pdo);
        $body = json_decode(file_get_contents('php://input'), true);
        $mensaje = trim((string)($body['mensaje'] ?? ''));
        if ($mensaje === '') jsonResponse(['error' => 'Mensaje vacio'], 400);
        $adminId = esOperador() ? 0 : (int)$u['usuario_id'];
        $st = $pdo->prepare("
            INSERT INTO chat_ayuda_operador (operador_id, admin_id, remitente_id, mensaje, ts)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $st->execute([$operadorId, $adminId, (int)$u['usuario_id'], mb_substr($mensaje, 0, 1000)]);
        jsonResponse(['success' => true, 'id' => (int)$pdo->lastInsertId()]);

    case 'operador_notificaciones':
        if (!esOperador() && !esAdmin() && !esInventario()) jsonResponse(['error' => 'Sin permiso'], 403);
        $uid = (int)($u['usuario_id'] ?? 0);
        if ($uid <= 0) jsonResponse(['error' => 'Sesion invalida'], 401);
        $limit = (int)($_GET['limit'] ?? 60);
        $limit = max(10, min($limit, 200));

        $out = [];
        asegurarTablaChatAyudaOperador($pdo);
        $chatClienteSt = $pdo->prepare("
            SELECT
                c.id AS source_id,
                c.ts,
                c.pedido_id,
                su.nombre AS remitente_nombre,
                c.mensaje,
                {$folioExpr} AS folio_hex
            FROM chat c
            JOIN pedidos p ON p.id = c.pedido_id
            JOIN usuarios su ON su.id = c.usuario_id
            WHERE p.operador_id = ?
              AND su.rol = 'cliente'
              AND p.estado = 'en_camino'
            ORDER BY c.id DESC
            LIMIT {$limit}
        ");
        $chatClienteSt->execute([$uid]);
        foreach ($chatClienteSt->fetchAll() as $r) {
            $out[] = [
                'uid' => 'cliente:' . (int)$r['source_id'],
                'source' => 'cliente',
                'source_id' => (int)$r['source_id'],
                'ts' => $r['ts'],
                'pedido_id' => (int)$r['pedido_id'],
                'title' => 'Mensaje de cliente #' . ($r['folio_hex'] ?: strtoupper(dechex((int)$r['pedido_id']))),
                'body' => mb_substr((string)$r['mensaje'], 0, 180),
                'from' => (string)$r['remitente_nombre'],
                'goto' => 'operador_pedidos.php?tab=activos&pedido_id=' . (int)$r['pedido_id']
            ];
        }

        $chatAyudaSt = $pdo->prepare("
            SELECT
                cao.id AS source_id,
                cao.ts,
                cao.operador_id,
                cao.admin_id,
                cao.remitente_id,
                cao.mensaje,
                ru.nombre AS remitente_nombre,
                ru.rol AS remitente_rol
            FROM chat_ayuda_operador cao
            JOIN usuarios ru ON ru.id = cao.remitente_id
            WHERE cao.operador_id = ?
              AND cao.remitente_id <> ?
            ORDER BY cao.id DESC
            LIMIT {$limit}
        ");
        $chatAyudaSt->execute([$uid, $uid]);
        foreach ($chatAyudaSt->fetchAll() as $r) {
            $rolTxt = ((string)$r['remitente_rol'] === 'inventario') ? 'Manager' : 'Admin';
            $out[] = [
                'uid' => 'ayuda:' . (int)$r['source_id'],
                'source' => 'ayuda',
                'source_id' => (int)$r['source_id'],
                'ts' => $r['ts'],
                'pedido_id' => null,
                'title' => 'Mensaje de ' . $rolTxt,
                'body' => mb_substr((string)$r['mensaje'], 0, 180),
                'from' => (string)$r['remitente_nombre'],
                'goto' => 'operador_pedidos.php?tab=ayuda'
            ];
        }

        usort($out, static function ($a, $b) {
            $at = strtotime((string)($a['ts'] ?? '1970-01-01 00:00:00'));
            $bt = strtotime((string)($b['ts'] ?? '1970-01-01 00:00:00'));
            return $bt <=> $at;
        });
        $out = array_slice($out, 0, $limit);
        jsonResponse($out);

    case 'panel_notificaciones':
        if (!esAdmin() && !esInventario()) jsonResponse(['error' => 'Sin permiso'], 403);
        $limit = (int)($_GET['limit'] ?? 80);
        $limit = max(10, min($limit, 200));
        $out = [];

        $stPend = $pdo->prepare("
            SELECT
                p.id,
                {$folioExpr} AS folio_hex,
                p.created_at,
                p.total,
                c.nombre AS cliente_nombre
            FROM pedidos p
            JOIN usuarios c ON c.id = p.cliente_id
            WHERE p.estado = 'pendiente'
            ORDER BY p.created_at DESC
            LIMIT {$limit}
        ");
        $stPend->execute();
        foreach ($stPend->fetchAll() as $r) {
            $pedidoId = (int)$r['id'];
            $out[] = [
                'uid' => 'pendiente:' . $pedidoId,
                'ts' => (string)$r['created_at'],
                'title' => 'Pedido pendiente #' . (string)($r['folio_hex'] ?: strtoupper(dechex($pedidoId))),
                'body' => 'Cliente: ' . (string)$r['cliente_nombre'],
                'from' => 'Sistema',
                'goto' => 'pedidos.php?historial=' . $pedidoId . '#detalle-historial'
            ];
        }

        if (tableExists($pdo, 'pedido_historial_estados')) {
            $stHistNoti = $pdo->prepare("
                SELECT
                    h.id,
                    h.pedido_id,
                    h.estado,
                    h.nota,
                    h.created_at,
                    {$folioExpr} AS folio_hex,
                    c.nombre AS cliente_nombre,
                    o.nombre AS operador_nombre
                FROM pedido_historial_estados h
                JOIN pedidos p ON p.id = h.pedido_id
                LEFT JOIN usuarios c ON c.id = p.cliente_id
                LEFT JOIN usuarios o ON o.id = p.operador_id
                WHERE h.estado IN ('aceptado', 'en_camino', 'entregado', 'cancelado', 'calificado')
                ORDER BY h.id DESC
                LIMIT {$limit}
            ");
            $stHistNoti->execute();
            foreach ($stHistNoti->fetchAll() as $r) {
                $pedidoId = (int)$r['pedido_id'];
                $estado = (string)$r['estado'];
                $tituloEstado = match ($estado) {
                    'aceptado' => 'Pedido aceptado',
                    'en_camino' => 'Pedido en camino',
                    'entregado' => 'Pedido entregado',
                    'cancelado' => 'Pedido cancelado',
                    'calificado' => 'Pedido calificado',
                    default => 'Actualizacion de pedido',
                };
                $bodyEstado = 'Cliente: ' . (string)($r['cliente_nombre'] ?? 'Cliente');
                if (!empty($r['operador_nombre'])) {
                    $bodyEstado .= ' · Operador: ' . (string)$r['operador_nombre'];
                }
                if (!empty($r['nota'])) {
                    $bodyEstado .= ' · ' . mb_substr((string)$r['nota'], 0, 140);
                }
                $out[] = [
                    'uid' => 'hist:' . (int)$r['id'],
                    'ts' => (string)$r['created_at'],
                    'title' => $tituloEstado . ' #' . (string)($r['folio_hex'] ?: strtoupper(dechex($pedidoId))),
                    'body' => $bodyEstado,
                    'from' => 'Sistema',
                    'goto' => 'pedidos.php?historial=' . $pedidoId . '#detalle-historial'
                ];
            }
        }

        if (tableExists($pdo, 'pedido_calificaciones')) {
            $stCalNoti = $pdo->prepare("
                SELECT
                    pc.id,
                    pc.pedido_id,
                    pc.estrellas,
                    pc.comentario,
                    pc.created_at,
                    {$folioExpr} AS folio_hex,
                    c.nombre AS cliente_nombre
                FROM pedido_calificaciones pc
                JOIN pedidos p ON p.id = pc.pedido_id
                LEFT JOIN usuarios c ON c.id = p.cliente_id
                ORDER BY pc.id DESC
                LIMIT {$limit}
            ");
            $stCalNoti->execute();
            foreach ($stCalNoti->fetchAll() as $r) {
                $pedidoId = (int)$r['pedido_id'];
                $estrellas = max(1, min(5, (int)($r['estrellas'] ?? 0)));
                $comentario = trim((string)($r['comentario'] ?? ''));
                $out[] = [
                    'uid' => 'rating:' . (int)$r['id'],
                    'ts' => (string)$r['created_at'],
                    'title' => 'Nueva calificacion #' . (string)($r['folio_hex'] ?: strtoupper(dechex($pedidoId))),
                    'body' => 'Cliente: ' . (string)($r['cliente_nombre'] ?? 'Cliente') . ' · ' . str_repeat('★', $estrellas) . ($comentario !== '' ? (' · ' . mb_substr($comentario, 0, 120)) : ''),
                    'from' => 'Cliente',
                    'goto' => 'pedidos.php?historial=' . $pedidoId . '#detalle-historial'
                ];
            }
        }

        if (tableExists($pdo, 'chat_ayuda_operador')) {
            $stHelp = $pdo->prepare("
                SELECT
                    cao.id,
                    cao.ts,
                    cao.operador_id,
                    cao.mensaje,
                    ru.nombre AS remitente_nombre,
                    ru.rol AS remitente_rol
                FROM chat_ayuda_operador cao
                JOIN usuarios ru ON ru.id = cao.remitente_id
                WHERE ru.rol = 'operador'
                ORDER BY cao.id DESC
                LIMIT {$limit}
            ");
            $stHelp->execute();
            foreach ($stHelp->fetchAll() as $r) {
                $opId = (int)$r['operador_id'];
                $out[] = [
                    'uid' => 'ophelp:' . (int)$r['id'],
                    'ts' => (string)$r['ts'],
                    'title' => 'Mensaje de operador',
                    'body' => mb_substr((string)$r['mensaje'], 0, 180),
                    'from' => (string)$r['remitente_nombre'],
                    'goto' => 'mensajes.php?operador_id=' . $opId
                ];
            }
        }

        usort($out, static function ($a, $b) {
            return strcmp((string)($b['ts'] ?? ''), (string)($a['ts'] ?? ''));
        });
        if (count($out) > $limit) $out = array_slice($out, 0, $limit);
        jsonResponse($out);

    case 'operador_mensajes':
        if (!esOperador() && !esAdmin()) jsonResponse(['error' => 'Sin permiso'], 403);
        $uid = (int)($u['usuario_id'] ?? 0);
        $limit = (int)($_GET['limit'] ?? 100);
        $limit = max(1, min($limit, 300));

        $where = "p.estado = 'en_camino' AND su.rol = 'cliente'";
        $params = [];
        if (esOperador()) {
            $where .= " AND p.operador_id = ?";
            $params[] = $uid;
        }

        $sql = "
            SELECT
                c.id,
                c.pedido_id,
                c.mensaje,
                c.ts,
                {$folioExpr} AS folio_hex,
                su.nombre AS remitente_nombre
            FROM chat c
            JOIN pedidos p ON p.id = c.pedido_id
            JOIN usuarios su ON su.id = c.usuario_id
            WHERE {$where}
            ORDER BY c.id DESC
            LIMIT {$limit}
        ";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        usort($rows, static function($a, $b) {
            return ((int)$a['id'] <=> (int)$b['id']);
        });
        jsonResponse($rows);

    case 'actualizar_pedido_cliente':
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') jsonResponse(['error' => 'Metodo no permitido'], 405);
        if (!esCliente() && !esAdmin()) jsonResponse(['error' => 'Sin permiso'], 403);

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) jsonResponse(['error' => 'Datos invalidos'], 422);

        $targetPedidoId = (int)($body['pedido_id'] ?? $pedidoId ?? 0);
        if ($targetPedidoId <= 0) jsonResponse(['error' => 'Pedido invalido'], 422);

        $itemsRaw = $body['items'] ?? [];
        if (!is_array($itemsRaw) || empty($itemsRaw)) jsonResponse(['error' => 'Debes enviar productos'], 422);

        $tipoPedido = 'formal';
        $fechaRequeridaRaw = trim((string)($body['fecha_requerida'] ?? ''));
        $notas = trim((string)($body['notas'] ?? ''));
        $fechaRequerida = null;
        if ($fechaRequeridaRaw !== '') {
            $tmp = str_replace('T', ' ', $fechaRequeridaRaw);
            $dt = DateTime::createFromFormat('Y-m-d H:i', $tmp) ?: DateTime::createFromFormat('Y-m-d H:i:s', $tmp);
            if ($dt) $fechaRequerida = $dt->format('Y-m-d H:i:s');
        }
        $notas = $notas !== '' ? mb_substr($notas, 0, 1000) : null;

        $items = [];
        foreach ($itemsRaw as $row) {
            $pid = (int)($row['id'] ?? 0);
            $qty = (int)($row['qty'] ?? 0);
            if ($pid <= 0 || $qty <= 0) continue;
            $ajuste = trim((string)($row['ajuste'] ?? ''));
            $ajuste = $ajuste !== '' ? mb_substr($ajuste, 0, 255) : null;

            if (!isset($items[$pid])) {
                $items[$pid] = ['id' => $pid, 'qty' => 0, 'ajuste' => $ajuste];
            }
            $items[$pid]['qty'] += $qty;
            if ($ajuste !== null) $items[$pid]['ajuste'] = $ajuste;
        }
        if (empty($items)) jsonResponse(['error' => 'No hay items validos'], 422);

        try {
            $pdo->beginTransaction();

            $stPedido = $pdo->prepare("
                SELECT id, cliente_id, estado
                FROM pedidos
                WHERE id = ?
                FOR UPDATE
            ");
            $stPedido->execute([$targetPedidoId]);
            $pedido = $stPedido->fetch();
            if (!$pedido) {
                throw new RuntimeException('Pedido no encontrado');
            }

            $uid = (int)($u['usuario_id'] ?? 0);
            if (!esAdmin() && (int)$pedido['cliente_id'] !== $uid) {
                throw new RuntimeException('No puedes editar este pedido');
            }
            if (!esAdmin() && $pedido['estado'] !== 'pendiente') {
                throw new RuntimeException('Solo puedes editar pedidos en estado pendiente');
            }

            $stOld = $pdo->prepare("SELECT producto_id, cantidad FROM pedido_items WHERE pedido_id = ? FOR UPDATE");
            $stOld->execute([$targetPedidoId]);
            $oldRows = $stOld->fetchAll();
            $oldQty = [];
            foreach ($oldRows as $r) {
                $oldQty[(int)$r['producto_id']] = (int)$r['cantidad'];
            }

            $idsUnion = array_values(array_unique(array_merge(array_keys($items), array_keys($oldQty))));
            $placeholders = implode(',', array_fill(0, count($idsUnion), '?'));
            $stProd = $pdo->prepare("
                SELECT id, nombre, precio, stock, activo, cliente_ajustable
                FROM productos
                WHERE id IN ($placeholders)
                FOR UPDATE
            ");
            $stProd->execute($idsUnion);
            $prodRows = $stProd->fetchAll();
            $productos = [];
            foreach ($prodRows as $pr) $productos[(int)$pr['id']] = $pr;

            $errores = [];
            foreach ($items as $pid => $item) {
                if (!isset($productos[$pid])) {
                    $errores[] = "Producto #$pid no encontrado";
                    continue;
                }
                $pr = $productos[$pid];
                if ((int)$pr['activo'] !== 1) {
                    $errores[] = $pr['nombre'] . ' no esta activo';
                    continue;
                }
                $prev = (int)($oldQty[$pid] ?? 0);
                $delta = (int)$item['qty'] - $prev;
                if ($delta > 0 && (int)$pr['stock'] < $delta) {
                    $errores[] = $pr['nombre'] . ' sin stock para aumentar (faltan ' . $delta . ')';
                }
            }
            if (!empty($errores)) {
                throw new RuntimeException('Inventario insuficiente: ' . implode(' | ', $errores));
            }

            $total = 0.0;
            foreach ($idsUnion as $pid) {
                $prev = (int)($oldQty[$pid] ?? 0);
                $next = (int)($items[$pid]['qty'] ?? 0);
                $delta = $next - $prev;
                if ($delta === 0) continue;
                $stockAntes = (int)$productos[$pid]['stock'];

                if ($delta > 0) {
                    $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?")->execute([$delta, $pid]);
                    $stockNuevo = $stockAntes - $delta;
                    registrarMovimientoInventario(
                        $pdo,
                        $pid,
                        'salida',
                        $delta,
                        $stockAntes,
                        $stockNuevo,
                        'pedido_actualizacion',
                        $targetPedidoId,
                        'Ajuste por edicion de pedido (aumento cantidad)',
                        (int)$u['usuario_id']
                    );
                } else {
                    $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?")->execute([abs($delta), $pid]);
                    $stockNuevo = $stockAntes + abs($delta);
                    registrarMovimientoInventario(
                        $pdo,
                        $pid,
                        'entrada',
                        abs($delta),
                        $stockAntes,
                        $stockNuevo,
                        'pedido_actualizacion',
                        $targetPedidoId,
                        'Ajuste por edicion de pedido (disminucion cantidad)',
                        (int)$u['usuario_id']
                    );
                }
                $productos[$pid]['stock'] = $stockNuevo;
            }

            $pdo->prepare("DELETE FROM pedido_items WHERE pedido_id = ?")->execute([$targetPedidoId]);
            $stIns = $pdo->prepare("
                INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unit, ajuste_cliente)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($items as $item) {
                $pid = (int)$item['id'];
                $pr = $productos[$pid];
                $qty = (int)$item['qty'];
                $ajuste = ((int)$pr['cliente_ajustable'] === 1) ? $item['ajuste'] : null;
                $stIns->execute([$targetPedidoId, $pid, $qty, (float)$pr['precio'], $ajuste]);
                $total += ((float)$pr['precio'] * $qty);
            }

            $pdo->prepare("
                UPDATE pedidos
                SET total = ?, tipo_pedido = ?, fecha_requerida = ?, notas = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$total, $tipoPedido, $fechaRequerida, $notas, $targetPedidoId]);

            recalcularHistorialCliente($pdo, (int)$pedido['cliente_id']);
            registrarHistorialPedido($pdo, $targetPedidoId, 'pedido_actualizado', (int)$u['usuario_id'], 'Pedido editado por cliente/admin');
            registrarNotificacionesEstandarPedido(
                $pdo,
                $targetPedidoId,
                (int)$pedido['cliente_id'],
                'pedido_actualizado',
                'Tu pedido fue actualizado con nuevos productos/cantidades.'
            );
            registrarAuditoria($pdo, (int)$u['usuario_id'], (string)($u['rol'] ?? ''), 'pedidos', 'actualizar_cliente', 'pedido', $targetPedidoId, 'Pedido actualizado por cliente/admin');

            $pdo->commit();
            jsonResponse([
                'success' => true,
                'pedido_id' => $targetPedidoId,
                'total' => $total
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['error' => $e->getMessage()], 409);
        }

    case 'historial_pedidos':
        if (!estaLogueado()) jsonResponse(['error' => 'No autenticado'], 401);

        $clienteFiltro = (int)($_GET['cliente_id'] ?? 0);
        $productoFiltro = (int)($_GET['producto_id'] ?? 0);
        $estadoFiltro = trim((string)($_GET['estado'] ?? ''));
        $tipoFiltro = trim((string)($_GET['tipo_pedido'] ?? ''));
        $fechaDesde = normalizarFechaApi((string)($_GET['fecha_desde'] ?? ''));
        $fechaHasta = normalizarFechaApi((string)($_GET['fecha_hasta'] ?? ''));
        $limit = (int)($_GET['limit'] ?? 100);
        $limit = max(1, min($limit, 500));

        $where = ["1=1"];
        $params = [];

        if (esCliente()) {
            $where[] = "p.cliente_id = ?";
            $params[] = (int)$u['usuario_id'];
        } else {
            if ($clienteFiltro > 0) {
                $where[] = "p.cliente_id = ?";
                $params[] = $clienteFiltro;
            }
        }

        if ($productoFiltro > 0) {
            $where[] = "EXISTS(SELECT 1 FROM pedido_items px WHERE px.pedido_id = p.id AND px.producto_id = ?)";
            $params[] = $productoFiltro;
        }
        if ($estadoFiltro !== '') {
            $where[] = "p.estado = ?";
            $params[] = $estadoFiltro;
        }
        if ($tipoFiltro !== '') {
            $where[] = "p.tipo_pedido = ?";
            $params[] = $tipoFiltro;
        }
        if ($fechaDesde !== null) {
            $where[] = "p.created_at >= ?";
            $params[] = $fechaDesde;
        }
        if ($fechaHasta !== null) {
            $where[] = "p.created_at <= ?";
            $params[] = $fechaHasta;
        }

        $sql = "
            SELECT
                p.id, {$folioExpr} AS folio_hex, p.estado, p.tipo_pedido, p.prioridad, p.total, p.created_at, p.updated_at,
                p.fecha_requerida, p.fecha_programada, p.transporte_linea, p.area_flujo,
                cli.id AS cliente_id, cli.nombre AS cliente_nombre,
                op.id AS operador_id, op.nombre AS operador_nombre
            FROM pedidos p
            JOIN usuarios cli ON cli.id = p.cliente_id
            LEFT JOIN usuarios op ON op.id = p.operador_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.created_at DESC
            LIMIT {$limit}
        ";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();

        if (!empty($rows)) {
            $pedidoIds = [];
            foreach ($rows as $r) {
                $pedidoIds[] = (int)($r['id'] ?? 0);
            }
            $placeholders = implode(',', array_fill(0, count($pedidoIds), '?'));
            $stItems = $pdo->prepare("
                SELECT
                    pi.pedido_id, pi.producto_id, p.codigo, p.nombre, pi.cantidad, pi.precio_unit, pi.ajuste_cliente
                FROM pedido_items pi
                JOIN productos p ON p.id = pi.producto_id
                WHERE pi.pedido_id IN ($placeholders)
                ORDER BY pi.pedido_id DESC, p.nombre ASC
            ");
            $stItems->execute($pedidoIds);
            $itemsRaw = $stItems->fetchAll();

            $itemsByPedido = [];
            foreach ($itemsRaw as $it) {
                $pid = (int)$it['pedido_id'];
                if (!isset($itemsByPedido[$pid])) $itemsByPedido[$pid] = [];
                $itemsByPedido[$pid][] = $it;
            }
            foreach ($rows as &$row) {
                $row['items'] = $itemsByPedido[(int)$row['id']] ?? [];
            }
            unset($row);
        }
        jsonResponse($rows);

    case 'programar_entrega':
        if (!esOperador() && !esAdmin()) jsonResponse(['error' => 'Sin permiso'], 403);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') jsonResponse(['error' => 'Metodo no permitido'], 405);

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) jsonResponse(['error' => 'Datos invalidos'], 422);
        $targetPedidoId = (int)($body['pedido_id'] ?? $pedidoId);
        if ($targetPedidoId <= 0) jsonResponse(['error' => 'Pedido invalido'], 422);

        $prioridad = trim((string)($body['prioridad'] ?? 'media'));
        $prioridades = ['baja', 'media', 'alta', 'urgente'];
        if (!in_array($prioridad, $prioridades, true)) $prioridad = 'media';

        $fechaProgramada = normalizarFechaApi((string)($body['fecha_programada'] ?? ''));
        $transporteLinea = trim((string)($body['transporte_linea'] ?? ''));
        $plDocumento = trim((string)($body['pl_documento'] ?? ''));
        $nota = trim((string)($body['nota'] ?? ''));

        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT cliente_id FROM pedidos WHERE id = ? FOR UPDATE");
            $st->execute([$targetPedidoId]);
            $pedido = $st->fetch();
            if (!$pedido) throw new RuntimeException('Pedido no encontrado');

            $upd = $pdo->prepare("
                UPDATE pedidos
                SET prioridad = ?, fecha_programada = ?, transporte_linea = ?, pl_documento = ?, area_flujo = 'logistica', updated_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([
                $prioridad,
                $fechaProgramada,
                $transporteLinea !== '' ? mb_substr($transporteLinea, 0, 120) : null,
                $plDocumento !== '' ? mb_substr($plDocumento, 0, 140) : null,
                $targetPedidoId
            ]);

            registrarHistorialPedido(
                $pdo,
                $targetPedidoId,
                'programado',
                (int)$u['usuario_id'],
                $nota !== '' ? $nota : 'Pedido programado para entrega/logistica'
            );
            registrarNotificacionesEstandarPedido(
                $pdo,
                $targetPedidoId,
                (int)$pedido['cliente_id'],
                'entrega_programada',
                'Tu pedido fue programado para entrega.'
            );
            registrarAuditoria($pdo, (int)$u['usuario_id'], (string)($u['rol'] ?? ''), 'logistica', 'programar_entrega', 'pedido', $targetPedidoId, 'Pedido programado');
            $pdo->commit();
            jsonResponse(['success' => true]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['error' => $e->getMessage()], 409);
        }

    case 'enviar_embarque':
        if (!esOperador() && !esAdmin()) jsonResponse(['error' => 'Sin permiso'], 403);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') jsonResponse(['error' => 'Metodo no permitido'], 405);
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT cliente_id FROM pedidos WHERE id = ? FOR UPDATE");
            $st->execute([$pedidoId]);
            $pedido = $st->fetch();
            if (!$pedido) throw new RuntimeException('Pedido no encontrado');

            $pdo->prepare("UPDATE pedidos SET area_flujo = 'embarque', updated_at = NOW() WHERE id = ?")
                ->execute([$pedidoId]);
            registrarHistorialPedido($pdo, $pedidoId, 'embarque', (int)$u['usuario_id'], 'Pedido enviado a embarque');
            registrarNotificacionesEstandarPedido(
                $pdo,
                $pedidoId,
                (int)$pedido['cliente_id'],
                'pedido_embarque',
                'Tu pedido paso al area de embarque para preparacion.'
            );
            registrarAuditoria($pdo, (int)$u['usuario_id'], (string)($u['rol'] ?? ''), 'embarque', 'enviar_embarque', 'pedido', $pedidoId, 'Pedido enviado a embarque');
            $pdo->commit();
            jsonResponse(['success' => true]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['error' => $e->getMessage()], 409);
        }

    case 'notificaciones':
        if (!estaLogueado()) jsonResponse(['error' => 'No autenticado'], 401);
        $targetPedidoId = (int)($_GET['pedido_id'] ?? 0);
        $limit = (int)($_GET['limit'] ?? 100);
        $limit = max(1, min($limit, 500));

        $where = ["1=1"];
        $params = [];
        if ($targetPedidoId > 0) {
            $where[] = "n.pedido_id = ?";
            $params[] = $targetPedidoId;
        }
        if (esCliente()) {
            $where[] = "n.cliente_id = ?";
            $params[] = (int)$u['usuario_id'];
        }

        $st = $pdo->prepare("
            SELECT n.*, p.estado AS pedido_estado
            FROM notificaciones_eventos n
            JOIN pedidos p ON p.id = n.pedido_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY n.id DESC
            LIMIT {$limit}
        ");
        $st->execute($params);
        jsonResponse($st->fetchAll());

    case 'inventario_alertas':
        if (!esAdmin() && !esOperador()) jsonResponse(['error' => 'Sin permiso'], 403);
        $minimo = (int)($_GET['minimo'] ?? 20);
        $minimo = max(0, min($minimo, 1000000));
        $st = $pdo->prepare("
            SELECT id, codigo, nombre, stock, activo
            FROM productos
            WHERE activo = 1 AND stock <= ?
            ORDER BY stock ASC, nombre ASC
            LIMIT 500
        ");
        $st->execute([$minimo]);
        jsonResponse($st->fetchAll());

    case 'catalogo_transportes':
        if (!esAdmin() && !esOperador()) jsonResponse(['error' => 'Sin permiso'], 403);
        jsonResponse(
            $pdo->query("SELECT id, nombre, capacidad_kg, activo FROM transporte_lineas WHERE activo = 1 ORDER BY nombre ASC")->fetchAll()
        );

    case 'timbrar_cfdi':
        if (!esAdmin()) jsonResponse(['error' => 'Sin permiso'], 403);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') jsonResponse(['error' => 'Metodo no permitido'], 405);
        $targetPedidoId = (int)($_GET['pedido_id'] ?? $pedidoId);
        if ($targetPedidoId <= 0) jsonResponse(['error' => 'Pedido invalido'], 422);

        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT id, cliente_id, estado, total, cfdi_status FROM pedidos WHERE id = ? FOR UPDATE");
            $st->execute([$targetPedidoId]);
            $pedido = $st->fetch();
            if (!$pedido) throw new RuntimeException('Pedido no encontrado');
            if ($pedido['estado'] !== 'entregado') throw new RuntimeException('Solo se puede timbrar un pedido entregado');
            if (($pedido['cfdi_status'] ?? 'pendiente') === 'timbrado') throw new RuntimeException('El pedido ya fue timbrado');

            $uuid = strtoupper(substr(bin2hex(random_bytes(20)), 0, 36));
            $pdfUrl = '/cfdi/' . $targetPedidoId . '/' . $uuid . '.pdf';
            $xmlUrl = '/cfdi/' . $targetPedidoId . '/' . $uuid . '.xml';

            $pdo->prepare("
                UPDATE pedidos
                SET cfdi_status = 'timbrado', cfdi_uuid = ?, cfdi_pdf_url = ?, cfdi_xml_url = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$uuid, $pdfUrl, $xmlUrl, $targetPedidoId]);

            registrarEventoCfdi($pdo, $targetPedidoId, 'timbrado', 'Timbrado generado (modo demo)', [
                'uuid' => $uuid,
                'pdf_url' => $pdfUrl,
                'xml_url' => $xmlUrl,
                'total' => (float)$pedido['total']
            ]);
            registrarHistorialPedido($pdo, $targetPedidoId, 'cfdi_timbrado', (int)$u['usuario_id'], 'CFDI generado');
            registrarNotificacionEvento(
                $pdo,
                $targetPedidoId,
                (int)$pedido['cliente_id'],
                'email',
                'cfdi_timbrado',
                'Tu CFDI fue generado y esta listo para envio.'
            );
            registrarAuditoria($pdo, (int)$u['usuario_id'], (string)($u['rol'] ?? ''), 'cfdi', 'timbrar', 'pedido', $targetPedidoId, 'CFDI timbrado UUID ' . $uuid);
            $pdo->commit();
            jsonResponse([
                'success' => true,
                'uuid' => $uuid,
                'pdf_url' => $pdfUrl,
                'xml_url' => $xmlUrl
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['error' => $e->getMessage()], 409);
        }

    default:
        jsonResponse(['error' => 'Accion no valida'], 400);
}

function haversine($lat1, $lon1, $lat2, $lon2) {
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $R * 2 * asin(sqrt($a));
}

function normalizarFechaApi(string $valor): ?string {
    $valor = trim($valor);
    if ($valor === '') return null;
    $tmp = str_replace('T', ' ', $valor);
    $dt = DateTime::createFromFormat('Y-m-d H:i', $tmp) ?: DateTime::createFromFormat('Y-m-d H:i:s', $tmp);
    return $dt ? $dt->format('Y-m-d H:i:s') : null;
}

function usuarioPuedeVerPedido(PDO $pdo, int $pedidoId, array $u): bool {
    if ($pedidoId <= 0) return false;
    if (esAdmin()) return true;

    $uid = (int)($u['usuario_id'] ?? 0);
    if ($uid <= 0) return false;

    $st = $pdo->prepare('SELECT cliente_id, operador_id FROM pedidos WHERE id=? LIMIT 1');
    $st->execute([$pedidoId]);
    $pedido = $st->fetch();
    if (!$pedido) return false;

    return $uid === (int)$pedido['cliente_id'] || $uid === (int)$pedido['operador_id'];
}

function asegurarTablaCancelacionesPedido(PDO $pdo): void {
    static $ready = false;
    if ($ready) return;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pedido_cancelaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pedido_id INT NOT NULL,
            solicitado_por ENUM('cliente','operador') NOT NULL,
            solicitante_id INT NOT NULL,
            motivo VARCHAR(400) NOT NULL,
            estado_solicitud ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
            revisado_por INT NULL,
            nota_revision VARCHAR(400) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_cancelaciones_pedido (pedido_id),
            INDEX idx_cancelaciones_estado (estado_solicitud),
            FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $ready = true;
}

function asegurarTablaCalificacionesPedido(PDO $pdo): void {
    static $ready = false;
    if ($ready) return;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pedido_calificaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pedido_id INT NOT NULL,
            cliente_id INT NOT NULL,
            estrellas TINYINT NOT NULL,
            comentario VARCHAR(400) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_calif_pedido_cliente (pedido_id, cliente_id),
            INDEX idx_calif_pedido (pedido_id),
            FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $ready = true;
}

function isLocalDevRequest(): bool {
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
    if ($host === '') return false;
    $host = preg_replace('/:\d+$/', '', $host);
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function asegurarTablaChatAyudaOperador(PDO $pdo): void {
    static $ready = false;
    if ($ready) return;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_ayuda_operador (
            id INT AUTO_INCREMENT PRIMARY KEY,
            operador_id INT NOT NULL,
            admin_id INT NOT NULL DEFAULT 0,
            remitente_id INT NOT NULL,
            mensaje TEXT NOT NULL,
            ts DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_chat_ayuda_operador_lookup (operador_id, id),
            INDEX idx_chat_ayuda_operador_remitente (remitente_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $ready = true;
}

function asegurarTablaTrackingShares(PDO $pdo): void {
    static $ready = false;
    if ($ready) return;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tracking_shares (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pedido_id INT NOT NULL,
            created_by INT NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            last_access_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tracking_shares_pedido_active (pedido_id, revoked_at, expires_at),
            FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
            FOREIGN KEY (created_by) REFERENCES usuarios(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $ready = true;
}

function crearTrackingShare(PDO $pdo, int $pedidoId, int $createdBy, int $ttl): array {
    asegurarTablaTrackingShares($pdo);

    // Solo mantenemos un enlace activo por pedido para simplificar control de revocacion
    revocarTrackingShares($pdo, $pedidoId);

    $token = bin2hex(random_bytes(24));
    $tokenHash = hash_hmac('sha256', $token, trackingShareSecret());
    $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

    $ins = $pdo->prepare("
        INSERT INTO tracking_shares (pedido_id, created_by, token_hash, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $ins->execute([$pedidoId, $createdBy, $tokenHash, $expiresAt]);

    return [
        'token' => $token,
        'expires_at' => $expiresAt
    ];
}

function obtenerTrackingSharePorToken(PDO $pdo, string $token): ?array {
    if ($token === '') return null;
    asegurarTablaTrackingShares($pdo);

    $tokenHash = hash_hmac('sha256', $token, trackingShareSecret());
    $st = $pdo->prepare("
        SELECT id, pedido_id, expires_at
        FROM tracking_shares
        WHERE token_hash = ?
          AND revoked_at IS NULL
          AND expires_at > NOW()
        LIMIT 1
    ");
    $st->execute([$tokenHash]);
    $share = $st->fetch();
    if (!$share) return null;

    $pdo->prepare("UPDATE tracking_shares SET last_access_at = NOW() WHERE id = ?")->execute([(int)$share['id']]);
    return $share;
}

function obtenerTrackingShareActivo(PDO $pdo, int $pedidoId): ?array {
    asegurarTablaTrackingShares($pdo);

    $st = $pdo->prepare("
        SELECT id, expires_at
        FROM tracking_shares
        WHERE pedido_id = ?
          AND revoked_at IS NULL
          AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1
    ");
    $st->execute([$pedidoId]);
    $row = $st->fetch();
    return $row ?: null;
}

function revocarTrackingShares(PDO $pdo, int $pedidoId): int {
    asegurarTablaTrackingShares($pdo);

    $st = $pdo->prepare("
        UPDATE tracking_shares
        SET revoked_at = NOW()
        WHERE pedido_id = ?
          AND revoked_at IS NULL
          AND expires_at > NOW()
    ");
    $st->execute([$pedidoId]);
    return $st->rowCount();
}

function crearTokenTracking(int $pedidoId, int $ttl): string {
    $payload = [
        'pedido_id' => $pedidoId,
        'iat' => time(),
        'exp' => time() + $ttl,
        'nonce' => bin2hex(random_bytes(8))
    ];

    $payloadB64 = base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
    $firmaB64 = base64UrlEncode(hash_hmac('sha256', $payloadB64, trackingShareSecret(), true));
    return $payloadB64 . '.' . $firmaB64;
}

function validarTokenTracking(string $token): ?array {
    if ($token === '' || substr_count($token, '.') !== 1) return null;

    [$payloadB64, $firmaB64] = explode('.', $token, 2);
    if ($payloadB64 === '' || $firmaB64 === '') return null;

    $firmaEsperada = base64UrlEncode(hash_hmac('sha256', $payloadB64, trackingShareSecret(), true));
    if (!hash_equals($firmaEsperada, $firmaB64)) return null;

    $payloadJson = base64UrlDecode($payloadB64);
    if ($payloadJson === null) return null;

    $payload = json_decode($payloadJson, true);
    if (!is_array($payload)) return null;

    $exp = (int)($payload['exp'] ?? 0);
    if ($exp <= time()) return null;

    return $payload;
}

function construirUrlTrackingPublico(string $token): string {
    if (defined('APP_PUBLIC_BASE_URL') && APP_PUBLIC_BASE_URL !== '') {
        $base = rtrim((string)APP_PUBLIC_BASE_URL, '/');
        return $base . '/pages/tracking_publico.php?t=' . rawurlencode($token);
    }

    $httpsOn = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $httpsOn ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/login-demo/api/pedido.php');
    $basePath = rtrim(dirname(dirname($scriptName)), '/');

    return $scheme . '://' . $host . $basePath . '/pages/tracking_publico.php?t=' . rawurlencode($token);
}

function trackingShareSecret(): string {
    if (defined('TRACKING_SHARE_SECRET') && TRACKING_SHARE_SECRET !== '') {
        return (string)TRACKING_SHARE_SECRET;
    }

    $fallback = (defined('DB_NAME') ? DB_NAME : 'nexuspanel') . '|nexuspanel|tracking|v1';
    return hash('sha256', $fallback);
}

function base64UrlEncode(string $raw): string {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function base64UrlDecode(string $value): ?string {
    $remainder = strlen($value) % 4;
    if ($remainder > 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }

    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}



