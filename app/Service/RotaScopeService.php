<?php

namespace App\Service;

use App\Common\OperadoraScope;
use App\Model\Db\Database;
use App\Model\Entity\Cliente as EntityCliente;
use PDO;

/**
 * Escopo operacional por rotas cadastrais (cliente ∈ rota).
 * Coletor não é mais fixado em rota_atribuicoes — ownership fica em coletas.coletor_id.
 */
class RotaScopeService
{
    public static function operadoraTemClientesEmRotas(): bool
    {
        $db = new Database();

        return (bool)$db->execute(
            'SELECT 1 FROM rota_atribuicoes WHERE operadora_id = ? LIMIT 1',
            [OperadoraScope::getOperadoraId()]
        )->fetch();
    }

    public static function clienteEmRota(int $clienteId): bool
    {
        if ($clienteId <= 0) {
            return false;
        }
        $db = new Database();

        return (bool)$db->execute(
            'SELECT 1 FROM rota_atribuicoes WHERE cliente_id = ? AND operadora_id = ? LIMIT 1',
            [$clienteId, OperadoraScope::getOperadoraId()]
        )->fetch();
    }

    /** Compat API/UI: indica se há base de clientes em rotas (não vínculo por coletor). */
    public static function coletorTemRota(int $coletorId): bool
    {
        return self::operadoraTemClientesEmRotas();
    }

    /** @deprecated Use clienteEmRota() — coletor não é mais filtrado em rota_atribuicoes. */
    public static function clienteNaRotaDoColetor(int $clienteId, int $coletorId): bool
    {
        return self::clienteEmRota($clienteId);
    }

    public static function assertClientePermitido(int $clienteId, int $coletorId, bool $isAdmin): void
    {
        if ($isAdmin) {
            return;
        }
        if (!self::operadoraTemClientesEmRotas()) {
            throw new \InvalidArgumentException('Nenhum cliente vinculado às rotas operacionais. Cadastre rotas e clientes.');
        }
        if (!self::clienteEmRota($clienteId)) {
            throw new \InvalidArgumentException('Cliente não está vinculado a nenhuma rota.');
        }
    }

    /** @return array{join:string,where:string,params:array,data:string} */
    public static function paradasDoDiaQuery(
        int $coletorId,
        bool $isAdmin,
        ?string $dataReferencia = null,
        ?int $rotaId = null
    ): array {
        $ref = ($dataReferencia !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataReferencia))
            ? $dataReferencia
            : date('Y-m-d');
        $opId = OperadoraScope::getOperadoraId();
        $join = '';
        $params = [];

        if (!$isAdmin) {
            if (!self::operadoraTemClientesEmRotas()) {
                return [
                    'join' => '',
                    'where' => "c.status = 'ativo' AND c.operadora_id = ? AND 1=0",
                    'params' => [$opId],
                    'data' => $ref,
                ];
            }
            $join = ' INNER JOIN rota_atribuicoes ra ON ra.cliente_id = c.id AND ra.operadora_id = c.operadora_id';
        }

        $where = "c.status = 'ativo' AND c.operadora_id = ?";
        $params[] = $opId;

        if ($rotaId !== null && $rotaId > 0) {
            if ($join === '') {
                $join = ' INNER JOIN rota_atribuicoes ra ON ra.cliente_id = c.id AND ra.operadora_id = c.operadora_id';
            }
            $where .= ' AND ra.rota_id = ?';
            $params[] = $rotaId;
        }

        $where .= " AND (c.prioridade = 'urgente' OR c.proxima_coleta IS NULL OR c.proxima_coleta <= ?)";
        $params[] = $ref;

        return ['join' => $join, 'where' => $where, 'params' => $params, 'data' => $ref];
    }

    /** @return list<EntityCliente> */
    public static function paradasDoDia(
        int $coletorId,
        bool $isAdmin,
        ?string $dataReferencia = null,
        ?int $rotaId = null
    ): array {
        $q = self::paradasDoDiaQuery($coletorId, $isAdmin, $dataReferencia, $rotaId);
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

    /** Filtro SQL extra para listagens de coletas (não-admin): só coletas do próprio coletor. */
    public static function coletasWhereForColetor(int $coletorId): array
    {
        return [' AND c.coletor_id = ?', [$coletorId]];
    }

    /** Contagem de clientes urgentes/atrasados (coletor vê o pool das rotas, como gestor). */
    public static function countUrgentesAtrasados(int $coletorId, bool $isAdmin): array
    {
        $hoje = date('Y-m-d');
        $db = new Database();
        $opId = OperadoraScope::getOperadoraId();

        if ($isAdmin || !self::operadoraTemClientesEmRotas()) {
            if (!$isAdmin && !self::operadoraTemClientesEmRotas()) {
                return ['urgentes' => 0, 'atrasados' => 0];
            }
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

        $base = "FROM clientes c
                 INNER JOIN rota_atribuicoes ra ON ra.cliente_id = c.id AND ra.operadora_id = c.operadora_id
                 WHERE c.status = 'ativo' AND c.operadora_id = ?";

        $urgentes = (int)$db->execute(
            'SELECT COUNT(DISTINCT c.id) AS qtd '.$base." AND c.prioridade = 'urgente'",
            [$opId]
        )->fetch(PDO::FETCH_ASSOC)['qtd'];

        $atrasados = (int)$db->execute(
            'SELECT COUNT(DISTINCT c.id) AS qtd '.$base.' AND c.proxima_coleta IS NOT NULL AND c.proxima_coleta <= ?',
            [$opId, $hoje]
        )->fetch(PDO::FETCH_ASSOC)['qtd'];

        return ['urgentes' => $urgentes, 'atrasados' => $atrasados];
    }
}
