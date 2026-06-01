<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (!estaLogueado()) {
    header('Location: index.php');
    exit;
}

// Unlock session file to allow concurrent AJAX requests
session_write_close();

$usuario = usuarioActual();
$esAdmin = esAdmin();
$esInventario = esInventario();
$nombreRol = nombreRolActual();
$inicioHref = $esInventario ? 'pages/inventario.php?vista=inicio' : 'pages/dashboard.php';

// Asegurar que existe el archivo .env
$envPath = __DIR__ . '/.env';
if (!is_file($envPath)) {
    @copy(__DIR__ . '/.env.example', $envPath);
}

function tgwaBaseUrl(): string {
    $base = getenv('TELEGRAM_BASE_URL') ?: 'http://localhost:3785';
    return rtrim($base, '/');
}

function tgRequest(string $method, string $path, ?array $payload = null, array $query = []): array {
    $url = tgwaBaseUrl() . $path;
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $headers = [
        'Content-Type: application/json',
    ];

    $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE);
    $context = stream_context_create([
        'http' => [
            'method' => strtoupper($method),
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'ignore_errors' => true,
            'timeout' => 15,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);

    $status = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $headerLine, $m)) {
                $status = (int)$m[1];
                break;
            }
        }
    }

    if ($raw === false) {
        return ['ok' => false, 'status' => 0, 'error' => 'Servicio TelegramWA no responde en ' . tgwaBaseUrl()];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'status' => $status, 'error' => 'TelegramWA respondió algo que no es JSON.', 'raw' => $raw];
    }

    return $json;
}

