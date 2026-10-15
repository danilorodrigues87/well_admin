<?php

namespace App\Service\Sinir;

/**
 * Lê metadados não sensíveis do JWT de acesso retornado pelo SINIR.
 *
 * Atenção: o segundo valor de sub é um identificador interno do contexto
 * autenticado. Ele não é o parCodigo público usado no payload dos parceiros.
 */
final class SinirTokenInspector
{
    /** @return array<string,mixed> */
    public static function claimsPublicos(string $jwt): array
    {
        $data = self::payload($jwt);
        if ($data === null) {
            return [];
        }

        $permitidos = [
            'sub',
            'role',
            'roles',
            'authorities',
            'parCodigo',
            'pessoaCodigo',
            'unidade',
            'unidadeId',
            'iss',
            'exp',
        ];
        $out = [];
        foreach ($permitidos as $key) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $data[$key];
            }
        }

        return $out;
    }

    /**
     * Extrai o segundo identificador interno do claim sub ("id,id").
     * Não comparar com transportadoras/destinadores.sinir_cod_unidade.
     */
    public static function unidade(string $jwt): ?int
    {
        $data = self::payload($jwt);
        if ($data === null || !is_string($data['sub'] ?? null)) {
            return null;
        }
        if (!preg_match('/^\d+,(\d+)$/', $data['sub'], $matches)) {
            return null;
        }

        $unidade = (int)$matches[1];

        return $unidade > 0 ? $unidade : null;
    }

    /** @return array<string,mixed>|null */
    private static function payload(string $jwt): ?array
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

        return is_array($data) ? $data : null;
    }
}
