<?php

require __DIR__.'/../vendor/autoload.php';

use App\Utils\View;
use App\Common\Environment;
use App\Http\Middleware\Queue as MiddlewareQueue;

Environment::load(__DIR__.'/../');

if (php_sapi_name() !== 'cli' && \App\Common\MaintenanceMode::isActive()) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Retry-After: 300');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<title>Manutenção</title></head><body style="font-family:sans-serif;max-width:32rem;margin:4rem auto;padding:0 1rem;">'
        .'<h1>Manutenção</h1><p>O sistema está temporariamente indisponível. Tente novamente em alguns minutos.</p>'
        .'</body></html>';
    exit;
}

$detectRequestUrl = function () {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($scriptDir === '/' || $scriptDir === '.') {
        $scriptDir = '';
    }
    return rtrim($scheme.'://'.$host.$scriptDir, '/');
};

$envUrl = rtrim((string)(getenv('URL') ?: ''), '/');
$requestUrl = $detectRequestUrl();
$envHost = parse_url($envUrl, PHP_URL_HOST);
$requestHost = $_SERVER['HTTP_HOST'] ?? '';
$envPath = rtrim((string)(parse_url($envUrl, PHP_URL_PATH) ?? ''), '/');
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if ($scriptDir === '/' || $scriptDir === '.') {
    $scriptDir = '';
}
$scriptPath = rtrim($scriptDir, '/');

if ($envHost && $requestHost && strcasecmp((string)$envHost, (string)$requestHost) !== 0) {
    $appUrl = $requestUrl;
} elseif ($envUrl !== '' && $envPath === $scriptPath) {
    $appUrl = $envUrl;
} elseif ($envHost) {
    $scheme = parse_url($envUrl, PHP_URL_SCHEME) ?: ((str_starts_with($requestUrl, 'https')) ? 'https' : 'http');
    $appUrl = rtrim($scheme.'://'.$envHost.$scriptPath, '/');
} else {
    $appUrl = $requestUrl;
}

define('URL', rtrim($appUrl, '/'));
\App\Common\SessionBootstrap::configure();
define('SITE', (string)Environment::get('SITE', 'Well Eco Admin'));
define('TIMEZONE', (string)Environment::get('TIMEZONE', 'America/Cuiaba'));
date_default_timezone_set(TIMEZONE);

View::init(['URL' => URL]);

MiddlewareQueue::setMap([
    'maintenance' => \App\Http\Middleware\Maintenance::class,
    'required-admin-logout' => \App\Http\Middleware\RequireAdminLogout::class,
    'required-admin-login' => \App\Http\Middleware\RequireAdminLogin::class,
    'required-gerador-logout' => \App\Http\Middleware\RequireGeradorLogout::class,
    'required-gerador-login' => \App\Http\Middleware\RequireGeradorLogin::class,
    'api-cors' => \App\Http\Middleware\ApiCors::class,
    'required-api-auth' => \App\Http\Middleware\RequireApiAuth::class,
    'required-gerador-api-auth' => \App\Http\Middleware\RequireGeradorApiAuth::class,
]);

MiddlewareQueue::setDefault(['maintenance']);
