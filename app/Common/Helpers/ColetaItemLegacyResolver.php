<?php

namespace App\Common\Helpers;

/**
 * Resolve tipo_residuo_id para nomes truncados do ETL legado (sem material no texto).
 */
class ColetaItemLegacyResolver
{
    /** @var array<string,int> nome normalizado → tipo_residuo_id */
    private const CONTAINER_ALIASES = [
        '1 sacos de' => 36,
        '2 sacos de' => 36,
        '3 sacos de' => 36,
        '1 tambores de' => 69,
        '1 caixas de' => 16,
        '2 caixas de' => 16,
        '1 granel de' => 42,
        '2 granel de' => 42,
        '1 bombonas de' => 69,
        '2 outros de' => 54,
    ];

    /**
     * @param array{cliente_fantasia?:string,plano_nome?:string,legacy_peso?:string,sibling_tipos?:list<int>} $ctx
     * @return array{id:?int,method:string,score:float}
     */
    public static function resolveTruncated(string $nome, array $ctx = []): array
    {
        $empty = ['id' => null, 'method' => 'none', 'score' => 0.0];
        $key = mb_strtolower(trim($nome));
        if (!self::isTruncatedContainer($key)) {
            return $empty;
        }

        $peso = mb_strtolower((string)($ctx['legacy_peso'] ?? ''));
        if ($peso !== '') {
            $fromPeso = TipoResiduoMatcher::resolveDetailed('', (string)$ctx['legacy_peso']);
            if ($fromPeso['id'] !== null && $fromPeso['method'] !== 'none') {
                return [
                    'id' => $fromPeso['id'],
                    'method' => 'trunc_peso_'.$fromPeso['method'],
                    'score' => min(88.0, $fromPeso['score']),
                ];
            }
        }

        $fantasia = mb_strtolower((string)($ctx['cliente_fantasia'] ?? ''));
        $plano = mb_strtolower((string)($ctx['plano_nome'] ?? ''));

        if (str_contains($key, 'caixas de') && self::isHealthClient($fantasia, $plano)) {
            return ['id' => 16, 'method' => 'trunc_rsss_caixas', 'score' => 82.0];
        }

        if (str_contains($key, 'sacos de') && self::isHealthClient($fantasia, $plano)) {
            return ['id' => 16, 'method' => 'trunc_rsss_sacos', 'score' => 82.0];
        }

        if (str_contains($key, 'granel de') && str_contains($fantasia, 'borracharia')) {
            return ['id' => 28, 'method' => 'trunc_granel_borracharia', 'score' => 85.0];
        }

        if (str_contains($key, 'granel de') && (str_contains($fantasia, 'motos') || str_contains($plano, 'moto'))) {
            return ['id' => 28, 'method' => 'trunc_granel_motos', 'score' => 80.0];
        }

        if (str_contains($key, 'tambores de') || str_contains($key, 'bombonas de')) {
            if (str_contains($fantasia, 'posto') || str_contains($fantasia, 'diesel')
                || str_contains($fantasia, 'lubrif') || str_contains($plano, 'retifica')) {
                return ['id' => 69, 'method' => 'trunc_oleo_container', 'score' => 82.0];
            }
            if (str_contains($plano, 'oficinas') || str_contains($fantasia, 'motos')) {
                return ['id' => 69, 'method' => 'trunc_oleo_oficina', 'score' => 80.0];
            }
        }

        if (str_contains($key, 'sacos de') && (str_contains($plano, 'oficinas') || str_contains($fantasia, 'motos'))) {
            return ['id' => 36, 'method' => 'trunc_vasilhame_oficina', 'score' => 78.0];
        }

        $siblings = $ctx['sibling_tipos'] ?? [];
        if ($siblings !== []) {
            $rss = in_array(16, $siblings, true);
            if ($rss && (str_contains($key, 'sacos de') || str_contains($key, 'caixas de'))) {
                return ['id' => 16, 'method' => 'trunc_sibling_rss', 'score' => 75.0];
            }
        }

        if (isset(self::CONTAINER_ALIASES[$key])) {
            return [
                'id' => self::CONTAINER_ALIASES[$key],
                'method' => 'trunc_container_default',
                'score' => 72.0,
            ];
        }

        return $empty;
    }

    private static function isTruncatedContainer(string $nomeLower): bool
    {
        return (bool) preg_match(
            '/^\d+\s+(?:big\s+bags?|sacos?|caixas?|tambores?|bombonas?|granel(?:es)?|outros?)\s+de\s*$/u',
            $nomeLower
        );
    }

    private static function isHealthClient(string $fantasia, string $plano): bool
    {
        if (str_contains($plano, 'rss') || str_contains($plano, 'vacina') || str_contains($plano, 'saude')) {
            return true;
        }

        $needles = [
            'vet', 'farm', 'clinica', 'clínica', 'vacina', 'pet clean', 'analisa',
            'polivet', 'ultra popular', 'emerg', 'ceo', 'silvestre', 'bruno scheffel',
        ];
        foreach ($needles as $n) {
            if (str_contains($fantasia, $n)) {
                return true;
            }
        }

        return false;
    }
}
