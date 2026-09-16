<?php

namespace App\Service;

use App\Model\Db\Database;

class TermosDeUsoService
{
    public const VERSAO = '2026-09';
    public const DATA_VERSAO = '16/09/2026';

    public static function usuarioAceitouVersaoAtual(?int $usuarioId = null): bool
    {
        if ($usuarioId === null) {
            return false;
        }
        $row = self::getUsuarioTermosRow($usuarioId);
        if (!$row || (int)($row['termos_uso'] ?? 0) !== 1) {
            return false;
        }
        $v = trim((string)($row['termos_versao'] ?? ''));

        return $v === self::VERSAO;
    }

    public static function clienteUsuarioAceitouVersaoAtual(?int $clienteUsuarioId = null): bool
    {
        if ($clienteUsuarioId === null) {
            return false;
        }
        $row = self::getClienteUsuarioTermosRow($clienteUsuarioId);
        if (!$row || (int)($row['termos_uso'] ?? 0) !== 1) {
            return false;
        }
        $v = trim((string)($row['termos_versao'] ?? ''));

        return $v === self::VERSAO;
    }

    public static function registrarAceiteAdmin(int $usuarioId): bool
    {
        if ($usuarioId <= 0) {
            return false;
        }

        $db = new Database();
        $db->execute(
            'UPDATE usuarios SET termos_uso = 1, termos_versao = ?, termos_aceito_em = ? WHERE id = ?',
            [self::VERSAO, date('Y-m-d H:i:s'), $usuarioId]
        );

        return self::usuarioAceitouVersaoAtual($usuarioId);
    }

    public static function registrarAceiteGerador(int $clienteUsuarioId): bool
    {
        if ($clienteUsuarioId <= 0) {
            return false;
        }
        $db = new Database();
        $db->execute(
            'UPDATE cliente_usuarios SET termos_uso = 1, termos_versao = ?, termos_aceito_em = NOW()
             WHERE id = ?',
            [self::VERSAO, $clienteUsuarioId]
        );

        return true;
    }

    /** @return array<string,mixed>|null */
    private static function getUsuarioTermosRow(int $usuarioId): ?array
    {
        try {
            $db = new Database();
            $row = $db->execute(
                'SELECT termos_uso, termos_versao, termos_aceito_em FROM usuarios WHERE id = ? LIMIT 1',
                [$usuarioId]
            )->fetch(\PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    private static function getClienteUsuarioTermosRow(int $clienteUsuarioId): ?array
    {
        $db = new Database();
        $row = $db->execute(
            'SELECT termos_uso, termos_versao, termos_aceito_em FROM cliente_usuarios WHERE id = ? LIMIT 1',
            [$clienteUsuarioId]
        )->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
