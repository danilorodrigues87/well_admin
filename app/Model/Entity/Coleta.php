<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use App\Model\Entity\Concerns\TenantScoped;
use PDO;

class Coleta
{
    use TenantScoped;

    public int $id = 0;
    public int $operadora_id = 1;
    public ?int $numero_mtr = null;
    public ?int $numero_relatorio = null;
    public ?int $legacy_manifesto = null;
    public int $cliente_id = 0;
    public int $coletor_id = 0;
    public ?int $transportadora_id = null;
    public ?int $destinador_id = null;
    public ?int $veiculo_id = null;
    public string $status = 'rascunho';
    public ?string $doc_referencia = null;
    public ?string $data_coleta = null;
    public ?string $hora = null;
    public ?string $relatorio = null;
    public ?string $assinatura_cliente_path = null;
    public string $situacao_recebimento = 'pendente';
    public ?string $data_recebimento = null;
    public ?string $tratamento = null;
    public string $cliente_nome = '';
    public int $cliente_exige_mtr = 0;
    public string $coletor_nome = '';
    public ?string $sinir_man_numero = null;
    public ?string $sinir_codigo_barras = null;
    public ?string $sinir_status = null;
    public ?string $sinir_enviado_em = null;
    public ?string $sinir_recebido_em = null;
    public ?string $cdf_path = null;
    public ?string $cdf_obtido_em = null;
    public ?string $sinir_cdf_codigo = null;
    public ?string $cdf_tipo = null;

    public static function count(string $where = '1=1', array $params = []): int
    {
        [$where, $params] = self::tenantWhere($where, $params, 'c.operadora_id');
        $db = new Database();
        $stmt = $db->execute(
            'SELECT COUNT(*) AS qtd FROM coletas c
             INNER JOIN clientes cl ON cl.id = c.cliente_id AND cl.operadora_id = c.operadora_id
             WHERE '.$where,
            $params
        );

        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    /** @return self[] */
    public static function list(string $where, array $params, string $limit): array
    {
        [$where, $params] = self::tenantWhere($where, $params, 'c.operadora_id');
        $db = new Database();
        $stmt = $db->execute(
            'SELECT c.*, cl.nome_fantasia AS cliente_nome, cl.exige_mtr AS cliente_exige_mtr, u.nome AS coletor_nome
             FROM coletas c
             INNER JOIN clientes cl ON cl.id = c.cliente_id AND cl.operadora_id = c.operadora_id
             LEFT JOIN usuarios u ON u.id = c.coletor_id AND u.operadora_id = c.operadora_id
             WHERE '.$where.' ORDER BY c.id DESC LIMIT '.$limit,
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
            'SELECT c.*, cl.nome_fantasia AS cliente_nome, cl.exige_mtr AS cliente_exige_mtr, u.nome AS coletor_nome
             FROM coletas c
             INNER JOIN clientes cl ON cl.id = c.cliente_id AND cl.operadora_id = c.operadora_id
             LEFT JOIN usuarios u ON u.id = c.coletor_id AND u.operadora_id = c.operadora_id
             WHERE c.id = ? AND c.operadora_id = ?',
            [$id, ...self::tenantIdParams()]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromArray($row) : null;
    }

    public static function insert(array $data): int
    {
        return (int)(new Database('coletas'))->insert(self::ensureTenantInsert($data));
    }

    public static function update(int $id, array $data): void
    {
        if ($data === []) {
            return;
        }
        $db = new Database();
        $fields = array_keys($data);
        $db->execute(
            'UPDATE coletas SET '.implode('=?, ', $fields).'=? WHERE id = ? AND operadora_id = ?',
            [...array_values($data), $id, ...self::tenantIdParams()]
        );
    }

    /** @param array<string, mixed> $row */
    private static function fromArray(array $row): self
    {
        $c = new self();
        $c->id = (int)$row['id'];
        $c->operadora_id = (int)($row['operadora_id'] ?? 1);
        $c->numero_mtr = isset($row['numero_mtr']) && $row['numero_mtr'] !== null ? (int)$row['numero_mtr'] : null;
        $c->numero_relatorio = isset($row['numero_relatorio']) && $row['numero_relatorio'] !== null
            ? (int)$row['numero_relatorio'] : null;
        $c->legacy_manifesto = isset($row['legacy_manifesto']) && $row['legacy_manifesto'] !== null
            ? (int)$row['legacy_manifesto'] : null;
        $c->cliente_id = (int)$row['cliente_id'];
        $c->coletor_id = (int)$row['coletor_id'];
        $c->transportadora_id = isset($row['transportadora_id']) && $row['transportadora_id'] !== null
            ? (int)$row['transportadora_id'] : null;
        $c->destinador_id = isset($row['destinador_id']) && $row['destinador_id'] !== null
            ? (int)$row['destinador_id'] : null;
        $c->veiculo_id = isset($row['veiculo_id']) ? (int)$row['veiculo_id'] : null;
        $c->status = (string)$row['status'];
        $c->doc_referencia = $row['doc_referencia'] ?? null;
        $c->data_coleta = $row['data_coleta'] ?? null;
        $c->hora = $row['hora'] ?? null;
        $c->relatorio = $row['relatorio'] ?? null;
        $c->assinatura_cliente_path = isset($row['assinatura_cliente_path']) ? (string)$row['assinatura_cliente_path'] : null;
        $c->situacao_recebimento = (string)($row['situacao_recebimento'] ?? 'pendente');
        $c->data_recebimento = $row['data_recebimento'] ?? null;
        $c->tratamento = $row['tratamento'] ?? null;
        $c->cliente_nome = (string)($row['cliente_nome'] ?? '');
        $c->cliente_exige_mtr = (int)($row['cliente_exige_mtr'] ?? 0);
        $c->coletor_nome = (string)($row['coletor_nome'] ?? '');
        $c->sinir_man_numero = isset($row['sinir_man_numero']) ? (string)$row['sinir_man_numero'] : null;
        $c->sinir_codigo_barras = isset($row['sinir_codigo_barras']) ? (string)$row['sinir_codigo_barras'] : null;
        $c->sinir_status = isset($row['sinir_status']) ? (string)$row['sinir_status'] : null;
        $c->sinir_enviado_em = $row['sinir_enviado_em'] ?? null;
        $c->sinir_recebido_em = $row['sinir_recebido_em'] ?? null;
        $c->cdf_path = isset($row['cdf_path']) ? (string)$row['cdf_path'] : null;
        $c->cdf_obtido_em = $row['cdf_obtido_em'] ?? null;
        $c->sinir_cdf_codigo = isset($row['sinir_cdf_codigo']) ? (string)$row['sinir_cdf_codigo'] : null;
        $c->cdf_tipo = isset($row['cdf_tipo']) ? (string)$row['cdf_tipo'] : null;

        return $c;
    }
}
