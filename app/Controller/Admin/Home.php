<?php

namespace App\Controller\Admin;

use App\Service\DashboardService;
use App\Utils\View;

class Home
{
    public static function index($request): string
    {
        $kpis = DashboardService::kpis();
        $coletasChart = DashboardService::coletasPorMes();
        $faturamentoChart = DashboardService::faturamentoPorMes();
        $statusChart = DashboardService::coletasPorStatus();

        $content = View::render('admin/modules/home/index', [
            'site' => SITE,
            'coletas_mes' => $kpis['coletas_mes'],
            'rascunhos' => $kpis['rascunhos'],
            'urgentes' => $kpis['urgentes'],
            'atrasados' => $kpis['atrasados'],
            'sem_coletor' => $kpis['sem_coletor'],
            'pend_recebimento' => $kpis['pend_recebimento'],
            'clientes_ativos' => $kpis['clientes_ativos'],
            'chart_coletas_labels' => json_encode($coletasChart['labels'], JSON_UNESCAPED_UNICODE),
            'chart_coletas_values' => json_encode($coletasChart['values']),
            'chart_faturamento_labels' => json_encode($faturamentoChart['labels'], JSON_UNESCAPED_UNICODE),
            'chart_faturamento_values' => json_encode($faturamentoChart['values']),
            'chart_status_labels' => json_encode($statusChart['labels'], JSON_UNESCAPED_UNICODE),
            'chart_status_values' => json_encode($statusChart['values']),
        ]);

        $scripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>'
            .'<script src="'.URL.'/resources/js/dashboard-charts.js?v=20260916i"></script>';

        return Page::getPage('Dashboard — '.SITE, $content, 'dashboard', $scripts);
    }
}
