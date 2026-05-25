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
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_product')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_product VARCHAR(255) NULL AFTER reviewed_at");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_quantity')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_quantity VARCHAR(120) NULL AFTER ai_product");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_delivery_date')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_delivery_date VARCHAR(120) NULL AFTER ai_quantity");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_customer')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_customer VARCHAR(255) NULL AFTER ai_delivery_date");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_observations')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_observations TEXT NULL AFTER ai_customer");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_confidence')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_confidence DECIMAL(5,2) NULL AFTER ai_observations");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_is_order')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_is_order TINYINT(1) NULL AFTER ai_confidence");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_model')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_model VARCHAR(120) NULL AFTER ai_is_order");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_raw_json')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_raw_json LONGTEXT NULL AFTER ai_model");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_extracted_at')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_extracted_at DATETIME NULL AFTER ai_raw_json");
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

    if (!tableExists($pdo, 'app_settings')) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS app_settings (
                setting_key VARCHAR(120) PRIMARY KEY,
                setting_value TEXT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    // Las cuentas se gestionan desde el modal. No se reinsertan desde .env al borrar.
    $pdo->prepare("
        INSERT INTO app_settings (setting_key, setting_value)
        VALUES ('mail_env_bootstrapped', 'disabled')
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ")->execute();
}

ensureEmailDashboardSchema($pdo);

function mailProviderConfig(string $provider, string $email): array
{
    $provider = strtolower(trim($provider));
    $domain = strtolower((string)substr(strrchr($email, '@') ?: '', 1));

    $configs = [
        'gmail' => [
            'label' => 'Gmail',
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'imap_secure' => 1,
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 465,
            'smtp_secure' => 'ssl',
        ],
        'outlook' => [
            'label' => 'Outlook/Hotmail',
            'imap_host' => 'outlook.office365.com',
            'imap_port' => 993,
            'imap_secure' => 1,
            'smtp_host' => 'smtp.office365.com',
            'smtp_port' => 587,
            'smtp_secure' => 'tls',
        ],
        'yahoo' => [
            'label' => 'Yahoo',
            'imap_host' => 'imap.mail.yahoo.com',
            'imap_port' => 993,
            'imap_secure' => 1,
            'smtp_host' => 'smtp.mail.yahoo.com',
            'smtp_port' => 465,
            'smtp_secure' => 'ssl',
        ],
    ];

    if (isset($configs[$provider])) {
        return $configs[$provider];
    }

    $domainConfigs = [
        'teotek.com.mx' => [
            'label' => 'Teotek',
            'imap_host' => 'mail.teotek.com.mx',
            'imap_port' => 993,
            'imap_secure' => 1,
            'smtp_host' => 'mail.teotek.com.mx',
            'smtp_port' => 465,
            'smtp_secure' => 'ssl',
        ],
        'vast.com.mx' => [
            'label' => 'Vast',
            'imap_host' => 'mail.vast.com.mx',
            'imap_port' => 993,
            'imap_secure' => 1,
            'smtp_host' => 'mail.vast.com.mx',
            'smtp_port' => 465,
            'smtp_secure' => 'ssl',
        ],
    ];

    if (isset($domainConfigs[$domain])) {
        return $domainConfigs[$domain];
    }

    if ($domain !== '') {
        return [
            'label' => 'Correo empresarial',
            'imap_host' => 'mail.' . $domain,
            'imap_port' => 993,
            'imap_secure' => 1,
            'smtp_host' => 'mail.' . $domain,
            'smtp_port' => 465,
            'smtp_secure' => 'ssl',
        ];
    }

    return [
        'label' => 'Correo empresarial',
        'imap_host' => '',
        'imap_port' => 993,
        'imap_secure' => 1,
        'smtp_host' => '',
        'smtp_port' => 465,
        'smtp_secure' => 'ssl',
    ];
}

function normalizeMailAccountInput(array $post, ?array $current = null): array
{
    $email = trim((string)($post['email'] ?? ''));
    $providerKey = trim((string)($post['provider_key'] ?? 'enterprise'));
    $provider = trim((string)($post['provider'] ?? ''));
    $config = mailProviderConfig($providerKey, $email);

    $imapPass = trim((string)($post['imap_pass'] ?? ''));
    $smtpPass = trim((string)($post['smtp_pass'] ?? ''));

    return [
        'email' => $email,
        'provider' => $provider !== '' ? $provider : (string)$config['label'],
        'imap_host' => trim((string)($post['imap_host'] ?? '')) ?: (string)$config['imap_host'],
        'imap_port' => (int)($post['imap_port'] ?? $config['imap_port']),
        'imap_secure' => (int)($post['imap_secure'] ?? $config['imap_secure']) === 1 ? 1 : 0,
        'imap_user' => trim((string)($post['imap_user'] ?? '')) ?: $email,
        'imap_pass' => $imapPass !== '' ? $imapPass : (string)($current['imap_pass'] ?? ''),
        'imap_mailbox' => trim((string)($post['imap_mailbox'] ?? 'INBOX')) ?: 'INBOX',
        'imap_only_unseen' => (int)($post['imap_only_unseen'] ?? 1) === 1 ? 1 : 0,
        'smtp_host' => trim((string)($post['smtp_host'] ?? '')) ?: (string)$config['smtp_host'],
        'smtp_port' => (int)($post['smtp_port'] ?? $config['smtp_port']),
        'smtp_secure' => trim((string)($post['smtp_secure'] ?? '')) ?: (string)$config['smtp_secure'],
        'smtp_user' => trim((string)($post['smtp_user'] ?? '')) ?: $email,
        'smtp_pass' => $smtpPass !== '' ? $smtpPass : ($imapPass !== '' ? $imapPass : (string)($current['smtp_pass'] ?? '')),
        'is_active' => (int)($post['is_active'] ?? 1) === 1 ? 1 : 0,
    ];
}

function testImapAccountConnection(array $account): void
{
    if (!function_exists('imap_open')) {
        throw new RuntimeException('La extension IMAP de PHP no esta habilitada en XAMPP.');
    }

    $host = (string)($account['imap_host'] ?? '');
    $port = (int)($account['imap_port'] ?? 993);
    $secure = (int)($account['imap_secure'] ?? 1) === 1;
    $user = (string)($account['imap_user'] ?? '');
    $pass = (string)($account['imap_pass'] ?? '');
    $mailboxName = (string)($account['imap_mailbox'] ?? 'INBOX');

    if ($host === '' || $user === '' || $pass === '') {
        throw new RuntimeException('Faltan datos para probar la conexion IMAP.');
    }

    $mailbox = sprintf('{%s:%d/imap/%s}%s', $host, $port > 0 ? $port : 993, $secure ? 'ssl' : 'notls', $mailboxName);
    $imap = @imap_open($mailbox, $user, $pass, OP_READONLY, 1);
    if ($imap === false) {
        $err = imap_last_error() ?: 'Error desconocido';
        imap_errors();
        throw new RuntimeException('No se pudo conectar con IMAP. Revisa correo, password o configuracion avanzada. Detalle: ' . $err);
    }
    imap_errors();
    imap_close($imap);
}

function compactMailText(string $text, int $maxLength = 6000): string
{
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    $text = trim($text);
    return mb_substr($text, 0, $maxLength);
}

function extractJsonObjectFromText(string $content): array
{
    $content = trim($content);
    $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
    $content = preg_replace('/\s*```$/', '', $content) ?? $content;

    $decoded = json_decode($content, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    if (preg_match('/\{.*\}/s', $content, $m)) {
        $decoded = json_decode($m[0], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    throw new RuntimeException('La IA respondio, pero no regreso JSON valido.');
}

function normalizeAiExtraction(array $data): array
{
    $confidence = $data['nivel_confianza'] ?? $data['confianza'] ?? $data['confidence'] ?? null;
    $confidence = is_numeric($confidence) ? (float)$confidence : null;
    if ($confidence !== null && $confidence <= 1) {
        $confidence *= 100;
    }
    if ($confidence !== null) {
        $confidence = max(0, min(100, $confidence));
    }

    $isOrder = $data['es_pedido'] ?? $data['is_order'] ?? null;
    if (is_string($isOrder)) {
        $isOrder = in_array(mb_strtolower($isOrder), ['si', 'sí', 'true', '1', 'yes'], true);
    } elseif ($isOrder !== null) {
        $isOrder = (bool)$isOrder;
    }

    return [
        'producto_solicitado' => trim((string)($data['producto_solicitado'] ?? $data['producto'] ?? '')),
        'cantidad' => trim((string)($data['cantidad'] ?? '')),
        'fecha_entrega' => trim((string)($data['fecha_entrega'] ?? $data['fecha_de_entrega'] ?? '')),
        'cliente' => trim((string)($data['cliente'] ?? '')),
        'observaciones' => trim((string)($data['observaciones'] ?? '')),
        'nivel_confianza' => $confidence,
        'es_pedido' => $isOrder,
    ];
}

function ollamaEndpointParts(): array
{
    $parts = parse_url(defined('OLLAMA_BASE_URL') ? OLLAMA_BASE_URL : 'http://localhost:11434');
    $host = (string)($parts['host'] ?? '127.0.0.1');
    $port = (int)($parts['port'] ?? 11434);
    if ($host === 'localhost') {
        $host = '127.0.0.1';
    }
    return [$host, $port];
}

function isOllamaRunning(): bool
{
    [$host, $port] = ollamaEndpointParts();
    $socket = @fsockopen($host, $port, $errno, $errstr, 0.8);
    if (is_resource($socket)) {
        fclose($socket);
        return true;
    }
    return false;
}

function findOllamaExecutable(): string
{
    $candidates = [];
    $envExe = getenv('OLLAMA_EXE');
    if ($envExe) {
        $candidates[] = $envExe;
    }
    $localAppData = getenv('LOCALAPPDATA');
    if ($localAppData) {
        $candidates[] = $localAppData . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Ollama' . DIRECTORY_SEPARATOR . 'ollama.exe';
    }
    $userProfile = getenv('USERPROFILE');
    if ($userProfile) {
        $candidates[] = $userProfile . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Ollama' . DIRECTORY_SEPARATOR . 'ollama.exe';
    }
    $candidates[] = 'C:\\Users\\DEMIAN\\AppData\\Local\\Programs\\Ollama\\ollama.exe';

    foreach (array_unique($candidates) as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    return '';
}

function startOllamaFromProject(bool $waitForReady = false): bool
{
    if (isOllamaRunning()) {
        return true;
    }

    $exe = findOllamaExecutable();
    if ($exe === '' || !function_exists('popen')) {
        return false;
    }

    $cmd = 'cmd /C start "NexusPanel Ollama" /MIN ' . escapeshellarg($exe) . ' serve';
    $handle = @popen($cmd, 'r');
    if (is_resource($handle)) {
        @pclose($handle);
    }

    if (!$waitForReady) {
        return true;
    }

    $deadline = microtime(true) + 12;
    do {
        usleep(500000);
        if (isOllamaRunning()) {
            return true;
        }
    } while (microtime(true) < $deadline);

    return false;
}

function analyzeMailWithOllama(array $mail): array
{
    if (!defined('OLLAMA_BASE_URL') || OLLAMA_BASE_URL === '') {
        throw new RuntimeException('Falta configurar OLLAMA_BASE_URL.');
    }
    if (!startOllamaFromProject(true)) {
        throw new RuntimeException('No se pudo iniciar Ollama automaticamente desde el proyecto. Abre Ollama manualmente o verifica la ruta del ejecutable.');
    }

    $body = compactMailText((string)($mail['body_text'] ?: strip_tags((string)($mail['body_html']))));
    if ($body === '') {
        throw new RuntimeException('El correo no tiene texto suficiente para analizar.');
    }

    $systemPrompt = implode("\n", [
        'Eres un extractor local para pedidos de alimentos o insumos.',
        'Tu unica tarea es leer correos y regresar JSON estricto.',
        'No inventes datos. Si falta informacion usa null o cadena vacia.',
        'No ejecutes acciones, no descuentes inventario y no crees pedidos.',
        'El humano administrador validara todo antes de avanzar.',
        'Responde solo con JSON usando estas llaves:',
        'producto_solicitado, cantidad, fecha_entrega, cliente, observaciones, nivel_confianza, es_pedido.',
        'Nunca omitas ninguna llave. Si falta un dato usa cadena vacia, null o false segun corresponda.',
        'cantidad debe conservar la unidad si aparece en el correo, por ejemplo "40 piezas" o "20 toneladas".',
        'observaciones debe resumir notas operativas como confirmar disponibilidad, factura, flete, horarios o urgencia.',
        'nivel_confianza es obligatorio y debe ser un numero de 0 a 100.',
        'fecha_entrega debe normalizarse como YYYY-MM-DD cuando sea posible; si no es clara, conserva el texto original.',
    ]);

    $userPrompt = "Asunto: " . (string)($mail['subject'] ?? '') . "\n"
        . "Remitente: " . (string)($mail['from_name'] ?: $mail['from_email'] ?: '') . "\n"
        . "Fecha correo: " . (string)($mail['received_at'] ?: $mail['fetched_at'] ?: '') . "\n\n"
        . "Cuerpo del correo:\n" . $body;

    $payload = [
        'model' => OLLAMA_MODEL,
        'stream' => false,
        'format' => 'json',
        'options' => [
            'temperature' => 0.1,
        ],
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
    ];

    $url = OLLAMA_BASE_URL . '/api/chat';
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timeout' => 90,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        throw new RuntimeException('No se pudo conectar con Ollama en ' . OLLAMA_BASE_URL . '. Verifica que este corriendo y que el modelo este descargado.');
    }

    $response = json_decode($raw, true);
    if (!is_array($response)) {
        throw new RuntimeException('Ollama respondio con un formato inesperado.');
    }
    if (isset($response['error'])) {
        throw new RuntimeException('Ollama: ' . (string)$response['error']);
    }

    $content = (string)($response['message']['content'] ?? $response['response'] ?? '');
    if ($content === '') {
        throw new RuntimeException('Ollama no regreso contenido para analizar.');
    }

    $json = extractJsonObjectFromText($content);
    $normalized = normalizeAiExtraction($json);
    $normalized['raw_json'] = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $normalized;
}

function saveAiExtraction(PDO $pdo, int $id, array $data): void
{
    $confidence = $data['nivel_confianza'];
    $isOrder = $data['es_pedido'];

    $pdo->prepare("
        UPDATE email_inbox_messages
        SET ai_product = ?,
            ai_quantity = ?,
            ai_delivery_date = ?,
            ai_customer = ?,
            ai_observations = ?,
            ai_confidence = ?,
            ai_is_order = ?,
            ai_model = ?,
            ai_raw_json = ?,
            ai_extracted_at = NOW()
        WHERE id = ?
    ")->execute([
        $data['producto_solicitado'] !== '' ? mb_substr($data['producto_solicitado'], 0, 255) : null,
        $data['cantidad'] !== '' ? mb_substr($data['cantidad'], 0, 120) : null,
        $data['fecha_entrega'] !== '' ? mb_substr($data['fecha_entrega'], 0, 120) : null,
        $data['cliente'] !== '' ? mb_substr($data['cliente'], 0, 255) : null,
        $data['observaciones'] !== '' ? mb_substr($data['observaciones'], 0, 2500) : null,
        $confidence !== null ? round((float)$confidence, 2) : null,
        $isOrder === null ? null : ($isOrder ? 1 : 0),
        OLLAMA_MODEL,
        $data['raw_json'] ?? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $id,
    ]);
}

$msg = '';
$err = '';
$mailModalError = '';
$reopenMailModal = false;
$reopenAddPanel = false;
$showAdvancedPanel = false;
$failedMailPost = [];
$postSelectedId = 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Al abrir el modulo de pedidos, dejamos Ollama arrancando en segundo plano para la IA.
    startOllamaFromProject(false);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'sync_imap') {
            $activeCount = (int)$pdo->query("SELECT COUNT(*) FROM mail_accounts WHERE is_active = 1")->fetchColumn();
            if ($activeCount === 0) {
                throw new RuntimeException('No hay cuentas IMAP activas. Vincula una cuenta antes de sincronizar.');
            }
            require_once '../workers/imap_reader.php';
            $result = readInboxMessages($pdo, 30);
            $msg = 'Sincronizacion IMAP completada. Guardados: ' . (int)$result['saved'] . ', omitidos: ' . (int)$result['skipped'] . '.';
        } elseif ($action === 'add_mail_account') {
            $accountInput = normalizeMailAccountInput($_POST);

            if (!filter_var($accountInput['email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Correo invalido para la cuenta.');
            }
            if ($accountInput['imap_host'] === '' || $accountInput['imap_user'] === '' || $accountInput['imap_pass'] === '') {
                throw new RuntimeException('Faltan datos IMAP obligatorios.');
            }

            testImapAccountConnection($accountInput);

            if ($accountInput['is_active'] === 1) {
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
                $accountInput['email'],
                $accountInput['provider'] !== '' ? mb_substr($accountInput['provider'], 0, 80) : null,
                mb_substr($accountInput['imap_host'], 0, 190),
                $accountInput['imap_port'] > 0 ? $accountInput['imap_port'] : 993,
                $accountInput['imap_secure'],
                mb_substr($accountInput['imap_user'], 0, 190),
                mb_substr($accountInput['imap_pass'], 0, 255),
                $accountInput['imap_mailbox'] !== '' ? mb_substr($accountInput['imap_mailbox'], 0, 120) : 'INBOX',
                $accountInput['imap_only_unseen'],
                $accountInput['smtp_host'] !== '' ? mb_substr($accountInput['smtp_host'], 0, 190) : null,
                $accountInput['smtp_port'] > 0 ? $accountInput['smtp_port'] : null,
                $accountInput['smtp_secure'] !== '' ? mb_substr($accountInput['smtp_secure'], 0, 20) : null,
                $accountInput['smtp_user'] !== '' ? mb_substr($accountInput['smtp_user'], 0, 190) : null,
                $accountInput['smtp_pass'] !== '' ? mb_substr($accountInput['smtp_pass'], 0, 255) : null,
                $accountInput['is_active'],
                (int)($usuario['usuario_id'] ?? 0),
            ]);
            $msg = 'Conexion verificada. Cuenta de correo guardada correctamente.';
        } elseif ($action === 'update_mail_account') {
            $accountId = (int)($_POST['account_id'] ?? 0);

            if ($accountId <= 0) {
                throw new RuntimeException('Cuenta de correo invalida para editar.');
            }

            $stCurrent = $pdo->prepare("SELECT imap_pass, smtp_pass FROM mail_accounts WHERE id = ? LIMIT 1");
            $stCurrent->execute([$accountId]);
            $curr = $stCurrent->fetch();
            if (!$curr) {
                throw new RuntimeException('No se encontro la cuenta para editar.');
            }

            $accountInput = normalizeMailAccountInput($_POST, $curr);
            if (!filter_var($accountInput['email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Correo invalido para la cuenta.');
            }
            if ($accountInput['imap_host'] === '' || $accountInput['imap_user'] === '') {
                throw new RuntimeException('Faltan datos IMAP obligatorios.');
            }

            testImapAccountConnection($accountInput);

            if ($accountInput['is_active'] === 1) {
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
                $accountInput['email'],
                $accountInput['provider'] !== '' ? mb_substr($accountInput['provider'], 0, 80) : null,
                mb_substr($accountInput['imap_host'], 0, 190),
                $accountInput['imap_port'] > 0 ? $accountInput['imap_port'] : 993,
                $accountInput['imap_secure'],
                mb_substr($accountInput['imap_user'], 0, 190),
                mb_substr($accountInput['imap_pass'], 0, 255),
                $accountInput['imap_mailbox'] !== '' ? mb_substr($accountInput['imap_mailbox'], 0, 120) : 'INBOX',
                $accountInput['imap_only_unseen'],
                $accountInput['smtp_host'] !== '' ? mb_substr($accountInput['smtp_host'], 0, 190) : null,
                $accountInput['smtp_port'] > 0 ? $accountInput['smtp_port'] : null,
                $accountInput['smtp_secure'] !== '' ? mb_substr($accountInput['smtp_secure'], 0, 20) : null,
                $accountInput['smtp_user'] !== '' ? mb_substr($accountInput['smtp_user'], 0, 190) : null,
                $accountInput['smtp_pass'] !== '' ? mb_substr($accountInput['smtp_pass'], 0, 255) : null,
                $accountInput['is_active'],
                $accountId,
            ]);
            $msg = 'Conexion verificada. Cuenta actualizada correctamente.';
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
            $pdo->prepare("
                INSERT INTO app_settings (setting_key, setting_value)
                VALUES ('mail_env_bootstrapped', 'disabled')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ")->execute();
            $pdo->prepare("DELETE FROM mail_accounts WHERE id = ?")->execute([$accountId]);
            $msg = 'Cuenta eliminada.';
        } elseif ($action === 'analyze_ai') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Correo invalido para analizar.');
            }

            $stMail = $pdo->prepare("SELECT * FROM email_inbox_messages WHERE id = ? LIMIT 1");
            $stMail->execute([$id]);
            $mail = $stMail->fetch();
            if (!$mail) {
                throw new RuntimeException('No se encontro el correo para analizar.');
            }

            $extraction = analyzeMailWithOllama($mail);
            saveAiExtraction($pdo, $id, $extraction);

            $msg = 'Analisis con IA completado. Revisa y confirma antes de avanzar.';
            $postSelectedId = $id;
        } elseif ($action === 'save_ai_extraction') {
            $id = (int)($_POST['id'] ?? 0);
            $decision = (string)($_POST['ai_decision'] ?? 'save');
            if ($id <= 0) {
                throw new RuntimeException('Correo invalido para guardar extraccion.');
            }

            $confidence = trim((string)($_POST['ai_confidence'] ?? ''));
            $confidenceValue = is_numeric($confidence) ? (float)$confidence : null;
            if ($confidenceValue !== null) {
                $confidenceValue = max(0, min(100, $confidenceValue));
            }

            $isOrderPost = (string)($_POST['ai_is_order'] ?? '');
            $isOrder = $isOrderPost === '' ? null : ((int)$isOrderPost === 1);

            $extraction = [
                'producto_solicitado' => trim((string)($_POST['ai_product'] ?? '')),
                'cantidad' => trim((string)($_POST['ai_quantity'] ?? '')),
                'fecha_entrega' => trim((string)($_POST['ai_delivery_date'] ?? '')),
                'cliente' => trim((string)($_POST['ai_customer'] ?? '')),
                'observaciones' => trim((string)($_POST['ai_observations'] ?? '')),
                'nivel_confianza' => $confidenceValue,
                'es_pedido' => $isOrder,
                'raw_json' => json_encode([
                    'producto_solicitado' => trim((string)($_POST['ai_product'] ?? '')),
                    'cantidad' => trim((string)($_POST['ai_quantity'] ?? '')),
                    'fecha_entrega' => trim((string)($_POST['ai_delivery_date'] ?? '')),
                    'cliente' => trim((string)($_POST['ai_customer'] ?? '')),
                    'observaciones' => trim((string)($_POST['ai_observations'] ?? '')),
                    'nivel_confianza' => $confidenceValue,
                    'es_pedido' => $isOrder,
                    'origen' => 'revision_manual',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
            saveAiExtraction($pdo, $id, $extraction);

            if ($decision === 'accept') {
                $note = trim((string)($_POST['review_note'] ?? ''));
                if ($note === '') {
                    $note = 'Validado por administrador desde prellenado IA.';
                }
                $pdo->prepare("UPDATE email_inbox_messages SET review_status='candidato_pedido', review_note=?, reviewed_at=NOW(), is_unseen=0 WHERE id=?")
                    ->execute([mb_substr($note, 0, 1500), $id]);
                $msg = 'Extraccion aceptada. El correo paso a candidato a pedido.';
            } elseif ($decision === 'discard') {
                $pdo->prepare("UPDATE email_inbox_messages SET review_status='descartado', reviewed_at=NOW() WHERE id=?")
                    ->execute([$id]);
                $msg = 'Extraccion descartada. El correo quedo marcado como descartado.';
            } else {
                $msg = 'Extraccion IA guardada para revision.';
            }

            $postSelectedId = $id;
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
        if (in_array($action, ['add_mail_account', 'update_mail_account'], true)) {
            $reopenMailModal = true;
            $showAdvancedPanel = true;
            $failedMailPost = $_POST;
            $mailModalError = $err;
            $reopenAddPanel = $action === 'add_mail_account';
        }
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$filterSeen = (string)($_GET['seen'] ?? 'all');
$filterReview = (string)($_GET['review'] ?? 'all');
$selectedId = (int)($_GET['id'] ?? 0);
if ($selectedId <= 0 && $postSelectedId > 0) {
    $selectedId = $postSelectedId;
}

$metrics = [
    'today' => 0,
    'unseen' => 0,
    'pending' => 0,
    'candidates' => 0,
];

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

$rows = [];
$selected = null;

if ($activeMailAccounts > 0) {
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

    $metrics['today'] = (int)$pdo->query("SELECT COUNT(*) FROM email_inbox_messages WHERE DATE(COALESCE(received_at, fetched_at)) = CURDATE()")->fetchColumn();
    $metrics['unseen'] = (int)$pdo->query("SELECT COUNT(*) FROM email_inbox_messages WHERE is_unseen = 1")->fetchColumn();
    $metrics['pending'] = (int)$pdo->query("SELECT COUNT(*) FROM email_inbox_messages WHERE review_status = 'pendiente'")->fetchColumn();
    $metrics['candidates'] = (int)$pdo->query("SELECT COUNT(*) FROM email_inbox_messages WHERE review_status = 'candidato_pedido'")->fetchColumn();

    $st = $pdo->prepare("SELECT * FROM email_inbox_messages $sqlWhere ORDER BY COALESCE(received_at, fetched_at) DESC LIMIT 200");
    $st->execute($params);
    $rows = $st->fetchAll();
}

if ($selectedId <= 0 && !empty($rows)) {
    $selectedId = (int)$rows[0]['id'];
}

if ($activeMailAccounts > 0 && $selectedId > 0) {
    $stOne = $pdo->prepare("SELECT * FROM email_inbox_messages WHERE id = ? LIMIT 1");
    $stOne->execute([$selectedId]);
    $selected = $stOne->fetch();
}

$failedMailJson = json_encode([
    'reopenModal' => $reopenMailModal,
    'reopenAddPanel' => $reopenAddPanel,
    'showAdvanced' => $showAdvancedPanel,
    'values' => [
        'action' => (string)($failedMailPost['action'] ?? ''),
        'account_id' => (string)($failedMailPost['account_id'] ?? '0'),
        'provider_key' => (string)($failedMailPost['provider_key'] ?? 'enterprise'),
        'provider' => (string)($failedMailPost['provider'] ?? ''),
        'email' => (string)($failedMailPost['email'] ?? ''),
        'imap_host' => (string)($failedMailPost['imap_host'] ?? ''),
        'imap_port' => (string)($failedMailPost['imap_port'] ?? '993'),
        'imap_user' => (string)($failedMailPost['imap_user'] ?? ''),
        'imap_mailbox' => (string)($failedMailPost['imap_mailbox'] ?? 'INBOX'),
        'imap_pass' => (string)($failedMailPost['imap_pass'] ?? ''),
        'imap_secure' => isset($failedMailPost['imap_secure']) ? 1 : 0,
        'imap_only_unseen' => isset($failedMailPost['imap_only_unseen']) ? 1 : 0,
        'smtp_host' => (string)($failedMailPost['smtp_host'] ?? ''),
        'smtp_port' => (string)($failedMailPost['smtp_port'] ?? '465'),
        'smtp_user' => (string)($failedMailPost['smtp_user'] ?? ''),
        'smtp_pass' => (string)($failedMailPost['smtp_pass'] ?? ''),
        'smtp_secure' => (string)($failedMailPost['smtp_secure'] ?? 'ssl'),
        'is_active' => isset($failedMailPost['is_active']) ? 1 : 0,
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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
        .btn-main:disabled, .btn-ok:disabled {
            opacity:.55; cursor:not-allowed; box-shadow:none; filter:saturate(.7);
        }
        .btn-main:disabled:hover { background:#0ea5e9; color:#fff; }
        .btn-ok:disabled:hover { background:#10b981; color:#fff; }
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
        .ai-card {
            border:1px solid rgba(14,165,233,.28); border-radius:12px;
            background:linear-gradient(135deg,rgba(8,47,73,.55),rgba(15,23,42,.98));
            padding:14px; margin:14px 0; position:relative; overflow:hidden;
        }
        .ai-card::after {
            content:''; position:absolute; right:-38px; top:-38px; width:110px; height:110px;
            border-radius:50%; background:rgba(14,165,233,.11); pointer-events:none;
        }
        .ai-head { position:relative; z-index:1; display:flex; justify-content:space-between; gap:12px; align-items:flex-start; margin-bottom:12px; }
        .ai-title { margin:0; font-size:14px; font-weight:900; color:#f8fafc; }
        .ai-subtitle { margin:4px 0 0; color:#9fb6d3; font-size:12px; }
        .ai-badge {
            display:inline-flex; align-items:center; gap:6px; padding:5px 9px; border-radius:999px;
            border:1px solid rgba(14,165,233,.35); background:rgba(14,165,233,.12);
            color:#7dd3fc; font-size:11px; font-weight:900; white-space:nowrap;
        }
        .ai-grid { position:relative; z-index:1; display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .ai-grid .full { grid-column:1 / -1; }
        .ai-field label { display:block; margin-bottom:5px; color:#dbeafe; font-size:11px; font-weight:800; }
        .ai-field input, .ai-field select, .ai-field textarea {
            width:100%; min-height:40px; border-radius:9px; border:1px solid #334155;
            background:#1e293b; color:#f8fafc; padding:8px 10px; font-size:13px;
        }
        .ai-field textarea { min-height:82px; resize:vertical; }
        .ai-actions { position:relative; z-index:1; display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; }
        .btn-ai {
            min-height:40px; border-radius:9px; padding:0 14px; border:1px solid rgba(125,211,252,.38);
            background:rgba(14,165,233,.14); color:#dff7ff; font-weight:900; font-size:12px;
            display:inline-flex; align-items:center; justify-content:center; gap:8px;
        }
        .btn-ai.primary { border:0; background:#0ea5e9; color:#fff; }
        .btn-ai.accept { border:0; background:#10b981; color:#fff; }
        .btn-ai.discard { border:0; background:#475569; color:#e2e8f0; }
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
        .mail-empty-state {
            margin-bottom:16px; border:1px solid rgba(245,158,11,.35); border-radius:12px;
            background:rgba(245,158,11,.08); padding:16px;
            display:flex; align-items:center; justify-content:space-between; gap:14px;
        }
        .mail-empty-state h3 { margin:0 0 5px; font-size:15px; font-weight:900; color:#fde68a; }
        .mail-empty-state p { margin:0; color:#cbd5e1; font-size:12px; }
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
        .mail-simple-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px; }
        .provider-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; grid-column:1 / -1; }
        .provider-option {
            position:relative; display:flex; align-items:center; gap:10px; min-height:58px;
            border:1px solid #26364d; border-radius:10px; padding:10px 12px;
            background:#111c31; color:#dbeafe; cursor:pointer; transition:.16s ease;
        }
        .provider-option input { position:absolute; opacity:0; pointer-events:none; }
        .provider-option i { color:#38bdf8; font-size:16px; width:18px; text-align:center; }
        .provider-option span { font-size:12px; font-weight:800; }
        .provider-option:has(input:checked) {
            border-color:#0ea5e9; background:rgba(14,165,233,.14); color:#fff;
            box-shadow:0 0 0 3px rgba(14,165,233,.08);
        }
        .mail-advanced-toggle {
            border:0; background:transparent; color:#7dd3fc; font-size:12px; font-weight:800;
            display:inline-flex; align-items:center; gap:8px; padding:0;
        }
        .mail-advanced-fields { display:none; margin-top:12px; padding-top:12px; border-top:1px solid #1e293b; }
        .mail-advanced-fields.show { display:block; }
        .mail-test-note {
            display:flex; align-items:center; gap:8px; color:#94a3b8; font-size:12px;
            padding:8px 10px; border:1px solid #1e293b; background:#0b1222; border-radius:9px;
        }
        @media (max-width: 980px) {
            .channel-overview { grid-template-columns:1fr; }
            .mail-empty-state { flex-direction:column; align-items:flex-start; }
            .metrics { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .content-grid.mail-grid { grid-template-columns:1fr; }
            .mail-toolbar { grid-template-columns:1fr; }
            .mail-page-header { align-items:flex-start; flex-direction:column; }
            .mail-list { max-height:45vh; }
            .mail-accounts-grid { grid-template-columns:1fr; }
            .mail-accounts-grid .wide,
            .mail-accounts-grid .full { grid-column:span 1; }
            .ai-grid { grid-template-columns:1fr; }
            .ai-grid .full { grid-column:span 1; }
            .ai-head { flex-direction:column; }
            .mail-simple-grid,
            .provider-grid { grid-template-columns:1fr; }
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
                <button class="btn-ok" type="submit" <?= $activeMailAccounts === 0 ? 'disabled' : '' ?>><i class="fa-solid fa-rotate"></i> Sincronizar IMAP</button>
                <button class="mail-config-chip" type="button" id="openMailAccountsModal">
                    <i class="fa-solid fa-envelope-circle-check"></i>
                    Cuentas
                    <strong><?= $activeMailAccounts ?>/2</strong>
                </button>
            </form>
        </div>

        <?php if ($activeMailAccounts === 0): ?>
            <section class="mail-empty-state">
                <div>
                    <h3>No hay cuentas de correo vinculadas</h3>
                    <p>Vincula una cuenta IMAP/SMTP para sincronizar nuevos correos de pedidos.</p>
                </div>
                <button class="btn-main" type="button" id="openMailAccountsModalEmpty"><i class="fa-solid fa-plus"></i> Vincular cuenta</button>
            </section>
        <?php endif; ?>

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
                        <div class="p-3 text-muted">
                            <?= $activeMailAccounts === 0 ? 'Vincula una cuenta para cargar correos.' : 'No hay correos para los filtros actuales.' ?>
                        </div>
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
                    <p class="text-muted">
                        <?= $activeMailAccounts === 0 ? 'Vincula una cuenta para revisar correos.' : 'Selecciona un correo de la bandeja.' ?>
                    </p>
                <?php else: ?>
                    <div class="detail-table">
                        <div class="detail-label">De</div><div class="detail-value"><?= htmlspecialchars((string)($selected['from_name'] ?: $selected['from_email'] ?: '-')) ?></div>
                        <div class="detail-label">Asunto</div><div class="detail-value"><?= htmlspecialchars((string)($selected['subject'] ?: '(Sin asunto)')) ?></div>
                        <div class="detail-label">Fecha</div><div class="detail-value"><?= htmlspecialchars((string)($selected['received_at'] ?: $selected['fetched_at'])) ?></div>
                        <div class="detail-label">Message-ID</div><div class="detail-value"><span class="msg-id-chip"><?= htmlspecialchars((string)$selected['message_id']) ?></span></div>
                    </div>
                    <div class="mail-body mb-3"><?= htmlspecialchars((string)($selected['body_text'] ?: strip_tags((string)$selected['body_html']) ?: '(Sin contenido legible)')) ?></div>

                    <?php
                        $hasAiExtraction = trim((string)($selected['ai_product'] ?? '')) !== ''
                            || trim((string)($selected['ai_quantity'] ?? '')) !== ''
                            || trim((string)($selected['ai_customer'] ?? '')) !== ''
                            || trim((string)($selected['ai_observations'] ?? '')) !== ''
                            || $selected['ai_confidence'] !== null;
                    ?>
                    <section class="ai-card">
                        <div class="ai-head">
                            <div>
                                <h4 class="ai-title"><i class="fa-solid fa-wand-magic-sparkles"></i> Prellenado inteligente con IA</h4>
                                <p class="ai-subtitle">La IA solo propone datos. El administrador confirma, corrige o descarta.</p>
                            </div>
                            <span class="ai-badge">
                                <i class="fa-solid fa-microchip"></i>
                                <?= htmlspecialchars(defined('OLLAMA_MODEL') ? OLLAMA_MODEL : 'llama3.2:3b') ?>
                            </span>
                        </div>

                        <form method="post" class="ai-actions">
                            <input type="hidden" name="action" value="analyze_ai">
                            <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>">
                            <button class="btn-ai primary" type="submit">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                                <?= $hasAiExtraction ? 'Analizar de nuevo' : 'Analizar con IA' ?>
                            </button>
                            <?php if (!$hasAiExtraction): ?>
                                <span class="ai-subtitle">Pendiente de extraccion. No se modifica inventario ni se crea pedido.</span>
                            <?php endif; ?>
                        </form>

                        <?php if ($hasAiExtraction): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="save_ai_extraction">
                                <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>">
                                <div class="ai-grid">
                                    <div class="ai-field">
                                        <label>Producto solicitado</label>
                                        <input name="ai_product" value="<?= htmlspecialchars((string)($selected['ai_product'] ?? '')) ?>" placeholder="Ej: alimento para pollo">
                                    </div>
                                    <div class="ai-field">
                                        <label>Cantidad</label>
                                        <input name="ai_quantity" value="<?= htmlspecialchars((string)($selected['ai_quantity'] ?? '')) ?>" placeholder="Ej: 20 toneladas">
                                    </div>
                                    <div class="ai-field">
                                        <label>Fecha de entrega</label>
                                        <input name="ai_delivery_date" value="<?= htmlspecialchars((string)($selected['ai_delivery_date'] ?? '')) ?>" placeholder="Ej: manana / 2026-05-25">
                                    </div>
                                    <div class="ai-field">
                                        <label>Cliente</label>
                                        <input name="ai_customer" value="<?= htmlspecialchars((string)($selected['ai_customer'] ?? '')) ?>" placeholder="Cliente detectado">
                                    </div>
                                    <div class="ai-field">
                                        <label>Nivel de confianza</label>
                                        <input name="ai_confidence" type="number" min="0" max="100" step="0.01" value="<?= htmlspecialchars((string)($selected['ai_confidence'] ?? '')) ?>" placeholder="0 a 100">
                                    </div>
                                    <div class="ai-field">
                                        <label>¿Parece pedido?</label>
                                        <select name="ai_is_order">
                                            <option value="" <?= $selected['ai_is_order'] === null ? 'selected' : '' ?>>Sin definir</option>
                                            <option value="1" <?= (string)($selected['ai_is_order'] ?? '') === '1' ? 'selected' : '' ?>>Si</option>
                                            <option value="0" <?= (string)($selected['ai_is_order'] ?? '') === '0' ? 'selected' : '' ?>>No</option>
                                        </select>
                                    </div>
                                    <div class="ai-field full">
                                        <label>Observaciones</label>
                                        <textarea name="ai_observations" placeholder="Notas detectadas por IA o corregidas por admin"><?= htmlspecialchars((string)($selected['ai_observations'] ?? '')) ?></textarea>
                                    </div>
                                    <div class="ai-field full">
                                        <label>Nota operativa para revision</label>
                                        <textarea name="review_note" placeholder="Ej: validar inventario, confirmar tonelaje, solicitar factura"><?= htmlspecialchars((string)($selected['review_note'] ?? '')) ?></textarea>
                                    </div>
                                </div>
                                <div class="ai-actions">
                                    <button class="btn-ai" name="ai_decision" value="save" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar correcciones</button>
                                    <button class="btn-ai accept" name="ai_decision" value="accept" type="submit"><i class="fa-solid fa-check"></i> Aceptar como candidato</button>
                                    <button class="btn-ai discard" name="ai_decision" value="discard" type="submit"><i class="fa-solid fa-ban"></i> Descartar</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </section>

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
        <?php if ($mailModalError !== ''): ?>
            <div class="alert-nexus alert-error" style="margin-bottom:12px;">
                <i class="fa-solid fa-circle-exclamation"></i>
                No se guardo la cuenta. <?= htmlspecialchars($mailModalError) ?>
            </div>
        <?php endif; ?>
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
                <input type="hidden" id="mailProvider" name="provider" value="Gmail">
                <input type="hidden" id="mailSmtpSecure" name="smtp_secure" value="ssl">
                <div class="mail-simple-grid">
                    <div class="provider-grid" aria-label="Proveedor de correo">
                        <label class="provider-option">
                            <input type="radio" name="provider_key" value="gmail" checked>
                            <i class="fa-brands fa-google"></i><span>Gmail</span>
                        </label>
                        <label class="provider-option">
                            <input type="radio" name="provider_key" value="outlook">
                            <i class="fa-brands fa-microsoft"></i><span>Outlook/Hotmail</span>
                        </label>
                        <label class="provider-option">
                            <input type="radio" name="provider_key" value="yahoo">
                            <i class="fa-solid fa-y"></i><span>Yahoo</span>
                        </label>
                        <label class="provider-option">
                            <input type="radio" name="provider_key" value="enterprise">
                            <i class="fa-solid fa-building"></i><span>Correo empresarial</span>
                        </label>
                    </div>
                    <input class="form-control search-input" id="mailEmail" name="email" type="email" placeholder="correo@dominio.com" required>
                    <input class="form-control search-input" id="mailImapPass" name="imap_pass" type="password" placeholder="Password o app password" required>
                    <div class="mail-test-note">
                        <i class="fa-solid fa-shield-halved"></i>
                        Se probara la conexion IMAP antes de guardar la cuenta.
                    </div>
                    <button class="mail-advanced-toggle" id="toggleMailAdvanced" type="button">
                        <i class="fa-solid fa-sliders"></i> Configuracion avanzada
                    </button>
                </div>
                <div class="mail-advanced-fields" id="mailAdvancedFields">
                    <div class="mail-accounts-grid">
                        <input class="form-control search-input" id="mailImapHost" name="imap_host" placeholder="IMAP Host" required>
                        <input class="form-control search-input" id="mailImapPort" name="imap_port" type="number" value="993" min="1" required>
                        <input class="form-control search-input" id="mailImapUser" name="imap_user" placeholder="IMAP User" required>
                        <input class="form-control search-input wide" id="mailImapMailbox" name="imap_mailbox" placeholder="IMAP Mailbox (INBOX)" value="INBOX">
                        <input class="form-control search-input" id="mailSmtpHost" name="smtp_host" placeholder="SMTP Host">
                        <input class="form-control search-input" id="mailSmtpPort" name="smtp_port" type="number" value="465" min="1">
                        <input class="form-control search-input" id="mailSmtpUser" name="smtp_user" placeholder="SMTP User">
                        <input class="form-control search-input full" id="mailSmtpPass" name="smtp_pass" type="password" placeholder="SMTP Password (opcional, usa el mismo si se deja vacio)">
                    </div>
                </div>
                <div class="mail-actions">
                    <label class="check-inline"><input type="checkbox" id="mailImapSecure" name="imap_secure" value="1" checked> IMAP SSL</label>
                    <label class="check-inline"><input type="checkbox" id="mailOnlyUnseen" name="imap_only_unseen" value="1" checked> Solo no leidos</label>
                    <label class="check-inline"><input type="checkbox" id="mailIsActive" name="is_active" value="1" checked> Activa</label>
                    <button class="btn-main" id="mailSubmitBtn" type="submit"><i class="fa-solid fa-plug-circle-check"></i> Probar y guardar</button>
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
    const openBtnEmpty = document.getElementById('openMailAccountsModalEmpty');
    const closeBtn = document.getElementById('closeMailAccountsModal');
    const modal = document.getElementById('mailAccountsModal');
    const backdrop = document.getElementById('mailAccountsBackdrop');
    const showAddBtn = document.getElementById('showAddMailPanel');
    const hideAddBtn = document.getElementById('hideAddMailPanel');
    const addPanel = document.getElementById('mailAddPanel');
    const addHeader = document.getElementById('mailAddHeader');
    const mailEmail = document.getElementById('mailEmail');
    const mailProvider = document.getElementById('mailProvider');
    const mailImapHost = document.getElementById('mailImapHost');
    const mailImapPort = document.getElementById('mailImapPort');
    const mailImapUser = document.getElementById('mailImapUser');
    const mailImapMailbox = document.getElementById('mailImapMailbox');
    const mailImapSecure = document.getElementById('mailImapSecure');
    const mailSmtpHost = document.getElementById('mailSmtpHost');
    const mailSmtpPort = document.getElementById('mailSmtpPort');
    const mailSmtpUser = document.getElementById('mailSmtpUser');
    const mailSmtpSecure = document.getElementById('mailSmtpSecure');
    const mailAdvancedFields = document.getElementById('mailAdvancedFields');
    const toggleMailAdvanced = document.getElementById('toggleMailAdvanced');
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
    const failedMailState = <?= $failedMailJson ?: '{}' ?>;

    const providerConfigs = {
        gmail: {
            label: 'Gmail',
            imapHost: 'imap.gmail.com',
            imapPort: '993',
            smtpHost: 'smtp.gmail.com',
            smtpPort: '465',
            smtpSecure: 'ssl',
        },
        outlook: {
            label: 'Outlook/Hotmail',
            imapHost: 'outlook.office365.com',
            imapPort: '993',
            smtpHost: 'smtp.office365.com',
            smtpPort: '587',
            smtpSecure: 'tls',
        },
        yahoo: {
            label: 'Yahoo',
            imapHost: 'imap.mail.yahoo.com',
            imapPort: '993',
            smtpHost: 'smtp.mail.yahoo.com',
            smtpPort: '465',
            smtpSecure: 'ssl',
        },
        enterprise: {
            label: 'Correo empresarial',
            imapHost: '',
            imapPort: '993',
            smtpHost: '',
            smtpPort: '465',
            smtpSecure: 'ssl',
        },
    };
    const domainConfigs = {
        'teotek.com.mx': {
            label: 'Teotek',
            imapHost: 'mail.teotek.com.mx',
            imapPort: '993',
            smtpHost: 'mail.teotek.com.mx',
            smtpPort: '465',
            smtpSecure: 'ssl',
        },
        'vast.com.mx': {
            label: 'Vast',
            imapHost: 'mail.vast.com.mx',
            imapPort: '993',
            smtpHost: 'mail.vast.com.mx',
            smtpPort: '465',
            smtpSecure: 'ssl',
        },
    };

    const emailDomain = () => {
        const value = (mailEmail?.value || '').trim();
        const at = value.lastIndexOf('@');
        return at >= 0 ? value.slice(at + 1).toLowerCase() : '';
    };

    const selectedProviderKey = () => {
        return document.querySelector('input[name="provider_key"]:checked')?.value || 'enterprise';
    };

    const applyProviderConfig = () => {
        const key = selectedProviderKey();
        const config = providerConfigs[key] || providerConfigs.enterprise;
        const domain = emailDomain();
        const domainConfig = domainConfigs[domain];
        const autoHost = key === 'enterprise' && domain ? `mail.${domain}` : '';

        const finalConfig = domainConfig || config;

        if (mailProvider) mailProvider.value = finalConfig.label;
        if (mailImapHost) mailImapHost.value = finalConfig.imapHost || autoHost;
        if (mailImapPort) mailImapPort.value = finalConfig.imapPort;
        if (mailImapUser) mailImapUser.value = (mailEmail?.value || '').trim();
        if (mailImapMailbox && !mailImapMailbox.value) mailImapMailbox.value = 'INBOX';
        if (mailSmtpHost) mailSmtpHost.value = finalConfig.smtpHost || autoHost;
        if (mailSmtpPort) mailSmtpPort.value = finalConfig.smtpPort;
        if (mailSmtpUser) mailSmtpUser.value = (mailEmail?.value || '').trim();
        if (mailSmtpSecure) mailSmtpSecure.value = finalConfig.smtpSecure;
        if (mailImapSecure) mailImapSecure.checked = true;
    };

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
    openBtnEmpty?.addEventListener('click', () => {
        openModal();
        editPanel?.classList.remove('show');
        addPanel?.classList.add('show');
        addHeader?.classList.add('show');
        mailAdvancedFields?.classList.remove('show');
        applyProviderConfig();
    });
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
        mailAdvancedFields?.classList.remove('show');
        applyProviderConfig();
        addPanel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    hideAddBtn?.addEventListener('click', () => {
        addPanel?.classList.remove('show');
        addHeader?.classList.remove('show');
        mailAdvancedFields?.classList.remove('show');
    });
    toggleMailAdvanced?.addEventListener('click', () => {
        mailAdvancedFields?.classList.toggle('show');
    });
    document.querySelectorAll('input[name="provider_key"]').forEach((input) => {
        input.addEventListener('change', applyProviderConfig);
    });
    mailEmail?.addEventListener('input', applyProviderConfig);

    const restoreFailedAdd = () => {
        const values = failedMailState?.values || {};
        const providerRadio = document.querySelector(`input[name="provider_key"][value="${values.provider_key || 'enterprise'}"]`);
        if (providerRadio) providerRadio.checked = true;
        if (mailEmail) mailEmail.value = values.email || '';
        if (mailProvider) mailProvider.value = values.provider || '';
        if (mailImapHost) mailImapHost.value = values.imap_host || '';
        if (mailImapPort) mailImapPort.value = values.imap_port || '993';
        if (mailImapUser) mailImapUser.value = values.imap_user || values.email || '';
        if (mailImapMailbox) mailImapMailbox.value = values.imap_mailbox || 'INBOX';
        const mailImapPass = document.getElementById('mailImapPass');
        if (mailImapPass) mailImapPass.value = values.imap_pass || '';
        if (mailSmtpHost) mailSmtpHost.value = values.smtp_host || '';
        if (mailSmtpPort) mailSmtpPort.value = values.smtp_port || '465';
        if (mailSmtpUser) mailSmtpUser.value = values.smtp_user || values.email || '';
        const mailSmtpPass = document.getElementById('mailSmtpPass');
        if (mailSmtpPass) mailSmtpPass.value = values.smtp_pass || '';
        if (mailSmtpSecure) mailSmtpSecure.value = values.smtp_secure || 'ssl';
        if (mailImapSecure) mailImapSecure.checked = Number(values.imap_secure ?? 1) === 1;
        const mailOnlyUnseen = document.getElementById('mailOnlyUnseen');
        const mailIsActive = document.getElementById('mailIsActive');
        if (mailOnlyUnseen) mailOnlyUnseen.checked = Number(values.imap_only_unseen ?? 1) === 1;
        if (mailIsActive) mailIsActive.checked = Number(values.is_active ?? 1) === 1;
    };

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

    if (failedMailState?.reopenModal) {
        openModal();
        if (failedMailState.reopenAddPanel) {
            editPanel?.classList.remove('show');
            addPanel?.classList.add('show');
            addHeader?.classList.add('show');
            if (failedMailState.showAdvanced) mailAdvancedFields?.classList.add('show');
            restoreFailedAdd();
            addPanel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
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
