<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$db   = 'teotekco_nexuspanel';
$user = 'teotekco_nexuspanel';
$pass = 'nexusSecret2026'; // Con n minúscula ahora

echo "<h3>Probando conexión a la Base de Datos...</h3>";
echo "Host: $host <br>Usuario: $user <br>Base de Datos: $db <hr>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    echo "<h4 style='color:green;'>✅ ¡CONEXIÓN EXITOSA! El sistema debería funcionar ahora.</h4>";
} catch (PDOException $e) {
    echo "<h4 style='color:red;'>❌ ERROR DE CONEXIÓN:</h4>";
    echo $e->getMessage();
    echo "<br><br><b>Sugerencias:</b><br>";
    echo "1. Revisa que el usuario '$user' tenga permisos asignados a la BD '$db' en tu panel de hosting.<br>";
    echo "2. Verifica que la contraseña sea exactamente la que pusiste.<br>";
    echo "3. Algunos hostings usan un host diferente a 'localhost' (ej. mysql.tudominio.com).";
}
