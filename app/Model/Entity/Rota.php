<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use App\Model\Entity\Concerns\TenantScoped;
use PDO;

class Rota
{
    use TenantScoped;

    public int $id = 0;
    public int $operadora_id = 1;
    public string $nome = '';
    public string $descricao = '';
    public int $ativo = 1;

    /** @return self[] */
    public static function getAllActive(): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT * FROM rotas WHERE ativo = 1 AND operadora_id = ? ORDER BY nome',
            self::tenantIdParams()
        );
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }

        return $items;
    }

    public static function count(string $where = '1=1', array $params = []): int
    {
        [$where, $params] = self::tenantWhere($where, $params);
        $db = new Database();
        $stmt = $db->execute('SELECT COUNT(*) AS qtd FROM rotas WHERE '.$where, $params);

        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    /** @return self[] */
    public static function list(string $where, array $params, string $limit): array
    {
        [$where, $params] = self::tenantWhere($where, $params);
        $db = new Database();
        $stmt = $db->execute(
            'SELECT * FROM rotas WHERE '.$where.' ORDER BY nome LIMIT '.$limit,
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
            'SELECT * FROM rotas WHERE id = ? AND operadora_id = ?',
            [$id, ...self::tenantIdParams()]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromArray($row) : null;
    }

    public static function insert(array $data): int
    {
        return (int)(new Database('rotas'))->insert(self::ensureTenantInsert($data));
    }

    public static function update(int $id, array $data): void
    {
        $db = new Database();
        $fields = array_keys($data);
        $db->execute(
            'UPDATE rotas SET '.implode('=?, ', $fields).'=? WHERE id = ? AND operadora_id = ?',
            [...array_values($data), $id, ...self::tenantIdParams()]
        );
    }

    public static function delete(int $id): void
    {
        (new Database())->execute(
            'UPDATE rotas SET ativo = 0 WHERE id = ? AND operadora_id = ?',
            [$id, ...self::tenantIdParams()]
        );
    }

    /** @param array<string, mixed> $row */
    private static function fromArray(array $row): self
    {
        $r = new self();
        $r->id = (int)$row['id'];
        $r->operadora_id = (int)($row['operadora_id'] ?? 1);
        $r->nome = (string)$row['nome'];
        $r->descricao = (string)($row['descricao'] ?? '');
        $r->ativo = (int)$row['ativo'];

        return $r;
    }
}
