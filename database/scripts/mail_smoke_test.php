<?php

declare(strict_types=1);

/**
 * Teste de envio SMTP — uso: php database/scripts/mail_smoke_test.php destino@email.com
 */

require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\CompanyConfig;
use App\Common\Environment;
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

$subject = CompanyConfig::shortName().' — teste SMTP';
$from = trim((string)Environment::get('MAIL_FROM', ''));

$result = MailService::enviarTesteSmtp($to, $subject, $html);

if ($result['ok']) {
    echo "SMTP aceitou o envio.\n";
    echo "  De: {$from} (".CompanyConfig::mailFromName().")\n";
    echo "  Para: {$to}\n";
    echo "  Assunto: {$subject}\n";
    echo "\nSe não chegar em alguns minutos:\n";
    echo "  1) Brevo → Transactional → Logs (status: delivered, deferred, blocked)\n";
    echo "  2) Domínio well.eco.br autenticado (DKIM verde) e remetente noreply@ permitido\n";
    echo "  3) Gmail: Spam, Promoções; teste outro e-mail (Outlook)\n";
    echo "  4) Debug SMTP: MAIL_SMTP_DEBUG=2 php database/scripts/mail_smoke_test.php ...\n";
    exit(0);
}

fwrite(STDERR, 'Falha: '.($result['error'] ?? 'desconhecida')."\n");
exit(4);
