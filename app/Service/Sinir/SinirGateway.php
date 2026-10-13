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

    /**
     * POST que pode retornar PDF binário (ex.: buscaPdfManifestoPorCodigoBarras).
     *
     * @return array{ok:bool,status:int,pdf:?string,body:?array,raw:string,error:?string}
     */
    public function postForPdf(string $path, ?string $bearerToken = null): array
    {
        $result = $this->request('POST', $path, [], $bearerToken);
        $raw = $result['raw'];
        if ($raw !== '' && str_starts_with($raw, '%PDF')) {
            return [
                'ok' => $result['status'] >= 200 && $result['status'] < 300,
                'status' => $result['status'],
                'pdf' => $raw,
                'body' => null,
                'raw' => $raw,
                'error' => null,
            ];
        }

        return [
            'ok' => $result['ok'],
            'status' => $result['status'],
            'pdf' => null,
            'body' => $result['body'],
            'raw' => $raw,
            'error' => $result['error'] ?? ($result['body']['mensagem'] ?? null),
        ];
    }

    /**
     * @return array{
     *   ok:bool,status:int,body:?array,raw:string,error:?string,
     *   curl_errno?:int,primary_ip?:string,content_type?:?string,
     *   response_headers?:string,request_bytes?:int,hint?:?string
     * }
     */
    private function request(string $method, string $path, array $body, ?string $bearerToken): array
    {
        $url = rtrim(SinirConfig::baseUrl(), '/').'/'.ltrim($path, '/');

        // Expect: vazio evita 100-continue — vários gateways Java/Tomcat respondem 400 sem body.
        $headers = [
            'Content-Type: application/json; charset=UTF-8',
            'Accept: application/json',
            'Expect:',
        ];
        if ($bearerToken !== null && $bearerToken !== '') {
            $headers[] = 'Authorization: Bearer '.$bearerToken;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'raw' => '', 'error' => 'curl_init falhou'];
        }

        $responseHeaders = '';
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            // Não seguir redirect em POST (pode virar GET e perder body → 400 vazio).
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders): int {
                $responseHeaders .= $headerLine;

                return strlen($headerLine);
            },
        ];

        if (defined('CURL_IPRESOLVE_V4')) {
            $opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }

        // Aceita gzip/deflate se o SINIR comprimir.
        if (defined('CURLOPT_ENCODING')) {
            $opts[CURLOPT_ENCODING] = '';
        }

        if (!SinirConfig::sslVerify()) {
            $opts[CURLOPT_SSL_VERIFYPEER] = false;
            $opts[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        $jsonBody = '';
        if ($method === 'POST') {
            $payload = self::stripNulls($body);
            $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($jsonBody === false) {
                curl_close($ch);

                return [
                    'ok' => false,
                    'status' => 0,
                    'body' => null,
                    'raw' => '',
                    'error' => 'Falha ao serializar JSON do payload SINIR.',
                ];
            }
            // NÃO setar Content-Length manualmente: o curl já envia.
            // Content-Length duplicado → Tomcat HTTP 400 HTML genérico (sem JSON).
            $opts[CURLOPT_POSTFIELDS] = $jsonBody;
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = (int)curl_errno($ch);
        $primaryIp = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
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
                'content_type' => $contentType !== '' ? $contentType : null,
                'response_headers' => $responseHeaders,
                'request_bytes' => strlen($jsonBody),
                'hint' => $hint,
            ];
        }

        $decoded = json_decode($raw, true);
        $bodyArray = is_array($decoded) ? $decoded : null;
        $apiError = is_array($bodyArray) && !empty($bodyArray['erro']);
        $httpOk = $status >= 200 && $status < 300;

        $errorMsg = null;
        if ($apiError) {
            $errorMsg = (string)($bodyArray['mensagem'] ?? $bodyArray['message'] ?? 'Erro retornado pela API SINIR');
        } elseif (!$httpOk && is_array($bodyArray)) {
            $errorMsg = (string)($bodyArray['mensagem'] ?? $bodyArray['message'] ?? $bodyArray['retorno'] ?? '');
            if ($errorMsg === '') {
                $errorMsg = null;
            }
        } elseif ($curlError !== '') {
            $errorMsg = $curlError;
        }

        if (!$httpOk && ($errorMsg === null || $errorMsg === '')) {
            if (trim($raw) === '') {
                $errorMsg = 'SINIR respondeu HTTP '.$status.' sem corpo. '
                    .'Verifique token/unidade e rode: php database/scripts/sinir_debug_salvar_manifesto.php {coleta_id}';
            } elseif (stripos($contentType, 'text/html') !== false || str_contains($raw, '<html')) {
                // Tomcat Bad Request — extrai Description se houver
                $desc = '';
                if (preg_match('/<b>Description<\/b>\s*([^<]+)/i', $raw, $m)) {
                    $desc = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
                }
                $errorMsg = 'SINIR/Tomcat HTTP '.$status
                    .($desc !== '' ? ': '.$desc : ' (resposta HTML, não JSON — possível header/payload malformado).');
            }
        }

        return [
            'ok' => $httpOk && !$apiError,
            'status' => $status,
            'body' => $bodyArray,
            'raw' => $raw,
            'error' => $errorMsg,
            'curl_errno' => $curlErrno,
            'primary_ip' => $primaryIp !== '' ? $primaryIp : null,
            'content_type' => $contentType !== '' ? $contentType : null,
            'response_headers' => $responseHeaders,
            'request_bytes' => strlen($jsonBody),
        ];
    }

    /** Remove chaves null recursivamente (API SINIR costuma rejeitar null explícito). */
    private static function stripNulls(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if ($v === null) {
                continue;
            }
            if (is_array($v)) {
                $out[$k] = self::stripNulls($v);
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
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
