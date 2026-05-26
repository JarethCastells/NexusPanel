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

    if (!tableExists($pdo, 'mail_accounts')) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS mail_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(190) NOT NULL,
                provider VARCHAR(80) NULL,
                imap_host VARCHAR(190) NOT NULL,
                imap_port INT NOT NULL DEFAULT 993,
                imap_secure TINYINT(1) NOT NULL DEFAULT 1,
                imap_user VARCHAR(190) NOT NULL,
                imap_pass VARCHAR(255) NOT NULL,
                imap_mailbox VARCHAR(120) NOT NULL DEFAULT 'INBOX',
                imap_only_unseen TINYINT(1) NOT NULL DEFAULT 1,
                smtp_host VARCHAR(190) NULL,
                smtp_port INT NULL,
                smtp_secure VARCHAR(20) NULL,
                smtp_user VARCHAR(190) NULL,
                smtp_pass VARCHAR(255) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_mail_accounts_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    // Bootstrap demo: migrar cuenta de .env a BD una sola vez si no hay cuentas.
    $hasEnvMail = MAIL_IMAP_HOST !== '' && MAIL_IMAP_USER !== '' && MAIL_IMAP_PASS !== '';
    if ($hasEnvMail) {
        $countAccounts = (int)$pdo->query("SELECT COUNT(*) FROM mail_accounts")->fetchColumn();
        if ($countAccounts === 0) {
            $ins = $pdo->prepare("
                INSERT INTO mail_accounts
                (email, provider, imap_host, imap_port, imap_secure, imap_user, imap_pass, imap_mailbox, imap_only_unseen, smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NULL)
            ");
            $ins->execute([
                MAIL_IMAP_USER,
                'ENV Bootstrap',
                MAIL_IMAP_HOST,
                (int)MAIL_IMAP_PORT,
                MAIL_IMAP_SECURE ? 1 : 0,
                MAIL_IMAP_USER,
                MAIL_IMAP_PASS,
                MAIL_IMAP_MAILBOX,
                MAIL_IMAP_ONLY_UNSEEN ? 1 : 0,
                MAIL_SMTP_HOST !== '' ? MAIL_SMTP_HOST : null,
                (int)MAIL_SMTP_PORT > 0 ? (int)MAIL_SMTP_PORT : null,
                MAIL_SMTP_SECURE !== '' ? MAIL_SMTP_SECURE : null,
                MAIL_SMTP_USER !== '' ? MAIL_SMTP_USER : null,
                MAIL_SMTP_PASS !== '' ? MAIL_SMTP_PASS : null,
            ]);
        }
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
        } elseif ($action === 'add_mail_account') {
            $email = trim((string)($_POST['email'] ?? ''));
            $provider = trim((string)($_POST['provider'] ?? ''));
            $imapHost = trim((string)($_POST['imap_host'] ?? ''));
            $imapPort = (int)($_POST['imap_port'] ?? 993);
            $imapSecure = (int)($_POST['imap_secure'] ?? 1) === 1 ? 1 : 0;
            $imapUser = trim((string)($_POST['imap_user'] ?? ''));
            $imapPass = trim((string)($_POST['imap_pass'] ?? ''));
            $imapMailbox = trim((string)($_POST['imap_mailbox'] ?? 'INBOX'));
            $imapOnlyUnseen = (int)($_POST['imap_only_unseen'] ?? 1) === 1 ? 1 : 0;
            $smtpHost = trim((string)($_POST['smtp_host'] ?? ''));
            $smtpPort = (int)($_POST['smtp_port'] ?? 465);
            $smtpSecure = trim((string)($_POST['smtp_secure'] ?? 'ssl'));
            $smtpUser = trim((string)($_POST['smtp_user'] ?? ''));
            $smtpPass = trim((string)($_POST['smtp_pass'] ?? ''));
            $isActive = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Correo invalido para la cuenta.');
            }
            if ($imapHost === '' || $imapUser === '' || $imapPass === '') {
                throw new RuntimeException('Faltan datos IMAP obligatorios.');
            }

            if ($isActive === 1) {
                $activeCount = (int)$pdo->query("SELECT COUNT(*) FROM mail_accounts WHERE is_active = 1")->fetchColumn();
                if ($activeCount >= 2) {
                    throw new RuntimeException('Solo se permiten 2 cuentas activas. Desactiva una antes de agregar otra activa.');
                }
            }

            $pdo->prepare("
                INSERT INTO mail_accounts
                (email, provider, imap_host, imap_port, imap_secure, imap_user, imap_pass, imap_mailbox, imap_only_unseen, smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $email,
                $provider !== '' ? mb_substr($provider, 0, 80) : null,
                mb_substr($imapHost, 0, 190),
                $imapPort > 0 ? $imapPort : 993,
                $imapSecure,
                mb_substr($imapUser, 0, 190),
                mb_substr($imapPass, 0, 255),
                $imapMailbox !== '' ? mb_substr($imapMailbox, 0, 120) : 'INBOX',
                $imapOnlyUnseen,
                $smtpHost !== '' ? mb_substr($smtpHost, 0, 190) : null,
                $smtpPort > 0 ? $smtpPort : null,
                $smtpSecure !== '' ? mb_substr($smtpSecure, 0, 20) : null,
                $smtpUser !== '' ? mb_substr($smtpUser, 0, 190) : null,
                $smtpPass !== '' ? mb_substr($smtpPass, 0, 255) : null,
                $isActive,
                (int)($usuario['usuario_id'] ?? 0),
            ]);
            $msg = 'Cuenta de correo guardada correctamente.';
        } elseif ($action === 'update_mail_account') {
            $accountId = (int)($_POST['account_id'] ?? 0);
            $email = trim((string)($_POST['email'] ?? ''));
            $provider = trim((string)($_POST['provider'] ?? ''));
            $imapHost = trim((string)($_POST['imap_host'] ?? ''));
            $imapPort = (int)($_POST['imap_port'] ?? 993);
            $imapSecure = (int)($_POST['imap_secure'] ?? 1) === 1 ? 1 : 0;
            $imapUser = trim((string)($_POST['imap_user'] ?? ''));
            $imapPass = trim((string)($_POST['imap_pass'] ?? ''));
            $imapMailbox = trim((string)($_POST['imap_mailbox'] ?? 'INBOX'));
            $imapOnlyUnseen = (int)($_POST['imap_only_unseen'] ?? 1) === 1 ? 1 : 0;
            $smtpHost = trim((string)($_POST['smtp_host'] ?? ''));
            $smtpPort = (int)($_POST['smtp_port'] ?? 465);
            $smtpSecure = trim((string)($_POST['smtp_secure'] ?? 'ssl'));
            $smtpUser = trim((string)($_POST['smtp_user'] ?? ''));
            $smtpPass = trim((string)($_POST['smtp_pass'] ?? ''));
            $isActive = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;

            if ($accountId <= 0) {
                throw new RuntimeException('Cuenta de correo invalida para editar.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Correo invalido para la cuenta.');
            }
            if ($imapHost === '' || $imapUser === '') {
                throw new RuntimeException('Faltan datos IMAP obligatorios.');
            }

            $stCurrent = $pdo->prepare("SELECT imap_pass, smtp_pass FROM mail_accounts WHERE id = ? LIMIT 1");
            $stCurrent->execute([$accountId]);
            $curr = $stCurrent->fetch();
            if (!$curr) {
                throw new RuntimeException('No se encontro la cuenta para editar.');
            }

            if ($isActive === 1) {
                $stCnt = $pdo->prepare("SELECT COUNT(*) FROM mail_accounts WHERE is_active = 1 AND id <> ?");
                $stCnt->execute([$accountId]);
                $activeOthers = (int)$stCnt->fetchColumn();
                if ($activeOthers >= 2) {
                    throw new RuntimeException('Solo se permiten 2 cuentas activas. Desactiva una antes de activar esta.');
                }
            }

            $pdo->prepare("
                UPDATE mail_accounts
                SET email=?, provider=?, imap_host=?, imap_port=?, imap_secure=?, imap_user=?, imap_pass=?, imap_mailbox=?, imap_only_unseen=?, smtp_host=?, smtp_port=?, smtp_secure=?, smtp_user=?, smtp_pass=?, is_active=?
                WHERE id=?
            ")->execute([
                $email,
                $provider !== '' ? mb_substr($provider, 0, 80) : null,
                mb_substr($imapHost, 0, 190),
                $imapPort > 0 ? $imapPort : 993,
                $imapSecure,
                mb_substr($imapUser, 0, 190),
                $imapPass !== '' ? mb_substr($imapPass, 0, 255) : (string)$curr['imap_pass'],
                $imapMailbox !== '' ? mb_substr($imapMailbox, 0, 120) : 'INBOX',
                $imapOnlyUnseen,
                $smtpHost !== '' ? mb_substr($smtpHost, 0, 190) : null,
                $smtpPort > 0 ? $smtpPort : null,
                $smtpSecure !== '' ? mb_substr($smtpSecure, 0, 20) : null,
                $smtpUser !== '' ? mb_substr($smtpUser, 0, 190) : null,
                $smtpPass !== '' ? mb_substr($smtpPass, 0, 255) : (string)($curr['smtp_pass'] ?? ''),
                $isActive,
                $accountId,
            ]);
            $msg = 'Cuenta actualizada correctamente.';
        } elseif ($action === 'toggle_mail_account') {
            $accountId = (int)($_POST['account_id'] ?? 0);
            $target = (int)($_POST['target_active'] ?? 0) === 1 ? 1 : 0;
            if ($accountId <= 0) {
                throw new RuntimeException('Cuenta de correo invalida.');
            }
            if ($target === 1) {
                $activeCount = (int)$pdo->query("SELECT COUNT(*) FROM mail_accounts WHERE is_active = 1")->fetchColumn();
                $stAct = $pdo->prepare("SELECT is_active FROM mail_accounts WHERE id = ? LIMIT 1");
                $stAct->execute([$accountId]);
                $curr = (int)($stAct->fetchColumn() ?? 0);
                if ($curr !== 1 && $activeCount >= 2) {
                    throw new RuntimeException('Ya hay 2 cuentas activas. Desactiva una para activar otra.');
                }
            }
            $pdo->prepare("UPDATE mail_accounts SET is_active = ? WHERE id = ?")->execute([$target, $accountId]);
            $msg = $target === 1 ? 'Cuenta activada.' : 'Cuenta desactivada.';
        } elseif ($action === 'delete_mail_account') {
            $accountId = (int)($_POST['account_id'] ?? 0);
            if ($accountId <= 0) {
                throw new RuntimeException('Cuenta de correo invalida.');
            }
            $pdo->prepare("DELETE FROM mail_accounts WHERE id = ?")->execute([$accountId]);
            $msg = 'Cuenta eliminada.';
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
$mailAccounts = $pdo->query("SELECT * FROM mail_accounts ORDER BY id DESC")->fetchAll();
$activeMailAccounts = 0;
$activeSmtpAccounts = 0;
foreach ($mailAccounts as $ma) {
    if ((int)($ma['is_active'] ?? 0) === 1) {
        $activeMailAccounts++;
        if (
            trim((string)($ma['smtp_host'] ?? '')) !== ''
            && trim((string)($ma['smtp_user'] ?? '')) !== ''
            && trim((string)($ma['smtp_pass'] ?? '')) !== ''
        ) {
            $activeSmtpAccounts++;
        }
    }
}

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
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
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
        .mail-config-chip {
            display:inline-flex; align-items:center; gap:8px;
            min-height:42px; padding:0 14px; border-radius:9px;
            border:1px solid rgba(14,165,233,.42); background:rgba(14,165,233,.08);
            color:#e2f3ff; font-weight:700; font-size:13px;
        }
        .mail-config-chip strong { color:#38bdf8; font-family:var(--font-mono); }
        .channel-overview {
            display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px;
            margin-bottom:16px;
        }
        .channel-card {
            position:relative; overflow:hidden; min-height:118px;
            border:1px solid #1e293b; border-radius:12px; padding:16px;
            background:linear-gradient(135deg,rgba(15,23,42,.98),rgba(8,18,35,.92));
            display:flex; align-items:flex-start; justify-content:space-between; gap:14px;
        }
        .channel-card::after {
            content:''; position:absolute; right:-42px; top:-42px; width:120px; height:120px;
            border-radius:50%; background:rgba(14,165,233,.10); pointer-events:none;
        }
        .channel-card.whatsapp::after { background:rgba(34,197,94,.12); }
        .channel-copy { position:relative; z-index:1; min-width:0; }
        .channel-kicker { color:#8aa4c2; font-size:11px; text-transform:uppercase; letter-spacing:.08em; font-weight:800; }
        .channel-title { margin:5px 0 6px; font-size:16px; font-weight:900; color:#f8fafc; }
        .channel-meta { display:flex; gap:8px; flex-wrap:wrap; color:#9fb6d3; font-size:12px; }
        .channel-pill {
            display:inline-flex; align-items:center; gap:6px; padding:5px 9px;
            border-radius:999px; background:rgba(30,41,59,.82);
            border:1px solid rgba(148,163,184,.22); color:#cbd5e1; font-size:11px; font-weight:800;
        }
        .channel-pill.ok { color:#86efac; border-color:rgba(16,185,129,.35); background:rgba(16,185,129,.12); }
        .channel-pill.warn { color:#fcd34d; border-color:rgba(245,158,11,.35); background:rgba(245,158,11,.12); }
        .channel-actions { position:relative; z-index:1; display:flex; flex-direction:column; gap:8px; align-items:flex-end; }
        .channel-action-link {
            min-height:36px; border-radius:9px; padding:0 12px; display:inline-flex; align-items:center; gap:8px;
            text-decoration:none; color:#dff7ff; border:1px solid rgba(14,165,233,.42); background:rgba(14,165,233,.08);
            font-size:12px; font-weight:800; white-space:nowrap;
        }
        .channel-action-link:hover { color:#fff; background:rgba(14,165,233,.16); }
        .mail-modal-backdrop {
            position:fixed; inset:0; background:rgba(2,8,23,.74);
            backdrop-filter:blur(3px); z-index:2000; display:none;
        }
        .mail-modal-backdrop.show { display:block; }
        .mail-modal {
            position:fixed; left:50%; top:50%; transform:translate(-50%,-50%);
            width:min(1100px, calc(100vw - 28px)); max-height:86vh; overflow:auto;
            border-radius:12px; border:1px solid #1e293b; background:#0b1222;
            box-shadow:0 30px 80px rgba(0,0,0,.55); z-index:2001; display:none;
        }
        .mail-modal.show { display:block; }
        .mail-modal-header {
            padding:14px 16px; border-bottom:1px solid #1e293b; display:flex;
            align-items:center; justify-content:space-between; gap:12px;
        }
        .mail-modal-title { margin:0; font-size:15px; font-weight:800; }
        .mail-modal-body { padding:14px 16px 16px; }
        .mail-modal-close {
            width:34px; height:34px; border-radius:8px; border:1px solid #334155;
            background:#1e293b; color:#e2e8f0; display:inline-flex; align-items:center; justify-content:center;
        }
        .mail-modal-close:hover { background:#334155; }
        .mail-accounts-panel { background:#0f172a; border:1px solid #1e293b; border-radius:10px; padding:12px; }
        .mail-modal-stack { display:grid; gap:12px; }
        .mail-section-title { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px; }
        .mail-section-title h3 { margin:0; font-size:14px; font-weight:800; }
        .mail-section-title span { color:#64748b; font-size:12px; }
        .mail-edit-panel { display:none; border-color:rgba(14,165,233,.42); background:#0b1730; }
        .mail-edit-panel.show { display:block; }
        .mail-add-panel { display:none; }
        .mail-add-panel.show { display:block; }
        .mail-accounts-head { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:10px; }
        .mail-accounts-head h3 { margin:0; font-size:14px; font-weight:800; }
        .mail-accounts-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-bottom:10px; }
        .mail-accounts-grid .wide { grid-column:span 2; }
        .mail-accounts-grid .full { grid-column:span 4; }
        .mail-accounts-list { border:1px solid #1e293b; border-radius:8px; overflow:hidden; }
        .mail-account-row { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 12px; border-bottom:1px solid #1e293b; }
        .mail-account-row:last-child { border-bottom:none; }
        .mail-account-info { font-size:12px; color:#bcd3ee; min-width:0; flex:1; }
        .mail-account-info strong { display:block; color:#f8fafc; margin-bottom:4px; }
        .mail-account-actions { display:flex; gap:8px; align-items:center; }
        .mini-btn { min-height:32px; border-radius:8px; padding:0 10px; font-size:12px; font-weight:700; }
        .mini-btn.on { background:#0ea5e9; color:#fff; border:0; }
        .mini-btn.off { background:#334155; color:#e2e8f0; border:0; }
        .mini-btn.del { background:#7f1d1d; color:#fecaca; border:0; }
        .mini-btn.edit { background:#0f3d5c; color:#bae6fd; border:0; }
        @media (max-width: 980px) {
            .channel-overview { grid-template-columns:1fr; }
            .metrics { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .content-grid.mail-grid { grid-template-columns:1fr; }
            .mail-toolbar { grid-template-columns:1fr; }
            .mail-page-header { align-items:flex-start; flex-direction:column; }
            .mail-list { max-height:45vh; }
            .mail-accounts-grid { grid-template-columns:1fr; }
            .mail-accounts-grid .wide,
            .mail-accounts-grid .full { grid-column:span 1; }
            .mail-account-row { flex-direction:column; align-items:flex-start; }
            .mail-config-chip { width:100%; justify-content:center; }
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
        <a href="pedidos.php" class="nav-item active"><i class="fa-solid fa-envelope-open-text"></i><span>Pedidos</span><div class="nav-indicator"></div></a>
        <a href="mensajes.php" class="nav-item"><i class="fa-solid fa-comments"></i><span>Mensajes internos</span></a>
        <a href="../whatsapp.php" class="nav-item"><i class="fa-brands fa-whatsapp"></i><span>WhatsApp</span></a>
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
                <button class="mail-config-chip" type="button" id="openMailAccountsModal">
                    <i class="fa-solid fa-envelope-circle-check"></i>
                    Cuentas
                    <strong><?= $activeMailAccounts ?>/2</strong>
                </button>
            </form>
        </div>

        <section class="channel-overview" aria-label="Canales vinculados">
            <article class="channel-card">
                <div class="channel-copy">
                    <div class="channel-kicker">Correo operativo</div>
                    <div class="channel-title">IMAP para lectura + SMTP para respuesta</div>
                    <div class="channel-meta">
                        <span class="channel-pill <?= $activeMailAccounts > 0 ? 'ok' : 'warn' ?>"><i class="fa-solid fa-inbox"></i> IMAP <?= $activeMailAccounts ?>/2</span>
                        <span class="channel-pill <?= $activeSmtpAccounts > 0 ? 'ok' : 'warn' ?>"><i class="fa-solid fa-paper-plane"></i> SMTP <?= $activeSmtpAccounts ?>/<?= max(1, $activeMailAccounts) ?></span>
                    </div>
                </div>
                <div class="channel-actions">
                    <button class="channel-action-link" type="button" id="openMailAccountsModalFromCard"><i class="fa-solid fa-gear"></i> Configurar</button>
                </div>
            </article>
            <article class="channel-card whatsapp">
                <div class="channel-copy">
                    <div class="channel-kicker">Canal alterno</div>
                    <div class="channel-title">WhatsApp vinculado a OpenWA</div>
                    <div class="channel-meta" id="whatsappChannelStatus">
                        <span class="channel-pill warn"><i class="fa-solid fa-circle-notch fa-spin"></i> Revisando conexion</span>
                    </div>
                </div>
                <div class="channel-actions">
                    <a class="channel-action-link" href="../whatsapp.php"><i class="fa-brands fa-whatsapp"></i> Abrir WhatsApp</a>
                </div>
            </article>
        </section>

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
<div class="mail-modal-backdrop" id="mailAccountsBackdrop"></div>
<section class="mail-modal" id="mailAccountsModal" aria-hidden="true">
    <div class="mail-modal-header">
        <h3 class="mail-modal-title">Cuentas de correo vinculadas (activas: <?= $activeMailAccounts ?>/2)</h3>
        <button class="mail-modal-close" type="button" id="closeMailAccountsModal"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="mail-modal-body">
        <section class="mail-accounts-panel">
            <div class="mail-accounts-head">
                <h3>Configuracion IMAP/SMTP</h3>
            </div>
            <div class="mail-section-title">
                <h3>Cuentas configuradas</h3>
                <button class="btn-main" id="showAddMailPanel" type="button"><i class="fa-solid fa-plus"></i> Agregar otra cuenta</button>
            </div>
            <div class="mail-accounts-list" style="margin-bottom:12px;">
                <?php if (empty($mailAccounts)): ?>
                    <div class="mail-account-row"><div class="mail-account-info">No hay cuentas guardadas. Agrega la primera cuenta.</div></div>
                <?php else: ?>
                    <?php foreach ($mailAccounts as $acc): ?>
                        <div class="mail-account-row">
                            <div class="mail-account-info">
                                <strong><?= htmlspecialchars((string)$acc['email']) ?></strong>
                                IMAP <?= htmlspecialchars((string)$acc['imap_host']) ?>:<?= (int)$acc['imap_port'] ?>
                                · <?= (int)$acc['is_active'] === 1 ? 'Activa' : 'Inactiva' ?>
                            </div>
                            <div class="mail-account-actions">
                                <form method="post">
                                    <input type="hidden" name="action" value="toggle_mail_account">
                                    <input type="hidden" name="account_id" value="<?= (int)$acc['id'] ?>">
                                    <input type="hidden" name="target_active" value="<?= (int)$acc['is_active'] === 1 ? 0 : 1 ?>">
                                    <button class="mini-btn <?= (int)$acc['is_active'] === 1 ? 'off' : 'on' ?>" type="submit">
                                        <?= (int)$acc['is_active'] === 1 ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                                <button
                                    class="mini-btn edit js-edit-mail-account"
                                    type="button"
                                    data-id="<?= (int)$acc['id'] ?>"
                                    data-email="<?= htmlspecialchars((string)$acc['email'], ENT_QUOTES) ?>"
                                    data-provider="<?= htmlspecialchars((string)($acc['provider'] ?? ''), ENT_QUOTES) ?>"
                                    data-imap-host="<?= htmlspecialchars((string)$acc['imap_host'], ENT_QUOTES) ?>"
                                    data-imap-port="<?= (int)$acc['imap_port'] ?>"
                                    data-imap-user="<?= htmlspecialchars((string)$acc['imap_user'], ENT_QUOTES) ?>"
                                    data-imap-mailbox="<?= htmlspecialchars((string)($acc['imap_mailbox'] ?? 'INBOX'), ENT_QUOTES) ?>"
                                    data-imap-secure="<?= (int)$acc['imap_secure'] ?>"
                                    data-imap-only-unseen="<?= (int)$acc['imap_only_unseen'] ?>"
                                    data-smtp-host="<?= htmlspecialchars((string)($acc['smtp_host'] ?? ''), ENT_QUOTES) ?>"
                                    data-smtp-port="<?= (int)($acc['smtp_port'] ?? 465) ?>"
                                    data-smtp-user="<?= htmlspecialchars((string)($acc['smtp_user'] ?? ''), ENT_QUOTES) ?>"
                                    data-is-active="<?= (int)$acc['is_active'] ?>"
                                >Editar</button>
                                <form method="post" onsubmit="return confirm('Eliminar esta cuenta de correo?');">
                                    <input type="hidden" name="action" value="delete_mail_account">
                                    <input type="hidden" name="account_id" value="<?= (int)$acc['id'] ?>">
                                    <button class="mini-btn del" type="submit">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <section class="mail-accounts-panel mail-edit-panel" id="mailEditPanel" style="margin-bottom:12px;">
                <div class="mail-section-title">
                    <h3 id="mailEditTitle">Editar cuenta</h3>
                    <button class="btn-glow" id="mailCancelEditBtn" type="button">Cerrar edicion</button>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="update_mail_account">
                    <input type="hidden" name="account_id" id="editMailAccountId" value="0">
                    <div class="mail-accounts-grid">
                        <input class="form-control search-input" id="editMailEmail" name="email" type="email" placeholder="correo@dominio.com" required>
                        <input class="form-control search-input" id="editMailProvider" name="provider" placeholder="Proveedor (ej. Gmail)">
                        <input class="form-control search-input" id="editMailImapHost" name="imap_host" placeholder="IMAP Host" required>
                        <input class="form-control search-input" id="editMailImapPort" name="imap_port" type="number" value="993" min="1" required>
                        <input class="form-control search-input" id="editMailImapUser" name="imap_user" placeholder="IMAP User" required>
                        <input class="form-control search-input wide" id="editMailImapMailbox" name="imap_mailbox" placeholder="IMAP Mailbox (INBOX)" value="INBOX">
                        <input class="form-control search-input full" id="editMailImapPass" name="imap_pass" placeholder="IMAP Password (dejar vacio para conservar)">
                        <input class="form-control search-input" id="editMailSmtpHost" name="smtp_host" placeholder="SMTP Host">
                        <input class="form-control search-input" id="editMailSmtpPort" name="smtp_port" type="number" value="465" min="1">
                        <input class="form-control search-input" id="editMailSmtpUser" name="smtp_user" placeholder="SMTP User">
                        <input class="form-control search-input full" id="editMailSmtpPass" name="smtp_pass" placeholder="SMTP Password (opcional, vacio conserva)">
                    </div>
                    <div class="mail-actions">
                        <label class="check-inline"><input type="checkbox" id="editMailImapSecure" name="imap_secure" value="1"> IMAP SSL</label>
                        <label class="check-inline"><input type="checkbox" id="editMailOnlyUnseen" name="imap_only_unseen" value="1"> Solo no leidos</label>
                        <label class="check-inline"><input type="checkbox" id="editMailIsActive" name="is_active" value="1"> Activa</label>
                        <button class="btn-main" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button>
                    </div>
                </form>
            </section>
            <div class="mail-section-title mail-add-panel" id="mailAddHeader">
                <h3>Agregar nueva cuenta</h3>
                <button class="btn-glow" id="hideAddMailPanel" type="button">Cancelar alta</button>
            </div>
            <form method="post" class="mail-add-panel" id="mailAddPanel">
                <input type="hidden" name="action" id="mailFormAction" value="add_mail_account">
                <input type="hidden" name="account_id" id="mailAccountId" value="0">
                <div class="mail-accounts-grid">
                    <input class="form-control search-input" id="mailEmail" name="email" type="email" placeholder="correo@dominio.com" required>
                    <input class="form-control search-input" id="mailProvider" name="provider" placeholder="Proveedor (ej. Gmail)">
                    <input class="form-control search-input" id="mailImapHost" name="imap_host" placeholder="IMAP Host" required>
                    <input class="form-control search-input" id="mailImapPort" name="imap_port" type="number" value="993" min="1" required>
                    <input class="form-control search-input" id="mailImapUser" name="imap_user" placeholder="IMAP User" required>
                    <input class="form-control search-input wide" id="mailImapMailbox" name="imap_mailbox" placeholder="IMAP Mailbox (INBOX)" value="INBOX">
                    <input class="form-control search-input full" id="mailImapPass" name="imap_pass" placeholder="IMAP Password" required>
                    <input class="form-control search-input" id="mailSmtpHost" name="smtp_host" placeholder="SMTP Host">
                    <input class="form-control search-input" id="mailSmtpPort" name="smtp_port" type="number" value="465" min="1">
                    <input class="form-control search-input" id="mailSmtpUser" name="smtp_user" placeholder="SMTP User">
                    <input class="form-control search-input full" id="mailSmtpPass" name="smtp_pass" placeholder="SMTP Password">
                </div>
                <div class="mail-actions">
                    <label class="check-inline"><input type="checkbox" id="mailImapSecure" name="imap_secure" value="1" checked> IMAP SSL</label>
                    <label class="check-inline"><input type="checkbox" id="mailOnlyUnseen" name="imap_only_unseen" value="1" checked> Solo no leidos</label>
                    <label class="check-inline"><input type="checkbox" id="mailIsActive" name="is_active" value="1" checked> Activa</label>
                    <button class="btn-main" id="mailSubmitBtn" type="submit"><i class="fa-solid fa-plus"></i> Agregar cuenta</button>
                    <button class="btn-glow" id="mailCancelEditBtn" type="button" style="display:none;">Cancelar edicion</button>
                </div>
            </form>
            <div class="mail-accounts-list mt-2" style="display:none;">
                <?php if (empty($mailAccounts)): ?>
                    <div class="mail-account-row"><div class="mail-account-info">No hay cuentas guardadas. Usa el formulario para vincular la primera.</div></div>
                <?php else: ?>
                    <?php foreach ($mailAccounts as $acc): ?>
                        <div class="mail-account-row">
                            <div class="mail-account-info">
                                <strong><?= htmlspecialchars((string)$acc['email']) ?></strong>
                                · IMAP <?= htmlspecialchars((string)$acc['imap_host']) ?>:<?= (int)$acc['imap_port'] ?>
                                · <?= (int)$acc['is_active'] === 1 ? 'Activa' : 'Inactiva' ?>
                            </div>
                            <div class="mail-account-actions">
                                <form method="post">
                                    <input type="hidden" name="action" value="toggle_mail_account">
                                    <input type="hidden" name="account_id" value="<?= (int)$acc['id'] ?>">
                                    <input type="hidden" name="target_active" value="<?= (int)$acc['is_active'] === 1 ? 0 : 1 ?>">
                                    <button class="mini-btn <?= (int)$acc['is_active'] === 1 ? 'off' : 'on' ?>" type="submit">
                                        <?= (int)$acc['is_active'] === 1 ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                                <button
                                    class="mini-btn edit js-edit-mail-account"
                                    type="button"
                                    data-id="<?= (int)$acc['id'] ?>"
                                    data-email="<?= htmlspecialchars((string)$acc['email'], ENT_QUOTES) ?>"
                                    data-provider="<?= htmlspecialchars((string)($acc['provider'] ?? ''), ENT_QUOTES) ?>"
                                    data-imap-host="<?= htmlspecialchars((string)$acc['imap_host'], ENT_QUOTES) ?>"
                                    data-imap-port="<?= (int)$acc['imap_port'] ?>"
                                    data-imap-user="<?= htmlspecialchars((string)$acc['imap_user'], ENT_QUOTES) ?>"
                                    data-imap-mailbox="<?= htmlspecialchars((string)($acc['imap_mailbox'] ?? 'INBOX'), ENT_QUOTES) ?>"
                                    data-imap-secure="<?= (int)$acc['imap_secure'] ?>"
                                    data-imap-only-unseen="<?= (int)$acc['imap_only_unseen'] ?>"
                                    data-smtp-host="<?= htmlspecialchars((string)($acc['smtp_host'] ?? ''), ENT_QUOTES) ?>"
                                    data-smtp-port="<?= (int)($acc['smtp_port'] ?? 465) ?>"
                                    data-smtp-user="<?= htmlspecialchars((string)($acc['smtp_user'] ?? ''), ENT_QUOTES) ?>"
                                    data-is-active="<?= (int)$acc['is_active'] ?>"
                                >Editar</button>
                                <form method="post" onsubmit="return confirm('Eliminar esta cuenta de correo?');">
                                    <input type="hidden" name="action" value="delete_mail_account">
                                    <input type="hidden" name="account_id" value="<?= (int)$acc['id'] ?>">
                                    <button class="mini-btn del" type="submit">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</section>
<script>
(() => {
    const openBtn = document.getElementById('openMailAccountsModal');
    const openBtnFromCard = document.getElementById('openMailAccountsModalFromCard');
    const closeBtn = document.getElementById('closeMailAccountsModal');
    const modal = document.getElementById('mailAccountsModal');
    const backdrop = document.getElementById('mailAccountsBackdrop');
    const showAddBtn = document.getElementById('showAddMailPanel');
    const hideAddBtn = document.getElementById('hideAddMailPanel');
    const addPanel = document.getElementById('mailAddPanel');
    const addHeader = document.getElementById('mailAddHeader');
    const editPanel = document.getElementById('mailEditPanel');
    const editTitle = document.getElementById('mailEditTitle');
    const cancelEditBtn = document.getElementById('mailCancelEditBtn');
    const editAccountId = document.getElementById('editMailAccountId');
    const editMailEmail = document.getElementById('editMailEmail');
    const editMailProvider = document.getElementById('editMailProvider');
    const editMailImapHost = document.getElementById('editMailImapHost');
    const editMailImapPort = document.getElementById('editMailImapPort');
    const editMailImapUser = document.getElementById('editMailImapUser');
    const editMailImapMailbox = document.getElementById('editMailImapMailbox');
    const editMailImapPass = document.getElementById('editMailImapPass');
    const editMailSmtpHost = document.getElementById('editMailSmtpHost');
    const editMailSmtpPort = document.getElementById('editMailSmtpPort');
    const editMailSmtpUser = document.getElementById('editMailSmtpUser');
    const editMailSmtpPass = document.getElementById('editMailSmtpPass');
    const editMailImapSecure = document.getElementById('editMailImapSecure');
    const editMailOnlyUnseen = document.getElementById('editMailOnlyUnseen');
    const editMailIsActive = document.getElementById('editMailIsActive');
    if (!openBtn || !closeBtn || !modal || !backdrop) return;

    const openModal = () => {
        modal.classList.add('show');
        backdrop.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };
    const closeModal = () => {
        modal.classList.remove('show');
        backdrop.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };
    openBtn.addEventListener('click', openModal);
    openBtnFromCard?.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal();
    });

    cancelEditBtn?.addEventListener('click', () => {
        editPanel?.classList.remove('show');
    });
    showAddBtn?.addEventListener('click', () => {
        editPanel?.classList.remove('show');
        addPanel?.classList.add('show');
        addHeader?.classList.add('show');
        addPanel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    hideAddBtn?.addEventListener('click', () => {
        addPanel?.classList.remove('show');
        addHeader?.classList.remove('show');
    });

    document.querySelectorAll('.js-edit-mail-account').forEach((btn) => {
        btn.addEventListener('click', () => {
            openModal();
            addPanel?.classList.remove('show');
            addHeader?.classList.remove('show');
            editPanel?.classList.add('show');
            if (editTitle) editTitle.textContent = `Editar cuenta: ${btn.getAttribute('data-email') || ''}`;
            if (editAccountId) editAccountId.value = btn.getAttribute('data-id') || '0';
            if (editMailEmail) editMailEmail.value = btn.getAttribute('data-email') || '';
            if (editMailProvider) editMailProvider.value = btn.getAttribute('data-provider') || '';
            if (editMailImapHost) editMailImapHost.value = btn.getAttribute('data-imap-host') || '';
            if (editMailImapPort) editMailImapPort.value = btn.getAttribute('data-imap-port') || '993';
            if (editMailImapUser) editMailImapUser.value = btn.getAttribute('data-imap-user') || '';
            if (editMailImapMailbox) editMailImapMailbox.value = btn.getAttribute('data-imap-mailbox') || 'INBOX';
            if (editMailImapPass) editMailImapPass.value = '';
            if (editMailSmtpHost) editMailSmtpHost.value = btn.getAttribute('data-smtp-host') || '';
            if (editMailSmtpPort) editMailSmtpPort.value = btn.getAttribute('data-smtp-port') || '465';
            if (editMailSmtpUser) editMailSmtpUser.value = btn.getAttribute('data-smtp-user') || '';
            if (editMailSmtpPass) editMailSmtpPass.value = '';
            if (editMailImapSecure) editMailImapSecure.checked = (btn.getAttribute('data-imap-secure') || '1') === '1';
            if (editMailOnlyUnseen) editMailOnlyUnseen.checked = (btn.getAttribute('data-imap-only-unseen') || '1') === '1';
            if (editMailIsActive) editMailIsActive.checked = (btn.getAttribute('data-is-active') || '1') === '1';
            editPanel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
})();

(() => {
    const statusWrap = document.getElementById('whatsappChannelStatus');
    if (!statusWrap) return;
    const pill = (text, icon, state = '') => `<span class="channel-pill ${state}"><i class="${icon}"></i> ${text}</span>`;
    fetch('../whatsapp.php?action=status', { cache: 'no-store' })
        .then((res) => res.json())
        .then((data) => {
            if (!data.ok) {
                statusWrap.innerHTML = pill('OpenWA sin conexion', 'fa-solid fa-triangle-exclamation', 'warn');
                return;
            }
            const ready = Number(data.ready || 0);
            const sessions = Number(data.sessions || 0);
            statusWrap.innerHTML = [
                pill(`${sessions} sesiones`, 'fa-brands fa-whatsapp', sessions > 0 ? 'ok' : 'warn'),
                pill(`${ready} conectadas`, 'fa-solid fa-signal', ready > 0 ? 'ok' : 'warn'),
            ].join('');
        })
        .catch(() => {
            statusWrap.innerHTML = pill('OpenWA no responde', 'fa-solid fa-plug-circle-xmark', 'warn');
        });
})();
</script>
</body>
</html>
