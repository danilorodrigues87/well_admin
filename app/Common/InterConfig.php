<?php

namespace App\Common;

class InterConfig
{
    public static function isEnabled(): bool
    {
        return filter_var(Environment::get('INTER_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function env(): string
    {
        $env = strtolower(trim((string)Environment::get('INTER_ENV', 'production')));

        return $env === 'sandbox' ? 'sandbox' : 'production';
    }

    public static function baseUrl(): string
    {
        if (self::env() === 'sandbox') {
            return 'https://cdpj-sandbox.partners.uatinter.co';
        }

        return 'https://cdpj.partners.bancointer.com.br';
    }

    public static function clientId(): string
    {
        return trim((string)Environment::get('INTER_CLIENT_ID', ''));
    }

    public static function clientSecret(): string
    {
        return trim((string)Environment::get('INTER_CLIENT_SECRET', ''));
    }

    public static function contaCorrente(): string
    {
        return preg_replace('/\D/', '', (string)Environment::get('INTER_CONTA_CORRENTE', ''));
    }

    public static function cnpj(): string
    {
        return preg_replace('/\D/', '', (string)Environment::get('INTER_CNPJ', ''));
    }

    public static function scope(): string
    {
        $default = 'boleto-cobranca.read boleto-cobranca.write webhook.read webhook.write';

        return trim((string)Environment::get('INTER_SCOPE', $default));
    }

    public static function certPath(): string
    {
        $configured = trim((string)Environment::get('INTER_CERT_PATH', ''));
        if ($configured !== '') {
            return self::resolvePath($configured);
        }

        return self::projectPath('storage/inter/certificado.crt');
    }

    public static function keyPath(): string
    {
        $configured = trim((string)Environment::get('INTER_KEY_PATH', ''));
        if ($configured !== '') {
            return self::resolvePath($configured);
        }

        return self::projectPath('storage/inter/chave.key');
    }

    public static function keyPassword(): ?string
    {
        $password = trim((string)Environment::get('INTER_KEY_PASSWORD', ''));

        return $password !== '' ? $password : null;
    }

    public static function tokenCachePath(): string
    {
        $configured = trim((string)Environment::get('INTER_TOKEN_CACHE_PATH', ''));
        if ($configured !== '') {
            return self::resolvePath($configured);
        }

        return self::projectPath('storage/inter/oauth-token.json');
    }

    public static function webhookUrl(): string
    {
        return trim((string)Environment::get('INTER_WEBHOOK_URL', ''));
    }

    /** Segredo opcional validado via header X-Webhook-Secret no endpoint local. */
    public static function webhookSecret(): string
    {
        return trim((string)Environment::get('INTER_WEBHOOK_SECRET', ''));
    }

    public static function isConfigured(): bool
    {
        return self::clientId() !== ''
            && self::clientSecret() !== ''
            && self::contaCorrente() !== ''
            && is_readable(self::certPath())
            && is_readable(self::keyPath());
    }

    /** @return list<string> */
    public static function configurationErrors(): array
    {
        $errors = [];

        if (self::clientId() === '') {
            $errors[] = 'INTER_CLIENT_ID ausente no .env';
        }
        if (self::clientSecret() === '') {
            $errors[] = 'INTER_CLIENT_SECRET ausente no .env';
        }
        if (self::contaCorrente() === '') {
            $errors[] = 'INTER_CONTA_CORRENTE ausente no .env';
        }
        if (!is_readable(self::certPath())) {
            $errors[] = 'Certificado não encontrado: '.self::certPath();
        }
        if (!is_readable(self::keyPath())) {
            $errors[] = 'Chave privada não encontrada: '.self::keyPath();
        }

        return $errors;
    }

    private static function projectPath(string $relative): string
    {
        return rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/').'/'.ltrim($relative, '/');
    }

    private static function resolvePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (preg_match('#^[A-Za-z]:/#', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        return self::projectPath($path);
    }
}
