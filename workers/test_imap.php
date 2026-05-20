<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . '/imap_reader.php';

try {
    $result = readInboxMessages($pdo, 30);
    echo "IMAP OK\n";
    echo "Guardados: " . (int)$result['saved'] . "\n";
    echo "Omitidos: " . (int)$result['skipped'] . "\n";
    echo "Mensajes:\n";

    foreach ($result['items'] as $item) {
        echo "- UID " . $item['uid']
            . " | " . ($item['received_at'] ?? '-')
            . " | " . ($item['from'] ?? '-')
            . " | " . ($item['subject'] ?? '-') . "\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "IMAP ERROR\n";
    echo $e->getMessage() . "\n";
}
