<?php

namespace App\Common\Helpers;

use App\Common\CompanyConfig;
use App\Model\Db\Database;

final class ContratoBrandingHelper
{
    /** Caminho relativo à raiz do app (após {{URL}}). */
    public const LOGO_PADRAO = '/resources/assets/imgs/logo.png';

    public static function logoUrl(int $operadoraId = 1): string
    {
        $path = self::logoPathRelativo($operadoraId);
        if ($path === '') {
            $path = self::LOGO_PADRAO;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        $base = rtrim((string)URL, '/');
        if (!str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return $base.$path;
    }

    public static function logoPathRelativo(int $operadoraId): string
    {
        $custom = self::configOperadora($operadoraId, 'contrato_logo_path');
        if ($custom !== null && trim($custom) !== '') {
            return trim($custom);
        }
        $url = self::configOperadora($operadoraId, 'contrato_logo_url');
        if ($url !== null && trim($url) !== '') {
            $u = trim($url);
            if (str_starts_with($u, 'http://') || str_starts_with($u, 'https://')) {
                return $u;
            }

            return str_starts_with($u, '/') ? $u : '/'.$u;
        }

        return self::LOGO_PADRAO;
    }

    public static function logoHtml(int $operadoraId = 1): string
    {
        $path = self::logoPathRelativo($operadoraId);
        $src = self::logoUrl($operadoraId);
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $src = $path;
        }
        $alt = htmlspecialchars(CompanyConfig::name($operadoraId), ENT_QUOTES, 'UTF-8');
        $srcEsc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');

        return '<header class="contrato-logo-header">'
            .'<img src="'.$srcEsc.'" alt="'.$alt.'" class="contrato-logo" width="200" height="auto" />'
            .'</header>';
    }

    private static function configOperadora(int $operadoraId, string $chave): ?string
    {
        try {
            $db = new Database();
            $row = $db->execute(
                'SELECT valor FROM operadora_config WHERE operadora_id = ? AND chave = ? LIMIT 1',
                [$operadoraId, $chave]
            )->fetch(\PDO::FETCH_ASSOC);

            return is_array($row) ? (string)$row['valor'] : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