function respondJson(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['action'])) {
    $action = (string)$_GET['action'];

    if ($action === 'status') {
        $res = tgRequest('GET', '/api/status');
        respondJson($res);
    }

    if ($action === 'send_code' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $phone = trim((string)($input['phone'] ?? ''));
        if ($phone === '') {
            respondJson(['ok' => false, 'error' => 'Falta el número de teléfono.'], 400);
        }
        $res = tgRequest('POST', '/api/send-code', ['phone' => $phone]);
        respondJson($res);
    }

    if ($action === 'verify_code' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $code = trim((string)($input['code'] ?? ''));
        $password = trim((string)($input['password'] ?? ''));
        if ($code === '') {
            respondJson(['ok' => false, 'error' => 'Falta el código de verificación.'], 400);
        }
        $res = tgRequest('POST', '/api/verify-code', ['code' => $code, 'password' => $password]);
        respondJson($res);
    }

    if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $res = tgRequest('POST', '/api/logout');
        respondJson($res);
    }

    if ($action === 'chats') {
        $res = tgRequest('GET', '/api/chats');
        respondJson($res);
    }

    if ($action === 'messages') {
        $chatId = trim((string)($_GET['chat_id'] ?? ''));
        if ($chatId === '') {
            respondJson(['ok' => false, 'error' => 'Falta chat_id'], 400);
        }
        $limit = min(200, max(1, (int)($_GET['limit'] ?? 100)));
        $res = tgRequest('GET', '/api/messages', null, ['chatId' => $chatId, 'limit' => $limit]);
        respondJson($res);
    }

    if ($action === 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $chatId = trim((string)($input['chat_id'] ?? ''));
        $text = trim((string)($input['text'] ?? ''));
        if ($chatId === '' || $text === '') {
            respondJson(['ok' => false, 'error' => 'Faltan chat_id o texto.'], 400);
        }
        $res = tgRequest('POST', '/api/send-message', ['chatId' => $chatId, 'text' => $text]);
        respondJson($res);
    }

    if ($action === 'save_config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $apiId = trim((string)($input['api_id'] ?? ''));
        $apiHash = trim((string)($input['api_hash'] ?? ''));

        if ($apiId === '' || $apiHash === '') {
            respondJson(['ok' => false, 'error' => 'Faltan API ID o API Hash.'], 400);
        }

        // Modificar .env
        $envLines = is_file($envPath) ? file($envPath, FILE_IGNORE_NEW_LINES) : [];
        $hasApiId = false;
        $hasApiHash = false;
        foreach ($envLines as &$line) {
            if (str_starts_with(trim($line), 'TG_API_ID=')) {
                $line = "TG_API_ID={$apiId}";
                $hasApiId = true;
            }
            if (str_starts_with(trim($line), 'TG_API_HASH=')) {
                $line = "TG_API_HASH={$apiHash}";
                $hasApiHash = true;
            }
        }
        unset($line);

        if (!$hasApiId) {
            $envLines[] = "TG_API_ID={$apiId}";
        }
        if (!$hasApiHash) {
            $envLines[] = "TG_API_HASH={$apiHash}";
        }

        file_put_contents($envPath, implode("\n", $envLines) . "\n");
        putenv("TG_API_ID={$apiId}");
        putenv("TG_API_HASH={$apiHash}");

        respondJson(['ok' => true]);
    }

    if ($action === 'get_config') {
        respondJson([
            'ok' => true,
            'api_id' => getenv('TG_API_ID') ?: '',
            'api_hash' => getenv('TG_API_HASH') ?: '',
        ]);
    }

    respondJson(['ok' => false, 'error' => 'Acción desconocida.'], 400);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - Telegram</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="assets/js/theme.js"></script>
    <style>
        /* Layout base */
        .main-content { display:flex; flex-direction:column; height:100vh; overflow:hidden; }
        .content-area { flex:1; display:flex; flex-direction:column; min-height:0; padding:0 20px 16px; overflow:hidden; }

        /* Stat cards */
        .tg-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:12px; flex-shrink:0; }
        .tg-stat { border:1px solid rgba(255,255,255,0.08); border-radius:14px; padding:12px 16px; background:linear-gradient(145deg,rgba(15,23,42,0.6),rgba(8,18,35,0.8)); backdrop-filter:blur(10px); box-shadow:0 4px 20px rgba(0,0,0,0.2); transition:transform .2s; }
        .tg-stat:hover { transform:translateY(-2px); }
        .tg-stat strong { display:block; font-size:22px; font-weight:800; background:linear-gradient(to right,#60a5fa,#38bdf8); -webkit-background-clip:text; -webkit-text-fill-color:transparent; line-height:1; }
        .tg-stat span { display:block; color:var(--text-muted); font-size:11px; margin-top:5px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }

        /* Main shell */
        .tg-shell { flex:1; display:grid; grid-template-columns:300px minmax(0,1fr); gap:14px; min-height:0; animation:fadeIn .4s ease-out; }

        /* Left side panel */
        .tg-side { display:flex; flex-direction:column; min-height:0; border:1px solid rgba(255,255,255,0.08); background:rgba(10,20,35,0.55); backdrop-filter:blur(16px); border-radius:18px; overflow:hidden; box-shadow:0 8px 32px rgba(0,0,0,0.3); }
        .tg-section { padding:12px 14px; border-bottom:1px solid rgba(255,255,255,0.06); flex-shrink:0; }

        /* Conversations area fills remaining space */
        .tg-convos-wrap { flex:1; display:flex; flex-direction:column; min-height:0; border-top:1px solid rgba(255,255,255,0.06); }
        .tg-convos-head { flex-shrink:0; padding:9px 12px; border-bottom:1px solid rgba(255,255,255,0.06); display:flex; flex-direction:column; gap:6px; }
        .tg-convos-head-row { display:flex; align-items:center; justify-content:space-between; }
        .tg-convos-list { flex:1; overflow-y:auto; padding:5px 8px; min-height:0; scrollbar-width:thin; scrollbar-color:rgba(255,255,255,0.15) transparent; }

        /* Right chat panel */
        .tg-chat { display:flex; flex-direction:column; min-height:0; border:1px solid rgba(255,255,255,0.08); background:rgba(10,20,35,0.55); backdrop-filter:blur(16px); border-radius:18px; overflow:hidden; box-shadow:0 8px 32px rgba(0,0,0,0.3); }
        .tg-chat-head { flex-shrink:0; padding:10px 14px; border-bottom:1px solid rgba(255,255,255,0.06); display:flex; align-items:center; justify-content:space-between; gap:8px; background:linear-gradient(135deg, rgba(0,0,0,0.2) 0%, rgba(0,20,40,0.15) 100%); }

        /* Header icon-only action buttons */
        .tg-head-btn { position:relative; display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.04); color:#94a3b8; font-size:13px; cursor:pointer; transition:all .18s cubic-bezier(.4,0,.2,1); }
        .tg-head-btn:hover { background:rgba(0,212,255,0.12); border-color:rgba(0,212,255,0.35); color:#67e8f9; transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,212,255,0.15); }
        .tg-head-btn:active { transform:translateY(0); }
        .tg-head-btn.active { background:rgba(0,212,255,0.18); border-color:rgba(0,212,255,0.5); color:#00d4ff; box-shadow:0 0 0 3px rgba(0,212,255,0.12); }
        .tg-head-btn:disabled { opacity:0.35; cursor:not-allowed; transform:none; box-shadow:none; }
        
        /* Session control pill buttons */
        .tg-sess-btn { display:inline-flex; align-items:center; gap:5px; padding:5px 11px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:.3px; border:1px solid; cursor:pointer; transition:all .18s cubic-bezier(.4,0,.2,1); white-space:nowrap; }
        .tg-sess-btn:disabled { opacity:.35; cursor:not-allowed; transform:none !important; box-shadow:none !important; }
        .tg-sess-btn-logout { background:rgba(239,68,68,0.1); border-color:rgba(239,68,68,0.3); color:#fca5a5; }
        .tg-sess-btn-logout:not(:disabled):hover { background:rgba(239,68,68,0.22); border-color:#ef4444; color:#f87171; transform:translateY(-1px); box-shadow:0 4px 12px rgba(239,68,68,0.2); }
        
        /* Divider between search btn and session controls */
        .tg-head-divider { width:1px; height:22px; background:rgba(255,255,255,0.1); flex-shrink:0; }

        /* Message search bar */
        .tg-search-bar { flex-shrink:0; display:flex; align-items:center; gap:10px; padding:0 16px; max-height:0; overflow:hidden; transition:max-height .3s cubic-bezier(.4,0,.2,1), padding .3s cubic-bezier(.4,0,.2,1); border-bottom:1px solid transparent; background:linear-gradient(135deg,rgba(0,212,255,0.04) 0%,rgba(0,20,40,0.08) 100%); }
        .tg-search-bar.open { max-height:58px; padding:9px 16px; border-bottom-color:rgba(0,212,255,0.12); }
        .tg-search-bar input { flex:1; background:rgba(0,0,0,0.3); border:1.5px solid rgba(0,212,255,0.18); border-radius:999px; padding:8px 18px; color:#f8fafc; font-size:13px; outline:none; transition:border-color .2s, box-shadow .2s, background .2s; }
        .tg-search-bar input:focus { background:rgba(0,0,0,0.4); border-color:rgba(0,212,255,0.6); box-shadow:0 0 0 3px rgba(0,212,255,0.1); }
        .tg-search-bar input::placeholder { color:rgba(255,255,255,0.25); font-style:italic; }
        .tg-search-nav { display:flex; align-items:center; gap:4px; flex-shrink:0; }
        .tg-search-count { font-size:11px; color:#64748b; min-width:48px; text-align:center; padding:0 4px; font-variant-numeric:tabular-nums; }
        .tg-search-nav button { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); color:#64748b; border-radius:7px; width:27px; height:27px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .15s; font-size:11px; }
        .tg-search-nav button:hover { background:rgba(0,212,255,0.12); border-color:rgba(0,212,255,0.35); color:#67e8f9; }
        .tg-search-close { background:none; border:none; color:#475569; cursor:pointer; padding:5px 7px; font-size:15px; border-radius:50%; transition:all .15s; display:flex; align-items:center; }
        .tg-search-close:hover { background:rgba(239,68,68,0.12); color:#f87171; }
        mark.tg-highlight { background:rgba(250,204,21,0.28); color:#fef08a; border-radius:3px; padding:0 2px; transition:background .15s; }
        mark.tg-highlight.tg-current { background:rgba(250,204,21,0.75); color:#1c1917; font-weight:700; box-shadow:0 0 0 2px rgba(250,204,21,0.4); }

        .tg-chat-body { flex:1; overflow-y:auto; display:flex; flex-direction:column-reverse; gap:10px; padding:14px; min-height:0; scrollbar-width:thin; scrollbar-color:rgba(255,255,255,0.15) transparent; }
        .tg-compose { flex-shrink:0; padding:10px 14px; border-top:1px solid rgba(255,255,255,0.06); display:flex; align-items:center; gap:10px; background:rgba(0,0,0,0.1); }
        .tg-compose input { border-radius:999px; padding:9px 18px; border:1px solid rgba(255,255,255,0.1); background:rgba(0,0,0,0.2); color:#fff; transition:all .2s; flex:1; min-width:0; }
        .tg-compose input:focus { background:rgba(0,0,0,0.3); border-color:rgba(0,212,255,0.5); box-shadow:0 0 0 3px rgba(0,212,255,0.1); outline:none; }
        .tg-compose button { border-radius:999px; padding:9px 20px; font-weight:700; white-space:nowrap; }

        /* Shared */
        .tg-title { color:#f8fafc; font-size:13px; font-weight:800; margin:0; letter-spacing:.4px; }
        .tg-muted { color:var(--text-muted); font-size:11px; margin:3px 0 0; }
        .tg-row { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .tg-badge { background:rgba(0,212,255,0.15); border:1px solid rgba(0,212,255,0.3); color:#67e8f9; font-size:10px; font-weight:700; border-radius:999px; padding:2px 7px; }
        .tg-empty { color:var(--text-muted); font-size:12px; padding:18px; text-align:center; font-style:italic; }
        
        .tg-status { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:999px; font-size:10px; font-weight:800; background:rgba(148,163,184,.12); color:#cbd5e1; border:1px solid rgba(148,163,184,.24); text-transform:uppercase; letter-spacing:.5px; }
        .tg-status.ready, .tg-status.connected { background:rgba(16,185,129,.14); color:#86efac; border-color:rgba(16,185,129,.35); }
        .tg-status.code_sent { background:rgba(245,158,11,.12); color:#fcd34d; border-color:rgba(245,158,11,.35); }
        .tg-status.disconnected { background:rgba(239,68,68,.12); color:#fca5a5; border-color:rgba(239,68,68,.35); }

        /* Conversation items */
        .tg-convo { width:100%; text-align:left; border:1px solid rgba(255,255,255,0.04); border-radius:12px; padding:9px 10px; background:rgba(255,255,255,0.02); color:#e5edf8; margin-bottom:6px; cursor:pointer; transition:all .2s; display:block; }
        .tg-convo-top { display:flex; align-items:center; justify-content:space-between; gap:8px; min-width:0; }
        .tg-convo-name { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12.5px; font-weight:800; color:#f8fafc; }
        .tg-convo-time { flex-shrink:0; color:rgba(148,163,184,.82); font-size:10px; }
        .tg-convo-preview { display:block; color:var(--text-muted); font-size:10.8px; margin-top:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .tg-convo-meta { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:5px; }
        .tg-convo-count { flex-shrink:0; border-radius:999px; padding:2px 7px; background:rgba(15,23,42,.66); border:1px solid rgba(148,163,184,.16); color:#bae6fd; font-size:10px; font-weight:800; }
        .tg-convo:hover { background:rgba(255,255,255,0.05); border-color:rgba(0,212,255,0.3); transform:translateX(2px); }
        .tg-convo.active { background:rgba(0,212,255,0.08); border-color:rgba(0,212,255,0.55); }

        /* Bubbles */
        .tg-bubble { max-width:min(76%,680px); padding:9px 13px; color:#f1f5f9; word-break:break-word; font-size:13.5px; line-height:1.5; animation:slideUp .25s ease-out; box-shadow:0 2px 8px rgba(0,0,0,0.15); }
        .tg-bubble.outgoing { align-self:flex-end; background:linear-gradient(135deg,rgba(37,99,235,0.85),rgba(14,165,233,0.85)); border-radius:16px 16px 4px 16px; }
        .tg-bubble.incoming { align-self:flex-start; background:rgba(30,41,59,0.9); border-radius:16px 16px 16px 4px; border:1px solid rgba(255,255,255,0.07); }
        .tg-meta { color:rgba(255,255,255,0.5); font-size:10px; margin-top:4px; display:flex; gap:8px; flex-wrap:wrap; }
        .tg-bubble.outgoing .tg-meta { justify-content:flex-end; color:rgba(255,255,255,0.65); }
        .tg-bubble-from { font-size:10px; font-weight:700; color:#00d4ff; margin-bottom:3px; }

        .tg-input-row { display:grid; grid-template-columns:1fr auto; gap:8px; }
        .tg-btn-new { display:flex; align-items:center; justify-content:center; gap:6px; padding:6px 12px; font-size:12px; font-weight:600; border-radius:999px; cursor:pointer; background:rgba(0,212,255,0.1); border:1px solid rgba(0,212,255,0.25); color:#67e8f9; transition:all .2s; width:100%; }
        .tg-btn-new:hover { background:rgba(0,212,255,0.18); }
        .tg-search { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:9px; padding:6px 11px; color:#fff; font-size:12px; width:100%; outline:none; transition:border-color .2s; }
        .tg-search:focus { border-color:rgba(0,212,255,0.4); }
        .tg-search::placeholder { color:rgba(255,255,255,0.3); }

        /* Loader */
        .tg-loader { height: 3px; width: 100%; background: rgba(0,212,255,0.1); position: relative; overflow: hidden; flex-shrink: 0; display: none; }
        .tg-loader::after { content: ''; position: absolute; top: 0; left: 0; height: 100%; width: 40%; background: #00d4ff; animation: loadingBar 1s infinite ease-in-out; border-radius: 3px; }
        @keyframes loadingBar { 0% { left: -40%; } 100% { left: 100%; } }

        @keyframes slideUp { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
        @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
        @media (max-width:980px) { .tg-shell { grid-template-columns:1fr; } .tg-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:560px) { .tg-grid { grid-template-columns:1fr; } .tg-compose,.tg-input-row { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo"><div class="logo-icon-sm"><i class="fa-solid fa-hexagon-nodes"></i></div><span class="logo-text-sm">Nexus<strong>Panel</strong></span></div>
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
    </div>
    <div class="sidebar-user">
        <div class="user-avatar"><?= htmlspecialchars(strtoupper(substr((string)($usuario['nombre'] ?? 'U'), 0, 1))) ?></div>
        <div class="user-info"><span class="user-name"><?= htmlspecialchars((string)($usuario['nombre'] ?? 'Usuario')) ?></span><span class="user-role role-admin"><i class="fa-brands fa-telegram"></i> <?= htmlspecialchars($nombreRol) ?></span></div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="<?= htmlspecialchars($inicioHref) ?>" class="nav-item"><i class="fa-solid fa-chart-line"></i><span>Inicio</span></a>
        <?php if ($esAdmin): ?><a href="pages/usuarios.php" class="nav-item"><i class="fa-solid fa-users"></i><span>Usuarios</span></a><?php endif; ?>
        <a href="pages/productos.php" class="nav-item"><i class="fa-solid fa-flask-vial"></i><span>Productos e inventario</span></a>
        <a href="pages/pedidos.php" class="nav-item"><i class="fa-solid fa-receipt"></i><span>Pedidos</span></a>
        <a href="pages/mensajes.php" class="nav-item"><i class="fa-solid fa-comments"></i><span>Mensajes internos</span></a>
        <a href="whatsapp.php" class="nav-item"><i class="fa-brands fa-whatsapp"></i><span>WhatsApp</span></a>
        <a href="telegram.php" class="nav-item active"><i class="fa-brands fa-telegram"></i><span>Telegram</span><div class="nav-indicator"></div></a>
        <div class="nav-section-label">Cuenta</div>
        <a href="logout.php" class="nav-item nav-logout"><i class="fa-solid fa-right-from-bracket"></i><span>Cerrar sesion</span></a>
    </nav>
</aside>

<main class="main-content">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenu"><i class="fa-solid fa-bars"></i></button>
            <div class="breadcrumb-custom"><span>NexusPanel</span><i class="fa-solid fa-chevron-right"></i><span class="active">Telegram</span></div>
        </div>
        <div class="topbar-right">
            <button class="topbar-btn theme-toggle" onclick="toggleTheme()" title="Cambiar tema"><i class="fa-solid fa-moon theme-toggle-icon"></i></button>
            <div class="topbar-date" id="topbarDate"></div>
        </div>
    </header>

    <div class="content-area">
        <div class="tg-grid">
            <div class="tg-stat"><strong id="statStatus">Desconectado</strong><span>Estado</span></div>
            <div class="tg-stat"><strong id="statPhone">-</strong><span>Teléfono</span></div>
            <div class="tg-stat"><strong id="statChats">0</strong><span>Conversaciones</span></div>
            <div class="tg-stat" style="display:none !important;"><strong id="statMessages">0</strong><span>Mensajes cargados</span></div>
        </div>

        <div class="tg-shell">
            <section class="tg-side">
                <!-- Header -->
                <div class="tg-section">
                    <div class="tg-row">
                        <div>
                            <h3 class="tg-title"><i class="fab fa-telegram" style="color:#00d4ff;"></i> Telegram</h3>
                            <p class="tg-muted" id="apiHint">Telegram Personal (MTProto)</p>
                        </div>
                        <button class="btn-primary-custom btn-table" type="button" id="refreshBtn" title="Actualizar"><i class="fa-solid fa-rotate"></i></button>
                    </div>
                </div>

                <!-- API config -->
                <div class="tg-section">
                    <div style="font-size:11px;font-weight:800;color:#00d4ff;text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px;"><i class="fa-solid fa-key"></i> Credenciales API (my.telegram.org)</div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <input class="modal-input" id="tgApiId" placeholder="API ID (ej. 12345)" style="font-size:12px; padding:6px 12px;">
                        <input class="modal-input" id="tgApiHash" placeholder="API Hash (ej. abcde123...)" style="font-size:12px; padding:6px 12px;" type="password">
                        <button class="btn-secondary-custom" type="button" id="saveConfigBtn" style="font-size:12px; padding:6px 12px; width:100%; justify-content:center;"><i class="fa-solid fa-floppy-disk"></i> Guardar Credenciales</button>
                    </div>
                </div>

                <!-- Authentication Wizard -->
                <div class="tg-section" id="authSection">
                    <!-- Step 1: Disconnected (Enter Phone) -->
                    <div id="authStepPhone" style="display:none;">
                        <label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:5px;">Número de Teléfono</label>
                        <div class="tg-input-row">
                            <input class="modal-input" id="phoneInput" placeholder="+521234567890" style="font-size:12px; padding:7px 12px;">
                            <button class="btn-secondary-custom" type="button" id="sendCodeBtn" style="font-size:12px; padding:7px 12px;"><i class="fa-solid fa-paper-plane"></i> Enviar</button>
                        </div>
                    </div>

                    <!-- Step 2: Code Sent (OTP & Password) -->
                    <div id="authStepVerify" style="display:none;">
                        <label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:5px;">Código de Verificación (OTP)</label>
                        <input class="modal-input" id="codeInput" placeholder="Código recibido en Telegram" style="font-size:12px; padding:7px 12px; width:100%; margin-bottom:8px;">
                        
                        <label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:5px;">Contraseña de 2 Pasos (opcional)</label>
                        <input class="modal-input" id="passwordInput" type="password" placeholder="Tu contraseña si tienes 2FA" style="font-size:12px; padding:7px 12px; width:100%; margin-bottom:8px;">
                        
                        <div style="display:flex; gap:6px;">
                            <button class="btn-danger-custom" type="button" id="cancelVerifyBtn" style="flex:1; font-size:12px; padding:7px 12px;"><i class="fa-solid fa-arrow-left"></i> Atrás</button>
                            <button class="btn-secondary-custom" type="button" id="verifyCodeBtn" style="flex:1; font-size:12px; padding:7px 12px;"><i class="fa-solid fa-check"></i> Verificar</button>
                        </div>
                    </div>

                    <!-- Step 3: Connected -->
                    <div id="authStepConnected" style="display:none;">
                        <div style="display:flex; align-items:center; gap:10px; padding:10px; background:rgba(0,212,255,0.07); border:1px solid rgba(0,212,255,0.25); border-radius:12px;">
                            <div style="width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,#00d4ff,#0088cc); display:flex; align-items:center; justify-content:center; font-size:18px; color:#fff; flex-shrink:0;"><i class="fa-solid fa-user"></i></div>
                            <div style="min-width:0; flex:1;">
                                <div id="connectedName" style="font-weight:800; font-size:13px; color:#f8fafc; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">-</div>
                                <div id="connectedPhone" style="font-size:11px; color:#00d4ff;">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chats / Conversations -->
                <div class="tg-convos-wrap">
                    <div class="tg-convos-head">
                        <div class="tg-convos-head-row">
                            <h3 class="tg-title"><i class="fa-regular fa-comments"></i> Chats</h3>
                            <span class="tg-badge" id="statConvosBadge">0</span>
                        </div>
                        <button class="tg-btn-new" onclick="startNewChat()"><i class="fas fa-plus"></i> Nuevo Chat</button>
                        <input id="chatSearch" class="tg-search" placeholder="🔍 Buscar..." oninput="filterChats(this.value)">
                    </div>
                    <div id="chatListLoader" class="tg-loader"></div>
                    <div class="tg-convos-list" id="conversationList"><div class="tg-empty">Vincula tu cuenta para ver chats.</div></div>
                </div>
            </section>

            <section class="tg-chat">
                <div class="tg-chat-head">
                    <!-- Contact info -->
                    <div style="min-width:0; flex:1;">
                        <h3 class="tg-title" id="chatTitle" style="font-size:14px;">Mensajes de Telegram</h3>
                        <p class="tg-muted" id="chatSubtitle">Vincula tu cuenta y selecciona una conversación.</p>
                    </div>

                    <!-- Action buttons -->
                    <div style="display:flex; align-items:center; gap:6px; flex-shrink:0;">
                        <!-- Search toggle -->
                        <button class="tg-head-btn" type="button" id="searchToggleBtn" title="Buscar en la conversación">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>

                        <div class="tg-head-divider"></div>

                        <!-- Session controls -->
                        <button class="tg-sess-btn tg-sess-btn-logout" type="button" id="logoutSessionBtn" disabled title="Desvincular cuenta">
                            <i class="fa-solid fa-link-slash" style="font-size:10px;"></i> Desvincular
                        </button>

                        <div class="tg-head-divider"></div>

                        <!-- Status badge -->
                        <span class="tg-status" id="sessionStatus">Desconectado</span>
                    </div>
                </div>

                <!-- Message Search Bar -->
                <div class="tg-search-bar" id="msgSearchBar">
                    <i class="fa-solid fa-magnifying-glass" style="color:rgba(0,212,255,0.6); font-size:13px; flex-shrink:0;"></i>
                    <input type="text" id="msgSearchInput" placeholder="Buscar mensajes en esta conversación..." autocomplete="off">
                    <div class="tg-search-nav">
                        <span class="tg-search-count" id="msgSearchCount"></span>
                        <button type="button" id="msgSearchPrev" title="Resultado anterior (Shift+Enter)"><i class="fa-solid fa-chevron-up"></i></button>
                        <button type="button" id="msgSearchNext" title="Siguiente resultado (Enter)"><i class="fa-solid fa-chevron-down"></i></button>
                    </div>
                    <button class="tg-search-close" type="button" id="msgSearchClose" title="Cerrar búsqueda (Esc)"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <div class="tg-chat-body" id="chatBody"><div class="tg-empty">Aún no hay mensajes cargados.</div></div>
                
                <!-- Compose -->
                <div class="tg-compose">
                    <input type="text" id="messageInput" placeholder="Escribe un mensaje..." disabled>
                    <button class="btn-secondary-custom" id="sendBtn" style="border-radius:999px; padding:9px 20px;" disabled><i class="fa-solid fa-paper-plane"></i></button>
                </div>
            </section>
        </div>
    </div>
</main>

<!-- New chat modal -->
<div id="newChatModal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:9999;display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px);">
    <div style="background:rgba(10,20,35,0.95);border:1px solid rgba(0,212,255,0.3);border-radius:18px;padding:24px;width:min(420px,92vw);box-shadow:0 24px 60px rgba(0,0,0,0.5);">
        <h3 style="color:#f8fafc;font-size:16px;font-weight:800;margin:0 0 6px;"><i class="fa-brands fa-telegram" style="color:#00d4ff;"></i> Enviar mensaje nuevo</h3>
        <p style="color:#94a3b8;font-size:12px;margin:0 0 16px;">Ingresa el Chat ID (numérico), el número telefónico (+521...) o el @username del destinatario.</p>
        <label style="font-size:11px;color:#64748b;display:block;margin-bottom:5px;">Destinatario</label>
        <input type="text" id="newChatId" placeholder="Ej: @usuario o +521234567890 o -10012345" style="width:100%;background:rgba(0,0,0,0.3);border:1.5px solid rgba(255,255,255,0.1);border-radius:10px;padding:9px 13px;color:#f8fafc;font-size:13px;outline:none;box-sizing:border-box;margin-bottom:10px;">
        <label style="font-size:11px;color:#64748b;display:block;margin-bottom:5px;">Mensaje</label>
        <textarea id="newChatMsg" rows="3" placeholder="Escribe tu mensaje..." style="width:100%;background:rgba(0,0,0,0.3);border:1.5px solid rgba(255,255,255,0.1);border-radius:10px;padding:9px 13px;color:#f8fafc;font-size:13px;outline:none;box-sizing:border-box;resize:none;"></textarea>
        <div style="display:flex;gap:8px;margin-top:14px;justify-content:flex-end;">
            <button class="tg-sess-btn" style="border-color:rgba(255,255,255,0.1);color:#64748b;background:none;" onclick="closeNewChat()">Cancelar</button>
            <button class="btn-secondary-custom" id="sendNewChatBtn" onclick="sendNewChat()" style="font-size:12px; padding:7px 15px;"><i class="fa-solid fa-paper-plane"></i> Enviar</button>
        </div>
        <div id="newChatErr" style="font-size:11px;color:#f87171;margin-top:8px;min-height:16px;"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/dashboard.js"></script>
<script>
// ── State ────────────────────────────────────────────────────────────────────
const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

let selectedChat = null;
let chats = [];
let messages = [];
let sessionStatus = 'disconnected';
let isConnected = false;
let pollingTimer = null;

// ── API helper ───────────────────────────────────────────────────────────────
async function api(action, opts = {}) {
    const method = opts.method || 'GET';
    const body = opts.body ? JSON.stringify(opts.body) : null;
    const url = `telegram.php?action=${action}${opts.query || ''}`;
    const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body
    });
    return res.json();
}

// ── Translation helper ───────────────────────────────────────────────────────
function translateStatus(s) {
    if (s === 'ready') return 'Conectado';
    if (s === 'code_sent') return 'Ingresar OTP';
    if (s === 'disconnected') return 'Desconectado';
    return s || 'Desconectado';
}

// ── Load credentials ─────────────────────────────────────────────────────────
async function loadConfig() {
    try {
        const res = await api('get_config');
        if (res.ok) {
            $('tgApiId').value = res.api_id || '';
            $('tgApiHash').value = res.api_hash || '';
        }
    } catch (e) {
        console.error('Error al cargar config', e);
    }
}

// ── Save credentials ─────────────────────────────────────────────────────────
$('saveConfigBtn').addEventListener('click', async () => {
    const apiId = $('tgApiId').value.trim();
    const apiHash = $('tgApiHash').value.trim();
    if (!apiId || !apiHash) {
        alert('Ingresa el API ID y API Hash.');
        return;
    }
    const btn = $('saveConfigBtn');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';
    btn.disabled = true;
    try {
        const res = await api('save_config', { method: 'POST', body: { api_id: apiId, api_hash: apiHash } });
        if (res.ok) {
            alert('Configuración guardada correctamente.');
        } else {
            alert('Error: ' + res.error);
        }
    } catch (err) {
        alert('Error: ' + err.message);
    } finally {
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar Credenciales';
        btn.disabled = false;
    }
});

// ── Refresh session status ───────────────────────────────────────────────────
async function refreshSessionStatus() {
    try {
        const res = await api('status');
        if (res.ok) {
            const oldStatus = sessionStatus;
            sessionStatus = res.status;
            
            // Update stats cards
            $('statStatus').textContent = translateStatus(sessionStatus);
            $('statPhone').textContent = res.phone || '-';

            // Show/Hide steps
            if (sessionStatus === 'ready') {
                isConnected = true;
                $('authStepPhone').style.display = 'none';
                $('authStepVerify').style.display = 'none';
                $('authStepConnected').style.display = 'block';
                $('connectedName').textContent = res.name || 'Telegram User';
                $('connectedPhone').textContent = res.phone || '';
                $('logoutSessionBtn').disabled = false;
                $('sessionStatus').className = 'tg-status ready';
                $('sessionStatus').textContent = 'Conectado';
            } else if (sessionStatus === 'code_sent') {
                isConnected = false;
                $('authStepPhone').style.display = 'none';
                $('authStepVerify').style.display = 'block';
                $('authStepConnected').style.display = 'none';
                $('logoutSessionBtn').disabled = true;
                $('sessionStatus').className = 'tg-status code_sent';
                $('sessionStatus').textContent = 'Ingresar OTP';
            } else {
                isConnected = false;
                $('authStepPhone').style.display = 'block';
                $('authStepVerify').style.display = 'none';
                $('authStepConnected').style.display = 'none';
                $('logoutSessionBtn').disabled = true;
                $('sessionStatus').className = 'tg-status disconnected';
                $('sessionStatus').textContent = 'Desconectado';
            }

            // Transition to ready: triggers chat load
            if (oldStatus !== 'ready' && sessionStatus === 'ready') {
                await loadChats();
            }
        } else {
            $('statStatus').textContent = 'Offline';
            $('sessionStatus').className = 'tg-status disconnected';
            $('sessionStatus').textContent = 'Inicia el servidor Node';
            $('statPhone').textContent = '-';
            $('authStepPhone').style.display = 'block';
            $('authStepVerify').style.display = 'none';
            $('authStepConnected').style.display = 'none';
            $('logoutSessionBtn').disabled = true;
        }
    } catch (e) {
        console.error('Error al actualizar estado', e);
    }
}

// ── Send Code ────────────────────────────────────────────────────────────────
$('sendCodeBtn').addEventListener('click', async () => {
    const phone = $('phoneInput').value.trim();
    if (!phone) {
        alert('Ingresa tu número de teléfono.');
        return;
    }
    const btn = $('sendCodeBtn');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    btn.disabled = true;
    try {
        const res = await api('send_code', { method: 'POST', body: { phone } });
        if (res.ok) {
            await refreshSessionStatus();
        } else {
            alert('Error: ' + res.error);
        }
    } catch (e) {
        alert('Error al enviar código: ' + e.message);
    } finally {
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Enviar';
        btn.disabled = false;
    }
});

// ── Verify Code ──────────────────────────────────────────────────────────────
$('verifyCodeBtn').addEventListener('click', async () => {
    const code = $('codeInput').value.trim();
    const password = $('passwordInput').value.trim();
    if (!code) {
        alert('Ingresa el código OTP.');
        return;
    }
    const btn = $('verifyCodeBtn');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verificando...';
    btn.disabled = true;
    try {
        const res = await api('verify_code', { method: 'POST', body: { code, password } });
        if (res.ok) {
            $('codeInput').value = '';
            $('passwordInput').value = '';
            await refreshSessionStatus();
        } else {
            alert('Error: ' + res.error);
        }
    } catch (e) {
        alert('Error al verificar: ' + e.message);
    } finally {
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Verificar';
        btn.disabled = false;
    }
});

// ── Cancel Verify ────────────────────────────────────────────────────────────
$('cancelVerifyBtn').addEventListener('click', async () => {
    const btn = $('cancelVerifyBtn');
    btn.disabled = true;
    try {
        await api('logout', { method: 'POST' });
        await refreshSessionStatus();
    } catch (e) {
        console.error(e);
    } finally {
        btn.disabled = false;
    }
});

// ── Logout ───────────────────────────────────────────────────────────────────
$('logoutSessionBtn').addEventListener('click', async () => {
    if (!confirm('¿Estás seguro de que deseas desvincular esta cuenta de Telegram?')) return;
    const btn = $('logoutSessionBtn');
    btn.disabled = true;
    try {
        await api('logout', { method: 'POST' });
        selectedChat = null;
        chats = [];
        messages = [];
        renderChats();
        renderMessages();
        $('chatTitle').textContent = 'Mensajes de Telegram';
        $('chatSubtitle').textContent = 'Vincula tu cuenta y selecciona una conversación.';
        await refreshSessionStatus();
    } catch (e) {
        alert('Error al cerrar sesión: ' + e.message);
    } finally {
        btn.disabled = false;
    }
});

// ── Load Chats ───────────────────────────────────────────────────────────────
let isLoadingChats = false;
async function loadChats() {
    if (!isConnected || isLoadingChats) return;
    isLoadingChats = true;
    $('chatListLoader').style.display = 'block';
    try {
        const res = await api('chats');
        if (res.ok && Array.isArray(res.data)) {
            chats = res.data;
            renderChats();
            $('statChats').textContent = chats.length;
            $('statConvosBadge').textContent = chats.length;
        }
    } catch (e) {
        console.error('Error al cargar chats', e);
    } finally {
        $('chatListLoader').style.display = 'none';
        isLoadingChats = false;
    }
}

// ── Render Chats ──────────────────────────────────────────────────────────────
let chatFilterTerm = '';
function filterChats(term) {
    chatFilterTerm = term.toLowerCase().trim();
    renderChats();
}

function renderChats() {
    const list = $('conversationList');
    if (!isConnected) {
        list.innerHTML = '<div class="tg-empty">Vincula tu cuenta para ver chats.</div>';
        return;
    }
    
    const filtered = chats.filter(c => {
        if (!chatFilterTerm) return true;
        return c.name.toLowerCase().includes(chatFilterTerm) || (c.username || '').toLowerCase().includes(chatFilterTerm);
    });

    if (filtered.length === 0) {
        list.innerHTML = '<div class="tg-empty">No se encontraron chats.</div>';
        return;
    }

    list.innerHTML = filtered.map(c => {
        const active = selectedChat && selectedChat.id === c.id ? 'active' : '';
        const unreadBadge = c.unread > 0 ? `<span class="tg-convo-count">${c.unread}</span>` : '';
        
        let dateStr = '';
        if (c.lastDate) {
            const date = new Date(c.lastDate * 1000);
            dateStr = date.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
        }

        return `
        <button class="tg-convo ${active}" onclick="selectChat('${esc(c.id)}', '${esc(c.name)}', '${esc(c.username)}')">
            <div class="tg-convo-top">
                <span class="tg-convo-name">${esc(c.name)}</span>
                <span class="tg-convo-time">${dateStr}</span>
            </div>
            <div class="tg-convo-preview">${esc(c.lastMsg || 'Sin mensajes')}</div>
            <div class="tg-convo-meta">
                <span style="font-size:10px; color:rgba(0,212,255,0.8);">${esc(c.type)}</span>
                ${unreadBadge}
            </div>
        </button>
        `;
    }).join('');
}

// ── Select Chat ──────────────────────────────────────────────────────────────
function selectChat(chatId, name, username) {
    selectedChat = { id: chatId, name, username };
    $('chatTitle').textContent = name;
    $('chatSubtitle').textContent = username ? '@' + username : `ID: ${chatId}`;
    $('messageInput').disabled = false;
    $('sendBtn').disabled = false;
    
    if (window.closeSearch) window.closeSearch();
    renderChats();
    loadMessages();
}

// ── Load Messages ────────────────────────────────────────────────────────────
let isLoadingMessages = false;
async function loadMessages() {
    if (!selectedChat || isLoadingMessages) return;
    isLoadingMessages = true;
    try {
        const res = await api('messages', { query: `&chat_id=${encodeURIComponent(selectedChat.id)}` });
        if (res.ok && Array.isArray(res.data)) {
            messages = res.data;
            renderMessages();
        }
    } catch (e) {
        console.error('Error al cargar mensajes', e);
    } finally {
        isLoadingMessages = false;
    }
}

// ── Render Messages ──────────────────────────────────────────────────────────
function renderMessages() {
    const body = $('chatBody');
    if (messages.length === 0) {
        body.innerHTML = '<div class="tg-empty">No hay mensajes cargados.</div>';
        return;
    }
    
    body.innerHTML = [...messages].reverse().map(m => {
        const date = new Date(m.date * 1000);
        const timeStr = date.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
        const outgoing = m.out ? 'outgoing' : 'incoming';
        const senderName = (!m.out && m.fromName) ? `<div class="tg-bubble-from">${esc(m.fromName)}</div>` : '';

        return `
        <div class="tg-bubble ${outgoing}">
            ${senderName}
            <div>${esc(m.text)}</div>
            <div class="tg-meta"><span>${timeStr}</span></div>
        </div>
        `;
    }).join('');

    if (window.highlightMatches && $('msgSearchInput').value) {
        window.highlightMatches($('msgSearchInput').value.trim());
    }
}

// ── Send Message ─────────────────────────────────────────────────────────────
async function sendMessage() {
    if (!selectedChat) return;
    const input = $('messageInput');
    const text = input.value.trim();
    if (!text) return;

    input.value = '';
    const btn = $('sendBtn');
    btn.disabled = true;

    try {
        const res = await api('send_message', { method: 'POST', body: { chat_id: selectedChat.id, text } });
        if (res.ok) {
            await loadMessages();
        } else {
            alert('Error al enviar: ' + res.error);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        btn.disabled = false;
        input.focus();
    }
}

$('sendBtn').addEventListener('click', sendMessage);
$('messageInput').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

// ── Polling Loop ─────────────────────────────────────────────────────────────
function startPolling() {
    stopPolling();
    // Fast polling every 3 seconds
    pollingTimer = setInterval(async () => {
        await refreshSessionStatus();
        if (isConnected) {
            await loadChats();
            if (selectedChat) {
                await loadMessages();
            }
        }
    }, 3000);
}

function stopPolling() {
    if (pollingTimer) {
        clearInterval(pollingTimer);
        pollingTimer = null;
    }
}

// ── New Chat Modal ───────────────────────────────────────────────────────────
function startNewChat() {
    $('newChatModal').style.display = 'flex';
    setTimeout(() => $('newChatId').focus(), 50);
}

function closeNewChat() {
    $('newChatModal').style.display = 'none';
}

async function sendNewChat() {
    const chatId = $('newChatId').value.trim();
    const text = $('newChatMsg').value.trim();
    $('newChatErr').textContent = '';
    
    if (!chatId || !text) {
        $('newChatErr').textContent = 'Completa todos los campos.';
        return;
    }

    const btn = $('sendNewChatBtn');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';
    btn.disabled = true;

    try {
        const res = await api('send_message', { method: 'POST', body: { chat_id: chatId, text } });
        if (res.ok) {
            closeNewChat();
            $('newChatId').value = '';
            $('newChatMsg').value = '';
            selectChat(chatId, chatId, '');
            await loadChats();
        } else {
            $('newChatErr').textContent = res.error || 'Error al iniciar chat.';
        }
    } catch (e) {
        $('newChatErr').textContent = 'Error: ' + e.message;
    } finally {
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Enviar';
        btn.disabled = false;
    }
}

// ── Message Search ──────────────────────────────────────────────────────────
(function() {
    const searchBar   = $('msgSearchBar');
    const searchInput = $('msgSearchInput');
    const searchCount = $('msgSearchCount');
    const toggleBtn   = $('searchToggleBtn');
    const closeBtn    = $('msgSearchClose');
    const prevBtn     = $('msgSearchPrev');
    const nextBtn     = $('msgSearchNext');

    let matches      = [];
    let currentIndex = -1;

    function openSearch() {
        searchBar.classList.add('open');
        searchInput.focus();
        toggleBtn.classList.add('active');
    }

    function closeSearch() {
        searchBar.classList.remove('open');
        searchInput.value = '';
        clearHighlights();
        searchCount.textContent = '';
        toggleBtn.classList.remove('active');
    }
    window.closeSearch = closeSearch;

    function clearHighlights() {
        const chatBody = $('chatBody');
        chatBody.querySelectorAll('mark.tg-highlight').forEach(mark => {
            const parent = mark.parentNode;
            parent.replaceChild(document.createTextNode(mark.textContent), mark);
            parent.normalize();
        });
        matches = [];
        currentIndex = -1;
    }

    function escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function highlightMatches(query) {
        clearHighlights();
        if (!query) { searchCount.textContent = ''; return; }

        const chatBody = $('chatBody');
        const regex    = new RegExp(`(${escapeRegex(query)})`, 'gi');

        const walker = document.createTreeWalker(
            chatBody,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode(node) {
                    const tag = node.parentElement?.tagName?.toUpperCase();
                    if (['SCRIPT','STYLE','BUTTON','TIME','INPUT'].includes(tag)) return NodeFilter.FILTER_REJECT;
                    return regex.test(node.nodeValue) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
                }
            }
        );

        const nodesToWrap = [];
        let n;
        while ((n = walker.nextNode())) nodesToWrap.push(n);

        nodesToWrap.forEach(textNode => {
            regex.lastIndex = 0;
            const parts = textNode.nodeValue.split(regex);
            if (parts.length <= 1) return;

            const frag = document.createDocumentFragment();
            parts.forEach(part => {
                if (regex.test(part)) {
                    regex.lastIndex = 0;
                    const mark = document.createElement('mark');
                    mark.className = 'tg-highlight';
                    mark.textContent = part;
                    frag.appendChild(mark);
                    matches.push(mark);
                } else {
                    frag.appendChild(document.createTextNode(part));
                }
            });
            textNode.parentNode.replaceChild(frag, textNode);
        });

        if (matches.length > 0) {
            currentIndex = 0;
            scrollToCurrent();
        }
        updateCount();
    }
    window.highlightMatches = highlightMatches;

    function scrollToCurrent() {
        matches.forEach((m, i) => {
            m.classList.toggle('tg-current', i === currentIndex);
        });
        if (matches[currentIndex]) {
            matches[currentIndex].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function updateCount() {
        if (matches.length === 0) {
            searchCount.textContent = searchInput.value ? 'Sin resultados' : '';
        } else {
            searchCount.textContent = `${currentIndex + 1} / ${matches.length}`;
        }
    }

    toggleBtn.addEventListener('click', () => {
        if (searchBar.classList.contains('open')) {
            closeSearch();
        } else {
            openSearch();
        }
    });

    closeBtn.addEventListener('click', closeSearch);

    searchInput.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeSearch(); return; }
        if (e.key === 'Enter') {
            e.preventDefault();
            if (e.shiftKey) {
                goToPrev();
            } else {
                goToNext();
            }
        }
    });

    searchInput.addEventListener('input', () => {
        highlightMatches(searchInput.value.trim());
    });

    function goToNext() {
        if (!matches.length) return;
        currentIndex = (currentIndex + 1) % matches.length;
        scrollToCurrent();
        updateCount();
    }

    function goToPrev() {
        if (!matches.length) return;
        currentIndex = (currentIndex - 1 + matches.length) % matches.length;
        scrollToCurrent();
        updateCount();
    }

    nextBtn.addEventListener('click', goToNext);
    prevBtn.addEventListener('click', goToPrev);
})();

// ── Refresh action button ────────────────────────────────────────────────────
$('refreshBtn').addEventListener('click', async () => {
    const btn = $('refreshBtn');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    btn.disabled = true;
    try {
        await refreshSessionStatus();
        if (isConnected) {
            await loadChats();
            if (selectedChat) {
                await loadMessages();
            }
        }
    } catch(e) {
        console.error(e);
    } finally {
        btn.innerHTML = '<i class="fa-solid fa-rotate"></i>';
        btn.disabled = false;
    }
});

// ── DOM Init ──────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    await loadConfig();
    await refreshSessionStatus();
    startPolling();
});
</script>
</body>
</html>
