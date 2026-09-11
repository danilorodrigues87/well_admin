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

    /** @return array{ok:bool,status:int,body:?array,raw:string,error:?string} */
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
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'status' => $status, 'body' => null, 'raw' => '', 'error' => $curlError ?: 'Erro de rede'];
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
        ];
    }
}
