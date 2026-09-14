<?php

/**
 * Smoke test: Token de Integração → Token de Acesso (POST /token).
 * Uso: php database/scripts/sinir_smoke_token.php
 */

require dirname(__DIR__, 2).'/includes/app.php';

use App\Service\Sinir\SinirService;

$result = SinirService::smokeTestToken();

echo ($result['ok'] ? '[OK] ' : '[FALHA] ').$result['message'].PHP_EOL;
if (!empty($result['details'])) {
    echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
}

exit($result['ok'] ? 0 : 1);
