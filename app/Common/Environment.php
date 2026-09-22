<?php

namespace App\Common;

class Environment
{
    public static function load(string $dir): bool
    {
        $path = rtrim(str_replace('\\', '/', $dir), '/').'/.env';
        if (!is_file($path)) {
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }
            // Variáveis já injetadas (Docker / Easypanel Environment) têm prioridade sobre o arquivo .env
            if (self::envAlreadySet($key)) {
                continue;
            }
            $len = strlen($value);
            if ($len >= 2) {
                $q = $value[0];
                if (($q === '"' || $q === "'") && $value[$len - 1] === $q) {
                    $value = substr($value, 1, -1);
                }
            }
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        return true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        return $default;
    }

    private static function envAlreadySet(string $key): bool
    {
        if (getenv($key) !== false) {
            return true;
        }
        if (isset($_SERVER[$key]) && (string)$_SERVER[$key] !== '') {
            return true;
        }

        return array_key_exists($key, $_ENV) && (string)$_ENV[$key] !== '';
    }
}
