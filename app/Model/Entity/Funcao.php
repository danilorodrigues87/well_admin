<?php

namespace App\Model\Entity;

use App\Common\Helpers\ModuleGateHelper;
use App\Model\Db\Database;
use PDO;

class Funcao
{
    public int $id = 0;
    public string $nome = '';
    public string $slug = '';
    public string $descricao = '';
    public int $is_admin = 0;

    public static function getAll(): array
    {
        $db = new Database();
        $stmt = $db->execute('SELECT * FROM funcoes ORDER BY nome ASC');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }
        return $items;
    }

    public static function getById(int $id): ?self
    {
        $db = new Database();
        $stmt = $db->execute('SELECT * FROM funcoes WHERE id = ? LIMIT 1', [$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromArray($row) : null;
    }

    public static function update(int $id, array $data): void
    {
        $db = new Database();
        $fields = array_keys($data);
        $sql = 'UPDATE funcoes SET '.implode('=?, ', $fields).'=? WHERE id = ?';
        $db->execute($sql, [...array_values($data), $id]);
        ModuleGateHelper::limparCache($id);
    }

    public static function syncModulos(int $funcaoId, array $moduloIds): void
    {
        $db = new Database();
        $db->execute('DELETE FROM funcao_modulos WHERE funcao_id = ?', [$funcaoId]);
        foreach ($moduloIds as $moduloId) {
            $moduloId = (int)$moduloId;
            if ($moduloId > 0) {
                $db->execute(
                    'INSERT INTO funcao_modulos (funcao_id, modulo_id) VALUES (?, ?)',
                    [$funcaoId, $moduloId]
                );
            }
        }
        ModuleGateHelper::limparCache($funcaoId);
    }

    private static function fromArray(array $row): self
    {
        $f = new self();
        $f->id = (int)$row['id'];
        $f->nome = (string)$row['nome'];
        $f->slug = (string)$row['slug'];
        $f->descricao = (string)($row['descricao'] ?? '');
        $f->is_admin = (int)($row['is_admin'] ?? 0);
        return $f;
    }
}
