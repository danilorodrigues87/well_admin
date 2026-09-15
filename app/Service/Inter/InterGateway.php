<?php

namespace App\Service\Inter;

use App\Common\InterConfig;

class InterGateway
{
    private int $timeout;

    public function __construct(int $timeoutSeconds = 60)
    {
        $this->timeout = $timeoutSeconds;
    }

    /**
     * OAuth2 client_credentials com mTLS.
     *
     * @return array{ok:bool,status:int,body:?array,raw:string,error:?string,curl_errno?:int}
     */
    public function requestToken(): array
    {
        $url = InterConfig::baseUrl().'/oauth/v2/token';
        $fields = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => InterConfig::clientId(),
            'client_secret' => InterConfig::clientSecret(),
            'scope' => InterConfig::scope(),
        ]);

        return $this->curlRequest('POST', $url, $fields, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ], true);
    }

    /**
     * @param array<string,mixed>|string|null $body
     * @param list<string> $extraHeaders
     * @return array{ok:bool,status:int,body:?array,raw:string,error:?string,curl_errno?:int}
     */
    public function request(string $method, string $path, array|string|null $body, ?string $bearerToken, array $extraHeaders = []): array
    {
        $url = rtrim(InterConfig::baseUrl(), '/').'/'.ltrim($path, '/');
        $headers = array_merge([
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer '.$bearerToken,
            'x-conta-corrente: '.InterConfig::contaCorrente(),
        ], $extraHeaders);

        $payload = null;
        if ($body !== null) {
            $payload = is_array($body) ? json_encode($body, JSON_UNESCAPED_UNICODE) : $body;
        }

        return $this->curlRequest($method, $url, $payload, $headers, true);
    }

    /**
     * @param list<string> $headers
     * @return array{ok:bool,status:int,body:?array,raw:string,error:?string,curl_errno?:int}
     */
    private function curlRequest(string $method, string $url, ?string $payload, array $headers, bool $withMtls): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'raw' => '', 'error' => 'curl_init falhou'];
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ];

        if ($payload !== null && strtoupper($method) !== 'GET') {
            $opts[CURLOPT_POSTFIELDS] = $payload;
        }

        if ($withMtls) {
            $opts[CURLOPT_SSLCERT] = InterConfig::certPath();
            $opts[CURLOPT_SSLKEY] = InterConfig::keyPath();
            $password = InterConfig::keyPassword();
            if ($password !== null) {
                $opts[CURLOPT_SSLKEYPASSWD] = $password;
            }
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = (int)curl_errno($ch);
        curl_close($ch);

        if ($raw === false) {
            return [
                'ok' => false,
                'status' => $status,
                'body' => null,
                'raw' => '',
                'error' => $curlError !== '' ? $curlError : 'Erro de rede',
                'curl_errno' => $curlErrno,
            ];
        }

        $decoded = json_decode($raw, true);
        $body = is_array($decoded) ? $decoded : null;

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $body,
            'raw' => $raw,
            'error' => $status >= 400
                ? self::extractApiError($body, $raw)
                : ($curlError !== '' ? $curlError : null),
        ];
    }

    /** @param array<string,mixed>|null $body */
    private static function extractApiError(?array $body, string $raw): string
    {
        if (is_array($body)) {
            foreach (['detail', 'mensagem', 'message', 'title', 'error'] as $key) {
                if (!empty($body[$key]) && is_string($body[$key])) {
                    return $body[$key];
                }
            }
            if (!empty($body['violacoes']) && is_array($body['violacoes'])) {
                return json_encode($body['violacoes'], JSON_UNESCAPED_UNICODE);
            }
        }

        return $raw !== '' ? mb_substr($raw, 0, 500) : 'Erro HTTP na API Inter';
    }
}
