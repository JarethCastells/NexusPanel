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
            'caption' => (string)($message['caption'] ?? $message['body'] ?? ''),
            'deprecatedMms3Url' => $message['deprecatedMms3Url'] ?? $message['clientUrl'] ?? null,
            'mediaUrl' => $message['mediaUrl'] ?? $message['deprecatedMms3Url'] ?? $message['clientUrl'] ?? null,
            'mimetype' => $message['mimetype'] ?? $message['media']['mimetype'] ?? null,
            'mediaData' => $message['mediaData'] ?? null,
            'filehash' => $message['filehash'] ?? null,
        ];
    }

    usort($rows, function ($a, $b) {
        $getTs = function($val) {
            if (empty($val)) return 0;
            if (is_numeric($val)) {
                $num = (int)$val;
                return $num > 20000000000 ? (int)($num / 1000) : $num;
            }
            $ts = @strtotime((string)$val);
            return $ts === false ? 0 : $ts;
        };
        return $getTs($b['createdAt'] ?? $b['timestamp'] ?? 0) <=> $getTs($a['createdAt'] ?? $a['timestamp'] ?? 0);
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

    if ($action === 'get_media') {
        $sessionId = trim((string)($_GET['session_id'] ?? ''));
        $chatId = trim((string)($_GET['chat_id'] ?? ''));
        $messageId = trim((string)($_GET['message_id'] ?? ''));
        if ($sessionId === '' || $chatId === '' || $messageId === '') {
            http_response_code(400); exit;
        }
        $res = openwaRequest('GET', '/sessions/' . rawurlencode($sessionId) . '/messages/' . rawurlencode($chatId) . '/' . rawurlencode($messageId) . '/media');
        if ($res['ok'] && isset($res['data']['data'])) {
            $base64 = $res['data']['data'];
            $mime = $res['data']['mimetype'] ?? 'application/octet-stream';
            header('Content-Type: ' . $mime);
            header('Cache-Control: public, max-age=86400');
            echo base64_decode($base64);
        } else {
            http_response_code(404);
        }
        exit;
    }

    if ($action === 'send_media' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $sessionId = trim((string)($input['session_id'] ?? ''));
        $chatId = trim((string)($input['chat_id'] ?? ''));
        $caption = trim((string)($input['caption'] ?? ''));
        $mimetype = trim((string)($input['mimetype'] ?? ''));
        $filename = trim((string)($input['filename'] ?? ''));
        $data = trim((string)($input['data'] ?? ''));
        
        if (strpos($data, ';base64,') !== false) {
            $data = substr($data, strpos($data, ';base64,') + 8);
        }

        $endpoint = trim((string)($input['endpoint'] ?? 'send-document'));

        if ($sessionId === '' || $chatId === '' || $data === '') {
            respondJson(['ok' => false, 'error' => 'Faltan datos.'], 400);
        }

        $res = openwaRequest('POST', '/sessions/' . rawurlencode($sessionId) . '/messages/' . $endpoint, [
            'chatId' => $chatId,
            'caption' => $caption,
            'mimetype' => $mimetype,
            'filename' => $filename,
            'base64' => $data,
        ]);
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
        if ($res['ok']) {
            $normalized = normalizeMessages(is_array($res['data']) ? $res['data'] : []);
            $res['data'] = $normalized['messages'];
        }
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
        .wa-compose { flex-shrink:0; padding:10px 14px; border-top:1px solid rgba(255,255,255,0.06); display:flex; align-items:center; gap:10px; background:rgba(0,0,0,0.1); }
        .wa-compose input { border-radius:999px; padding:9px 18px; border:1px solid rgba(255,255,255,0.1); background:rgba(0,0,0,0.2); color:#fff; transition:all .2s; flex:1; min-width:0; }
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

        /* Media in bubbles */
        .wa-image { max-width:280px; max-height:280px; width:auto; height:auto; object-fit:contain; border-radius:12px; margin:4px 0; display:block; cursor:pointer; transition:opacity .2s; }
        .wa-image:hover { opacity:.9; }
        .wa-caption { font-size:12.5px; color:rgba(255,255,255,0.8); margin-top:4px; word-break:break-word; }

        /* Loader */
        .wa-loader { height: 3px; width: 100%; background: rgba(0,212,255,0.1); position: relative; overflow: hidden; flex-shrink: 0; display: none; }
        .wa-loader::after { content: ''; position: absolute; top: 0; left: 0; height: 100%; width: 40%; background: #00d4ff; animation: loadingBar 1s infinite ease-in-out; border-radius: 3px; }
        @keyframes loadingBar { 0% { left: -40%; } 100% { left: 100%; } }

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
                        <input id="chatSearch" class="wa-search" placeholder="🔍 Buscar..." oninput="filterChats(this.value)">
                    </div>
                    <div id="chatListLoader" class="wa-loader"></div>
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
                <div id="chatBodyLoader" class="wa-loader"></div>
                <div class="wa-chat-body" id="chatBody"><div class="wa-empty">Aun no hay mensajes cargados.</div></div>
                
                <!-- Media Preview Area -->
                <div id="mediaPreview" style="display:none; padding:10px; background:rgba(255,255,255,0.05); border-top:1px solid rgba(255,255,255,0.1); align-items:center; gap:10px;">
                    <div id="mediaPreviewImgContainer" style="width:50px; height:50px; border-radius:8px; overflow:hidden; background:#000; display:flex; align-items:center; justify-content:center;">
                        <img id="mediaPreviewImg" src="" style="max-width:100%; max-height:100%; object-fit:cover; display:none;">
                        <i id="mediaPreviewIcon" class="fa-solid fa-file" style="color:white; display:none; font-size:24px;"></i>
                    </div>
                    <div style="flex:1; overflow:hidden;">
                        <div id="mediaPreviewName" style="color:white; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"></div>
                        <div id="mediaPreviewSize" style="color:#94a3b8; font-size:11px;"></div>
                    </div>
                    <button type="button" class="btn-danger-custom" onclick="clearMediaPreview()" style="padding:5px 10px; border-radius:50%;"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <form class="wa-compose" id="composeForm">
                    <label for="mediaInput" class="btn-secondary-custom" style="cursor:pointer; border-radius:999px; padding:9px 14px; margin-right:5px;" title="Adjuntar archivo">
                        <i class="fa-solid fa-paperclip"></i>
                    </label>
                    <input type="file" id="mediaInput" style="display:none;" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx" disabled>
                    <input class="modal-input" id="messageInput" placeholder="Escribe un mensaje..." disabled style="border-radius:999px; flex:1;">
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
let currentMediaFile = null;

const $ = id => document.getElementById(id);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const getMs = (m) => {
    const val = m.createdAt || m.timestamp;
    if (!val) return 0;
    if (typeof val === 'number') {
        return val < 2e10 ? val * 1000 : val;
    }
    const parsed = Date.parse(val);
    if (!isNaN(parsed)) return parsed;
    const num = Number(val);
    if (!isNaN(num) && num > 0) {
        return num < 2e10 ? num * 1000 : num;
    }
    return 0;
};


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
    let ts = value;
    if (typeof value === 'string') {
        const num = Number(value);
        if (!isNaN(num) && num > 0) {
            ts = num;
        }
    }
    const finalTs = typeof ts === 'number' ? (ts < 2e10 ? ts * 1000 : ts) : ts;
    const date = new Date(finalTs);
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
        const chatId = btn.dataset.chatId || '';
        if (selectedChat === chatId) return; // ignore clicking the already selected chat

        selectedChat = chatId;
        lastMsgId = ''; // reset so detection works fresh for this chat
        latestMessages = []; // clear old messages immediately
        lastRenderedMsgIds = ''; // force full re-render
        currentRenderedChat = selectedChat;
        
        renderConversations(items);
        
        // Show immediate loader while fetching
        const body = $('chatBody');
        if (body) {
            body.innerHTML = '<div style="text-align:center; padding:40px;"><i class="fa-solid fa-spinner fa-spin fa-2x" style="color:#00d4ff;"></i><div style="margin-top:12px;color:#94a3b8;font-size:13px;">Cargando mensajes...</div></div>';
        }

        // Call the centralized load function
        await loadCurrentChatMessages();
    }));
}

// Global variable to track which chat is currently rendered
let currentRenderedChat = null;
let lastRenderedMsgIds = ''; // track what we last rendered to avoid unnecessary DOM work

function buildBubble(m) {
    const direction = String(m.direction || '').toLowerCase() === 'outgoing' ? 'outgoing' : 'incoming';
    const msgType = String(m.type || 'text').toLowerCase();
    const msgId = String(m.id || m._id || '');

    let contentHtml = '';
    
    // Check if m.body looks like base64 (fallback if mediaData is missing)
    let fallbackB64 = '';
    if (m.body && m.body.length > 50 && !m.body.includes(' ') && !m.body.includes('\n')) {
        fallbackB64 = m.body.startsWith('data:') ? m.body : `data:${m.mimetype || 'application/octet-stream'};base64,${m.body}`;
    }

    // Image support
    if (msgType === 'image' || msgType === 'sticker') {
        const imgSrc = m.mediaUrl || m.deprecatedMms3Url || '';
        const mediaData = m.mediaData;
        let b64Src = '';
        if (mediaData && typeof mediaData === 'object') {
            b64Src = mediaData.preview || mediaData.base64 || '';
            if (b64Src && !b64Src.startsWith('data:')) {
                const mime = m.mimetype || 'image/jpeg';
                b64Src = `data:${mime};base64,${b64Src}`;
            }
        }
        let finalSrc = b64Src || fallbackB64 || imgSrc;
        if (!finalSrc && selectedSession) {
            finalSrc = `whatsapp.php?action=get_media&session_id=${encodeURIComponent(selectedSession.id)}&chat_id=${encodeURIComponent(selectedChat || m.chatId)}&message_id=${encodeURIComponent(msgId)}`;
        }

        if (finalSrc) {
            const alt = esc(m.caption || m.body || 'Imagen');
            contentHtml = `<img src="${esc(finalSrc)}" alt="${alt}" class="wa-image" loading="lazy" onclick="openMediaModal(this.src, 'image')" onerror="this.style.display='none';this.nextElementSibling&&(this.nextElementSibling.style.display='')" />`;
            contentHtml += `<div style="display:none;color:#94a3b8;font-size:12px;">📷 [Imagen no disponible]</div>`;
            const caption = m.caption || '';
            if (caption && msgType !== 'sticker') {
                contentHtml += `<div class="wa-caption">${esc(caption)}</div>`;
            }
        } else {
            contentHtml = `<div>📷 ${esc(m.caption || '[imagen]')}</div>`;
        }
    }
    // Video support
    else if (msgType === 'video') {
        let finalSrc = fallbackB64 || m.mediaUrl || '';
        if (!finalSrc && selectedSession) {
            finalSrc = `whatsapp.php?action=get_media&session_id=${encodeURIComponent(selectedSession.id)}&chat_id=${encodeURIComponent(selectedChat || m.chatId)}&message_id=${encodeURIComponent(msgId)}`;
        }
        if (finalSrc) {
            contentHtml = `<video src="${esc(finalSrc)}" class="wa-image" onclick="openMediaModal(this.src, 'video')" style="max-width:100%; max-height: 250px; border-radius:8px;"></video>`;
            contentHtml += `<div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); color:white; font-size:30px; pointer-events:none; text-shadow:0 2px 4px rgba(0,0,0,0.5);"><i class="fa-solid fa-play-circle"></i></div>`;
            contentHtml = `<div style="position:relative; display:inline-block;">${contentHtml}</div>`;
            const caption = m.caption || '';
            if (caption) {
                contentHtml += `<div class="wa-caption">${esc(caption)}</div>`;
            }
        } else {
            contentHtml = `<div>🎥 ${esc(m.caption || '[video]')}</div>`;
        }
    }
    // Audio/ptt support
    else if (msgType === 'audio' || msgType === 'ptt') {
        let finalSrc = fallbackB64 || m.mediaUrl || '';
        if (!finalSrc && selectedSession) {
            finalSrc = `whatsapp.php?action=get_media&session_id=${encodeURIComponent(selectedSession.id)}&chat_id=${encodeURIComponent(selectedChat || m.chatId)}&message_id=${encodeURIComponent(msgId)}`;
        }
        if (finalSrc) {
            contentHtml = `<audio src="${esc(finalSrc)}" controls style="max-width:100%; height:40px; margin:4px 0; outline:none;"></audio>`;
        } else {
            contentHtml = `<div>🎵 ${esc(m.caption || '[audio]')}</div>`;
        }
    }
    // Document support
    else if (msgType === 'document') {
        let finalSrc = fallbackB64 || m.mediaUrl || '';
        if (!finalSrc && selectedSession) {
            finalSrc = `whatsapp.php?action=get_media&session_id=${encodeURIComponent(selectedSession.id)}&chat_id=${encodeURIComponent(selectedChat || m.chatId)}&message_id=${encodeURIComponent(msgId)}`;
        }
        if (finalSrc) {
            contentHtml = `<div>📄 <a href="${esc(finalSrc)}" download="${esc(m.caption || 'documento')}" style="color:#60a5fa; text-decoration:none;" target="_blank">${esc(m.caption || 'Descargar Documento')}</a></div>`;
        } else {
            contentHtml = `<div>📄 ${esc(m.caption || '[documento]')}</div>`;
        }
    }
    // Default: text
    else {
        const text = m.body || `[${m.type || 'mensaje'}]`;
        contentHtml = `<div>${esc(text)}</div>`;
    }

    const div = document.createElement('div');
    div.className = `wa-bubble ${direction}`;
    div.dataset.msgId = msgId;
    div.innerHTML = `
        ${contentHtml}
        <div class="wa-meta"><span>${esc(direction === 'outgoing' ? 'Enviado' : 'Recibido')}</span><span>${esc(fmtDate(m.createdAt || m.timestamp))}</span><span>${esc(m.status || '')}</span></div>
    `;
    return div;
}

async function renderChat() {
    const body = $('chatBody');
    let rows = selectedChat ? latestMessages.filter(m => m.chatId === selectedChat) : latestMessages;
    rows.sort((a, b) => getMs(b) - getMs(a));

    // Update UI counters and input states
    $('statMessages').textContent = latestMessages.length;
    $('messageInput').disabled = !selectedSession || !selectedChat;
    $('mediaInput').disabled = !selectedSession || !selectedChat;
    $('sendBtn').disabled = !selectedSession || !selectedChat;
    $('chatSubtitle').textContent = selectedChat ? labelForChat(selectedChat) : 'Selecciona una conversacion para responder.';

    // If chat changed, force full re-render
    if (currentRenderedChat !== selectedChat) {
        lastRenderedMsgIds = '';
        currentRenderedChat = selectedChat;
    }

    // If no messages, show placeholder
    if (!rows.length) {
        if (lastRenderedMsgIds !== '__empty__') {
            body.innerHTML = '<div class="wa-empty">Sin mensajes para mostrar.</div>';
            lastRenderedMsgIds = '__empty__';
        }
        return;
    }

    // Check if anything actually changed
    const newMsgIds = rows.map(m => String(m.id || m._id || '')).join('|');
    if (newMsgIds === lastRenderedMsgIds) return; // nothing changed, skip DOM work

    // Build all bubbles in a DocumentFragment (off-screen, no flicker)
    const frag = document.createDocumentFragment();
    for (const m of rows) {
        frag.appendChild(buildBubble(m));
    }

    // Swap content in one paint frame
    body.innerHTML = '';
    body.appendChild(frag);
    lastRenderedMsgIds = newMsgIds;

    // Scroll to bottom (in column-reverse, scrollTop 0 = newest messages visible)
    body.scrollTop = 0;
}

let lastMsgId = '';         // track last seen message id to detect new ones
let unreadChats = {};       // chatId -> unread count map
let msgPollTimer = null;    // fast timer for messages (3s)
let chatListPollTimer = null; // slow timer for chat list (30s)

function showChatListLoader(show) { const el = $('chatListLoader'); if (el) el.style.display = show ? 'block' : 'none'; }
function showChatBodyLoader(show) { const el = $('chatBodyLoader'); if (el) el.style.display = show ? 'block' : 'none'; }

async function loadChatList() {
    if (!selectedSession) return;
    const state = String(selectedSession.status || '').toLowerCase();
    if (!['ready','connected'].includes(state)) return;
    if (!convos.length) showChatListLoader(true);
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
    finally { showChatListLoader(false); }
}

async function loadCurrentChatMessages() {
    if (!selectedSession || !selectedChat) return;
    const state = String(selectedSession.status || '').toLowerCase();
    if (!['ready','connected'].includes(state)) return;
    if (currentRenderedChat !== selectedChat || latestMessages.length === 0) showChatBodyLoader(true);
    try {
        let engineMessages = await api('engine_chat_messages', { query: `&session_id=${encodeURIComponent(selectedSession.id)}&chat_id=${encodeURIComponent(selectedChat)}&limit=200` });
        engineMessages = Array.isArray(engineMessages) ? engineMessages
            : (Array.isArray(engineMessages?.value) ? engineMessages.value
            : (engineMessages?.messages || []));
        if (!Array.isArray(engineMessages)) return;

        // Force chatId on every message — we already know which chat these belong to
        engineMessages = engineMessages.map(m => ({ ...m, chatId: m.chatId || selectedChat }));

        // Sort descending (newest first)
        engineMessages.sort((a, b) => getMs(b) - getMs(a));

        // Detect new messages by comparing counts and last ID
        const newLastId = engineMessages.length ? (engineMessages[0]?.id || engineMessages[engineMessages.length-1]?.id || '') : '';
        const newestMsg = engineMessages[0];
        const isIncoming = newestMsg && String(newestMsg.direction || '').toLowerCase() === 'incoming';
        const hadNewMessages = newLastId && newLastId !== lastMsgId && latestMessages.length > 0 && isIncoming;

        if (hadNewMessages) {
            onNewMessage(selectedChat);
        }

        if (engineMessages.length > 0) {
            latestMessages = engineMessages;
            lastMsgId = newLastId;
            // Update matching conversation in convos so the sidebar updates in real-time
            const newestMsg = engineMessages[0];
            const convo = convos.find(c => c.chatId === selectedChat);
            if (convo) {
                convo.lastBody = newestMsg.body || `[${newestMsg.type || 'mensaje'}]`;
                convo.lastAt = newestMsg.createdAt || newestMsg.timestamp;
                renderConversations(convos);
            }
        }

        await renderChat();
    } catch (err) {
        console.error('[WA] loadCurrentChatMessages error:', err);
        if (latestMessages.length === 0) {
            const body = $('chatBody');
            if (body) {
                body.innerHTML = `<div class="wa-empty" style="color:#ef4444;">Error al cargar mensajes: ${esc(err.message)}</div>`;
                lastRenderedMsgIds = '__error__';
            }
        }
    } finally {
        showChatBodyLoader(false);
    }
}

function onNewMessage(chatId) {
    // Flash tab title
    let originalTitle = document.title;
    let flashCount = 0;
    const flashInterval = setInterval(() => {
        document.title = flashCount % 2 === 0 ? '💬 Nuevo mensaje!' : originalTitle;
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
                    dbMsgs.sort((a, b) => getMs(b) - getMs(a));
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
    const stStatus = String(selectedSession.status || '').toLowerCase();
    const canChat = selectedChat && ['ready','connected'].includes(stStatus);
    
    $('messageInput').disabled = !canChat;
    $('mediaInput').disabled = !canChat;
    $('sendBtn').disabled = !canChat;
    
    if (canChat && !msgPollTimer) {
        // ... (existing logic)
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
    const btn = $('createSessionBtn');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creando...';
    btn.disabled = true;
    try {
        await api('create_session', { method:'POST', body:{ name } });
        await loadSessions();
    } catch (err) {
        alert(err.message);
    } finally {
        btn.innerHTML = orig;
        btn.disabled = false;
    }
});

$('startSessionBtn').addEventListener('click', async () => {
    if (!selectedSession) return;
    const btn = $('startSessionBtn');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Iniciando...';
    btn.disabled = true;
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
        btn.innerHTML = orig;
        setStatus(selectedSession);
    }
});

$('stopSessionBtn').addEventListener('click', async () => {
    if (!selectedSession) return;
    const btn = $('stopSessionBtn');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Cerrando...';
    btn.disabled = true;
    try {
        await api('stop_session', { method:'POST', body:{ session_id:selectedSession.id } });
        await loadSessions();
    } catch (err) {
        alert(err.message);
    } finally {
        btn.innerHTML = orig;
        setStatus(selectedSession);
    }
});

$('logoutSessionBtn').addEventListener('click', async () => {
    if (!selectedSession) return;
    if (!confirm('¿Seguro que deseas desvincular esta cuenta de WhatsApp? Tendrás que volver a escanear el QR.')) return;
    const btn = $('logoutSessionBtn');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Desvinculando...';
    btn.disabled = true;
    try {
        await api('logout_session', { method:'POST', body:{ session_id:selectedSession.id } });
        await loadSessions();
    } catch (err) {
        alert(err.message);
    } finally {
        btn.innerHTML = orig;
        setStatus(selectedSession);
    }
});

function clearMediaPreview() {
    currentMediaFile = null;
    $('mediaInput').value = '';
    $('mediaPreview').style.display = 'none';
    $('mediaPreviewImg').src = '';
}

function handleFileSelection(file) {
    if (!file) {
        clearMediaPreview();
        return;
    }
    currentMediaFile = file;
    const preview = $('mediaPreview');
    const img = $('mediaPreviewImg');
    const icon = $('mediaPreviewIcon');
    const name = $('mediaPreviewName');
    const size = $('mediaPreviewSize');
    
    name.textContent = file.name || 'Archivo pegado';
    size.textContent = (file.size / 1024).toFixed(1) + ' KB';
    
    if (file.type.startsWith('image/')) {
        const url = URL.createObjectURL(file);
        img.src = url;
        img.style.display = 'block';
        icon.style.display = 'none';
        // URL.revokeObjectURL(url) can be called later to save memory
    } else {
        img.style.display = 'none';
        icon.style.display = 'block';
        if (file.type.startsWith('video/')) icon.className = 'fa-solid fa-video';
        else if (file.type.startsWith('audio/')) icon.className = 'fa-solid fa-music';
        else icon.className = 'fa-solid fa-file';
    }
    preview.style.display = 'flex';
}

$('mediaInput').addEventListener('change', (e) => {
    handleFileSelection(e.target.files[0]);
    $('messageInput').focus();
});

$('messageInput').addEventListener('paste', (e) => {
    const items = (e.clipboardData || e.originalEvent.clipboardData).items;
    for (let index in items) {
        const item = items[index];
        if (item.kind === 'file') {
            const blob = item.getAsFile();
            if (blob) {
                // If it's pasted, it might not have a good name
                const ext = blob.type.split('/')[1] || 'bin';
                const f = new File([blob], `Pasted_${Date.now()}.${ext}`, { type: blob.type });
                handleFileSelection(f);
                e.preventDefault(); // Stop default pasting if it's an image
                return;
            }
        }
    }
});

$('composeForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = $('messageInput').value.trim();
    const file = currentMediaFile;
    
    if (!selectedSession || !selectedChat || (!text && !file)) return;
    $('sendBtn').disabled = true;
    try {
        if (file) {
            const reader = new FileReader();
            reader.onload = async (event) => {
                const base64Data = event.target.result;
                const mimetype = file.type || 'application/octet-stream';
                const filename = file.name || 'archivo';
                
                let endpoint = 'send-document';
                if (mimetype.startsWith('image/')) endpoint = 'send-image';
                else if (mimetype.startsWith('video/')) endpoint = 'send-video';
                else if (mimetype.startsWith('audio/')) endpoint = 'send-audio';

                await api('send_media', { 
                    method: 'POST', 
                    body: { 
                        session_id: selectedSession.id, 
                        chat_id: selectedChat, 
                        caption: text,
                        mimetype: mimetype,
                        filename: filename,
                        data: base64Data,
                        endpoint: endpoint
                    } 
                });
                
                $('messageInput').value = '';
                clearMediaPreview();
                await loadMessages(false);
                $('sendBtn').disabled = false;
            };
            reader.readAsDataURL(file);
        } else {
            await api('send_message', { method:'POST', body:{ session_id:selectedSession.id, chat_id:selectedChat, text } });
            $('messageInput').value = '';
            await loadMessages(false);
            $('sendBtn').disabled = false;
        }
    } catch (err) {
        alert(err.message);
        $('sendBtn').disabled = false;
    }
});

document.addEventListener('DOMContentLoaded', async () => {
    await loadSessions();
    startPolling();
});
</script>

<!-- Media Modal -->
<div id="waMediaModal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:9999; display:none; flex-direction:column; align-items:center; justify-content:center; opacity:0; transition:opacity 0.2s; backdrop-filter:blur(5px);">
    <div style="position:absolute; top:20px; right:30px; display:flex; gap:20px; z-index:10000;">
        <a id="waMediaDownload" href="#" download="media" style="color:white; font-size:26px; text-decoration:none; cursor:pointer; transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'" title="Descargar"><i class="fa-solid fa-download"></i></a>
        <span id="waMediaClose" style="color:white; font-size:26px; cursor:pointer; transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'" title="Cerrar"><i class="fa-solid fa-xmark"></i></span>
    </div>
    <div id="waMediaContent" style="max-width:90%; max-height:85vh; display:flex; justify-content:center; align-items:center;"></div>
</div>

<script>
function openMediaModal(src, type) {
    const modal = document.getElementById('waMediaModal');
    const content = document.getElementById('waMediaContent');
    const download = document.getElementById('waMediaDownload');
    
    download.href = src;
    
    if (type === 'image') {
        content.innerHTML = `<img src="${src}" style="max-width:100%; max-height:85vh; object-fit:contain; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.5);" />`;
    } else if (type === 'video') {
        content.innerHTML = `<video src="${src}" controls autoplay style="max-width:100%; max-height:85vh; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.5);"></video>`;
    }
    
    modal.style.display = 'flex';
    void modal.offsetWidth; // force reflow
    modal.style.opacity = '1';
    
    document.getElementById('waMediaClose').onclick = () => {
        modal.style.opacity = '0';
        setTimeout(() => { modal.style.display = 'none'; content.innerHTML = ''; }, 200);
    };
    modal.onclick = (e) => {
        if (e.target === modal || e.target === content) document.getElementById('waMediaClose').click();
    };
}
</script>
</body>
</html>
