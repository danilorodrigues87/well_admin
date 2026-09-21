<?php

namespace App\Service;

use App\Common\OperadoraScope;
use App\Model\Db\Database;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\Usuario as EntityUsuario;

class DashboardService
{
    /** @return array<string, int> */
    public static function kpis(): array
    {
        $db = new Database();
        $opId = OperadoraScope::getOperadoraId();
        $hoje = date('Y-m-d');
        $mesInicio = date('Y-m-01');
        $mesFim = date('Y-m-t');

        $coletasMes = EntityColeta::count(
            "c.status = 'finalizada' AND c.data_coleta BETWEEN ? AND ?",
            [$mesInicio, $mesFim]
        );

        $rascunhos = EntityColeta::count("c.status = 'rascunho'", []);

        $urgentes = (int)$db->execute(
            "SELECT COUNT(*) AS qtd FROM clientes
             WHERE status = 'ativo' AND prioridade = 'urgente' AND operadora_id = ?",
            [$opId]
        )->fetch(\PDO::FETCH_ASSOC)['qtd'];

        $atrasados = (int)$db->execute(
            "SELECT COUNT(*) AS qtd FROM clientes
             WHERE status = 'ativo' AND proxima_coleta IS NOT NULL AND proxima_coleta <= ?
             AND operadora_id = ?",
            [$hoje, $opId]
        )->fetch(\PDO::FETCH_ASSOC)['qtd'];

        $clientesEmRotas = (int)$db->execute(
            'SELECT COUNT(DISTINCT cliente_id) AS qtd FROM rota_atribuicoes WHERE operadora_id = ?',
            [$opId]
        )->fetch(\PDO::FETCH_ASSOC)['qtd'];

        $pendRecebimento = EntityColeta::count(
            "c.status = 'finalizada' AND c.situacao_recebimento = 'pendente'",
            []
        );

        $clientesAtivos = (int)$db->execute(
            "SELECT COUNT(*) AS qtd FROM clientes WHERE status = 'ativo' AND operadora_id = ?",
            [$opId]
        )->fetch(\PDO::FETCH_ASSOC)['qtd'];

        return [
            'coletas_mes' => $coletasMes,
            'rascunhos' => $rascunhos,
            'urgentes' => $urgentes,
            'atrasados' => $atrasados,
            'clientes_em_rotas' => $clientesEmRotas,
            'pend_recebimento' => $pendRecebimento,
            'clientes_ativos' => $clientesAtivos,
        ];
    }

