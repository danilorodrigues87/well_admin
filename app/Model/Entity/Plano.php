<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class Plano
{
    public int $id = 0;
    public string $nome = '';
    public string $descricao = '';
    public float $valor_mensal = 0;
    public float $coletas_mensais = 0;
    public string $tipo = '';
    public int $ativo = 1;

    public static function getAllActive(): array
    {
        $db = new Database();
        $stmt = $db->execute('SELECT * FROM planos WHERE ativo = 1 ORDER BY nome');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }
        return $items;
    }

    public static function count(string $where = '1=1', array $params = []): int
    {
        $db = new Database();
        $stmt = $db->execute('SELECT COUNT(*) AS qtd FROM planos WHERE '.$where, $params);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    public static function list(string $where, array $params, string $limit): array
    {
        $db = new Database();
        $stmt = $db->execute('SELECT * FROM planos WHERE '.$where.' ORDER BY nome LIMIT '.$limit, $params);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }
        return $items;
    }

    public static function getById(int $id): ?self
    {
        $db = new Database();
        $stmt = $db->execute('SELECT * FROM planos WHERE id = ?', [$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromArray($row) : null;
    }

    public static function insert(array $data): int
    {
        return (int)(new Database('planos'))->insert($data);
    }

    public static function update(int $id, array $data): void
    {
        $db = new Database();
        $fields = array_keys($data);
        $db->execute(
            'UPDATE planos SET '.implode('=?, ', $fields).'=? WHERE id = ?',
            [...array_values($data), $id]
        );
    }

    public static function delete(int $id): void
    {
        (new Database())->execute('UPDATE planos SET ativo = 0 WHERE id = ?', [$id]);
    }

    private static function fromArray(array $row): self
    {
        $p = new self();
        $p->id = (int)$row['id'];
        $p->nome = (string)$row['nome'];
        $p->descricao = (string)($row['descricao'] ?? '');
        $p->valor_mensal = (float)$row['valor_mensal'];
        $p->coletas_mensais = (float)$row['coletas_mensais'];
        $p->tipo = (string)($row['tipo'] ?? '');
        $p->ativo = (int)$row['ativo'];
        return $p;
    }
}
