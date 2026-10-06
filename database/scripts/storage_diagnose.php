<?php

/**
 * Diagnóstico de storage (evidências / assinaturas / Inter) — VPS / EasyPanel.
 * Uso no console do container:
 *   php database/scripts/storage_diagnose.php
 */

declare(strict_types=1);

require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\StoragePath;
use App\Service\EvidenceStorageService;

echo "=== Storage — diagnóstico ===\n";
echo 'PHP: '.PHP_VERSION."\n";
echo 'Raiz projeto: '.StoragePath::projectRoot()."\n";

$diag = StoragePath::diagnose();
echo 'STORAGE_PATH env: '.(trim((string)(getenv('STORAGE_PATH') ?: '')) !== '' ? getenv('STORAGE_PATH') : '(não definido)')."\n";
echo 'Storage root: '.$diag['root']."\n";
echo '  origem: '.$diag['source']."\n";
echo '  existe: '.($diag['exists'] ? 'sim' : 'não')."\n";
echo '  gravável: '.($diag['writable'] ? 'sim' : 'não')."\n";

foreach (['coletas', 'inter', 'boletos', 'chamados'] as $sub) {
    $p = StoragePath::join($sub);
    echo "\n{$sub}/ → {$p}\n";
    echo '  existe: '.(is_dir($p) ? 'sim' : 'não');
    if (is_dir($p)) {
        echo ' · gravável: '.(is_writable($p) ? 'sim' : 'não');
    }
    echo "\n";
}

echo "\nGD / WebP:\n";
echo '  extension gd: '.(extension_loaded('gd') ? 'sim' : 'não')."\n";
echo '  imagewebp: '.(function_exists('imagewebp') ? 'sim' : 'não')."\n";
echo '  EvidenceStorageService::supportsWebp: '.(EvidenceStorageService::supportsWebp() ? 'sim' : 'não')."\n";

$probeDir = StoragePath::join('coletas');
$probeOk = false;
$probeMsg = '';
try {
    if (!is_dir($probeDir)) {
        mkdir($probeDir, 0755, true);
    }
    $probeFile = $probeDir.'/.write_probe_'.getmypid();
    if (file_put_contents($probeFile, 'ok') === false) {
        $probeMsg = 'file_put_contents falhou em '.$probeFile;
    } else {
        $probeOk = true;
        @unlink($probeFile);
        $probeMsg = 'escrita OK em coletas/';
    }
} catch (\Throwable $e) {
    $probeMsg = $e->getMessage();
}

echo "\nProbe escrita coletas/: ".($probeOk ? '[OK] ' : '[FALHA] ').$probeMsg."\n";

if (!$diag['writable'] || !$probeOk) {
    echo "\nCorrija o bind mount EasyPanel (host → /var/www/html/storage) e permissões www-data.\n";
    echo "Opcional: STORAGE_PATH=/caminho/absoluto/do/volume no .env do serviço.\n";
    exit(1);
}

if (!EvidenceStorageService::supportsWebp()) {
    echo "\nAviso: GD sem WebP — evidências serão gravadas em JPG/PNG (rebuild Dockerfile com libwebp).\n";
}

echo "\nStorage pronto para evidências e assinaturas.\n";
exit(0);
