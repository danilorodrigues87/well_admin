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
     *   mensagem:?string
     * }
     */
    public static function interpretar(array $body, bool $httpOk): array
    {
        $flat = self::achatar($body);

        $retornoCodigo = isset($flat['retornoCodigo']) ? (int)$flat['retornoCodigo'] : null;
        $erroFlag = !empty($body['erro']) || !empty($flat['erro']);
        $msg = trim((string)($flat['retorno'] ?? $flat['mensagem'] ?? $body['mensagem'] ?? ''));

        $situacao = self::extrairSituacao($flat);
        $man = self::extrairManifestoNumero($flat);
        $barcode = self::extrairCodigoBarras($flat);

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
            'mensagem' => $msg !== '' ? $msg : null,
        ];
    }

    /** @return array<string,mixed> */
    private static function achatar(array $body): array
    {
        $out = $body;
        foreach (['objetoResposta', 'respostaApiwsManifestoDTO'] as $key) {
            $inner = $body[$key] ?? null;
            if (!is_array($inner)) {
                continue;
            }
            if (array_is_list($inner) && is_array($inner[0] ?? null)) {
                $out = array_merge($out, $inner[0]);
            } elseif (!array_is_list($inner)) {
                $out = array_merge($out, $inner);
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $flat */
    private static function extrairSituacao(array $flat): ?int
    {
        foreach (['situacaoManifestoCodigo', 'manSituacaoCodigo', 'situacaoCodigo', 'manSituacao'] as $k) {
            if (isset($flat[$k]) && $flat[$k] !== '' && $flat[$k] !== null) {
                return (int)$flat[$k];
            }
        }

        return null;
    }

    /** @param array<string,mixed> $flat */
    private static function extrairManifestoNumero(array $flat): ?string
    {
        foreach (['manifestoCodigo', 'manNumero', 'manifestoNumeroNacional', 'numeroManifesto'] as $k) {
            $v = trim((string)($flat[$k] ?? ''));
            if ($v !== '' && $v !== '0') {
                return $v;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $flat */
    private static function extrairCodigoBarras(array $flat): string
    {
        foreach (['codigoBarra', 'codigoBarras', 'manCodigoBarras', 'codigoDeBarras'] as $k) {
            $v = trim((string)($flat[$k] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }
}
