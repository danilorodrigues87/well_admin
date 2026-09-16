<?php

namespace App\Model\Entity;

use App\Common\OperadoraScope;
use App\Model\Db\Database;
use App\Model\Entity\Concerns\TenantScoped;
use PDO;

class ClienteUsuario
{
    use TenantScoped;

    public int $id = 0;
    public int $operadora_id = 1;
    public int $cliente_id = 0;
    public string $nome = '';
    public string $email = '';
    public string $senha_hash = '';
    public int $ativo = 1;
    public ?string $ultimo_login = null;
    public string $cliente_nome = '';

    public static function getByEmail(string $email, ?int $operadoraId = null): ?self
    {
        $email = trim(strtolower($email));
        if ($email === '') {
            return null;
        }

        $db = new Database();
        if ($operadoraId !== null && $operadoraId > 0) {
            $stmt = $db->execute(
                'SELECT cu.*, c.nome_fantasia AS cliente_nome
                 FROM cliente_usuarios cu
                 INNER JOIN clientes c ON c.id = cu.cliente_id AND c.operadora_id = cu.operadora_id
                 WHERE cu.email = ? AND cu.operadora_id = ? LIMIT 1',
                [$email, $operadoraId]
            );
        } else {
            $stmt = $db->execute(
                'SELECT cu.*, c.nome_fantasia AS cliente_nome
                 FROM cliente_usuarios cu
                 INNER JOIN clientes c ON c.id = cu.cliente_id AND c.operadora_id = cu.operadora_id
                 WHERE cu.email = ? LIMIT 1',
                [$email]
            );
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::fromArray($row) : null;
    }

    public static function getById(int $id): ?self
    {
        $db = new Database();
        $row = $db->execute(
            'SELECT cu.*, c.nome_fantasia AS cliente_nome
             FROM cliente_usuarios cu
             INNER JOIN clientes c ON c.id = cu.cliente_id AND c.operadora_id = cu.operadora_id
             WHERE cu.id = ? AND cu.operadora_id = ? LIMIT 1',
            [$id, ...self::tenantIdParams()]
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::fromArray($row) : null;
    }

    public static function getByClienteId(int $clienteId): ?self
    {
        $db = new Database();
        $row = $db->execute(
            'SELECT cu.*, c.nome_fantasia AS cliente_nome
             FROM cliente_usuarios cu
             INNER JOIN clientes c ON c.id = cu.cliente_id AND c.operadora_id = cu.operadora_id
             WHERE cu.cliente_id = ? AND cu.operadora_id = ?
             LIMIT 1',
            [$clienteId, ...self::tenantIdParams()]
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::fromArray($row) : null;
    }

    /** @return self[] */
    public static function listByCliente(int $clienteId): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT cu.*, c.nome_fantasia AS cliente_nome
             FROM cliente_usuarios cu
             INNER JOIN clientes c ON c.id = cu.cliente_id AND c.operadora_id = cu.operadora_id
             WHERE cu.cliente_id = ? AND cu.operadora_id = ?
             ORDER BY cu.nome ASC',
            [$clienteId, ...self::tenantIdParams()]
        );

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }

        return $items;
    }

    /** @param array<string,mixed> $data */
    public static function insert(array $data): int
    {
        $db = new Database();
        $db->execute(
            'INSERT INTO cliente_usuarios (operadora_id, cliente_id, nome, email, senha_hash, ativo)
             VALUES (?,?,?,?,?,?)',
            [
                OperadoraScope::getOperadoraId(),
                $data['cliente_id'],
                $data['nome'],
                strtolower(trim((string)$data['email'])),
                $data['senha_hash'],
                (int)($data['ativo'] ?? 1),
            ]
        );

        return (int)$db->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        $sets = [];
        $params = [];
        foreach (['nome', 'email', 'ativo'] as $key) {
            if (array_key_exists($key, $data)) {
                $sets[] = $key.' = ?';
                $params[] = $key === 'email' ? strtolower(trim((string)$data[$key])) : $data[$key];
            }
        }
        if (array_key_exists('senha_hash', $data)) {
            $sets[] = 'senha_hash = ?';
            $params[] = $data['senha_hash'];
        }
        if ($sets === []) {
            return;
        }

        $params[] = $id;
        $params[] = OperadoraScope::getOperadoraId();
        $db = new Database();
        $db->execute(
            'UPDATE cliente_usuarios SET '.implode(', ', $sets).' WHERE id = ? AND operadora_id = ?',
            $params
        );
    }

    /**
     * @param list<int> $clienteIds
     * @return array<int, string> cliente_id => ativo|inativo
     */
    public static function mapPortalStatus(array $clienteIds): array
    {
        $clienteIds = array_values(array_unique(array_filter(array_map('intval', $clienteIds))));
        if ($clienteIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($clienteIds), '?'));
        $params = array_merge([OperadoraScope::getOperadoraId()], $clienteIds);

        try {
            $db = new Database();
            $stmt = $db->execute(
                'SELECT cliente_id, ativo FROM cliente_usuarios
                 WHERE operadora_id = ? AND cliente_id IN ('.$placeholders.')',
                $params
            );

            $map = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $map[(int)$row['cliente_id']] = (int)$row['ativo'] === 1 ? 'ativo' : 'inativo';
            }

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    public static function touchUltimoLogin(int $id): void
    {
        $db = new Database();
        $db->execute(
            'UPDATE cliente_usuarios SET ultimo_login = NOW() WHERE id = ? AND operadora_id = ?',
            [$id, OperadoraScope::getOperadoraId()]
        );
    }

    /** @param array<string,mixed> $row */
    private static function fromArray(array $row): self
    {
        $e = new self();
        $e->id = (int)$row['id'];
        $e->operadora_id = (int)($row['operadora_id'] ?? 1);
        $e->cliente_id = (int)$row['cliente_id'];
        $e->nome = (string)$row['nome'];
        $e->email = (string)$row['email'];
        $e->senha_hash = (string)$row['senha_hash'];
        $e->ativo = (int)($row['ativo'] ?? 1);
        $e->ultimo_login = isset($row['ultimo_login']) ? (string)$row['ultimo_login'] : null;
        $e->cliente_nome = (string)($row['cliente_nome'] ?? '');

        return $e;
    }
}
