<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class FuncaoModulo
{
    public static function getSlugsByFuncaoId(int $funcaoId): array
    {
        $db = new Database('funcao_modulos');
        $stmt = $db->execute(
            'SELECT m.slug FROM funcao_modulos fm
             INNER JOIN modulos m ON m.id = fm.modulo_id
             WHERE fm.funcao_id = ?',
            [$funcaoId]
        );
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'slug');
    }

    public static function getModuloIdsByFuncaoId(int $funcaoId): array
    {
        $db = new Database('funcao_modulos');
        $stmt = $db->execute('SELECT modulo_id FROM funcao_modulos WHERE funcao_id = ?', [$funcaoId]);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'modulo_id'));
    }
}
