<?php

namespace App\Service;

use App\Common\OperadoraScope;
use App\Model\Db\Database;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Rota as EntityRota;

class AgendamentoService
{
    /**
     * @return array{join:string,where:string,params:array<int|string>}
     */
    public static function buildListagemQuery(
        int $coletorId = 0,
        bool $isAdmin = true,
        int $rotaId = 0,
        string $prioridade = '',
        string $busca = ''
    ): array {
        $opId = OperadoraScope::getOperadoraId();
        $join = '';
        $params = [];

        if (!$isAdmin && $coletorId > 0) {
            $join = ' INNER JOIN rota_atribuicoes ra ON ra.cliente_id = c.id AND ra.operadora_id = c.operadora_id';
        }

        $where = "c.status = 'ativo' AND c.operadora_id = ?";
        $params[] = $opId;

        if ($rotaId > 0) {
            if ($join === '') {
                $join = ' INNER JOIN rota_atribuicoes ra ON ra.cliente_id = c.id AND ra.operadora_id = c.operadora_id';
            }
            $where .= ' AND ra.rota_id = ?';
            $params[] = $rotaId;
        }

        if ($prioridade === 'normal' || $prioridade === 'urgente') {
            $where .= ' AND c.prioridade = ?';
            $params[] = $prioridade;
        }

        $busca = trim($busca);
        if ($busca !== '') {
            $where .= ' AND (c.nome_fantasia LIKE ? OR c.razao_social LIKE ? OR c.cidade LIKE ?)';
            $like = '%'.$busca.'%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return ['join' => $join, 'where' => $where, 'params' => $params];
    }

    /**
     * @return array{items: list<array<string,mixed>>, meta: array<string,int>}
     */
    public static function listar(
        string $busca = '',
        int $page = 1,
        int $perPage = 20,
        int $coletorId = 0,
        bool $isAdmin = true,
        int $rotaId = 0,
        string $prioridade = '',
        bool $coletorSomentePendentes = false
    ): array {
        $db = new Database();
        $page = max(1, $page);
        $perPage = min(50, max(1, $perPage));

        if (!$isAdmin && $coletorSomentePendentes && $coletorId > 0) {
            $q = RotaScopeService::paradasDoDiaQuery($coletorId, false);
            if ($busca !== '') {
                $q['where'] .= ' AND (c.nome_fantasia LIKE ? OR c.razao_social LIKE ? OR c.cidade LIKE ?)';
                $like = '%'.trim($busca).'%';
                $q['params'][] = $like;
                $q['params'][] = $like;
                $q['params'][] = $like;
            }
        } else {
            $q = self::buildListagemQuery($coletorId, $isAdmin, $rotaId, $prioridade, $busca);
        }

        $countSql = 'SELECT COUNT(DISTINCT c.id) AS qtd FROM clientes c'.$q['join'].' WHERE '.$q['where'];
        $total = (int)$db->execute($countSql, $q['params'])->fetch(\PDO::FETCH_ASSOC)['qtd'];

        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT DISTINCT c.*, p.nome AS plano_nome FROM clientes c
                LEFT JOIN planos p ON p.id = c.plano_id AND p.operadora_id = c.operadora_id'.$q['join'].'
                WHERE '.$q['where'].' ORDER BY c.prioridade DESC, c.proxima_coleta ASC
                LIMIT '.$perPage.' OFFSET '.$offset;
        $stmt = $db->execute($sql, $q['params']);

        $items = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $c = EntityCliente::fromRow($row);
            $planoId = (int)($c->plano_id ?? 0);
            $items[] = [
                'id' => (int)$c->id,
                'nome_fantasia' => (string)$c->nome_fantasia,
                'cidade' => (string)($c->cidade ?? ''),
                'proxima_coleta' => $c->proxima_coleta,
                'prioridade' => (string)$c->prioridade,
                'plano_nome' => (string)($row['plano_nome'] ?? ''),
                'saldo_plano' => $planoId > 0 ? PlanoService::totalSaldoIncluso($planoId) : 0.0,
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

    /** @return array{atualizados:int,rota_nome:string} */
    public static function agendarRotaEmLote(int $rotaId, string $proximaColeta, ?string $prioridade = null): array
    {
        if ($rotaId <= 0) {
            throw new \InvalidArgumentException('Selecione uma rota.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $proximaColeta)) {
            throw new \InvalidArgumentException('Data de coleta inválida.');
        }
        if ($prioridade !== null && !in_array($prioridade, ['normal', 'urgente'], true)) {
            throw new \InvalidArgumentException('Prioridade inválida.');
        }

        $rota = EntityRota::getById($rotaId);
        if (!$rota || !$rota->ativo) {
            throw new \InvalidArgumentException('Rota não encontrada ou inativa.');
        }

        $db = new Database();
        $opId = OperadoraScope::getOperadoraId();

        $set = 'c.proxima_coleta = ?';
        $params = [$proximaColeta];
        if ($prioridade !== null) {
            $set .= ', c.prioridade = ?';
            $params[] = $prioridade;
        }
        $params[] = $rotaId;
        $params[] = $opId;

        $sql = 'UPDATE clientes c
                INNER JOIN rota_atribuicoes ra ON ra.cliente_id = c.id AND ra.operadora_id = c.operadora_id
                SET '.$set.'
                WHERE ra.rota_id = ? AND c.operadora_id = ? AND c.status = ?';
        $params[] = 'ativo';

        $stmt = $db->execute($sql, $params);

        return [
            'atualizados' => $stmt->rowCount(),
            'rota_nome' => $rota->nome,
        ];
    }
}
