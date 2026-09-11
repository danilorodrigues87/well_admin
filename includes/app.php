<?php

require __DIR__.'/../vendor/autoload.php';

use App\Utils\View;
use App\Common\Environment;
use App\Http\Middleware\Queue as MiddlewareQueue;

Environment::load(__DIR__.'/../');

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
define('SITE', (string)Environment::get('SITE', 'Well Eco Admin'));
define('TIMEZONE', (string)Environment::get('TIMEZONE', 'America/Cuiaba'));
date_default_timezone_set(TIMEZONE);

View::init(['URL' => URL]);

MiddlewareQueue::setMap([
    'maintenance' => \App\Http\Middleware\Maintenance::class,
    'required-admin-logout' => \App\Http\Middleware\RequireAdminLogout::class,
    'required-admin-login' => \App\Http\Middleware\RequireAdminLogin::class,
]);

MiddlewareQueue::setDefault(['maintenance']);
