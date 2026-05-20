<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireGestion();

$usuario = usuarioActual();
$esInventario = esInventario();
$nombreRol = nombreRolActual();

function ensureEmailDashboardSchema(PDO $pdo): void {
    if (!tableExists($pdo, 'email_inbox_messages')) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS email_inbox_messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_id VARCHAR(255) NOT NULL,
            imap_uid BIGINT UNSIGNED NULL,
            from_email VARCHAR(255) NULL,
            from_name VARCHAR(255) NULL,
            subject VARCHAR(500) NULL,
            body_text LONGTEXT NULL,
            body_html LONGTEXT NULL,
            received_at DATETIME NULL,
            fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_unseen TINYINT(1) NOT NULL DEFAULT 1,
            source_mailbox VARCHAR(120) NOT NULL DEFAULT 'INBOX',
            raw_headers LONGTEXT NULL,
            UNIQUE KEY uq_email_inbox_message_id (message_id),
            KEY idx_email_inbox_received_at (received_at),
            KEY idx_email_inbox_unseen (is_unseen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!columnExists($pdo, 'email_inbox_messages', 'review_status')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN review_status VARCHAR(20) NOT NULL DEFAULT 'pendiente' AFTER is_unseen");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'review_note')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN review_note TEXT NULL AFTER review_status");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'reviewed_at')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN reviewed_at DATETIME NULL AFTER review_note");
    }
    if (!indexExists($pdo, 'email_inbox_messages', 'idx_email_inbox_review_status')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD INDEX idx_email_inbox_review_status (review_status)");
    }
}

