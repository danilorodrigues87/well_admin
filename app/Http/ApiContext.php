<?php

namespace App\Http;

class ApiContext
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

    public static function userId(): int
    {
        return (int)(self::$user['id'] ?? 0);
    }

    public static function isAdmin(): bool
    {
        return !empty(self::$user['is_admin']);
    }

    public static function clear(): void
    {
        self::$user = null;
    }
}
