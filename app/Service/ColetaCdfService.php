<?php

namespace App\Service;

use App\Common\StoragePath;
use App\Model\Entity\Coleta as EntityColeta;

/**
 * Armazena PDF do MTR SINIR ou CDF enviado manualmente (CDF light — portal gerador).
 */
class ColetaCdfService
{
    private static function dirForColeta(int $coletaId): string
    {
        return StoragePath::ensureDir('coletas/'.$coletaId);
    }

    public static function relativePath(int $coletaId, string $filename = 'cdf.pdf'): string
    {
        return 'coletas/'.$coletaId.'/'.$filename;
    }

    public static function absolutePath(EntityColeta $c): ?string
    {
        $rel = trim((string)($c->cdf_path ?? ''));
        if ($rel === '') {
            return null;
        }
        $path = StoragePath::join($rel);

        return is_readable($path) ? $path : null;
    }

    public static function temCdf(EntityColeta $c): bool
    {
        return self::absolutePath($c) !== null;
    }

    public static function absoluteMtrOficial(int $coletaId): ?string
    {
        $path = StoragePath::join(self::relativePath($coletaId, 'mtr_sinir.pdf'));

        return is_readable($path) ? $path : null;
    }

    /**
     * Grava o PDF oficial do manifesto sem substituir um CDF já vinculado ao portal.
     *
     * @return array{ok:bool,message:string,path?:string}
     */
    public static function gravarMtrOficial(int $coletaId, string $binary): array
    {
        if ($binary === '' || !str_starts_with($binary, '%PDF')) {
            return ['ok' => false, 'message' => 'Resposta não é um PDF válido.'];
        }

        $dir = self::dirForColeta($coletaId);
        $full = $dir.'/mtr_sinir.pdf';
        if (file_put_contents($full, $binary) === false) {
            return ['ok' => false, 'message' => 'Não foi possível gravar o PDF em storage.'];
        }

        $rel = self::relativePath($coletaId, 'mtr_sinir.pdf');
        $coleta = EntityColeta::getById($coletaId);
        $tipo = (string)($coleta->cdf_tipo ?? '');
        if ($coleta && !in_array($tipo, ['sinir_cdf', 'manual'], true)) {
            EntityColeta::update($coletaId, [
                'cdf_path' => $rel,
                'cdf_obtido_em' => date('Y-m-d H:i:s'),
                'cdf_tipo' => 'mtr_pdf',
            ]);
        }

        return ['ok' => true, 'message' => 'PDF salvo.', 'path' => $rel];
    }

    public static function rotuloTipo(?string $cdfTipo, ?string $sinirCdfCodigo): string
    {
        return match ($cdfTipo) {
            'sinir_cdf' => $sinirCdfCodigo ? 'CDF SINIR #'.$sinirCdfCodigo : 'CDF SINIR',
            'mtr_pdf' => 'PDF do MTR (SINIR)',
            'manual' => 'PDF anexado pela operação',
            default => 'Documento PDF',
        };
    }

    /** @return array{ok:bool,message:string,path?:string} */
    public static function gravarPdf(
        int $coletaId,
        string $binary,
        string $filename = 'cdf.pdf',
        ?string $cdfTipo = null,
        ?string $sinirCdfCodigo = null
    ): array {
        if ($binary === '' || !str_starts_with($binary, '%PDF')) {
            return ['ok' => false, 'message' => 'Resposta não é um PDF válido.'];
        }

        $dir = self::dirForColeta($coletaId);
        $safeName = preg_match('/^[a-z0-9_.-]+$/i', $filename) ? $filename : 'cdf.pdf';
        $full = $dir.'/'.$safeName;
        if (file_put_contents($full, $binary) === false) {
            return ['ok' => false, 'message' => 'Não foi possível gravar o PDF em storage.'];
        }

        $rel = self::relativePath($coletaId, $safeName);
        $update = [
            'cdf_path' => $rel,
            'cdf_obtido_em' => date('Y-m-d H:i:s'),
        ];
        if ($cdfTipo !== null) {
            $update['cdf_tipo'] = $cdfTipo;
        }
        if ($sinirCdfCodigo !== null && $sinirCdfCodigo !== '') {
            $update['sinir_cdf_codigo'] = $sinirCdfCodigo;
        }
        EntityColeta::update($coletaId, $update);

        return ['ok' => true, 'message' => 'PDF salvo.', 'path' => $rel];
    }

    /** @return array{ok:bool,message:string} */
    public static function gravarUpload(int $coletaId, string $tmpPath, string $mime): array
    {
        if (!is_readable($tmpPath)) {
            return ['ok' => false, 'message' => 'Arquivo inválido.'];
        }
        if ($mime !== 'application/pdf' && $mime !== 'application/x-pdf') {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected = $finfo ? finfo_file($finfo, $tmpPath) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
            if ($detected !== 'application/pdf') {
                return ['ok' => false, 'message' => 'Envie apenas PDF.'];
            }
        }
        $size = filesize($tmpPath);
        if ($size === false || $size > 5 * 1024 * 1024) {
            return ['ok' => false, 'message' => 'PDF deve ter no máximo 5 MB.'];
        }

        $binary = file_get_contents($tmpPath);
        if ($binary === false) {
            return ['ok' => false, 'message' => 'Falha ao ler o arquivo.'];
        }

        return self::gravarPdf($coletaId, $binary, 'cdf_manual.pdf', 'manual');
    }
}
