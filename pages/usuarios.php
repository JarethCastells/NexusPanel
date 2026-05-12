<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireAdmin();

$msg = '';
$error = '';

function findUsuarioPorId(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare("SELECT id, rol FROM usuarios WHERE id = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function fotoPerfilUrl(array $u): string {
    $nombre = trim((string)($u['nombre'] ?? 'Usuario'));
    return 'https://ui-avatars.com/api/?name=' . rawurlencode($nombre) . '&background=0f172a&color=22d3ee&size=96';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $pass   = trim($_POST['password'] ?? '');
        $rol    = $_POST['rol'] ?? 'operador';
        $camionGrande = isset($_POST['camion_grande']) ? 1 : 0;
        $capacidadPedidos = (int)($_POST['capacidad_pedidos'] ?? 5);
        if ($capacidadPedidos < 1) $capacidadPedidos = 1;
        if ($capacidadPedidos > 20) $capacidadPedidos = 20;

        if ($nombre && $email && $pass) {
            try {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol, camion_grande, capacidad_pedidos) VALUES (?,?,?,?,?,?)")
                    ->execute([$nombre, $email, $hash, $rol, $camionGrande, $capacidadPedidos]);
                $msg = "Usuario '$nombre' creado correctamente.";
            } catch (PDOException $e) {
                $error = 'Error: El correo ya existe.';
            }
        } else {
            $error = 'Completa todos los campos.';
        }
    }

    if ($_POST['action'] === 'toggle') {
        $id = (int)$_POST['id'];
        $target = findUsuarioPorId($pdo, $id);
        if (!$target) {
            $error = 'Usuario no encontrado.';
        } elseif ($id === (int)$_SESSION['usuario_id']) {
            $error = 'No puedes desactivar tu propia cuenta.';
        } elseif (($target['rol'] ?? '') === 'administrador') {
            $error = 'No se puede desactivar una cuenta de administrador.';
        } else {
            $pdo->prepare('UPDATE usuarios SET activo = 1 - activo WHERE id = ?')->execute([$id]);
            $msg = 'Estado actualizado.';
        }
    }

    if ($_POST['action'] === 'eliminar') {
        $id = (int)$_POST['id'];
        $target = findUsuarioPorId($pdo, $id);
        if (!$target) {
            $error = 'Usuario no encontrado.';
        } elseif ($id == (int)$_SESSION['usuario_id']) {
            $error = 'No puedes eliminar tu propia cuenta.';
        } elseif (($target['rol'] ?? '') === 'administrador') {
            $error = 'No se puede eliminar una cuenta de administrador.';
        } else {
            $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
            $msg = 'Usuario eliminado.';
        }
    }

    if ($_POST['action'] === 'editar') {
        $id       = (int)($_POST['id'] ?? 0);
        $target   = findUsuarioPorId($pdo, $id);
        $nombre   = trim($_POST['nombre'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $domicilio= trim($_POST['domicilio'] ?? '');
        $edadRaw  = trim($_POST['edad'] ?? '');
        $rol      = $_POST['rol'] ?? 'cliente';
        $password = trim($_POST['password'] ?? '');
        $camionGrande = isset($_POST['camion_grande']) ? 1 : 0;
        $capacidadPedidos = (int)($_POST['capacidad_pedidos'] ?? 5);
        if ($capacidadPedidos < 1) $capacidadPedidos = 1;
        if ($capacidadPedidos > 20) $capacidadPedidos = 20;
        $edad     = ($edadRaw === '') ? null : (int)$edadRaw;

        if (!$target) {
            $error = 'Usuario no encontrado.';
        } elseif ($nombre === '' || $email === '') {
            $error = 'Nombre y correo son obligatorios.';
        } else {
            if (($target['rol'] ?? '') === 'administrador') {
                $rol = 'administrador';
            } elseif (!in_array($rol, ['administrador', 'operador', 'cliente'], true)) {
                $rol = 'cliente';
            }

            try {
                if ($password !== '') {
                    if (strlen($password) < 6) {
                        throw new RuntimeException('La nueva contrasena debe tener al menos 6 caracteres.');
                    }
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare("\n                        UPDATE usuarios\n                        SET nombre=?, email=?, telefono=?, domicilio=?, edad=?, rol=?, camion_grande=?, capacidad_pedidos=?, password=?\n                        WHERE id=?\n                    ")->execute([$nombre, $email, $telefono ?: null, $domicilio ?: null, $edad, $rol, $camionGrande, $capacidadPedidos, $hash, $id]);
                } else {
                    $pdo->prepare("\n                        UPDATE usuarios\n                        SET nombre=?, email=?, telefono=?, domicilio=?, edad=?, rol=?, camion_grande=?, capacidad_pedidos=?\n                        WHERE id=?\n                    ")->execute([$nombre, $email, $telefono ?: null, $domicilio ?: null, $edad, $rol, $camionGrande, $capacidadPedidos, $id]);
                }
                $msg = 'Usuario actualizado correctamente.';
            } catch (RuntimeException $ex) {
                $error = $ex->getMessage();
            } catch (PDOException $e) {
                $error = 'No se pudo actualizar el usuario (correo duplicado o datos invalidos).';
            }
        }
    }
}

$usuarios = $pdo->query('SELECT * FROM usuarios ORDER BY id DESC')->fetchAll();
$clientes = array_values(array_filter($usuarios, static function ($u) {
    return (($u['rol'] ?? '') === 'cliente');
}));
$inicioMes = date('Y-m-01 00:00:00');

$stResumenCompras = $pdo->query("\n    SELECT\n        p.cliente_id AS usuario_id,\n        COUNT(DISTINCT p.id) AS pedidos,\n        COALESCE(SUM(pi.cantidad), 0) AS unidades,\n        MAX(p.created_at) AS ultima_compra\n    FROM pedidos p\n    LEFT JOIN pedido_items pi ON pi.pedido_id = p.id\n    WHERE p.estado <> 'cancelado'\n    GROUP BY p.cliente_id\n");
$resumenCompras = [];
foreach ($stResumenCompras as $r) {
    $resumenCompras[(int)$r['usuario_id']] = $r;
}

$stTopCategorias = $pdo->query("\n    SELECT\n        p.cliente_id AS usuario_id,\n        COALESCE(e.nombre, 'Sin categoria') AS categoria,\n        SUM(pi.cantidad) AS unidades\n    FROM pedidos p\n    JOIN pedido_items pi ON pi.pedido_id = p.id\n    LEFT JOIN productos pr ON pr.id = pi.producto_id\n    LEFT JOIN especies e ON e.id = pr.especie_id\n    WHERE p.estado <> 'cancelado'\n    GROUP BY p.cliente_id, COALESCE(e.nombre, 'Sin categoria')\n    ORDER BY p.cliente_id ASC, unidades DESC\n");
$topCategorias = [];
foreach ($stTopCategorias as $r) {
    $uid = (int)$r['usuario_id'];
    if (!isset($topCategorias[$uid])) $topCategorias[$uid] = [];
    if (count($topCategorias[$uid]) < 3) {
        $topCategorias[$uid][] = [
            'categoria' => (string)$r['categoria'],
            'unidades' => (int)$r['unidades'],
        ];
    }
}

$usuariosMapa = array_values(array_map(static function ($u) {
    return [
        'id' => (int)$u['id'],
        'nombre' => (string)$u['nombre'],
        'rol' => (string)$u['rol'],
        'lat' => $u['lat'] !== null ? (float)$u['lat'] : null,
        'lng' => $u['lng'] !== null ? (float)$u['lng'] : null,
        'domicilio' => (string)($u['domicilio'] ?? ''),
    ];
}, $clientes));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Gestion de Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .usuarios-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 16px; margin-bottom: 16px; }
        .usuarios-mapa { height: 340px; border-radius: 14px; border: 1px solid var(--border); overflow: hidden; }
        .perfil-card-list { display: grid; gap: 12px; }
        .perfil-card { border: 1px solid var(--border); border-radius: 14px; background: rgba(255,255,255,0.02); overflow: hidden; }
        .perfil-card-head { display:flex; align-items:center; gap:12px; padding:12px 14px; cursor:pointer; }
        .perfil-foto { width:48px; height:48px; border-radius:50%; object-fit:cover; border:1px solid var(--border); }
        .perfil-main { flex:1; min-width:0; }
        .perfil-nombre { font-weight:600; color:var(--text-primary); display:flex; align-items:center; gap:8px; }
        .perfil-sub { color:var(--text-dim); font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .badge-new { font-size:10px; padding:2px 8px; border-radius:999px; border:1px solid rgba(16,185,129,0.4); color:#6ee7b7; }
        .perfil-toggle { color: var(--primary); }
        .perfil-body { display:none; padding:0 14px 14px; border-top:1px solid var(--border); }
        .perfil-card.open .perfil-body { display:block; }
        .perfil-meta { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-top:10px; }
        .meta-box { background:var(--bg-input); border:1px solid var(--border); border-radius:10px; padding:10px; }
        .meta-label { font-size:11px; color:var(--text-dim); text-transform:uppercase; letter-spacing:.6px; }
        .meta-value { font-size:13px; color:var(--text-primary); margin-top:4px; }
        .cat-chips { display:flex; gap:6px; flex-wrap:wrap; margin-top:8px; }
        .cat-chip { font-size:11px; padding:5px 9px; border-radius:999px; background:rgba(0,212,255,0.12); border:1px solid rgba(0,212,255,0.22); color:#7dd3fc; }
        .table-wrapper { overflow-x:auto; -webkit-overflow-scrolling:touch; }
        .data-table { min-width: 980px; }
        .modal-dark .modal-content { background: var(--bg-card); border:1px solid var(--border); border-radius:16px; color:var(--text-primary); }
        .modal-dark .modal-header { border-bottom:1px solid var(--border); padding:20px 24px; }
        .modal-dark .modal-footer { border-top:1px solid var(--border); }
        .modal-dark .btn-close { filter: invert(1) opacity(0.5); }
        .modal-input, .modal-select { width:100%; padding:12px 16px; background:var(--bg-input); border:1px solid var(--border); border-radius:10px; color:var(--text-primary); font-size:14px; }
        .btn-primary-custom { background:linear-gradient(135deg,var(--primary),var(--accent)); border:none; color:#fff; padding:10px 24px; border-radius:10px; font-weight:600; }
        .btn-secondary-custom { background:none; border:1px solid var(--border); color:var(--text-muted); padding:10px 24px; border-radius:10px; }
        .action-btn { width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:none;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px; }
        .action-btn.toggle-btn { color: var(--warning); }
        .action-btn.delete-btn { color: var(--error); }
        .maps-btn { color: #38bdf8; }
        .users-filter {
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--bg-input);
            color: var(--text-primary);
            font-size: 13px;
            margin-bottom: 12px;
        }
        .notif-count {
            position: absolute;
            top: -2px;
            right: -2px;
            min-width: 18px;
            height: 18px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }
        .notif-count.hidden { display: none; }
        .panel-noti-dropdown {
            width: 360px;
            max-width: calc(100vw - 24px);
            border: 1px solid var(--border);
            background: #0b1528;
        }
        .panel-noti-header {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #fff;
        }
        .panel-noti-list {
            max-height: 320px;
            overflow: auto;
            padding: 8px;
            display: grid;
            gap: 8px;
        }
        .panel-noti-item {
            display: block;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: rgba(255,255,255,.02);
            color: inherit;
            text-decoration: none;
            padding: 10px;
        }
        .panel-noti-item strong { display: block; font-size: 13px; color: #fff; }
        .panel-noti-item small { display: block; color: var(--text-muted); margin-top: 2px; font-size: 11px; }
        @media (max-width: 980px) {
            .usuarios-grid { grid-template-columns: 1fr; }
            .usuarios-mapa { height: 300px; }
        }
        @media (max-width: 768px) {
            .content-area { padding: 12px !important; }
            .welcome-banner { padding: 14px !important; gap: 10px !important; }
            .welcome-banner h1 { font-size: 20px !important; }
            .welcome-banner p { font-size: 13px !important; }
            .usuarios-mapa { height: 260px !important; }
            .perfil-meta { grid-template-columns: 1fr !important; }
            .users-filter { font-size: 14px; padding: 11px 12px; }
            .table-wrapper { margin: 0 -6px; padding: 0 6px; }
            .data-table { min-width: 760px !important; }
            .data-table th, .data-table td { padding: 11px 10px !important; font-size: 12px !important; }
            .action-btn { width: 34px; height: 34px; }
        }
    </style>
</head>
<body data-theme="<?= function_exists('temaActual') ? htmlspecialchars(temaActual()) : 'dark' ?>">
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo"><div class="logo-icon-sm"><i class="fa-solid fa-hexagon-nodes"></i></div><span class="logo-text-sm">Nexus<strong>Panel</strong></span></div>
    </div>
    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($_SESSION['nombre'],0,1)) ?></div>
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($_SESSION['nombre']) ?></span>
            <span class="user-role role-admin"><i class="fa-solid fa-shield-halved"></i> Administrador</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="dashboard.php" class="nav-item"><i class="fa-solid fa-chart-line"></i><span>Inicio</span></a>
        <a href="usuarios.php" class="nav-item active"><i class="fa-solid fa-users"></i><span>Usuarios</span><div class="nav-indicator"></div></a>
        <a href="productos.php" class="nav-item"><i class="fa-solid fa-flask-vial"></i><span>Productos e inventario</span></a>
        <a href="pedidos.php" class="nav-item"><i class="fa-solid fa-receipt"></i><span>Pedidos</span></a>
        <a href="logistica_masiva.php" class="nav-item"><i class="fa-solid fa-truck-ramp-box"></i><span>Logistica Masiva</span></a>
        <a href="mensajes.php" class="nav-item"><i class="fa-solid fa-comments"></i><span>Mensajes</span></a><div class="nav-section-label">Operaciones</div>
        <a href="mapa.php" class="nav-item"><i class="fa-solid fa-map-location-dot"></i><span>Mapa de Usuarios</span></a>
        <div class="nav-section-label">Cuenta</div>
        
        <a href="../logout.php" class="nav-item nav-logout"><i class="fa-solid fa-right-from-bracket"></i><span>Cerrar sesion</span></a>
    </nav>
</aside>

<main class="main-content">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenu"><i class="fa-solid fa-bars"></i></button>
            <div class="breadcrumb-custom"><span>NexusPanel</span><i class="fa-solid fa-chevron-right"></i><span>Admin</span><i class="fa-solid fa-chevron-right"></i><span class="active">Usuarios</span></div>
        </div>
        <div class="topbar-right">
            <button id="btnToggleTheme" class="topbar-btn" title="Cambiar Paleta" onclick="toggleTheme()"><i class="fa-solid fa-palette"></i></button>
            <div class="topbar-date" id="topbarDate"></div>
            <div class="dropdown">
                <button class="topbar-btn" type="button" id="panelNotiBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Notificaciones">
                    <i class="fa-solid fa-bell"></i><span class="notif-count hidden" id="panelNotiCount">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0 panel-noti-dropdown" aria-labelledby="panelNotiBtn">
                    <div class="panel-noti-header">
                        <strong>Notificaciones</strong>
                        <button id="btnPanelNotiClear" class="btn btn-sm btn-link p-0" type="button">Limpiar</button>
                    </div>
                    <div class="panel-noti-list" id="panelNotiList">
                        <div style="color:var(--text-muted);font-size:12px;padding:8px;">Sin notificaciones.</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="content-area">
        <div class="welcome-banner" style="margin-bottom:24px;">
            <div class="welcome-text">
                <h1>Gestion de Usuarios</h1>
                <p>Vista interactiva con mapa, perfiles y comportamiento de compra.</p>
            </div>
            <button class="btn-panel" style="font-size:14px;padding:12px 20px;" data-bs-toggle="modal" data-bs-target="#modalCrear">
                <i class="fa-solid fa-user-plus"></i> Nuevo Usuario
            </button>
        </div>

        <?php if ($msg): ?><div class="alert-custom alert-success" style="margin-bottom:20px;display:flex;align-items:center;gap:12px;padding:14px 18px;border-radius:12px;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;font-size:14px;"><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert-custom alert-error" style="margin-bottom:20px;display:flex;align-items:center;gap:12px;padding:14px 18px;border-radius:12px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;font-size:14px;"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="usuarios-grid">
            <div class="card-panel">
                <div class="panel-header"><div><h3 class="panel-title">Mapa de ubicacion de usuarios</h3><p class="panel-subtitle">Integracion con Leaflet (equivalente a Google Maps).</p></div></div>
                <div id="usuariosMapaAdmin" class="usuarios-mapa"></div>
            </div>
            <div class="card-panel">
                <div class="panel-header"><div><h3 class="panel-title">Perfiles resumidos (solo clientes)</h3><p class="panel-subtitle">Haz clic para expandir informacion completa.</p></div></div>
                <input type="search" class="users-filter" id="clientesFilter" placeholder="Buscar cliente por nombre o correo..."><div class="perfil-card-list" id="clientesCardList">
                    <?php foreach ($clientes as $u): ?>
                        <?php
                        $uid = (int)$u['id'];
                        $resumen = $resumenCompras[$uid] ?? ['pedidos' => 0, 'unidades' => 0, 'ultima_compra' => null];
                        $cats = $topCategorias[$uid] ?? [];
                        $esNuevo = !empty($u['created_at']) && ((string)$u['created_at'] >= $inicioMes);
                        ?>
                        <article class="perfil-card" id="perfil-card-<?= $uid ?>">
                            <div class="perfil-card-head" data-card-target="perfil-card-<?= $uid ?>">
                                <img src="<?= htmlspecialchars(fotoPerfilUrl($u), ENT_QUOTES) ?>" alt="Foto de <?= htmlspecialchars($u['nombre']) ?>" class="perfil-foto">
                                <div class="perfil-main">
                                    <div class="perfil-nombre">
                                        <span><?= htmlspecialchars($u['nombre']) ?></span>
                                        <?php if ($esNuevo): ?><span class="badge-new">Nuevo</span><?php endif; ?>
                                    </div>
                                    <div class="perfil-sub"><?= htmlspecialchars($u['rol']) ?> · <?= htmlspecialchars($u['email']) ?></div>
                                </div>
                                <i class="fa-solid fa-chevron-down perfil-toggle"></i>
                            </div>
                            <div class="perfil-body">
                                <div class="perfil-meta">
                                    <div class="meta-box"><div class="meta-label">Historial compras</div><div class="meta-value"><?= (int)$resumen['pedidos'] ?> pedido(s)</div></div>
                                    <div class="meta-box"><div class="meta-label">Unidades compradas</div><div class="meta-value"><?= (int)$resumen['unidades'] ?> pzas</div></div>
                                    <div class="meta-box"><div class="meta-label">Ultima compra</div><div class="meta-value"><?= !empty($resumen['ultima_compra']) ? htmlspecialchars((string)$resumen['ultima_compra']) : 'Sin compras' ?></div></div>
                                    <div class="meta-box"><div class="meta-label">Contacto</div><div class="meta-value"><?= htmlspecialchars((string)($u['telefono'] ?: 'Sin telefono')) ?></div></div>
                                </div>
                                <div class="meta-label" style="margin-top:10px;">Categorias que mas compra (sin precios)</div>
                                <div class="cat-chips">
                                    <?php if (empty($cats)): ?>
                                        <span class="cat-chip">Sin historial</span>
                                    <?php else: ?>
                                        <?php foreach ($cats as $c): ?>
                                            <span class="cat-chip"><?= htmlspecialchars($c['categoria']) ?> (<?= (int)$c['unidades'] ?>)</span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="meta-label" style="margin-top:10px;">Domicilio</div>
                                <div class="meta-value"><?= htmlspecialchars((string)($u['domicilio'] ?: 'Sin domicilio')) ?></div>
                                <?php if ($u['rol'] === 'cliente' && $u['lat'] !== null && $u['lng'] !== null): ?>
                                    <a class="btn-panel" target="_blank" rel="noopener" style="margin-top:10px;display:inline-flex;" href="https://www.google.com/maps?q=<?= rawurlencode((string)$u['lat'] . ',' . (string)$u['lng']) ?>">
                                        <i class="fa-solid fa-map-location-dot"></i> Ver en Maps
                                    </a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card-panel" style="animation-delay:0.1s;">
            <div class="panel-header"><div><h3 class="panel-title">Usuarios registrados (todos)</h3><p class="panel-subtitle"><?= count($usuarios) ?> usuarios en el sistema</p></div></div>
            <input type="search" class="users-filter" id="allUsersFilter" placeholder="Buscar usuario por nombre, correo o rol..."><div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Usuario</th><th>Email</th><th>Telefono</th><th>Edad</th><th>Rol</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr class="table-row-animate">
                            <td><span style="font-family:var(--font-mono);font-size:12px;color:var(--text-dim);">#<?= $u['id'] ?></span></td>
                            <td>
                                <div class="table-user">
                                    <div class="table-avatar"><?= strtoupper(substr($u['nombre'],0,1)) ?></div>
                                    <div>
                                        <div style="display:flex;align-items:center;gap:7px;"><?= htmlspecialchars($u['nombre']) ?><?php if ($u['id'] == $_SESSION['usuario_id']): ?><span style="font-size:10px;padding:2px 7px;background:rgba(0,212,255,0.1);color:var(--primary);border-radius:100px;border:1px solid rgba(0,212,255,0.2);">Tu</span><?php endif; ?></div>
                                        <?php if (!empty($u['domicilio'])): ?><div style="font-size:11px;color:var(--text-dim);margin-top:2px;"><i class="fa-solid fa-location-dot" style="font-size:10px;"></i> <?= htmlspecialchars(mb_strimwidth($u['domicilio'],0,28,'...')) ?></div><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><span class="table-email"><?= htmlspecialchars($u['email']) ?></span></td>
                            <td><span class="table-date"><?= htmlspecialchars($u['telefono'] ?? '-') ?></span></td>
                            <td><span class="table-date"><?= $u['edad'] ? $u['edad'].' anos' : '-' ?></span></td>
                            <td><span class="role-pill <?= $u['rol']==='administrador' ? 'pill-admin':'pill-operator' ?>"><i class="fa-solid <?= $u['rol']==='administrador' ? 'fa-shield-halved':'fa-user-gear' ?>"></i> <?= ucfirst($u['rol']) ?></span></td>
                            <td>
                                <div style="display:flex;gap:6px;">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="action-btn toggle-btn" title="<?= $u['activo']?'Desactivar':'Activar' ?>" <?= ($u['id'] == $_SESSION['usuario_id'] || $u['rol'] === 'administrador') ? 'disabled style="opacity:.45;cursor:not-allowed;"' : '' ?>><i class="fa-solid <?= $u['activo']?'fa-toggle-on':'fa-toggle-off' ?>"></i></button>
                                    </form>
                                    <button type="button" class="action-btn" title="Editar" style="color:var(--primary);" data-bs-toggle="modal" data-bs-target="#modalEditar" data-id="<?= $u['id'] ?>" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>" data-email="<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>" data-telefono="<?= htmlspecialchars($u['telefono'] ?? '', ENT_QUOTES) ?>" data-domicilio="<?= htmlspecialchars($u['domicilio'] ?? '', ENT_QUOTES) ?>" data-edad="<?= (int)($u['edad'] ?? 0) ?>" data-rol="<?= htmlspecialchars($u['rol'], ENT_QUOTES) ?>" data-camion-grande="<?= (int)($u['camion_grande'] ?? 0) ?>" data-capacidad-pedidos="<?= (int)($u['capacidad_pedidos'] ?? 5) ?>"><i class="fa-solid fa-pen"></i></button>
                                    <?php if ($u['rol'] === 'cliente' && $u['lat'] !== null && $u['lng'] !== null): ?>
                                    <a class="action-btn maps-btn" target="_blank" rel="noopener" title="Ver en Maps" href="https://www.google.com/maps?q=<?= rawurlencode((string)$u['lat'] . ',' . (string)$u['lng']) ?>"><i class="fa-solid fa-map-location-dot"></i></a>
                                    <?php endif; ?>
                                    <?php if ($u['id'] != $_SESSION['usuario_id'] && $u['rol'] !== 'administrador'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Eliminar usuario <?= htmlspecialchars(addslashes($u['nombre'])) ?>?')"><input type="hidden" name="action" value="eliminar"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button type="submit" class="action-btn delete-btn" title="Eliminar"><i class="fa-solid fa-trash"></i></button></form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<div class="modal fade modal-dark" id="modalCrear" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" style="font-weight:600;"><i class="fa-solid fa-user-plus" style="color:var(--primary);margin-right:10px;"></i>Nuevo Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST"><input type="hidden" name="action" value="crear"><div class="modal-body" style="padding:24px;display:flex;flex-direction:column;gap:18px;"><div><label>Nombre completo</label><input type="text" name="nombre" class="modal-input" required></div><div><label>Correo electronico</label><input type="email" name="email" class="modal-input" required></div><div><label>Contrasena</label><input type="password" name="password" class="modal-input" required minlength="6"></div><div><label>Rol</label><select name="rol" class="modal-select"><option value="operador">Operador</option><option value="administrador">Administrador</option><option value="cliente">Cliente</option></select></div><div><label>Capacidad maxima de pedidos simultaneos</label><input type="number" name="capacidad_pedidos" class="modal-input" min="1" max="20" value="5"></div><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="camion_grande" value="1"> Camion grande (puede llevar mas de 5 pedidos)</label></div><div class="modal-footer" style="padding:16px 24px;gap:10px;"><button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn-primary-custom"><i class="fa-solid fa-check"></i> Crear usuario</button></div></form>
    </div></div>
</div>

<div class="modal fade modal-dark" id="modalEditar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" style="font-weight:600;"><i class="fa-solid fa-user-pen" style="color:var(--primary);margin-right:10px;"></i>Editar Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST"><input type="hidden" name="action" value="editar"><input type="hidden" name="id" id="edit_id"><div class="modal-body" style="padding:24px;display:flex;flex-direction:column;gap:18px;"><div><label>Nombre completo</label><input type="text" name="nombre" id="edit_nombre" class="modal-input" required></div><div><label>Correo electronico</label><input type="email" name="email" id="edit_email" class="modal-input" required></div><div><label>Telefono</label><input type="text" name="telefono" id="edit_telefono" class="modal-input"></div><div><label>Edad</label><input type="number" min="0" max="120" name="edad" id="edit_edad" class="modal-input"></div><div><label>Domicilio</label><input type="text" name="domicilio" id="edit_domicilio" class="modal-input"></div><div><label>Rol</label><select name="rol" id="edit_rol" class="modal-select"><option value="cliente">Cliente</option><option value="operador">Operador</option><option value="administrador">Administrador</option></select></div><div><label>Capacidad maxima de pedidos simultaneos</label><input type="number" name="capacidad_pedidos" id="edit_capacidad_pedidos" class="modal-input" min="1" max="20" value="5"></div><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="camion_grande" id="edit_camion_grande" value="1"> Camion grande (puede llevar mas de 5 pedidos)</label><div><label>Nueva contrasena (opcional)</label><input type="password" name="password" class="modal-input" minlength="6"></div></div><div class="modal-footer" style="padding:16px 24px;gap:10px;"><button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn-primary-custom"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button></div></form>
    </div></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dashboard.js"></script>
<script>
const PANEL_USER_ID = <?= (int)($_SESSION['usuario_id'] ?? 0) ?>;
let panelNotiCache = [];
function safe(s){ return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;'); }
function panelNotiSeenKey(){ return `panel_noti_seen_${PANEL_USER_ID}`; }
function panelNotiSeenGet(){ try{ return new Set(JSON.parse(localStorage.getItem(panelNotiSeenKey()) || '[]')); }catch(_){ return new Set(); } }
function panelNotiSeenSet(setObj){ try{ localStorage.setItem(panelNotiSeenKey(), JSON.stringify(Array.from(setObj).slice(-600))); }catch(_){} }
function renderPanelNotis(rows){
    const list = document.getElementById('panelNotiList');
    const badge = document.getElementById('panelNotiCount');
    if(!list || !badge) return;
    panelNotiCache = Array.isArray(rows) ? rows : [];
    const seen = panelNotiSeenGet();
    const unseen = panelNotiCache.filter(r => !seen.has(String(r.uid || '')));
    badge.textContent = String(unseen.length);
    badge.classList.toggle('hidden', unseen.length < 1);
    if (!unseen.length){
        list.innerHTML = '<div style="color:var(--text-muted);font-size:12px;padding:8px;">Sin notificaciones.</div>';
        return;
    }
    list.innerHTML = unseen.map(r => `
        <a href="${safe(r.goto || '#')}" class="panel-noti-item" style="${seen.has(String(r.uid||'')) ? '' : 'border-color:rgba(0,212,255,.55);box-shadow:0 0 0 1px rgba(0,212,255,.18) inset;'}">
            <strong>${safe(r.title || 'Notificacion')}</strong>
            <small>${safe(r.from || 'Sistema')}</small>
            <div style="font-size:12px;color:var(--text-light);margin-top:4px;">${safe(r.body || '')}</div>
            <small>${safe(r.ts || '')}</small>
        </a>
    `).join('');
}
async function pollPanelNotis(){
    try{
        const res = await fetch('../api/pedido.php?action=panel_notificaciones&limit=80', { cache:'no-store' });
        if(!res.ok) return;
        const rows = await res.json();
        if(!Array.isArray(rows)) return;
        renderPanelNotis(rows);
    }catch(_){}
}
function markPanelNotisRead(){
    const seen = panelNotiSeenGet();
    (panelNotiCache || []).forEach(r => seen.add(String(r.uid || '')));
    panelNotiSeenSet(seen);
    renderPanelNotis(panelNotiCache);
}
function initPanelNotis(){
    const clearBtn = document.getElementById('btnPanelNotiClear');
    const dropBtn = document.getElementById('panelNotiBtn');
    clearBtn?.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); markPanelNotisRead(); });
    dropBtn?.addEventListener('show.bs.dropdown', () => markPanelNotisRead());
    pollPanelNotis();
    setInterval(pollPanelNotis, 7000);
}

const usuariosMapa = <?= json_encode($usuariosMapa, JSON_UNESCAPED_UNICODE) ?>;
const modalEditar = document.getElementById('modalEditar');
modalEditar?.addEventListener('show.bs.modal', (ev) => {
    const btn = ev.relatedTarget;
    if (!btn) return;
    document.getElementById('edit_id').value = btn.getAttribute('data-id') || '';
    document.getElementById('edit_nombre').value = btn.getAttribute('data-nombre') || '';
    document.getElementById('edit_email').value = btn.getAttribute('data-email') || '';
    document.getElementById('edit_telefono').value = btn.getAttribute('data-telefono') || '';
    document.getElementById('edit_domicilio').value = btn.getAttribute('data-domicilio') || '';
    document.getElementById('edit_edad').value = btn.getAttribute('data-edad') || '';
    document.getElementById('edit_rol').value = btn.getAttribute('data-rol') || 'cliente';
    document.getElementById('edit_capacidad_pedidos').value = btn.getAttribute('data-capacidad-pedidos') || '5';
    document.getElementById('edit_camion_grande').checked = (btn.getAttribute('data-camion-grande') === '1');
});
const clientesFilter = document.getElementById('clientesFilter');
clientesFilter?.addEventListener('input', () => {
    const term = String(clientesFilter.value || '').toLowerCase().trim();
    document.querySelectorAll('#clientesCardList .perfil-card').forEach((card) => {
        const hay = String(card.textContent || '').toLowerCase();
        card.style.display = (!term || hay.includes(term)) ? '' : 'none';
    });
});

const allUsersFilter = document.getElementById('allUsersFilter');
allUsersFilter?.addEventListener('input', () => {
    const term = String(allUsersFilter.value || '').toLowerCase().trim();
    document.querySelectorAll('.data-table tbody tr').forEach((row) => {
        const hay = String(row.textContent || '').toLowerCase();
        row.style.display = (!term || hay.includes(term)) ? '' : 'none';
    });
});

document.querySelectorAll('[data-card-target]').forEach((el) => {
    el.addEventListener('click', () => {
        const id = el.getAttribute('data-card-target');
        document.getElementById(id)?.classList.toggle('open');
    });
});

const usuariosConGeo = usuariosMapa.filter((u) => Number.isFinite(u.lat) && Number.isFinite(u.lng));
const mapEl = document.getElementById('usuariosMapaAdmin');
if (mapEl && window.L) {
    const baseCenter = usuariosConGeo.length ? [usuariosConGeo[0].lat, usuariosConGeo[0].lng] : [19.4326, -99.1332];
    const map = L.map('usuariosMapaAdmin').setView(baseCenter, usuariosConGeo.length ? 10 : 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const bounds = [];
    usuariosConGeo.forEach((u) => {
        const marker = L.marker([u.lat, u.lng]).addTo(map);
        marker.bindPopup(`<strong>${u.nombre}</strong><br>${u.rol}<br>${u.domicilio || 'Sin domicilio'}`);
        bounds.push([u.lat, u.lng]);
    });
    if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [22, 22] });
    }
}
initPanelNotis();
</script>
</body>
</html>












