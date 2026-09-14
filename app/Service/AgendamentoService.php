<?php

namespace App\Service;

use App\Model\Db\Database;
use App\Model\Entity\Cliente as EntityCliente;

class AgendamentoService
{
    /**
     * @return array{items: list<array<string,mixed>>, meta: array<string,int>}
     */
    public static function listar(string $busca = '', int $page = 1, int $perPage = 20): array
    {
        $db = new Database();
        $page = max(1, $page);
        $perPage = min(50, max(1, $perPage));
        $busca = trim($busca);

        $where = "c.status = 'ativo'";
        $params = [];
        $join = '';

        if ($busca !== '') {
            $where .= ' AND (c.nome_fantasia LIKE ? OR c.razao_social LIKE ? OR c.cidade LIKE ?)';
            $like = '%'.$busca.'%';
            $params = [$like, $like, $like];
        }

        $countSql = 'SELECT COUNT(DISTINCT c.id) AS qtd FROM clientes c'.$join.' WHERE '.$where;
        $total = (int)$db->execute($countSql, $params)->fetch(\PDO::FETCH_ASSOC)['qtd'];

        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT DISTINCT c.*, p.nome AS plano_nome FROM clientes c
                LEFT JOIN planos p ON p.id = c.plano_id'.$join.'
                WHERE '.$where.' ORDER BY c.prioridade DESC, c.proxima_coleta ASC
                LIMIT '.$perPage.' OFFSET '.$offset;
        $stmt = $db->execute($sql, $params);

        $items = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $c = EntityCliente::fromRow($row);
            $items[] = [
                'id' => (int)$c->id,
                'nome_fantasia' => (string)$c->nome_fantasia,
                'cidade' => (string)($c->cidade ?? ''),
                'proxima_coleta' => $c->proxima_coleta,
                'prioridade' => (string)$c->prioridade,
                'saldo_residuo' => (float)$c->saldo_residuo,
                'plano_nome' => (string)($row['plano_nome'] ?? ''),
            ];
        }

        return [
            'items' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int)ceil($total / max(1, $perPage))),
            ],
        ];
    }
}
