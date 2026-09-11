<?php

namespace App\Common\Helpers;

class CsrfHelper
{
    private const SESSION_KEY = 'well_eco_csrf';

    public static function init(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
    }

    public static function getToken(): string
    {
        self::init();
        return $_SESSION[self::SESSION_KEY] ?? '';
    }

    public static function field(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf" value="'.$token.'">';
    }

    public static function validate(?string $token): bool
    {
        self::init();
        $expected = $_SESSION[self::SESSION_KEY] ?? '';
        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }
}
