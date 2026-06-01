<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (!estaLogueado()) {
    header('Location: index.php');
    exit;
}

// Unlock session file to allow concurrent AJAX requests (prevents background sync from blocking the UI)
session_write_close();

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

function openwaIsLocalUrl(): bool {
    $host = parse_url(openwaBaseUrl(), PHP_URL_HOST);
    return in_array(strtolower((string)$host), ['localhost', '127.0.0.1', '::1'], true);
}

function openwaIsRunning(): bool {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true,
            'timeout' => 2,
        ],
    ]);

    $raw = @file_get_contents(openwaBaseUrl() . '/api/health', false, $context);
    return $raw !== false;
}

function startOpenwaFromProject(bool $waitForReady = true): bool {
    // Deshabilitado para evitar conflictos (EADDRINUSE) con la consola del usuario.
    return false;

    $projectDir = __DIR__ . DIRECTORY_SEPARATOR . 'OpenWA';
    $distMain = $projectDir . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'main.js';
    $packageJson = $projectDir . DIRECTORY_SEPARATOR . 'package.json';
    if (!is_dir($projectDir) || !is_file($distMain) || !is_file($packageJson)) {
        return false;
    }

    $logsDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0775, true);
    }
    $outLog = $logsDir . DIRECTORY_SEPARATOR . 'openwa.out.log';
    $errLog = $logsDir . DIRECTORY_SEPARATOR . 'openwa.err.log';
    $port = parse_url(openwaBaseUrl(), PHP_URL_PORT) ?: 2785;

    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        $launcher = $logsDir . DIRECTORY_SEPARATOR . 'start_openwa.cmd';
        $bat = "@echo off\r\n"
            . "cd /D \"" . $projectDir . "\"\r\n"
            . "set PORT=" . $port . "\r\n"
            . "npm run start:prod >> \"" . $outLog . "\" 2>> \"" . $errLog . "\"\r\n";
        @file_put_contents($launcher, $bat);
        @pclose(@popen('start "" /B "' . $launcher . '"', 'r'));
    } else {
        $cmd = 'cd ' . escapeshellarg($projectDir) . ' && PORT=' . escapeshellarg((string)$port) . ' npm run start:prod >> ' . escapeshellarg($outLog) . ' 2>> ' . escapeshellarg($errLog) . ' &';
        @exec($cmd);
    }

    if (!$waitForReady) {
        return true;
    }

    for ($i = 0; $i < 10; $i++) {
        usleep(700000);
        if (openwaIsRunning()) {
            return true;
        }
    }

    return openwaIsRunning();
}

function openwaRequest(string $method, string $path, ?array $payload = null, array $query = []): array {
    startOpenwaFromProject(false);

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
            'timeout' => 60,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false && startOpenwaFromProject(true)) {
        $raw = @file_get_contents($url, false, $context);
    }

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
        return ['ok' => false, 'status' => 0, 'error' => 'OpenWA todavia no responde en ' . openwaBaseUrl()];
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

function textEndsWith(string $value, string $suffix): bool {
    return $suffix === '' || substr($value, -strlen($suffix)) === $suffix;
}

function isPrimaryWhatsAppChat(string $chatId): bool {
    $chatId = strtolower(trim($chatId));
    if ($chatId === '') {
        return false;
    }

    if ($chatId === 'status@broadcast' || textEndsWith($chatId, '@broadcast')) {
        return false;
    }

    return textEndsWith($chatId, '@c.us') || textEndsWith($chatId, '@g.us') || textEndsWith($chatId, '@lid');
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
            'id' => (string)(is_array($message['id'] ?? null) ? ($message['id']['_serialized'] ?? '') : ($message['waMessageId'] ?? $message['id'] ?? '')),
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
        return $getTs($b['timestamp'] ?? $b['createdAt'] ?? 0) <=> $getTs($a['timestamp'] ?? $a['createdAt'] ?? 0);
    });

    return ['messages' => $rows, 'total' => (int)($payload['total'] ?? count($rows))];
}

