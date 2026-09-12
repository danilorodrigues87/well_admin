<?php

namespace App\Common;

class ApiConfig
{
    public static function jwtSecret(): string
    {
        $secret = trim((string)Environment::get('API_JWT_SECRET', ''));
        if ($secret === '') {
            throw new \RuntimeException('API_JWT_SECRET não configurado no .env');
        }

        return $secret;
    }

    public static function jwtTtlSeconds(): int
    {
        return max(300, (int)Environment::get('API_JWT_TTL', 86400));
    }

    /** @return string[] */
    public static function corsOrigins(): array
    {
        $raw = trim((string)Environment::get('API_CORS_ORIGINS', '*'));
        if ($raw === '' || $raw === '*') {
            return ['*'];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public static function isEnabled(): bool
    {
        return filter_var(Environment::get('API_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN);
    }
}
