<?php

namespace App\Model\Entity;

use App\Common\OperadoraScope;
use App\Model\Db\Database;
use App\Model\Entity\Concerns\TenantScoped;
use PDO;

class InterCobranca
{
    use TenantScoped;

    public int $id = 0;
    public ?int $cliente_id = null;
    public ?string $competencia = null;
    public string $codigo_solicitacao = '';
    public ?string $seu_numero = null;
    public float $valor_nominal = 0.0;
    public float $valor_calculado = 0.0;
    public string $data_vencimento = '';
    public string $status = 'EMITIDA';
    public ?string $linha_digitavel = null;
    public ?string $pix_copia_cola = null;
    public ?string $pdf_path = null;
    public ?string $cliente_nome = null;
    public ?string $email_enviado_em = null;
    public ?string $email_erro = null;
    public ?string $detalhes_json = null;
    public ?string $observacao_ajuste = null;

    public function valorCobrado(): float
    {
        return $this->valor_nominal > 0 ? $this->valor_nominal : $this->valor_calculado;
    }

    /** @return array<string,mixed>|null */
    public function getDetalhesParsed(): ?array
    {
        if ($this->detalhes_json === null || trim($this->detalhes_json) === '') {
            return null;
        }
        $decoded = json_decode($this->detalhes_json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): self
    {
        $db = new Database();
        $opId = OperadoraScope::getOperadoraId();
        $db->execute(
            'INSERT INTO inter_cobrancas
                (operadora_id, cliente_id, competencia, codigo_solicitacao, seu_numero, valor_nominal, valor_calculado,
                 data_vencimento, status, linha_digitavel, pix_copia_cola, pdf_path,
                 payload_request, payload_response, detalhes_json, multa_mora_json, observacao_ajuste)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $opId,
                $data['cliente_id'] ?? null,
                $data['competencia'] ?? null,
                $data['codigo_solicitacao'],
                $data['seu_numero'] ?? null,
                $data['valor_nominal'] ?? 0,
                $data['valor_calculado'] ?? 0,
                $data['data_vencimento'],
                $data['status'] ?? 'EMITIDA',
                $data['linha_digitavel'] ?? null,
                $data['pix_copia_cola'] ?? null,
                $data['pdf_path'] ?? null,
                $data['payload_request'] ?? null,
                $data['payload_response'] ?? null,
                $data['detalhes_json'] ?? null,
                $data['multa_mora_json'] ?? null,
                $data['observacao_ajuste'] ?? null,
            ]
        );

        $entity = self::getById((int)$db->lastInsertId());

        return $entity ?? new self();
    }

