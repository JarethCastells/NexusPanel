<?php
require_once 'includes/config.php';
$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);

$rol_a_eliminar = 'cliente';
$stmt = $pdo->prepare("DELETE FROM usuarios WHERE rol = ?");
$stmt->execute([$rol_a_eliminar]);

$filas = $stmt->rowCount();
echo "Se han eliminado $filas usuarios con el rol de '$rol_a_eliminar'.";

unlink(__FILE__);
