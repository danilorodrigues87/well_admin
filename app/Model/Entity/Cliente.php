<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class Cliente
{
    public int $id = 0;
    public string $nome_fantasia = '';
    public string $razao_social = '';
    public string $cnpj = '';
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
    public float $saldo_residuo = 0.0;
    public string $prioridade = 'normal';

    public static function count(string $where = '1=1', array $params = []): int
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT COUNT(*) AS qtd FROM clientes c WHERE '.$where,
            $params
        );
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    public static function list(string $where, array $params, string $limit): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT c.*, p.nome AS plano_nome FROM clientes c
             LEFT JOIN planos p ON p.id = c.plano_id
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
             LEFT JOIN planos p ON p.id = c.plano_id WHERE c.id = ?',
            [$id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromArray($row) : null;
    }

    public static function insert(array $data): int
    {
        return (int)(new Database('clientes'))->insert($data);
    }

    public static function update(int $id, array $data): void
    {
        $db = new Database();
        $fields = array_keys($data);
        $db->execute(
            'UPDATE clientes SET '.implode('=?, ', $fields).'=? WHERE id = ?',
            [...array_values($data), $id]
        );
    }

    public static function delete(int $id): void
    {
        (new Database())->execute('UPDATE clientes SET status = ? WHERE id = ?', ['inativo', $id]);
    }

    public static function fromRow(array $row): self
    {
        return self::fromArray($row);
    }

    private static function fromArray(array $row): self
    {
        $c = new self();
        $c->id = (int)$row['id'];
        $c->nome_fantasia = (string)$row['nome_fantasia'];
        $c->razao_social = (string)$row['razao_social'];
        $c->cnpj = (string)($row['cnpj'] ?? '');
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
        $c->saldo_residuo = (float)($row['saldo_residuo'] ?? 0);
        $c->prioridade = (string)($row['prioridade'] ?? 'normal');
        return $c;
    }
}
