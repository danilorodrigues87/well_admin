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
    public string $situacao = 'disponivel';
    public int $km_atual = 0;
    public string $combustivel = 'diesel';
    public ?int $em_uso_usuario_id = null;
    public ?string $em_uso_desde = null;
    public string $em_uso_nome = '';

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

    public static function countPainel(string $where, array $params): int
    {
        [$where, $params] = self::tenantWhere($where, $params, 'v.operadora_id');
        $db = new Database();
        $stmt = $db->execute('SELECT COUNT(*) AS qtd FROM veiculos v WHERE '.$where, $params);

        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    /** @return self[] */
    public static function listPainel(string $where, array $params, string $limit): array
    {
        [$where, $params] = self::tenantWhere($where, $params, 'v.operadora_id');
        $db = new Database();
        $stmt = $db->execute(
            'SELECT v.*, u.nome AS em_uso_nome
             FROM veiculos v
             LEFT JOIN usuarios u ON u.id = v.em_uso_usuario_id
             WHERE '.$where.' ORDER BY v.marca, v.modelo LIMIT '.$limit,
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
        $v->situacao = (string)($row['situacao'] ?? 'disponivel');
        $v->km_atual = (int)($row['km_atual'] ?? 0);
        $v->combustivel = (string)($row['combustivel'] ?? 'diesel');
        $v->em_uso_usuario_id = isset($row['em_uso_usuario_id']) && $row['em_uso_usuario_id'] !== null
            ? (int)$row['em_uso_usuario_id']
            : null;
        $v->em_uso_desde = isset($row['em_uso_desde']) && $row['em_uso_desde'] !== null
            ? (string)$row['em_uso_desde']
            : null;
        $v->em_uso_nome = (string)($row['em_uso_nome'] ?? '');

        return $v;
    }
}
