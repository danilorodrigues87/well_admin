<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class Usuario
{
    public int $id = 0;
    public string $nome = '';
    public string $email = '';
    public string $senha = '';
    public int $funcao_id = 0;
    public string $ativo = 's';
    public string $funcao_nome = '';
    public int $is_admin = 0;

    public static function getByEmail(string $email): ?self
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT u.*, f.nome AS funcao_nome, f.is_admin FROM usuarios u
             INNER JOIN funcoes f ON f.id = u.funcao_id WHERE u.email = ? LIMIT 1',
            [$email]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromArray($row) : null;
    }

    public static function getById(int $id): ?self
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT u.*, f.nome AS funcao_nome, f.is_admin FROM usuarios u
             INNER JOIN funcoes f ON f.id = u.funcao_id WHERE u.id = ? LIMIT 1',
            [$id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromArray($row) : null;
    }

    public static function count(?string $where = null, array $params = []): int
    {
        $db = new Database();
        $sql = 'SELECT COUNT(*) AS qtd FROM usuarios u INNER JOIN funcoes f ON f.id = u.funcao_id';
        if ($where) {
            $sql .= ' WHERE '.$where;
        }
        $stmt = $db->execute($sql, $params);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    /** @return self[] */
    public static function list(string $where, array $params, string $limit): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT u.*, f.nome AS funcao_nome, f.is_admin FROM usuarios u
             INNER JOIN funcoes f ON f.id = u.funcao_id
             WHERE '.$where.' ORDER BY u.nome ASC LIMIT '.$limit,
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
        $db = new Database('usuarios');
        return (int)$db->insert($data);
    }

    public static function update(int $id, array $data): void
    {
        if (empty($data)) {
            return;
        }
        $db = new Database();
        $fields = array_keys($data);
        $sql = 'UPDATE usuarios SET '.implode('=?, ', $fields).'=? WHERE id = ?';
        $db->execute($sql, [...array_values($data), $id]);
    }

    public static function delete(int $id): void
    {
        $db = new Database('usuarios');
        $db->execute('DELETE FROM usuarios WHERE id = ?', [$id]);
    }

    /** @return self[] */
    public static function getColetoresAtivos(): array
    {
        return self::list("f.slug = 'coletor' AND u.ativo = 's'", [], '500');
    }

    private static function fromArray(array $row): self
    {
        $u = new self();
        $u->id = (int)$row['id'];
        $u->nome = (string)$row['nome'];
        $u->email = (string)$row['email'];
        $u->senha = (string)($row['senha'] ?? '');
        $u->funcao_id = (int)$row['funcao_id'];
        $u->ativo = (string)$row['ativo'];
        $u->funcao_nome = (string)($row['funcao_nome'] ?? '');
        $u->is_admin = (int)($row['is_admin'] ?? 0);
        return $u;
    }
}
