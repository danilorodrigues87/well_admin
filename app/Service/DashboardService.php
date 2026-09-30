<?php

namespace App\Service;

use App\Common\Helpers\ColetorSelectHelper;
use App\Common\OperadoraScope;
use App\Model\Db\Database;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\ColetaSolicitacao;
class DashboardService
{
    /**
     * KPIs do painel admin respeitando escopo do usuário (coletor vê só suas coletas).
     *
     * @return array<string, int|float>
     */
    public static function kpisPanel(array $usuario): array
    {
        $db = new Database();
        $opId = OperadoraScope::getOperadoraId();
        $hoje = date('Y-m-d');
        $mesInicio = date('Y-m-01');
        $mesFim = date('Y-m-t');
        $compMes = date('Y-m');

        $isAdmin = !empty($usuario['is_admin']);
        $userId = (int)($usuario['id'] ?? 0);
        $isColetor = ColetorSelectHelper::isColetorSession($usuario);
        $coletorFilter = $isColetor && !$isAdmin ? ' AND c.coletor_id = ?' : '';
        $coletorParams = $isColetor && !$isAdmin ? [$userId] : [];

        $coletasHoje = EntityColeta::count(
            "c.status = 'finalizada' AND c.data_coleta = ?".$coletorFilter,
            array_merge([$hoje], $coletorParams)
        );

        $coletasMes = EntityColeta::count(
            "c.status = 'finalizada' AND c.data_coleta BETWEEN ? AND ?".$coletorFilter,
            array_merge([$mesInicio, $mesFim], $coletorParams)
        );

        $rascunhos = EntityColeta::count(
            "c.status = 'rascunho'".$coletorFilter,
            $coletorParams
        );

        $clientesAtivos = (int)$db->execute(
            "SELECT COUNT(*) AS qtd FROM clientes WHERE status = 'ativo' AND operadora_id = ?",
            [$opId]
        )->fetch(\PDO::FETCH_ASSOC)['qtd'];

        $paradasHoje = RotaScopeService::countParadasAgendadasNaData(
            $hoje,
            $isColetor ? $userId : 0,
            !$isColetor || $isAdmin
        );

        $rotasComColetaHoje = count(RotaScopeService::rotasComAgendamentoNaData($hoje));

        $solicitacoesPendentes = ColetaSolicitacao::countPendentesOperadora();

        $mtrSinirPendente = (int)$db->execute(
            "SELECT COUNT(*) AS qtd FROM coletas c
             INNER JOIN clientes cl ON cl.id = c.cliente_id AND cl.operadora_id = c.operadora_id
             WHERE c.operadora_id = ? AND c.status = 'finalizada' AND cl.exige_mtr = 1
               AND (c.sinir_status IS NULL OR c.sinir_status IN ('pendente', 'erro'))"
            .($isColetor && !$isAdmin ? ' AND c.coletor_id = ?' : ''),
            $isColetor && !$isAdmin ? [$opId, $userId] : [$opId]
        )->fetch(\PDO::FETCH_ASSOC)['qtd'];

        $fin = self::financeiroResumoCompetencia($compMes);

        return array_merge([
            'coletas_hoje' => $coletasHoje,
            'coletas_mes' => $coletasMes,
            'rascunhos' => $rascunhos,
            'clientes_ativos' => $clientesAtivos,
            'paradas_hoje' => $paradasHoje,
            'rotas_com_coleta_hoje' => $rotasComColetaHoje,
            'solicitacoes_pendentes' => $solicitacoesPendentes,
            'mtr_sinir_pendente' => $mtrSinirPendente,
        ], $fin);
    }

    /** @deprecated Use kpisPanel() */
    public static function kpis(): array
    {
        return self::kpisPanel(['is_admin' => 1, 'id' => 0]);
    }

