<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class ResiduoClasse
{
    public int $id = 0;
    public string $nome = '';
    public string $slug = '';
    public string $descricao = '';
    public int $ordem = 0;
    public int $ativo = 1;

    public static function count(string $where = 'ativo = 1', array $params = []): int
    {
        $db = new Database();
        $stmt = $db->execute('SELECT COUNT(*) AS qtd FROM residuo_classes WHERE '.$where, $params);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    /** @return self[] */
    public static function list(string $where, array $params, string $limit): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT * FROM residuo_classes WHERE '.$where.' ORDER BY ordem, nome LIMIT '.$limit,
            $params
        );
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }
        return $items;
    }

    /** @return self[] */
    public static function getAllActive(): array
    {
        return self::list('ativo = 1', [], '9999');
    }

    public static function getById(int $id): ?self
    {
        $db = new Database();
        $stmt = $db->execute('SELECT * FROM residuo_classes WHERE id = ?', [$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromArray($row) : null;
    }

    public static function insert(array $data): int
    {
        return (int)(new Database('residuo_classes'))->insert($data);
    }

    public static function update(int $id, array $data): void
    {
        $db = new Database();
        $fields = array_keys($data);
        $db->execute(
            'UPDATE residuo_classes SET '.implode('=?, ', $fields).'=? WHERE id = ?',
            [...array_values($data), $id]
        );
    }

    public static function delete(int $id): void
    {
        (new Database())->execute('UPDATE residuo_classes SET ativo = 0 WHERE id = ?', [$id]);
    }

    public static function slugFromNome(string $nome): string
    {
        $slug = strtolower(trim($nome));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        return trim($slug, '-') ?: 'classe';
    }

    private static function fromArray(array $row): self
    {
        $c = new self();
        $c->id = (int)$row['id'];
        $c->nome = (string)$row['nome'];
        $c->slug = (string)($row['slug'] ?? '');
        $c->descricao = (string)($row['descricao'] ?? '');
        $c->ordem = (int)($row['ordem'] ?? 0);
        $c->ativo = (int)($row['ativo'] ?? 1);
        return $c;
    }
}