    public static function getByCodigo(string $codigoSolicitacao): ?self
    {
        $db = new Database();
        $row = $db->execute(
            'SELECT * FROM inter_cobrancas WHERE codigo_solicitacao = ? AND operadora_id = ? LIMIT 1',
            [$codigoSolicitacao, ...self::tenantIdParams()]
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::fromArray($row) : null;
    }

    public static function getByClienteCompetencia(int $clienteId, string $competencia): ?self
    {
        $db = new Database();
        $row = $db->execute(
            'SELECT * FROM inter_cobrancas WHERE cliente_id = ? AND competencia = ? AND operadora_id = ? LIMIT 1',
            [$clienteId, $competencia, ...self::tenantIdParams()]
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::fromArray($row) : null;
    }

    public static function getById(int $id): ?self
    {
        $db = new Database();
        $row = $db->execute(
            'SELECT ic.*, c.nome_fantasia AS cliente_nome
             FROM inter_cobrancas ic
             LEFT JOIN clientes c ON c.id = ic.cliente_id
             WHERE ic.id = ? AND ic.operadora_id = ? LIMIT 1',
            [$id, ...self::tenantIdParams()]
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::fromArray($row) : null;
    }

    public static function countHistorico(string $where, array $params): int
    {
        [$where, $params] = self::tenantWhere($where, $params, 'ic.operadora_id');
        $db = new Database();
        $row = $db->execute(
            'SELECT COUNT(*) AS qtd FROM inter_cobrancas ic WHERE '.$where,
            $params
        )->fetch(PDO::FETCH_ASSOC);

        return (int)($row['qtd'] ?? 0);
    }

    /**
     * @return list<self>
     */
    public static function listHistorico(string $where, array $params, string $limit): array
    {
        [$where, $params] = self::tenantWhere($where, $params, 'ic.operadora_id');
        $db = new Database();
        $stmt = $db->execute(
            'SELECT ic.*, c.nome_fantasia AS cliente_nome
             FROM inter_cobrancas ic
             LEFT JOIN clientes c ON c.id = ic.cliente_id AND c.operadora_id = ic.operadora_id
             WHERE '.$where.'
             ORDER BY ic.created_at DESC
             LIMIT '.$limit,
            $params
        );

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }

        return $items;
    }

    /** @param array<string,mixed> $fields */
    public function update(array $fields): void
    {
        if ($this->id <= 0) {
            return;
        }

        $allowed = [
            'status', 'linha_digitavel', 'pix_copia_cola', 'pdf_path', 'payload_response',
            'email_enviado_em', 'email_erro',
        ];
        $sets = [];
        $params = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $fields)) {
                $sets[] = $key.' = ?';
                $params[] = $fields[$key];
            }
        }
        if ($sets === []) {
            return;
        }

        $params[] = $this->id;
        $db = new Database();
        $params[] = OperadoraScope::getOperadoraId();
        $db->execute(
            'UPDATE inter_cobrancas SET '.implode(', ', $sets).' WHERE id = ? AND operadora_id = ?',
            $params
        );

        foreach ($allowed as $key) {
            if (array_key_exists($key, $fields) && property_exists($this, $key)) {
                $this->{$key} = $fields[$key];
            }
        }
    }

    public function pdfAbsolutePath(): ?string
    {
        if ($this->pdf_path === null || $this->pdf_path === '') {
            return null;
        }

        $root = rtrim(str_replace('\\', '/', dirname(__DIR__, 3)), '/');

        return $root.'/'.ltrim($this->pdf_path, '/');
    }

    /** @param array<string,mixed> $row */
    private static function fromArray(array $row): self
    {
        $e = new self();
        $e->id = (int)$row['id'];
        $e->cliente_id = isset($row['cliente_id']) ? (int)$row['cliente_id'] : null;
        $e->competencia = isset($row['competencia']) ? (string)$row['competencia'] : null;
        $e->codigo_solicitacao = (string)$row['codigo_solicitacao'];
        $e->seu_numero = isset($row['seu_numero']) ? (string)$row['seu_numero'] : null;
        $e->valor_nominal = (float)$row['valor_nominal'];
        $e->valor_calculado = (float)($row['valor_calculado'] ?? 0);
        $e->data_vencimento = (string)$row['data_vencimento'];
        $e->status = (string)$row['status'];
        $e->linha_digitavel = isset($row['linha_digitavel']) ? (string)$row['linha_digitavel'] : null;
        $e->pix_copia_cola = isset($row['pix_copia_cola']) ? (string)$row['pix_copia_cola'] : null;
        $e->pdf_path = isset($row['pdf_path']) ? (string)$row['pdf_path'] : null;
        $e->cliente_nome = isset($row['cliente_nome']) ? (string)$row['cliente_nome'] : null;
        $e->email_enviado_em = isset($row['email_enviado_em']) ? (string)$row['email_enviado_em'] : null;
        $e->email_erro = isset($row['email_erro']) ? (string)$row['email_erro'] : null;
        $e->detalhes_json = isset($row['detalhes_json']) ? (string)$row['detalhes_json'] : null;
        $e->observacao_ajuste = isset($row['observacao_ajuste']) ? (string)$row['observacao_ajuste'] : null;

        return $e;
    }
}