    /**
     * @return array{
     *   fin_emitido_mes:float,fin_recebido_mes:float,fin_aberto_mes:float,fin_vencido_mes:float,
     *   fin_qtd_emitida:int,fin_qtd_paga:int,fin_qtd_aberta:int,fin_qtd_vencida:int
     * }
     */
    public static function financeiroResumoCompetencia(string $competenciaYm): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $competenciaYm)) {
            $competenciaYm = date('Y-m');
        }
        $db = new Database();
        $opId = OperadoraScope::getOperadoraId();
        $rows = $db->execute(
            'SELECT status, COUNT(*) AS qtd, COALESCE(SUM(valor_nominal), 0) AS total
             FROM inter_cobrancas
             WHERE operadora_id = ? AND competencia = ?
             GROUP BY status',
            [$opId, $competenciaYm]
        );
        $emitido = 0.0;
        $recebido = 0.0;
        $aberto = 0.0;
        $vencido = 0.0;
        $qEmit = 0;
        $qPago = 0;
        $qAberto = 0;
        $qVenc = 0;
        while ($row = $rows->fetch(\PDO::FETCH_ASSOC)) {
            $st = strtoupper((string)$row['status']);
            $total = (float)$row['total'];
            $qtd = (int)$row['qtd'];
            if (in_array($st, ['CANCELADA', 'CANCELADO'], true)) {
                continue;
            }
            $emitido += $total;
            $qEmit += $qtd;
            if (in_array($st, ['PAGO', 'RECEBIDO'], true)) {
                $recebido += $total;
                $qPago += $qtd;
            } elseif (in_array($st, ['VENCIDA', 'VENCIDO', 'ATRASADO'], true)) {
                $vencido += $total;
                $qVenc += $qtd;
            } else {
                $aberto += $total;
                $qAberto += $qtd;
            }
        }

        return [
            'fin_emitido_mes' => $emitido,
            'fin_recebido_mes' => $recebido,
            'fin_aberto_mes' => $aberto,
            'fin_vencido_mes' => $vencido,
            'fin_qtd_emitida' => $qEmit,
            'fin_qtd_paga' => $qPago,
            'fin_qtd_aberta' => $qAberto,
            'fin_qtd_vencida' => $qVenc,
        ];
    }

    /** @return array{labels:list<string>,emitido:list<float>,recebido:list<float>} */
    public static function cobrancasEmitidoRecebidoPorMes(int $meses = 6): array
    {
        $db = new Database();
        $opId = OperadoraScope::getOperadoraId();
        $labels = [];
        $emitido = [];
        $recebido = [];

        for ($i = $meses - 1; $i >= 0; $i--) {
            $ref = strtotime('-'.$i.' months');
            $comp = date('Y-m', $ref);
            $labels[] = self::mesLabel($ref);
            $stmt = $db->execute(
                'SELECT status, COALESCE(SUM(valor_nominal), 0) AS total FROM inter_cobrancas
                 WHERE operadora_id = ? AND competencia = ?
                 GROUP BY status',
                [$opId, $comp]
            );
            $em = 0.0;
            $rec = 0.0;
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $st = strtoupper((string)$row['status']);
                $total = (float)$row['total'];
                if (in_array($st, ['CANCELADA', 'CANCELADO'], true)) {
                    continue;
                }
                $em += $total;
                if (in_array($st, ['PAGO', 'RECEBIDO'], true)) {
                    $rec += $total;
                }
            }
            $emitido[] = $em;
            $recebido[] = $rec;
        }

        return ['labels' => $labels, 'emitido' => $emitido, 'recebido' => $recebido];
    }

    /** @return array{labels:list<string>,values:list<int>} */
    public static function cobrancasPorStatusCompetencia(?string $competenciaYm = null): array
    {
        $comp = ($competenciaYm !== null && preg_match('/^\d{4}-\d{2}$/', $competenciaYm))
            ? $competenciaYm
            : date('Y-m');
        $db = new Database();
        $opId = OperadoraScope::getOperadoraId();
        $stmt = $db->execute(
            'SELECT status, COUNT(*) AS qtd FROM inter_cobrancas
             WHERE operadora_id = ? AND competencia = ?
             GROUP BY status ORDER BY qtd DESC',
            [$opId, $comp]
        );
        $labels = [];
        $values = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $labels[] = (string)$row['status'];
            $values[] = (int)$row['qtd'];
        }

        return ['labels' => $labels, 'values' => $values];
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
        return self::coletasPorStatusColetor(0, true);
    }

    /** @return array{labels:list<string>,values:list<int>} */
    public static function coletasPorStatusColetor(int $userId, bool $isAdmin): array
    {
        $db = new Database();
        $opId = OperadoraScope::getOperadoraId();
        if ($isAdmin || $userId <= 0) {
            $stmt = $db->execute(
                "SELECT status, COUNT(*) AS qtd FROM coletas WHERE operadora_id = ?
                 AND status != 'cancelada' GROUP BY status ORDER BY qtd DESC",
                [$opId]
            );
        } else {
            $stmt = $db->execute(
                "SELECT status, COUNT(*) AS qtd FROM coletas
                 WHERE operadora_id = ? AND coletor_id = ? AND status != 'cancelada'
                 GROUP BY status ORDER BY qtd DESC",
                [$opId, $userId]
            );
        }

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
        if (!$isAdmin && $userId <= 0) {
            return 0;
        }

        return RotaScopeService::countParadasAgendadasNaData(
            date('Y-m-d'),
            $isAdmin ? 0 : $userId,
            $isAdmin
        );
    }
}
