<?php

/**
 * Diagnóstico SMTP (local / VPS / Easypanel).
 * Uso: php database/scripts/mail_diagnose.php
 */

declare(strict_types=1);

require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\CompanyConfig;
use App\Common\Environment;
use App\Service\MailService;

function mailPassHint(string $pass): string
{
    if ($pass === '') {
        return '(vazio)';
    }
    $len = strlen($pass);
    if (str_starts_with($pass, 'xsmtpsib-')) {
        return 'xsmtpsib-… (SMTP key — ok) · '.$len.' chars';
    }
    if (str_starts_with($pass, 'xkeysib-')) {
        return 'xkeysib-… (API key — NÃO serve para SMTP) · '.$len.' chars';
    }

    return 'outro formato · '.$len.' chars · prefixo: '.substr($pass, 0, min(8, $len)).'…';
}

function envSource(string $key): string
{
    if (getenv($key) !== false) {
        return 'processo (Docker/Easypanel Environment)';
    }
    if (isset($_SERVER[$key]) && (string)$_SERVER[$key] !== '') {
        return '$_SERVER';
    }
    if (array_key_exists($key, $_ENV) && (string)$_ENV[$key] !== '') {
        return '.env / $_ENV';
    }

    return '(não definido)';
}

echo "=== E-mail SMTP — diagnóstico ===\n";
echo 'PHP: '.PHP_VERSION."\n";
echo 'Raiz: '.dirname(__DIR__, 2)."\n\n";

$host = trim((string)Environment::get('MAIL_HOST', ''));
$user = trim((string)Environment::get('MAIL_USER', ''));
$from = trim((string)Environment::get('MAIL_FROM', ''));
$pass = (string)Environment::get('MAIL_PASS', '');
$port = (string)Environment::get('MAIL_PORT', '587');
$enc = (string)Environment::get('MAIL_ENCRYPTION', 'tls');
$notif = (string)Environment::get('MAIL_NOTIFICATIONS_ENABLED', 'true');

echo "MAIL_HOST: {$host} · origem: ".envSource('MAIL_HOST')."\n";
echo "MAIL_PORT: {$port} · MAIL_ENCRYPTION: {$enc}\n";
echo "MAIL_USER: ".($user !== '' ? $user : '(vazio)')." · origem: ".envSource('MAIL_USER')."\n";
echo "MAIL_FROM: ".($from !== '' ? $from : '(vazio)')."\n";
echo 'MAIL_FROM_NAME: '.CompanyConfig::mailFromName()."\n";
echo 'MAIL_PASS: '.mailPassHint($pass).' · origem: '.envSource('MAIL_PASS')."\n";
echo 'MAIL_NOTIFICATIONS_ENABLED: '.$notif."\n";
echo 'MailService::isConfigured(): '.(MailService::isConfigured() ? 'sim' : 'não')."\n\n";

$errors = [];
if ($host === '') {
    $errors[] = 'MAIL_HOST vazio';
}
if ($user === '') {
    $errors[] = 'MAIL_USER vazio';
}
if ($from === '') {
    $errors[] = 'MAIL_FROM vazio';
}
if ($pass === '') {
    $errors[] = 'MAIL_PASS vazio';
} elseif (str_starts_with($pass, 'xkeysib-')) {
    $errors[] = 'MAIL_PASS é chave de API; gere chave SMTP (xsmtpsib-) no Brevo';
}
if ($user !== '' && str_contains($user, 'smtp-relay')) {
    $errors[] = 'MAIL_USER não pode ser smtp-relay.brevo.com — use o login xxx@smtp-brevo.com';
}

if ($errors !== []) {
    echo "Problemas:\n";
    foreach ($errors as $e) {
        echo "  - {$e}\n";
    }
    echo "\n";
} else {
    echo "Configuração mínima OK. Teste: php database/scripts/mail_smoke_test.php seu@email.com\n\n";
}

echo "Easypanel: se MAIL_PASS mostrar origem 'processo' com xkeysib ou valor antigo,\n";
echo "  atualize Environment do serviço e reinicie (sobrescreve .env do deploy).\n\n";

echo "=== Se smoke test OK mas nada chega na caixa ===\n";
echo "  • Brevo → Transactional → Logs — se 'sender is not valid': autentique well.eco.br + remetente noreply@\n";
echo "  • Senders & Domains: domínio Verified (verde); sender noreply@well.eco.br cadastrado\n";
echo "  • Conta Brevo: cota / conta validada (trial pode bloquear destinos)\n";
echo "  • DNS SPF: include:spf.brevo.com além do ImprovMX\n";
