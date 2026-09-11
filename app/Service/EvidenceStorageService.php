<?php

namespace App\Service;

use App\Model\Entity\ColetaEvidencia as EntityColetaEvidencia;

class EvidenceStorageService
{
    /** Limite upload wizard (coletor). */
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** Limite download ETL legado (Cloudinary pode enviar JPG > 5 MB). */
    public const MAX_DOWNLOAD_BYTES = 12 * 1024 * 1024;

    public const MAX_WIDTH = 1920;

    public const WEBP_QUALITY = 85;

    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public static function supportsWebp(): bool
    {
        return extension_loaded('gd') && function_exists('imagewebp');
    }

    /** @param array{name:string,type:string,tmp_name:string,error:int,size?:int} $file */
    public static function saveUploaded(int $coletaId, int $ordem, array $file): ?array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            return null;
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return null;
        }

        $mime = self::detectMime($tmp, (string)($file['type'] ?? ''));
        if (!self::isAllowedMime($mime)) {
            return null;
        }

        return self::persistFromSource($coletaId, $ordem, $tmp, $mime);
    }

    public static function saveFromPath(int $coletaId, int $ordem, string $sourcePath, ?string $hintMime = null, ?int $maxBytes = null): ?array
    {
        $maxBytes ??= self::MAX_BYTES;
        if (!is_file($sourcePath) || filesize($sourcePath) > $maxBytes) {
            return null;
        }

        $mime = self::detectMime($sourcePath, (string)$hintMime);
        if (!self::isAllowedMime($mime)) {
            return null;
        }

        return self::persistFromSource($coletaId, $ordem, $sourcePath, $mime);
    }

    public static function downloadAndSave(int $coletaId, int $ordem, string $url): ?array
    {
        $url = self::optimizeLegacyDownloadUrl(trim($url));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'well_ev_');
        if ($tmp === false) {
            return null;
        }

        $ctx = stream_context_create([
            'http' => ['timeout' => 60, 'follow_location' => 1],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $data = @file_get_contents($url, false, $ctx);
        if ($data === false || strlen($data) > self::MAX_DOWNLOAD_BYTES) {
            @unlink($tmp);
            return null;
        }

        if (file_put_contents($tmp, $data) === false) {
            @unlink($tmp);
            return null;
        }

        $result = self::saveFromPath($coletaId, $ordem, $tmp, null, self::MAX_DOWNLOAD_BYTES);
        @unlink($tmp);

        return $result;
    }

    /** @return array{arquivo:string,mime:string}|null */
    private static function persistFromSource(int $coletaId, int $ordem, string $sourcePath, string $mime): ?array
    {
        $baseDir = dirname(__DIR__, 2).'/storage/coletas/'.$coletaId;
        if (!is_dir($baseDir) && !mkdir($baseDir, 0755, true)) {
            return null;
        }

        if (self::supportsWebp()) {
            $filename = 'evidencia_'.$ordem.'.webp';
            $dest = $baseDir.'/'.$filename;
            if (self::convertToWebp($sourcePath, $mime, $dest)) {
                return [
                    'arquivo' => 'coletas/'.$coletaId.'/'.$filename,
                    'mime' => 'image/webp',
                ];
            }
        }

        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $filename = 'evidencia_'.$ordem.'.'.$ext;
        $dest = $baseDir.'/'.$filename;

        $moved = is_uploaded_file($sourcePath)
            ? move_uploaded_file($sourcePath, $dest)
            : copy($sourcePath, $dest);
        if (!$moved) {
            return null;
        }

        return [
            'arquivo' => 'coletas/'.$coletaId.'/'.$filename,
            'mime' => $mime,
        ];
    }

    private static function convertToWebp(string $sourcePath, string $mime, string $dest): bool
    {
        $image = self::loadImage($sourcePath, $mime);
        if ($image === null) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($image);
            return false;
        }

        if ($width > self::MAX_WIDTH) {
            $newHeight = (int)round($height * (self::MAX_WIDTH / $width));
            $resized = imagescale($image, self::MAX_WIDTH, max(1, $newHeight));
            imagedestroy($image);
            if ($resized === false) {
                return false;
            }
            $image = $resized;
        }

        $ok = imagewebp($image, $dest, self::WEBP_QUALITY);
        imagedestroy($image);

        return $ok && is_file($dest);
    }

    /** @return \GdImage|resource|null */
    private static function loadImage(string $path, string $mime)
    {
        return match ($mime) {
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => @imagecreatefromjpeg($path),
        };
    }

    private static function detectMime(string $path, string $hint = ''): string
    {
        if ($hint !== '' && self::isAllowedMime($hint)) {
            return $hint;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string)finfo_file($finfo, $path) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        return $mime;
    }

    private static function isAllowedMime(string $mime): bool
    {
        return in_array($mime, self::ALLOWED_MIMES, true);
    }

    /** Reduz JPGs grandes do Cloudinary legado antes do download. */
    public static function optimizeLegacyDownloadUrl(string $url): string
    {
        if ($url === '' || !str_contains($url, 'res.cloudinary.com') || !str_contains($url, '/image/upload/')) {
            return $url;
        }

        if (preg_match('#/image/upload/[^v/][^/]*/v\d+#', $url)) {
            return $url;
        }

        if (preg_match('#/image/upload/v\d+#', $url)) {
            $optimized = preg_replace('#(/image/upload/)#', '$1q_auto,w_1920/', $url, 1);

            return is_string($optimized) ? $optimized : $url;
        }

        return $url;
    }

    /** @param array<int, array{name:string,type:string,tmp_name:string,error:int}> $files */
    public static function saveBatchForColeta(int $coletaId, array $files): void
    {
        if (empty($files)) {
            return;
        }

        $ordem = 1;
        foreach ($files as $file) {
            if ($ordem > 3) {
                break;
            }

            $saved = self::saveUploaded($coletaId, $ordem, $file);
            if ($saved === null) {
                continue;
            }

            EntityColetaEvidencia::insert([
                'coleta_id' => $coletaId,
                'ordem' => $ordem,
                'arquivo' => $saved['arquivo'],
                'mime' => $saved['mime'],
            ]);
            $ordem++;
        }
    }
}
