<?php

namespace App\Http;

class GeradorContext
{
    /** @var array<string, mixed>|null */
    private static ?array $user = null;

    /** @param array<string, mixed> $user */
    public static function setUser(array $user): void
    {
        self::$user = $user;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        return self::$user;
    }

    public static function clienteUsuarioId(): int
    {
        return (int)(self::$user['cliente_usuario_id'] ?? self::$user['id'] ?? 0);
    }

    public static function clienteId(): int
    {
        return (int)(self::$user['cliente_id'] ?? 0);
    }

    public static function operadoraId(): int
    {
        return (int)(self::$user['operadora_id'] ?? 1);
    }

    public static function clear(): void
    {
        self::$user = null;
    }
}
