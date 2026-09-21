<?php

namespace App\Model\Entity;

use App\Common\OperadoraScope;
use App\Model\Db\Database;
use App\Model\Entity\Concerns\TenantScoped;
use PDO;

class ColetaSolicitacao
{
    use TenantScoped;

    public int $id = 0;
    public int $operadora_id = 1;
    public int $cliente_id = 0;
    public int $cliente_usuario_id = 0;
    public string $data_desejada = '';
    public string $status = 'pendente';
    public string $tipo = 'inclusa';
    public ?string $motivo_gerador = null;
    public ?string $resposta_admin = null;
    public ?float $valor_cobranca_extra = null;
    public ?string $data_aprovada = null;
    public ?int $aprovado_por_usuario_id = null;
    public ?string $aprovado_em = null;
    public ?int $coleta_id = null;
    public ?string $created_at = null;
    public string $cliente_nome = '';
    public string $solicitante_nome = '';

    public static function insert(array $data): int
    {
        return (int)(new Database('coleta_solicitacoes'))->insert(self::ensureTenantInsert($data));
    }

    public static function update(int $id, array $data): void
    {
        $db = new Database();
        $fields = array_keys($data);
        $db->execute(
            'UPDATE coleta_solicitacoes SET '.implode('=?, ', $fields).'=? WHERE id = ? AND operadora_id = ?',
            [...array_values($data), $id, ...self::tenantIdParams()]
        );
    }

    public static function getById(int $id): ?self
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT cs.*, c.nome_fantasia AS cliente_nome, cu.nome AS solicitante_nome
             FROM coleta_solicitacoes cs
             INNER JOIN clientes c ON c.id = cs.cliente_id AND c.operadora_id = cs.operadora_id
             INNER JOIN cliente_usuarios cu ON cu.id = cs.cliente_usuario_id
             WHERE cs.id = ? AND cs.operadora_id = ?',
            [$id, ...self::tenantIdParams()]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromRow($row) : null;
    }

    public static function hasPendente(int $clienteId): bool
    {
        $db = new Database();
        $row = $db->execute(
            "SELECT 1 FROM coleta_solicitacoes
             WHERE cliente_id = ? AND operadora_id = ? AND status = 'pendente' LIMIT 1",
            [$clienteId, ...self::tenantIdParams()]
        )->fetch();

        return (bool)$row;
    }

    public static function countPendentesOperadora(): int
    {
        $db = new Database();
        $row = $db->execute(
            "SELECT COUNT(*) AS qtd FROM coleta_solicitacoes WHERE operadora_id = ? AND status = 'pendente'",
            self::tenantIdParams()
        )->fetch(PDO::FETCH_ASSOC);

        return (int)($row['qtd'] ?? 0);
    }

    /** @return self[] */
    public static function listByCliente(int $clienteId, int $limit = 20): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT cs.*, c.nome_fantasia AS cliente_nome, cu.nome AS solicitante_nome
             FROM coleta_solicitacoes cs
             INNER JOIN clientes c ON c.id = cs.cliente_id AND c.operadora_id = cs.operadora_id
             INNER JOIN cliente_usuarios cu ON cu.id = cs.cliente_usuario_id
             WHERE cs.cliente_id = ? AND cs.operadora_id = ?
             ORDER BY cs.created_at DESC
             LIMIT '.(int)$limit,
            [$clienteId, ...self::tenantIdParams()]
        );
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromRow($row);
        }

        return $items;
    }

    /** @return array{items:self[],total:int} */
    public static function listAdmin(string $statusFilter, int $page, int $perPage): array
    {
        $db = new Database();
        $where = 'cs.operadora_id = ?';
        $params = [OperadoraScope::getOperadoraId()];
        if (in_array($statusFilter, ['pendente', 'aprovada', 'recusada', 'cancelada'], true)) {
            $where .= ' AND cs.status = ?';
            $params[] = $statusFilter;
        }

        $total = (int)$db->execute(
            'SELECT COUNT(*) AS qtd FROM coleta_solicitacoes cs WHERE '.$where,
            $params
        )->fetch(PDO::FETCH_ASSOC)['qtd'];

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $db->execute(
            'SELECT cs.*, c.nome_fantasia AS cliente_nome, cu.nome AS solicitante_nome
             FROM coleta_solicitacoes cs
             INNER JOIN clientes c ON c.id = cs.cliente_id AND c.operadora_id = cs.operadora_id
             INNER JOIN cliente_usuarios cu ON cu.id = cs.cliente_usuario_id
             WHERE '.$where.'
             ORDER BY FIELD(cs.status, \'pendente\', \'aprovada\', \'recusada\', \'cancelada\'), cs.created_at DESC
             LIMIT '.$offset.', '.(int)$perPage,
            $params
        );
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromRow($row);
        }

        return ['items' => $items, 'total' => $total];
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): self
    {
        $s = new self();
        $s->id = (int)$row['id'];
        $s->operadora_id = (int)$row['operadora_id'];
        $s->cliente_id = (int)$row['cliente_id'];
        $s->cliente_usuario_id = (int)$row['cliente_usuario_id'];
        $s->data_desejada = (string)$row['data_desejada'];
        $s->status = (string)$row['status'];
        $s->tipo = (string)$row['tipo'];
        $s->motivo_gerador = $row['motivo_gerador'] ?? null;
        $s->resposta_admin = $row['resposta_admin'] ?? null;
        $s->valor_cobranca_extra = isset($row['valor_cobranca_extra']) && $row['valor_cobranca_extra'] !== null
            ? (float)$row['valor_cobranca_extra'] : null;
        $s->data_aprovada = $row['data_aprovada'] ?? null;
        $s->aprovado_por_usuario_id = isset($row['aprovado_por_usuario_id']) && $row['aprovado_por_usuario_id'] !== null
            ? (int)$row['aprovado_por_usuario_id'] : null;
        $s->aprovado_em = $row['aprovado_em'] ?? null;
        $s->coleta_id = isset($row['coleta_id']) && $row['coleta_id'] !== null ? (int)$row['coleta_id'] : null;
        $s->created_at = $row['created_at'] ?? null;
        $s->cliente_nome = (string)($row['cliente_nome'] ?? '');
        $s->solicitante_nome = (string)($row['solicitante_nome'] ?? '');

        return $s;
    }
}
