<?php

namespace App\Model\Entity;

use App\Common\OperadoraScope;
use App\Model\Db\Database;
use PDO;

class OperadoraConfig
{
    public static function get(string $chave, ?string $default = null): ?string
    {
        try {
            $db = new Database();
            $row = $db->execute(
                'SELECT valor FROM operadora_config WHERE operadora_id = ? AND chave = ? LIMIT 1',
                [OperadoraScope::getOperadoraId(), $chave]
            )->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                return $default;
            }

            return (string)$row['valor'];
        } catch (\Throwable) {
            return $default;
        }
    }

    /** @param list<string> $chaves */
    public static function getMany(array $chaves): array
    {
        if ($chaves === []) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($chaves), '?'));
            $params = array_merge([OperadoraScope::getOperadoraId()], $chaves);
            $db = new Database();
            $stmt = $db->execute(
                'SELECT chave, valor FROM operadora_config WHERE operadora_id = ? AND chave IN ('.$placeholders.')',
                $params
            );

            $map = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $map[(string)$row['chave']] = (string)$row['valor'];
            }

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    public static function set(string $chave, string $valor): void
    {
        $db = new Database();
        $db->execute(
            'INSERT INTO operadora_config (operadora_id, chave, valor) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
            [OperadoraScope::getOperadoraId(), $chave, $valor]
        );
    }

    /** @param array<string,string> $pares */
    public static function setMany(array $pares): void
    {
        foreach ($pares as $chave => $valor) {
            self::set($chave, $valor);
        }
    }
}