    /** @return array{labels:list<string>,values:list<int>} */
    public static function coletasPorMes(int $meses = 6): array
    {
        $db = new Database();
        $opId = OperadoraScope::getOperadoraId();
        $labels = [];
        $values = [];

        for ($i = $meses - 1; $i >= 0; $i--) {
            $ref = strtotime('-'.$i.' months');
            $inicio = date('Y-m-01', $ref);
            $fim = date('Y-m-t', $ref);
            $labels[] = self::mesLabel($ref);
            $values[] = (int)$db->execute(
                "SELECT COUNT(*) AS qtd FROM coletas
                 WHERE operadora_id = ? AND status = 'finalizada' AND data_coleta BETWEEN ? AND ?",
                [$opId, $inicio, $fim]
            )->fetch(\PDO::FETCH_ASSOC)['qtd'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array{labels:list<string>,values:list<float>} */
    public static function faturamentoPorMes(int $meses = 6): array
    {
        $db = new Database();
        $opId = OperadoraScope::getOperadoraId();
        $labels = [];
        $values = [];

        for ($i = $meses - 1; $i >= 0; $i--) {
            $ref = strtotime('-'.$i.' months');
            $comp = date('Y-m', $ref);
            $labels[] = self::mesLabel($ref);
            $values[] = (float)$db->execute(
                'SELECT COALESCE(SUM(valor_nominal), 0) AS total FROM inter_cobrancas
                 WHERE operadora_id = ? AND competencia = ?',
                [$opId, $comp]
            )->fetch(\PDO::FETCH_ASSOC)['total'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array{labels:list<string>,values:list<int>} */
    public static function coletasPorStatus(): array
    {
        $db = new Database();
        $opId = OperadoraScope::getOperadoraId();
        $stmt = $db->execute(
            "SELECT status, COUNT(*) AS qtd FROM coletas WHERE operadora_id = ?
             AND status != 'cancelada' GROUP BY status ORDER BY qtd DESC",
            [$opId]
        );

        $labels = [];
        $values = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $labels[] = ucfirst((string)$row['status']);
            $values[] = (int)$row['qtd'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private static function mesLabel(int $timestamp): string
    {
        static $meses = [
            1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
            7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez',
        ];
        $m = (int)date('n', $timestamp);

        return ($meses[$m] ?? date('M', $timestamp)).'/'.date('y', $timestamp);
    }

    /** KPIs resumidos para o app coletor (escopo do usuário). */
    public static function kpisColetor(int $userId, bool $isAdmin): array
    {
        $hoje = date('Y-m-d');
        $mesInicio = date('Y-m-01');
        $mesFim = date('Y-m-t');

        if ($isAdmin) {
            $coletorFilter = '';
            $paramsMes = [$mesInicio, $mesFim];
            $paramsRasc = [];
        } else {
            $coletorFilter = ' AND c.coletor_id = ?';
            $paramsMes = [$mesInicio, $mesFim, $userId];
            $paramsRasc = [$userId];
        }

        $coletasMes = EntityColeta::count(
            "c.status = 'finalizada' AND c.data_coleta BETWEEN ? AND ?".$coletorFilter,
            $paramsMes
        );

        $rascunhos = EntityColeta::count(
            "c.status = 'rascunho'".$coletorFilter,
            $paramsRasc
        );

        $urgentesAtrasados = RotaScopeService::countUrgentesAtrasados($userId, $isAdmin);

        $paradasHoje = self::paradasHoje($userId, $isAdmin);

        return [
            'coletas_mes' => $coletasMes,
            'rascunhos' => $rascunhos,
            'urgentes' => $urgentesAtrasados['urgentes'],
            'atrasados' => $urgentesAtrasados['atrasados'],
            'paradas_hoje' => $paradasHoje,
            'hoje' => $hoje,
        ];
    }

    /** @return array{labels:list<string>,values:list<int>} */
    public static function coletasPorMesColetor(int $userId, bool $isAdmin, int $meses = 6): array
    {
        $db = new Database();
        $opId = OperadoraScope::getOperadoraId();
        $labels = [];
        $values = [];

        for ($i = $meses - 1; $i >= 0; $i--) {
            $ref = strtotime('-'.$i.' months');
            $inicio = date('Y-m-01', $ref);
            $fim = date('Y-m-t', $ref);
            $labels[] = self::mesLabel($ref);
            if ($isAdmin) {
                $values[] = (int)$db->execute(
                    "SELECT COUNT(*) AS qtd FROM coletas
                     WHERE operadora_id = ? AND status = 'finalizada' AND data_coleta BETWEEN ? AND ?",
                    [$opId, $inicio, $fim]
                )->fetch(\PDO::FETCH_ASSOC)['qtd'];
            } else {
                $values[] = (int)$db->execute(
                    "SELECT COUNT(*) AS qtd FROM coletas
                     WHERE operadora_id = ? AND status = 'finalizada' AND data_coleta BETWEEN ? AND ?
                     AND coletor_id = ?",
                    [$opId, $inicio, $fim, $userId]
                )->fetch(\PDO::FETCH_ASSOC)['qtd'];
            }
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public static function paradasHoje(int $userId, bool $isAdmin): int
    {
        $coletorId = $isAdmin ? 0 : $userId;
        if (!$isAdmin && $coletorId <= 0) {
            return 0;
        }

        if ($isAdmin) {
            $coletores = EntityUsuario::getColetoresAtivos();
            $total = 0;
            foreach ($coletores as $c) {
                $total += count(RotaDoDiaService::listarParadas($c->id, false, date('Y-m-d'), null));
            }

            return $total;
        }

        return count(RotaDoDiaService::listarParadas($coletorId, false, date('Y-m-d'), null));
    }
}
