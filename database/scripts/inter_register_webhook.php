<?php
/**
 * Registra URL de webhook de cobrança no Inter.
 * Uso: php database/scripts/inter_register_webhook.php
 */
declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\InterConfig;
use App\Service\Inter\InterCobrancaService;

$url = InterConfig::webhookUrl();
if ($url === '') {
    fwrite(STDERR, "Defina INTER_WEBHOOK_URL no .env\n");
    exit(1);
}

$result = (new InterCobrancaService())->registrarWebhook($url);
if (!$result['ok']) {
    fwrite(STDERR, 'Erro: '.($result['error'] ?? 'HTTP '.$result['raw_status'])."\n");
    exit(1);
}

echo "Webhook registrado: {$url}\n";
