<?php

namespace App\Common;

use App\Model\Db\Database;
use App\Session\User\Login;

/**
 * Escopo da operadora (tenant) ativa na requisição.
 * MVP: sempre operadora_id=1 (Well) até existir Painel Plataforma.
 */
class OperadoraScope
{
    private static ?int $overrideId = null;

    public static function setOverride(?int $operadoraId): void
    {
        self::$overrideId = $operadoraId !== null && $operadoraId > 0 ? $operadoraId : null;
    }

    public static function getOverride(): ?int
    {
        return self::$overrideId;
    }

    public static function getOperadoraId(): int
    {
        if (self::$overrideId !== null) {
            return self::$overrideId;
        }

        $session = Login::getUserLogedData();
        if ($session !== null && !empty($session['usuario']['operadora_id'])) {
            return (int)$session['usuario']['operadora_id'];
        }

        return 1;
    }

    /** @return array<string, mixed>|null */
    public static function getOperadoraSnapshot(): ?array
    {
        $session = Login::getUserLogedData();

        return $session['usuario']['operadora'] ?? null;
    }

    public static function sqlOperadora(string $column = 'operadora_id'): string
    {
        return $column.' = '.(int)self::getOperadoraId();
    }

    /** @param array<string, mixed> $params */
    public static function withOperadora(string $where, array &$params, string $column = 'operadora_id'): string
    {
        $params[] = self::getOperadoraId();

        return $where.' AND '.$column.' = ?';
    }

    public static function pertence(string $tabela, int $id, string $pk = 'id'): bool
    {
        if ($id <= 0) {
            return false;
        }
        $allowed = [
            'clientes', 'veiculos', 'rotas', 'planos', 'coletas',
            'usuarios', 'inter_cobrancas', 'rota_atribuicoes',
        ];
        if (!in_array($tabela, $allowed, true)) {
            return false;
        }

        $db = new Database();
        $stmt = $db->execute(
            'SELECT operadora_id FROM `'.$tabela.'` WHERE `'.$pk.'` = ? LIMIT 1',
            [$id]
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        return (int)$row['operadora_id'] === self::getOperadoraId();
    }
}
