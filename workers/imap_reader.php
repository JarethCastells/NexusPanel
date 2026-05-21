<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

function mailConfigReady(): bool
{
    return MAIL_IMAP_HOST !== '' && MAIL_IMAP_USER !== '' && MAIL_IMAP_PASS !== '';
}

function getActiveMailAccounts(PDO $pdo): array
{
    if (!function_exists('tableExists') || !tableExists($pdo, 'mail_accounts')) {
        return [];
    }
    $st = $pdo->query("
        SELECT *
        FROM mail_accounts
        WHERE is_active = 1
        ORDER BY id ASC
        LIMIT 2
    ");
    return $st ? ($st->fetchAll() ?: []) : [];
}

function bootstrapEnvMailAccount(PDO $pdo): void
{
    if (!function_exists('tableExists') || !tableExists($pdo, 'mail_accounts')) {
        return;
    }
    if (!mailConfigReady()) {
        return;
    }
    $count = (int)$pdo->query("SELECT COUNT(*) FROM mail_accounts")->fetchColumn();
    if ($count > 0) {
        return;
    }
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

function decodeMimeHeader(?string $value): string
{
    $value = (string)$value;
    if ($value === '') {
        return '';
    }

    $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
    if ($decoded === false || $decoded === null) {
        return trim($value);
    }
    return trim($decoded);
}

function normalizeDate(?string $date): ?string
{
    $date = trim((string)$date);
    if ($date === '') {
        return null;
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d H:i:s', $ts);
}

function fetchBodyText($imap, int $msgNo): array
{
    $structure = @imap_fetchstructure($imap, $msgNo);
    $bodyText = '';
    $bodyHtml = '';

    if (!$structure) {
        $raw = @imap_body($imap, $msgNo) ?: '';
        return [trim(strip_tags($raw)), ''];
    }

    if (!empty($structure->parts) && is_array($structure->parts)) {
        foreach ($structure->parts as $index => $part) {
            $partNo = (string)($index + 1);
            $data = @imap_fetchbody($imap, $msgNo, $partNo) ?: '';
            $enc = (int)($part->encoding ?? 0);

            if ($enc === ENCBASE64) {
                $data = base64_decode($data, true) ?: '';
            } elseif ($enc === ENCQUOTEDPRINTABLE) {
                $data = quoted_printable_decode($data);
            }

            $subtype = strtoupper((string)($part->subtype ?? ''));
            $type = (int)($part->type ?? -1);
            if ($type === TYPEMULTIPART) {
                continue;
            }
            if ($type === TYPETEXT && $subtype === 'PLAIN' && $bodyText === '') {
                $bodyText = trim((string)$data);
            }
            if ($type === TYPETEXT && $subtype === 'HTML' && $bodyHtml === '') {
                $bodyHtml = trim((string)$data);
            }
        }
    } else {
        $raw = @imap_body($imap, $msgNo) ?: '';
        $enc = (int)($structure->encoding ?? 0);
        if ($enc === ENCBASE64) {
            $raw = base64_decode($raw, true) ?: '';
        } elseif ($enc === ENCQUOTEDPRINTABLE) {
            $raw = quoted_printable_decode($raw);
        }
        $subtype = strtoupper((string)($structure->subtype ?? 'PLAIN'));
        if ($subtype === 'HTML') {
            $bodyHtml = trim($raw);
        } else {
            $bodyText = trim($raw);
        }
    }

    if ($bodyText === '' && $bodyHtml !== '') {
        $bodyText = trim(html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    return [$bodyText, $bodyHtml];
}

function readInboxMessagesFromAccount(PDO $pdo, array $account, int $limit = 25): array
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
    $onlyUnseen = (int)($account['imap_only_unseen'] ?? 1) === 1;

    if ($host === '' || $user === '' || $pass === '') {
        throw new RuntimeException('Cuenta de correo incompleta para IMAP.');
    }

    $mailbox = sprintf(
        '{%s:%d/imap/%s}%s',
        $host,
        $port,
        $secure ? 'ssl' : 'notls',
        $mailboxName
    );

    $imap = @imap_open($mailbox, $user, $pass, 0, 1);
    if ($imap === false) {
        $err = imap_last_error() ?: 'Error desconocido';
        throw new RuntimeException('No se pudo abrir IMAP: ' . $err);
    }

    $criteria = $onlyUnseen ? 'UNSEEN' : 'ALL';
    $uids = @imap_search($imap, $criteria, SE_UID);
    if ($uids === false || empty($uids)) {
        $msgNos = @imap_search($imap, $criteria);
        if ($msgNos === false || empty($msgNos)) {
            imap_close($imap);
            return ['saved' => 0, 'skipped' => 0, 'items' => []];
        }
        $uids = [];
        foreach ($msgNos as $msgNo) {
            $uid = imap_uid($imap, (int)$msgNo);
            if ($uid > 0) {
                $uids[] = $uid;
            }
        }
        if (empty($uids)) {
            imap_close($imap);
            return ['saved' => 0, 'skipped' => 0, 'items' => []];
        }
    }

    rsort($uids, SORT_NUMERIC);
    $uids = array_slice($uids, 0, max(1, $limit));

    $insert = $pdo->prepare("
        INSERT INTO email_inbox_messages
        (message_id, imap_uid, from_email, from_name, subject, body_text, body_html, received_at, is_unseen, source_mailbox, raw_headers)
        VALUES (:message_id, :imap_uid, :from_email, :from_name, :subject, :body_text, :body_html, :received_at, :is_unseen, :source_mailbox, :raw_headers)
        ON DUPLICATE KEY UPDATE
            is_unseen = VALUES(is_unseen),
            fetched_at = CURRENT_TIMESTAMP
    ");

    $saved = 0;
    $skipped = 0;
    $items = [];

    foreach ($uids as $uid) {
        $msgNo = imap_msgno($imap, (int)$uid);
        if ($msgNo <= 0) {
            $skipped++;
            continue;
        }

        $overviewList = @imap_fetch_overview($imap, (string)$uid, FT_UID) ?: [];
        $overview = $overviewList[0] ?? null;
        $headers = @imap_fetchheader($imap, $msgNo) ?: '';
        [$bodyText, $bodyHtml] = fetchBodyText($imap, $msgNo);

        $messageId = trim((string)($overview->message_id ?? ''));
        if ($messageId === '') {
            $messageId = 'uid-' . $uid . '-' . md5((string)$headers);
        }

        $fromRaw = decodeMimeHeader((string)($overview->from ?? ''));
        $fromEmail = null;
        $fromName = null;
        if (preg_match('/<([^>]+)>/', $fromRaw, $m)) {
            $fromEmail = strtolower(trim($m[1]));
            $fromName = trim(str_replace($m[0], '', $fromRaw), " \t\n\r\0\x0B\"'");
        } elseif (filter_var($fromRaw, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = strtolower($fromRaw);
        }

        $subject = decodeMimeHeader((string)($overview->subject ?? ''));
        $receivedAt = normalizeDate((string)($overview->date ?? ''));
        $isUnseen = isset($overview->seen) ? ((int)$overview->seen === 0 ? 1 : 0) : 1;

        $insert->execute([
            ':message_id' => mb_substr($messageId, 0, 255),
            ':imap_uid' => (int)$uid,
            ':from_email' => $fromEmail !== null ? mb_substr($fromEmail, 0, 255) : null,
            ':from_name' => $fromName !== null ? mb_substr($fromName, 0, 255) : null,
            ':subject' => $subject !== '' ? mb_substr($subject, 0, 500) : null,
            ':body_text' => $bodyText !== '' ? $bodyText : null,
            ':body_html' => $bodyHtml !== '' ? $bodyHtml : null,
            ':received_at' => $receivedAt,
            ':is_unseen' => $isUnseen,
            ':source_mailbox' => (($account['email'] ?? $user) . ':' . $mailboxName),
            ':raw_headers' => $headers !== '' ? $headers : null,
        ]);

        if ($insert->rowCount() > 0) {
            $saved++;
        } else {
            $skipped++;
        }

        $items[] = [
            'uid' => (int)$uid,
            'from' => $fromEmail ?? $fromRaw,
            'subject' => $subject,
            'received_at' => $receivedAt,
        ];
    }

    imap_close($imap);
    return ['saved' => $saved, 'skipped' => $skipped, 'items' => $items];
}

function readInboxMessages(PDO $pdo, int $limit = 25): array
{
    bootstrapEnvMailAccount($pdo);
    $accounts = getActiveMailAccounts($pdo);
    if (empty($accounts)) {
        throw new RuntimeException('No hay cuentas activas en mail_accounts. Configura al menos una cuenta desde el modulo de correos.');
    }

    $saved = 0;
    $skipped = 0;
    $items = [];

    foreach ($accounts as $account) {
        $result = readInboxMessagesFromAccount($pdo, $account, $limit);
        $saved += (int)($result['saved'] ?? 0);
        $skipped += (int)($result['skipped'] ?? 0);
        foreach (($result['items'] ?? []) as $it) {
            $items[] = $it;
        }
    }

    return ['saved' => $saved, 'skipped' => $skipped, 'items' => $items];
}
