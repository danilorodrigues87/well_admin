<?php

namespace App\Service;

use App\Common\OperadoraScope;
use App\Model\Db\Database;
use App\Model\Entity\Cliente as EntityCliente;
use PDO;

class RotaScopeService
{
    public static function coletorTemRota(int $coletorId): bool
    {
        if ($coletorId <= 0) {
            return false;
        }
        $db = new Database();

        return (bool)$db->execute(
            'SELECT 1 FROM rota_atribuicoes WHERE coletor_id = ? AND operadora_id = ? LIMIT 1',
            [$coletorId, OperadoraScope::getOperadoraId()]
        )->fetch();
    }

    public static function clienteNaRotaDoColetor(int $clienteId, int $coletorId): bool
    {
        if ($clienteId <= 0 || $coletorId <= 0) {
            return false;
        }
        $db = new Database();

        return (bool)$db->execute(
            'SELECT 1 FROM rota_atribuicoes WHERE cliente_id = ? AND coletor_id = ? AND operadora_id = ? LIMIT 1',
            [$clienteId, $coletorId, OperadoraScope::getOperadoraId()]
        )->fetch();
    }

    public static function assertClientePermitido(int $clienteId, int $coletorId, bool $isAdmin): void
    {
        if ($isAdmin) {
            return;
        }
        if (!self::coletorTemRota($coletorId)) {
            throw new \InvalidArgumentException('Você não possui clientes atribuídos em nenhuma rota.');
        }
        if (!self::clienteNaRotaDoColetor($clienteId, $coletorId)) {
            throw new \InvalidArgumentException('Cliente não pertence à sua rota.');
        }
    }

    /** @return array{join:string,where:string,params:array} */
    public static function paradasDoDiaQuery(int $coletorId, bool $isAdmin): array
    {
        $hoje = date('Y-m-d');
        $join = '';
        $opId = OperadoraScope::getOperadoraId();
        $where = "c.status = 'ativo' AND c.operadora_id = ?";
        $params = [$opId];

        if (!$isAdmin) {
            if (!self::coletorTemRota($coletorId)) {
                $where .= ' AND 1=0';

                return ['join' => $join, 'where' => $where, 'params' => $params];
            }
            $join = ' INNER JOIN rota_atribuicoes ra ON ra.cliente_id = c.id AND ra.coletor_id = ? AND ra.operadora_id = c.operadora_id';
            $params[] = $coletorId;
        }

        $where .= " AND (c.prioridade = 'urgente' OR c.proxima_coleta IS NULL OR c.proxima_coleta <= ?)";
        $params[] = $hoje;

        return ['join' => $join, 'where' => $where, 'params' => $params];
    }

    /** @return list<EntityCliente> */
    public static function paradasDoDia(int $coletorId, bool $isAdmin): array
    {
        $q = self::paradasDoDiaQuery($coletorId, $isAdmin);
        $db = new Database();
        $sql = 'SELECT DISTINCT c.*, p.nome AS plano_nome FROM clientes c
                LEFT JOIN planos p ON p.id = c.plano_id'.$q['join'].'
                WHERE '.$q['where'].'
                ORDER BY c.prioridade DESC, c.proxima_coleta ASC, c.nome_fantasia ASC';
        $stmt = $db->execute($sql, $q['params']);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = EntityCliente::fromRow($row);
        }

        return $items;
    }

    /** Filtro SQL extra para listagens de coletas (não-admin). */
    public static function coletasWhereForColetor(int $coletorId): array
    {
        if (!self::coletorTemRota($coletorId)) {
            return [' AND c.coletor_id = ?', [$coletorId]];
        }

        return [
            ' AND c.coletor_id = ? AND c.cliente_id IN (
                SELECT ra.cliente_id FROM rota_atribuicoes ra
                WHERE ra.coletor_id = ? AND ra.operadora_id = c.operadora_id
            )',
            [$coletorId, $coletorId],
        ];
    }

    /** Contagem de clientes urgentes/atrasados scoped à rota do coletor. */
    public static function countUrgentesAtrasados(int $coletorId, bool $isAdmin): array
    {
        $hoje = date('Y-m-d');
        $db = new Database();
        $opId = OperadoraScope::getOperadoraId();

        if ($isAdmin) {
            $urgentes = (int)$db->execute(
                "SELECT COUNT(*) AS qtd FROM clientes
                 WHERE status = 'ativo' AND prioridade = 'urgente' AND operadora_id = ?",
                [$opId]
            )->fetch(PDO::FETCH_ASSOC)['qtd'];
            $atrasados = (int)$db->execute(
                "SELECT COUNT(*) AS qtd FROM clientes
                 WHERE status = 'ativo' AND proxima_coleta IS NOT NULL AND proxima_coleta <= ?
                 AND operadora_id = ?",
                [$hoje, $opId]
            )->fetch(PDO::FETCH_ASSOC)['qtd'];

            return ['urgentes' => $urgentes, 'atrasados' => $atrasados];
        }

        if (!self::coletorTemRota($coletorId)) {
            return ['urgentes' => 0, 'atrasados' => 0];
        }

        $base = "FROM clientes c
                 INNER JOIN rota_atribuicoes ra ON ra.cliente_id = c.id AND ra.coletor_id = ?
                    AND ra.operadora_id = c.operadora_id
                 WHERE c.status = 'ativo' AND c.operadora_id = ?";

        $urgentes = (int)$db->execute(
            'SELECT COUNT(DISTINCT c.id) AS qtd '.$base." AND c.prioridade = 'urgente'",
            [$coletorId, $opId]
        )->fetch(PDO::FETCH_ASSOC)['qtd'];

        $atrasados = (int)$db->execute(
            'SELECT COUNT(DISTINCT c.id) AS qtd '.$base.' AND c.proxima_coleta IS NOT NULL AND c.proxima_coleta <= ?',
            [$coletorId, $opId, $hoje]
        )->fetch(PDO::FETCH_ASSOC)['qtd'];

        return ['urgentes' => $urgentes, 'atrasados' => $atrasados];
    }
}
