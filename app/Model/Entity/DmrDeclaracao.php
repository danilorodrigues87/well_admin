<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use App\Model\Entity\Concerns\TenantScoped;
use PDO;

class DmrDeclaracao
{
    use TenantScoped;

    public int $id = 0;
    public int $operadora_id = 1;
    public int $cliente_id = 0;
    public string $competencia = '';
    public string $status = 'rascunho';
    public ?string $observacao = null;
    public int $tot_coletas = 0;
    public float $tot_kg = 0.0;
    public int $tot_com_mtr = 0;
    public int $tot_com_cdf = 0;
    public ?string $fechada_em = null;
    public string $cliente_nome = '';

    public static function count(string $where = '1=1', array $params = []): int
    {
        [$where, $params] = self::tenantWhere($where, $params, 'd.operadora_id');
        $db = new Database();
        $stmt = $db->execute(
            'SELECT COUNT(*) AS qtd FROM dmr_declaracoes d WHERE '.$where,
            $params
        );

        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    /** @return self[] */
    public static function list(string $where, array $params, string $limit): array
    {
        [$where, $params] = self::tenantWhere($where, $params, 'd.operadora_id');
        $db = new Database();
        $stmt = $db->execute(
            'SELECT d.*, cl.nome_fantasia AS cliente_nome
             FROM dmr_declaracoes d
             INNER JOIN clientes cl ON cl.id = d.cliente_id AND cl.operadora_id = d.operadora_id
             WHERE '.$where.' ORDER BY d.competencia DESC, d.id DESC LIMIT '.$limit,
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
            'SELECT d.*, cl.nome_fantasia AS cliente_nome
             FROM dmr_declaracoes d
             INNER JOIN clientes cl ON cl.id = d.cliente_id AND cl.operadora_id = d.operadora_id
             WHERE d.id = ? AND d.operadora_id = ?',
            [$id, ...self::tenantIdParams()]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromArray($row) : null;
    }

    public static function getByClienteCompetencia(int $clienteId, string $competencia): ?self
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT d.*, cl.nome_fantasia AS cliente_nome
             FROM dmr_declaracoes d
             INNER JOIN clientes cl ON cl.id = d.cliente_id AND cl.operadora_id = d.operadora_id
             WHERE d.cliente_id = ? AND d.competencia = ? AND d.operadora_id = ?',
            [$clienteId, $competencia, ...self::tenantIdParams()]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromArray($row) : null;
    }

    public static function insert(array $data): int
    {
        return (int)(new Database('dmr_declaracoes'))->insert(self::ensureTenantInsert($data));
    }

    public static function update(int $id, array $data): void
    {
        if ($data === []) {
            return;
        }
        $db = new Database();
        $fields = array_keys($data);
        $db->execute(
            'UPDATE dmr_declaracoes SET '.implode('=?, ', $fields).'=? WHERE id = ? AND operadora_id = ?',
            [...array_values($data), $id, ...self::tenantIdParams()]
        );
    }

    /** @param array<string,mixed> $row */
    private static function fromArray(array $row): self
    {
        $d = new self();
        $d->id = (int)$row['id'];
        $d->operadora_id = (int)($row['operadora_id'] ?? 1);
        $d->cliente_id = (int)$row['cliente_id'];
        $d->competencia = (string)$row['competencia'];
        $d->status = (string)$row['status'];
        $d->observacao = isset($row['observacao']) ? (string)$row['observacao'] : null;
        $d->tot_coletas = (int)($row['tot_coletas'] ?? 0);
        $d->tot_kg = (float)($row['tot_kg'] ?? 0);
        $d->tot_com_mtr = (int)($row['tot_com_mtr'] ?? 0);
        $d->tot_com_cdf = (int)($row['tot_com_cdf'] ?? 0);
        $d->fechada_em = $row['fechada_em'] ?? null;
        $d->cliente_nome = (string)($row['cliente_nome'] ?? '');

        return $d;
    }
}
