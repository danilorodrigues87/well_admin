<?php

/**
 * Diagnóstico Inter (VPS / Docker / Easypanel).
 * Uso no console do container:
 *   php database/scripts/inter_diagnose.php
 */

declare(strict_types=1);

require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\InterConfig;
use App\Service\Inter\InterService;

echo "=== Inter — diagnóstico ===\n";
echo 'PHP: '.PHP_VERSION."\n";
echo 'Raiz projeto: '.dirname(__DIR__, 2)."\n";
echo 'INTER_ENABLED: '.(InterConfig::isEnabled() ? 'true' : 'false')."\n";
echo 'INTER_ENV: '.InterConfig::env()."\n";
echo 'Base URL: '.InterConfig::baseUrl()."\n";
echo 'Client ID: '.(InterConfig::clientId() !== '' ? substr(InterConfig::clientId(), 0, 4).'…' : '(vazio)')."\n";
echo 'Client secret: '.(InterConfig::clientSecret() !== '' ? '(preenchido)' : '(vazio)')."\n";
echo 'Conta corrente: '.(InterConfig::contaCorrente() !== '' ? InterConfig::contaCorrente() : '(vazio)')."\n\n";

$cert = InterConfig::certPath();
$key = InterConfig::keyPath();
echo "Cert path: {$cert}\n";
echo '  exists: '.(is_file($cert) ? 'sim' : 'não').' · readable: '.(is_readable($cert) ? 'sim' : 'não');
if (is_file($cert)) {
    echo ' · size: '.filesize($cert).' bytes';
}
echo "\n";

echo "Key path: {$key}\n";
echo '  exists: '.(is_file($key) ? 'sim' : 'não').' · readable: '.(is_readable($key) ? 'sim' : 'não');
if (is_file($key)) {
    echo ' · size: '.filesize($key).' bytes';
}
echo "\n\n";

$interDir = dirname(__DIR__, 2).'/storage/inter';
echo "Conteúdo de storage/inter:\n";
if (!is_dir($interDir)) {
    echo "  (pasta não existe — crie storage/inter no volume persistente)\n";
} else {
    foreach (scandir($interDir) ?: [] as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        $p = $interDir.'/'.$f;
        echo '  - '.$f.(is_dir($p) ? '/' : '').' · '.(is_readable($p) ? 'ok' : 'sem leitura')."\n";
    }
}

echo "\nErros de configuração:\n";
$errors = InterConfig::configurationErrors();
if ($errors === []) {
    echo "  (nenhum — arquivos e .env OK)\n";
} else {
    foreach ($errors as $e) {
        echo '  - '.$e."\n";
    }
}

if (InterConfig::isConfigured()) {
    echo "\n--- Smoke OAuth (pode levar ~10s) ---\n";
    $smoke = InterService::smokeTestToken();
    echo ($smoke['ok'] ? '[OK] ' : '[FALHA] ').$smoke['message']."\n";
    if (!empty($smoke['details'])) {
        echo json_encode($smoke['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
    }
    exit($smoke['ok'] ? 0 : 1);
}

echo "\nCorrija os itens acima antes de emitir boletos.\n";
echo "Nomes exigidos: storage/inter/certificado.crt e storage/inter/chave.key\n";
exit(1);
