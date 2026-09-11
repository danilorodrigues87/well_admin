<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class Rota
{
    public int $id = 0;
    public string $nome = '';
    public string $descricao = '';
    public int $ativo = 1;

    public static function getAllActive(): array
    {
        $db = new Database();
        $stmt = $db->execute('SELECT * FROM rotas WHERE ativo = 1 ORDER BY nome');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }
        return $items;
    }

    public static function count(string $where = '1=1', array $params = []): int
    {
        $db = new Database();
        $stmt = $db->execute('SELECT COUNT(*) AS qtd FROM rotas WHERE '.$where, $params);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    public static function list(string $where, array $params, string $limit): array
    {
        $db = new Database();
        $stmt = $db->execute('SELECT * FROM rotas WHERE '.$where.' ORDER BY nome LIMIT '.$limit, $params);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }
        return $items;
    }

    public static function getById(int $id): ?self
    {
        $db = new Database();
        $stmt = $db->execute('SELECT * FROM rotas WHERE id = ?', [$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromArray($row) : null;
    }

    public static function insert(array $data): int
    {
        return (int)(new Database('rotas'))->insert($data);
    }

    public static function update(int $id, array $data): void
    {
        $db = new Database();
        $fields = array_keys($data);
        $db->execute(
            'UPDATE rotas SET '.implode('=?, ', $fields).'=? WHERE id = ?',
            [...array_values($data), $id]
        );
    }

    public static function delete(int $id): void
    {
        (new Database())->execute('UPDATE rotas SET ativo = 0 WHERE id = ?', [$id]);
    }

    private static function fromArray(array $row): self
    {
        $r = new self();
        $r->id = (int)$row['id'];
        $r->nome = (string)$row['nome'];
        $r->descricao = (string)($row['descricao'] ?? '');
        $r->ativo = (int)$row['ativo'];
        return $r;
    }
}
