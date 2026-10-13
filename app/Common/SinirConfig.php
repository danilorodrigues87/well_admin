<?php

namespace App\Common;

use App\Model\Entity\Operadora;

/**
 * Config SINIR.
 *
 * Credenciais de negócio: preferência `operadoras.sinir_*` (tenant), fallback `.env` (piloto Well).
 * Flags globais (enabled, env, ssl) ficam só no `.env`.
 */
class SinirConfig
{
    /** @var array{token:string,unidade:int,unidade_destinador:int,cnpj:string}|null */
    private static ?array $tenantCache = null;

    public static function isEnabled(): bool
    {
        return filter_var(Environment::get('SINIR_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function baseUrl(): string
    {
        $env = (string)Environment::get('SINIR_ENV', 'production');
        if ($env === 'homolog') {
            return 'https://homolog-admin.sinir.gov.br/apiws/rest';
        }

        return 'https://admin.sinir.gov.br/apiws/rest';
    }

    public static function unidade(): int
    {
        $t = self::tenantSinir();
        if ($t['unidade'] > 0) {
            return $t['unidade'];
        }

        return (int)Environment::get('SINIR_UNIDADE', 0);
    }

    /** Unidade destinador padrão da operadora (default: mesma unidade transportador). */
    public static function destinadorUnidade(): int
    {
        $t = self::tenantSinir();
        if ($t['unidade_destinador'] > 0) {
            return $t['unidade_destinador'];
        }
        $dest = (int)Environment::get('SINIR_UNIDADE_DESTINADOR', 0);

        return $dest > 0 ? $dest : self::unidade();
    }

    public static function cnpj(): string
    {
        $t = self::tenantSinir();
        if ($t['cnpj'] !== '') {
            return $t['cnpj'];
        }

        return preg_replace('/\D/', '', (string)Environment::get('SINIR_CNPJ', '')) ?? '';
    }

    /** Token da operadora (listas/sync) — não é o token do gerador nem de destinador terceiro. */
    public static function integrationToken(): string
    {
        $t = self::tenantSinir();
        if ($t['token'] !== '') {
            return $t['token'];
        }

        return trim((string)Environment::get('SINIR_INTEGRATION_TOKEN', ''));
    }

    public static function isConfigured(): bool
    {
        return self::integrationToken() !== ''
            && self::unidade() > 0
            && self::cnpj() !== '';
    }

    /** false apenas em dev local (ex.: XAMPP sem CA bundle). Produção: sempre true. */
    public static function sslVerify(): bool
    {
        return filter_var(Environment::get('SINIR_SSL_VERIFY', 'true'), FILTER_VALIDATE_BOOLEAN);
    }

    /** Limpa cache (testes CLI / troca de tenant). */
    public static function clearTenantCache(): void
    {
        self::$tenantCache = null;
    }

    /** @return array{token:string,unidade:int,unidade_destinador:int,cnpj:string} */
    private static function tenantSinir(): array
    {
        if (self::$tenantCache !== null) {
            return self::$tenantCache;
        }

        $empty = ['token' => '', 'unidade' => 0, 'unidade_destinador' => 0, 'cnpj' => ''];
        try {
            $opId = OperadoraScope::getOperadoraId();
            if ($opId <= 0) {
                return self::$tenantCache = $empty;
            }
            $op = Operadora::getById($opId);
            if (!$op) {
                return self::$tenantCache = $empty;
            }

            return self::$tenantCache = [
                'token' => trim((string)$op->sinir_integration_token),
                'unidade' => (int)preg_replace('/\D/', '', (string)$op->sinir_unidade),
                'unidade_destinador' => (int)preg_replace('/\D/', '', (string)$op->sinir_unidade_destinador),
                'cnpj' => preg_replace('/\D/', '', (string)($op->sinir_cnpj !== '' ? $op->sinir_cnpj : $op->cnpj)) ?? '',
            ];
        } catch (\Throwable) {
            return self::$tenantCache = $empty;
        }
    }
}
