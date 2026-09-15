<?php

namespace App\Common;

use App\Model\Entity\ConfigSistema;

class CobrancaConfig
{
    /** @return array{tipo:string,taxa:float,valor:float} */
    public static function multa(): array
    {
        return self::loadMultaMora('multa', [
            'tipo' => strtoupper(trim((string)Environment::get('COBRANCA_MULTA_TIPO', 'PERCENTUAL'))),
            'taxa' => (float)Environment::get('COBRANCA_MULTA_TAXA', '2'),
            'valor' => (float)Environment::get('COBRANCA_MULTA_VALOR', '0'),
        ]);
    }

    /** @return array{tipo:string,taxa:float,valor:float} */
    public static function mora(): array
    {
        return self::loadMultaMora('mora', [
            'tipo' => strtoupper(trim((string)Environment::get('COBRANCA_MORA_TIPO', 'TAXAMENSAL'))),
            'taxa' => (float)Environment::get('COBRANCA_MORA_TAXA', '1'),
            'valor' => (float)Environment::get('COBRANCA_MORA_VALOR', '0'),
        ]);
    }

    /** @return array{multa?:array<string,mixed>,mora?:array<string,mixed>} Cobrança v3 — campos codigo/taxa/valor (sem data). */
    public static function toInterPayload(string $dataVencimento, ?array $override = null): array
    {
        $multa = self::resolveMulta($override['multa'] ?? null);
        $mora = self::resolveMora($override['mora'] ?? null);

        $result = [];

        $codigoMulta = self::mapMultaTipo($multa['tipo']);
        if ($codigoMulta !== null) {
            $result['multa'] = [
                'codigo' => $codigoMulta,
                'taxa' => $codigoMulta === 'PERCENTUAL' ? (float)$multa['taxa'] : 0.0,
                'valor' => $codigoMulta === 'VALORFIXO' ? (float)$multa['valor'] : 0.0,
            ];
        }

        $codigoMora = self::mapMoraTipo($mora['tipo']);
        if ($codigoMora !== null) {
            $result['mora'] = [
                'codigo' => $codigoMora,
                'taxa' => $codigoMora === 'TAXAMENSAL' ? (float)$mora['taxa'] : 0.0,
                'valor' => $codigoMora === 'VALORDIA' ? (float)$mora['valor'] : 0.0,
            ];
        }

        return $result;
    }

    /** @return array{multa:array{tipo:string,taxa:float,valor:float},mora:array{tipo:string,taxa:float,valor:float}} */
    public static function snapshot(?array $override = null): array
    {
        return [
            'multa' => self::resolveMulta($override['multa'] ?? null),
            'mora' => self::resolveMora($override['mora'] ?? null),
        ];
    }

    /** @param array<string,mixed> $post */
    public static function saveFromPost(array $post): void
    {
        ConfigSistema::setMany([
            'cobranca.multa_tipo' => self::sanitizeMultaTipo((string)($post['multa_tipo'] ?? 'PERCENTUAL')),
            'cobranca.multa_taxa' => self::formatDecimal($post['multa_taxa'] ?? '0'),
            'cobranca.multa_valor' => self::formatDecimal($post['multa_valor'] ?? '0'),
            'cobranca.mora_tipo' => self::sanitizeMoraTipo((string)($post['mora_tipo'] ?? 'TAXAMENSAL')),
            'cobranca.mora_taxa' => self::formatDecimal($post['mora_taxa'] ?? '0'),
            'cobranca.mora_valor' => self::formatDecimal($post['mora_valor'] ?? '0'),
        ]);
    }

    /** @param array<string,mixed> $post */
    public static function parseOverrideFromPost(array $post): array
    {
        return [
            'multa' => [
                'tipo' => self::sanitizeMultaTipo((string)($post['lote_multa_tipo'] ?? self::multa()['tipo'])),
                'taxa' => (float)str_replace(',', '.', (string)($post['lote_multa_taxa'] ?? self::multa()['taxa'])),
                'valor' => (float)str_replace(',', '.', (string)($post['lote_multa_valor'] ?? self::multa()['valor'])),
            ],
            'mora' => [
                'tipo' => self::sanitizeMoraTipo((string)($post['lote_mora_tipo'] ?? self::mora()['tipo'])),
                'taxa' => (float)str_replace(',', '.', (string)($post['lote_mora_taxa'] ?? self::mora()['taxa'])),
                'valor' => (float)str_replace(',', '.', (string)($post['lote_mora_valor'] ?? self::mora()['valor'])),
            ],
        ];
    }

