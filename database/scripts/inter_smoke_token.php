<?php

/**
 * Smoke test: OAuth2 + mTLS Banco Inter.
 * Uso: php database/scripts/inter_smoke_token.php
 */

require dirname(__DIR__, 2).'/includes/app.php';

use App\Service\Inter\InterService;

$result = InterService::smokeTestToken();

echo ($result['ok'] ? '[OK] ' : '[FALHA] ').$result['message'].PHP_EOL;
if (!empty($result['details'])) {
    echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
}

exit($result['ok'] ? 0 : 1);
