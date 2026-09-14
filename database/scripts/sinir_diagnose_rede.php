<?php

/**
 * Diagnóstico de rede: servidor → admin.sinir.gov.br
 * Uso: php database/scripts/sinir_diagnose_rede.php
 */

require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\SinirConfig;

$host = 'admin.sinir.gov.br';
$url = SinirConfig::baseUrl().'/token';

echo "=== Diagnóstico SINIR (rede) ===".PHP_EOL;
echo 'URL alvo: '.$url.PHP_EOL;
echo 'Servidor PHP: '.php_uname('n').PHP_EOL;

$ip = gethostbyname($host);
echo 'DNS '.$host.' → '.($ip === $host ? 'FALHOU (não resolveu)' : $ip).PHP_EOL;

if (!function_exists('curl_init')) {
    echo '[FALHA] extensão curl não disponível no PHP'.PHP_EOL;
    exit(1);
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_NOBODY => true,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
]);
if (defined('CURL_IPRESOLVE_V4')) {
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
}

$start = microtime(true);
curl_exec($ch);
$elapsed = round((microtime(true) - $start) * 1000);
$errno = curl_errno($ch);
$error = curl_error($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$primaryIp = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
curl_close($ch);

echo 'Tempo: '.$elapsed.' ms'.PHP_EOL;
echo 'HTTP: '.$http.PHP_EOL;
echo 'IP conectado: '.($primaryIp ?: '—').PHP_EOL;

if ($errno !== 0) {
    echo '[FALHA] cURL #'.$errno.': '.$error.PHP_EOL;
    echo PHP_EOL.'Provável causa: firewall outbound da HostGator ou bloqueio do IP do datacenter pelo SINIR.'.PHP_EOL;
    echo 'Teste também no SSH: curl -v --connect-timeout 15 -X POST '.$url.PHP_EOL;
    exit(1);
}

echo '[OK] Conexão TCP/HTTPS estabelecida (HTTP '.$http.' é esperado sem token válido).'.PHP_EOL;
exit(0);
