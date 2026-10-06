<?php

namespace App\Common;

/**
 * Raiz persistente de arquivos (evidências, Inter, boletos, chamados).
 *
 * Em EasyPanel/Docker o bind mount deve apontar para o mesmo caminho que o PHP usa.
 * Padrão: {raiz do projeto}/storage
 * Opcional: STORAGE_PATH no .env (absoluto ou relativo à raiz do projeto).
 */
class StoragePath
{
    public static function projectRoot(): string
    {
        return rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/');
    }

    public static function root(): string
    {
        $configured = trim((string)Environment::get('STORAGE_PATH', ''));
        if ($configured === '') {
            return self::projectRoot().'/storage';
        }

        return self::resolve($configured);
    }

    /** Caminho absoluto sob storage (ex.: coletas/12/evidencia_1.webp). */
    public static function join(string $relative = ''): string
    {
        $relative = str_replace('\\', '/', ltrim($relative, '/'));
        if ($relative === '') {
            return self::root();
        }

        return self::root().'/'.$relative;
    }

    /** Garante diretório (0755) e retorna o caminho absoluto. */
    public static function ensureDir(string $relative): string
    {
        $dir = self::join($relative);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar pasta em storage: '.$dir);
        }

        return $dir;
    }

    public static function resolve(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '') {
            return self::projectRoot().'/storage';
        }
        if (preg_match('#^[A-Za-z]:/#', $path) || str_starts_with($path, '/')) {
            return rtrim($path, '/');
        }

        return self::projectRoot().'/'.ltrim($path, '/');
    }

    /** @return array{root:string,writable:bool,exists:bool,source:string} */
    public static function diagnose(): array
    {
        $configured = trim((string)Environment::get('STORAGE_PATH', ''));
        $root = self::root();

        return [
            'root' => $root,
            'exists' => is_dir($root),
            'writable' => is_dir($root) && is_writable($root),
            'source' => $configured !== '' ? 'STORAGE_PATH' : 'project/storage',
        ];
    }
}
