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
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_delivery_date_value')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_delivery_date_value DATE NULL AFTER ai_delivery_date");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_delivery_time_value')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_delivery_time_value TIME NULL AFTER ai_delivery_date_value");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_delivery_type')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_delivery_type VARCHAR(40) NULL AFTER ai_delivery_time_value");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_customer')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_customer VARCHAR(255) NULL AFTER ai_delivery_type");
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
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_category')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_category VARCHAR(80) NULL AFTER ai_raw_json");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_processing_status')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_processing_status VARCHAR(80) NULL AFTER ai_category");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_alerts')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_alerts TEXT NULL AFTER ai_processing_status");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_stock_json')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_stock_json LONGTEXT NULL AFTER ai_alerts");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'ai_extracted_at')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN ai_extracted_at DATETIME NULL AFTER ai_stock_json");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'confirmed_pedido_id')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN confirmed_pedido_id INT NULL AFTER ai_extracted_at");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'confirmation_sent_at')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN confirmation_sent_at DATETIME NULL AFTER confirmed_pedido_id");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'confirmation_email_status')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN confirmation_email_status VARCHAR(30) NULL AFTER confirmation_sent_at");
    }
    if (!columnExists($pdo, 'email_inbox_messages', 'confirmation_email_error')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD COLUMN confirmation_email_error TEXT NULL AFTER confirmation_email_status");
    }
    if (!indexExists($pdo, 'email_inbox_messages', 'idx_email_inbox_review_status')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD INDEX idx_email_inbox_review_status (review_status)");
    }
    if (!indexExists($pdo, 'email_inbox_messages', 'idx_email_inbox_confirmed_pedido')) {
        $pdo->exec("ALTER TABLE email_inbox_messages ADD INDEX idx_email_inbox_confirmed_pedido (confirmed_pedido_id)");
    }

    if (tableExists($pdo, 'pedidos')) {
        if (!columnExists($pdo, 'pedidos', 'source_channel')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN source_channel VARCHAR(30) NOT NULL DEFAULT 'web' AFTER estado");
        }
        if (!columnExists($pdo, 'pedidos', 'source_email_id')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN source_email_id BIGINT UNSIGNED NULL AFTER source_channel");
        }
        if (!columnExists($pdo, 'pedidos', 'source_message_id')) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN source_message_id VARCHAR(255) NULL AFTER source_email_id");
        }
        if (!indexExists($pdo, 'pedidos', 'idx_pedidos_source_email')) {
            $pdo->exec("ALTER TABLE pedidos ADD INDEX idx_pedidos_source_email (source_email_id)");
        }
        if (!indexExists($pdo, 'pedidos', 'idx_pedidos_source_channel')) {
            $pdo->exec("ALTER TABLE pedidos ADD INDEX idx_pedidos_source_channel (source_channel)");
        }
    }

    if (tableExists($pdo, 'pedidos')) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pedido_historial_estados (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT NOT NULL,
                estado VARCHAR(40) NOT NULL,
                nota VARCHAR(255) NULL,
                usuario_id INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_phe_pedido (pedido_id),
                KEY idx_phe_estado (estado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    if (tableExists($pdo, 'usuarios') && tableExists($pdo, 'productos')) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cliente_producto_historial (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cliente_id INT NOT NULL,
                producto_id INT NOT NULL,
                cantidad_total INT NOT NULL DEFAULT 0,
                veces_pedido INT NOT NULL DEFAULT 0,
                ultima_fecha DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY ux_cliente_producto (cliente_id, producto_id),
                KEY idx_historial_cliente (cliente_id),
                KEY idx_historial_producto (producto_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    if (tableExists($pdo, 'productos')) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS inventario_movimientos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                producto_id INT NOT NULL,
                tipo ENUM('entrada','salida','ajuste','reserva','liberacion') NOT NULL,
                cantidad INT NOT NULL,
                stock_anterior INT NOT NULL,
                stock_nuevo INT NOT NULL,
                referencia_tipo VARCHAR(40) NULL,
                referencia_id INT NULL,
                nota VARCHAR(255) NULL,
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_im_producto (producto_id),
                KEY idx_im_fecha (created_at),
                KEY idx_im_ref (referencia_tipo, referencia_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    if (tableExists($pdo, 'pedidos')) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notificaciones_eventos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT NOT NULL,
                cliente_id INT NOT NULL,
                canal ENUM('interno','email','whatsapp','sms','llamada') NOT NULL,
                evento VARCHAR(60) NOT NULL,
                mensaje TEXT NOT NULL,
                estado ENUM('pendiente','enviado','error') NOT NULL DEFAULT 'pendiente',
                metadata_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_ne_pedido (pedido_id),
                KEY idx_ne_cliente (cliente_id),
                KEY idx_ne_estado (estado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS auditoria_eventos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NULL,
            rol VARCHAR(40) NULL,
            modulo VARCHAR(80) NOT NULL,
            accion VARCHAR(80) NOT NULL,
            referencia_tipo VARCHAR(40) NULL,
            referencia_id INT NULL,
            detalles TEXT NULL,
            ip_origen VARCHAR(80) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_auditoria_usuario (usuario_id),
            KEY idx_auditoria_modulo (modulo),
            KEY idx_auditoria_ref (referencia_tipo, referencia_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if (!tableExists($pdo, 'email_inbox_ai_items')) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS email_inbox_ai_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email_id BIGINT UNSIGNED NOT NULL,
                line_index INT NOT NULL DEFAULT 0,
                product_text VARCHAR(255) NOT NULL,
                product_id INT NULL,
                product_code VARCHAR(80) NULL,
                product_name VARCHAR(255) NULL,
                quantity_value DECIMAL(12,3) NULL,
                unit_slug VARCHAR(40) NOT NULL DEFAULT 'otro',
                unit_text VARCHAR(80) NULL,
                stock_available DECIMAL(12,3) NULL,
                stock_missing DECIMAL(12,3) NULL,
                stock_status VARCHAR(40) NOT NULL DEFAULT 'producto_no_encontrado',
                match_score INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_ai_items_email (email_id),
                KEY idx_ai_items_product (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
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

function compactMailText(string $text, int $maxLength = 3500): string
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

function normalizeAiExtractionLegacy(array $data): array
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

function normalizeAiConfidence($value): ?float
{
    $confidence = is_numeric($value) ? (float)$value : null;
    if ($confidence !== null && $confidence <= 1) {
        $confidence *= 100;
    }
    return $confidence !== null ? max(0, min(100, $confidence)) : null;
}

function aiText($value): string
{
    if ($value === null) return '';
    if (is_bool($value)) return $value ? 'Si' : 'No';
    if (is_scalar($value)) return trim((string)$value);
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $item) {
            $txt = aiText($item);
            if ($txt !== '') $parts[] = $txt;
        }
        return implode(', ', $parts);
    }
    return '';
}

function aiList($value): array
{
    if ($value === null || $value === '') return [];
    if (!is_array($value)) {
        $txt = aiText($value);
        return $txt !== '' ? [$txt] : [];
    }
    $out = [];
    foreach ($value as $item) {
        $txt = aiText($item);
        if ($txt !== '') $out[] = $txt;
    }
    return $out;
}

function normalizeAlertKey(string $value): string
{
    return aiSearchKey($value);
}

function uniqueAiAlerts(array $alerts): array
{
    $seen = [];
    $out = [];
    foreach ($alerts as $alert) {
        $alert = trim((string)$alert);
        if ($alert === '') continue;
        $key = normalizeAlertKey($alert);
        if ($key === '' || isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = $alert;
    }
    return $out;
}

function alertIsStockConfirmation(string $alert): bool
{
    $key = normalizeAlertKey($alert);
    return strpos($key, 'CONFIRMAR EXISTENCIA') !== false
        || strpos($key, 'CONFIRMAR DISPONIBILIDAD') !== false
        || strpos($key, 'EXISTENCIA COMPLETA') !== false;
}

function alertIsMissingQuantity(string $alert): bool
{
    $key = normalizeAlertKey($alert);
    return strpos($key, 'FALTAN CANTIDADES') !== false
        || strpos($key, 'FALTA CANTIDAD') !== false
        || strpos($key, 'CANTIDAD FALTANTE') !== false;
}

function alertIsProductAmbiguous(string $alert): bool
{
    $key = normalizeAlertKey($alert);
    return strpos($key, 'PRODUCTO AMBIGUO') !== false
        || strpos($key, 'PRODUCTOS AMBIGUOS') !== false
        || strpos($key, 'PRODUCTO NO CLARO') !== false;
}

function parseQuantityValue($value): array
{
    $text = aiText($value);
    $numeric = null;
    $unit = '';
    if (preg_match('/(-?\d+(?:[.,]\d+)?)/', $text, $m)) {
        $numeric = (float)str_replace(',', '.', $m[1]);
        $unit = trim(preg_replace('/^-?\d+(?:[.,]\d+)?\s*/', '', $text) ?? '');
    }
    return [$text, $numeric, $unit];
}

function normalizeUnitSlug(string $unitText): string
{
    $key = aiSearchKey($unitText);
    if ($key === '') return 'otro';
    if (in_array($key, ['PZ', 'PZA', 'PZAS', 'PIEZA', 'PIEZAS', 'UNIDAD', 'UNIDADES'], true)) return 'piezas';
    if (in_array($key, ['KG', 'KILO', 'KILOS', 'KILOGRAMO', 'KILOGRAMOS'], true)) return 'kg';
    if (in_array($key, ['TON', 'TONELADA', 'TONELADAS', 'T'], true)) return 'toneladas';
    if (in_array($key, ['CAJA', 'CAJAS', 'BOX'], true)) return 'cajas';
    if (in_array($key, ['BULTO', 'BULTOS', 'BAG', 'BAGS'], true)) return 'bultos';
    if (in_array($key, ['LT', 'L', 'LITRO', 'LITROS'], true)) return 'litros';
    return 'otro';
}

function inferAiProductsFromMailText(string $text): array
{
    $products = [];
    $source = trim($text);
    if ($source === '') return [];

    if (preg_match_all('/Producto\s*\d+\s*:\s*([^\r\n]+)\R\s*Cantidad\s*:\s*(\d+(?:[.,]\d+)?)\s*([^\r\n]*)/iu', $source, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $unit = trim((string)($m[3] ?? ''));
            $products[] = [
                'producto' => aiText($m[1] ?? ''),
                'cantidad_texto' => aiText($m[2] ?? ''),
                'cantidad_numero' => (float)str_replace(',', '.', (string)($m[2] ?? '')),
                'unidad' => normalizeUnitSlug($unit),
                'unidad_slug' => normalizeUnitSlug($unit),
                'confianza' => 96.0,
            ];
        }
    }

    if (!$products && preg_match_all('/(?:Solicito|Solicitamos|Necesito|Necesitamos|Requiero|Requerimos)\s+(\d+(?:[.,]\d+)?)\s+([[:alpha:]áéíóúÁÉÍÓÚñÑ]+)\s+de\s+(.+?)(?=\s+para\s+entrega|\s+para\s+el|\s+con\s+entrega|\.|\R|$)/iu', $source, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $unit = normalizeUnitSlug((string)($m[2] ?? ''));
            $products[] = [
                'producto' => aiText($m[3] ?? ''),
                'cantidad_texto' => aiText($m[1] ?? ''),
                'cantidad_numero' => (float)str_replace(',', '.', (string)($m[1] ?? '')),
                'unidad' => $unit,
                'unidad_slug' => $unit,
                'confianza' => 94.0,
            ];
        }
    }

    return array_values(array_filter($products, static fn($item) => trim((string)($item['producto'] ?? '')) !== ''));
}

function productLooksLikeSameRequest(string $shortName, string $longName): bool
{
    $short = aiSearchKey($shortName);
    $long = aiSearchKey($longName);
    return $short !== '' && $long !== '' && (strpos($long, $short) !== false || strpos($short, $long) !== false);
}

function enrichAiExtractionFromMailText(array $data, string $mailText): array
{
    $inferred = inferAiProductsFromMailText($mailText);
    if ($inferred) {
        if (empty($data['productos'])) {
            $data['productos'] = $inferred;
        } else {
            foreach ($inferred as $idx => $inferredItem) {
                $current = $data['productos'][$idx] ?? null;
                if (!$current) {
                    $data['productos'][] = $inferredItem;
                    continue;
                }
                $currentName = aiText($current['producto'] ?? '');
                $inferredName = aiText($inferredItem['producto'] ?? '');
                if ($inferredName !== '' && (strlen($inferredName) > strlen($currentName)) && productLooksLikeSameRequest($currentName, $inferredName)) {
                    $data['productos'][$idx]['producto'] = $inferredName;
                }
                if (($data['productos'][$idx]['cantidad_numero'] ?? null) === null && ($inferredItem['cantidad_numero'] ?? null) !== null) {
                    $data['productos'][$idx]['cantidad_numero'] = $inferredItem['cantidad_numero'];
                    $data['productos'][$idx]['cantidad_texto'] = $inferredItem['cantidad_texto'];
                }
                if (normalizeUnitSlug(aiText($data['productos'][$idx]['unidad'] ?? '')) === 'otro' && ($inferredItem['unidad_slug'] ?? '') !== 'otro') {
                    $data['productos'][$idx]['unidad'] = $inferredItem['unidad_slug'];
                    $data['productos'][$idx]['unidad_slug'] = $inferredItem['unidad_slug'];
                }
            }
        }
        $firstProduct = $data['productos'][0] ?? null;
        if ($firstProduct) {
            $data['producto_solicitado'] = aiText($firstProduct['producto'] ?? '');
            $data['cantidad'] = aiText($firstProduct['cantidad_texto'] ?? '');
        }
    }

    if (trim((string)($data['categoria'] ?? '')) === '' && ($data['es_pedido'] ?? null) === true) {
        $data['categoria'] = 'pedido_compra';
    }
    return $data;
}

function normalizeAiProducts(array $data): array
{
    $rawProducts = $data['productos'] ?? $data['items'] ?? $data['lineas'] ?? null;
    $products = [];

    if (is_array($rawProducts)) {
        foreach ($rawProducts as $item) {
            if (is_array($item)) {
                $name = aiText($item['producto'] ?? $item['nombre'] ?? $item['producto_solicitado'] ?? $item['descripcion'] ?? '');
                [$quantityText, $quantityNumber, $unitFromText] = parseQuantityValue($item['cantidad'] ?? $item['qty'] ?? '');
                $unit = aiText($item['unidad'] ?? $item['unidad_medida'] ?? $unitFromText);
                $confidence = normalizeAiConfidence($item['confianza'] ?? $item['nivel_confianza'] ?? null);
            } else {
                $name = aiText($item);
                $quantityText = '';
                $quantityNumber = null;
                $unit = '';
                $confidence = null;
            }
            if ($name !== '' || $quantityText !== '') {
                $products[] = [
                    'producto' => $name,
                    'cantidad_texto' => $quantityText,
                    'cantidad_numero' => $quantityNumber,
                    'unidad' => $unit,
                    'unidad_slug' => normalizeUnitSlug($unit),
                    'confianza' => $confidence,
                ];
            }
        }
    }

    if (!$products) {
        $productNames = aiList($data['producto_solicitado'] ?? $data['producto'] ?? '');
        $quantities = aiList($data['cantidad'] ?? '');
        $max = max(count($productNames), count($quantities));
        for ($i = 0; $i < $max; $i++) {
            [$quantityText, $quantityNumber, $unit] = parseQuantityValue($quantities[$i] ?? '');
            $products[] = [
                'producto' => $productNames[$i] ?? '',
                'cantidad_texto' => $quantityText,
                'cantidad_numero' => $quantityNumber,
                'unidad' => $unit,
                'unidad_slug' => normalizeUnitSlug($unit),
                'confianza' => null,
            ];
        }
    }

    return array_values(array_filter($products, static function (array $item): bool {
        return trim((string)$item['producto']) !== '' || trim((string)$item['cantidad_texto']) !== '';
    }));
}

function normalizeAiDateValue($value): string
{
    $value = trim((string)$value);
    if ($value === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : '';
}

function normalizeAiTimeValue($value): string
{
    $value = trim((string)$value);
    if ($value === '') return '';
    if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $value, $m)) {
        return sprintf('%02d:%02d:%02d', (int)$m[1], (int)$m[2], isset($m[3]) ? (int)$m[3] : 0);
    }
    $ts = strtotime($value);
    return $ts ? date('H:i:s', $ts) : '';
}

function spanishWeekdayIndex(string $text): ?int
{
    $key = aiSearchKey($text);
    $days = [
        'LUNES' => 1,
        'MARTES' => 2,
        'MIERCOLES' => 3,
        'JUEVES' => 4,
        'VIERNES' => 5,
        'SABADO' => 6,
        'DOMINGO' => 7,
    ];
    foreach ($days as $day => $idx) {
        if (preg_match('/\b' . preg_quote($day, '/') . '\b/', $key)) {
            return $idx;
        }
    }
    return null;
}

function resolveSpanishRelativeDate(string $text, ?string $baseDate = null): string
{
    $text = trim($text);
    if ($text === '') return '';

    $baseTs = $baseDate ? strtotime($baseDate) : time();
    if (!$baseTs) $baseTs = time();
    $base = new DateTimeImmutable(date('Y-m-d', $baseTs));
    $key = aiSearchKey($text);

    if (preg_match('/\bHOY\b/', $key)) {
        return $base->format('Y-m-d');
    }
    if (preg_match('/\bPASADO MANANA\b/', $key)) {
        return $base->modify('+2 days')->format('Y-m-d');
    }
    if (preg_match('/\bMANANA\b/', $key)) {
        return $base->modify('+1 day')->format('Y-m-d');
    }

    $weekday = spanishWeekdayIndex($text);
    if ($weekday !== null) {
        $baseWeekday = (int)$base->format('N');
        $delta = $weekday - $baseWeekday;
        if (preg_match('/\b(PROXIMA|PROXIMO|SIGUIENTE)\b/', $key)) {
            if ($delta <= 0) $delta += 7;
        } elseif (!preg_match('/\bESTA SEMANA\b/', $key) && $delta < 0) {
            $delta += 7;
        }
        return $base->modify(($delta >= 0 ? '+' : '') . $delta . ' days')->format('Y-m-d');
    }

    return '';
}

function isRelativeDeliveryText(string $text): bool
{
    $key = aiSearchKey($text);
    return (bool)preg_match('/\b(ESTA SEMANA|HOY|MANANA|PASADO MANANA|LUNES|MARTES|MIERCOLES|JUEVES|VIERNES|SABADO|DOMINGO|PROXIMA|PROXIMO|SIGUIENTE)\b/', $key);
}

function normalizeAiDelivery(array $data, ?string $baseDate = null): array
{
    $delivery = $data['fecha_entrega'] ?? $data['fecha_de_entrega'] ?? $data['entrega'] ?? [];
    if (is_array($delivery)) {
        $text = aiText($delivery['texto_original'] ?? $delivery['texto'] ?? '');
        $date = normalizeAiDateValue($delivery['fecha'] ?? $delivery['fecha_normalizada'] ?? $delivery['date'] ?? '');
        $time = normalizeAiTimeValue($delivery['hora'] ?? $delivery['hora_normalizada'] ?? $delivery['time'] ?? '');
        $type = aiText($delivery['tipo'] ?? $delivery['type'] ?? '');
    } else {
        $text = aiText($delivery);
        $date = normalizeAiDateValue($delivery);
        $time = normalizeAiTimeValue('');
        $type = '';
    }
    if ($date === '' && $text !== '') {
        $date = resolveSpanishRelativeDate($text, $baseDate);
    }
    if ($date !== '' && $type === '' && isRelativeDeliveryText($text)) {
        $type = 'fecha_relativa';
    }
    $typeKey = aiSearchKey($type);
    if (!in_array($typeKey, ['FECHA EXACTA', 'FECHA RELATIVA', 'VENTANA', 'SIN FECHA'], true)) {
        if ($date !== '' && preg_match('/\b(esta semana|manana|mañana|hoy|viernes|lunes|martes|miercoles|miércoles|jueves|sabado|sábado|domingo)\b/i', $text)) {
            $type = 'fecha_relativa';
        } elseif ($date !== '') {
            $type = 'fecha_exacta';
        } else {
            $type = 'sin_fecha';
        }
    } else {
        $type = strtolower(str_replace(' ', '_', $typeKey));
    }
    return [
        'tipo' => $type,
        'texto_original' => $text,
        'fecha' => $date,
        'hora' => $time,
    ];
}

function normalizeAiExtraction(array $data, ?string $baseDate = null): array
{
    $confidence = normalizeAiConfidence($data['nivel_confianza'] ?? $data['confianza_global'] ?? $data['confianza'] ?? $data['confidence'] ?? null);
    $isOrder = $data['es_pedido'] ?? $data['is_order'] ?? null;
    if (is_string($isOrder)) {
        $isOrder = in_array(mb_strtolower($isOrder), ['si', 'sÃ­', 'true', '1', 'yes'], true);
    } elseif ($isOrder !== null) {
        $isOrder = (bool)$isOrder;
    }

    $products = normalizeAiProducts($data);
    $firstProduct = $products[0] ?? ['producto' => '', 'cantidad_texto' => ''];
    $delivery = normalizeAiDelivery($data, $baseDate);
    $fieldConfidence = $data['confianza_campos'] ?? $data['field_confidence'] ?? [];
    if (!is_array($fieldConfidence)) $fieldConfidence = [];
    $alerts = aiList($data['alertas'] ?? $data['riesgos'] ?? []);
    foreach (aiList($data['faltantes'] ?? []) as $missing) {
        $alerts[] = 'Falta: ' . $missing;
    }
    $alerts = uniqueAiAlerts($alerts);

    return [
        'producto_solicitado' => aiText($firstProduct['producto'] ?? ''),
        'cantidad' => aiText($firstProduct['cantidad_texto'] ?? ''),
        'fecha_entrega' => $delivery['texto_original'] ?: $delivery['fecha'],
        'fecha_entrega_fecha' => $delivery['fecha'],
        'fecha_entrega_hora' => $delivery['hora'],
        'fecha_entrega_tipo' => $delivery['tipo'],
        'cliente' => aiText(is_array($data['cliente'] ?? null) ? (($data['cliente']['nombre'] ?? '') ?: $data['cliente']) : ($data['cliente'] ?? '')),
        'observaciones' => aiText($data['observaciones'] ?? $data['notas'] ?? ''),
        'nivel_confianza' => $confidence,
        'es_pedido' => $isOrder,
        'categoria' => aiText($data['categoria'] ?? $data['category'] ?? ''),
        'estado_ia' => aiText($data['estado_ia'] ?? $data['estado'] ?? $data['processing_status'] ?? ''),
        'alertas' => $alerts,
        'confianza_campos' => $fieldConfidence,
        'productos' => $products,
    ];
}

function aiSearchKey(string $value): string
{
    $plain = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($plain) && $plain !== '') {
        $value = $plain;
    }
    $value = mb_strtoupper(trim($value));
    $value = strtr($value, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N']);
    $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

function scoreProductMatch(string $needle, array $product): int
{
    $needleKey = aiSearchKey($needle);
    if ($needleKey === '') return 0;
    $haystack = aiSearchKey(($product['codigo'] ?? '') . ' ' . ($product['nombre'] ?? ''));
    if ($haystack === '') return 0;
    if ($needleKey === aiSearchKey((string)($product['codigo'] ?? '')) || $needleKey === aiSearchKey((string)($product['nombre'] ?? ''))) {
        return 100;
    }
    if (strpos($haystack, $needleKey) !== false || strpos($needleKey, $haystack) !== false) {
        return 88;
    }
    $needleTokens = array_values(array_filter(explode(' ', $needleKey), static fn($t) => strlen($t) >= 2));
    if (!$needleTokens) return 0;
    $hits = 0;
    foreach ($needleTokens as $token) {
        if (strpos($haystack, $token) !== false) $hits++;
    }
    $coverage = $hits / max(1, count($needleTokens));
    return (int)round($coverage * 82);
}

function bestProductMatch(array $catalog, string $requested): ?array
{
    $best = null;
    $bestScore = 0;
    foreach ($catalog as $product) {
        $score = scoreProductMatch($requested, $product);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $product;
        }
    }
    if (!$best || $bestScore < 45) return null;
    $best['match_score'] = $bestScore;
    return $best;
}

function checkAiStock(PDO $pdo, array $products): array
{
    $catalog = $pdo->query("
        SELECT id, codigo, nombre, stock, unidad_medida, activo
        FROM productos
        WHERE activo = 1
        ORDER BY nombre ASC
    ")->fetchAll();

    $items = [];
    $covered = 0;
    $partial = 0;
    $missing = 0;
    $unknown = 0;

    foreach ($products as $item) {
        $requested = aiText($item['producto'] ?? '');
        $qty = $item['cantidad_numero'] ?? null;
        if ($qty === null) {
            [, $qty] = parseQuantityValue($item['cantidad_texto'] ?? '');
        }
        $match = bestProductMatch($catalog, $requested);
        $stock = $match ? (float)$match['stock'] : null;
        $status = 'producto_no_encontrado';
        if ($match && $qty === null) {
            $status = 'cantidad_faltante';
            $unknown++;
        } elseif ($match && $stock <= 0) {
            $status = 'sin_stock';
            $partial++;
        } elseif ($match && $stock >= (float)$qty) {
            $status = 'cubre_completo';
            $covered++;
        } elseif ($match) {
            $status = 'parcial';
            $partial++;
        } else {
            $missing++;
        }

        $items[] = [
            'producto_solicitado' => $requested,
            'cantidad_solicitada' => $qty,
            'cantidad_texto' => aiText($item['cantidad_texto'] ?? ''),
            'unidad_solicitada' => aiText($item['unidad'] ?? ''),
            'unidad_slug' => aiText($item['unidad_slug'] ?? normalizeUnitSlug(aiText($item['unidad'] ?? ''))),
            'status' => $status,
            'producto_id' => $match['id'] ?? null,
            'producto_catalogo' => $match['nombre'] ?? '',
            'codigo' => $match['codigo'] ?? '',
            'stock_disponible' => $stock,
            'unidad_stock' => $match['unidad_medida'] ?? '',
            'faltante' => ($match && $qty !== null) ? max(0, (float)$qty - $stock) : null,
            'match_score' => $match['match_score'] ?? 0,
        ];
    }

    $overall = 'sin_productos';
    if ($products) {
        if ($missing > 0 || $partial > 0) $overall = 'no_cubre_completo';
        elseif ($unknown > 0) $overall = 'requiere_cantidad';
        else $overall = 'cubre_completo';
    }

    return [
        'estado' => $overall,
        'total_productos' => count($products),
        'cubiertos' => $covered,
        'parciales' => $partial,
        'no_encontrados' => $missing,
        'cantidad_desconocida' => $unknown,
        'items' => $items,
    ];
}

function localAiConfidence(array $data, array $stock): float
{
    $score = 35.0;
    if (($data['es_pedido'] ?? null) === true) $score += 15;
    if (trim((string)($data['cliente'] ?? '')) !== '') $score += 10;
    if (!empty($data['productos'])) $score += 15;
    $allProductsHaveQty = true;
    foreach (($data['productos'] ?? []) as $product) {
        $hasName = trim((string)($product['producto'] ?? '')) !== '';
        $hasQty = ($product['cantidad_numero'] ?? null) !== null || trim((string)($product['cantidad_texto'] ?? '')) !== '';
        if (!$hasName || !$hasQty) {
            $allProductsHaveQty = false;
            break;
        }
    }
    if ($allProductsHaveQty && !empty($data['productos'])) $score += 10;
    if (trim((string)($data['fecha_entrega'] ?? '')) !== '') $score += 6;
    if (($stock['estado'] ?? '') === 'cubre_completo') $score += 12;
    if (($stock['estado'] ?? '') === 'no_cubre_completo') $score -= 15;
    if (preg_match('/\b(esta semana|manana|mañana|hoy|viernes|lunes|martes|miercoles|miércoles|jueves|sabado|sábado|domingo)\b/i', (string)($data['fecha_entrega'] ?? ''))) {
        $score -= 6;
    }
    return max(0, min(100, $score));
}

function reconcileAiExtractionWithStock(array $data, array $stock): array
{
    $hasProducts = !empty($data['productos']);
    $hasCustomer = trim((string)($data['cliente'] ?? '')) !== '';
    $hasQuantities = true;
    foreach (($data['productos'] ?? []) as $product) {
        if (($product['cantidad_numero'] ?? null) === null && trim((string)($product['cantidad_texto'] ?? '')) === '') {
            $hasQuantities = false;
            break;
        }
    }
    $allProductsMatched = true;
    foreach (($stock['items'] ?? []) as $stockItem) {
        $status = (string)($stockItem['status'] ?? '');
        $matchScore = (int)($stockItem['match_score'] ?? 0);
        if (!in_array($status, ['cubre_completo', 'cantidad_faltante'], true) || $matchScore < 70) {
            $allProductsMatched = false;
            break;
        }
    }

    $alerts = uniqueAiAlerts($data['alertas'] ?? []);
    $stockCovers = ($stock['estado'] ?? '') === 'cubre_completo';
    if ($stockCovers) {
        $alerts = array_values(array_filter($alerts, static function ($alert) use ($hasQuantities, $allProductsMatched) {
            $alert = (string)$alert;
            if (alertIsStockConfirmation($alert)) return false;
            if ($hasQuantities && alertIsMissingQuantity($alert)) return false;
            if ($allProductsMatched && alertIsProductAmbiguous($alert)) return false;
            return true;
        }));
        $obs = trim((string)($data['observaciones'] ?? ''));
        if ($obs === '') {
            $data['observaciones'] = 'Existencia completa validada contra inventario.';
        } elseif (stripos(aiSearchKey($obs), 'EXISTENCIA') === false) {
            $data['observaciones'] = $obs . "\nExistencia completa validada contra inventario.";
        }
    }

    if (($data['es_pedido'] ?? null) === true && $hasProducts && $hasCustomer && $hasQuantities) {
        $data['estado_ia'] = $stockCovers ? 'listo_para_capturar' : 'requiere_revision';
    } elseif (($data['es_pedido'] ?? null) === true) {
        $data['estado_ia'] = 'incompleto';
    }

    if (($data['nivel_confianza'] ?? null) === null || (float)$data['nivel_confianza'] <= 1) {
        $data['nivel_confianza'] = localAiConfidence($data, $stock);
    }
    $data['nivel_confianza'] = max(1, min(100, (float)$data['nivel_confianza']));

    $data['alertas'] = $alerts;
    return $data;
}

function saveAiItemRows(PDO $pdo, int $emailId, array $stock): void
{
    $pdo->prepare("DELETE FROM email_inbox_ai_items WHERE email_id = ?")->execute([$emailId]);
    $items = is_array($stock['items'] ?? null) ? $stock['items'] : [];
    if (!$items) return;

    $ins = $pdo->prepare("
        INSERT INTO email_inbox_ai_items
            (email_id, line_index, product_text, product_id, product_code, product_name,
             quantity_value, unit_slug, unit_text, stock_available, stock_missing, stock_status, match_score)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $idx => $item) {
        $ins->execute([
            $emailId,
            $idx + 1,
            mb_substr((string)($item['producto_solicitado'] ?? ''), 0, 255),
            !empty($item['producto_id']) ? (int)$item['producto_id'] : null,
            ($item['codigo'] ?? '') !== '' ? mb_substr((string)$item['codigo'], 0, 80) : null,
            ($item['producto_catalogo'] ?? '') !== '' ? mb_substr((string)$item['producto_catalogo'], 0, 255) : null,
            ($item['cantidad_solicitada'] ?? null) !== null ? (float)$item['cantidad_solicitada'] : null,
            mb_substr((string)($item['unidad_slug'] ?? 'otro'), 0, 40),
            ($item['unidad_solicitada'] ?? '') !== '' ? mb_substr((string)$item['unidad_solicitada'], 0, 80) : null,
            ($item['stock_disponible'] ?? null) !== null ? (float)$item['stock_disponible'] : null,
            ($item['faltante'] ?? null) !== null ? (float)$item['faltante'] : null,
            mb_substr((string)($item['status'] ?? 'producto_no_encontrado'), 0, 40),
            (int)($item['match_score'] ?? 0),
        ]);
    }
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

function ollamaHttpBaseUrl(): string
{
    $base = defined('OLLAMA_BASE_URL') ? OLLAMA_BASE_URL : 'http://localhost:11434';
    $parts = parse_url($base);
    if (!is_array($parts)) return rtrim($base, '/');
    $scheme = $parts['scheme'] ?? 'http';
    $host = $parts['host'] ?? '127.0.0.1';
    if ($host === 'localhost') {
        $host = '127.0.0.1';
    }
    $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
    $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
    return $scheme . '://' . $host . $port . $path;
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
    @set_time_limit(90);

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
        'Eres un extractor local para pedidos de alimentos, medicamentos o insumos veterinarios.',
        'Tu unica tarea es leer correos y regresar JSON estricto, sin Markdown.',
        'No inventes datos. Si falta informacion usa null, cadena vacia o arreglo vacio.',
        'No ejecutes acciones, no descuentes inventario y no crees pedidos.',
        'No decidas inventario ni existencia. El sistema empatara contra catalogo y validara stock despues.',
        'El humano administrador validara todo antes de avanzar.',
        'Clasifica el correo en una de estas categorias: pedido_compra, cotizacion, consulta_inventario, seguimiento, confirmacion_pago, queja, otro.',
        'Define estado_ia como: listo_para_capturar, requiere_revision, incompleto, no_es_pedido.',
        'productos siempre debe ser un arreglo y debe conservar el orden del correo.',
        'Cada producto debe tener exactamente estas claves: producto, cantidad, unidad, confianza.',
        'producto debe ser solo el nombre/codigo solicitado, sin incluir la palabra Producto, sin incluir cantidad y sin inventar codigo.',
        'cantidad debe ser numerica si aparece. Si el correo dice "Cantidad: 15 piezas", cantidad debe ser 15 y unidad debe ser piezas.',
        'unidad debe normalizarse a una de estas opciones si aplica: piezas, kg, toneladas, cajas, bultos, litros, otro.',
        'Incluye confianza_campos con claves cliente, productos, cantidades, entrega, categoria; cada una de 0 a 100.',
        'nivel_confianza debe ser global de 0 a 100 y penalizar solo datos faltantes, no la necesidad de validar inventario.',
        'entrega debe ser objeto con texto_original, tipo, fecha y hora. tipo solo puede ser fecha_exacta, fecha_relativa, ventana o sin_fecha.',
        'fecha usa YYYY-MM-DD o null; hora usa HH:MM:SS o null.',
        'Si el correo dice una fecha relativa como "viernes de esta semana", calcula fecha usando Fecha correo y Fecha actual del sistema. Si no hay hora, usa null.',
        'Para "esta semana", usa la semana calendario de la Fecha correo. Ejemplo: si Fecha correo es lunes y dice viernes de esta semana, fecha es el viernes de esa misma semana.',
        'alertas debe incluir solo faltantes reales del texto: faltan cantidades, producto ambiguo, fecha no interpretable o correo no pedido.',
        'No agregues alertas de confirmar existencia ni stock; eso lo calcula el sistema.',
        'observaciones debe resumir notas operativas como confirmar disponibilidad, factura, flete, horarios o urgencia.',
        'Nunca regreses producto_solicitado o cantidad como arreglos planos; usa productos.',
        'Formato exacto esperado: {"categoria":"","estado_ia":"","es_pedido":true,"cliente":{"nombre":"","confianza":0},"productos":[{"producto":"","cantidad":0,"unidad":"","confianza":0}],"entrega":{"texto_original":"","tipo":"sin_fecha","fecha":null,"hora":null,"confianza":0},"observaciones":"","alertas":[],"faltantes":[],"confianza_campos":{"cliente":0,"productos":0,"cantidades":0,"entrega":0,"categoria":0},"nivel_confianza":0}',
    ]);

    $userPrompt = "Asunto: " . (string)($mail['subject'] ?? '') . "\n"
        . "Remitente: " . (string)($mail['from_name'] ?: $mail['from_email'] ?: '') . "\n"
        . "Fecha correo: " . (string)($mail['received_at'] ?: $mail['fetched_at'] ?: '') . "\n\n"
        . "Fecha actual del sistema: " . date('Y-m-d') . "\n\n"
        . "Cuerpo del correo:\n" . $body;

    $payload = [
        'model' => OLLAMA_MODEL,
        'stream' => false,
        'format' => 'json',
        'options' => [
            'temperature' => 0.1,
            'num_predict' => 700,
            'num_ctx' => 4096,
        ],
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
    ];

    $bodyJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($bodyJson === false) {
        throw new RuntimeException('No se pudo preparar la solicitud JSON para Ollama.');
    }

    $url = ollamaHttpBaseUrl() . '/api/chat';
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($bodyJson) . "\r\nConnection: close\r\n",
            'content' => $bodyJson,
            'timeout' => 55,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        $last = error_get_last();
        $detail = is_array($last) && !empty($last['message']) ? ' Detalle: ' . $last['message'] : '';
        throw new RuntimeException('Ollama tardo demasiado o no respondio en ' . ollamaHttpBaseUrl() . '. Intenta de nuevo con un correo mas corto o reinicia Ollama.' . $detail);
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
    $normalized = normalizeAiExtraction($json, (string)($mail['received_at'] ?: $mail['fetched_at'] ?: ''));
    $normalized = enrichAiExtractionFromMailText($normalized, $body);
    $normalized['raw_json'] = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $normalized;
}

function saveAiExtraction(PDO $pdo, int $id, array $data): void
{
    $stock = checkAiStock($pdo, $data['productos'] ?? []);
    $data = reconcileAiExtractionWithStock($data, $stock);
    $confidence = $data['nivel_confianza'];
    $isOrder = $data['es_pedido'];
    $raw = $data;
    $raw['stock'] = $stock;

    $pdo->prepare("
        UPDATE email_inbox_messages
        SET ai_product = ?,
            ai_quantity = ?,
            ai_delivery_date = ?,
            ai_delivery_date_value = ?,
            ai_delivery_time_value = ?,
            ai_delivery_type = ?,
            ai_customer = ?,
            ai_observations = ?,
            ai_confidence = ?,
            ai_is_order = ?,
            ai_model = ?,
            ai_raw_json = ?,
            ai_category = ?,
            ai_processing_status = ?,
            ai_alerts = ?,
            ai_stock_json = ?,
            ai_extracted_at = NOW()
        WHERE id = ?
    ")->execute([
        $data['producto_solicitado'] !== '' ? mb_substr($data['producto_solicitado'], 0, 255) : null,
        $data['cantidad'] !== '' ? mb_substr($data['cantidad'], 0, 120) : null,
        $data['fecha_entrega'] !== '' ? mb_substr($data['fecha_entrega'], 0, 120) : null,
        ($data['fecha_entrega_fecha'] ?? '') !== '' ? $data['fecha_entrega_fecha'] : null,
        ($data['fecha_entrega_hora'] ?? '') !== '' ? $data['fecha_entrega_hora'] : null,
        ($data['fecha_entrega_tipo'] ?? '') !== '' ? mb_substr((string)$data['fecha_entrega_tipo'], 0, 40) : null,
        $data['cliente'] !== '' ? mb_substr($data['cliente'], 0, 255) : null,
        $data['observaciones'] !== '' ? mb_substr($data['observaciones'], 0, 2500) : null,
        $confidence !== null ? round((float)$confidence, 2) : null,
        $isOrder === null ? null : ($isOrder ? 1 : 0),
        OLLAMA_MODEL,
        json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $data['categoria'] !== '' ? mb_substr((string)$data['categoria'], 0, 80) : null,
        $data['estado_ia'] !== '' ? mb_substr((string)$data['estado_ia'], 0, 80) : null,
        !empty($data['alertas']) ? mb_substr(implode("\n", $data['alertas']), 0, 2500) : null,
        json_encode($stock, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $id,
    ]);
    saveAiItemRows($pdo, $id, $stock);
}

function emailInboxSmtpAccount(PDO $pdo, array $mail): array
{
    $sourceEmail = '';
    $sourceMailbox = (string)($mail['source_mailbox'] ?? '');
    if (preg_match('/^([^:]+):/', $sourceMailbox, $m)) {
        $sourceEmail = trim((string)$m[1]);
    }

    if ($sourceEmail !== '') {
        $st = $pdo->prepare("
            SELECT * FROM mail_accounts
            WHERE is_active = 1
              AND LOWER(email) = LOWER(?)
              AND COALESCE(smtp_host, '') <> ''
              AND COALESCE(smtp_user, '') <> ''
              AND COALESCE(smtp_pass, '') <> ''
            LIMIT 1
        ");
        $st->execute([$sourceEmail]);
        $account = $st->fetch();
        if ($account) return $account;
    }

    $st = $pdo->query("
        SELECT * FROM mail_accounts
        WHERE is_active = 1
          AND COALESCE(smtp_host, '') <> ''
          AND COALESCE(smtp_user, '') <> ''
          AND COALESCE(smtp_pass, '') <> ''
        ORDER BY id ASC
        LIMIT 1
    ");
    $account = $st->fetch();
    if (!$account) {
        throw new RuntimeException('No hay cuenta SMTP activa para enviar la confirmacion.');
    }
    return $account;
}

function smtpReadLine($socket): string
{
    $line = fgets($socket, 2048);
    return $line === false ? '' : rtrim($line, "\r\n");
}

function smtpReadResponse($socket): string
{
    $response = '';
    do {
        $line = smtpReadLine($socket);
        if ($line === '') break;
        $response .= ($response !== '' ? "\n" : '') . $line;
    } while (isset($line[3]) && $line[3] === '-');
    return $response;
}

function smtpExpect($socket, array $codes, string $context): string
{
    $response = smtpReadResponse($socket);
    $code = substr($response, 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException($context . ': ' . $response);
    }
    return $response;
}

function smtpCommand($socket, string $command, array $codes, string $context): string
{
    fwrite($socket, $command . "\r\n");
    return smtpExpect($socket, $codes, $context);
}

function smtpSafeMessageBody(string $body): string
{
    $body = smtpNormalizeLineEndings($body);
    return preg_replace('/(^|\r\n)\./', '$1..', $body) ?? $body;
}

function smtpNormalizeLineEndings(string $value): string
{
    return str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $value));
}

function smtpBase64MessageBody(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    return rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
}

function smtpEncodedHeader(string $value): string
{
    $value = trim(preg_replace('/[\r\n]+/', ' ', $value) ?? $value);
    if ($value === '') return '';

    if (function_exists('mb_encode_mimeheader')) {
        $encoded = mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
        return str_replace("\r\n ", "\r\n\t", $encoded);
    }

    $chunks = str_split(base64_encode($value), 48);
    return implode("\r\n\t", array_map(static fn($chunk) => '=?UTF-8?B?' . $chunk . '?=', $chunks));
}

function smtpFoldHeader(string $name, string $value, int $limit = 76): string
{
    $value = trim(preg_replace('/[\r\n]+/', ' ', $value) ?? $value);
    $line = $name . ': ' . $value;
    if (strlen($line) <= $limit) return $line;

    $folded = $name . ':';
    $current = '';
    foreach (preg_split('/\s+/', $value) ?: [] as $word) {
        if ($word === '') continue;
        if ($current !== '' && strlen($current . ' ' . $word) > $limit - 1) {
            $folded .= "\r\n\t" . $current;
            $current = $word;
        } else {
            $current = $current === '' ? $word : $current . ' ' . $word;
        }
    }
    if ($current !== '') {
        $folded .= "\r\n\t" . $current;
    }
    return $folded;
}

function smtpAssertTransportLineLengths(string $message): void
{
    foreach (explode("\r\n", smtpNormalizeLineEndings($message)) as $idx => $line) {
        if (strlen($line) > 998) {
            throw new RuntimeException('El correo genero una linea demasiado larga para SMTP en la linea ' . ($idx + 1) . ' (' . strlen($line) . ' bytes).');
        }
    }
}

function smtpSendMail(array $account, string $to, string $subject, string $textBody, ?string $htmlBody = null): void
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('El remitente del correo no tiene email valido para responder.');
    }

    $host = trim((string)($account['smtp_host'] ?? ''));
    $port = (int)($account['smtp_port'] ?? 0);
    $secure = strtolower(trim((string)($account['smtp_secure'] ?? 'ssl')));
    $user = trim((string)($account['smtp_user'] ?? ''));
    $pass = (string)($account['smtp_pass'] ?? '');
    $from = trim((string)($account['email'] ?? $user));
    if ($host === '' || $port <= 0 || $user === '' || $pass === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('La cuenta SMTP esta incompleta.');
    }

    $target = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $socket = @stream_socket_client($target, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        throw new RuntimeException('No se pudo conectar al SMTP: ' . $errstr);
    }
    stream_set_timeout($socket, 20);

    try {
        smtpExpect($socket, ['220'], 'SMTP saludo');
        smtpCommand($socket, 'EHLO nexuspanel.local', ['250'], 'SMTP EHLO');
        if ($secure === 'tls') {
            smtpCommand($socket, 'STARTTLS', ['220'], 'SMTP STARTTLS');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('No se pudo activar TLS para SMTP.');
            }
            smtpCommand($socket, 'EHLO nexuspanel.local', ['250'], 'SMTP EHLO TLS');
        }
        smtpCommand($socket, 'AUTH LOGIN', ['334'], 'SMTP AUTH');
        smtpCommand($socket, base64_encode($user), ['334'], 'SMTP usuario');
        smtpCommand($socket, base64_encode($pass), ['235'], 'SMTP password');
        smtpCommand($socket, 'MAIL FROM:<' . $from . '>', ['250'], 'SMTP remitente');
        smtpCommand($socket, 'RCPT TO:<' . $to . '>', ['250', '251'], 'SMTP destinatario');
        smtpCommand($socket, 'DATA', ['354'], 'SMTP DATA');

        $boundary = 'nexus_' . bin2hex(random_bytes(12));
        $headers = [
            smtpFoldHeader('From', 'NexusPanel <' . $from . '>'),
            smtpFoldHeader('To', '<' . $to . '>'),
            'Subject: ' . smtpEncodedHeader($subject),
            'MIME-Version: 1.0',
            'Date: ' . date(DATE_RFC2822),
        ];
        if ($htmlBody !== null && trim($htmlBody) !== '') {
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $message = implode("\r\n", $headers)
                . "\r\n\r\n--{$boundary}\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . smtpBase64MessageBody($textBody)
                . "\r\n\r\n--{$boundary}\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . smtpBase64MessageBody($htmlBody)
                . "\r\n\r\n--{$boundary}--";
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            $message = implode("\r\n", $headers) . "\r\n\r\n" . smtpBase64MessageBody($textBody);
        }
        $message = smtpNormalizeLineEndings($message);
        smtpAssertTransportLineLengths($message);
        $message = smtpSafeMessageBody($message);
        fwrite($socket, $message . "\r\n.\r\n");
        smtpExpect($socket, ['250'], 'SMTP envio');
        @fwrite($socket, "QUIT\r\n");
    } finally {
        @fclose($socket);
    }
}

function findOrCreateEmailCustomer(PDO $pdo, array $mail): int
{
    $email = trim((string)($mail['from_email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('El correo no tiene remitente valido para crear el cliente.');
    }

    $st = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $st->execute([$email]);
    $existing = (int)($st->fetchColumn() ?: 0);
    if ($existing > 0) return $existing;

    $name = trim((string)($mail['ai_customer'] ?: $mail['from_name'] ?: $email));
    $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $pdo->prepare("
        INSERT INTO usuarios (nombre, email, password, rol, activo)
        VALUES (?, ?, ?, 'cliente', 1)
    ")->execute([
        mb_substr($name, 0, 120),
        mb_substr($email, 0, 180),
        $password,
    ]);
    return (int)$pdo->lastInsertId();
}

function emailAiItemsForOrder(PDO $pdo, int $emailId): array
{
    $st = $pdo->prepare("
        SELECT ai.*, p.precio, p.stock, p.nombre AS current_product_name, p.codigo AS current_product_code
        FROM email_inbox_ai_items ai
        LEFT JOIN productos p ON p.id = ai.product_id
        WHERE ai.email_id = ?
        ORDER BY ai.line_index ASC, ai.id ASC
    ");
    $st->execute([$emailId]);
    return $st->fetchAll();
}

function buildEmailConfirmationBody(array $mail, array $items, int $pedidoId, string $folio): string
{
    $customer = trim((string)($mail['ai_customer'] ?: $mail['from_name'] ?: ''));
    $delivery = trim((string)($mail['ai_delivery_date_value'] ?: $mail['ai_delivery_date'] ?: 'Por confirmar'));
    $lines = [];
    foreach ($items as $item) {
        $qty = rtrim(rtrim(number_format((float)$item['quantity_value'], 3, '.', ''), '0'), '.');
        $unit = trim((string)($item['unit_slug'] ?? ''));
        $name = trim((string)($item['product_name'] ?: $item['current_product_name'] ?: $item['product_text']));
        $lines[] = '- ' . $qty . ' ' . $unit . ' de ' . $name;
    }

    return trim("Buen dia" . ($customer !== '' ? " {$customer}" : '') . ".\n\n"
        . "Confirmamos la recepcion de su pedido.\n\n"
        . "Folio: {$folio}\n"
        . "Productos:\n" . implode("\n", $lines) . "\n\n"
        . "Fecha solicitada de entrega: {$delivery}\n\n"
        . "Nuestro equipo dara seguimiento operativo y se comunicara si requiere alguna validacion adicional.\n\n"
        . "Saludos,\nNexusPanel");
}

function buildEmailConfirmationHtml(array $mail, array $items, int $pedidoId, string $folio): string
{
    $customer = trim((string)($mail['ai_customer'] ?: $mail['from_name'] ?: ''));
    $delivery = trim((string)($mail['ai_delivery_date_value'] ?: $mail['ai_delivery_date'] ?: 'Por confirmar'));
    $safeCustomer = htmlspecialchars($customer !== '' ? $customer : 'cliente', ENT_QUOTES, 'UTF-8');
    $safeFolio = htmlspecialchars($folio, ENT_QUOTES, 'UTF-8');
    $safeDelivery = htmlspecialchars($delivery, ENT_QUOTES, 'UTF-8');

    $rows = '';
    foreach ($items as $item) {
        $qty = rtrim(rtrim(number_format((float)$item['quantity_value'], 3, '.', ''), '0'), '.');
        $unit = trim((string)($item['unit_slug'] ?? ''));
        $name = trim((string)($item['product_name'] ?: $item['current_product_name'] ?: $item['product_text']));
        $code = trim((string)($item['product_code'] ?: $item['current_product_code'] ?: ''));
        $rows .= '<tr>'
            . '<td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-weight:700;">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ($code !== '' ? '<div style="font-size:12px;color:#64748b;margin-top:3px;">Codigo: ' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</div>' : '') . '</td>'
            . '<td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;text-align:right;white-space:nowrap;">' . htmlspecialchars($qty . ' ' . $unit, ENT_QUOTES, 'UTF-8') . '</td>'
            . '</tr>';
    }

    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
        . '<div style="padding:28px 16px;">'
        . '<div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 18px 45px rgba(15,23,42,.12);">'
        . '<div style="background:#082f49;padding:24px 28px;color:#ffffff;">'
        . '<div style="font-size:13px;font-weight:700;color:#7dd3fc;text-transform:uppercase;letter-spacing:.08em;">Pedido confirmado</div>'
        . '<h1 style="margin:8px 0 0;font-size:26px;line-height:1.2;">Gracias, ' . $safeCustomer . '</h1>'
        . '<p style="margin:10px 0 0;color:#dbeafe;font-size:15px;line-height:1.55;">Confirmamos la recepcion de su pedido. Nuestro equipo dara seguimiento operativo y se comunicara si requiere alguna validacion adicional.</p>'
        . '</div>'
        . '<div style="padding:24px 28px;">'
        . '<div style="display:block;background:#ecfeff;border:1px solid #bae6fd;border-radius:14px;padding:16px 18px;margin-bottom:20px;">'
        . '<div style="font-size:12px;color:#0369a1;font-weight:700;text-transform:uppercase;letter-spacing:.06em;">Folio de seguimiento</div>'
        . '<div style="font-size:28px;font-weight:800;color:#0f172a;letter-spacing:.04em;margin-top:4px;">' . $safeFolio . '</div>'
        . '</div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;margin-bottom:20px;">'
        . '<thead><tr><th align="left" style="background:#f8fafc;padding:12px 16px;color:#334155;font-size:12px;text-transform:uppercase;letter-spacing:.06em;">Producto</th><th align="right" style="background:#f8fafc;padding:12px 16px;color:#334155;font-size:12px;text-transform:uppercase;letter-spacing:.06em;">Cantidad</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table>'
        . '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:16px 18px;margin-bottom:20px;">'
        . '<div style="font-size:12px;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.06em;">Fecha solicitada de entrega</div>'
        . '<div style="font-size:18px;font-weight:800;color:#0f172a;margin-top:4px;">' . $safeDelivery . '</div>'
        . '</div>'
        . '<p style="margin:0;color:#475569;font-size:14px;line-height:1.6;">Si necesita hacer algun ajuste, puede responder directamente a este correo.</p>'
        . '</div>'
        . '<div style="background:#0f172a;color:#cbd5e1;padding:16px 28px;font-size:12px;">NexusPanel · Confirmacion automatica generada por el area operativa.</div>'
        . '</div></div></body></html>';
}

function insertPedidoFromEmail(PDO $pdo, int $clienteId, array $mail, array $items, int $usuarioId): array
{
    $now = fechaMysqlAhora();
    $folio = function_exists('generarFolioHex') ? generarFolioHex($pdo) : '';
    $total = 0.0;
    foreach ($items as $item) {
        $total += (float)($item['precio'] ?? 0) * (int)$item['quantity_value'];
    }

    $cols = [];
    $vals = [];
    $add = static function (string $col, $val) use (&$cols, &$vals): void {
        $cols[] = $col;
        $vals[] = $val;
    };

    if (columnExists($pdo, 'pedidos', 'folio_hex')) $add('folio_hex', $folio);
    $add('cliente_id', $clienteId);
    $add('operador_id', null);
    $add('estado', 'pendiente');
    if (columnExists($pdo, 'pedidos', 'source_channel')) $add('source_channel', 'email_ia');
    if (columnExists($pdo, 'pedidos', 'source_email_id')) $add('source_email_id', (int)$mail['id']);
    if (columnExists($pdo, 'pedidos', 'source_message_id')) $add('source_message_id', mb_substr((string)($mail['message_id'] ?? ''), 0, 255));
    if (columnExists($pdo, 'pedidos', 'tipo_pedido')) $add('tipo_pedido', 'formal');
    $add('total', $total);
    if (columnExists($pdo, 'pedidos', 'fecha_requerida')) {
        $deliveryDate = trim((string)($mail['ai_delivery_date_value'] ?? ''));
        $deliveryTime = trim((string)($mail['ai_delivery_time_value'] ?? ''));
        $add('fecha_requerida', $deliveryDate !== '' ? ($deliveryDate . ' ' . ($deliveryTime !== '' ? $deliveryTime : '00:00:00')) : null);
    }
    if (columnExists($pdo, 'pedidos', 'notas')) {
        $add('notas', mb_substr('Pedido confirmado desde correo IA #' . (int)$mail['id'] . "\n" . (string)($mail['ai_observations'] ?? ''), 0, 2500));
    }
    if (columnExists($pdo, 'pedidos', 'created_at')) $add('created_at', $now);
    if (columnExists($pdo, 'pedidos', 'updated_at')) $add('updated_at', $now);

    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $pdo->prepare("INSERT INTO pedidos (" . implode(',', $cols) . ") VALUES ($placeholders)")->execute($vals);
    $pedidoId = (int)$pdo->lastInsertId();
    if ($folio === '') $folio = strtoupper(dechex($pedidoId));

    $hasAjuste = columnExists($pdo, 'pedido_items', 'ajuste_cliente');
    $stItem = $pdo->prepare($hasAjuste
        ? "INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unit, ajuste_cliente) VALUES (?, ?, ?, ?, ?)"
        : "INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unit) VALUES (?, ?, ?, ?)"
    );
    $stStock = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");

    foreach ($items as $item) {
        $qty = (int)$item['quantity_value'];
        $args = [$pedidoId, (int)$item['product_id'], $qty, (float)($item['precio'] ?? 0)];
        if ($hasAjuste) $args[] = null;
        $stItem->execute($args);
        $stockAntes = (int)$item['stock'];
        $stockNuevo = $stockAntes - $qty;
        $stStock->execute([$qty, (int)$item['product_id']]);
        registrarMovimientoInventario($pdo, (int)$item['product_id'], 'salida', $qty, $stockAntes, $stockNuevo, 'pedido', $pedidoId, 'Descuento por confirmacion de pedido desde correo IA', $usuarioId);
    }

    recalcularHistorialCliente($pdo, $clienteId);
    registrarHistorialPedido($pdo, $pedidoId, 'pendiente', $usuarioId, 'Pedido confirmado desde correo IA');
    registrarNotificacionEvento($pdo, $pedidoId, $clienteId, 'email', 'pedido_confirmado_email', 'Pedido confirmado desde correo IA.', 'pendiente', ['email_inbox_id' => (int)$mail['id']]);
    registrarAuditoria($pdo, $usuarioId, nombreRolActual(), 'pedidos_email', 'confirmar_pedido_email', 'pedido', $pedidoId, 'Correo inbox #' . (int)$mail['id']);

    return ['pedido_id' => $pedidoId, 'folio' => $folio, 'total' => $total];
}

function confirmEmailCandidateAsOrder(PDO $pdo, int $emailId, int $usuarioId): array
{
    $pdo->beginTransaction();
    try {
        $stMail = $pdo->prepare("SELECT * FROM email_inbox_messages WHERE id = ? FOR UPDATE");
        $stMail->execute([$emailId]);
        $mail = $stMail->fetch();
        if (!$mail) throw new RuntimeException('No se encontro el correo para confirmar.');
        if (!empty($mail['confirmed_pedido_id'])) {
            throw new RuntimeException('Este correo ya fue confirmado como pedido #' . (int)$mail['confirmed_pedido_id'] . '.');
        }
        if ((int)($mail['ai_is_order'] ?? 0) !== 1) {
            throw new RuntimeException('El correo no esta marcado como pedido.');
        }

        $items = emailAiItemsForOrder($pdo, $emailId);
        if (!$items) throw new RuntimeException('No hay productos tipados para crear el pedido. Analiza y guarda la extraccion primero.');

        foreach ($items as $idx => $item) {
            if (empty($item['product_id'])) {
                throw new RuntimeException('Producto ' . ($idx + 1) . ' no tiene producto_id empatado.');
            }
            if ((float)($item['quantity_value'] ?? 0) <= 0) {
                throw new RuntimeException('Producto ' . ($idx + 1) . ' no tiene cantidad valida.');
            }
            if ((float)$item['quantity_value'] !== floor((float)$item['quantity_value'])) {
                throw new RuntimeException('Producto ' . ($idx + 1) . ' tiene cantidad decimal, pero pedido_items solo acepta enteros.');
            }
            if (!in_array((string)$item['stock_status'], ['cubre_completo'], true)) {
                throw new RuntimeException('Producto ' . ($idx + 1) . ' no tiene stock confirmado.');
            }
            if ((int)($item['stock'] ?? 0) < (int)$item['quantity_value']) {
                throw new RuntimeException('Stock insuficiente para ' . (string)($item['current_product_name'] ?? $item['product_text']));
            }
        }

        $clienteId = findOrCreateEmailCustomer($pdo, $mail);
        $pedido = insertPedidoFromEmail($pdo, $clienteId, $mail, $items, $usuarioId);
        $pdo->prepare("
            UPDATE email_inbox_messages
            SET confirmed_pedido_id = ?,
                confirmation_email_status = 'pendiente',
                confirmation_email_error = NULL,
                review_status = 'candidato_pedido',
                reviewed_at = NOW(),
                is_unseen = 0
            WHERE id = ?
        ")->execute([$pedido['pedido_id'], $emailId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $stMail = $pdo->prepare("SELECT * FROM email_inbox_messages WHERE id = ? LIMIT 1");
    $stMail->execute([$emailId]);
    $mail = $stMail->fetch();
    if (!$mail) {
        throw new RuntimeException('No se encontro el correo confirmado.');
    }
    return sendConfirmationEmailForMail($pdo, $mail, (int)$pedido['pedido_id'], (string)$pedido['folio']);
}

function pedidoEmailFolio(PDO $pdo, int $pedidoId): string
{
    if ($pedidoId <= 0) return '';
    if (columnExists($pdo, 'pedidos', 'folio_hex')) {
        $st = $pdo->prepare("SELECT folio_hex FROM pedidos WHERE id = ? LIMIT 1");
        $st->execute([$pedidoId]);
        $folio = trim((string)($st->fetchColumn() ?: ''));
        if ($folio !== '') return $folio;
    }
    return strtoupper(dechex($pedidoId));
}

function sendConfirmationEmailForMail(PDO $pdo, array $mail, int $pedidoId, string $folio): array
{
    $emailId = (int)($mail['id'] ?? 0);
    if ($emailId <= 0 || $pedidoId <= 0) {
        throw new RuntimeException('Faltan datos para enviar la confirmacion.');
    }
    $items = emailAiItemsForOrder($pdo, $emailId);
    if (!$items) {
        throw new RuntimeException('No hay productos tipados para armar el correo de confirmacion.');
    }
    $subject = 'Confirmacion de pedido #' . $folio;
    $body = buildEmailConfirmationBody($mail, $items, $pedidoId, $folio);
    $htmlBody = buildEmailConfirmationHtml($mail, $items, $pedidoId, $folio);

    try {
        $account = emailInboxSmtpAccount($pdo, $mail);
        smtpSendMail($account, (string)$mail['from_email'], $subject, $body, $htmlBody);
        $pdo->prepare("UPDATE email_inbox_messages SET confirmation_sent_at = NOW(), confirmation_email_status = 'enviado', confirmation_email_error = NULL WHERE id = ?")
            ->execute([$emailId]);
        registrarNotificacionEvento($pdo, $pedidoId, findOrCreateEmailCustomer($pdo, $mail), 'email', 'pedido_confirmado_email', $body, 'enviado', ['email_inbox_id' => $emailId]);
        return ['pedido_id' => $pedidoId, 'folio' => $folio, 'email_sent' => true, 'email_body' => $body];
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE email_inbox_messages SET confirmation_email_status = 'error', confirmation_email_error = ? WHERE id = ?")
            ->execute([mb_substr($e->getMessage(), 0, 2500), $emailId]);
        throw new RuntimeException('Pedido #' . $folio . ', pero no se pudo enviar el correo: ' . $e->getMessage());
    }
}

function resendEmailOrderConfirmation(PDO $pdo, int $emailId): array
{
    $stMail = $pdo->prepare("SELECT * FROM email_inbox_messages WHERE id = ? LIMIT 1");
    $stMail->execute([$emailId]);
    $mail = $stMail->fetch();
    if (!$mail) {
        throw new RuntimeException('No se encontro el correo para reenviar confirmacion.');
    }
    $pedidoId = (int)($mail['confirmed_pedido_id'] ?? 0);
    if ($pedidoId <= 0) {
        throw new RuntimeException('Este correo todavia no tiene un pedido confirmado.');
    }
    return sendConfirmationEmailForMail($pdo, $mail, $pedidoId, pedidoEmailFolio($pdo, $pedidoId));
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
            $itemNames = $_POST['ai_item_name'] ?? [];
            $itemQuantities = $_POST['ai_item_quantity'] ?? [];
            $itemUnits = $_POST['ai_item_unit'] ?? [];
            $products = [];
            if (is_array($itemNames)) {
                $rows = max(count($itemNames), is_array($itemQuantities) ? count($itemQuantities) : 0);
                for ($i = 0; $i < $rows; $i++) {
                    $name = trim((string)($itemNames[$i] ?? ''));
                    $qtyText = trim((string)($itemQuantities[$i] ?? ''));
                    $unit = trim((string)($itemUnits[$i] ?? ''));
                    if ($name === '' && $qtyText === '') continue;
                    [, $qtyNumber, $unitFromText] = parseQuantityValue($qtyText);
                    $products[] = [
                        'producto' => $name,
                        'cantidad_texto' => $qtyText,
                        'cantidad_numero' => $qtyNumber,
                        'unidad' => $unit !== '' ? $unit : $unitFromText,
                        'unidad_slug' => normalizeUnitSlug($unit !== '' ? $unit : $unitFromText),
                        'confianza' => null,
                    ];
                }
            }
            if (!$products) {
                [, $qtyNumber, $unitFromText] = parseQuantityValue($_POST['ai_quantity'] ?? '');
                $products[] = [
                    'producto' => trim((string)($_POST['ai_product'] ?? '')),
                    'cantidad_texto' => trim((string)($_POST['ai_quantity'] ?? '')),
                    'cantidad_numero' => $qtyNumber,
                    'unidad' => $unitFromText,
                    'unidad_slug' => normalizeUnitSlug($unitFromText),
                    'confianza' => null,
                ];
            }
            $firstProduct = $products[0] ?? ['producto' => '', 'cantidad_texto' => ''];
            $deliveryText = trim((string)($_POST['ai_delivery_date'] ?? ''));
            $deliveryDate = normalizeAiDateValue($_POST['ai_delivery_date_value'] ?? '');
            if ($deliveryDate === '' && $deliveryText !== '') {
                $deliveryDate = resolveSpanishRelativeDate($deliveryText, date('Y-m-d'));
            }

            $extraction = [
                'producto_solicitado' => aiText($firstProduct['producto'] ?? ''),
                'cantidad' => aiText($firstProduct['cantidad_texto'] ?? ''),
                'fecha_entrega' => $deliveryText,
                'fecha_entrega_fecha' => $deliveryDate,
                'fecha_entrega_hora' => normalizeAiTimeValue($_POST['ai_delivery_time_value'] ?? ''),
                'fecha_entrega_tipo' => trim((string)($_POST['ai_delivery_type'] ?? '')),
                'cliente' => trim((string)($_POST['ai_customer'] ?? '')),
                'observaciones' => trim((string)($_POST['ai_observations'] ?? '')),
                'nivel_confianza' => $confidenceValue,
                'es_pedido' => $isOrder,
                'categoria' => trim((string)($_POST['ai_category'] ?? '')),
                'estado_ia' => trim((string)($_POST['ai_processing_status'] ?? '')),
                'alertas' => aiList($_POST['ai_alerts'] ?? ''),
                'productos' => $products,
                'confianza_campos' => [],
            ];
            saveAiExtraction($pdo, $id, $extraction);

            if ($decision === 'confirm_order') {
                $result = confirmEmailCandidateAsOrder($pdo, $id, (int)($usuario['usuario_id'] ?? $usuario['id'] ?? 0));
                $msg = 'Pedido #' . $result['folio'] . ' confirmado y correo enviado al cliente.';
            } elseif ($decision === 'resend_confirmation') {
                $result = resendEmailOrderConfirmation($pdo, $id);
                $msg = 'Correo de confirmacion reenviado para el pedido #' . $result['folio'] . '.';
            } elseif ($decision === 'accept') {
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
        } elseif ($action === 'confirm_email_order') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Correo invalido para confirmar pedido.');
            }
            $result = confirmEmailCandidateAsOrder($pdo, $id, (int)($usuario['usuario_id'] ?? $usuario['id'] ?? 0));
            $msg = 'Pedido #' . $result['folio'] . ' confirmado y correo enviado al cliente.';
            $postSelectedId = $id;
        } elseif ($action === 'resend_confirmation_email') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Correo invalido para reenviar confirmacion.');
            }
            $result = resendEmailOrderConfirmation($pdo, $id);
            $msg = 'Correo de confirmacion reenviado para el pedido #' . $result['folio'] . '.';
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
            $postSelectedId = $id;
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

function normalizeAiStockStatus(string $status): string
{
    $key = aiSearchKey($status);
    if (in_array($key, ['CUBRE COMPLETO', 'COVERED', 'OK', 'ENOUGH', 'SUFFICIENT', 'FULLY COVERED'], true)) {
        return 'cubre_completo';
    }
    if (in_array($key, ['PARCIAL', 'PARTIAL', 'INSUFFICIENT', 'NOT ENOUGH', 'NO CUBRE COMPLETO'], true)) {
        return 'parcial';
    }
    if (in_array($key, ['SIN STOCK', 'NO STOCK', 'OUT OF STOCK'], true)) {
        return 'sin_stock';
    }
    if (in_array($key, ['CANTIDAD FALTANTE', 'MISSING QUANTITY', 'QUANTITY MISSING'], true)) {
        return 'cantidad_faltante';
    }
    if (in_array($key, ['PRODUCTO NO ENCONTRADO', 'NOT FOUND', 'PRODUCT NOT FOUND'], true)) {
        return 'producto_no_encontrado';
    }
    return $status;
}

function normalizeAiStockPayload(array $stock): array
{
    if (!is_array($stock['items'] ?? null)) {
        return $stock;
    }

    $overall = (string)($stock['estado'] ?? '');
    foreach ($stock['items'] as $idx => $item) {
        if (!is_array($item)) {
            continue;
        }

        $status = normalizeAiStockStatus((string)($item['status'] ?? $item['stock_status'] ?? ''));
        $stockAvailable = $item['stock_disponible'] ?? $item['stock_available'] ?? null;
        $qty = $item['cantidad_solicitada'] ?? $item['quantity_value'] ?? null;
        $productName = aiText($item['producto_catalogo'] ?? $item['product_name'] ?? '');

        if ($status === '' && $productName !== '') {
            if ($qty === null || $qty === '') {
                $status = 'cantidad_faltante';
            } elseif ($stockAvailable !== null && (float)$stockAvailable >= (float)$qty) {
                $status = 'cubre_completo';
            } elseif ($stockAvailable !== null) {
                $status = 'parcial';
            }
        }
        if ($status === '' && $overall === 'cubre_completo' && $productName !== '') {
            $status = 'cubre_completo';
        }

        $item['status'] = $status !== '' ? $status : 'producto_no_encontrado';
        $item['stock_disponible'] = $stockAvailable;
        $item['faltante'] = $item['faltante'] ?? $item['stock_missing'] ?? null;
        $item['producto_catalogo'] = $productName;
        $item['unidad_stock'] = $item['unidad_stock'] ?? $item['unit_text'] ?? '';
        $stock['items'][$idx] = $item;
    }

    return $stock;
}

function selectedAiPayload(?array $selected): array
{
    if (!$selected) return ['productos' => [], 'stock' => [], 'confianza_campos' => [], 'alertas' => []];
    $raw = json_decode((string)($selected['ai_raw_json'] ?? ''), true);
    if (!is_array($raw)) $raw = [];
    $stock = json_decode((string)($selected['ai_stock_json'] ?? ''), true);
    if (!is_array($stock)) $stock = is_array($raw['stock'] ?? null) ? $raw['stock'] : [];
    $stock = normalizeAiStockPayload($stock);
    $products = is_array($raw['productos'] ?? null) ? $raw['productos'] : [];
    if (!$products && (trim((string)($selected['ai_product'] ?? '')) !== '' || trim((string)($selected['ai_quantity'] ?? '')) !== '')) {
        [, $qtyNumber, $unit] = parseQuantityValue($selected['ai_quantity'] ?? '');
        $products[] = [
            'producto' => (string)($selected['ai_product'] ?? ''),
            'cantidad_texto' => (string)($selected['ai_quantity'] ?? ''),
            'cantidad_numero' => $qtyNumber,
            'unidad' => $unit,
            'confianza' => null,
        ];
    }
    $columnAlerts = trim((string)($selected['ai_alerts'] ?? '')) !== '' ? preg_split('/\R+/', (string)$selected['ai_alerts']) : [];
    $rawAlerts = is_array($raw['alertas'] ?? null) ? $raw['alertas'] : [];
    return [
        'productos' => $products,
        'stock' => $stock,
        'confianza_campos' => is_array($raw['confianza_campos'] ?? null) ? $raw['confianza_campos'] : [],
        'alertas' => uniqueAiAlerts($columnAlerts ?: $rawAlerts),
    ];
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
        .mail-original-compact {
            display:grid; grid-template-columns:repeat(3,minmax(0,1fr)) auto; gap:8px; align-items:center;
            padding:10px; margin-bottom:12px; border:1px solid rgba(148,163,184,.16); border-radius:9px;
            background:rgba(8,19,38,.48);
        }
        .mail-original-meta { min-width:0; }
        .mail-original-meta span {
            display:block; color:#8aa4c2; font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.04em;
        }
        .mail-original-meta strong {
            display:block; margin-top:3px; color:#e2e8f0; font-size:12px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        }
        .mail-original-body {
            white-space:pre-wrap; font-size:13px; line-height:1.6;
            background:#091326; border:1px solid #1d4f73; border-radius:9px;
            padding:14px; max-height:62vh; overflow:auto; color:#e7f3ff;
        }
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
        .ai-products + .ai-grid > .ai-field:nth-child(1),
        .ai-products + .ai-grid > .ai-field:nth-child(2) { display:none; }
        .ai-grid .full { grid-column:1 / -1; }
        .ai-field label { display:block; margin-bottom:5px; color:#dbeafe; font-size:11px; font-weight:800; }
        .ai-field input, .ai-field select, .ai-field textarea {
            width:100%; min-height:40px; border-radius:9px; border:1px solid #334155;
            background:#1e293b; color:#f8fafc; padding:8px 10px; font-size:13px;
        }
        .ai-field textarea { min-height:82px; resize:vertical; }
        .ai-summary-row { position:relative; z-index:1; display:flex; flex-wrap:wrap; gap:8px; margin:10px 0 12px; }
        .ai-chip { border:1px solid rgba(148,163,184,.18); background:rgba(15,23,42,.62); color:#cbd5e1; border-radius:999px; padding:6px 9px; font-size:11px; font-weight:800; }
        .ai-chip.ok { color:#86efac; border-color:rgba(34,197,94,.35); background:rgba(22,101,52,.16); }
        .ai-chip.warn { color:#fde68a; border-color:rgba(245,158,11,.35); background:rgba(120,53,15,.18); }
        .ai-chip.bad { color:#fecaca; border-color:rgba(239,68,68,.35); background:rgba(127,29,29,.18); }
        .ai-products { position:relative; z-index:1; display:grid; gap:8px; margin:8px 0 12px; }
        .ai-product-row { display:grid; grid-template-columns:1.5fr .55fr .55fr .8fr; gap:8px; align-items:end; padding:10px; border:1px solid rgba(148,163,184,.16); border-radius:10px; background:rgba(15,23,42,.42); }
        .ai-product-row .stock-note { font-size:11px; line-height:1.35; color:#cbd5e1; }
        .ai-product-row .stock-note strong { display:block; color:#f8fafc; }
        .ai-product-row .stock-note.ok { color:#86efac; }
        .ai-product-row .stock-note.warn { color:#fde68a; }
        .ai-product-row .stock-note.bad { color:#fecaca; }
        .ai-alerts { position:relative; z-index:1; display:grid; gap:6px; margin-bottom:12px; }
        .ai-alert { border:1px solid rgba(245,158,11,.25); background:rgba(245,158,11,.08); color:#fde68a; border-radius:9px; padding:8px 10px; font-size:12px; }
        .ai-actions { position:relative; z-index:1; display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; }
        .ai-quick-review {
            position:relative; z-index:1; display:grid; gap:10px; margin:10px 0 12px;
            border:1px solid rgba(125,211,252,.22); border-radius:10px; background:rgba(15,23,42,.48);
            padding:10px;
        }
        .ai-quick-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
        .ai-quick-head h5 { margin:0; color:#f8fafc; font-size:13px; font-weight:900; }
        .ai-quick-head p { margin:3px 0 0; color:#9fb6d3; font-size:11px; }
        .ai-compact-table {
            width:100%; table-layout:auto; border-collapse:separate; border-spacing:0;
            border:1px solid rgba(148,163,184,.14); border-radius:8px; overflow:hidden;
            background:rgba(8,19,38,.45);
        }
        .ai-compact-table th,
        .ai-compact-table td {
            padding:6px 8px; border-bottom:1px solid rgba(148,163,184,.10);
            font-size:11px; line-height:1.25; vertical-align:middle; white-space:nowrap;
        }
        .ai-compact-table tr:last-child th,
        .ai-compact-table tr:last-child td { border-bottom:0; }
        .ai-compact-table th {
            width:1%; color:#8aa4c2; font-weight:900; text-transform:uppercase; letter-spacing:.04em;
            background:rgba(15,23,42,.34);
        }
        .ai-compact-table td { color:#e2e8f0; font-weight:800; max-width:260px; overflow:hidden; text-overflow:ellipsis; }
        .ai-compact-table .wide { white-space:normal; max-width:none; }
        .ai-product-table { margin-top:2px; }
        .ai-product-table th { text-align:left; }
        .ai-product-table td:nth-child(2),
        .ai-product-table td:nth-child(3) { width:1%; text-align:right; }
        .ai-product-table .product-name { max-width:360px; text-align:left; }
        .ai-stock-ok { color:#86efac !important; }
        .ai-stock-warn { color:#fde68a !important; }
        .ai-stock-bad { color:#fecaca !important; }
        .ai-quick-item {
            min-width:0; border:1px solid rgba(148,163,184,.12); border-radius:8px;
            background:rgba(8,19,38,.38); padding:7px 8px; font-size:11px; color:#e2e8f0;
        }
        .ai-quick-label { display:block; color:#8aa4c2; font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.04em; }
        .ai-quick-value { display:block; margin-top:3px; color:#e2e8f0; font-size:11px; font-weight:800; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .ai-quick-products { display:grid; gap:7px; }
        .ai-quick-product { display:grid; grid-template-columns:minmax(0,1fr) 110px 150px; gap:8px; align-items:center; border:1px solid rgba(148,163,184,.14); border-radius:8px; padding:8px 10px; background:rgba(8,19,38,.58); font-size:12px; }
        .ai-quick-product strong { color:#f8fafc; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .ai-quick-product span { color:#cbd5e1; }
        .ai-quick-product .ok { color:#86efac; }
        .ai-quick-product .warn { color:#fde68a; }
        .ai-quick-product .bad { color:#fecaca; }
        .ai-modal-backdrop {
            position:fixed; inset:0; z-index:2400; display:none; background:rgba(2,8,23,.78);
            backdrop-filter:blur(4px);
        }
        .ai-modal-backdrop.show { display:block; }
        .ai-review-modal, .ai-edit-modal {
            position:fixed; left:50%; top:50%; transform:translate(-50%,-50%);
            z-index:2401; display:none; width:min(760px, calc(100vw - 28px)); max-height:88vh; overflow:auto;
            border:1px solid rgba(14,165,233,.32); border-radius:12px; background:#0b1222;
            box-shadow:0 30px 90px rgba(0,0,0,.64);
        }
        .ai-edit-modal { width:min(1080px, calc(100vw - 28px)); }
        .ai-review-modal.show, .ai-edit-modal.show { display:block; }
        .ai-modal-header {
            display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
            padding:14px 16px; border-bottom:1px solid #1e293b;
        }
        .ai-modal-header h5 { margin:0; color:#f8fafc; font-size:15px; font-weight:900; }
        .ai-modal-header p { margin:4px 0 0; color:#9fb6d3; font-size:12px; }
        .ai-modal-close {
            width:34px; height:34px; border-radius:8px; border:1px solid #334155;
            background:#1e293b; color:#e2e8f0; display:inline-flex; align-items:center; justify-content:center;
        }
        .ai-modal-body { padding:14px 16px 16px; }
        .ai-modal-actions {
            display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:flex-start;
            margin-top:14px; padding-top:12px; border-top:1px solid rgba(30,41,59,.8);
        }
        .ai-modal-actions .edit-away { margin-left:auto; }
        .ai-modal-actions .cancel-away { background:#475569; color:#e2e8f0; border:0; }
        .btn-ai {
            min-height:40px; border-radius:9px; padding:0 14px; border:1px solid rgba(125,211,252,.38);
            background:rgba(14,165,233,.14); color:#dff7ff; font-weight:900; font-size:12px;
            display:inline-flex; align-items:center; justify-content:center; gap:8px;
        }
        .btn-ai.primary { border:0; background:#0ea5e9; color:#fff; }
        .btn-ai.accept { border:0; background:#10b981; color:#fff; }
        .btn-ai.confirm { border:0; background:#22c55e; color:#052e16; }
        .btn-ai.discard { border:0; background:#475569; color:#e2e8f0; }
        .ai-loading-backdrop {
            position:fixed; inset:0; z-index:2300; display:none;
            background:rgba(2,8,23,.82); backdrop-filter:blur(5px);
        }
        .ai-loading-backdrop.show { display:block; }
        .ai-loading-modal {
            position:fixed; left:50%; top:50%; transform:translate(-50%,-50%);
            z-index:2301; display:none; width:min(520px, calc(100vw - 28px));
            border:1px solid rgba(14,165,233,.32); border-radius:14px;
            background:linear-gradient(145deg,#0b1222,#081a2f);
            box-shadow:0 30px 90px rgba(0,0,0,.62); overflow:hidden;
        }
        .ai-loading-modal.show { display:block; }
        .ai-loading-top {
            padding:18px 18px 14px; display:flex; gap:14px; align-items:flex-start;
            border-bottom:1px solid rgba(30,41,59,.82);
        }
        .ai-loading-orbit {
            width:48px; height:48px; border-radius:12px; flex:0 0 auto;
            display:grid; place-items:center; color:#7dd3fc;
            background:rgba(14,165,233,.12); border:1px solid rgba(14,165,233,.28);
        }
        .ai-loading-orbit i { animation:aiSpin 1s linear infinite; }
        .ai-loading-title { margin:0; color:#f8fafc; font-size:16px; font-weight:900; }
        .ai-loading-copy { margin:5px 0 0; color:#9fb6d3; font-size:12px; line-height:1.5; }
        .ai-loading-body { padding:14px 18px 18px; }
        .ai-loading-steps { display:grid; gap:9px; margin:0 0 14px; padding:0; list-style:none; }
        .ai-loading-steps li {
            display:flex; align-items:center; gap:10px; min-height:34px; color:#cbd5e1;
            font-size:12px; font-weight:700;
        }
        .ai-loading-steps span {
            width:24px; height:24px; border-radius:999px; display:grid; place-items:center;
            background:rgba(14,165,233,.10); color:#7dd3fc; border:1px solid rgba(14,165,233,.25);
            font-size:11px;
        }
        .ai-loading-bar { height:7px; overflow:hidden; border-radius:999px; background:#172033; }
        .ai-loading-bar::before {
            content:''; display:block; height:100%; width:42%; border-radius:999px;
            background:linear-gradient(90deg,#0ea5e9,#10b981);
            animation:aiLoadingBar 1.55s ease-in-out infinite;
        }
        .ai-loading-foot { margin:10px 0 0; color:#7f93ad; font-size:11px; }
        @keyframes aiSpin { to { transform:rotate(360deg); } }
        @keyframes aiLoadingBar {
            0% { transform:translateX(-110%); }
            55% { transform:translateX(75%); }
            100% { transform:translateX(250%); }
        }
        .review-form { margin-top:16px; display:grid; gap:10px; }
        .review-form label { font-size:12px; color:#dbeafe; font-weight:700; }
        .review-compact {
            display:flex; align-items:center; justify-content:space-between; gap:10px;
            margin-top:12px; padding:10px; border:1px solid rgba(148,163,184,.16);
            border-radius:9px; background:rgba(8,19,38,.48);
        }
        .review-compact-info { min-width:0; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .review-compact-info strong { color:#e2e8f0; font-size:12px; }
        .review-compact-actions { display:flex; align-items:center; justify-content:flex-end; gap:8px; flex-wrap:wrap; }
        .review-note-preview {
            color:#94a3b8; font-size:11px; max-width:420px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        }
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
            .mail-empty-state { flex-direction:column; align-items:flex-start; }
            .metrics { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .content-grid.mail-grid { grid-template-columns:1fr; }
            .mail-toolbar { grid-template-columns:1fr; }
            .mail-page-header { align-items:flex-start; flex-direction:column; }
            .mail-list { max-height:45vh; }
            .mail-accounts-grid { grid-template-columns:1fr; }
            .mail-accounts-grid .wide,
            .mail-accounts-grid .full { grid-column:span 1; }
            .mail-original-compact { grid-template-columns:1fr; }
            .review-compact { align-items:flex-start; flex-direction:column; }
            .review-compact-actions { width:100%; justify-content:flex-start; }
            .review-note-preview { max-width:100%; }
            .ai-grid { grid-template-columns:1fr; }
            .ai-grid .full { grid-column:span 1; }
            .ai-product-row { grid-template-columns:1fr; }
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
        <a href="reportes_ventas.php" class="nav-item"><i class="fa-solid fa-file-excel"></i><span>Reportes</span></a>
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
                                <div class="mt-2 d-flex flex-wrap gap-1">
                                    <span class="<?= statusBadge((string)$r['review_status']) ?>"><?= htmlspecialchars((string)$r['review_status']) ?></span>
                                    <?php if (!empty($r['confirmed_pedido_id'])): ?>
                                        <span class="status-pill status-ok"><i class="fa-solid fa-receipt"></i> Pedido #<?= (int)$r['confirmed_pedido_id'] ?></span>
                                    <?php elseif ((string)($r['confirmation_email_status'] ?? '') === 'error'): ?>
                                        <span class="status-pill status-pending"><i class="fa-solid fa-envelope-circle-xmark"></i> Error correo</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>

            <?php
                $selectedHasAiExtraction = $selected && (
                    trim((string)($selected['ai_product'] ?? '')) !== ''
                    || trim((string)($selected['ai_quantity'] ?? '')) !== ''
                    || trim((string)($selected['ai_customer'] ?? '')) !== ''
                    || trim((string)($selected['ai_observations'] ?? '')) !== ''
                    || ($selected['ai_confidence'] ?? null) !== null
                );
                $selectedConfirmed = $selected && !empty($selected['confirmed_pedido_id']);
            ?>
            <article class="mail-panel">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title"><?= $selected ? 'Correo seleccionado' : 'Detalle y Revision' ?></h3>
                        <p class="panel-subtitle">
                            <?php if (!$selected): ?>
                                Preparado para extraccion con IA
                            <?php elseif ($selectedConfirmed): ?>
                                Pedido confirmado #<?= (int)$selected['confirmed_pedido_id'] ?>
                            <?php elseif ($selectedHasAiExtraction): ?>
                                Extraccion IA lista para revisar
                            <?php else: ?>
                                Listo para analizar con IA
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="mail-detail-content">
                <?php if (!$selected): ?>
                    <p class="text-muted">
                        <?= $activeMailAccounts === 0 ? 'Vincula una cuenta para revisar correos.' : 'Selecciona un correo de la bandeja.' ?>
                    </p>
                <?php else: ?>
                    <?php
                        $selectedSender = (string)($selected['from_name'] ?: $selected['from_email'] ?: '-');
                        $selectedSubject = (string)($selected['subject'] ?: '(Sin asunto)');
                        $selectedDate = (string)($selected['received_at'] ?: $selected['fetched_at']);
                        $selectedBody = (string)($selected['body_text'] ?: strip_tags((string)$selected['body_html']) ?: '(Sin contenido legible)');
                    ?>
                    <div class="mail-original-compact">
                        <div class="mail-original-meta">
                            <span>De</span>
                            <strong title="<?= htmlspecialchars($selectedSender) ?>"><?= htmlspecialchars($selectedSender) ?></strong>
                        </div>
                        <div class="mail-original-meta">
                            <span>Asunto</span>
                            <strong title="<?= htmlspecialchars($selectedSubject) ?>"><?= htmlspecialchars($selectedSubject) ?></strong>
                        </div>
                        <div class="mail-original-meta">
                            <span>Fecha</span>
                            <strong><?= htmlspecialchars($selectedDate) ?></strong>
                        </div>
                        <button class="btn-ai primary" type="button" id="openOriginalMailModal"><i class="fa-solid fa-envelope-open-text"></i> Ver correo</button>
                    </div>
                    <div class="mail-modal-backdrop" id="originalMailBackdrop"></div>
                    <section class="mail-modal" id="originalMailModal" aria-hidden="true">
                        <div class="mail-modal-header">
                            <h3 class="mail-modal-title">Correo original</h3>
                            <button class="mail-modal-close" type="button" id="closeOriginalMailModal"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <div class="mail-modal-body">
                            <div class="detail-table">
                                <div class="detail-label">De</div><div class="detail-value"><?= htmlspecialchars($selectedSender) ?></div>
                                <div class="detail-label">Asunto</div><div class="detail-value"><?= htmlspecialchars($selectedSubject) ?></div>
                                <div class="detail-label">Fecha</div><div class="detail-value"><?= htmlspecialchars($selectedDate) ?></div>
                                <div class="detail-label">Message-ID</div><div class="detail-value"><span class="msg-id-chip"><?= htmlspecialchars((string)$selected['message_id']) ?></span></div>
                            </div>
                            <div class="mail-original-body"><?= htmlspecialchars($selectedBody) ?></div>
                        </div>
                    </section>

                    <?php
                        $hasAiExtraction = trim((string)($selected['ai_product'] ?? '')) !== ''
                            || trim((string)($selected['ai_quantity'] ?? '')) !== ''
                            || trim((string)($selected['ai_customer'] ?? '')) !== ''
                            || trim((string)($selected['ai_observations'] ?? '')) !== ''
                            || $selected['ai_confidence'] !== null;
                        $isConfirmedOrder = !empty($selected['confirmed_pedido_id']);
                    ?>
                    <section class="ai-card">
                        <div class="ai-head">
                            <div>
                                <h4 class="ai-title"><i class="fa-solid fa-wand-magic-sparkles"></i> Prellenado inteligente con IA</h4>
                                <p class="ai-subtitle">
                                    <?= $hasAiExtraction
                                        ? 'La IA solo propone datos. El administrador confirma, corrige o descarta.'
                                        : 'Extrae cliente, productos, entrega y stock sugerido.' ?>
                                </p>
                            </div>
                            <span class="ai-badge">
                                <i class="fa-solid fa-microchip"></i>
                                <?= htmlspecialchars(defined('OLLAMA_MODEL') ? OLLAMA_MODEL : 'llama3.2:3b') ?>
                            </span>
                        </div>

                        <form method="post" class="ai-actions js-ai-analyze-form">
                            <input type="hidden" name="action" value="analyze_ai">
                            <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>">
                            <button class="btn-ai primary" type="submit">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                                <?= $hasAiExtraction ? 'Analizar de nuevo' : 'Analizar con IA' ?>
                            </button>
                            <?php if (!$hasAiExtraction): ?>
                                <span class="ai-subtitle">Sin prellenado. No se modifica inventario ni se crea pedido.</span>
                            <?php endif; ?>
                        </form>

                        <?php if (!$hasAiExtraction): ?>
                            <div class="ai-summary-row">
                                <span class="ai-chip warn">Prellenado: pendiente</span>
                                <span class="ai-chip <?= $isConfirmedOrder ? 'ok' : 'warn' ?>">
                                    <?= $isConfirmedOrder ? 'Pedido confirmado #' . (int)$selected['confirmed_pedido_id'] : 'Pedido no confirmado' ?>
                                </span>
                                <span class="ai-chip warn">Inventario sin validar</span>
                            </div>
                        <?php endif; ?>

                        <?php if ($hasAiExtraction): ?>
                            <form method="post" class="js-ai-save-form">
                                <input type="hidden" name="action" value="save_ai_extraction">
                                <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>">
                                <?php
                                    $aiPayload = selectedAiPayload($selected);
                                    $aiProducts = $aiPayload['productos'];
                                    $aiStock = $aiPayload['stock'];
                                    $aiStockItems = is_array($aiStock['items'] ?? null) ? $aiStock['items'] : [];
                                    $stockState = (string)($aiStock['estado'] ?? '');
                                    $stockClass = $stockState === 'cubre_completo' ? 'ok' : (in_array($stockState, ['no_cubre_completo', 'sin_productos'], true) ? 'bad' : 'warn');
                                    $stockLabel = [
                                        'cubre_completo' => 'Stock cubre el pedido',
                                        'no_cubre_completo' => 'Stock insuficiente o producto no encontrado',
                                        'requiere_cantidad' => 'Falta cantidad para validar stock',
                                        'sin_productos' => 'Sin productos para validar',
                                    ][$stockState] ?? 'Stock pendiente';
                                    if (!$aiProducts) {
                                        $aiProducts[] = ['producto' => (string)($selected['ai_product'] ?? ''), 'cantidad_texto' => (string)($selected['ai_quantity'] ?? ''), 'unidad' => ''];
                                    }
                                ?>
                                <div class="ai-summary-row">
                                    <span class="ai-chip <?= ((int)($selected['ai_is_order'] ?? 0) === 1) ? 'ok' : 'warn' ?>">Pedido: <?= ((int)($selected['ai_is_order'] ?? 0) === 1) ? 'Si' : 'Por confirmar' ?></span>
                                    <span class="ai-chip"><?= htmlspecialchars((string)($selected['ai_category'] ?? 'sin_categoria')) ?></span>
                                    <span class="ai-chip"><?= htmlspecialchars((string)($selected['ai_processing_status'] ?? 'requiere_revision')) ?></span>
                                    <span class="ai-chip <?= $stockClass ?>"><?= htmlspecialchars($stockLabel) ?></span>
                                </div>
                                <?php if (!empty($aiPayload['alertas'])): ?>
                                    <div class="ai-alerts">
                                        <?php foreach ($aiPayload['alertas'] as $alert): ?>
                                            <div class="ai-alert"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars((string)$alert) ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="ai-quick-review">
                                    <div class="ai-quick-head">
                                        <div>
                                            <h5>Resumen del pedido detectado</h5>
                                            <p>Vista compacta con los campos actuales y comparativa rapida de inventario.</p>
                                        </div>
                                        <?php if (!empty($selected['confirmed_pedido_id'])): ?>
                                            <span class="ai-chip ok"><i class="fa-solid fa-receipt"></i> Confirmado</span>
                                        <?php endif; ?>
                                    </div>
                                    <table class="ai-compact-table">
                                        <tbody>
                                            <tr>
                                                <th>Cliente</th>
                                                <td><?= htmlspecialchars((string)($selected['ai_customer'] ?: 'Por definir')) ?></td>
                                                <th>Entrega</th>
                                                <td><?= htmlspecialchars((string)($selected['ai_delivery_date_value'] ?: $selected['ai_delivery_date'] ?: 'Por definir')) ?></td>
                                                <th>Confianza</th>
                                                <td><?= htmlspecialchars((string)($selected['ai_confidence'] ?? '')) ?><?= $selected['ai_confidence'] !== null ? '%' : 'Sin dato' ?></td>
                                            </tr>
                                            <tr>
                                                <th>Categoria</th>
                                                <td><?= htmlspecialchars((string)($selected['ai_category'] ?: 'sin_categoria')) ?></td>
                                                <th>Estado IA</th>
                                                <td><?= htmlspecialchars((string)($selected['ai_processing_status'] ?: 'requiere_revision')) ?></td>
                                                <th>Stock</th>
                                                <td class="<?= $stockClass === 'ok' ? 'ai-stock-ok' : ($stockClass === 'bad' ? 'ai-stock-bad' : 'ai-stock-warn') ?>"><?= htmlspecialchars($stockLabel) ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <table class="ai-compact-table ai-product-table">
                                        <thead>
                                            <tr>
                                                <th>Producto</th>
                                                <th>Cant.</th>
                                                <th>Stock</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($aiProducts as $idx => $item): ?>
                                            <?php
                                                $stockItem = $aiStockItems[$idx] ?? [];
                                                $status = (string)($stockItem['status'] ?? '');
                                                $stockTone = $status === 'cubre_completo' ? 'ok' : (in_array($status, ['parcial', 'sin_stock', 'producto_no_encontrado'], true) ? 'bad' : 'warn');
                                                $stockText = 'Pendiente';
                                                if ($status === 'cubre_completo') {
                                                    $stockText = 'Cubre: ' . ($stockItem['stock_disponible'] ?? '-') . ' ' . ($stockItem['unidad_stock'] ?? '');
                                                } elseif ($status === 'parcial') {
                                                    $stockText = 'Faltan ' . ($stockItem['faltante'] ?? '-') . ' / Disp. ' . ($stockItem['stock_disponible'] ?? '-');
                                                } elseif ($status === 'sin_stock') {
                                                    $stockText = 'Sin stock';
                                                } elseif ($status === 'producto_no_encontrado') {
                                                    $stockText = 'No encontrado';
                                                } elseif ($status === 'cantidad_faltante') {
                                                    $stockText = 'Falta cantidad';
                                                }
                                            ?>
                                            <tr>
                                                <td class="product-name" title="<?= htmlspecialchars(aiText($item['producto'] ?? 'Producto sin nombre')) ?>"><?= htmlspecialchars(aiText($item['producto'] ?? 'Producto sin nombre')) ?></td>
                                                <td><?= htmlspecialchars(aiText($item['cantidad_texto'] ?? ($item['cantidad'] ?? ''))) ?> <?= htmlspecialchars(aiText($item['unidad'] ?? '')) ?></td>
                                                <td class="<?= $stockTone === 'ok' ? 'ai-stock-ok' : ($stockTone === 'bad' ? 'ai-stock-bad' : 'ai-stock-warn') ?>"><?= htmlspecialchars($stockText) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php if (trim((string)($selected['ai_observations'] ?? '')) !== ''): ?>
                                        <div class="ai-quick-item">
                                            <span class="ai-quick-label">Observaciones</span>
                                            <span class="ai-quick-value"><?= htmlspecialchars((string)$selected['ai_observations']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                    <div class="ai-modal-backdrop js-ai-modal-backdrop"></div>
                                    <section class="ai-review-modal js-ai-review-modal" aria-hidden="true">
                                        <div class="ai-modal-header">
                                            <div>
                                                <h5>Confirmar pedido extraido por IA</h5>
                                                <p>Si todo esta bien, acepta. Si algo esta mal, edita los campos completos.</p>
                                            </div>
                                            <button class="ai-modal-close js-close-ai-modal" type="button"><i class="fa-solid fa-xmark"></i></button>
                                        </div>
                                        <div class="ai-modal-body">
                                            <div class="ai-summary-row">
                                                <span class="ai-chip <?= ((int)($selected['ai_is_order'] ?? 0) === 1) ? 'ok' : 'warn' ?>">Pedido: <?= ((int)($selected['ai_is_order'] ?? 0) === 1) ? 'Si' : 'Por confirmar' ?></span>
                                                <span class="ai-chip"><?= htmlspecialchars((string)($selected['ai_category'] ?? 'sin_categoria')) ?></span>
                                                <span class="ai-chip"><?= htmlspecialchars((string)($selected['ai_processing_status'] ?? 'requiere_revision')) ?></span>
                                                <span class="ai-chip <?= $stockClass ?>"><?= htmlspecialchars($stockLabel) ?></span>
                                            </div>
                                            <div class="ai-quick-products">
                                                <?php foreach ($aiProducts as $idx => $item): ?>
                                                    <?php
                                                        $stockItem = $aiStockItems[$idx] ?? [];
                                                        $status = (string)($stockItem['status'] ?? '');
                                                        $stockTone = $status === 'cubre_completo' ? 'ok' : (in_array($status, ['parcial', 'sin_stock', 'producto_no_encontrado'], true) ? 'bad' : 'warn');
                                                        $stockText = $status === 'cubre_completo'
                                                            ? 'Cubre: ' . ($stockItem['stock_disponible'] ?? '-') . ' ' . ($stockItem['unidad_stock'] ?? '')
                                                            : ($status === 'parcial' ? 'Faltan ' . ($stockItem['faltante'] ?? '-') . ' / Disp. ' . ($stockItem['stock_disponible'] ?? '-') : ($status ?: 'Pendiente'));
                                                    ?>
                                                    <div class="ai-quick-product">
                                                        <strong><?= htmlspecialchars(aiText($item['producto'] ?? 'Producto sin nombre')) ?></strong>
                                                        <span><?= htmlspecialchars(aiText($item['cantidad_texto'] ?? ($item['cantidad'] ?? ''))) ?> <?= htmlspecialchars(aiText($item['unidad'] ?? '')) ?></span>
                                                        <span class="<?= $stockTone ?>"><?= htmlspecialchars($stockText) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="ai-modal-actions">
                                                <?php if (!empty($selected['confirmed_pedido_id'])): ?>
                                                    <span class="ai-chip ok"><i class="fa-solid fa-receipt"></i> Pedido confirmado #<?= (int)$selected['confirmed_pedido_id'] ?></span>
                                                    <button class="btn-ai edit-away js-open-ai-edit" type="button"><i class="fa-solid fa-pen-to-square"></i> Editar</button>
                                                    <button
                                                        class="btn-ai confirm js-resend-confirmation-btn"
                                                        name="ai_decision"
                                                        value="resend_confirmation"
                                                        type="submit"
                                                        onclick="return confirm('Se reenviara el correo de confirmacion sin crear otro pedido ni descontar inventario. Continuar?');"
                                                    ><i class="fa-solid fa-paper-plane"></i> Reenviar correo</button>
                                                <?php else: ?>
                                                    <button class="btn-ai confirm js-confirm-order-btn" name="ai_decision" value="confirm_order" type="submit"><i class="fa-solid fa-check"></i> Aceptar</button>
                                                    <button class="btn-ai cancel-away" name="ai_decision" value="discard" type="submit"><i class="fa-solid fa-ban"></i> Cancelar</button>
                                                    <button class="btn-ai edit-away js-open-ai-edit" type="button"><i class="fa-solid fa-pen-to-square"></i> Editar</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </section>
                                <section class="ai-edit-modal js-ai-edit-modal" aria-hidden="true">
                                    <div class="ai-modal-header">
                                        <div>
                                            <h5>Editar extraccion completa</h5>
                                            <p>Estos son los mismos campos actuales; corrige lo necesario y guarda o confirma.</p>
                                        </div>
                                        <button class="ai-modal-close js-close-ai-modal" type="button"><i class="fa-solid fa-xmark"></i></button>
                                    </div>
                                    <div class="ai-modal-body">
                                <div class="ai-products">
                                    <?php foreach ($aiProducts as $idx => $item): ?>
                                        <?php
                                            $stockItem = $aiStockItems[$idx] ?? [];
                                            $status = (string)($stockItem['status'] ?? '');
                                            $noteClass = $status === 'cubre_completo' ? 'ok' : (in_array($status, ['parcial', 'sin_stock', 'producto_no_encontrado'], true) ? 'bad' : 'warn');
                                            $note = 'Pendiente de cotejo';
                                            if ($status === 'cubre_completo') {
                                                $note = 'Cubre: ' . ($stockItem['stock_disponible'] ?? '-') . ' ' . ($stockItem['unidad_stock'] ?? '');
                                            } elseif ($status === 'parcial') {
                                                $note = 'Faltan ' . ($stockItem['faltante'] ?? '-') . '. Disponible: ' . ($stockItem['stock_disponible'] ?? '-');
                                            } elseif ($status === 'sin_stock') {
                                                $note = 'Sin stock disponible';
                                            } elseif ($status === 'producto_no_encontrado') {
                                                $note = 'No encontrado en catalogo';
                                            } elseif ($status === 'cantidad_faltante') {
                                                $note = 'Producto encontrado, falta cantidad';
                                            }
                                            $unitOptions = ['piezas', 'kg', 'toneladas', 'cajas', 'bultos', 'litros', 'otro'];
                                            $unitValue = normalizeUnitSlug(aiText($item['unidad_slug'] ?? ($item['unidad'] ?? '')));
                                        ?>
                                        <div class="ai-product-row">
                                            <div class="ai-field">
                                                <label>Producto <?= $idx + 1 ?></label>
                                                <input name="ai_item_name[]" value="<?= htmlspecialchars(aiText($item['producto'] ?? '')) ?>" placeholder="Producto solicitado">
                                            </div>
                                            <div class="ai-field">
                                                <label>Cantidad</label>
                                                <input name="ai_item_quantity[]" value="<?= htmlspecialchars(aiText($item['cantidad_texto'] ?? ($item['cantidad'] ?? ''))) ?>" placeholder="15">
                                            </div>
                                            <div class="ai-field">
                                                <label>Unidad</label>
                                                <select name="ai_item_unit[]">
                                                    <?php foreach ($unitOptions as $unitOption): ?>
                                                        <option value="<?= htmlspecialchars($unitOption) ?>" <?= $unitValue === $unitOption ? 'selected' : '' ?>><?= htmlspecialchars($unitOption) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="stock-note <?= $noteClass ?>">
                                                <strong><?= htmlspecialchars($note) ?></strong>
                                                <?= htmlspecialchars((string)($stockItem['producto_catalogo'] ?? '')) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
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
                                        <label>Fecha entrega</label>
                                        <?php
                                            $deliveryTextValue = (string)($selected['ai_delivery_date'] ?? '');
                                            $deliveryDateValue = (string)($selected['ai_delivery_date_value'] ?? '');
                                            if ($deliveryDateValue === '' && trim($deliveryTextValue) !== '') {
                                                $deliveryDateValue = resolveSpanishRelativeDate($deliveryTextValue, (string)($selected['received_at'] ?: $selected['fetched_at'] ?: ''));
                                            }
                                            $deliveryType = (string)($selected['ai_delivery_type'] ?? '');
                                            if ($deliveryType === '' || $deliveryType === 'sin_fecha') {
                                                $deliveryType = $deliveryDateValue !== '' ? (isRelativeDeliveryText($deliveryTextValue) ? 'fecha_relativa' : 'fecha_exacta') : 'sin_fecha';
                                            }
                                        ?>
                                        <input name="ai_delivery_date_value" type="date" value="<?= htmlspecialchars($deliveryDateValue) ?>">
                                    </div>
                                    <div class="ai-field">
                                        <label>Hora entrega</label>
                                        <input name="ai_delivery_time_value" type="time" step="60" value="<?= htmlspecialchars(substr((string)($selected['ai_delivery_time_value'] ?? ''), 0, 5)) ?>">
                                    </div>
                                    <div class="ai-field">
                                        <label>Tipo entrega</label>
                                        <select name="ai_delivery_type">
                                            <?php foreach (['fecha_exacta','fecha_relativa','ventana','sin_fecha'] as $deliveryTypeOption): ?>
                                                <option value="<?= htmlspecialchars($deliveryTypeOption) ?>" <?= $deliveryType === $deliveryTypeOption ? 'selected' : '' ?>><?= htmlspecialchars($deliveryTypeOption) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="ai-field full">
                                        <label>Texto original de entrega</label>
                                        <input name="ai_delivery_date" value="<?= htmlspecialchars($deliveryTextValue) ?>" placeholder="Ej: viernes de esta semana">
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
                                        <label>Categoria</label>
                                        <input name="ai_category" value="<?= htmlspecialchars((string)($selected['ai_category'] ?? '')) ?>" placeholder="pedido_compra">
                                    </div>
                                    <div class="ai-field">
                                        <label>Estado IA</label>
                                        <select name="ai_processing_status">
                                            <?php $aiStatus = (string)($selected['ai_processing_status'] ?? 'requiere_revision'); ?>
                                            <?php foreach (['listo_para_capturar','requiere_revision','incompleto','no_es_pedido'] as $statusOption): ?>
                                                <option value="<?= htmlspecialchars($statusOption) ?>" <?= $aiStatus === $statusOption ? 'selected' : '' ?>><?= htmlspecialchars($statusOption) ?></option>
                                            <?php endforeach; ?>
                                        </select>
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
                                        <label>Alertas IA</label>
                                        <textarea name="ai_alerts" placeholder="Una alerta por linea"><?= htmlspecialchars(implode("\n", $aiPayload['alertas'])) ?></textarea>
                                    </div>
                                    <div class="ai-field full">
                                        <label>Nota operativa para revision</label>
                                        <textarea name="review_note" placeholder="Ej: validar inventario, confirmar tonelaje, solicitar factura"><?= htmlspecialchars((string)($selected['review_note'] ?? '')) ?></textarea>
                                    </div>
                                </div>
                                <?php if (!empty($selected['confirmed_pedido_id'])): ?>
                                    <div class="ai-summary-row">
                                        <span class="ai-chip ok"><i class="fa-solid fa-receipt"></i> Pedido confirmado #<?= (int)$selected['confirmed_pedido_id'] ?></span>
                                        <span class="ai-chip <?= (string)($selected['confirmation_email_status'] ?? '') === 'enviado' ? 'ok' : 'warn' ?>">
                                            Correo: <?= htmlspecialchars((string)($selected['confirmation_email_status'] ?? 'pendiente')) ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($selected['confirmation_email_error'])): ?>
                                        <div class="ai-alert"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars((string)$selected['confirmation_email_error']) ?></div>
                                    <?php endif; ?>
                                    <div class="ai-actions">
                                        <button class="btn-ai" name="ai_decision" value="save" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar correcciones</button>
                                        <button
                                            class="btn-ai confirm js-resend-confirmation-btn"
                                            name="ai_decision"
                                            value="resend_confirmation"
                                            type="submit"
                                            onclick="return confirm('Se reenviara el correo de confirmacion sin crear otro pedido ni descontar inventario. Continuar?');"
                                        ><i class="fa-solid fa-paper-plane"></i> Reenviar correo de confirmacion</button>
                                    </div>
                                <?php else: ?>
                                    <div class="ai-actions">
                                        <button class="btn-ai" name="ai_decision" value="save" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar correcciones</button>
                                        <button class="btn-ai accept" name="ai_decision" value="accept" type="submit"><i class="fa-solid fa-check"></i> Aceptar como candidato</button>
                                        <button
                                            class="btn-ai confirm js-confirm-order-btn"
                                            name="ai_decision"
                                            value="confirm_order"
                                            type="submit"
                                            onclick="return confirm('Esto guardara las correcciones, creara el pedido, descontara inventario y enviara el correo de confirmacion al cliente. Continuar?');"
                                        ><i class="fa-solid fa-paper-plane"></i> Confirmar pedido y enviar correo</button>
                                        <button class="btn-ai discard" name="ai_decision" value="discard" type="submit"><i class="fa-solid fa-ban"></i> Descartar</button>
                                    </div>
                                <?php endif; ?>
                                    </div>
                                </section>
                            </form>
                        <?php endif; ?>
                    </section>

                    <?php $currentStatus = (string)($selected['review_status'] ?: 'pendiente'); ?>
                    <div class="review-compact">
                        <div class="review-compact-info">
                            <strong>Acciones de revision:</strong>
                            <span class="<?= statusBadge($currentStatus) ?>"><?= htmlspecialchars($currentStatus) ?></span>
                            <span class="<?= (int)$selected['is_unseen'] === 0 ? 'status-pill status-ok' : 'status-pill status-pending' ?>">
                                <?= (int)$selected['is_unseen'] === 0 ? 'Leido' : 'No leido' ?>
                            </span>
                            <?php if (trim((string)($selected['review_note'] ?? '')) !== ''): ?>
                                <span class="review-note-preview"><?= htmlspecialchars((string)$selected['review_note']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="review-compact-actions">
                            <?php if (!empty($selected['confirmed_pedido_id'])): ?>
                                <span class="ai-chip ok"><i class="fa-solid fa-receipt"></i> Confirmado</span>
                            <?php endif; ?>
                            <?php if ($hasAiExtraction): ?>
                                <button class="btn-ai primary js-open-ai-review" type="button"><i class="fa-solid fa-clipboard-check"></i> Revisar pedido</button>
                            <?php else: ?>
                                <span class="ai-chip warn">Sin prellenado IA</span>
                            <?php endif; ?>
                            <button class="btn-ai" type="button" id="openReviewStatusModal"><i class="fa-solid fa-pen-to-square"></i> Editar estado</button>
                        </div>
                    </div>
                    <div class="mail-modal-backdrop" id="reviewStatusBackdrop"></div>
                    <section class="mail-modal" id="reviewStatusModal" aria-hidden="true">
                        <div class="mail-modal-header">
                            <h3 class="mail-modal-title">Editar estado de revision</h3>
                            <button class="mail-modal-close" type="button" id="closeReviewStatusModal"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <div class="mail-modal-body">
                            <form method="post" class="review-form">
                                <input type="hidden" name="action" value="update_review">
                                <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>">
                                <label>Estado de revision</label>
                                <select class="form-select review-select" name="review_status">
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
                        </div>
                    </section>
                <?php endif; ?>
                </div>
            </article>
        </section>
    </div>
</main>
<div class="ai-loading-backdrop" id="aiLoadingBackdrop"></div>
<section class="ai-loading-modal" id="aiLoadingModal" role="alertdialog" aria-modal="true" aria-hidden="true" aria-labelledby="aiLoadingTitle">
    <div class="ai-loading-top">
        <div class="ai-loading-orbit"><i class="fa-solid fa-circle-notch"></i></div>
        <div>
            <h3 class="ai-loading-title" id="aiLoadingTitle">Analizando pedido con IA</h3>
            <p class="ai-loading-copy">Estamos leyendo el correo, extrayendo productos y validando existencias contra inventario.</p>
        </div>
    </div>
    <div class="ai-loading-body">
        <ul class="ai-loading-steps">
            <li><span><i class="fa-solid fa-envelope-open-text"></i></span> Interpretando el mensaje y detectando si es pedido</li>
            <li><span><i class="fa-solid fa-boxes-stacked"></i></span> Separando productos, cantidades y unidades</li>
            <li><span><i class="fa-solid fa-warehouse"></i></span> Comparando contra catalogo y stock disponible</li>
        </ul>
        <div class="ai-loading-bar" aria-hidden="true"></div>
        <p class="ai-loading-foot">Puede tardar unos segundos si Ollama esta iniciando o el correo viene largo.</p>
    </div>
</section>
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
    const loadingModal = document.getElementById('aiLoadingModal');
    const loadingBackdrop = document.getElementById('aiLoadingBackdrop');
    const forms = document.querySelectorAll('.js-ai-analyze-form');
    if (!loadingModal || !loadingBackdrop || forms.length === 0) return;

    const showAiLoading = (form) => {
        loadingModal.classList.add('show');
        loadingBackdrop.classList.add('show');
        loadingModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        const button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Analizando...';
        }
    };

    forms.forEach((form) => {
        form.addEventListener('submit', () => showAiLoading(form));
    });
})();

(() => {
    const openBtn = document.getElementById('openReviewStatusModal');
    const closeBtn = document.getElementById('closeReviewStatusModal');
    const modal = document.getElementById('reviewStatusModal');
    const backdrop = document.getElementById('reviewStatusBackdrop');
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
    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeModal();
    });
})();

(() => {
    const openBtn = document.getElementById('openOriginalMailModal');
    const closeBtn = document.getElementById('closeOriginalMailModal');
    const modal = document.getElementById('originalMailModal');
    const backdrop = document.getElementById('originalMailBackdrop');
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
    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeModal();
    });
})();

(() => {
    document.querySelectorAll('.js-ai-save-form').forEach((form) => {
        const backdrop = form.querySelector('.js-ai-modal-backdrop');
        const reviewModal = form.querySelector('.js-ai-review-modal');
        const editModal = form.querySelector('.js-ai-edit-modal');
        const openEditBtns = form.querySelectorAll('.js-open-ai-edit');
        const closeBtns = form.querySelectorAll('.js-close-ai-modal');
        if (!backdrop || !reviewModal) return;

        const closeAll = () => {
            backdrop.classList.remove('show');
            reviewModal.classList.remove('show');
            editModal?.classList.remove('show');
            reviewModal.setAttribute('aria-hidden', 'true');
            editModal?.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };
        const openReview = () => {
            backdrop.classList.add('show');
            reviewModal.classList.add('show');
            editModal?.classList.remove('show');
            reviewModal.setAttribute('aria-hidden', 'false');
            editModal?.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = 'hidden';
        };
        const openEdit = () => {
            backdrop.classList.add('show');
            reviewModal.classList.remove('show');
            editModal?.classList.add('show');
            reviewModal.setAttribute('aria-hidden', 'true');
            editModal?.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        };

        const panel = form.closest('.mail-panel') || document;
        panel.querySelectorAll('.js-open-ai-review').forEach((button) => {
            button.addEventListener('click', openReview);
        });
        openEditBtns.forEach((button) => button.addEventListener('click', openEdit));
        closeBtns.forEach((button) => button.addEventListener('click', closeAll));
        backdrop.addEventListener('click', closeAll);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeAll();
        });
    });
})();

(() => {
    document.querySelectorAll('.js-ai-save-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const submitter = event.submitter;
            if (!submitter || !['confirm_order', 'resend_confirmation'].includes(submitter.value)) return;

            let decisionInput = form.querySelector('input[name="ai_decision"][data-confirm-shadow="1"]');
            if (!decisionInput) {
                decisionInput = document.createElement('input');
                decisionInput.type = 'hidden';
                decisionInput.name = 'ai_decision';
                decisionInput.dataset.confirmShadow = '1';
                form.appendChild(decisionInput);
            }
            decisionInput.value = submitter.value;
            submitter.disabled = true;
            submitter.innerHTML = submitter.value === 'confirm_order'
                ? '<i class="fa-solid fa-circle-notch fa-spin"></i> Confirmando y enviando...'
                : '<i class="fa-solid fa-circle-notch fa-spin"></i> Reenviando correo...';
            form.querySelectorAll('button').forEach((button) => {
                if (button !== submitter) button.disabled = true;
            });
        });
    });
})();

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
