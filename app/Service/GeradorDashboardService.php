<?php

namespace App\Service;

use App\Common\GeradorScope;
use App\Common\Helpers\FormatHelper;
use App\Model\Db\Database;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\InterCobranca as EntityInterCobranca;

class GeradorDashboardService
{
    /** @return array<string,int|float|string|null> */
    public static function kpis(): array
    {
        $clienteId = GeradorScope::getClienteId();
        $opId = GeradorScope::getOperadoraId();
        $db = new Database();
        $mesInicio = date('Y-m-01');
        $mesFim = date('Y-m-t');

        $coletasMes = EntityColeta::count(
            "c.cliente_id = ? AND c.status = 'finalizada' AND c.data_coleta BETWEEN ? AND ?",
            [$clienteId, $mesInicio, $mesFim]
        );

        $totalColetas = EntityColeta::count(
            "c.cliente_id = ? AND c.status != 'cancelada'",
            [$clienteId]
        );

        $rowAberto = $db->execute(
            "SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_nominal), 0) AS total
             FROM inter_cobrancas
             WHERE cliente_id = ? AND operadora_id = ?
             AND UPPER(status) NOT IN ('PAGO', 'RECEBIDO', 'CANCELADA', 'CANCELADO')",
            [$clienteId, $opId]
        )->fetch(\PDO::FETCH_ASSOC);

        $proximo = $db->execute(
            "SELECT data_vencimento, valor_nominal, competencia
             FROM inter_cobrancas
             WHERE cliente_id = ? AND operadora_id = ?
             AND UPPER(status) NOT IN ('PAGO', 'RECEBIDO', 'CANCELADA', 'CANCELADO')
             ORDER BY data_vencimento ASC LIMIT 1",
            [$clienteId, $opId]
        )->fetch(\PDO::FETCH_ASSOC);

        return [
            'coletas_mes' => $coletasMes,
            'total_coletas' => $totalColetas,
            'boletos_abertos' => (int)($rowAberto['qtd'] ?? 0),
            'valor_aberto' => (float)($rowAberto['total'] ?? 0),
            'proximo_vencimento' => is_array($proximo) ? (string)($proximo['data_vencimento'] ?? '') : null,
            'proximo_valor' => is_array($proximo) ? (float)($proximo['valor_nominal'] ?? 0) : 0.0,
            'proximo_competencia' => is_array($proximo) ? (string)($proximo['competencia'] ?? '') : null,
        ];
    }

    /** @return array{labels:list<string>,values:list<int>} */
    public static function coletasPorMes(int $meses = 6): array
    {
        $clienteId = GeradorScope::getClienteId();
        $opId = GeradorScope::getOperadoraId();
        $db = new Database();
        $labels = [];
        $values = [];

        for ($i = $meses - 1; $i >= 0; $i--) {
            $ref = strtotime('-'.$i.' months');
            $inicio = date('Y-m-01', $ref);
            $fim = date('Y-m-t', $ref);
            $labels[] = self::mesLabel($ref);
            $values[] = (int)$db->execute(
                "SELECT COUNT(*) AS qtd FROM coletas
                 WHERE cliente_id = ? AND operadora_id = ?
                 AND status = 'finalizada' AND data_coleta BETWEEN ? AND ?",
                [$clienteId, $opId, $inicio, $fim]
            )->fetch(\PDO::FETCH_ASSOC)['qtd'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Agrupa pelo rótulo do portal (Pago, Em aberto, Vencido, Cancelado).
     * O status cru do Inter, como A_RECEBER, não aparece na legenda.
     *
     * @return array{labels:list<string>,values:list<int>,colors:list<string>}
     */
    public static function boletosPorStatus(): array
    {
        $clienteId = GeradorScope::getClienteId();
        $opId = GeradorScope::getOperadoraId();
        $db = new Database();
        $stmt = $db->execute(
            'SELECT status, COUNT(*) AS qtd FROM inter_cobrancas
             WHERE cliente_id = ? AND operadora_id = ?
             GROUP BY status',
            [$clienteId, $opId]
        );

        $agregado = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $label = FormatHelper::statusBoleto((string)$row['status'])['label'];
            $agregado[$label] = ($agregado[$label] ?? 0) + (int)$row['qtd'];
        }

        $ordem = ['Em aberto', 'Vencido', 'Pago', 'Cancelado'];
        $labels = [];
        $values = [];
        $colors = [];
        $cor = [
            'Em aberto' => '#0288D1',
            'Vencido' => '#D32F2F',
            'Pago' => '#2E7D32',
            'Cancelado' => '#6c757d',
        ];
        foreach ($ordem as $label) {
            if (!isset($agregado[$label])) {
                continue;
            }
            $labels[] = $label;
            $values[] = $agregado[$label];
            $colors[] = $cor[$label];
            unset($agregado[$label]);
        }
        foreach ($agregado as $label => $qtd) {
            $labels[] = $label;
            $values[] = $qtd;
            $colors[] = '#00897B';
        }

        return ['labels' => $labels, 'values' => $values, 'colors' => $colors];
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
}
