<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class RotaAtribuicao
{
    public int $id = 0;
    public int $rota_id = 0;
    public int $cliente_id = 0;
    public ?int $coletor_id = null;
    public string $cliente_nome = '';
    public string $cliente_cidade = '';
    public string $coletor_nome = '';

    public static function countByRota(int $rotaId, string $whereExtra = '', array $params = []): int
    {
        $db = new Database();
        $where = 'ra.rota_id = ?'.$whereExtra;
        array_unshift($params, $rotaId);
        $stmt = $db->execute(
            'SELECT COUNT(*) AS qtd FROM rota_atribuicoes ra
             INNER JOIN clientes c ON c.id = ra.cliente_id
             LEFT JOIN usuarios u ON u.id = ra.coletor_id
             WHERE '.$where,
            $params
        );

        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    /** @return self[] */
    public static function listByRota(int $rotaId, string $whereExtra, array $params, string $limit): array
    {
        $db = new Database();
        $where = 'ra.rota_id = ?'.$whereExtra;
        array_unshift($params, $rotaId);
        $stmt = $db->execute(
            'SELECT ra.*, c.nome_fantasia AS cliente_nome, c.cidade AS cliente_cidade,
                    u.nome AS coletor_nome
             FROM rota_atribuicoes ra
             INNER JOIN clientes c ON c.id = ra.cliente_id
             LEFT JOIN usuarios u ON u.id = ra.coletor_id
             WHERE '.$where.'
             ORDER BY c.nome_fantasia ASC
             LIMIT '.$limit,
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
            'SELECT ra.*, c.nome_fantasia AS cliente_nome, c.cidade AS cliente_cidade,
                    u.nome AS coletor_nome
             FROM rota_atribuicoes ra
             INNER JOIN clientes c ON c.id = ra.cliente_id
             LEFT JOIN usuarios u ON u.id = ra.coletor_id
             WHERE ra.id = ?',
            [$id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromArray($row) : null;
    }

    /** @return array{total:int,sem_coletor:int} */
    public static function statsByRota(int $rotaId): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN coletor_id IS NULL THEN 1 ELSE 0 END) AS sem_coletor
             FROM rota_atribuicoes WHERE rota_id = ?',
            [$rotaId]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => (int)($row['total'] ?? 0),
            'sem_coletor' => (int)($row['sem_coletor'] ?? 0),
        ];
    }

    public static function exists(int $rotaId, int $clienteId): bool
    {
        $db = new Database();
        $row = $db->execute(
            'SELECT 1 FROM rota_atribuicoes WHERE rota_id = ? AND cliente_id = ? LIMIT 1',
            [$rotaId, $clienteId]
        )->fetch();

        return (bool)$row;
    }

    public static function insert(int $rotaId, int $clienteId, ?int $coletorId = null): int
    {
        return (int)(new Database('rota_atribuicoes'))->insert([
            'rota_id' => $rotaId,
            'cliente_id' => $clienteId,
            'coletor_id' => $coletorId,
        ]);
    }

    public static function updateColetor(int $id, ?int $coletorId): void
    {
        (new Database())->execute(
            'UPDATE rota_atribuicoes SET coletor_id = ? WHERE id = ?',
            [$coletorId, $id]
        );
    }

    public static function delete(int $id): void
    {
        (new Database())->execute('DELETE FROM rota_atribuicoes WHERE id = ?', [$id]);
    }

    public static function setColetorEmLote(int $rotaId, int $coletorId, bool $onlySemColetor = true): int
    {
        $db = new Database();
        $sql = 'UPDATE rota_atribuicoes SET coletor_id = ? WHERE rota_id = ?';
        $params = [$coletorId, $rotaId];
        if ($onlySemColetor) {
            $sql .= ' AND coletor_id IS NULL';
        }
        $stmt = $db->execute($sql, $params);

        return $stmt->rowCount();
    }

    /** @return list<array{id:int,nome:string}> */
    public static function clientesDisponiveis(int $rotaId, string $busca = ''): array
    {
        $db = new Database();
        $where = "c.status = 'ativo' AND NOT EXISTS (
            SELECT 1 FROM rota_atribuicoes ra WHERE ra.cliente_id = c.id AND ra.rota_id = ?
        )";
        $params = [$rotaId];
        if ($busca !== '') {
            $where .= ' AND (c.nome_fantasia LIKE ? OR c.cidade LIKE ?)';
            $params[] = '%'.$busca.'%';
            $params[] = '%'.$busca.'%';
        }
        $stmt = $db->execute(
            'SELECT c.id, c.nome_fantasia AS nome FROM clientes c
             WHERE '.$where.'
             ORDER BY c.nome_fantasia ASC LIMIT 50',
            $params
        );
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = ['id' => (int)$row['id'], 'nome' => (string)$row['nome']];
        }

        return $items;
    }

    private static function fromArray(array $row): self
    {
        $a = new self();
        $a->id = (int)$row['id'];
        $a->rota_id = (int)$row['rota_id'];
        $a->cliente_id = (int)$row['cliente_id'];
        $a->coletor_id = isset($row['coletor_id']) && $row['coletor_id'] !== null
            ? (int)$row['coletor_id'] : null;
        $a->cliente_nome = (string)($row['cliente_nome'] ?? '');
        $a->cliente_cidade = (string)($row['cliente_cidade'] ?? '');
        $a->coletor_nome = (string)($row['coletor_nome'] ?? '');

        return $a;
    }
}
