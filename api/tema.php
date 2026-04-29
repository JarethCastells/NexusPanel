<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $tema = trim($input['tema'] ?? 'dark');

    if (in_array($tema, ['dark', 'palette'])) {
        try {
            $usuarioId = (int)$_SESSION['usuario_id'];
            // Usar una transaccion o asegurar el guardado
            $st = $pdo->prepare("INSERT INTO usuario_temas (user_id, tema) VALUES (?, ?) ON DUPLICATE KEY UPDATE tema = VALUES(tema)");
            $st->execute([$usuarioId, $tema]);
            
            $_SESSION['tema'] = $tema;
            jsonResponse(['success' => true, 'tema' => $tema]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    } else {
        jsonResponse(['success' => false, 'error' => 'Tema invalido'], 400);
    }
}
jsonResponse(['success' => false], 405);