    public static function resumoMultaMora(?array $override = null): string
    {
        $snap = self::snapshot($override);
        $parts = [];

        if (self::mapMultaTipo($snap['multa']['tipo']) !== null) {
            $parts[] = $snap['multa']['tipo'] === 'VALORFIXO'
                ? 'Multa R$ '.number_format($snap['multa']['valor'], 2, ',', '.')
                : 'Multa '.number_format($snap['multa']['taxa'], 2, ',', '.').'%';
        } else {
            $parts[] = 'Multa isenta';
        }

        if (self::mapMoraTipo($snap['mora']['tipo']) !== null) {
            $parts[] = $snap['mora']['tipo'] === 'VALORDIA'
                ? 'Juros R$ '.number_format($snap['mora']['valor'], 2, ',', '.').'/dia'
                : 'Juros '.number_format($snap['mora']['taxa'], 2, ',', '.').'% a.m.';
        } else {
            $parts[] = 'Juros isento';
        }

        return implode(' · ', $parts);
    }

    /** @param array{tipo:string,taxa:float,valor:float} $fallback */
    private static function loadMultaMora(string $prefix, array $fallback): array
    {
        $db = ConfigSistema::getMany([
            'cobranca.'.$prefix.'_tipo',
            'cobranca.'.$prefix.'_taxa',
            'cobranca.'.$prefix.'_valor',
        ]);

        $tipoKey = 'cobranca.'.$prefix.'_tipo';
        if (!isset($db[$tipoKey])) {
            return $fallback;
        }

        return [
            'tipo' => $prefix === 'multa'
                ? self::sanitizeMultaTipo($db[$tipoKey])
                : self::sanitizeMoraTipo($db[$tipoKey]),
            'taxa' => (float)($db['cobranca.'.$prefix.'_taxa'] ?? $fallback['taxa']),
            'valor' => (float)($db['cobranca.'.$prefix.'_valor'] ?? $fallback['valor']),
        ];
    }

    /** @param array{tipo:string,taxa:float,valor:float}|null $override */
    private static function resolveMulta(?array $override): array
    {
        $base = self::multa();
        if ($override === null) {
            return $base;
        }

        return [
            'tipo' => self::sanitizeMultaTipo($override['tipo'] ?? $base['tipo']),
            'taxa' => (float)($override['taxa'] ?? $base['taxa']),
            'valor' => (float)($override['valor'] ?? $base['valor']),
        ];
    }

    /** @param array{tipo:string,taxa:float,valor:float}|null $override */
    private static function resolveMora(?array $override): array
    {
        $base = self::mora();
        if ($override === null) {
            return $base;
        }

        return [
            'tipo' => self::sanitizeMoraTipo($override['tipo'] ?? $base['tipo']),
            'taxa' => (float)($override['taxa'] ?? $base['taxa']),
            'valor' => (float)($override['valor'] ?? $base['valor']),
        ];
    }

    private static function sanitizeMultaTipo(string $tipo): string
    {
        $tipo = strtoupper(trim($tipo));

        return in_array($tipo, ['PERCENTUAL', 'VALORFIXO', 'ISENTO'], true) ? $tipo : 'PERCENTUAL';
    }

    private static function sanitizeMoraTipo(string $tipo): string
    {
        $tipo = strtoupper(trim($tipo));

        return in_array($tipo, ['TAXAMENSAL', 'VALORDIA', 'ISENTO'], true) ? $tipo : 'TAXAMENSAL';
    }

    private static function mapMultaTipo(string $tipo): ?string
    {
        return match (strtoupper($tipo)) {
            'PERCENTUAL' => 'PERCENTUAL',
            'VALORFIXO' => 'VALORFIXO',
            default => null,
        };
    }

    private static function mapMoraTipo(string $tipo): ?string
    {
        return match (strtoupper($tipo)) {
            'TAXAMENSAL' => 'TAXAMENSAL',
            'VALORDIA' => 'VALORDIA',
            default => null,
        };
    }

    private static function formatDecimal(mixed $value): string
    {
        return number_format((float)str_replace(',', '.', (string)$value), 2, '.', '');
    }
}
