<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class Veiculo
{
    public int $id = 0;
    public string $modelo = '';
    public string $marca = '';
    public string $ano = '';
    public string $cor = '';
    public string $placa = '';
    public int $ativo = 1;

    public static function count(string $where = '1=1', array $params = []): int
    {
        $db = new Database();
        $stmt = $db->execute('SELECT COUNT(*) AS qtd FROM veiculos WHERE '.$where, $params);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    public static function list(string $where, array $params, string $limit): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT * FROM veiculos WHERE '.$where.' ORDER BY marca, modelo LIMIT '.$limit,
            $params
        );
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }
        return $items;
    }

    public static function getById(int $id): ?self
    {
        $db = new Database();
        $stmt = $db->execute('SELECT * FROM veiculos WHERE id = ?', [$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromArray($row) : null;
    }

    public static function insert(array $data): int
    {
        return (int)(new Database('veiculos'))->insert($data);
    }

    public static function update(int $id, array $data): void
    {
        $db = new Database();
        $fields = array_keys($data);
        $db->execute(
            'UPDATE veiculos SET '.implode('=?, ', $fields).'=? WHERE id = ?',
            [...array_values($data), $id]
        );
    }

    public static function delete(int $id): void
    {
        (new Database())->execute('DELETE FROM veiculos WHERE id = ?', [$id]);
    }

    private static function fromArray(array $row): self
    {
        $v = new self();
        $v->id = (int)$row['id'];
        $v->modelo = (string)$row['modelo'];
        $v->marca = (string)$row['marca'];
        $v->ano = (string)($row['ano'] ?? '');
        $v->cor = (string)($row['cor'] ?? '');
        $v->placa = (string)$row['placa'];
        $v->ativo = (int)$row['ativo'];
        return $v;
    }
}
