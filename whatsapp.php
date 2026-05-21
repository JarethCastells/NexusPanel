<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (!estaLogueado()) {
    header('Location: index.php');
    exit;
}

$usuario = usuarioActual();
$esAdmin = esAdmin();
$esInventario = esInventario();
$nombreRol = nombreRolActual();
$inicioHref = $esInventario ? 'pages/inventario.php?vista=inicio' : 'pages/dashboard.php';

function openwaBaseUrl(): string {
    $base = getenv('OPENWA_BASE_URL') ?: 'http://localhost:2785';
    return rtrim($base, '/');
}

function openwaApiKey(): string {
    $fromEnv = getenv('OPENWA_API_KEY');
    if ($fromEnv !== false && trim($fromEnv) !== '') {
        return trim($fromEnv);
    }

    $keyPath = __DIR__ . '/OpenWA/data/.api-key';
    if (is_file($keyPath)) {
        $key = trim((string)file_get_contents($keyPath));
        if ($key !== '') {
            return $key;
        }
    }

    return 'dev-admin-key';
}

function openwaRequest(string $method, string $path, ?array $payload = null, array $query = []): array {
    $url = openwaBaseUrl() . '/api' . $path;
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $headers = [
        'Content-Type: application/json',
        'X-API-Key: ' . openwaApiKey(),
    ];

    $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE);
    $context = stream_context_create([
        'http' => [
            'method' => strtoupper($method),
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'ignore_errors' => true,
            'timeout' => 12,
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
        return ['ok' => false, 'status' => 0, 'error' => 'No se pudo conectar con OpenWA en ' . openwaBaseUrl()];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'status' => $status, 'error' => 'OpenWA respondio algo que no es JSON.', 'raw' => $raw];
    }

    $data = array_key_exists('data', $json) && array_key_exists('success', $json) ? $json['data'] : $json;
    if (is_array($data) && array_key_exists('value', $data) && is_array($data['value'])) {
        $data = $data['value'];
    }

    $ok = $status >= 200 && $status < 300 && (($json['success'] ?? true) !== false);

    return [
        'ok' => $ok,
        'status' => $status,
        'data' => $data,
        'error' => $json['message'] ?? $json['error'] ?? ($ok ? null : 'Error de OpenWA'),
    ];
}

function respondJson(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeMessages(array $payload): array {
    $messages = $payload['messages'] ?? $payload;
    if (!is_array($messages)) {
        return ['messages' => [], 'total' => 0];
    }

    $rows = [];
    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }
        $isFromMe = !empty($message['fromMe']) || (isset($message['id']['fromMe']) && $message['id']['fromMe']);
        $dir = (string)($message['direction'] ?? '');
        if ($dir === '') {
            $dir = $isFromMe ? 'outgoing' : 'incoming';
        }

        $cId = (string)($message['chatId'] ?? '');
        if ($cId === '') {
            $cId = (string)($isFromMe ? ($message['to'] ?? '') : ($message['from'] ?? ''));
        }

        $rows[] = [
            'id' => (string)($message['id']['_serialized'] ?? $message['id'] ?? $message['waMessageId'] ?? ''),
            'chatId' => $cId,
            'body' => (string)($message['body'] ?? $message['text'] ?? $message['caption'] ?? ''),
            'type' => (string)($message['type'] ?? 'text'),
            'direction' => $dir,
            'status' => (string)($message['status'] ?? ''),
            'timestamp' => $message['timestamp'] ?? $message['createdAt'] ?? null,
            'createdAt' => $message['createdAt'] ?? null,
        ];
    }

    usort($rows, function ($a, $b) {
        return strtotime((string)($b['createdAt'] ?? $b['timestamp'] ?? '')) <=> strtotime((string)($a['createdAt'] ?? $a['timestamp'] ?? ''));
    });

    return ['messages' => $rows, 'total' => (int)($payload['total'] ?? count($rows))];
}

function conversationSummary(array $messages): array {
    $byChat = [];
    foreach ($messages as $message) {
        $chatId = $message['chatId'] ?? '';
        if ($chatId === '') {
            continue;
        }
        if (!isset($byChat[$chatId])) {
            $byChat[$chatId] = [
                'chatId' => $chatId,
                'lastBody' => $message['body'] ?: '[' . ($message['type'] ?: 'mensaje') . ']',
                'lastAt' => $message['createdAt'] ?? $message['timestamp'] ?? null,
                'count' => 0,
                'incoming' => 0,
                'outgoing' => 0,
            ];
        }
        $byChat[$chatId]['count']++;
        if (($message['direction'] ?? '') === 'incoming') {
            $byChat[$chatId]['incoming']++;
        } elseif (($message['direction'] ?? '') === 'outgoing') {
            $byChat[$chatId]['outgoing']++;
        }
    }

    return array_values($byChat);
}

function normalizeSessionsPayload($payload): array {
    if (!is_array($payload)) {
        return [];
    }
    if (isset($payload['sessions']) && is_array($payload['sessions'])) {
        return $payload['sessions'];
    }
    if (array_is_list($payload)) {
        return $payload;
    }
    return [];
}

