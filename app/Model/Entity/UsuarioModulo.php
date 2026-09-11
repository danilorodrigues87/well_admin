<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class UsuarioModulo
{
    public static function getOverridesByUsuarioId(int $usuarioId): array
    {
        $db = new Database('usuario_modulos');
        $stmt = $db->execute(
            'SELECT m.slug, um.tipo FROM usuario_modulos um
             INNER JOIN modulos m ON m.id = um.modulo_id
             WHERE um.usuario_id = ?',
            [$usuarioId]
        );
        $grant = [];
        $revoke = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['tipo'] === 'revoke') {
                $revoke[] = $row['slug'];
            } else {
                $grant[] = $row['slug'];
            }
        }
        return ['grant' => $grant, 'revoke' => $revoke];
    }
}
