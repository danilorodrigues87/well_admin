<?php

namespace App\Common;

class SinirConfig
{
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
        return (int)Environment::get('SINIR_UNIDADE', 0);
    }

    /** Unidade destinador (default: mesma da Well). */
    public static function destinadorUnidade(): int
    {
        $dest = (int)Environment::get('SINIR_UNIDADE_DESTINADOR', 0);

        return $dest > 0 ? $dest : self::unidade();
    }

    public static function cnpj(): string
    {
        return preg_replace('/\D/', '', (string)Environment::get('SINIR_CNPJ', ''));
    }

    public static function integrationToken(): string
    {
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
}
