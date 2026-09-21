<?php

declare(strict_types=1);

/**
 * Teste de envio SMTP — uso: php database/scripts/mail_smoke_test.php destino@email.com
 */

require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\CompanyConfig;
use App\Service\MailService;

$to = trim($argv[1] ?? '');
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php database/scripts/mail_smoke_test.php destino@email.com\n");
    exit(1);
}

if (!MailService::isConfigured()) {
    fwrite(STDERR, "SMTP incompleto: configure MAIL_HOST, MAIL_USER, MAIL_FROM no .env\n");
    exit(2);
}

$html = '<p>Teste SMTP Well Admin em '.htmlspecialchars(date('d/m/Y H:i'), ENT_QUOTES, 'UTF-8').'.</p>'
    .'<p>Remetente: '.htmlspecialchars(CompanyConfig::mailFromName(), ENT_QUOTES, 'UTF-8').'</p>';

$result = MailService::enviarTesteSmtp($to, CompanyConfig::shortName().' — teste SMTP', $html);

if ($result['ok']) {
    echo "E-mail enviado para {$to}.\n";
    exit(0);
}

fwrite(STDERR, 'Falha: '.($result['error'] ?? 'desconhecida')."\n");
exit(4);
