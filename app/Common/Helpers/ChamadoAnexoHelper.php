<?php

namespace App\Common\Helpers;

class ChamadoAnexoHelper
{
    private const MAX_BYTES = 5242880;
    private const MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public static function dirBase(): string
    {
        $root = rtrim(str_replace('\\', '/', realpath(__DIR__.'/../../../') ?: (__DIR__.'/../../..')), '/');

        return $root.'/storage/chamados';
    }

    public static function dirOperadora(int $operadoraId): string
    {
        return self::dirBase().'/'.(int)$operadoraId;
    }

    /** @return array{relative:string,nome:string,mime:string,bytes:int}|null */
    public static function salvarUpload(int $operadoraId, array $file): ?array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return null;
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)($finfo->file($tmp) ?: '');
        if (!isset(self::MIMES[$mime])) {
            return null;
        }

        $bin = file_get_contents($tmp);
        if ($bin === false || $bin === '') {
            return null;
        }

        $dir = self::dirOperadora($operadoraId);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }

        $ext = self::MIMES[$mime];
        $nomeOrig = basename((string)($file['name'] ?? ('print.'.$ext)));
        $nomeOrig = mb_substr(preg_replace('/[^\w.\-\sÀ-ÿ]+/u', '', $nomeOrig) ?: ('print.'.$ext), 0, 120);
        $nomeArq = date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
        $abs = $dir.'/'.$nomeArq;
        if (file_put_contents($abs, $bin) === false) {
            return null;
        }

        return [
            'relative' => 'storage/chamados/'.(int)$operadoraId.'/'.$nomeArq,
            'nome' => $nomeOrig,
            'mime' => $mime,
            'bytes' => strlen($bin),
        ];
    }

    public static function caminhoAbsoluto(string $relative): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }
        if (!str_starts_with($relative, 'storage/chamados/')) {
            return null;
        }
        $root = rtrim(str_replace('\\', '/', realpath(__DIR__.'/../../../') ?: (__DIR__.'/../../..')), '/');
        $abs = $root.'/'.$relative;
        $real = realpath($abs);
        $base = realpath(self::dirBase());
        if ($real === false || $base === false) {
            return is_file($abs) ? $abs : null;
        }
        $realN = str_replace('\\', '/', $real);
        $baseN = str_replace('\\', '/', $base);
        if (!str_starts_with($realN, $baseN.'/') && $realN !== $baseN) {
            return null;
        }

        return is_file($real) ? $real : null;
    }

    public static function mimePorArquivo(string $abs): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)($finfo->file($abs) ?: '');

        return isset(self::MIMES[$mime]) ? $mime : 'application/octet-stream';
    }
}
