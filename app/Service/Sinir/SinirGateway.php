<?php

namespace App\Service\Sinir;

use App\Common\SinirConfig;

class SinirGateway
{
    private int $timeout;

    /** @var 'bearer'|'x-access-token'|'raw' */
    private string $authMode;

    public function __construct(int $timeoutSeconds = 30, string $authMode = 'bearer')
    {
        $this->timeout = $timeoutSeconds;
        $this->authMode = $authMode;
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
        return $this->requestForPdf('POST', $path, $bearerToken);
    }

    /** @return array{ok:bool,status:int,pdf:?string,body:?array,raw:string,error:?string} */
    public function getForPdf(string $path, ?string $bearerToken = null): array
    {
        return $this->requestForPdf('GET', $path, $bearerToken);
    }

    /**
     * @return array{ok:bool,status:int,pdf:?string,body:?array,raw:string,error:?string}
     */
    private function requestForPdf(string $method, string $path, ?string $bearerToken): array
    {
        $result = $this->request($method, $path, [], $bearerToken);
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
     *   response_headers?:string,request_headers?:?string,request_bytes?:int,hint?:?string
     * }
     */
    private function request(string $method, string $path, array $body, ?string $bearerToken): array
    {
        $url = rtrim(SinirConfig::baseUrl(), '/').'/'.ltrim($path, '/');

        // Não enviar Expect (nem vazio): Tomcat 9 rejeita Expect: vazio com HTML 400.
        // Corpos <1MB não disparam 100-continue no curl moderno.
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($bearerToken !== null && $bearerToken !== '') {
            $token = trim($bearerToken);
            $headers = array_merge($headers, match ($this->authMode) {
                'x-access-token' => ['x-access-token: '.$token],
                'raw' => ['Authorization: '.$token],
                default => ['Authorization: Bearer '.$token],
            });
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
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders): int {
                $responseHeaders .= $headerLine;

                return strlen($headerLine);
            },
        ];

        if (defined('CURLINFO_HEADER_OUT')) {
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        }

        if (defined('CURL_HTTP_VERSION_1_1')) {
            $opts[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
        }

        if (defined('CURL_IPRESOLVE_V4')) {
            $opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }

        // Evita br/zstd no Accept-Encoding (alguns proxies/WAF respondem 400 HTML).
        if (defined('CURLOPT_ENCODING')) {
            $opts[CURLOPT_ENCODING] = 'gzip,deflate';
        }

        if (!SinirConfig::sslVerify()) {
            $opts[CURLOPT_SSL_VERIFYPEER] = false;
            $opts[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        $jsonBody = '';
        if (strtoupper($method) === 'POST') {
            $payload = self::stripNulls($body);
            // Body vazio (ex.: /token) — não enviar "{}"
            if ($payload === []) {
                $jsonBody = '';
                $opts[CURLOPT_POST] = true;
                $opts[CURLOPT_POSTFIELDS] = '';
            } else {
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
                $opts[CURLOPT_POST] = true;
                $opts[CURLOPT_POSTFIELDS] = $jsonBody;
            }
        } else {
            $opts[CURLOPT_CUSTOMREQUEST] = $method;
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = (int)curl_errno($ch);
        $primaryIp = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $requestHeadersOut = defined('CURLINFO_HEADER_OUT')
            ? (string)curl_getinfo($ch, CURLINFO_HEADER_OUT)
            : '';
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
                'request_headers' => $requestHeadersOut !== '' ? $requestHeadersOut : null,
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
            'request_headers' => $requestHeadersOut !== '' ? $requestHeadersOut : null,
            'request_bytes' => strlen($jsonBody),
        ];
    }

    /** Remove null e string vazia recursivamente (API SINIR costuma rejeitar ambos). */
    private static function stripNulls(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if ($v === null || $v === '') {
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
