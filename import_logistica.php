<?php
require_once 'includes/config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = file_get_contents('sql/modulo_logistica.sql');
    
    // El archivo SQL tiene múltiples sentencias, PDO::exec solo ejecuta una o depende de la configuración.
    // Usaremos un método para ejecutar múltiples sentencias si es posible, o dividiremos por ;
    
    // Nota: El archivo SQL tiene SET FOREIGN_KEY_CHECKS, etc.
    $pdo->exec($sql);
    
    echo "¡Módulo de logística importado correctamente!";
} catch (PDOException $e) {
    echo "Error al importar el módulo: " . $e->getMessage();
}
?>
