<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class ResiduoGrupo
{
    public int $id = 0;
    public int $classe_id = 0;
    public string $codigo = '';
    public string $nome = '';
    public string $descricao = '';
    public string $classe_nome = '';
    public int $ativo = 1;

    public static function count(string $where = 'g.ativo = 1', array $params = []): int
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT COUNT(*) AS qtd FROM residuo_grupos g WHERE '.$where,
            $params
        );
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    /** @return self[] */
    public static function list(string $where, array $params, string $limit): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT g.*, c.nome AS classe_nome FROM residuo_grupos g
             INNER JOIN residuo_classes c ON c.id = g.classe_id
             WHERE '.$where.' ORDER BY c.ordem, g.codigo LIMIT '.$limit,
            $params
        );
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }
        return $items;
    }

    /** @return self[] */
    public static function getByClasseId(int $classeId): array
    {
        return self::list('g.ativo = 1 AND g.classe_id = ?', [$classeId], '9999');
    }

    public static function getById(int $id): ?self
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT g.*, c.nome AS classe_nome FROM residuo_grupos g
             INNER JOIN residuo_classes c ON c.id = g.classe_id WHERE g.id = ?',
            [$id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromArray($row) : null;
    }

    public static function insert(array $data): int
    {
        return (int)(new Database('residuo_grupos'))->insert($data);
    }

    public static function update(int $id, array $data): void
    {
        $db = new Database();
        $fields = array_keys($data);
        $db->execute(
            'UPDATE residuo_grupos SET '.implode('=?, ', $fields).'=? WHERE id = ?',
            [...array_values($data), $id]
        );
    }

    public static function delete(int $id): void
    {
        (new Database())->execute('UPDATE residuo_grupos SET ativo = 0 WHERE id = ?', [$id]);
    }

    private static function fromArray(array $row): self
    {
        $g = new self();
        $g->id = (int)$row['id'];
        $g->classe_id = (int)$row['classe_id'];
        $g->codigo = (string)$row['codigo'];
        $g->nome = (string)($row['nome'] ?? '');
        $g->descricao = (string)($row['descricao'] ?? '');
        $g->classe_nome = (string)($row['classe_nome'] ?? '');
        $g->ativo = (int)($row['ativo'] ?? 1);
        return $g;
    }
}
