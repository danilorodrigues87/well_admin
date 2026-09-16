<?php

namespace App\Service;

use App\Common\GeradorScope;
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

    /** @return array{labels:list<string>,values:list<float>} */
    public static function faturamentoPorMes(int $meses = 6): array
    {
        $clienteId = GeradorScope::getClienteId();
        $opId = GeradorScope::getOperadoraId();
        $db = new Database();
        $labels = [];
        $values = [];

        for ($i = $meses - 1; $i >= 0; $i--) {
            $ref = strtotime('-'.$i.' months');
            $comp = date('Y-m', $ref);
            $labels[] = self::mesLabel($ref);
            $values[] = (float)$db->execute(
                'SELECT COALESCE(SUM(valor_nominal), 0) AS total FROM inter_cobrancas
                 WHERE cliente_id = ? AND operadora_id = ? AND competencia = ?',
                [$clienteId, $opId, $comp]
            )->fetch(\PDO::FETCH_ASSOC)['total'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array{labels:list<string>,values:list<int>} */
    public static function boletosPorStatus(): array
    {
        $clienteId = GeradorScope::getClienteId();
        $opId = GeradorScope::getOperadoraId();
        $db = new Database();
        $stmt = $db->execute(
            'SELECT status, COUNT(*) AS qtd FROM inter_cobrancas
             WHERE cliente_id = ? AND operadora_id = ?
             GROUP BY status ORDER BY qtd DESC',
            [$clienteId, $opId]
        );

        $labels = [];
        $values = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $labels[] = (string)$row['status'];
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
}
