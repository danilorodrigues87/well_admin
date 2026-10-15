<?php

namespace App\Service\Sinir;

/**
 * Normaliza resposta de GET/POST retornaManifesto* (legado FEAM e MMA/SIGOR).
 */
final class SinirConsultaParser
{
    /**
     * @return array{
     *   ok:bool,
     *   retorno_codigo:?int,
     *   situacao_codigo:?int,
     *   manifesto_codigo:?string,
     *   codigo_barras:?string,
     *   cdf_codigo:?string,
     *   mensagem:?string
     * }
     */
    public static function interpretar(array $body, bool $httpOk): array
    {
        $retornoRaw = self::primeiroValor($body, ['retornoCodigo', 'restResponseCodigo']);
        $retornoCodigo = $retornoRaw !== null && $retornoRaw !== '' ? (int)$retornoRaw : null;
        $erroRaw = self::primeiroValor($body, ['erro', 'erroNacional']);
        $erroFlag = $erroRaw === true || $erroRaw === 1 || $erroRaw === 'true' || $erroRaw === '1';
        $msgRaw = self::primeiroValor($body, [
            'retorno',
            'mensagem',
            'restResponseMensagem',
            'mensagemErroNacional',
        ]);
        $msg = trim(is_scalar($msgRaw) ? (string)$msgRaw : '');

        $situacao = self::extrairSituacao($body);
        $man = self::extrairManifestoNumero($body);
        $barcode = self::extrairCodigoBarras($body);
        $cdf = self::extrairCodigoCdf($body);

        $ok = $httpOk && !$erroFlag && (
            ($retornoCodigo === 0)
            || ($retornoCodigo === null && ($man !== null || $situacao !== null) && !$erroFlag)
        );

        if (!$ok && $msg === '' && $erroFlag) {
            $msg = trim((string)($body['mensagem'] ?? 'Consulta rejeitada pelo SINIR.'));
        }

        return [
            'ok' => $ok,
            'retorno_codigo' => $retornoCodigo,
            'situacao_codigo' => $situacao,
            'manifesto_codigo' => $man,
            'codigo_barras' => $barcode !== '' ? $barcode : null,
            'cdf_codigo' => $cdf,
            'mensagem' => $msg !== '' ? $msg : null,
        ];
    }

    /** @param array<string,mixed> $flat */
    private static function extrairSituacao(array $flat): ?int
    {
        $valor = self::primeiroValor($flat, [
            'situacaoManifestoCodigo',
            'manSituacaoCodigo',
            'situacaoCodigo',
            'simCodigo',
        ]);
        if ($valor !== null && $valor !== '' && is_scalar($valor)) {
            return (int)$valor;
        }

        $situacao = self::primeiroValor($flat, [
            'situacaoManifesto',
            'manSituacao',
            'situacao',
            'situacaoDescricao',
            'descricaoSituacao',
            'manSituacaoDescricao',
        ]);
        if (is_array($situacao)) {
            $codigo = self::primeiroValor($situacao, ['codigo', 'id', 'simCodigo']);
            if ($codigo !== null && $codigo !== '' && is_scalar($codigo)) {
                return (int)$codigo;
            }
            $situacao = self::primeiroValor($situacao, ['descricao', 'nome', 'label']);
        } elseif (is_scalar($situacao)) {
            // tratado abaixo
        }

        if (is_scalar($situacao)) {
            $texto = trim((string)$situacao);
            if (ctype_digit($texto)) {
                return (int)$texto;
            }
            $normalizada = mb_strtoupper($texto, 'UTF-8');
            if (str_contains($normalizada, 'ARMAZEN')) {
                return 9;
            }
            if (str_contains($normalizada, 'CANCEL')) {
                return 4;
            }
            if (str_contains($normalizada, 'RECEB')) {
                return 3;
            }
            if (str_contains($normalizada, 'SALV') || str_contains($normalizada, 'ATIV')
                || str_contains($normalizada, 'EMIT')) {
                return 1;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $flat */
    private static function extrairManifestoNumero(array $flat): ?string
    {
        $valor = self::primeiroValor($flat, [
            'manifestoCodigo',
            'manNumero',
            'manifestoNumeroNacional',
            'numeroManifesto',
        ]);
        if (is_scalar($valor)) {
            $v = trim((string)$valor);
            if ($v !== '' && $v !== '0') {
                return $v;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $flat */
    private static function extrairCodigoBarras(array $flat): string
    {
        $valor = self::primeiroValor($flat, [
            'codigoBarra',
            'codigoBarras',
            'manCodigoBarras',
            'codigoDeBarras',
        ]);
        if (is_scalar($valor)) {
            return trim((string)$valor);
        }

        return '';
    }

    /** @param array<string,mixed> $flat */
    private static function extrairCodigoCdf(array $flat): ?string
    {
        $valor = self::primeiroValor($flat, [
            'cdfCodigo',
            'numeroCDF',
            'cerCodigo',
            'codigoGerado',
            'numeroCertificado',
        ]);
        if (!is_scalar($valor)) {
            return null;
        }
        $codigo = trim((string)$valor);

        return ($codigo !== '' && $codigo !== '0') ? $codigo : null;
    }

    /**
     * Busca recursiva tolerante às diferentes camadas de objetoResposta/DTO
     * usadas pela API nacional e pelos conectores estaduais.
     *
     * @param string[] $keys
     */
    private static function primeiroValor(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }
            $found = self::primeiroValor($value, $keys);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
