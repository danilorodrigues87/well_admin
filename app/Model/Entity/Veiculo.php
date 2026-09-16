<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use App\Model\Entity\Concerns\TenantScoped;
use PDO;

class Veiculo
{
    use TenantScoped;

    public int $id = 0;
    public int $operadora_id = 1;
    public string $modelo = '';
    public string $marca = '';
    public string $ano = '';
    public string $cor = '';
    public string $placa = '';
    public int $ativo = 1;

    public static function count(string $where = '1=1', array $params = []): int
    {
        [$where, $params] = self::tenantWhere($where, $params);
        $db = new Database();
        $stmt = $db->execute('SELECT COUNT(*) AS qtd FROM veiculos WHERE '.$where, $params);

        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    /** @return self[] */
    public static function list(string $where, array $params, string $limit): array
    {
        [$where, $params] = self::tenantWhere($where, $params);
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
        $stmt = $db->execute(
            'SELECT * FROM veiculos WHERE id = ? AND operadora_id = ?',
            [$id, ...self::tenantIdParams()]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromArray($row) : null;
    }

    public static function insert(array $data): int
    {
        return (int)(new Database('veiculos'))->insert(self::ensureTenantInsert($data));
    }

    public static function update(int $id, array $data): void
    {
        $db = new Database();
        $fields = array_keys($data);
        $db->execute(
            'UPDATE veiculos SET '.implode('=?, ', $fields).'=? WHERE id = ? AND operadora_id = ?',
            [...array_values($data), $id, ...self::tenantIdParams()]
        );
    }

    public static function delete(int $id): void
    {
        (new Database())->execute(
            'DELETE FROM veiculos WHERE id = ? AND operadora_id = ?',
            [$id, ...self::tenantIdParams()]
        );
    }

    /** @param array<string, mixed> $row */
    private static function fromArray(array $row): self
    {
        $v = new self();
        $v->id = (int)$row['id'];
        $v->operadora_id = (int)($row['operadora_id'] ?? 1);
        $v->modelo = (string)$row['modelo'];
        $v->marca = (string)$row['marca'];
        $v->ano = (string)($row['ano'] ?? '');
        $v->cor = (string)($row['cor'] ?? '');
        $v->placa = (string)$row['placa'];
        $v->ativo = (int)$row['ativo'];

        return $v;
    }
}