function conversationSummary(array $messages): array {
    $byChat = [];
    foreach ($messages as $message) {
        $chatId = $message['chatId'] ?? '';
        if ($chatId === '' || !isPrimaryWhatsAppChat((string)$chatId)) {
            continue;
        }
        if (!isset($byChat[$chatId])) {
            $byChat[$chatId] = [
                'chatId' => $chatId,
                'lastBody' => $message['body'] ?: '[' . ($message['type'] ?: 'mensaje') . ']',
                'lastAt' => $message['timestamp'] ?? $message['createdAt'] ?? null,
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

    if ($action === 'get_profile_pic') {
        $sessionId = trim((string)($_GET['session_id'] ?? ''));
        $chatId = trim((string)($_GET['chat_id'] ?? ''));
        if ($sessionId === '' || $chatId === '') {
            respondJson(['ok' => false, 'error' => 'Falta session_id o chat_id'], 400);
        }
        $res = openwaRequest('GET', '/sessions/' . rawurlencode($sessionId) . '/contacts/' . rawurlencode($chatId) . '/profile-picture');
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
        .wa-chat-head { flex-shrink:0; padding:10px 14px; border-bottom:1px solid rgba(255,255,255,0.06); display:flex; align-items:center; justify-content:space-between; gap:8px; background:linear-gradient(135deg, rgba(0,0,0,0.2) 0%, rgba(0,20,40,0.15) 100%); }

        /* Header icon-only action buttons */
        .wa-head-btn { position:relative; display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.04); color:#94a3b8; font-size:13px; cursor:pointer; transition:all .18s cubic-bezier(.4,0,.2,1); }
        .wa-head-btn:hover { background:rgba(0,212,255,0.12); border-color:rgba(0,212,255,0.35); color:#67e8f9; transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,212,255,0.15); }
        .wa-head-btn:active { transform:translateY(0); }
        .wa-head-btn.active { background:rgba(0,212,255,0.18); border-color:rgba(0,212,255,0.5); color:#00d4ff; box-shadow:0 0 0 3px rgba(0,212,255,0.12); }
        .wa-head-btn:disabled { opacity:0.35; cursor:not-allowed; transform:none; box-shadow:none; }
        /* Session control pill buttons */
        .wa-sess-btn { display:inline-flex; align-items:center; gap:5px; padding:5px 11px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:.3px; border:1px solid; cursor:pointer; transition:all .18s cubic-bezier(.4,0,.2,1); white-space:nowrap; }
        .wa-sess-btn:disabled { opacity:.35; cursor:not-allowed; transform:none !important; box-shadow:none !important; }
        .wa-sess-btn-start { background:rgba(16,185,129,0.1); border-color:rgba(16,185,129,0.3); color:#86efac; }
        .wa-sess-btn-start:not(:disabled):hover { background:rgba(16,185,129,0.22); border-color:#10b981; color:#4ade80; transform:translateY(-1px); box-shadow:0 4px 12px rgba(16,185,129,0.2); }
        .wa-sess-btn-stop { background:rgba(245,158,11,0.1); border-color:rgba(245,158,11,0.3); color:#fcd34d; }
        .wa-sess-btn-stop:not(:disabled):hover { background:rgba(245,158,11,0.22); border-color:#f59e0b; color:#fbbf24; transform:translateY(-1px); box-shadow:0 4px 12px rgba(245,158,11,0.2); }
        .wa-sess-btn-logout { background:rgba(239,68,68,0.1); border-color:rgba(239,68,68,0.3); color:#fca5a5; }
        .wa-sess-btn-logout:not(:disabled):hover { background:rgba(239,68,68,0.22); border-color:#ef4444; color:#f87171; transform:translateY(-1px); box-shadow:0 4px 12px rgba(239,68,68,0.2); }
        /* Divider between search btn and session controls */
        .wa-head-divider { width:1px; height:22px; background:rgba(255,255,255,0.1); flex-shrink:0; }

        /* Message search bar */
        .wa-search-bar { flex-shrink:0; display:flex; align-items:center; gap:10px; padding:0 16px; max-height:0; overflow:hidden; transition:max-height .3s cubic-bezier(.4,0,.2,1), padding .3s cubic-bezier(.4,0,.2,1); border-bottom:1px solid transparent; background:linear-gradient(135deg,rgba(0,212,255,0.04) 0%,rgba(0,20,40,0.08) 100%); }
        .wa-search-bar.open { max-height:58px; padding:9px 16px; border-bottom-color:rgba(0,212,255,0.12); }
        .wa-search-bar input { flex:1; background:rgba(0,0,0,0.3); border:1.5px solid rgba(0,212,255,0.18); border-radius:999px; padding:8px 18px; color:#f8fafc; font-size:13px; outline:none; transition:border-color .2s, box-shadow .2s, background .2s; }
        .wa-search-bar input:focus { background:rgba(0,0,0,0.4); border-color:rgba(0,212,255,0.6); box-shadow:0 0 0 3px rgba(0,212,255,0.1); }
        .wa-search-bar input::placeholder { color:rgba(255,255,255,0.25); font-style:italic; }
        .wa-search-nav { display:flex; align-items:center; gap:4px; flex-shrink:0; }
        .wa-search-count { font-size:11px; color:#64748b; min-width:48px; text-align:center; padding:0 4px; font-variant-numeric:tabular-nums; }
        .wa-search-nav button { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); color:#64748b; border-radius:7px; width:27px; height:27px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .15s; font-size:11px; }
        .wa-search-nav button:hover { background:rgba(0,212,255,0.12); border-color:rgba(0,212,255,0.35); color:#67e8f9; }
        .wa-search-close { background:none; border:none; color:#475569; cursor:pointer; padding:5px 7px; font-size:15px; border-radius:50%; transition:all .15s; display:flex; align-items:center; }
        .wa-search-close:hover { background:rgba(239,68,68,0.12); color:#f87171; }
        mark.wa-highlight { background:rgba(250,204,21,0.28); color:#fef08a; border-radius:3px; padding:0 2px; transition:background .15s; }
        mark.wa-highlight.wa-current { background:rgba(250,204,21,0.75); color:#1c1917; font-weight:700; box-shadow:0 0 0 2px rgba(250,204,21,0.4); }

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
        .wa-convo { width:100%; text-align:left; border:1px solid rgba(255,255,255,0.04); border-radius:12px; padding:9px 10px; background:rgba(255,255,255,0.02); color:#e5edf8; margin-bottom:6px; cursor:pointer; transition:all .2s; display:block; }
        .wa-convo-top { display:flex; align-items:center; justify-content:space-between; gap:8px; min-width:0; }
        .wa-convo-name { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12.5px; font-weight:800; color:#f8fafc; }
        .wa-convo-time { flex-shrink:0; color:rgba(148,163,184,.82); font-size:10px; }
        .wa-convo-preview { display:block; color:var(--text-muted); font-size:10.8px; margin-top:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .wa-convo-meta { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:5px; }
        .wa-convo-count { flex-shrink:0; border-radius:999px; padding:2px 7px; background:rgba(15,23,42,.66); border:1px solid rgba(148,163,184,.16); color:#bae6fd; font-size:10px; font-weight:800; }
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
        <a href="pages/reportes_ventas.php" class="nav-item"><i class="fa-solid fa-file-excel"></i><span>Reportes</span></a>
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
            <div class="wa-stat" style="display:none !important;"><strong id="statMessages">0</strong><span>Mensajes cargados</span></div>
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
                    <div id="chatListLoader" style="height:3px; width:100%; background:rgba(0,212,255,0.1); position:relative; overflow:hidden; flex-shrink:0; display:none;">
                        <div id="chatListProgressBar" style="height:100%; width:0%; background:#00d4ff; border-radius:3px;"></div>
                    </div>
                    <div id="chatListLoader" class="wa-loader"></div>
                    <div class="wa-convos-list" id="conversationList"><div class="wa-empty">Selecciona una sesiÃ³n.</div></div>
                </div>
            </section>

            <section class="wa-chat">
                <div class="wa-chat-head">
                    <!-- Contact info -->
                    <div style="min-width:0; flex:1;">
                        <h3 class="wa-title" id="chatTitle" style="font-size:14px;">Mensajes de WhatsApp</h3>
                        <p class="wa-muted" id="chatSubtitle">Selecciona una sesion y una conversacion.</p>
                    </div>

                    <!-- Action buttons -->
                    <div style="display:flex; align-items:center; gap:6px; flex-shrink:0;">

                        <!-- Search toggle -->
                        <button class="wa-head-btn" type="button" id="searchToggleBtn" title="Buscar en la conversación">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>

                        <div class="wa-head-divider"></div>

                        <!-- Session controls -->
                        <button class="wa-sess-btn wa-sess-btn-start" type="button" id="startSessionBtn" disabled title="Iniciar sesión">
                            <i class="fa-solid fa-play" style="font-size:10px;"></i> Iniciar
                        </button>
                        <button class="wa-sess-btn wa-sess-btn-stop" type="button" id="stopSessionBtn" disabled title="Cerrar sesión">
                            <i class="fa-solid fa-power-off" style="font-size:10px;"></i> Cerrar
                        </button>
                        <button class="wa-sess-btn wa-sess-btn-logout" type="button" id="logoutSessionBtn" disabled title="Desvincular dispositivo">
                            <i class="fa-solid fa-link-slash" style="font-size:10px;"></i> Desvincular
                        </button>

                        <div class="wa-head-divider"></div>

                        <!-- Status badge -->
                        <span class="wa-status" id="sessionStatus">sin sesion</span>
                    </div>
                </div>

                <!-- Message Search Bar -->
                <div class="wa-search-bar" id="msgSearchBar">
                    <i class="fa-solid fa-magnifying-glass" style="color:rgba(0,212,255,0.6); font-size:13px; flex-shrink:0;"></i>
                    <input type="text" id="msgSearchInput" placeholder="Buscar mensajes en esta conversación..." autocomplete="off">
                    <div class="wa-search-nav">
                        <span class="wa-search-count" id="msgSearchCount"></span>
                        <button type="button" id="msgSearchPrev" title="Resultado anterior (Shift+Enter)"><i class="fa-solid fa-chevron-up"></i></button>
                        <button type="button" id="msgSearchNext" title="Siguiente resultado (Enter)"><i class="fa-solid fa-chevron-down"></i></button>
                    </div>
                    <button class="wa-search-close" type="button" id="msgSearchClose" title="Cerrar búsqueda (Esc)"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <div id="chatBodyLoader" style="height:3px; width:100%; background:rgba(0,212,255,0.1); position:relative; overflow:hidden; flex-shrink:0; display:none;">
                    <div id="chatBodyProgressBar" style="height:100%; width:0%; background:#00d4ff; border-radius:3px;"></div>
                </div>
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
                    
                    <div id="recordingUI" style="display:none; flex:1; align-items:center; justify-content:space-between; background:rgba(239,68,68,0.1); border-radius:999px; padding:0 15px; border:1px solid rgba(239,68,68,0.3);">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:10px; height:10px; border-radius:50%; background:#ef4444; animation: pulse-red 1.5s infinite;"></div>
                            <span id="recordingTimer" style="color:#ef4444; font-weight:600; font-family:monospace; font-size:15px;">0:00</span>
                        </div>
                        <button type="button" id="cancelRecordBtn" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:16px; padding:5px;" title="Cancelar grabación"><i class="fa-solid fa-trash-can"></i></button>
                    </div>

                    <button class="btn-primary-custom" type="submit" id="sendBtn" disabled style="display:none;"><i class="fa-solid fa-paper-plane"></i></button>
                    <button class="btn-primary-custom" type="button" id="recordBtn" disabled style="background:#10b981; border-color:#059669; padding:9px 15px;"><i class="fa-solid fa-microphone"></i></button>
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
    if (m === null || m === undefined) return 0;
    const val = typeof m === 'object' ? (m.timestamp || m.createdAt) : m;
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
    const controller = options.timeoutMs ? new AbortController() : null;
    const timeout = controller ? setTimeout(() => controller.abort(), options.timeoutMs) : null;
    try {
        const res = await fetch(`whatsapp.php?action=${action}${options.query || ''}`, {
            method: options.method || 'GET',
            headers: { 'Content-Type': 'application/json' },
            body: options.body ? JSON.stringify(options.body) : undefined,
            cache: 'no-store',
            signal: controller ? controller.signal : undefined
        });
        const payload = await res.json().catch(() => ({ ok:false, error:'Respuesta invalida.' }));
        if (!res.ok || payload.ok === false) throw new Error(payload.error || 'No se completo la accion.');
        return payload.data ?? payload;
    } finally {
        if (timeout) clearTimeout(timeout);
    }
}

async function refreshSelectedSession() {
    if (!selectedSession) return;
    try {
        const data = await api('sessions');
        sessions = Array.isArray(data) ? data : [];
        const updated = sessions.find(s => s.id === selectedSession.id);
        if (updated) {
            const prevStatus = String(selectedSession.status || '').toLowerCase();
            const newStatus  = String(updated.status || '').toLowerCase();
            const wasReady   = ['ready','connected'].includes(prevStatus);
            const isNowReady = ['ready','connected'].includes(newStatus);
            
            selectedSession = updated;
            renderSessions();
            setStatus(selectedSession);
            
            if (!wasReady && isNowReady) {
                // Just connected → load everything and ensure polling is running
                loadAvatars();
                await loadChatList();
                if (!msgPollTimer) startPolling();
            }
        }
    } catch (_) {
        // Ignore refresh failures; keep using existing session state.
    }
}

function normalizeChatId(raw) {
    if (!raw) return '';
    if (typeof raw === 'object') {
        if (raw._serialized) return String(raw._serialized);
        if (raw.user && raw.server) return `${raw.user}@${raw.server}`;
    }
    return String(raw).trim();
}

function isPrimaryChatId(chatId) {
    const id = normalizeChatId(chatId).toLowerCase();
    if (!id || id === 'status@broadcast' || id.endsWith('@broadcast')) return false;
    return id.endsWith('@c.us') || id.endsWith('@g.us') || id.endsWith('@lid');
}

function firstValue(...values) {
    for (const value of values) {
        if (value !== undefined && value !== null && value !== '') return value;
    }
    return '';
}

function fallbackChatName(chatId) {
    const id = normalizeChatId(chatId);
    if (id.endsWith('@g.us')) return id.replace('@g.us', ' (grupo)');
    if (id.endsWith('@c.us')) return '+' + id.replace('@c.us', '');
    if (id.endsWith('@lid')) return id.replace('@lid', '');
    return id || 'Chat';
}

function normalizeConversation(raw) {
    if (!raw || typeof raw !== 'object') return null;
    const last = raw.lastMessage && typeof raw.lastMessage === 'object' ? raw.lastMessage : {};
    const chatId = normalizeChatId(firstValue(
        raw.chatId,
        raw.id,
        raw.remoteJid,
        raw.remote,
        raw.jid,
        last.chatId,
        last.fromMe ? last.to : last.from
    ));
    if (!isPrimaryChatId(chatId)) return null;

    const rawLastMessage = typeof raw.lastMessage === 'string' ? raw.lastMessage : '';
    const lastBody = firstValue(
        raw.lastBody,
        raw.lastMessageBody,
        raw.lastMessageText,
        rawLastMessage,
        last.body,
        last.text,
        last.caption,
        last.content,
        raw.body,
        raw.preview
    );
    const type = firstValue(raw.type, last.type, 'mensaje');
    const lastAt = firstValue(
        raw.lastAt,
        raw.lastMessageAt,
        raw.timestamp,
        raw.t,
        raw.updatedAt,
        raw.createdAt,
        last.timestamp,
        last.createdAt
    );
    const unread = Number(firstValue(raw.unread, raw.unreadCount, raw.unreadMessages, 0)) || 0;
    const count = Number(firstValue(raw.count, raw.messageCount, raw.total, 0)) || 0;

    return {
        chatId,
        name: String(firstValue(raw.name, raw.pushName, raw.formattedTitle, raw.contactName, raw.displayName, fallbackChatName(chatId))),
        lastBody: String(lastBody || (type ? `[${type}]` : '')),
        lastAt,
        unread,
        count,
        incoming: Number(raw.incoming || 0) || 0,
        outgoing: Number(raw.outgoing || 0) || 0,
        isGroup: Boolean(raw.isGroup || chatId.endsWith('@g.us')),
    };
}

function normalizeConversationList(items) {
    const byChat = new Map();
    (Array.isArray(items) ? items : []).forEach(item => {
        const convo = normalizeConversation(item);
        if (!convo) return;
        const existing = byChat.get(convo.chatId);
        if (!existing) {
            byChat.set(convo.chatId, convo);
        } else {
            const latest = getMs(convo.lastAt) >= getMs(existing.lastAt) ? convo : existing;
            byChat.set(convo.chatId, {
                ...existing,
                ...latest,
                count: (Number(existing.count) || 0) + (Number(convo.count) || 0),
                incoming: (Number(existing.incoming) || 0) + (Number(convo.incoming) || 0),
                outgoing: (Number(existing.outgoing) || 0) + (Number(convo.outgoing) || 0),
                unread: Math.max(Number(existing.unread) || 0, Number(convo.unread) || 0),
            });
        }
    });
    return Array.from(byChat.values()).sort((a, b) => {
        const diff = getMs(b.lastAt) - getMs(a.lastAt);
        return diff || String(a.name || '').localeCompare(String(b.name || ''), 'es');
    });
}

function labelForChat(chatId) {
    const normalized = normalizeChatId(chatId);
    const convo = Array.isArray(convos) ? convos.find(c => c.chatId === normalized) : null;
    if (convo && convo.name) return convo.name;
    return fallbackChatName(normalized);
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
            <div class="wa-empty" style="color:#bae6fd;line-height:1.55;">
                <strong style="color:#f8fafc;">Servicio OpenWA pendiente</strong><br>
                Inicia el backend en ${esc(baseUrl)} para habilitar sesiones y QR.
            </div>`;
        $('conversationList').innerHTML = '<div class="wa-empty">Cuando OpenWA responda, aqui se cargaran tus chats.</div>';
        $('chatTitle').textContent = 'Conecta OpenWA para continuar';
        $('chatSubtitle').textContent = 'El panel ya esta integrado; solo falta que el servicio local este activo.';
        $('chatBody').innerHTML = `
            <div class="wa-empty" style="max-width:640px;margin:0 auto;line-height:1.65;color:#cbd5e1;text-align:left;">
                <div style="display:flex;gap:14px;align-items:flex-start;padding:18px 20px;border:1px solid rgba(14,165,233,.28);border-radius:18px;background:linear-gradient(135deg,rgba(14,165,233,.12),rgba(15,23,42,.62));box-shadow:0 18px 45px rgba(0,0,0,.22);">
                    <div style="width:42px;height:42px;border-radius:14px;display:grid;place-items:center;background:rgba(14,165,233,.16);color:#38bdf8;border:1px solid rgba(56,189,248,.28);">
                        <i class="fa-brands fa-whatsapp"></i>
                    </div>
                    <div>
                        <strong style="display:block;color:#f8fafc;font-size:16px;margin-bottom:6px;">Listo para vincular WhatsApp</strong>
                        <span>El dashboard ya esta conectado al modulo. Para empezar, levanta OpenWA y despues refresca esta vista.</span>
                        <div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;">
                            <span style="padding:7px 10px;border-radius:999px;background:rgba(15,23,42,.72);border:1px solid rgba(148,163,184,.18);color:#93c5fd;">URL esperada: ${esc(baseUrl)}</span>
                            <span style="padding:7px 10px;border-radius:999px;background:rgba(15,23,42,.72);border:1px solid rgba(148,163,184,.18);color:#86efac;">Al conectar: sesiones, QR, chats y mensajes</span>
                        </div>
                    </div>
                </div>
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
    convos = normalizeConversationList(items);
    if (selectedChat && !convos.some(c => c.chatId === selectedChat)) {
        selectedChat = '';
        latestMessages = [];
        lastMsgId = '';
        lastRenderedMsgIds = '';
        currentRenderedChat = null;
    }

    $('statConvos').textContent = convos.length;
    if ($('statConvosBadge')) $('statConvosBadge').textContent = convos.length;
    const list = $('conversationList');
    if (!convos.length) {
        list.innerHTML = '<div class="wa-empty">Sin chats reales para mostrar. Los estados y broadcasts no se muestran como conversaciones.</div>';
        return;
    }
    list.innerHTML = convos.map(c => {
        const countLabel = c.unread ? `${c.unread} nuevos` : (c.count ? `${c.count} mensajes` : '');
        const preview = c.lastBody || (c.count ? `${c.count} mensajes` : 'Sin mensajes recientes');
        
        // Determinar un avatar basado en si es grupo o contacto
        const iconClass = c.isGroup ? 'fa-users' : 'fa-user';
        const avatarBg = c.isGroup ? 'rgba(37,99,235,0.2)' : 'rgba(148,163,184,0.15)';
        const avatarColor = c.isGroup ? '#60a5fa' : '#cbd5e1';
        
        return `
        <button class="wa-convo ${selectedChat === c.chatId ? 'active' : ''}" type="button" data-chat-id="${esc(c.chatId)}">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; border-radius:50%; background:${avatarBg}; color:${avatarColor}; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:16px; overflow:hidden; position:relative;">
                    <img data-avatar="${esc(c.chatId)}" src="" style="width:100%; height:100%; object-fit:cover; position:absolute; top:0; left:0; display:none;" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <i class="fa-solid ${iconClass}"></i>
                </div>
                <div style="flex:1; min-width:0;">
                    <div class="wa-convo-top">
                        <span class="wa-convo-name">${esc(labelForChat(c.chatId))}</span>
                        <span class="wa-convo-time">${esc(fmtDate(c.lastAt))}</span>
                    </div>
                    <span class="wa-convo-preview">${esc(preview)}</span>
                    <div class="wa-convo-meta">
                        <span class="wa-muted">${esc(c.isGroup ? 'Grupo' : 'Contacto')}</span>
                        ${countLabel ? `<span class="wa-convo-count">${esc(countLabel)}</span>` : ''}
                    </div>
                </div>
            </div>
        </button>
    `}).join('');
    list.querySelectorAll('.wa-convo').forEach(btn => btn.addEventListener('click', async () => {
        const chatId = btn.dataset.chatId || '';
        if (!isPrimaryChatId(chatId)) return;
        if (selectedChat === chatId) return; // ignore clicking the already selected chat

        selectedChat = chatId;
        lastMsgId = ''; // reset so detection works fresh for this chat
        latestMessages = []; // clear old messages immediately
        lastRenderedMsgIds = ''; // force full re-render
        currentRenderedChat = selectedChat;
        
        renderConversations(convos);
        
        // Show immediate loader while fetching
        const body = $('chatBody');
        if (body) {
            body.innerHTML = '<div style="display:flex; flex-direction:column; justify-content:center; align-items:center; height:100%; text-align:center; padding:40px;"><i class="fa-solid fa-circle-notch fa-spin" style="font-size: 60px; color: #00d4ff; filter: drop-shadow(0 0 10px rgba(0,212,255,0.6));"></i><div style="margin-top: 24px; color: #bae6fd; font-size: 18px; font-weight: bold;">Sincronizando el chat...</div><div style="margin-top: 8px; color: #64748b; font-size: 13px; max-width: 300px; line-height: 1.5;">WhatsApp est&aacute; descargando el historial. Por favor espera, esto puede tardar un poco.</div></div>';
        }

        // Call the centralized load function
        await loadCurrentChatMessages();
    }));

    loadAvatars();
}

const loadedAvatars = {};

function loadAvatars() {
    if (!selectedSession) return;
    const state = String(selectedSession.status || '').toLowerCase();
    const isReady = ['ready','connected'].includes(state);
    if (!isReady) return; // Don't fetch avatars if the engine isn't ready yet
    
    document.querySelectorAll('img[data-avatar]:not([data-loading])').forEach(img => {
        const chatId = img.getAttribute('data-avatar');
        img.setAttribute('data-loading', 'true');
        
        if (loadedAvatars[chatId]) {
            if (loadedAvatars[chatId] !== 'none') {
                img.src = loadedAvatars[chatId];
                img.style.display = 'block';
                if (img.nextElementSibling) img.nextElementSibling.style.display = 'none';
            }
            return;
        }

        api('get_profile_pic', { query: `&session_id=${encodeURIComponent(selectedSession.id)}&chat_id=${encodeURIComponent(chatId)}` })
            .then(data => {
                if (data && data.url) {
                    loadedAvatars[chatId] = data.url;
                    img.src = data.url;
                    img.style.display = 'block';
                    if (img.nextElementSibling) img.nextElementSibling.style.display = 'none';
                } else {
                    loadedAvatars[chatId] = 'none';
                }
            })
            .catch(() => {
                loadedAvatars[chatId] = 'none';
            });
    });
}

// Global variable to track which chat is currently rendered
let currentRenderedChat = null;
let lastRenderedMsgIds = ''; // track what we last rendered to avoid unnecessary DOM work

function buildBubble(m) {
    const isFromMe = m.fromMe === true || m.fromMe === 'true' || String(m.direction || '').toLowerCase() === 'outgoing';
    const direction = isFromMe ? 'outgoing' : 'incoming';
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
        <div class="wa-meta"><span>${esc(direction === 'outgoing' ? 'Enviado' : 'Recibido')}</span><span>${esc(fmtDate(m.timestamp || m.createdAt))}</span><span>${esc(m.status || '')}</span></div>
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
    $('recordBtn').disabled = !selectedSession || !selectedChat;
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

let chatListLoaderInterval = null;
function showChatListLoader(show) {
    const container = $('chatListLoader');
    const bar = $('chatListProgressBar');
    if (!container || !bar) return;
    
    if (show) {
        container.style.display = 'block';
        bar.style.transition = 'none';
        bar.style.width = '0%';
        
        if (chatListLoaderInterval) clearInterval(chatListLoaderInterval);
        
        let progress = 0;
        setTimeout(() => {
            bar.style.transition = 'width 0.2s ease-out';
            chatListLoaderInterval = setInterval(() => {
                progress += (90 - progress) * 0.15;
                bar.style.width = progress + '%';
            }, 150);
        }, 50);
    } else {
        if (chatListLoaderInterval) {
            clearInterval(chatListLoaderInterval);
            chatListLoaderInterval = null;
        }
        bar.style.transition = 'width 0.1s ease-out';
        bar.style.width = '100%';
        setTimeout(() => {
            container.style.display = 'none';
        }, 200);
    }
}
let chatBodyLoaderInterval = null;
function showChatBodyLoader(show) {
    const container = $('chatBodyLoader');
    const bar = $('chatBodyProgressBar');
    if (!container || !bar) return;
    
    if (show) {
        container.style.display = 'block';
        bar.style.transition = 'none';
        bar.style.width = '0%';
        
        if (chatBodyLoaderInterval) clearInterval(chatBodyLoaderInterval);
        
        let progress = 0;
        setTimeout(() => {
            bar.style.transition = 'width 0.2s ease-out';
            chatBodyLoaderInterval = setInterval(() => {
                progress += (90 - progress) * 0.15;
                bar.style.width = progress + '%';
            }, 150);
        }, 50);
    } else {
        if (chatBodyLoaderInterval) {
            clearInterval(chatBodyLoaderInterval);
            chatBodyLoaderInterval = null;
        }
        bar.style.transition = 'width 0.1s ease-out';
        bar.style.width = '100%';
        setTimeout(() => {
            container.style.display = 'none';
        }, 200);
    }
}

async function loadChatList() {
    if (!selectedSession) return;
    const state = String(selectedSession.status || '').toLowerCase();
    const isReady = ['ready','connected'].includes(state);
    if (!convos.length) showChatListLoader(true);
    try {
        if (isReady) {
            let engineChats = await api('engine_chats', { query: `&session_id=${encodeURIComponent(selectedSession.id)}&limit=1000`, timeoutMs: 30000 });
            engineChats = Array.isArray(engineChats) ? engineChats
                : (Array.isArray(engineChats?.value) ? engineChats.value
                : (Array.isArray(engineChats?.data) ? engineChats.data : []));
            if (engineChats.length) {
                convos = normalizeConversationList(engineChats);
                renderConversations(convos);
            }
        }
    } catch (_) {}
    finally { showChatListLoader(false); }
}

let isBackgroundSyncing = false;
async function startBackgroundChatSync() {
    if (isBackgroundSyncing || !selectedSession) return;
    const state = String(selectedSession.status || '').toLowerCase();
    if (!['ready','connected'].includes(state)) return;
    
    isBackgroundSyncing = true;
    try {
        const currentSessionId = selectedSession.id;
        for (const convo of convos) {
            if (!selectedSession || selectedSession.id !== currentSessionId) break;
            const curState = String(selectedSession.status || '').toLowerCase();
            if (!['ready','connected'].includes(curState)) break;
            if (!isPrimaryChatId(convo.chatId)) continue;
            
            const syncKey = `synced_${currentSessionId}_${convo.chatId}`;
            if (!localStorage.getItem(syncKey)) {
                try {
                    await api('engine_chat_messages', { query: `&session_id=${encodeURIComponent(currentSessionId)}&chat_id=${encodeURIComponent(convo.chatId)}&limit=50`, timeoutMs: 30000 });
                    localStorage.setItem(syncKey, '1');
                } catch(e) {}
                
                // Sleep slightly between fetches to not overload the phone
                await new Promise(r => setTimeout(r, 1500));
            }
        }
    } finally {
        isBackgroundSyncing = false;
    }
}

async function loadDbMessages(chatId = '') {
    if (!selectedSession) return [];
    const data = await api('messages', { query: `&session_id=${encodeURIComponent(selectedSession.id)}&limit=200${chatId ? '&chat_id=' + encodeURIComponent(chatId) : ''}` });
    return (Array.isArray(data.messages) ? data.messages : [])
        .map(m => ({ ...m, chatId: normalizeChatId(m.chatId) }))
        .filter(m => isPrimaryChatId(m.chatId) && (!chatId || m.chatId === chatId));
}
const localChatDbPromise = new Promise((resolve, reject) => {
    const request = indexedDB.open('wa_chat_cache', 1);
    request.onupgradeneeded = (e) => {
        const db = e.target.result;
        if (!db.objectStoreNames.contains('messages')) {
            db.createObjectStore('messages', { keyPath: 'chatId' });
        }
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
});

async function saveChatToCache(chatId, msgs) {
    try {
        const db = await localChatDbPromise;
        const tx = db.transaction('messages', 'readwrite');
        tx.objectStore('messages').put({ chatId, msgs: msgs.slice(0, 200) });
    } catch(e) {}
}

async function getChatFromCache(chatId) {
    try {
        const db = await localChatDbPromise;
        return new Promise(resolve => {
            const tx = db.transaction('messages', 'readonly');
            const req = tx.objectStore('messages').get(chatId);
            req.onsuccess = () => resolve(req.result ? req.result.msgs : []);
            req.onerror = () => resolve([]);
        });
    } catch(e) { return []; }
}

async function loadCurrentChatMessages() {
    if (!selectedSession || !selectedChat) {
        showChatBodyLoader(false);
        return;
    }
    if (!isPrimaryChatId(selectedChat)) {
        selectedChat = '';
        latestMessages = [];
        renderConversations(convos);
        await renderChat();
        return;
    }
    const state = String(selectedSession.status || '').toLowerCase();
    const isReady = ['ready','connected'].includes(state);
    
    const isFirstLoad = currentRenderedChat !== selectedChat || latestMessages.length === 0;
    if (isFirstLoad) showChatBodyLoader(true);
    
    try {
        let engineMessages = [];
        
        if (isFirstLoad) {
            // Instantly load from cache and database to provide a snappy UI experience
            let cachedMsgs = await getChatFromCache(selectedChat);
            let dbMessages = await loadDbMessages(selectedChat);
            
            const existingIds = new Set(dbMessages.map(m => m.id || m._id));
            const newCachedMsgs = cachedMsgs.filter(m => !existingIds.has(m.id || m._id));
            let initialMessages = [...newCachedMsgs, ...dbMessages];
            initialMessages.sort((a, b) => getMs(b) - getMs(a));

            if (initialMessages.length > 0) {
                latestMessages = initialMessages;
                const newLastId = latestMessages.length ? (latestMessages[0]?.id || latestMessages[latestMessages.length-1]?.id || '') : '';
                lastMsgId = newLastId;
                
                await renderChat();
                showChatBodyLoader(false);

                // Silently fetch from engine in background to get older messages if needed
                if (isReady) {
                    const chatWhenStarted = selectedChat;
                    api('engine_chat_messages', { query: `&session_id=${encodeURIComponent(selectedSession.id)}&chat_id=${encodeURIComponent(selectedChat)}&limit=200`, timeoutMs: 30000 })
                        .then(res => {
                            if (selectedChat !== chatWhenStarted) return; // user switched chats
                            let msgs = Array.isArray(res) ? res : (Array.isArray(res?.value) ? res.value : (res?.messages || []));
                            if (!Array.isArray(msgs)) msgs = [];
                            msgs = msgs.map(m => ({ ...m, chatId: normalizeChatId(m.chatId || selectedChat) })).filter(m => m.chatId === selectedChat);
                            const currentExistingIds = new Set(latestMessages.map(m => m.id || m._id));
                            const newMsgs = msgs.filter(m => !currentExistingIds.has(m.id || m._id));
                            if (newMsgs.length > 0) {
                                latestMessages = [...newMsgs, ...latestMessages];
                                latestMessages.sort((a, b) => getMs(b) - getMs(a));
                                renderChat();
                                saveChatToCache(selectedChat, latestMessages);
                            }
                        }).catch(()=>{});
                }
                return; // We already rendered, so early return
            } else {
                // If Cache and DB are totally empty, wait for the engine and show loader
                if (isReady) {
                    engineMessages = await api('engine_chat_messages', { query: `&session_id=${encodeURIComponent(selectedSession.id)}&chat_id=${encodeURIComponent(selectedChat)}&limit=200`, timeoutMs: 30000 });
                    engineMessages = Array.isArray(engineMessages) ? engineMessages
                        : (Array.isArray(engineMessages?.value) ? engineMessages.value
                        : (engineMessages?.messages || []));
                    if (!Array.isArray(engineMessages)) engineMessages = [];
                    engineMessages = engineMessages
                        .map(m => ({ ...m, chatId: normalizeChatId(m.chatId || selectedChat) }))
                        .filter(m => m.chatId === selectedChat);
                }
            }
        } else {
            // Polling: just get the latest from the DB to be fast
            engineMessages = await loadDbMessages(selectedChat);
        }

        if (!engineMessages.length && isFirstLoad) {
            engineMessages = await loadDbMessages(selectedChat);
        }

        if (isFirstLoad) {
            latestMessages = engineMessages;
        } else {
            const existingIds = new Set(latestMessages.map(m => m.id || m._id));
            const existingFingerprints = new Set(latestMessages.map(m => `alt_${m.body}_${m.timestamp}_${m.fromMe}`));
            
            const newMsgs = engineMessages.filter(m => {
                const id = m.id || m._id;
                if (existingIds.has(id)) return false;
                
                const fingerprint = `alt_${m.body}_${m.timestamp}_${m.fromMe}`;
                if (existingFingerprints.has(fingerprint)) return false;
                
                // Add to sets so multiple duplicates inside engineMessages itself don't get added
                existingIds.add(id);
                existingFingerprints.add(fingerprint);
                return true;
            });
            
            if (newMsgs.length > 0) {
                latestMessages = [...newMsgs, ...latestMessages];
            }
        }

        // Sort descending (newest first)
        engineMessages.sort((a, b) => getMs(b) - getMs(a));
        latestMessages.sort((a, b) => getMs(b) - getMs(a));

        // Detect new messages by comparing counts and last ID
        const newLastId = engineMessages.length ? (engineMessages[0]?.id || engineMessages[engineMessages.length-1]?.id || '') : '';
        const newestMsg = engineMessages[0];
        const isNewestFromMe = newestMsg && (newestMsg.fromMe === true || newestMsg.fromMe === 'true' || String(newestMsg.direction || '').toLowerCase() === 'outgoing');
        const isIncoming = newestMsg && !isNewestFromMe;
        const hadNewMessages = newLastId && newLastId !== lastMsgId && latestMessages.length > 0 && isIncoming;

        if (hadNewMessages) {
            onNewMessage(selectedChat);
        }

        if (engineMessages.length > 0) {
            lastMsgId = newLastId;
            // Update matching conversation in convos so the sidebar updates in real-time
            const newestMsg = engineMessages[0];
            const convo = convos.find(c => c.chatId === selectedChat);
            if (convo) {
                convo.lastBody = newestMsg.body || `[${newestMsg.type || 'mensaje'}]`;
                convo.lastAt = newestMsg.timestamp || newestMsg.createdAt;
                renderConversations(convos);
            }
        }

        // One final safety deduplication for initial loads
        const seenMsg = new Set();
        latestMessages = latestMessages.filter(m => {
            const id = m.id || m._id;
            if (id && seenMsg.has(id)) return false;
            if (id) seenMsg.add(id);
            
            const fingerprint = `alt_${m.body}_${m.timestamp}_${m.fromMe}`;
            if (seenMsg.has(fingerprint)) return false;
            seenMsg.add(fingerprint);
            
            return true;
        });

        await renderChat();
        saveChatToCache(selectedChat, latestMessages);
    } catch (err) {
        console.error('[WA] loadCurrentChatMessages error:', err);
        if (latestMessages.length === 0) {
            try {
                const dbMsgs = await loadDbMessages(selectedChat);
                if (dbMsgs.length) {
                    dbMsgs.sort((a, b) => getMs(b) - getMs(a));
                    latestMessages = dbMsgs;
                    await renderChat();
                } else {
                    const body = $('chatBody');
                    if (body) {
                        body.innerHTML = `<div class="wa-empty" style="color:#ef4444;">Error al cargar mensajes: ${esc(err.message)}</div>`;
                        lastRenderedMsgIds = '__error__';
                    }
                }
            } catch (_) {
                const body = $('chatBody');
                if (body) {
                    body.innerHTML = `<div class="wa-empty" style="color:#ef4444;">Error al cargar mensajes: ${esc(err.message)}</div>`;
                    lastRenderedMsgIds = '__error__';
                }
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
        const isReady = ['ready','connected'].includes(state);

        if (!isReady) {
            convos = [];
            latestMessages = [];
            selectedChat = '';
            $('conversationList').innerHTML = '<div class="wa-empty" style="color:#cbd5e1;line-height:1.5;text-align:center;">Vincule la sesión para cargar los chats de este número.</div>';
            $('chatBody').innerHTML = `
                <div class="wa-empty" style="max-width:640px;margin:0 auto;line-height:1.65;color:#cbd5e1;text-align:left;">
                    <div style="display:flex;gap:14px;align-items:flex-start;padding:18px 20px;border:1px solid rgba(14,165,233,.28);border-radius:18px;background:linear-gradient(135deg,rgba(14,165,233,.12),rgba(15,23,42,.62));box-shadow:0 18px 45px rgba(0,0,0,.22);">
                        <div style="width:42px;height:42px;border-radius:14px;display:grid;place-items:center;background:rgba(14,165,233,.16);color:#38bdf8;border:1px solid rgba(56,189,248,.28);">
                            <i class="fa-solid fa-qrcode"></i>
                        </div>
                        <div>
                            <strong style="display:block;color:#f8fafc;font-size:16px;margin-bottom:6px;">Sesión inactiva o esperando vinculación</strong>
                            <span>Asegúrate de que la sesión esté iniciada y vinculada para sincronizar los chats y mensajes.</span>
                        </div>
                    </div>
                </div>`;
            $('statConvos').textContent = '0';
            if ($('statConvosBadge')) $('statConvosBadge').textContent = '0';
            $('chatTitle').textContent = selectedSession.name || 'Sesión inactiva';
            $('chatSubtitle').textContent = 'Esperando conexión...';
            
            if (force || ['qr','qr_ready','initializing','authenticating'].includes(state)) {
                getQrMaybe();
            }
            return;
        }

        // Load chat list on initial load
        if (force || !convos.length) {
            let fetchedConvos = [];
            
            // Fast load from SQLite database through PHP endpoint
            try {
                const dbData = await api('messages', { query: `&session_id=${encodeURIComponent(selectedSession.id)}&limit=1000` });
                if (dbData && Array.isArray(dbData.conversations)) {
                    fetchedConvos = dbData.conversations;
                }
            } catch (_) {}
            
            if (fetchedConvos.length) {
                convos = normalizeConversationList(fetchedConvos);
                renderConversations(convos);
            } else {
                convos = [];
                renderConversations(convos);
            }
            
            // Fetch engine chats silently in background to update latest real info
            if (isReady) {
                loadChatList().then(() => {
                    startBackgroundChatSync();
                }); // Notice: NO await. We don't block the UI!
            }
        }

        // Load messages for current chat
        if (selectedChat) {
            await loadCurrentChatMessages();
        } else {
            const firstRealChat = convos.find(c => isPrimaryChatId(c.chatId));
            if (firstRealChat) {
                selectedChat = firstRealChat.chatId;
                await loadCurrentChatMessages();
            }
        }

        // Fallback: DB messages if engine gave nothing
        if (!latestMessages.length) {
            try {
                const data = await api('messages', { query: `&session_id=${encodeURIComponent(selectedSession.id)}&limit=200${selectedChat ? '&chat_id=' + encodeURIComponent(selectedChat) : ''}` });
                const dbMsgs = (Array.isArray(data.messages) ? data.messages : [])
                    .map(m => ({ ...m, chatId: normalizeChatId(m.chatId) }))
                    .filter(m => isPrimaryChatId(m.chatId) && (!selectedChat || m.chatId === selectedChat));
                if (dbMsgs.length) {
                    dbMsgs.sort((a, b) => getMs(b) - getMs(a));
                    if (!selectedChat) {
                        selectedChat = dbMsgs[0].chatId;
                    }
                    latestMessages = dbMsgs;
                    if (!convos.length) {
                        const fallbackConvos = Array.isArray(data.conversations) && data.conversations.length
                            ? data.conversations
                            : dbMsgs.map(m => ({
                            chatId: m.chatId,
                            lastBody: m.body || `[${m.type || 'mensaje'}]`,
                            lastAt: m.timestamp || m.createdAt,
                            count: 1,
                        }));
                        convos = normalizeConversationList(fallbackConvos);
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

let sessionPollTimer = null;  // poll session status every 5s to detect connect/disconnect

function startPolling() {
    stopPolling();
    if (!selectedSession) return;

    // ── Fast poll (3s): refresh messages + detect session status changes ──
    msgPollTimer = setInterval(async () => {
        // Always check session status first to detect QR→ready transitions
        const prevStatus = String(selectedSession?.status || '').toLowerCase();
        const wasReady   = ['ready','connected'].includes(prevStatus);
        
        await refreshSelectedSession();
        
        const nowStatus = String(selectedSession?.status || '').toLowerCase();
        const isReady   = ['ready','connected'].includes(nowStatus);

        // Just became ready → load chats immediately
        if (!wasReady && isReady) {
            await loadMessages(true);
            return;
        }

        // Already ready → update messages and sidebar
        if (isReady) {
            await loadCurrentChatMessages();
            
            try {
                const globalMsgs = await loadDbMessages('');
                if (globalMsgs && globalMsgs.length > 0) {
                    let updated = false;
                    for (const m of globalMsgs) {
                        const convo = convos.find(c => c.chatId === m.chatId);
                        if (convo) {
                            const mTime = m.timestamp || m.createdAt;
                            if (getMs(mTime) > getMs(convo.lastAt)) {
                                convo.lastBody = m.body || `[${m.type || 'mensaje'}]`;
                                convo.lastAt = mTime;
                                updated = true;
                            }
                        }
                    }
                    if (updated) renderConversations(convos);
                }
            } catch(e){}
        }
    }, 3000);

    // ── Slow poll (30s): full chat list refresh ──
    chatListPollTimer = setInterval(async () => {
        if (selectedSession && ['ready','connected'].includes(String(selectedSession.status || '').toLowerCase())) {
            await loadChatList();
        }
    }, 30000);
}

function stopPolling() {
    if (msgPollTimer)     { clearInterval(msgPollTimer);     msgPollTimer = null; }
    if (sessionPollTimer) { clearInterval(sessionPollTimer); sessionPollTimer = null; }
    if (chatListPollTimer){ clearInterval(chatListPollTimer);chatListPollTimer = null; }
    if (pollTimer)        { clearInterval(pollTimer);        pollTimer = null; }
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
    let text = $('messageInput').value.trim();
    let file = currentMediaFile;
    
    // If recording is active, grab the audio blob and send it!
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        const audioData = await stopRecording(false);
        if (audioData && audioData.blob) {
            // Construct a File-like object so the existing logic can send it
            file = new File([audioData.blob], "voice_note.webm", { type: audioData.mimeType });
            text = ''; // No caption for voice notes
        }
    }
    
    if (!selectedSession || !selectedChat || (!text && !file)) return;
    $('sendBtn').disabled = true;
    try {
        if (file) {
            const base64Data = await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = e => resolve(e.target.result);
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });
            
            const mimeTypeRaw = file.type || 'application/octet-stream';
            const mimetype = mimeTypeRaw.split(';')[0];
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
            clearMediaPreview();
        } else {
            await api('send_message', { method:'POST', body:{ session_id:selectedSession.id, chat_id:selectedChat, text } });
        }
        
        $('messageInput').value = '';
        toggleSendRecordBtns();
        await loadMessages(false);
    } catch (err) {
        alert("Error enviando: " + err.message);
    } finally {
        $('sendBtn').disabled = false;
        $('recordBtn').disabled = false;
    }
});

function toggleSendRecordBtns() {
    const text = $('messageInput').value.trim();
    const file = currentMediaFile;
    if (text || file) {
        $('sendBtn').style.display = 'block';
        $('recordBtn').style.display = 'none';
    } else {
        $('sendBtn').style.display = 'none';
        $('recordBtn').style.display = 'block';
    }
}

$('messageInput').addEventListener('input', toggleSendRecordBtns);

// Audio Recording Logic
let mediaRecorder;
let audioChunks = [];
let recordingInterval;
let recordingStartTime;

async function startRecording() {
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Tu navegador bloqueó el acceso al micrófono. Esto pasa si estás usando una IP (ej. 192.168...) sin HTTPS. Debes acceder desde "localhost" o usar HTTPS.');
            return;
        }
        
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        audioChunks = [];
        
        mediaRecorder.ondataavailable = e => {
            if (e.data.size > 0) audioChunks.push(e.data);
        };
        
        mediaRecorder.start();
        
        // UI Changes
        $('messageInput').style.display = 'none';
        $('recordingUI').style.display = 'flex';
        $('recordBtn').style.display = 'none';
        $('sendBtn').style.display = 'block';
        $('sendBtn').disabled = false;
        
        const attachLabel = document.querySelector('label[for="mediaInput"]');
        if (attachLabel) attachLabel.style.display = 'none';
        
        recordingStartTime = Date.now();
        $('recordingTimer').textContent = '0:00';
        recordingInterval = setInterval(() => {
            const secs = Math.floor((Date.now() - recordingStartTime) / 1000);
            const m = Math.floor(secs / 60);
            const s = secs % 60;
            $('recordingTimer').textContent = `${m}:${s.toString().padStart(2, '0')}`;
        }, 1000);
        
    } catch (err) {
        alert('No se pudo acceder al micrófono: ' + err.message);
    }
}

function stopRecording(cancel = false) {
    if (!mediaRecorder || mediaRecorder.state === 'inactive') return;
    
    clearInterval(recordingInterval);
    
    // Restore UI
    $('messageInput').style.display = 'block';
    $('recordingUI').style.display = 'none';
    const attachLabel = document.querySelector('label[for="mediaInput"]');
    if (attachLabel) attachLabel.style.display = 'flex';
    toggleSendRecordBtns();
    
    // Stop tracks
    mediaRecorder.stream.getTracks().forEach(t => t.stop());
    
    if (cancel) {
        mediaRecorder.onstop = null;
        mediaRecorder.stop();
        audioChunks = [];
    } else {
        return new Promise(resolve => {
            mediaRecorder.onstop = () => {
                const mimeType = mediaRecorder.mimeType || 'audio/webm';
                const audioBlob = new Blob(audioChunks, { type: mimeType });
                audioChunks = [];
                resolve({ blob: audioBlob, mimeType });
            };
            mediaRecorder.stop();
        });
    }
}

$('recordBtn').addEventListener('click', startRecording);
$('cancelRecordBtn').addEventListener('click', () => stopRecording(true));

// ── Message Search ──────────────────────────────────────────────────────────
(function() {
    const searchBar   = $('msgSearchBar');
    const searchInput = $('msgSearchInput');
    const searchCount = $('msgSearchCount');
    const toggleBtn   = $('searchToggleBtn');
    const closeBtn    = $('msgSearchClose');
    const prevBtn     = $('msgSearchPrev');
    const nextBtn     = $('msgSearchNext');

    let matches      = [];   // list of <mark> elements currently highlighted
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

    function clearHighlights() {
        const chatBody = $('chatBody');
        // Restore each highlighted <mark> to its text node
        chatBody.querySelectorAll('mark.wa-highlight').forEach(mark => {
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

        // Walk text nodes inside message bubbles only
        const walker = document.createTreeWalker(
            chatBody,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode(node) {
                    // Skip nodes inside script, style, button, time tags
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
                    mark.className = 'wa-highlight';
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

    function scrollToCurrent() {
        matches.forEach((m, i) => {
            m.classList.toggle('wa-current', i === currentIndex);
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

    // Toggle open/close with lupa button
    toggleBtn.addEventListener('click', () => {
        if (searchBar.classList.contains('open')) {
            closeSearch();
        } else {
            openSearch();
        }
    });

    closeBtn.addEventListener('click', closeSearch);

    // Keyboard: Escape closes, Enter navigates
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

    // Re-run search after chat renders (messages change)
    const origRenderChat = window.renderChat;
    if (typeof origRenderChat === 'function') {
        window.renderChat = function(...args) {
            const result = origRenderChat.apply(this, args);
            if (searchBar.classList.contains('open') && searchInput.value.trim()) {
                setTimeout(() => highlightMatches(searchInput.value.trim()), 50);
            }
            return result;
        };
    }

    // Also close search when chat changes
    document.addEventListener('chatChanged', closeSearch);
})();

document.addEventListener('DOMContentLoaded', async () => {
    await loadSessions();
    startPolling();

    // Global watcher: even with no selectedSession, keep checking for session state changes
    // so the UI updates without F5 when WhatsApp connects after scanning QR.
    setInterval(async () => {
        if (selectedSession) return; // already handled by startPolling's 3s timer
        try {
            const data = await api('sessions', { timeoutMs: 4000 });
            const list = Array.isArray(data) ? data : [];
            if (!list.length) return;
            const changed = list.some((s, i) => {
                const old = sessions[i];
                return !old || old.id !== s.id || old.status !== s.status;
            });
            if (changed) {
                // Full reload so UI transitions correctly (e.g. qr→ready loads chats)
                await loadSessions();
                startPolling();
            }
        } catch(e) {}
    }, 5000);
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
