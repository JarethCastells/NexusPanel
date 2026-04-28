<?php
/**
 * Asigna imágenes a productos en MySQL
 * 1. Pon las imágenes en uploads/productos/ con nombres prod_1.jpg, prod_2.jpg...
 * 2. Abre: http://localhost/login-demo/asignar_imagenes.php
 * 3. Elimina este archivo después
 */
require_once 'includes/db.php';

$dir  = __DIR__ . '/uploads/productos/';
$imgs = glob($dir . 'prod_*.jpg') ?: [];
$ok   = 0;

foreach ($imgs as $file) {
    preg_match('/prod_(\d+)\.jpg/', basename($file), $m);
    if (!isset($m[1])) continue;
    $pdo->prepare("UPDATE productos SET imagen=? WHERE id=?")->execute([basename($file), (int)$m[1]]);
    $ok++;
}

$sin = $pdo->query("SELECT id, nombre FROM productos WHERE imagen IS NULL OR imagen = '' ORDER BY id")->fetchAll();
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Asignar Imágenes</title>
<style>
body{font-family:sans-serif;background:#060a12;color:#f0f6ff;padding:40px;max-width:600px;margin:0 auto;}
.card{background:#0d1422;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:24px;margin-bottom:16px;}
.ok{color:#10b981;} .warn{color:#f59e0b;} .err{color:#ef4444;}
code{background:#1e293b;padding:2px 8px;border-radius:4px;font-size:13px;}
.btn{display:inline-block;margin-top:16px;padding:12px 24px;background:linear-gradient(135deg,#00d4ff,#7c3aed);color:#fff;border-radius:10px;text-decoration:none;font-weight:600;}
ul{margin-top:10px;padding-left:20px;}
li{font-size:13px;color:#94a3b8;padding:3px 0;}
</style></head>
<body>
<h2>🖼️ Asignar imágenes a productos</h2>

<div class="card">
    <?php if ($ok > 0): ?>
    <p class="ok">✅ <?= $ok ?> imágenes asignadas correctamente en MySQL.</p>
    <?php else: ?>
    <p class="err">❌ No se encontraron imágenes en <code>uploads/productos/</code></p>
    <p style="font-size:13px;color:#94a3b8;margin-top:12px;">Pon las imágenes ahí con el formato: <code>prod_1.jpg</code>, <code>prod_2.jpg</code>, etc.</p>
    <?php endif; ?>
</div>

<?php if ($sin): ?>
<div class="card">
    <p class="warn">⚠️ <?= count($sin) ?> productos sin imagen:</p>
    <ul>
        <?php foreach($sin as $p): ?>
        <li><code>prod_<?= $p['id'] ?>.jpg</code> → <?= htmlspecialchars($p['nombre']) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php else: ?>
<div class="card">
    <p class="ok">✅ Todos los productos tienen imagen asignada.</p>
</div>
<?php endif; ?>

<p style="font-size:12px;color:#475569;margin-top:20px;">⚠️ Elimina este archivo después: <code>asignar_imagenes.php</code></p>
<?php if ($ok > 0): ?><a href="pages/productos.php" class="btn">Ver productos →</a><?php endif; ?>
</body></html>
