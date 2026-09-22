<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use App\Model\Entity\Concerns\TenantScoped;
use PDO;

class Cliente
{
    use TenantScoped;

    public int $id = 0;
    public int $operadora_id = 1;
    public string $nome_fantasia = '';
    public string $razao_social = '';
    public string $cnpj = '';
    public ?int $sinir_cod_unidade = null;
    public int $exige_mtr = 0;
    public string $email = '';
    public string $telefone = '';
    public ?int $plano_id = null;
    public string $status = 'ativo';
    public string $logradouro = '';
    public string $numero = '';
    public string $bairro = '';
    public string $cep = '';
    public string $cidade = '';
    public string $uf = '';
    public string $responsavel = '';
    public string $telefone_resp = '';
    public string $plano_nome = '';
    public ?string $proxima_coleta = null;
    public string $prioridade = 'normal';
    public ?float $latitude = null;
    public ?float $longitude = null;
    public string $maps_link = '';
    public ?string $geocoded_at = null;
    public string $geocode_status = 'pendente';

    public static function enderecoCompleto(self $c): string
    {
        $partes = array_filter([
            trim($c->logradouro),
            trim($c->numero) !== '' ? 'nº '.trim($c->numero) : '',
            trim($c->bairro),
            trim($c->cep),
            trim($c->cidade),
            trim($c->uf),
            'Brasil',
        ], fn ($v) => $v !== '');

        return implode(', ', $partes);
    }

    public static function count(string $where = '1=1', array $params = []): int
    {
        [$where, $params] = self::tenantWhere($where, $params, 'c.operadora_id');
        $db = new Database();
        $stmt = $db->execute(
            'SELECT COUNT(*) AS qtd FROM clientes c WHERE '.$where,
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
            'SELECT c.*, p.nome AS plano_nome FROM clientes c
             LEFT JOIN planos p ON p.id = c.plano_id AND p.operadora_id = c.operadora_id
             WHERE '.$where.' ORDER BY c.nome_fantasia LIMIT '.$limit,
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
            'SELECT c.*, p.nome AS plano_nome FROM clientes c
             LEFT JOIN planos p ON p.id = c.plano_id AND p.operadora_id = c.operadora_id
             WHERE c.id = ? AND c.operadora_id = ?',
            [$id, ...self::tenantIdParams()]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromArray($row) : null;
    }

    public static function insert(array $data): int
    {
        return (int)(new Database('clientes'))->insert(self::ensureTenantInsert($data));
    }

    public static function update(int $id, array $data): void
    {
        $db = new Database();
        $fields = array_keys($data);
        $db->execute(
            'UPDATE clientes SET '.implode('=?, ', $fields).'=? WHERE id = ? AND operadora_id = ?',
            [...array_values($data), $id, ...self::tenantIdParams()]
        );
    }

    public static function delete(int $id): void
    {
        (new Database())->execute(
            'UPDATE clientes SET status = ? WHERE id = ? AND operadora_id = ?',
            ['inativo', $id, ...self::tenantIdParams()]
        );
    }

    public static function fromRow(array $row): self
    {
        return self::fromArray($row);
    }

    /** @param array<string, mixed> $row */
    private static function fromArray(array $row): self
    {
        $c = new self();
        $c->id = (int)$row['id'];
        $c->operadora_id = (int)($row['operadora_id'] ?? 1);
        $c->nome_fantasia = (string)$row['nome_fantasia'];
        $c->razao_social = (string)$row['razao_social'];
        $c->cnpj = (string)($row['cnpj'] ?? '');
        $c->sinir_cod_unidade = isset($row['sinir_cod_unidade']) && $row['sinir_cod_unidade'] !== null
            ? (int)$row['sinir_cod_unidade']
            : null;
        $c->exige_mtr = (int)($row['exige_mtr'] ?? 0);
        $c->email = (string)($row['email'] ?? '');
        $c->telefone = (string)($row['telefone'] ?? '');
        $c->plano_id = isset($row['plano_id']) ? (int)$row['plano_id'] : null;
        $c->status = (string)$row['status'];
        $c->logradouro = (string)($row['logradouro'] ?? '');
        $c->numero = (string)($row['numero'] ?? '');
        $c->bairro = (string)($row['bairro'] ?? '');
        $c->cep = (string)($row['cep'] ?? '');
        $c->cidade = (string)($row['cidade'] ?? '');
        $c->uf = (string)($row['uf'] ?? '');
        $c->responsavel = (string)($row['responsavel'] ?? '');
        $c->telefone_resp = (string)($row['telefone_resp'] ?? '');
        $c->plano_nome = (string)($row['plano_nome'] ?? '');
        $c->proxima_coleta = $row['proxima_coleta'] ?? null;
        $c->prioridade = (string)($row['prioridade'] ?? 'normal');
        $c->latitude = isset($row['latitude']) && $row['latitude'] !== null && $row['latitude'] !== ''
            ? (float)$row['latitude'] : null;
        $c->longitude = isset($row['longitude']) && $row['longitude'] !== null && $row['longitude'] !== ''
            ? (float)$row['longitude'] : null;
        $c->maps_link = (string)($row['maps_link'] ?? '');
        $c->geocoded_at = $row['geocoded_at'] ?? null;
        $c->geocode_status = (string)($row['geocode_status'] ?? 'pendente');

        return $c;
    }
}
