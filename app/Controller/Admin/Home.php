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
        $usuario = SessionUser::getUserLogedData()['usuario'] ?? [];
        $kpis = DashboardService::kpisPanel($usuario);
        $hoje = date('Y-m-d');
        $mesInicio = date('Y-m-01');
        $mesFim = date('Y-m-t');
        $links = self::kpiLinks($usuario, $hoje, $mesInicio, $mesFim);

        $canColetas = ModuleGateHelper::podeAcessar('coletas', $usuario);
        $canClientes = ModuleGateHelper::podeAcessar('clientes', $usuario);
        $canRotaDia = ModuleGateHelper::podeAcessar('rota_dia', $usuario);
        $canAgendamentos = ModuleGateHelper::podeAcessar('agendamentos', $usuario);
        $canPagamentos = ModuleGateHelper::podeAcessar('pagamentos', $usuario);

        $coletasChart = $canColetas ? DashboardService::coletasPorMes() : ['labels' => [], 'values' => []];
        $statusChart = $canColetas ? DashboardService::coletasPorStatus() : ['labels' => [], 'values' => []];
        $finDual = $canPagamentos ? DashboardService::cobrancasEmitidoRecebidoPorMes() : ['labels' => [], 'emitido' => [], 'recebido' => []];
        $finStatus = $canPagamentos ? DashboardService::cobrancasPorStatusCompetencia() : ['labels' => [], 'values' => []];

        $content = View::render('admin/modules/home/index', [
            'site' => SITE,
            'operacao_cards' => self::renderOperacaoCards($kpis, $links, $usuario),
            'finance_cards' => $canPagamentos ? self::renderFinanceCards($kpis, $links) : '',
            'show_coletas_section_class' => $canColetas ? '' : 'd-none',
            'show_finance_section_class' => $canPagamentos ? '' : 'd-none',
            'competencia_label' => self::competenciaLabel(date('Y-m')),
            'fin_emitido_fmt' => self::money($kpis['fin_emitido_mes'] ?? 0),
            'fin_recebido_fmt' => self::money($kpis['fin_recebido_mes'] ?? 0),
            'chart_coletas_labels' => json_encode($coletasChart['labels'], JSON_UNESCAPED_UNICODE),
            'chart_coletas_values' => json_encode($coletasChart['values']),
            'chart_status_labels' => json_encode($statusChart['labels'], JSON_UNESCAPED_UNICODE),
            'chart_status_values' => json_encode($statusChart['values']),
            'chart_fin_labels' => json_encode($finDual['labels'], JSON_UNESCAPED_UNICODE),
            'chart_fin_emitido' => json_encode($finDual['emitido']),
            'chart_fin_recebido' => json_encode($finDual['recebido']),
            'chart_fin_status_labels' => json_encode($finStatus['labels'], JSON_UNESCAPED_UNICODE),
            'chart_fin_status_values' => json_encode($finStatus['values']),
            'wrap_chart_coletas_open' => $canColetas && $links['chart_coletas'] !== ''
                ? self::linkOpen($links['chart_coletas']) : '<div class="h-100">',
            'wrap_chart_coletas_close' => $canColetas && $links['chart_coletas'] !== '' ? '</a>' : '</div>',
            'wrap_pagamentos_open' => $canPagamentos && $links['pagamentos'] !== ''
                ? self::linkOpen($links['pagamentos']) : '<div class="h-100">',
            'wrap_pagamentos_close' => $canPagamentos && $links['pagamentos'] !== '' ? '</a>' : '</div>',
        ]);

        $scripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>'
            .'<script src="'.URL.'/resources/js/dashboard-charts.js?v=20260929d"></script>';

        return Page::getPage('Dashboard — '.SITE, $content, 'dashboard', $scripts);
    }

    /** @param array<string, int|float> $kpis */
    private static function renderOperacaoCards(array $kpis, array $links, array $usuario): string
    {
        $cards = [
            [
                'key' => 'coletas_hoje',
                'module' => 'coletas',
                'label' => 'Coletas finalizadas hoje',
                'hint' => 'Ver coletas de hoje',
                'value' => (int)($kpis['coletas_hoje'] ?? 0),
                'border' => 'primary',
                'icon' => 'fa-truck',
                'iconClass' => 'text-primary',
            ],
            [
                'key' => 'rascunhos',
                'module' => 'coletas',
                'label' => 'Rascunhos abertos',
                'hint' => 'Continuar ou excluir',
                'value' => (int)($kpis['rascunhos'] ?? 0),
                'border' => 'warning',
                'icon' => 'fa-pen',
                'iconClass' => 'text-warning',
            ],
            [
                'key' => 'clientes_ativos',
                'module' => 'clientes',
                'label' => 'Clientes ativos',
                'hint' => 'Cadastro de clientes',
                'value' => (int)($kpis['clientes_ativos'] ?? 0),
                'border' => 'success',
                'icon' => 'fa-building',
                'iconClass' => 'text-success',
            ],
            [
                'key' => 'paradas_hoje',
                'module' => 'rota_dia',
                'label' => 'Paradas agendadas hoje',
                'hint' => 'Abrir rota do dia',
                'value' => (int)($kpis['paradas_hoje'] ?? 0),
                'border' => 'info',
                'icon' => 'fa-map-location-dot',
                'iconClass' => 'text-info',
            ],
            [
                'key' => 'solicitacoes',
                'module' => 'agendamentos',
                'label' => 'Solicitações portal',
                'hint' => 'Pendentes de aprovação',
                'value' => (int)($kpis['solicitacoes_pendentes'] ?? 0),
                'border' => 'danger',
                'icon' => 'fa-inbox',
                'iconClass' => 'text-danger',
            ],
        ];

        $extra = [
            [
                'key' => 'mtr_pendente',
                'module' => 'coletas',
                'label' => 'MTR SINIR pendente',
                'hint' => 'Clientes que exigem MTR',
                'value' => (int)($kpis['mtr_sinir_pendente'] ?? 0),
                'border' => 'secondary',
                'icon' => 'fa-file-contract',
                'iconClass' => 'text-secondary',
            ],
            [
                'key' => 'rotas_hoje',
                'module' => 'rota_dia',
                'label' => 'Rotas com coleta hoje',
                'hint' => 'Rotas cadastrais ativas',
                'value' => (int)($kpis['rotas_com_coleta_hoje'] ?? 0),
                'border' => 'secondary',
                'icon' => 'fa-route',
                'iconClass' => 'text-secondary',
            ],
            [
                'key' => 'coletas_mes',
                'module' => 'coletas',
                'label' => 'Coletas no mês',
                'hint' => 'Finalizadas no mês corrente',
                'value' => (int)($kpis['coletas_mes'] ?? 0),
                'border' => 'primary',
                'icon' => 'fa-calendar-check',
                'iconClass' => 'text-primary opacity-75',
            ],
        ];

        $html = '<div class="row g-3 mb-3">'.self::renderCardRow($cards, $links, $usuario).'</div>';
        if (self::anyModule($extra, $usuario)) {
            $html .= '<div class="row g-3 mb-4">'.self::renderCardRow($extra, $links, $usuario, 'sm').'</div>';
        }

        return $html;
    }

    /** @param array<int, array<string, mixed>> $cards */
    private static function renderCardRow(array $cards, array $links, array $usuario, string $size = ''): string
    {
        $html = '';
        $col = $size === 'sm' ? 'col-xl-4 col-md-4' : 'col-xl col-md-6';
        foreach ($cards as $c) {
            if (!ModuleGateHelper::podeAcessar($c['module'], $usuario)) {
                continue;
            }
            $linkKey = $c['key'];
            $path = $links[$linkKey] ?? '';
            $open = $path !== '' ? self::linkOpen($path) : '<div class="dashboard-kpi-card dashboard-kpi-card--static d-block h-100">';
            $close = $path !== '' ? '</a>' : '</div>';
            $html .= '<div class="'.$col.'">'.$open
                .'<div class="card border-start border-'.$c['border'].' border-4 h-100 shadow-sm">'
                .'<div class="card-body py-3">'
                .'<div class="d-flex justify-content-between align-items-start">'
                .'<div><div class="text-muted small">'.$c['label'].'</div>'
                .'<div class="fs-2 fw-bold">'.$c['value'].'</div>'
                .'<div class="small text-muted dashboard-kpi-hint">'.$c['hint'].'</div></div>'
                .'<i class="fas '.$c['icon'].' fa-lg '.$c['iconClass'].' opacity-50"></i>'
                .'</div></div></div>'.$close.'</div>';
        }

        return $html;
    }

    /** @param array<int, array<string, mixed>> $cards */
    private static function anyModule(array $cards, array $usuario): bool
    {
        foreach ($cards as $c) {
            if (ModuleGateHelper::podeAcessar($c['module'], $usuario)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, int|float> $kpis */
    private static function renderFinanceCards(array $kpis, array $links): string
    {
        $comp = self::competenciaLabel(date('Y-m'));
        $items = [
            ['key' => 'fin_emitido', 'label' => 'Emitido ('.$comp.')', 'value' => self::money($kpis['fin_emitido_mes'] ?? 0), 'sub' => (int)($kpis['fin_qtd_emitida'] ?? 0).' boleto(s)', 'border' => 'primary'],
            ['key' => 'fin_recebido', 'label' => 'Recebido', 'value' => self::money($kpis['fin_recebido_mes'] ?? 0), 'sub' => (int)($kpis['fin_qtd_paga'] ?? 0).' pago(s)', 'border' => 'success'],
            ['key' => 'fin_aberto', 'label' => 'Em aberto', 'value' => self::money($kpis['fin_aberto_mes'] ?? 0), 'sub' => (int)($kpis['fin_qtd_aberta'] ?? 0).' em carteira', 'border' => 'info'],
            ['key' => 'fin_vencido', 'label' => 'Vencido', 'value' => self::money($kpis['fin_vencido_mes'] ?? 0), 'sub' => (int)($kpis['fin_qtd_vencida'] ?? 0).' boleto(s)', 'border' => 'danger'],
        ];
        $html = '<h5 class="mb-3 mt-2"><i class="fas fa-money-bill-wave me-2 text-success"></i>Financeiro — competência '.$comp.'</h5>'
            .'<div class="row g-3 mb-4">';
        foreach ($items as $it) {
            $path = $links['pagamentos'] ?? '';
            $open = $path !== '' ? self::linkOpen($path) : '<div class="dashboard-kpi-card dashboard-kpi-card--static d-block h-100">';
            $close = $path !== '' ? '</a>' : '</div>';
            $html .= '<div class="col-xl-3 col-md-6">'.$open
                .'<div class="card border-start border-'.$it['border'].' border-4 h-100 shadow-sm">'
                .'<div class="card-body"><div class="text-muted small">'.$it['label'].'</div>'
                .'<div class="fs-4 fw-bold">'.$it['value'].'</div>'
                .'<div class="small text-muted">'.$it['sub'].'</div></div></div>'.$close.'</div>';
        }
        $html .= '</div>';

        return $html;
    }

    private static function linkOpen(string $path): string
    {
        $href = htmlspecialchars(URL.$path, ENT_QUOTES, 'UTF-8');

        return '<a href="'.$href.'" class="dashboard-kpi-card text-decoration-none text-body d-block h-100" title="Ver detalhes">';
    }

    private static function money(float $v): string
    {
        return 'R$ '.number_format($v, 2, ',', '.');
    }

    private static function competenciaLabel(string $ym): string
    {
        static $meses = [
            1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho',
            7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
        ];
        $p = explode('-', $ym);
        if (count($p) !== 2) {
            return $ym;
        }
        $m = (int)$p[1];

        return ($meses[$m] ?? $m).'/'.($p[0] ?? '');
    }

    /** @return array<string, string> slug => path com query (sem URL base) */
    private static function kpiLinks(array $usuario, string $hoje, string $mesInicio, string $mesFim): array
    {
        $q = static fn (array $params): string => http_build_query($params);

        return [
            'coletas_hoje' => self::kpiPath($usuario, 'coletas', '/painel/coletas?'.$q([
                'status' => 'finalizada',
                'data_inicio' => $hoje,
                'data_fim' => $hoje,
            ])),
            'coletas_mes' => self::kpiPath($usuario, 'coletas', '/painel/coletas?'.$q([
                'status' => 'finalizada',
                'data_inicio' => $mesInicio,
                'data_fim' => $mesFim,
            ])),
            'rascunhos' => self::kpiPath($usuario, 'coletas', '/painel/coletas?'.$q(['status' => 'rascunho'])),
            'clientes_ativos' => self::kpiPath($usuario, 'clientes', '/painel/clientes?'.$q(['status' => 'ativo'])),
            'paradas_hoje' => self::kpiPath($usuario, 'rota_dia', '/painel/rota-do-dia'),
            'rotas_hoje' => self::kpiPath($usuario, 'rota_dia', '/painel/rota-do-dia'),
            'solicitacoes' => self::kpiPath($usuario, 'agendamentos', '/painel/agendamentos'),
            'mtr_pendente' => self::kpiPath($usuario, 'coletas', '/painel/coletas?'.$q([
                'status' => 'finalizada',
                'filtro_mtr' => 'mtr_pendente',
            ])),
            'pagamentos' => self::kpiPath($usuario, 'pagamentos', '/painel/pagamentos'),
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
}
