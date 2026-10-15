<?php

namespace App\Service\Sinir;

/**
 * Lê metadados não sensíveis do JWT de acesso retornado pelo SINIR.
 */
final class SinirTokenInspector
{
    /**
     * O claim sub do apiws usa o formato "pessoaId,unidadeId".
     */
    public static function unidade(string $jwt): ?int
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return null;
        }

        $b64 = strtr($parts[1], '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        $json = base64_decode($b64, true);
        $data = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($data) || !is_string($data['sub'] ?? null)) {
            return null;
        }
        if (!preg_match('/^\d+,(\d+)$/', $data['sub'], $matches)) {
            return null;
        }

        $unidade = (int)$matches[1];

        return $unidade > 0 ? $unidade : null;
    }
}
