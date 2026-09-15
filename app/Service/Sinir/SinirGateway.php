<?php

namespace App\Service\Sinir;

use App\Common\SinirConfig;

class SinirGateway
{
    private int $timeout;

    public function __construct(int $timeoutSeconds = 30)
    {
        $this->timeout = $timeoutSeconds;
    }

    /** @return array{ok:bool,status:int,body:?array,raw:string,error:?string} */
    public function post(string $path, array $body = [], ?string $bearerToken = null): array
    {
        return $this->request('POST', $path, $body, $bearerToken);
    }

    /** @return array{ok:bool,status:int,body:?array,raw:string,error:?string} */
    public function get(string $path, ?string $bearerToken = null): array
    {
        return $this->request('GET', $path, [], $bearerToken);
    }

    /** @return array{ok:bool,status:int,body:?array,raw:string,error:?string,curl_errno?:int,primary_ip?:string} */
    private function request(string $method, string $path, array $body, ?string $bearerToken): array
    {
        $url = rtrim(SinirConfig::baseUrl(), '/').'/'.ltrim($path, '/');

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($bearerToken !== null && $bearerToken !== '') {
            $headers[] = 'Authorization: Bearer '.$bearerToken;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'raw' => '', 'error' => 'curl_init falhou'];
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ];

        if (defined('CURL_IPRESOLVE_V4')) {
            $opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }

        if (!SinirConfig::sslVerify()) {
            $opts[CURLOPT_SSL_VERIFYPEER] = false;
            $opts[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        if ($method === 'POST') {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = (int)curl_errno($ch);
        $primaryIp = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        curl_close($ch);

        if ($raw === false) {
            $hint = self::hintForCurlError($curlErrno, $curlError);

            return [
                'ok' => false,
                'status' => $status,
                'body' => null,
                'raw' => '',
                'error' => $curlError !== '' ? $curlError : 'Erro de rede',
                'curl_errno' => $curlErrno,
                'primary_ip' => $primaryIp !== '' ? $primaryIp : null,
                'hint' => $hint,
            ];
        }

        $decoded = json_decode($raw, true);
        $bodyArray = is_array($decoded) ? $decoded : null;
        $apiError = is_array($bodyArray) && !empty($bodyArray['erro']);

        return [
            'ok' => $status >= 200 && $status < 300 && !$apiError,
            'status' => $status,
            'body' => $bodyArray,
            'raw' => $raw,
            'error' => $apiError
                ? (string)($bodyArray['mensagem'] ?? 'Erro retornado pela API SINIR')
                : ($curlError !== '' ? $curlError : null),
            'curl_errno' => $curlErrno,
            'primary_ip' => $primaryIp !== '' ? $primaryIp : null,
        ];
    }

    private static function hintForCurlError(int $errno, string $message): ?string
    {
        if ($errno === 28 || str_contains($message, 'timed out')) {
            return 'Timeout de rede: o servidor não alcançou admin.sinir.gov.br. Teste curl no SSH e verifique firewall da hospedagem.';
        }
        if ($errno === 7) {
            return 'Conexão recusada ou bloqueada (firewall outbound / IP do datacenter bloqueado pelo SINIR).';
        }
        if ($errno === 6) {
            return 'Falha de DNS ao resolver admin.sinir.gov.br.';
        }

        return null;
    }
}