if (isset($_GET['action'])) {
    $action = (string)$_GET['action'];

    if ($action === 'status') {
        $res = openwaRequest('GET', '/sessions');
        $sessions = $res['ok'] ? normalizeSessionsPayload($res['data'] ?? []) : [];
        $ready = 0;
        foreach ($sessions as $session) {
            $state = strtolower((string)($session['status'] ?? ''));
            if (in_array($state, ['ready', 'connected'], true)) {
                $ready++;
            }
        }
        respondJson([
            'ok' => $res['ok'],
            'reachable' => $res['status'] >= 200 && $res['status'] < 500,
            'base_url' => openwaBaseUrl(),
            'sessions' => count($sessions),
            'ready' => $ready,
            'status' => $res['status'],
            'error' => $res['error'] ?? null,
        ], $res['ok'] ? 200 : 200);
    }

    if ($action === 'sessions') {
        $res = openwaRequest('GET', '/sessions');
        respondJson($res, $res['ok'] ? 200 : ($res['status'] ?: 502));
    }

    if ($action === 'engine_chats') {
        $sessionId = trim((string)($_GET['session_id'] ?? ''));
        if ($sessionId === '') {
            respondJson(['ok' => false, 'error' => 'Falta session_id'], 400);
        }
        $query = ['limit' => min(1000, max(1, (int)($_GET['limit'] ?? 500)))];
        $res = openwaRequest('GET', '/sessions/' . rawurlencode($sessionId) . '/chats', null, $query);
        respondJson($res, $res['ok'] ? 200 : ($res['status'] ?: 502));
    }

    if ($action === 'engine_chat_messages') {
        $sessionId = trim((string)($_GET['session_id'] ?? ''));
        $chatId = trim((string)($_GET['chat_id'] ?? ''));
        if ($sessionId === '' || $chatId === '') {
            respondJson(['ok' => false, 'error' => 'Falta session_id o chat_id'], 400);
        }
        $query = ['limit' => min(200, max(1, (int)($_GET['limit'] ?? 50)))];
        $res = openwaRequest('GET', '/sessions/' . rawurlencode($sessionId) . '/chats/' . rawurlencode($chatId) . '/messages', null, $query);
        respondJson($res, $res['ok'] ? 200 : ($res['status'] ?: 502));
    }

    if ($action === 'messages') {
        $sessionId = trim((string)($_GET['session_id'] ?? ''));
        $chatId = trim((string)($_GET['chat_id'] ?? ''));
        if ($sessionId === '') {
            respondJson(['ok' => false, 'error' => 'Falta session_id'], 400);
        }
        $query = ['limit' => min(300, max(1, (int)($_GET['limit'] ?? 120)))];
        if ($chatId !== '') {
            $query['chatId'] = $chatId;
        }
        $res = openwaRequest('GET', '/sessions/' . rawurlencode($sessionId) . '/messages', null, $query);
        if ($res['ok']) {
            $normalized = normalizeMessages(is_array($res['data']) ? $res['data'] : []);
            $res['data'] = $normalized + ['conversations' => conversationSummary($normalized['messages'])];
        }
        respondJson($res, $res['ok'] ? 200 : ($res['status'] ?: 502));
    }

    if ($action === 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $sessionId = trim((string)($input['session_id'] ?? ''));
        $chatId = trim((string)($input['chat_id'] ?? ''));
        $text = trim((string)($input['text'] ?? ''));
        if ($sessionId === '' || $chatId === '' || $text === '') {
            respondJson(['ok' => false, 'error' => 'Faltan session_id, chat_id o texto.'], 400);
        }
        $res = openwaRequest('POST', '/sessions/' . rawurlencode($sessionId) . '/messages/send-text', [
            'chatId' => $chatId,
            'text' => $text,
        ]);
        respondJson($res, $res['ok'] ? 200 : ($res['status'] ?: 502));
    }

    if ($action === 'create_session' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $name = preg_replace('/[^a-zA-Z0-9-]/', '-', trim((string)($input['name'] ?? 'mi-whatsapp')));
        $name = trim((string)$name, '-') ?: 'mi-whatsapp';
        $res = openwaRequest('POST', '/sessions', ['name' => $name]);
        respondJson($res, $res['ok'] ? 200 : ($res['status'] ?: 502));
    }

    if ($action === 'start_session' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $sessionId = trim((string)($input['session_id'] ?? ''));
        if ($sessionId === '') {
            respondJson(['ok' => false, 'error' => 'Falta session_id'], 400);
        }
        $res = openwaRequest('POST', '/sessions/' . rawurlencode($sessionId) . '/start');
        respondJson($res, $res['ok'] ? 200 : ($res['status'] ?: 502));
    }

    if ($action === 'stop_session' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $sessionId = trim((string)($input['session_id'] ?? ''));
        if ($sessionId === '') {
            respondJson(['ok' => false, 'error' => 'Falta session_id'], 400);
        }
        $res = openwaRequest('POST', '/sessions/' . rawurlencode($sessionId) . '/stop');
        respondJson($res, $res['ok'] ? 200 : ($res['status'] ?: 502));
    }

    if ($action === 'logout_session' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $sessionId = trim((string)($input['session_id'] ?? ''));
        if ($sessionId === '') {
            respondJson(['ok' => false, 'error' => 'Falta session_id'], 400);
        }
        // Llamamos a logout para desvincular
        $res = openwaRequest('POST', '/sessions/' . rawurlencode($sessionId) . '/logout');
        respondJson($res, $res['ok'] ? 200 : ($res['status'] ?: 502));
    }

    if ($action === 'delete_session' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $sessionId = trim((string)($input['session_id'] ?? ''));
        if ($sessionId === '') {
            respondJson(['ok' => false, 'error' => 'Falta session_id'], 400);
        }
        $res = openwaRequest('DELETE', '/sessions/' . rawurlencode($sessionId));
        respondJson($res, $res['ok'] ? 200 : ($res['status'] ?: 502));
    }

    if ($action === 'qr') {
        $sessionId = trim((string)($_GET['session_id'] ?? ''));
        if ($sessionId === '') {
            respondJson(['ok' => false, 'error' => 'Falta session_id'], 400);
        }
        $res = openwaRequest('GET', '/sessions/' . rawurlencode($sessionId) . '/qr');
        respondJson($res, $res['ok'] ? 200 : ($res['status'] ?: 502));
    }

    respondJson(['ok' => false, 'error' => 'Accion no soportada.'], 404);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel - WhatsApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="assets/js/theme.js"></script>
    <style>
        /* Layout base */
        .main-content { display:flex; flex-direction:column; height:100vh; overflow:hidden; }
        .content-area { flex:1; display:flex; flex-direction:column; min-height:0; padding:0 20px 16px; overflow:hidden; }

        /* Stat cards */
        .wa-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:12px; flex-shrink:0; }
        .wa-stat { border:1px solid rgba(255,255,255,0.08); border-radius:14px; padding:12px 16px; background:linear-gradient(145deg,rgba(15,23,42,0.6),rgba(8,18,35,0.8)); backdrop-filter:blur(10px); box-shadow:0 4px 20px rgba(0,0,0,0.2); transition:transform .2s; }
        .wa-stat:hover { transform:translateY(-2px); }
        .wa-stat strong { display:block; font-size:22px; font-weight:800; background:linear-gradient(to right,#60a5fa,#38bdf8); -webkit-background-clip:text; -webkit-text-fill-color:transparent; line-height:1; }
        .wa-stat span { display:block; color:var(--text-muted); font-size:11px; margin-top:5px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }

        /* Main shell */
        .wa-shell { flex:1; display:grid; grid-template-columns:300px minmax(0,1fr); gap:14px; min-height:0; animation:fadeIn .4s ease-out; }

        /* Left side panel */
        .wa-side { display:flex; flex-direction:column; min-height:0; border:1px solid rgba(255,255,255,0.08); background:rgba(10,20,35,0.55); backdrop-filter:blur(16px); border-radius:18px; overflow:hidden; box-shadow:0 8px 32px rgba(0,0,0,0.3); }
        .wa-section { padding:12px 14px; border-bottom:1px solid rgba(255,255,255,0.06); flex-shrink:0; }

        /* Sessions area */
        .wa-sessions-wrap { max-height:150px; overflow-y:auto; padding:5px 10px; flex-shrink:0; scrollbar-width:thin; scrollbar-color:rgba(255,255,255,0.1) transparent; }

        /* Conversations area fills remaining space */
        .wa-convos-wrap { flex:1; display:flex; flex-direction:column; min-height:0; border-top:1px solid rgba(255,255,255,0.06); }
        .wa-convos-head { flex-shrink:0; padding:9px 12px; border-bottom:1px solid rgba(255,255,255,0.06); display:flex; flex-direction:column; gap:6px; }
        .wa-convos-head-row { display:flex; align-items:center; justify-content:space-between; }
        .wa-convos-list { flex:1; overflow-y:auto; padding:5px 8px; min-height:0; scrollbar-width:thin; scrollbar-color:rgba(255,255,255,0.15) transparent; }

        /* Right chat panel */
        .wa-chat { display:flex; flex-direction:column; min-height:0; border:1px solid rgba(255,255,255,0.08); background:rgba(10,20,35,0.55); backdrop-filter:blur(16px); border-radius:18px; overflow:hidden; box-shadow:0 8px 32px rgba(0,0,0,0.3); }
        .wa-chat-head { flex-shrink:0; padding:12px 16px; border-bottom:1px solid rgba(255,255,255,0.06); display:flex; align-items:center; justify-content:space-between; gap:10px; background:rgba(0,0,0,0.1); }
        .wa-chat-body { flex:1; overflow-y:auto; display:flex; flex-direction:column-reverse; gap:10px; padding:14px; min-height:0; scrollbar-width:thin; scrollbar-color:rgba(255,255,255,0.15) transparent; }
        .wa-compose { flex-shrink:0; padding:10px 14px; border-top:1px solid rgba(255,255,255,0.06); display:grid; grid-template-columns:1fr auto; gap:10px; background:rgba(0,0,0,0.1); }
        .wa-compose input { border-radius:999px; padding:9px 18px; border:1px solid rgba(255,255,255,0.1); background:rgba(0,0,0,0.2); color:#fff; transition:all .2s; width:100%; }
        .wa-compose input:focus { background:rgba(0,0,0,0.3); border-color:rgba(0,212,255,0.5); box-shadow:0 0 0 3px rgba(0,212,255,0.1); outline:none; }
        .wa-compose button { border-radius:999px; padding:9px 20px; font-weight:700; white-space:nowrap; }

        /* Shared */
        .wa-title { color:#f8fafc; font-size:13px; font-weight:800; margin:0; letter-spacing:.4px; }
        .wa-muted { color:var(--text-muted); font-size:11px; margin:3px 0 0; }
        .wa-row { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .wa-badge { background:rgba(0,212,255,0.15); border:1px solid rgba(0,212,255,0.3); color:#67e8f9; font-size:10px; font-weight:700; border-radius:999px; padding:2px 7px; }
        .wa-empty { color:var(--text-muted); font-size:12px; padding:18px; text-align:center; font-style:italic; }
        .wa-status { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:999px; font-size:10px; font-weight:800; background:rgba(148,163,184,.12); color:#cbd5e1; border:1px solid rgba(148,163,184,.24); text-transform:uppercase; letter-spacing:.5px; }
        .wa-status.ready,.wa-status.connected { background:rgba(16,185,129,.14); color:#86efac; border-color:rgba(16,185,129,.35); }
        .wa-status.qr_ready,.wa-status.initializing,.wa-status.qr { background:rgba(245,158,11,.12); color:#fcd34d; border-color:rgba(245,158,11,.35); }

        /* Session items */
        .wa-session { display:flex; align-items:center; justify-content:space-between; padding:8px 10px; border:1px solid rgba(255,255,255,0.05); border-radius:10px; background:rgba(255,255,255,0.02); color:#e5edf8; margin-bottom:5px; cursor:pointer; transition:all .2s; }
        .wa-session-info { flex:1; min-width:0; }
        .wa-session strong { display:block; font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:700; }
        .wa-session span { display:block; color:var(--text-muted); font-size:10.5px; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .wa-session:hover { background:rgba(255,255,255,0.05); border-color:rgba(0,212,255,.4); }
        .wa-session.active { background:rgba(0,212,255,0.08); border-color:rgba(0,212,255,.6); }
        .wa-session-delete { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); color:#fca5a5; width:26px; height:26px; border-radius:7px; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all .2s; margin-left:6px; opacity:0; flex-shrink:0; }
        .wa-session:hover .wa-session-delete { opacity:1; }
        .wa-session-delete:hover { background:rgba(239,68,68,0.3); color:#fff; }

        /* Conversation items */
        .wa-convo { width:100%; text-align:left; border:1px solid rgba(255,255,255,0.04); border-radius:10px; padding:8px 10px; background:rgba(255,255,255,0.02); color:#e5edf8; margin-bottom:4px; cursor:pointer; transition:all .2s; display:block; }
        .wa-convo strong { display:block; font-size:12.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:700; }
        .wa-convo span { display:block; color:var(--text-muted); font-size:10.5px; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .wa-convo:hover { background:rgba(255,255,255,0.05); border-color:rgba(0,212,255,.3); transform:translateX(2px); }
        .wa-convo.active { background:rgba(0,212,255,0.08); border-color:rgba(0,212,255,.55); }

        /* Bubbles */
        .wa-bubble { max-width:min(76%,680px); padding:9px 13px; color:#f1f5f9; word-break:break-word; font-size:13.5px; line-height:1.5; animation:slideUp .25s ease-out; box-shadow:0 2px 8px rgba(0,0,0,0.15); }
        .wa-bubble.outgoing { align-self:flex-end; background:linear-gradient(135deg,rgba(37,99,235,0.85),rgba(14,165,233,0.85)); border-radius:16px 16px 4px 16px; }
        .wa-bubble.incoming { align-self:flex-start; background:rgba(30,41,59,0.9); border-radius:16px 16px 16px 4px; border:1px solid rgba(255,255,255,0.07); }
        .wa-meta { color:rgba(255,255,255,0.5); font-size:10px; margin-top:4px; display:flex; gap:8px; flex-wrap:wrap; }
        .wa-bubble.outgoing .wa-meta { justify-content:flex-end; color:rgba(255,255,255,0.65); }

        /* Misc */
        .wa-qr { text-align:center; }
        .wa-qr img { max-width:80%; border-radius:14px; background:#fff; padding:10px; box-shadow:0 8px 24px rgba(0,0,0,0.3); }
        .wa-input-row { display:grid; grid-template-columns:1fr auto; gap:8px; }
        .btn-danger-custom { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:6px 12px; font-size:12px; font-weight:600; border-radius:8px; cursor:pointer; transition:all .2s; background:rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.4); color:#fca5a5; }
        .btn-danger-custom:hover:not(:disabled) { background:rgba(239,68,68,0.25); border-color:rgba(239,68,68,0.6); }
        .btn-danger-custom:disabled { opacity:.5; cursor:not-allowed; }
        .wa-search { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:9px; padding:6px 11px; color:#fff; font-size:12px; width:100%; outline:none; transition:border-color .2s; }
        .wa-search:focus { border-color:rgba(0,212,255,0.4); }
        .wa-search::placeholder { color:rgba(255,255,255,0.3); }
        .wa-btn-new { display:flex; align-items:center; justify-content:center; gap:6px; padding:6px 12px; font-size:12px; font-weight:600; border-radius:9px; cursor:pointer; background:rgba(0,212,255,0.1); border:1px solid rgba(0,212,255,0.25); color:#67e8f9; transition:all .2s; width:100%; }
        .wa-btn-new:hover { background:rgba(0,212,255,0.18); }

        @keyframes slideUp { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
        @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
        @media (max-width:980px) { .wa-shell { grid-template-columns:1fr; } .wa-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:560px) { .wa-grid { grid-template-columns:1fr; } .wa-compose,.wa-input-row { grid-template-columns:1fr; } }
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
        <div class="user-info"><span class="user-name"><?= htmlspecialchars((string)($usuario['nombre'] ?? 'Usuario')) ?></span><span class="user-role role-admin"><i class="fa-brands fa-whatsapp"></i> <?= htmlspecialchars($nombreRol) ?></span></div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="<?= htmlspecialchars($inicioHref) ?>" class="nav-item"><i class="fa-solid fa-chart-line"></i><span>Inicio</span></a>
        <?php if ($esAdmin): ?><a href="pages/usuarios.php" class="nav-item"><i class="fa-solid fa-users"></i><span>Usuarios</span></a><?php endif; ?>
        <a href="pages/productos.php" class="nav-item"><i class="fa-solid fa-flask-vial"></i><span>Productos e inventario</span></a>
        <a href="pages/pedidos.php" class="nav-item"><i class="fa-solid fa-receipt"></i><span>Pedidos</span></a>
        <a href="pages/mensajes.php" class="nav-item"><i class="fa-solid fa-comments"></i><span>Mensajes internos</span></a>
        <a href="whatsapp.php" class="nav-item active"><i class="fa-brands fa-whatsapp"></i><span>WhatsApp</span><div class="nav-indicator"></div></a>
        <div class="nav-section-label">Cuenta</div>
        <a href="logout.php" class="nav-item nav-logout"><i class="fa-solid fa-right-from-bracket"></i><span>Cerrar sesion</span></a>
    </nav>
</aside>

<main class="main-content">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu-btn" id="mobileMenu"><i class="fa-solid fa-bars"></i></button>
            <div class="breadcrumb-custom"><span>NexusPanel</span><i class="fa-solid fa-chevron-right"></i><span class="active">WhatsApp</span></div>
        </div>
        <div class="topbar-right">
            <button class="topbar-btn theme-toggle" onclick="toggleTheme()" title="Cambiar tema"><i class="fa-solid fa-moon theme-toggle-icon"></i></button>
            <div class="topbar-date" id="topbarDate"></div>
        </div>
    </header>

    <div class="content-area">
        <div class="wa-grid">
            <div class="wa-stat"><strong id="statSessions">0</strong><span>Sesiones</span></div>
            <div class="wa-stat"><strong id="statReady">0</strong><span>Conectadas</span></div>
            <div class="wa-stat"><strong id="statConvos">0</strong><span>Conversaciones</span></div>
            <div class="wa-stat"><strong id="statMessages">0</strong><span>Mensajes cargados</span></div>
        </div>

        <div class="wa-shell">
            <section class="wa-side">
                <!-- Header -->
                <div class="wa-section">
                    <div class="wa-row">
                        <div>
                            <h3 class="wa-title"><i class="fab fa-whatsapp" style="color:#25d366;"></i> WhatsApp</h3>
                            <p class="wa-muted" id="apiHint"><?= htmlspecialchars(openwaBaseUrl()) ?></p>
                        </div>
                        <button class="btn-primary-custom btn-table" type="button" id="refreshBtn" title="Actualizar"><i class="fa-solid fa-rotate"></i></button>
                    </div>
                </div>

                <!-- Create session -->
                <div class="wa-section">
                    <div class="wa-input-row">
                        <input class="modal-input" id="newSessionName" placeholder="mi-whatsapp" style="font-size:12px; padding:7px 12px;">
                        <button class="btn-secondary-custom" type="button" id="createSessionBtn" style="font-size:12px; padding:7px 12px;"><i class="fa-solid fa-plus"></i> Crear</button>
                    </div>
                </div>

                <!-- Sessions list -->
                <div class="wa-section" style="padding-bottom:6px;">
                    <h3 class="wa-title">Sesiones</h3>
                </div>
                <div class="wa-sessions-wrap" id="sessionList"><div class="wa-empty">Cargando...</div></div>

                <!-- QR box -->
                <div class="wa-section wa-qr" id="qrBox" style="display:none; flex-shrink:0;">
                    <h3 class="wa-title">Escanea el QR</h3>
                    <p class="wa-muted">Abre WhatsApp â†’ Dispositivos vinculados</p>
                    <div id="qrContent" style="margin-top:10px;"></div>
                </div>

                <!-- Conversations (fills remaining space) -->
                <div class="wa-convos-wrap">
                    <div class="wa-convos-head">
                        <div class="wa-convos-head-row">
                            <h3 class="wa-title"><i class="fa-regular fa-comments"></i> Chats</h3>
                            <span class="wa-badge" id="statConvosBadge">0</span>
                        </div>
                        <button class="wa-btn-new" onclick="startNewChat()"><i class="fas fa-plus"></i> Nuevo Chat</button>
                        <input id="chatSearch" class="wa-search" placeholder="ðŸ” Buscar..." oninput="filterChats(this.value)">
                    </div>
                    <div class="wa-convos-list" id="conversationList"><div class="wa-empty">Selecciona una sesiÃ³n.</div></div>
                </div>
            </section>

            <section class="wa-chat">
                <div class="wa-chat-head">
                    <div style="min-width:0;">
                        <h3 class="wa-title" id="chatTitle">Mensajes de WhatsApp</h3>
                        <p class="wa-muted" id="chatSubtitle">Selecciona una sesion y una conversacion.</p>
                    </div>
                    <div class="wa-row" style="flex-shrink:0; gap:8px;">
                        <button class="btn-secondary-custom" type="button" id="startSessionBtn" disabled style="font-size:12px; padding:6px 12px;"><i class="fa-solid fa-play"></i> Iniciar</button>
                        <button class="btn-secondary-custom" type="button" id="stopSessionBtn" disabled style="font-size:12px; padding:6px 12px;"><i class="fa-solid fa-power-off"></i> Cerrar</button>
                        <button class="btn-danger-custom" type="button" id="logoutSessionBtn" disabled><i class="fa-solid fa-link-slash"></i> Desvincular</button>
                        <span class="wa-status" id="sessionStatus">sin sesion</span>
                    </div>
                </div>
                <div class="wa-chat-body" id="chatBody"><div class="wa-empty">Aun no hay mensajes cargados.</div></div>
                <form class="wa-compose" id="composeForm">
                    <input class="modal-input" id="messageInput" placeholder="Escribe un mensaje..." disabled style="border-radius:999px;">
                    <button class="btn-primary-custom" type="submit" id="sendBtn" disabled><i class="fa-solid fa-paper-plane"></i> Enviar</button>
                </form>
            </section>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/dashboard.js"></script>
<script>
let sessions = [];
let selectedSession = null;
let selectedChat = '';
let latestMessages = [];
let convos = [];
let pollTimer = null;
let currentQr = '';


const $ = (id) => document.getElementById(id);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));


async function api(action, options = {}) {
    const res = await fetch(`whatsapp.php?action=${action}${options.query || ''}`, {
        method: options.method || 'GET',
        headers: { 'Content-Type': 'application/json' },
        body: options.body ? JSON.stringify(options.body) : undefined,
        cache: 'no-store'
    });
    const payload = await res.json().catch(() => ({ ok:false, error:'Respuesta invalida.' }));
    if (!res.ok || payload.ok === false) throw new Error(payload.error || 'No se completo la accion.');
    return payload.data ?? payload;
}

async function refreshSelectedSession() {
    if (!selectedSession) return;
    try {
        const data = await api('sessions');
        sessions = Array.isArray(data) ? data : [];
        const updated = sessions.find(s => s.id === selectedSession.id);
        if (updated) {
            selectedSession = updated;
            renderSessions();
            setStatus(selectedSession);
        }
    } catch (_) {
        // Ignore refresh failures; keep using existing session state.
    }
}

function labelForChat(chatId) {
    const convo = Array.isArray(convos) ? convos.find(c => c.chatId === chatId) : null;
    if (convo && convo.name) return convo.name;
    return String(chatId || '').replace('@c.us', '').replace('@g.us', ' (grupo)');
}

function fmtDate(value) {
    if (!value) return '';
    const ts = typeof value === 'number' ? (value < 1e12 ? value * 1000 : value) : value;
    const date = new Date(ts);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString('es-MX', { dateStyle:'short', timeStyle:'short' });
}

function setStatus(session) {
    const status = $('sessionStatus');
    const raw = String(session?.status || 'sin sesion');
    const state = raw.toLowerCase();
    status.textContent = raw;
    status.className = `wa-status ${state}`;
    $('startSessionBtn').disabled = !session || ['ready','connected','initializing'].includes(state);
    $('stopSessionBtn').disabled = !session || !['ready','connected','initializing','qr_ready','authenticating'].includes(state);
    $('logoutSessionBtn').disabled = !session;
}

function renderSessions() {
    $('statSessions').textContent = sessions.length;
    $('statReady').textContent = sessions.filter(s => ['ready','connected'].includes(String(s.status || '').toLowerCase())).length;
    const list = $('sessionList');
    if (!sessions.length) {
        list.innerHTML = '<div class="wa-empty">No hay sesiones. Crea una y dale iniciar.</div>';
        setStatus(null);
        return;
    }
    list.innerHTML = sessions.map(s => `
        <div class="wa-session ${selectedSession?.id === s.id ? 'active' : ''}" data-session-id="${esc(s.id)}">
            <div class="wa-session-info" onclick="selectSession('${esc(s.id)}')">
                <strong>${esc(s.name || s.id)}</strong>
                <span>${esc(s.phone || s.pushName || s.id)} - ${esc(s.status || 'sin estado')}</span>
            </div>
            <button class="wa-session-delete" data-delete-id="${esc(s.id)}" title="Eliminar sesiÃ³n y quitarla del panel"><i class="fa-solid fa-xmark"></i></button>
        </div>
    `).join('');



    list.querySelectorAll('.wa-session-delete').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            deleteSession(btn.dataset.deleteId);
        });
    });
}

async function loadSessions() {
    try {
        const data = await api('sessions');
        sessions = Array.isArray(data) ? data : [];
        if (selectedSession) {
            const found = sessions.find(s => s.id === selectedSession.id);
            if (found) {
                selectedSession = found;
            } else {
                selectedSession = null;
                selectedChat = '';
                latestMessages = [];
            }
        }
        if (!selectedSession && sessions.length > 0) {
            selectedSession = sessions[0];
            selectedChat = '';
            latestMessages = [];
        }

        renderSessions();
        setStatus(selectedSession);

        if (selectedSession) {
            await loadMessages(false);
        } else {
            $('chatTitle').textContent = 'Mensajes de WhatsApp';
            $('chatSubtitle').textContent = 'Crea o selecciona una sesiÃ³n.';
            $('statConvos').textContent = '0';
            $('conversationList').innerHTML = '<div class="wa-empty">Selecciona una sesiÃ³n.</div>';
            renderChat();
        }
    } catch (err) {
        const baseUrl = $('apiHint')?.textContent || 'http://localhost:2785';
        sessions = [];
        selectedSession = null;
        selectedChat = '';
        latestMessages = [];
        convos = [];
        $('statSessions').textContent = '0';
        $('statReady').textContent = '0';
        $('statConvos').textContent = '0';
        $('statMessages').textContent = '0';
        $('sessionStatus').textContent = 'sin conexion';
        $('sessionStatus').className = 'wa-status';
        $('sessionList').innerHTML = `
            <div class="wa-empty" style="color:#fcd34d;line-height:1.55;">
                OpenWA no esta activo.<br>
                El panel esta listo, pero falta levantar el servicio en ${esc(baseUrl)}.
            </div>`;
        $('conversationList').innerHTML = '<div class="wa-empty">Primero levanta OpenWA y crea o inicia una sesion.</div>';
        $('chatTitle').textContent = 'WhatsApp pendiente de conexion';
        $('chatSubtitle').textContent = 'El conector PHP funciona; falta que el backend OpenWA este corriendo.';
        $('chatBody').innerHTML = `
            <div class="wa-empty" style="max-width:560px;margin:0 auto;line-height:1.7;color:#cbd5e1;">
                <strong style="color:#f8fafc;">No se pudo conectar con OpenWA.</strong><br>
                Necesitamos el backend OpenWA de tu companero o la URL donde este corriendo.
                Cuando responda en <code style="color:#67e8f9;">${esc(baseUrl)}</code>, aqui apareceran las sesiones, QR, chats y mensajes.
            </div>`;
        $('createSessionBtn').disabled = true;
        $('startSessionBtn').disabled = true;
        $('stopSessionBtn').disabled = true;
        $('logoutSessionBtn').disabled = true;
        $('messageInput').disabled = true;
        $('sendBtn').disabled = true;
    }
}

function filterChats(query) {
    const q = query.toLowerCase().trim();
    const btns = $('conversationList').querySelectorAll('.wa-convo');
    btns.forEach(btn => {
        const text = btn.textContent.toLowerCase();
        btn.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
}

function startNewChat() {
    if (!selectedSession || !['ready','connected'].includes(String(selectedSession.status || '').toLowerCase())) {
        return alert('Necesitas tener una sesiÃ³n vinculada y lista para iniciar un chat.');
    }
    const num = prompt('NÃºmero de WhatsApp (con cÃ³digo de paÃ­s, ej. 5215512345678):');
    if (!num) return;
    const clean = num.replace(/\D/g, '');
    if (!clean) return;
    const chatId = clean + '@c.us';
    if (!convos.find(c => c.chatId === chatId)) {
        convos.unshift({ chatId, name: '+' + clean, lastBody: '', lastAt: Date.now(), count: 0, incoming: 0, outgoing: 0 });
    }
    selectedChat = chatId;
    latestMessages = latestMessages.filter(m => m.chatId === chatId);
    renderConversations(convos);
    renderChat();
}

async function selectSession(id) {
    selectedSession = sessions.find(s => s.id === id) || null;
    selectedChat = '';
    latestMessages = [];
    convos = [];
    lastMsgId = '';
    currentQr = '';
    stopPolling();
    renderSessions();
    setStatus(selectedSession);
    $('chatTitle').textContent = selectedSession ? (selectedSession.name || selectedSession.id) : 'Mensajes de WhatsApp';
    $('chatSubtitle').textContent = selectedSession ? 'Cargando chats...' : 'Selecciona una sesion.';
    $('messageInput').disabled = true;
    $('sendBtn').disabled = true;
    await loadMessages(true);
    startPolling();
}

async function deleteSession(id) {
    if (!confirm('Â¿Seguro que deseas ELIMINAR esta sesiÃ³n por completo? Se removerÃ¡ del panel.')) return;
    try {
        await api('delete_session', { method:'POST', body:{ session_id:id } });
        if (selectedSession && selectedSession.id === id) {
            selectedSession = null;
            selectedChat = '';
            latestMessages = [];
        }
        await loadSessions();
    } catch (err) {
        alert(err.message);
    }
}

function renderConversations(items) {
    convos = items; // Keep global in sync
    $('statConvos').textContent = items.length;
    if ($('statConvosBadge')) $('statConvosBadge').textContent = items.length;
    const list = $('conversationList');
    if (!items.length) {
        list.innerHTML = '<div class="wa-empty">Sin conversaciones todavia. Usa "Iniciar Nuevo Chat" para escribir.</div>';
        return;
    }
    list.innerHTML = items.map(c => `
        <button class="wa-convo ${selectedChat === c.chatId ? 'active' : ''}" type="button" data-chat-id="${esc(c.chatId)}">
            <strong>${esc(labelForChat(c.chatId))}</strong>
            <span>${esc(c.lastBody || (c.count ? `${c.count} mensajes` : ''))}</span>
            <span>${esc(fmtDate(c.lastAt))}</span>
        </button>
    `).join('');
    list.querySelectorAll('.wa-convo').forEach(btn => btn.addEventListener('click', async () => {
        selectedChat = btn.dataset.chatId || '';
        lastMsgId = ''; // reset so detection works fresh for this chat
        renderConversations(items);
        // Load messages for this chat from engine
        if (selectedSession && selectedChat) {
            try {
                let engineMessages = await api('engine_chat_messages', { query: `&session_id=${encodeURIComponent(selectedSession.id)}&chat_id=${encodeURIComponent(selectedChat)}&limit=200` });
                engineMessages = Array.isArray(engineMessages) ? engineMessages : (engineMessages?.messages || []);
                if (Array.isArray(engineMessages)) {
                    latestMessages = engineMessages;
                    lastMsgId = engineMessages.length ? (engineMessages[0]?.id || engineMessages[engineMessages.length-1]?.id || '') : '';
                }
            } catch (_) {}
        }
        renderChat();
        // Scroll chat to bottom
        const body = $('chatBody');
        if (body) body.scrollTop = 0; // column-reverse so 0 = bottom
    }));
}

function renderChat() {
    const body = $('chatBody');
    const rows = selectedChat ? latestMessages.filter(m => m.chatId === selectedChat) : latestMessages;
    $('statMessages').textContent = latestMessages.length;
    $('messageInput').disabled = !selectedSession || !selectedChat;
    $('sendBtn').disabled = !selectedSession || !selectedChat;
    $('chatSubtitle').textContent = selectedChat ? labelForChat(selectedChat) : 'Selecciona una conversacion para responder.';
    if (!rows.length) {
        body.innerHTML = '<div class="wa-empty">Sin mensajes para mostrar.</div>';
        return;
    }
    body.innerHTML = rows.map(m => {
        const direction = String(m.direction || '').toLowerCase() === 'outgoing' ? 'outgoing' : 'incoming';
        const text = m.body || `[${m.type || 'mensaje'}]`;
        return `<div class="wa-bubble ${direction}">
            <div>${esc(text)}</div>
            <div class="wa-meta"><span>${esc(direction === 'outgoing' ? 'Enviado' : 'Recibido')}</span><span>${esc(fmtDate(m.createdAt || m.timestamp))}</span><span>${esc(m.status || '')}</span></div>
        </div>`;
    }).join('');
}

let lastMsgId = '';         // track last seen message id to detect new ones
let unreadChats = {};       // chatId -> unread count map
let msgPollTimer = null;    // fast timer for messages (3s)
let chatListPollTimer = null; // slow timer for chat list (30s)

async function loadChatList() {
    if (!selectedSession) return;
    const state = String(selectedSession.status || '').toLowerCase();
    if (!['ready','connected'].includes(state)) return;
    try {
        let engineChats = await api('engine_chats', { query: `&session_id=${encodeURIComponent(selectedSession.id)}&limit=1000` });
        engineChats = Array.isArray(engineChats) ? engineChats
            : (Array.isArray(engineChats?.value) ? engineChats.value
            : (Array.isArray(engineChats?.data) ? engineChats.data : []));
        if (engineChats.length) {
            convos = engineChats.map(c => ({
                chatId: c.id,
                name: c.name || c.pushName || '',
                lastBody: c.lastMessageBody || c.lastMessage || '',
                lastAt: c.lastMessageAt || c.timestamp || null,
                unread: c.unreadCount || 0,
                isGroup: c.isGroup || false,
                count: 0, incoming: 0, outgoing: 0,
            }));
            renderConversations(convos);
        }
    } catch (_) {}
}

async function loadCurrentChatMessages() {
    if (!selectedSession || !selectedChat) return;
    const state = String(selectedSession.status || '').toLowerCase();
    if (!['ready','connected'].includes(state)) return;
    try {
        let engineMessages = await api('engine_chat_messages', { query: `&session_id=${encodeURIComponent(selectedSession.id)}&chat_id=${encodeURIComponent(selectedChat)}&limit=200` });
        engineMessages = Array.isArray(engineMessages) ? engineMessages
            : (Array.isArray(engineMessages?.value) ? engineMessages.value
            : (engineMessages?.messages || []));
        if (!Array.isArray(engineMessages)) return;

        // Detect new messages by comparing counts and last ID
        const newLastId = engineMessages.length ? (engineMessages[0]?.id || engineMessages[engineMessages.length-1]?.id || '') : '';
        const hadNewMessages = newLastId && newLastId !== lastMsgId && latestMessages.length > 0;

        if (hadNewMessages) {
            // New message arrived â€” notify
            onNewMessage(selectedChat);
        }

        if (engineMessages.length > 0) {
            latestMessages = engineMessages;
            lastMsgId = newLastId;
        }

        renderChat();
    } catch (_) {}
}

function onNewMessage(chatId) {
    // Flash tab title
    let originalTitle = document.title;
    let flashCount = 0;
    const flashInterval = setInterval(() => {
        document.title = flashCount % 2 === 0 ? 'ðŸ’¬ Nuevo mensaje!' : originalTitle;
        flashCount++;
        if (flashCount > 6) {
            clearInterval(flashInterval);
            document.title = originalTitle;
        }
    }, 500);

    // Mark chat badge as unread in sidebar
    unreadChats[chatId] = (unreadChats[chatId] || 0) + 1;
    const btn = document.querySelector(`[data-chat-id="${CSS.escape(chatId)}"]`);
    if (btn) {
        btn.style.borderColor = 'rgba(34,197,94,0.7)';
        btn.style.background = 'rgba(34,197,94,0.08)';
        // Reset after 5s
        setTimeout(() => {
            btn.style.borderColor = '';
            btn.style.background = '';
        }, 5000);
    }

    // Play notification sound (subtle)
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.setValueAtTime(1100, ctx.currentTime + 0.1);
        gain.gain.setValueAtTime(0.1, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.3);
    } catch (_) {}
}

async function loadMessages(force) {
    if (!selectedSession) return;

    if (!['ready','connected'].includes(String(selectedSession.status || '').toLowerCase())) {
        await refreshSelectedSession();
    }

    setStatus(selectedSession);
    try {
        const state = String(selectedSession.status || '').toLowerCase();

        // Load chat list on initial load
        if (force || !convos.length) {
            await loadChatList();
        }

        // Load messages for current chat
        if (selectedChat) {
            await loadCurrentChatMessages();
        } else if (convos[0]) {
            selectedChat = convos[0].chatId;
            await loadCurrentChatMessages();
        }

        // Fallback: DB messages if engine gave nothing
        if (!latestMessages.length) {
            try {
                const data = await api('messages', { query: `&session_id=${encodeURIComponent(selectedSession.id)}&limit=200${selectedChat ? '&chat_id=' + encodeURIComponent(selectedChat) : ''}` });
                const dbMsgs = Array.isArray(data.messages) ? data.messages : [];
                if (dbMsgs.length) {
                    latestMessages = dbMsgs;
                    if (!convos.length) {
                        convos = Array.isArray(data.conversations) ? data.conversations : [];
                        renderConversations(convos);
                    }
                }
            } catch (_) {}
        }

        renderConversations(convos);
        renderChat();

        if (force || ['qr','qr_ready','initializing','authenticating'].includes(state)) {
            getQrMaybe();
        }
    } catch (err) {
        $('chatBody').innerHTML = `<div class="wa-empty" style="color:#fca5a5;">${esc(err.message)}</div>`;
    }
}

function startPolling() {
    stopPolling();
    if (!selectedSession) return;
    // Fast poll: refresh current chat messages every 3s
    msgPollTimer = setInterval(async () => {
        await loadCurrentChatMessages();
    }, 3000);
    // Slow poll: refresh full chat list every 30s
    chatListPollTimer = setInterval(async () => {
        await loadChatList();
    }, 30000);
}

function stopPolling() {
    if (msgPollTimer) { clearInterval(msgPollTimer); msgPollTimer = null; }
    if (chatListPollTimer) { clearInterval(chatListPollTimer); chatListPollTimer = null; }
    // legacy
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
}



async function getQrMaybe() {
    if (!selectedSession) return;
    const status = String(selectedSession.status || '').toLowerCase();
    if (['ready','connected'].includes(status)) {
        $('qrBox').style.display = 'none';
        return;
    }
    try {
        const data = await api('qr', { query: `&session_id=${encodeURIComponent(selectedSession.id)}` });
        const qr = data.qrCode || data.qr || data.dataUrl || '';
        if (qr && qr !== currentQr) {
            currentQr = qr;
            $('qrBox').style.display = '';
            $('qrContent').innerHTML = qr.startsWith('data:image') ? `<img src="${esc(qr)}" alt="QR WhatsApp">` : `<pre class="wa-muted" style="white-space:pre-wrap;">${esc(qr)}</pre>`;
        } else if (qr && qr === currentQr) {
            $('qrBox').style.display = '';
        }
    } catch (_) {
        $('qrBox').style.display = 'none';
    }
}

$('refreshBtn').addEventListener('click', loadSessions);
$('createSessionBtn').addEventListener('click', async () => {
    const name = $('newSessionName').value.trim() || 'mi-whatsapp';
    try {
        await api('create_session', { method:'POST', body:{ name } });
        await loadSessions();
    } catch (err) {
        alert(err.message);
    }
});

$('startSessionBtn').addEventListener('click', async () => {
    if (!selectedSession) return;
    $('startSessionBtn').disabled = true;
    try {
        await api('start_session', { method:'POST', body:{ session_id:selectedSession.id } });
        await loadSessions();
        if (selectedSession) {
            await selectSession(selectedSession.id);
        }
        await getQrMaybe();
    } catch (err) {
        alert(err.message);
    } finally {
        setStatus(selectedSession);
    }
});

$('stopSessionBtn').addEventListener('click', async () => {
    if (!selectedSession) return;
    $('stopSessionBtn').disabled = true;
    try {
        await api('stop_session', { method:'POST', body:{ session_id:selectedSession.id } });
        await loadSessions();
    } catch (err) {
        alert(err.message);
    } finally {
        setStatus(selectedSession);
    }
});

$('logoutSessionBtn').addEventListener('click', async () => {
    if (!selectedSession) return;
    if (!confirm('Â¿Seguro que deseas desvincular esta cuenta de WhatsApp? TendrÃ¡s que volver a escanear el QR.')) return;
    $('logoutSessionBtn').disabled = true;
    try {
        await api('logout_session', { method:'POST', body:{ session_id:selectedSession.id } });
        await loadSessions();
    } catch (err) {
        alert(err.message);
    } finally {
        setStatus(selectedSession);
    }
});

$('composeForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = $('messageInput').value.trim();
    if (!selectedSession || !selectedChat || !text) return;
    $('sendBtn').disabled = true;
    try {
        await api('send_message', { method:'POST', body:{ session_id:selectedSession.id, chat_id:selectedChat, text } });
        $('messageInput').value = '';
        await loadMessages(false);
    } catch (err) {
        alert(err.message);
    } finally {
        $('sendBtn').disabled = false;
    }
});

document.addEventListener('DOMContentLoaded', () => {
    loadSessions();
});
</script>
</body>
</html>
