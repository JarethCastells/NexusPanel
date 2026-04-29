<?php

function securityEnsureLoginThrottleTable(PDO $pdo): void {
    static $ready = false;
    if ($ready) return;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_throttles (
            bucket VARCHAR(190) PRIMARY KEY,
            hits INT NOT NULL DEFAULT 0,
            window_start DATETIME NOT NULL,
            blocked_until DATETIME NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $ready = true;
}

function securityClientIp(): string {
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
    ];

    foreach ($candidates as $candidate) {
        if (!$candidate) continue;
        $ip = trim(explode(',', $candidate)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }

    return '0.0.0.0';
}

function securityRateLimitCheck(
    PDO $pdo,
    string $bucket,
    int $maxHits,
    int $windowSeconds,
    int $blockSeconds
): array {
    securityEnsureLoginThrottleTable($pdo);
    $now = time();

    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare("SELECT hits, window_start, blocked_until FROM login_throttles WHERE bucket=? FOR UPDATE");
        $st->execute([$bucket]);
        $row = $st->fetch();

        if (!$row) {
            $pdo->prepare("INSERT INTO login_throttles (bucket, hits, window_start, blocked_until) VALUES (?, 0, NOW(), NULL)")
                ->execute([$bucket]);
            $row = ['hits' => 0, 'window_start' => date('Y-m-d H:i:s', $now), 'blocked_until' => null];
        }

        $hits = (int)$row['hits'];
        $windowStart = strtotime((string)$row['window_start']) ?: $now;
        $blockedUntilTs = $row['blocked_until'] ? (strtotime((string)$row['blocked_until']) ?: $now) : null;

        if ($blockedUntilTs && $blockedUntilTs > $now) {
            $retry = max(1, $blockedUntilTs - $now);
            $pdo->commit();
            return ['allowed' => false, 'retry_after' => $retry];
        }

        if (($now - $windowStart) > $windowSeconds) {
            $hits = 0;
            $windowStart = $now;
            $blockedUntilTs = null;
        }

        $hits++;
        $blockedUntilSql = null;
        $allowed = true;
        $retryAfter = 0;

        if ($hits > $maxHits) {
            $allowed = false;
            $retryAfter = max(1, $blockSeconds);
            $blockedUntilTs = $now + $blockSeconds;
            $blockedUntilSql = date('Y-m-d H:i:s', $blockedUntilTs);
        }

        $upd = $pdo->prepare("UPDATE login_throttles SET hits=?, window_start=?, blocked_until=? WHERE bucket=?");
        $upd->execute([
            $hits,
            date('Y-m-d H:i:s', $windowStart),
            $blockedUntilSql,
            $bucket
        ]);

        $pdo->commit();
        return ['allowed' => $allowed, 'retry_after' => $retryAfter];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['allowed' => true, 'retry_after' => 0];
    }
}

function securityRateLimitReset(PDO $pdo, string $bucket): void {
    securityEnsureLoginThrottleTable($pdo);
    $pdo->prepare("DELETE FROM login_throttles WHERE bucket=?")->execute([$bucket]);
}

function securityTurnstileEnabled(): bool {
    return defined('LOGIN_TURNSTILE_SITE_KEY') && defined('LOGIN_TURNSTILE_SECRET_KEY')
        && LOGIN_TURNSTILE_SITE_KEY !== '' && LOGIN_TURNSTILE_SECRET_KEY !== '';
}

function securityVerifyTurnstile(string $token, string $remoteIp): array {
    if (!securityTurnstileEnabled()) {
        return ['ok' => true, 'error' => ''];
    }

    if (trim($token) === '') {
        return ['ok' => false, 'error' => 'Completa la verificacion de seguridad (No soy un robot).'];
    }

    $postData = http_build_query([
        'secret' => LOGIN_TURNSTILE_SECRET_KEY,
        'response' => $token,
        'remoteip' => $remoteIp
    ]);

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $postData,
            'timeout' => 8
        ]
    ]);

    $raw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
    if ($raw === false) {
        return ['ok' => false, 'error' => 'No se pudo validar el captcha. Intenta de nuevo.'];
    }

    $json = json_decode($raw, true);
    if (!is_array($json) || !($json['success'] ?? false)) {
        return ['ok' => false, 'error' => 'Captcha invalido. Intenta de nuevo.'];
    }

    return ['ok' => true, 'error' => ''];
}
