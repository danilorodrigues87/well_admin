<?php

namespace App\Model\Entity;

use App\Common\OperadoraScope;
use App\Model\Db\Database;
use App\Model\Entity\Concerns\TenantScoped;
use PDO;

class ClienteContrato
{
    use TenantScoped;

    public int $id = 0;
    public int $operadora_id = 1;
    public int $cliente_id = 0;
    public int $plano_id = 0;
    public string $numero = '';
    public float $valor_mensal = 0;
    public int $qtd_meses = 12;
    public string $data_inicio = '';
    public string $data_fim = '';
    public int $dia_vencimento = 10;
    public string $primeira_competencia = '';
    public float $multa_atraso_pct = 2;
    public float $juros_mora_pct_mes = 1;
    public float $multa_cancelamento_pct = 10;
    public int $carencia_dias = 5;
    public string $status = 'rascunho';
    public ?string $html_snapshot = null;
    public ?string $assinado_em = null;
    public ?int $assinado_por_cliente_usuario_id = null;
    public int $criado_por_usuario_id = 0;
    public string $cliente_nome = '';
    public string $plano_nome = '';

    public static function tabelaExiste(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $ok = (bool)(new Database())->execute("SHOW TABLES LIKE 'clientes_contratos'")->fetch();
        } catch (\Throwable) {
            $ok = false;
        }

        return $ok;
    }

    public static function temPendenteAssinatura(int $clienteId): bool
    {
        if (!self::tabelaExiste() || $clienteId <= 0) {
            return false;
        }
        $db = new Database();
        $row = $db->execute(
            'SELECT id FROM clientes_contratos WHERE cliente_id = ? AND operadora_id = ? AND status = ? LIMIT 1',
            [$clienteId, OperadoraScope::getOperadoraId(), 'aguardando_assinatura']
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row);
    }

    public static function getPendenteAssinatura(int $clienteId): ?self
    {
        if ($clienteId <= 0) {
            return null;
        }
        $db = new Database();
        $row = $db->execute(
            'SELECT cc.*, c.nome_fantasia AS cliente_nome, p.nome AS plano_nome
             FROM clientes_contratos cc
             INNER JOIN clientes c ON c.id = cc.cliente_id
             LEFT JOIN planos p ON p.id = cc.plano_id
             WHERE cc.cliente_id = ? AND cc.operadora_id = ? AND cc.status = ?
             ORDER BY cc.id DESC LIMIT 1',
            [$clienteId, OperadoraScope::getOperadoraId(), 'aguardando_assinatura']
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::fromArray($row) : null;
    }

    public static function getById(int $id): ?self
    {
        $db = new Database();
        $row = $db->execute(
            'SELECT cc.*, c.nome_fantasia AS cliente_nome, p.nome AS plano_nome
             FROM clientes_contratos cc
             INNER JOIN clientes c ON c.id = cc.cliente_id
             LEFT JOIN planos p ON p.id = cc.plano_id
             WHERE cc.id = ? AND cc.operadora_id = ? LIMIT 1',
            [$id, ...self::tenantIdParams()]
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::fromArray($row) : null;
    }

    /** @return self[] */
    public static function listByCliente(int $clienteId): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT cc.*, c.nome_fantasia AS cliente_nome, p.nome AS plano_nome
             FROM clientes_contratos cc
             INNER JOIN clientes c ON c.id = cc.cliente_id
             LEFT JOIN planos p ON p.id = cc.plano_id
             WHERE cc.cliente_id = ? AND cc.operadora_id = ?
             ORDER BY cc.id DESC',
            [$clienteId, ...self::tenantIdParams()]
        );
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }

        return $items;
    }

    /** @return self[] */
    public static function listAll(?int $clienteId = null, int $limit = 200): array
    {
        $where = 'cc.operadora_id = ?';
        $params = self::tenantIdParams();
        if ($clienteId !== null && $clienteId > 0) {
            $where .= ' AND cc.cliente_id = ?';
            $params[] = $clienteId;
        }

        $db = new Database();
        $stmt = $db->execute(
            'SELECT cc.*, c.nome_fantasia AS cliente_nome, p.nome AS plano_nome
             FROM clientes_contratos cc
             INNER JOIN clientes c ON c.id = cc.cliente_id
             LEFT JOIN planos p ON p.id = cc.plano_id
             WHERE '.$where.'
             ORDER BY cc.id DESC
             LIMIT '.max(1, min(500, $limit)),
            $params
        );
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }

        return $items;
    }

    public static function insert(array $data): int
    {
        $db = new Database('clientes_contratos');

        return (int)$db->insert(self::ensureTenantInsert($data));
    }

    public static function update(int $id, array $data): void
    {
        if ($data === []) {
            return;
        }
        $db = new Database();
        $fields = array_keys($data);
        $sql = 'UPDATE clientes_contratos SET '.implode('=?, ', $fields).'=? WHERE id = ? AND operadora_id = ?';
        $db->execute($sql, [...array_values($data), $id, ...self::tenantIdParams()]);
    }

    /** @param array<string,mixed> $row */
    private static function fromArray(array $row): self
    {
        $c = new self();
        $c->id = (int)$row['id'];
        $c->operadora_id = (int)$row['operadora_id'];
        $c->cliente_id = (int)$row['cliente_id'];
        $c->plano_id = (int)$row['plano_id'];
        $c->numero = (string)$row['numero'];
        $c->valor_mensal = (float)$row['valor_mensal'];
        $c->qtd_meses = (int)$row['qtd_meses'];
        $c->data_inicio = (string)$row['data_inicio'];
        $c->data_fim = (string)$row['data_fim'];
        $c->dia_vencimento = (int)$row['dia_vencimento'];
        $c->primeira_competencia = (string)$row['primeira_competencia'];
        $c->multa_atraso_pct = (float)$row['multa_atraso_pct'];
        $c->juros_mora_pct_mes = (float)$row['juros_mora_pct_mes'];
        $c->multa_cancelamento_pct = (float)$row['multa_cancelamento_pct'];
        $c->carencia_dias = (int)$row['carencia_dias'];
        $c->status = (string)$row['status'];
        $c->html_snapshot = isset($row['html_snapshot']) ? (string)$row['html_snapshot'] : null;
        $c->assinado_em = isset($row['assinado_em']) ? (string)$row['assinado_em'] : null;
        $c->assinado_por_cliente_usuario_id = isset($row['assinado_por_cliente_usuario_id']) ? (int)$row['assinado_por_cliente_usuario_id'] : null;
        $c->criado_por_usuario_id = (int)($row['criado_por_usuario_id'] ?? 0);
        $c->cliente_nome = (string)($row['cliente_nome'] ?? '');
        $c->plano_nome = (string)($row['plano_nome'] ?? '');

        return $c;
    }
}
