<?php
/**
 * SETUP DE BASE DE DATOS — NexusPanel
 * Ejecutar UNA SOLA VEZ en: http://localhost/login-demo/setup_db.php
 * Luego ELIMINAR este archivo.
 */
require_once 'includes/config.php';

$log = [];
$errores = 0;

function run($pdo, $sql, $desc) {
    global $log, $errores;
    try {
        $pdo->exec($sql);
        $log[] = ['ok', $desc];
    } catch (PDOException $e) {
        $log[] = ['err', "$desc — " . $e->getMessage()];
        $errores++;
    }
}

try {
    // Conectar sin seleccionar BD para crearla si no existe
    $pdo0 = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    run($pdo0, "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", "Crear base de datos '" . DB_NAME . "'");

    // Conectar a la BD
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // ── TABLAS ──────────────────────────────────────────────

    run($pdo, "CREATE TABLE IF NOT EXISTS usuarios (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        nombre        VARCHAR(120) NOT NULL,
        email         VARCHAR(180) UNIQUE NOT NULL,
        password      VARCHAR(255) NOT NULL,
        rol           ENUM('administrador','inventario','distribucion','operador','cliente') NOT NULL,
        domicilio     VARCHAR(255),
        edad          TINYINT UNSIGNED,
        telefono      VARCHAR(20),
        lat           DOUBLE,
        lng           DOUBLE,
        zona_radio    DOUBLE DEFAULT 50,
        activo        TINYINT(1) DEFAULT 1,
        ultimo_acceso DATETIME,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Crear tabla usuarios");

    run($pdo, "CREATE TABLE IF NOT EXISTS productos (
        id      INT AUTO_INCREMENT PRIMARY KEY,
        codigo  VARCHAR(50) UNIQUE NOT NULL,
        nombre  VARCHAR(255) NOT NULL,
        precio  DECIMAL(10,2) NOT NULL DEFAULT 0,
        stock   INT DEFAULT 100,
        activo  TINYINT(1) DEFAULT 1,
        imagen  VARCHAR(100)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Crear tabla productos");

    run($pdo, "CREATE TABLE IF NOT EXISTS pedidos (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        cliente_id        INT NOT NULL,
        operador_id       INT,
        estado            ENUM('pendiente','aceptado','en_camino','entregado','cancelado') DEFAULT 'pendiente',
        total             DECIMAL(10,2) DEFAULT 0,
        lat_entrega       DOUBLE,
        lng_entrega       DOUBLE,
        domicilio_entrega VARCHAR(255),
        created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (cliente_id)  REFERENCES usuarios(id),
        FOREIGN KEY (operador_id) REFERENCES usuarios(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Crear tabla pedidos");

    run($pdo, "CREATE TABLE IF NOT EXISTS pedido_items (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        pedido_id   INT NOT NULL,
        producto_id INT NOT NULL,
        cantidad    INT NOT NULL DEFAULT 1,
        precio_unit DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (pedido_id)   REFERENCES pedidos(id),
        FOREIGN KEY (producto_id) REFERENCES productos(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Crear tabla pedido_items");

    run($pdo, "CREATE TABLE IF NOT EXISTS tracking (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        pedido_id   INT NOT NULL,
        operador_id INT NOT NULL,
        lat         DOUBLE NOT NULL,
        lng         DOUBLE NOT NULL,
        ts          DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pedido_id)   REFERENCES pedidos(id),
        FOREIGN KEY (operador_id) REFERENCES usuarios(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Crear tabla tracking");

    run($pdo, "CREATE TABLE IF NOT EXISTS tracking_shares (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        pedido_id     INT NOT NULL,
        created_by    INT NOT NULL,
        token_hash    CHAR(64) NOT NULL UNIQUE,
        expires_at    DATETIME NOT NULL,
        revoked_at    DATETIME NULL,
        last_access_at DATETIME NULL,
        created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tracking_shares_pedido_active (pedido_id, revoked_at, expires_at),
        FOREIGN KEY (pedido_id)  REFERENCES pedidos(id),
        FOREIGN KEY (created_by) REFERENCES usuarios(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Crear tabla tracking_shares");

    run($pdo, "CREATE TABLE IF NOT EXISTS chat (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        pedido_id  INT NOT NULL,
        usuario_id INT NOT NULL,
        mensaje    TEXT NOT NULL,
        ts         DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pedido_id)  REFERENCES pedidos(id),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Crear tabla chat");

    run($pdo, "CREATE TABLE IF NOT EXISTS login_throttles (
        bucket       VARCHAR(190) PRIMARY KEY,
        hits         INT NOT NULL DEFAULT 0,
        window_start DATETIME NOT NULL,
        blocked_until DATETIME NULL,
        updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Crear tabla login_throttles");

    // ── SEED USUARIOS ────────────────────────────────────────
    $count = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    if ($count == 0) {
        $st = $pdo->prepare("INSERT INTO usuarios (nombre,email,password,rol,domicilio,edad,telefono,lat,lng,zona_radio) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $users = [
            ['Admin Principal', 'admin@demo.com',     password_hash('admin123',    PASSWORD_DEFAULT), 'administrador', 'Av. Insurgentes Sur 123, CDMX',          35, '5511223344', 19.4326, -99.1332, 9999],
            ['Coordinacion Inventario', 'inventario@demo.com', password_hash('inventario123', PASSWORD_DEFAULT), 'inventario', 'Centro logistico NexusPanel', 30, '5500000000', 19.4326, -99.1332, 9999],
            ['Carlos López',    'operador@demo.com',  password_hash('operador123', PASSWORD_DEFAULT), 'operador',      'Calle Sonora 45, Col. Roma, CDMX',        28, '5599887766', 19.4194, -99.1599, 15],
            ['Ana Martínez',    'operador2@demo.com', password_hash('operador123', PASSWORD_DEFAULT), 'operador',      'Blvd. Manuel Ávila Camacho 32, GDL',      31, '3312345678', 20.6597, -103.3496, 15],
            ['Juan Cliente',    'cliente@demo.com',   password_hash('cliente123',  PASSWORD_DEFAULT), 'cliente',       'Av. Álvaro Obregón 88, Col. Roma, CDMX',  25, '5522334455', 19.4180, -99.1560, 0],
        ];
        foreach ($users as $u) $st->execute($u);
        $log[] = ['ok', 'Seed: 5 usuarios creados'];
    } else {
        $log[] = ['skip', "Usuarios ya existen ($count registros) — seed omitido"];
    }

    // ── SEED PRODUCTOS ───────────────────────────────────────
    $countP = $pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
    if ($countP == 0) {
        $st = $pdo->prepare("INSERT INTO productos (codigo,nombre,precio) VALUES (?,?,?)");
        $prods = [
            ['101190055', 'AB20 MEXICO PAH5',                 1250.00],
            ['173931925', 'ANIMARETO OMNIPLUS',                890.00],
            ['10010407',  'AUREO S700 36G/LBX50LB TB ES',    2100.00],
            ['10023673',  'AUROFAC200 200GA/KGX25KG BG',     1750.00],
            ['10009982',  'AVATEC 15% 150G/KGX20KG TB MX',   1980.00],
            ['260221',    'AVIAX 5% 25 KG MEXICO',           3200.00],
            ['299088',    'AVIAX PLUS 25KG MEXICO',          3450.00],
            ['299001',    'AVICARB',                           560.00],
            ['173560925', 'BEEF CATTLE SUPREME PREMIX',      2800.00],
            ['10009727',  'BMD 11% 110G/KGX25KG TB ES',     1650.00],
            ['4265009',   'BOVENSIN 20 25 KG BAG',           2200.00],
            ['10010469',  'BVTC 15% 150G/KGX25KG TB ES',   1820.00],
            ['80063400',  'CALCIUM IODATE 63.5% I',            740.00],
            ['173463925', 'CCF GAQSA PMX',                   1100.00],
            ['137106055', 'CELLERATE CULT CLASSC PLUS MEX',  4500.00],
            ['4260603',   'CERDIMIX 15 25 KG BAG',           1350.00],
            ['80551433',  'COBALT CARB 46% 15KG',              920.00],
            ['4260608',   'COXISTAC 12% 25 KG BAG',          2750.00],
            ['8511037',   'EPHICAX 110 25KG BAG',            2100.00],
            ['4260300',   'ESKALIN 25 25 KG BAG',            1480.00],
            ['10014064',  'DECCOX 6% 60G/KGX25KG TB ES',    1900.00],
            ['4260412',   'STAFAC 40 25 KG BAG',             1620.00],
            ['6185000',   'STAFAC 500 25 KG BOX',            3100.00],
            ['8319023',   'PAQ-PROTEX TM 25 KG BAG',         1780.00],
            ['77167925',  'VISTORE CU 580 MEXICO',           2350.00],
            ['10-804547', 'TABIC IB VAR 5000 DS',            5800.00],
            ['10-806541', 'TABIC IBVAR206 5000 DS',          6200.00],
        ];
        foreach ($prods as $p) $st->execute($p);
        $log[] = ['ok', 'Seed: 27 productos Phibro creados'];
    } else {
        $log[] = ['skip', "Productos ya existen ($countP registros) — seed omitido"];
    }

} catch (PDOException $e) {
    $log[] = ['err', 'Error fatal: ' . $e->getMessage()];
    $errores++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Setup DB — NexusPanel</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#060a12;color:#f0f6ff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:30px;}
.card{background:#0d1422;border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:32px;max-width:600px;width:100%;}
h1{font-size:22px;margin-bottom:6px;display:flex;align-items:center;gap:10px;}
.sub{color:#64748b;font-size:13px;margin-bottom:24px;}
.log-item{display:flex;align-items:flex-start;gap:10px;padding:9px 14px;border-radius:8px;margin-bottom:6px;font-size:13px;}
.log-item.ok  {background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.15);}
.log-item.err {background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.2);}
.log-item.skip{background:rgba(100,116,139,.08);border:1px solid rgba(100,116,139,.15);color:#94a3b8;}
.icon{font-size:15px;margin-top:1px;flex-shrink:0;}
.ok  .icon{color:#10b981} .err .icon{color:#ef4444} .skip .icon{color:#64748b}
.footer{margin-top:24px;padding-top:20px;border-top:1px solid rgba(255,255,255,.06);display:flex;gap:12px;flex-wrap:wrap;}
.btn{padding:12px 22px;border-radius:10px;font-size:14px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all .2s;}
.btn-primary{background:linear-gradient(135deg,#00d4ff,#7c3aed);color:#fff;}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(0,212,255,.25);}
.btn-danger{background:rgba(239,68,68,.1);color:#fca5a5;border:1px solid rgba(239,68,68,.2);}
.btn-danger:hover{background:rgba(239,68,68,.2);}
.summary{padding:14px;border-radius:10px;margin-bottom:20px;font-size:14px;font-weight:600;}
.summary.success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);color:#6ee7b7;}
.summary.error  {background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.2); color:#fca5a5;}
</style>
</head>
<body>
<div class="card">
    <h1>⚙️ Setup de Base de Datos</h1>
    <p class="sub">NexusPanel — MySQL / XAMPP</p>

    <div class="summary <?= $errores ? 'error' : 'success' ?>">
        <?= $errores ? "❌ $errores error(s) encontrado(s). Revisa los detalles abajo." : "✅ Todo configurado correctamente. ¡El sistema está listo!" ?>
    </div>

    <?php foreach ($log as [$tipo, $msg]): ?>
    <div class="log-item <?= $tipo ?>">
        <span class="icon"><?= $tipo==='ok' ? '✅' : ($tipo==='err' ? '❌' : '⏭️') ?></span>
        <span><?= htmlspecialchars($msg) ?></span>
    </div>
    <?php endforeach; ?>

    <?php if (!$errores): ?>
    <!-- Setup de imágenes -->
    <?php
    $imgDir = __DIR__ . '/uploads/productos/';
    $imgs   = glob($imgDir . 'prod_*.jpg') ?: [];
    if (count($imgs) > 0):
        // Asignar imágenes a productos
        foreach ($imgs as $file) {
            preg_match('/prod_(\d+)\.jpg/', basename($file), $m);
            if (isset($m[1])) {
                try { $pdo->prepare("UPDATE productos SET imagen=? WHERE id=?")->execute([basename($file), (int)$m[1]]); } catch(Exception $e){}
            }
        }
    ?>
    <div class="log-item ok" style="margin-top:8px;">
        <span class="icon">🖼️</span>
        <span><?= count($imgs) ?> imágenes de productos asignadas automáticamente</span>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <div class="footer">
        <?php if (!$errores): ?>
        <a href="index.php" class="btn btn-primary">🚀 Ir al sistema</a>
        <?php endif; ?>
        <a href="setup_db.php" class="btn btn-danger" onclick="return confirm('¿Volver a ejecutar el setup?')">🔄 Re-ejecutar</a>
    </div>

    <p style="margin-top:20px;font-size:11px;color:#475569;">⚠️ Elimina este archivo después de usarlo: <code>setup_db.php</code></p>
</div>
</body>
</html>
