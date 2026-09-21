<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use App\Model\Entity\Concerns\TenantScoped;
use PDO;

class Plano
{
    use TenantScoped;

    public int $id = 0;
    public int $operadora_id = 1;
    public string $nome = '';
    public string $descricao = '';
    public float $valor_mensal = 0;
    public float $coletas_mensais = 0;
    public ?int $coletas_periodo_meses = null;
    public int $coletas_por_periodo = 1;
    public string $tipo = '';
    public int $ativo = 1;
    public ?string $contrato_clausula_1 = null;
    public ?string $contrato_clausula_2 = null;
    public ?string $contrato_clausula_3 = null;
    public ?string $contrato_clausula_extra = null;
    public ?string $contrato_pagamento_parcelado = null;
    public ?string $contrato_pagamento_vista = null;
    public ?string $contrato_obs_pontualidade = null;

    /** @return self[] */
    public static function getAllActive(): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT * FROM planos WHERE ativo = 1 AND operadora_id = ? ORDER BY nome',
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
        $stmt = $db->execute('SELECT COUNT(*) AS qtd FROM planos WHERE '.$where, $params);

        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    /** @return self[] */
    public static function list(string $where, array $params, string $limit): array
    {
        [$where, $params] = self::tenantWhere($where, $params);
        $db = new Database();
        $stmt = $db->execute(
            'SELECT * FROM planos WHERE '.$where.' ORDER BY nome LIMIT '.$limit,
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
            'SELECT * FROM planos WHERE id = ? AND operadora_id = ?',
            [$id, ...self::tenantIdParams()]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromArray($row) : null;
    }

    public static function insert(array $data): int
    {
        return (int)(new Database('planos'))->insert(self::ensureTenantInsert($data));
    }

    public static function update(int $id, array $data): void
    {
        $db = new Database();
        $fields = array_keys($data);
        $db->execute(
            'UPDATE planos SET '.implode('=?, ', $fields).'=? WHERE id = ? AND operadora_id = ?',
            [...array_values($data), $id, ...self::tenantIdParams()]
        );
    }

    public static function delete(int $id): void
    {
        (new Database())->execute(
            'UPDATE planos SET ativo = 0 WHERE id = ? AND operadora_id = ?',
            [$id, ...self::tenantIdParams()]
        );
    }

    /** @param array<string, mixed> $row */
    private static function fromArray(array $row): self
    {
        $p = new self();
        $p->id = (int)$row['id'];
        $p->operadora_id = (int)($row['operadora_id'] ?? 1);
        $p->nome = (string)$row['nome'];
        $p->descricao = (string)($row['descricao'] ?? '');
        $p->valor_mensal = (float)$row['valor_mensal'];
        $p->coletas_mensais = (float)$row['coletas_mensais'];
        $p->coletas_periodo_meses = isset($row['coletas_periodo_meses']) && $row['coletas_periodo_meses'] !== null
            ? (int)$row['coletas_periodo_meses'] : null;
        $p->coletas_por_periodo = max(1, (int)($row['coletas_por_periodo'] ?? 1));
        $p->tipo = (string)($row['tipo'] ?? '');
        $p->ativo = (int)$row['ativo'];
        $p->contrato_clausula_1 = isset($row['contrato_clausula_1']) ? (string)$row['contrato_clausula_1'] : null;
        $p->contrato_clausula_2 = isset($row['contrato_clausula_2']) ? (string)$row['contrato_clausula_2'] : null;
        $p->contrato_clausula_3 = isset($row['contrato_clausula_3']) ? (string)$row['contrato_clausula_3'] : null;
        $p->contrato_clausula_extra = isset($row['contrato_clausula_extra']) ? (string)$row['contrato_clausula_extra'] : null;
        $p->contrato_pagamento_parcelado = isset($row['contrato_pagamento_parcelado']) ? (string)$row['contrato_pagamento_parcelado'] : null;
        $p->contrato_pagamento_vista = isset($row['contrato_pagamento_vista']) ? (string)$row['contrato_pagamento_vista'] : null;
        $p->contrato_obs_pontualidade = isset($row['contrato_obs_pontualidade']) ? (string)$row['contrato_obs_pontualidade'] : null;

        return $p;
    }
}