ensureEmailDashboardSchema($pdo);

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'sync_imap') {
            require_once '../workers/imap_reader.php';
            $result = readInboxMessages($pdo, 30);
            $msg = 'Sincronizacion IMAP completada. Guardados: ' . (int)$result['saved'] . ', omitidos: ' . (int)$result['skipped'] . '.';
        } elseif ($action === 'update_review') {
            $id = (int)($_POST['id'] ?? 0);
            $status = trim((string)($_POST['review_status'] ?? 'pendiente'));
            $note = trim((string)($_POST['review_note'] ?? ''));
            $valid = ['pendiente', 'revisado', 'candidato_pedido', 'descartado'];

            if ($id <= 0) {
                throw new RuntimeException('Correo invalido.');
            }
            if (!in_array($status, $valid, true)) {
                throw new RuntimeException('Estado de revision invalido.');
            }

            $pdo->prepare("UPDATE email_inbox_messages SET review_status=?, review_note=?, reviewed_at=NOW() WHERE id=?")
                ->execute([$status, $note !== '' ? mb_substr($note, 0, 1500) : null, $id]);

            $markSeen = (int)($_POST['mark_seen'] ?? 0) === 1 ? 0 : 1;
            $pdo->prepare("UPDATE email_inbox_messages SET is_unseen=? WHERE id=?")->execute([$markSeen, $id]);

            $msg = 'Revision actualizada.';
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$filterSeen = (string)($_GET['seen'] ?? 'all');
$filterReview = (string)($_GET['review'] ?? 'all');
$selectedId = (int)($_GET['id'] ?? 0);

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(from_email LIKE ? OR from_name LIKE ? OR subject LIKE ? OR body_text LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($filterSeen === 'unseen') {
    $where[] = 'is_unseen = 1';
} elseif ($filterSeen === 'seen') {
    $where[] = 'is_unseen = 0';
}
if ($filterReview !== 'all') {
    $where[] = 'review_status = ?';
    $params[] = $filterReview;
}

$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$metrics = [
    'today' => 0,
    'unseen' => 0,
    'pending' => 0,
    'candidates' => 0,
];

$metrics['today'] = (int)$pdo->query("SELECT COUNT(*) FROM email_inbox_messages WHERE DATE(COALESCE(received_at, fetched_at)) = CURDATE()")->fetchColumn();
$metrics['unseen'] = (int)$pdo->query("SELECT COUNT(*) FROM email_inbox_messages WHERE is_unseen = 1")->fetchColumn();
$metrics['pending'] = (int)$pdo->query("SELECT COUNT(*) FROM email_inbox_messages WHERE review_status = 'pendiente'")->fetchColumn();
$metrics['candidates'] = (int)$pdo->query("SELECT COUNT(*) FROM email_inbox_messages WHERE review_status = 'candidato_pedido'")->fetchColumn();

$st = $pdo->prepare("SELECT * FROM email_inbox_messages $sqlWhere ORDER BY COALESCE(received_at, fetched_at) DESC LIMIT 200");
$st->execute($params);
$rows = $st->fetchAll();

if ($selectedId <= 0 && !empty($rows)) {
    $selectedId = (int)$rows[0]['id'];
}

$selected = null;
if ($selectedId > 0) {
    $stOne = $pdo->prepare("SELECT * FROM email_inbox_messages WHERE id = ? LIMIT 1");
    $stOne->execute([$selectedId]);
    $selected = $stOne->fetch();
}

function statusBadge(string $status): string {
    if ($status === 'candidato_pedido') return 'status-pill status-candidate';
    if ($status === 'revisado') return 'status-pill status-ok';
    if ($status === 'descartado') return 'status-pill status-off';
    return 'status-pill status-pending';
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Correos Pedidos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <style>
        body { background:#020617; }
        .mail-page-header {
            display:flex; align-items:center; justify-content:space-between; gap:18px;
            margin-bottom:24px;
        }
        .mail-page-title { display:flex; align-items:center; gap:16px; }
        .mail-title-icon {
            width:52px; height:52px; border-radius:12px;
            display:flex; align-items:center; justify-content:center;
            background:rgba(14,165,233,.12);
            border:1px solid rgba(14,165,233,.28);
            color:#0ea5e9; font-size:22px;
        }
        .mail-page-title h1 { margin:0; font-size:24px; line-height:1.1; font-weight:800; }
        .mail-page-title p { margin:6px 0 0; color:#8aa4c2; font-size:13px; }
        .mail-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .metrics {
            display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px;
            margin-bottom:16px;
        }
        .metric {
            min-height:86px; border:1px solid #1e293b; border-radius:10px;
            padding:15px 16px; background:#0f172a;
            display:flex; align-items:center; justify-content:space-between; gap:12px;
        }
        .metric-icon {
            width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center;
            background:rgba(14,165,233,.12); color:#38bdf8; border:1px solid rgba(14,165,233,.22);
        }
        .metric h4 { margin:0; font-family:var(--font-mono); font-size:26px; line-height:1; }
        .metric small { display:block; margin-top:6px; color:#8aa4c2; font-size:12px; }
        .mail-toolbar {
            background:#0f172a; border:1px solid #1e293b; border-radius:10px;
            padding:12px; margin-bottom:16px;
            display:grid; grid-template-columns:minmax(280px,1fr) 180px 220px auto auto; gap:10px; align-items:center;
        }
        .mail-search-wrap { position:relative; }
        .mail-search-wrap i {
            position:absolute; left:14px; top:50%; transform:translateY(-50%);
            color:#64748b; font-size:13px;
        }
        .search-input, .toolbar-select, .review-select, .review-note {
            min-height:42px; background:#1e293b !important;
            border:1px solid #334155 !important;
            color:#f8fafc !important; border-radius:9px !important;
            font-size:13px;
        }
        .search-input { padding-left:38px !important; }
        .search-input::placeholder, .review-note::placeholder { color:#8193ab !important; }
        .search-input:focus, .toolbar-select:focus, .review-select:focus, .review-note:focus {
            border-color:#0ea5e9 !important; box-shadow:0 0 0 3px rgba(14,165,233,.14) !important;
        }
        .btn-main, .btn-ok, .btn-glow {
            min-height:42px; border-radius:9px; padding:0 16px;
            display:inline-flex; align-items:center; justify-content:center; gap:8px;
            font-size:13px; font-weight:800; text-decoration:none; white-space:nowrap;
        }
        .btn-main { border:0; color:#fff; background:#0ea5e9; box-shadow:0 8px 18px rgba(14,165,233,.18); }
        .btn-main:hover { color:#fff; background:#0284c7; }
        .btn-ok { border:0; color:#fff; background:#10b981; box-shadow:0 8px 18px rgba(16,185,129,.16); }
        .btn-ok:hover { color:#fff; background:#059669; }
        .btn-glow { border:1px solid rgba(14,165,233,.45); color:#d8efff; background:rgba(14,165,233,.06); }
        .btn-glow:hover { color:#fff; background:rgba(14,165,233,.14); }
        .content-grid.mail-grid {
            display:grid; grid-template-columns:minmax(420px, 1.05fr) minmax(420px, .95fr);
            gap:16px; align-items:start;
        }
        .mail-panel { background:#0f172a; border:1px solid #1e293b; border-radius:10px; overflow:hidden; }
        .mail-panel .panel-header { padding:16px 18px; }
        .mail-list { max-height:610px; overflow:auto; background:#0b1222; border-top:1px solid #1e293b; }
        .mail-item {
            display:block; padding:15px 16px; border-bottom:1px solid rgba(30,41,59,.78);
            color:inherit; text-decoration:none; transition:.16s ease; position:relative;
        }
        .mail-item:hover { background:#111c31; }
        .mail-item.active { background:#082f49; }
        .mail-item.active::before {
            content:''; position:absolute; left:0; top:0; bottom:0; width:3px; background:#0ea5e9;
        }
        .mail-subject { font-weight:800; font-size:13px; line-height:1.35; margin-bottom:5px; color:#f8fafc; }
        .mail-meta { font-size:12px; color:#94a3b8; display:flex; gap:8px; flex-wrap:wrap; }
        .mail-preview {
            font-size:12px; color:#7f93ad; margin-top:8px;
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        }
        .mail-detail-content { padding:16px 18px 18px; }
        .detail-table {
            display:grid; grid-template-columns:86px minmax(0,1fr); gap:9px 12px;
            padding-bottom:14px; margin-bottom:14px; border-bottom:1px solid #1e293b;
            font-size:12px;
        }
        .detail-label { color:#dbeafe; font-weight:800; }
        .detail-value { color:#bcd3ee; min-width:0; overflow:hidden; text-overflow:ellipsis; }
        .mail-body {
            white-space:pre-wrap; font-size:13px; line-height:1.6;
            background:#091326; border:1px solid #1d4f73; border-radius:9px;
            padding:14px; min-height:220px; max-height:320px; overflow:auto; color:#e7f3ff;
        }
        .review-form { margin-top:16px; display:grid; gap:10px; }
        .review-form label { font-size:12px; color:#dbeafe; font-weight:700; }
        .status-pill {
            display:inline-flex; align-items:center; gap:6px; padding:5px 10px; border-radius:999px; font-size:11px; font-weight:800;
            border:1px solid transparent;
        }
        .status-candidate { background:rgba(14,165,233,.16); color:#7dd3fc; border-color:rgba(14,165,233,.35); }
        .status-ok { background:rgba(16,185,129,.16); color:#6ee7b7; border-color:rgba(16,185,129,.35); }
        .status-off { background:rgba(100,116,139,.2); color:#cbd5e1; border-color:rgba(148,163,184,.35); }
        .status-pending { background:rgba(245,158,11,.14); color:#fcd34d; border-color:rgba(245,158,11,.35); }
        .new-pill { display:inline-flex; padding:4px 9px; border-radius:999px; background:rgba(6,182,212,.16); color:#67e8f9; font-size:11px; font-weight:800; border:1px solid rgba(6,182,212,.32); }
        .msg-id-chip { display:block; max-width:100%; overflow:hidden; text-overflow:ellipsis; background:rgba(30,41,59,.85); border:1px solid rgba(14,165,233,.24); border-radius:7px; padding:4px 7px; color:#9fd7ff; font-size:11px; }
        .check-inline { display:flex; align-items:center; gap:8px; color:#b8d6f6; font-size:13px; }
        .check-inline input[type="checkbox"] { accent-color:#06b6d4; }
        .phase-note { color:#64748b; font-size:12px; }
        .alert-nexus { margin-bottom:16px; }
        @media (max-width: 980px) {
            .metrics { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .content-grid.mail-grid { grid-template-columns:1fr; }
            .mail-toolbar { grid-template-columns:1fr; }
            .mail-page-header { align-items:flex-start; flex-direction:column; }
            .mail-list { max-height:45vh; }
        }
    </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="logo-icon-sm"><i class="fa-solid fa-hexagon-nodes"></i></div>
            <span class="logo-text-sm">Nexus<strong>Panel</strong></span>
        </div>
    </div>
    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr((string)($usuario['nombre'] ?? 'U'), 0, 1)) ?></div>
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($usuario['nombre'] ?? 'Usuario') ?></span>
            <span class="user-role role-admin"><i class="fa-solid fa-envelope-open-text"></i> <?= htmlspecialchars($nombreRol) ?></span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="dashboard.php" class="nav-item"><i class="fa-solid fa-chart-line"></i><span>Inicio</span></a>
        <a href="usuarios.php" class="nav-item"><i class="fa-solid fa-users"></i><span>Usuarios</span></a>
        <a href="productos.php" class="nav-item"><i class="fa-solid fa-flask-vial"></i><span>Productos e inventario</span></a>
        <a href="pedidos.php" class="nav-item active"><i class="fa-solid fa-envelope-open-text"></i><span>Correos Pedidos</span><div class="nav-indicator"></div></a>
        <a href="logistica_inteligente.php" class="nav-item"><i class="fa-solid fa-truck-fast"></i><span>Logistica Inteligente</span></a>
        <a href="mensajes.php" class="nav-item"><i class="fa-solid fa-comments"></i><span>Mensajes</span></a>
        <div class="nav-section-label">Operaciones</div>
        <a href="mapa.php" class="nav-item"><i class="fa-solid fa-map-location-dot"></i><span>Mapa de Usuarios</span></a>
        <div class="nav-section-label">Cuenta</div>
        <a href="../logout.php" class="nav-item nav-logout"><i class="fa-solid fa-right-from-bracket"></i><span>Cerrar sesion</span></a>
    </nav>
</aside>

<main class="main-content">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenu"><i class="fa-solid fa-bars"></i></button>
            <div class="breadcrumb-custom"><span>NexusPanel</span><i class="fa-solid fa-chevron-right"></i><span class="active">Correos Pedidos</span></div>
        </div>
        <div class="topbar-right">
            <button class="topbar-btn theme-toggle" onclick="toggleTheme()" title="Cambiar tema">
                <i class="fa-solid fa-moon theme-toggle-icon"></i>
            </button>
            <div class="topbar-date" id="topbarDate"></div>
        </div>
    </header>

    <div class="content-area">
        <?php if ($msg !== ''): ?>
            <div class="alert-nexus alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if ($err !== ''): ?>
            <div class="alert-nexus alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <div class="mail-page-header">
            <div class="mail-page-title">
                <div class="mail-title-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
                <div>
                    <h1>Dashboard de Correos de Pedidos</h1>
                    <p>Bandeja IMAP conectada para revisar solicitudes antes de IA.</p>
                </div>
            </div>
            <form method="post" class="mail-actions">
                <input type="hidden" name="action" value="sync_imap">
                <button class="btn-ok" type="submit"><i class="fa-solid fa-rotate"></i> Sincronizar IMAP</button>
            </form>
        </div>

        <section class="metrics">
            <article class="metric"><div><h4><?= $metrics['today'] ?></h4><small>Correos hoy</small></div><div class="metric-icon"><i class="fa-solid fa-inbox"></i></div></article>
            <article class="metric"><div><h4><?= $metrics['unseen'] ?></h4><small>No leidos</small></div><div class="metric-icon"><i class="fa-solid fa-envelope"></i></div></article>
            <article class="metric"><div><h4><?= $metrics['pending'] ?></h4><small>Pendientes de revision</small></div><div class="metric-icon"><i class="fa-solid fa-clock"></i></div></article>
            <article class="metric"><div><h4><?= $metrics['candidates'] ?></h4><small>Candidatos a pedido</small></div><div class="metric-icon"><i class="fa-solid fa-clipboard-check"></i></div></article>
        </section>

        <form method="get" class="mail-toolbar">
            <div class="mail-search-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input class="form-control search-input" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar remitente, asunto o contenido">
            </div>
            <select class="form-select toolbar-select" name="seen">
                    <option value="all" <?= $filterSeen==='all' ? 'selected' : '' ?>>Todos</option>
                    <option value="unseen" <?= $filterSeen==='unseen' ? 'selected' : '' ?>>No leidos</option>
                    <option value="seen" <?= $filterSeen==='seen' ? 'selected' : '' ?>>Leidos</option>
            </select>
            <select class="form-select toolbar-select" name="review">
                    <option value="all" <?= $filterReview==='all' ? 'selected' : '' ?>>Todos los estados</option>
                    <option value="pendiente" <?= $filterReview==='pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="revisado" <?= $filterReview==='revisado' ? 'selected' : '' ?>>Revisado</option>
                    <option value="candidato_pedido" <?= $filterReview==='candidato_pedido' ? 'selected' : '' ?>>Candidato a pedido</option>
                    <option value="descartado" <?= $filterReview==='descartado' ? 'selected' : '' ?>>Descartado</option>
            </select>
            <button class="btn-main" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
            <a class="btn-glow" href="pedidos.php">Limpiar</a>
        </form>

        <section class="content-grid mail-grid">
            <article class="mail-panel">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">Bandeja</h3>
                        <p class="panel-subtitle"><?= count($rows) ?> correos cargados</p>
                    </div>
                    <span class="phase-note">Revision manual</span>
                </div>
                <div class="mail-list">
                    <?php if (empty($rows)): ?>
                        <div class="p-3 text-muted">No hay correos para los filtros actuales.</div>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                                $id = (int)$r['id'];
                                $isActive = $id === $selectedId;
                                $sender = trim((string)($r['from_name'] ?: $r['from_email'] ?: 'Sin remitente'));
                                $subject = trim((string)($r['subject'] ?: '(Sin asunto)'));
                                $preview = trim((string)($r['body_text'] ?: strip_tags((string)$r['body_html'])));
                                $received = $r['received_at'] ?: $r['fetched_at'];
                            ?>
                            <a class="mail-item <?= $isActive ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['id' => $id])) ?>">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="mail-subject"><?= htmlspecialchars($subject) ?></div>
                                    <?php if ((int)$r['is_unseen'] === 1): ?><span class="new-pill">Nuevo</span><?php endif; ?>
                                </div>
                                <div class="mail-meta"><?= htmlspecialchars($sender) ?> · <?= htmlspecialchars((string)$received) ?></div>
                                <div class="mail-preview"><?= htmlspecialchars($preview) ?></div>
                                <div class="mt-2"><span class="<?= statusBadge((string)$r['review_status']) ?>"><?= htmlspecialchars((string)$r['review_status']) ?></span></div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>

            <article class="mail-panel">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">Detalle y Revision</h3>
                        <p class="panel-subtitle">Preparado para extraccion con IA</p>
                    </div>
                </div>
                <div class="mail-detail-content">
                <?php if (!$selected): ?>
                    <p class="text-muted">Selecciona un correo de la bandeja.</p>
                <?php else: ?>
                    <div class="detail-table">
                        <div class="detail-label">De</div><div class="detail-value"><?= htmlspecialchars((string)($selected['from_name'] ?: $selected['from_email'] ?: '-')) ?></div>
                        <div class="detail-label">Asunto</div><div class="detail-value"><?= htmlspecialchars((string)($selected['subject'] ?: '(Sin asunto)')) ?></div>
                        <div class="detail-label">Fecha</div><div class="detail-value"><?= htmlspecialchars((string)($selected['received_at'] ?: $selected['fetched_at'])) ?></div>
                        <div class="detail-label">Message-ID</div><div class="detail-value"><span class="msg-id-chip"><?= htmlspecialchars((string)$selected['message_id']) ?></span></div>
                    </div>
                    <div class="mail-body mb-3"><?= htmlspecialchars((string)($selected['body_text'] ?: strip_tags((string)$selected['body_html']) ?: '(Sin contenido legible)')) ?></div>

                    <form method="post" class="review-form">
                        <input type="hidden" name="action" value="update_review">
                        <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>">
                        <label>Estado de revision</label>
                        <select class="form-select review-select" name="review_status">
                            <?php $currentStatus = (string)($selected['review_status'] ?: 'pendiente'); ?>
                            <option value="pendiente" <?= $currentStatus==='pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="candidato_pedido" <?= $currentStatus==='candidato_pedido' ? 'selected' : '' ?>>Candidato a pedido</option>
                            <option value="revisado" <?= $currentStatus==='revisado' ? 'selected' : '' ?>>Revisado</option>
                            <option value="descartado" <?= $currentStatus==='descartado' ? 'selected' : '' ?>>Descartado</option>
                        </select>
                        <label>Nota operativa</label>
                        <textarea class="form-control review-note" name="review_note" rows="4" placeholder="Ej: solicitar validacion inventario, confirmar tonelaje, etc."><?= htmlspecialchars((string)($selected['review_note'] ?? '')) ?></textarea>
                        <div class="check-inline">
                            <input type="checkbox" id="markSeen" name="mark_seen" value="1" <?= (int)$selected['is_unseen'] === 0 ? 'checked' : '' ?>>
                            <label for="markSeen">Marcar como leido</label>
                        </div>
                        <button class="btn-main" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar revision</button>
                    </form>
                <?php endif; ?>
                </div>
            </article>
        </section>
    </div>
</main>
</body>
</html>
