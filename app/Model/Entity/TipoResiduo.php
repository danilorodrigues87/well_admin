<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class TipoResiduo
{
    public int $id = 0;
    public string $nome = '';
    public ?int $classe_id = null;
    public ?int $grupo_id = null;
    public string $cod_ibama = '';
    public ?int $tra_codigo = null;
    public ?int $tie_codigo = null;
    public ?int $tia_codigo = null;
    public ?int $cla_codigo = null;
    public ?int $uni_codigo = null;
    public string $classe_nome = '';
    public string $grupo_codigo = '';
    public int $ativo = 1;

    public static function count(string $where = '1=1', array $params = []): int
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT COUNT(*) AS qtd FROM tipos_residuos t WHERE '.$where,
            $params
        );
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    /** @return self[] */
    public static function list(string $where, array $params, string $limit): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT t.*, c.nome AS classe_nome, g.codigo AS grupo_codigo
             FROM tipos_residuos t
             LEFT JOIN residuo_classes c ON c.id = t.classe_id
             LEFT JOIN residuo_grupos g ON g.id = t.grupo_id
             WHERE '.$where.' ORDER BY t.nome LIMIT '.$limit,
            $params
        );
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }
        return $items;
    }

    public static function findByCodIbama(string $codIbama): ?self
    {
        $codIbama = trim($codIbama);
        if ($codIbama === '') {
            return null;
        }
        $db = new Database();
        $stmt = $db->execute(
            'SELECT t.*, c.nome AS classe_nome, g.codigo AS grupo_codigo
             FROM tipos_residuos t
             LEFT JOIN residuo_classes c ON c.id = t.classe_id
             LEFT JOIN residuo_grupos g ON g.id = t.grupo_id
             WHERE t.cod_ibama = ? AND t.ativo = 1 LIMIT 1',
            [$codIbama]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromArray($row) : null;
    }

    public static function findByNome(string $nome): ?self
    {
        $nome = trim($nome);
        if ($nome === '') {
            return null;
        }
        $db = new Database();
        $stmt = $db->execute(
            'SELECT t.*, c.nome AS classe_nome, g.codigo AS grupo_codigo
             FROM tipos_residuos t
             LEFT JOIN residuo_classes c ON c.id = t.classe_id
             LEFT JOIN residuo_grupos g ON g.id = t.grupo_id
             WHERE t.nome = ? AND t.ativo = 1 LIMIT 1',
            [$nome]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromArray($row) : null;
    }

    public static function getById(int $id): ?self
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT t.*, c.nome AS classe_nome, g.codigo AS grupo_codigo
             FROM tipos_residuos t
             LEFT JOIN residuo_classes c ON c.id = t.classe_id
             LEFT JOIN residuo_grupos g ON g.id = t.grupo_id
             WHERE t.id = ?',
            [$id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromArray($row) : null;
    }

    public static function insert(array $data): int
    {
        return (int)(new Database('tipos_residuos'))->insert($data);
    }

    public static function update(int $id, array $data): void
    {
        $db = new Database();
        $fields = array_keys($data);
        $db->execute(
            'UPDATE tipos_residuos SET '.implode('=?, ', $fields).'=? WHERE id = ?',
            [...array_values($data), $id]
        );
    }

    public static function delete(int $id): void
    {
        (new Database())->execute('UPDATE tipos_residuos SET ativo = 0 WHERE id = ?', [$id]);
    }

    private static function fromArray(array $row): self
    {
        $t = new self();
        $t->id = (int)$row['id'];
        $t->nome = (string)$row['nome'];
        $t->classe_id = isset($row['classe_id']) ? (int)$row['classe_id'] : null;
        $t->grupo_id = isset($row['grupo_id']) ? (int)$row['grupo_id'] : null;
        $t->cod_ibama = (string)($row['cod_ibama'] ?? '');
        $t->tra_codigo = isset($row['tra_codigo']) ? (int)$row['tra_codigo'] : null;
        $t->tie_codigo = isset($row['tie_codigo']) ? (int)$row['tie_codigo'] : null;
        $t->tia_codigo = isset($row['tia_codigo']) ? (int)$row['tia_codigo'] : null;
        $t->cla_codigo = isset($row['cla_codigo']) ? (int)$row['cla_codigo'] : null;
        $t->uni_codigo = isset($row['uni_codigo']) ? (int)$row['uni_codigo'] : null;
        $t->classe_nome = (string)($row['classe_nome'] ?? '');
        $t->grupo_codigo = (string)($row['grupo_codigo'] ?? '');
        $t->ativo = (int)($row['ativo'] ?? 1);
        return $t;
    }
}
