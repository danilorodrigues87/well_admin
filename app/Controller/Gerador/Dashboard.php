<?php

namespace App\Controller\Gerador;

use App\Common\Helpers\CrudHelper;
use App\Common\Helpers\FormatHelper;
use App\Common\Helpers\MoneyHelper;
use App\Common\GeradorScope;
use App\Service\ColetaSolicitacaoService;
use App\Service\GeradorAgendamentoVisivelService;
use App\Service\GeradorDashboardService;
use App\Service\GeradorPortalService;
use App\Session\Gerador\Login as GeradorSession;
use App\Utils\View;

class Dashboard extends Page
{
    public static function index($request): string
    {
        $session = GeradorSession::getData() ?? [];
        $kpis = GeradorDashboardService::kpis();
        $clienteId = GeradorScope::getClienteId();
        $cota = ColetaSolicitacaoService::resumoCota($clienteId);
        $agendamento = GeradorAgendamentoVisivelService::resumo($clienteId);
        $agendamentoHtml = self::renderAgendamentoAlert($agendamento);
        $coletasChart = GeradorDashboardService::coletasPorMes();
        $faturamentoChart = GeradorDashboardService::faturamentoPorMes();
        $boletosChart = GeradorDashboardService::boletosPorStatus();

        $coletas = GeradorPortalService::listColetas(1, 5);
        $boletos = GeradorPortalService::listBoletos(1, 5);

        $coletasHtml = '';
        foreach ($coletas['items'] as $c) {
            $mtr = !empty($c['mtr_disponivel']) && !empty($c['numero_mtr'])
                ? CrudHelper::e((string)$c['numero_mtr'])
                : '<span class="text-muted">'.CrudHelper::e((string)($c['mtr_rotulo'] ?? '—')).'</span>';
            $coletasHtml .= '<tr>
                <td>'.$mtr.'</td>
                <td>'.FormatHelper::dateBr((string)($c['data_coleta'] ?? '')).'</td>
                <td>'.FormatHelper::statusColetaBadge((string)($c['status'] ?? '')).'</td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="'.URL.'/gerador/coletas/'.(int)$c['id'].'">Ver</a></td>
            </tr>';
        }
        if ($coletasHtml === '') {
            $coletasHtml = '<tr><td colspan="4" class="text-muted text-center py-3">Nenhuma coleta registrada.</td></tr>';
        }

        $boletosHtml = '';
        foreach ($boletos['items'] as $b) {
            $valor = (float)($b['valor_cobrado'] ?? $b['valor_nominal'] ?? 0);
            $boletosHtml .= '<tr>
                <td>'.FormatHelper::competenciaBr((string)($b['competencia'] ?? '')).'</td>
                <td class="text-end fw-semibold">'.MoneyHelper::format($valor).'</td>
                <td>'.FormatHelper::dateBr((string)($b['data_vencimento'] ?? '')).'</td>
                <td>'.FormatHelper::statusBoletoBadge((string)($b['status'] ?? '')).'</td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="'.URL.'/gerador/boletos/'.(int)$b['id'].'">Ver</a></td>
            </tr>';
        }
        if ($boletosHtml === '') {
            $boletosHtml = '<tr><td colspan="5" class="text-muted text-center py-3">Nenhum boleto emitido.</td></tr>';
        }

        $proximoVenc = $kpis['proximo_vencimento']
            ? FormatHelper::dateBr((string)$kpis['proximo_vencimento'])
            : '—';

        $content = View::render('gerador/dashboard', [
            'cliente_nome' => htmlspecialchars((string)($session['cliente_nome'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'user_nome' => htmlspecialchars((string)($session['nome'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'cota_restantes' => (int)$cota['restantes'],
            'cota_limite' => (int)$cota['limite'],
            'cota_periodo' => htmlspecialchars((string)$cota['periodo_label'], ENT_QUOTES, 'UTF-8'),
            'coletas_mes' => (int)$kpis['coletas_mes'],
            'total_coletas' => (int)$kpis['total_coletas'],
            'boletos_abertos' => (int)$kpis['boletos_abertos'],
            'valor_aberto' => MoneyHelper::format((float)$kpis['valor_aberto']),
            'proximo_vencimento' => $proximoVenc,
            'proximo_valor' => MoneyHelper::format((float)$kpis['proximo_valor']),
            'agendamento_alert' => $agendamentoHtml,
            'coletas_rows' => $coletasHtml,
            'boletos_rows' => $boletosHtml,
            'chart_coletas_labels' => json_encode($coletasChart['labels'], JSON_UNESCAPED_UNICODE),
            'chart_coletas_values' => json_encode($coletasChart['values']),
            'chart_faturamento_labels' => json_encode($faturamentoChart['labels'], JSON_UNESCAPED_UNICODE),
            'chart_faturamento_values' => json_encode($faturamentoChart['values']),
            'chart_boletos_labels' => json_encode($boletosChart['labels'], JSON_UNESCAPED_UNICODE),
            'chart_boletos_values' => json_encode($boletosChart['values']),
        ]);

        $scripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>'
            .'<script src="'.URL.'/resources/js/dashboard-charts.js?v=20260916i"></script>';

        return self::render('Início', $content, 'dashboard', $scripts);
    }

    /** @param array<string,mixed>|null $ag */
    private static function renderAgendamentoAlert(?array $ag): string
    {
        if ($ag === null) {
            return '';
        }
        $data = FormatHelper::dateBr((string)($ag['data'] ?? ''));
        $css = match ($ag['situacao'] ?? '') {
            'atrasada' => 'warning',
            'pendente_aprovacao' => 'info',
            'hoje' => 'success',
            default => 'primary',
        };
        $titulo = match ($ag['situacao'] ?? '') {
            'pendente_aprovacao' => 'Coleta solicitada',
            'atrasada' => 'Coleta prevista (em atraso)',
            'hoje' => 'Coleta prevista para hoje',
            default => 'Próxima coleta agendada',
        };

        return '<div class="alert alert-'.$css.' d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">'
            .'<div><strong>'.$titulo.':</strong> '.$data
            .'<span class="d-block small mb-0">'.htmlspecialchars((string)($ag['mensagem'] ?? ''), ENT_QUOTES, 'UTF-8').'</span></div>'
            .'<a href="'.URL.'/gerador/agendamentos" class="btn btn-sm btn-outline-'.$css.'">Ver agendamentos</a>'
            .'</div>';
    }
}
