<?php

namespace App\Controller\Admin;

use App\Common\Helpers\ModuleGateHelper;
use App\Service\DashboardService;
use App\Session\User\Login as SessionUser;
use App\Utils\View;

class Home
{
    public static function index($request): string
    {
        $kpis = DashboardService::kpis();
        $coletasChart = DashboardService::coletasPorMes();
        $faturamentoChart = DashboardService::faturamentoPorMes();
        $statusChart = DashboardService::coletasPorStatus();

        $usuario = SessionUser::getUserLogedData()['usuario'] ?? [];
        $mesInicio = date('Y-m-01');
        $mesFim = date('Y-m-t');
        $links = self::kpiLinks($usuario, $mesInicio, $mesFim);

        $content = View::render('admin/modules/home/index', array_merge([
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
        ], self::kpiLinkWraps($links)));

        $scripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>'
            .'<script src="'.URL.'/resources/js/dashboard-charts.js?v=20260916i"></script>';

        return Page::getPage('Dashboard — '.SITE, $content, 'dashboard', $scripts);
    }

    /** @return array<string, string> slug => path com query (sem URL base) */
    private static function kpiLinks(array $usuario, string $mesInicio, string $mesFim): array
    {
        $q = static fn (array $params): string => http_build_query($params);

        return [
            'coletas_mes' => self::kpiPath($usuario, 'coletas', '/painel/coletas?'.$q([
                'status' => 'finalizada',
                'data_inicio' => $mesInicio,
                'data_fim' => $mesFim,
            ])),
            'rascunhos' => self::kpiPath($usuario, 'coletas', '/painel/coletas?'.$q(['status' => 'rascunho'])),
            'urgentes' => self::kpiPath($usuario, 'clientes', '/painel/clientes?'.$q([
                'status' => 'ativo',
                'prioridade' => 'urgente',
            ])),
            'atrasados' => self::kpiPath($usuario, 'agendamentos', '/painel/agendamentos'),
            'clientes_ativos' => self::kpiPath($usuario, 'clientes', '/painel/clientes?'.$q(['status' => 'ativo'])),
            'sem_coletor' => self::kpiPath($usuario, 'rotas', '/painel/rotas'),
            'pend_recebimento' => self::kpiPath($usuario, 'coletas', '/painel/coletas?'.$q([
                'status' => 'finalizada',
                'situacao_recebimento' => 'pendente',
            ])),
            'faturamento' => self::kpiPath($usuario, 'pagamentos', '/painel/pagamentos'),
            'chart_coletas' => self::kpiPath($usuario, 'coletas', '/painel/coletas?'.$q(['status' => 'finalizada'])),
        ];
    }

    private static function kpiPath(array $usuario, string $moduleSlug, string $path): string
    {
        if (!ModuleGateHelper::podeAcessar($moduleSlug, $usuario)) {
            return '';
        }

        return $path;
    }

    /**
     * @param array<string, string> $links
     * @return array<string, string> wrap_{key}_open / wrap_{key}_close
     */
    private static function kpiLinkWraps(array $links): array
    {
        $out = [];
        foreach ($links as $key => $path) {
            if ($path !== '') {
                $href = htmlspecialchars(URL.$path, ENT_QUOTES, 'UTF-8');
                $out['wrap_'.$key.'_open'] = '<a href="'.$href.'" class="dashboard-kpi-card text-decoration-none text-body d-block h-100" title="Ver detalhes">';
                $out['wrap_'.$key.'_close'] = '</a>';
            } else {
                $out['wrap_'.$key.'_open'] = '<div class="dashboard-kpi-card dashboard-kpi-card--static h-100">';
                $out['wrap_'.$key.'_close'] = '</div>';
            }
        }

        return $out;
    }
}
