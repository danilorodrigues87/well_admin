<?php

namespace App\Controller\Gerador;

use App\Common\Helpers\CrudHelper;
use App\Common\Helpers\FormatHelper;
use App\Common\Helpers\MoneyHelper;
use App\Http\Response;
use App\Service\GeradorPortalService;
use App\Utils\View;

class Boletos extends Page
{
    public static function index($request): string
    {
        $page = max(1, (int)($request->getQueryParams()['page'] ?? 1));
        $result = GeradorPortalService::listBoletos($page, 20);

        $rows = '';
        foreach ($result['items'] as $b) {
            $valor = (float)($b['valor_cobrado'] ?? $b['valor_nominal'] ?? 0);
            $pdfBtn = !empty($b['pdf_url'])
                ? '<a class="btn btn-sm btn-outline-secondary" target="_blank" href="'.CrudHelper::e((string)$b['pdf_url']).'" title="PDF"><i class="fas fa-file-pdf"></i></a>'
                : '';
            $rows .= '<tr>
                <td>'.FormatHelper::competenciaBr((string)($b['competencia'] ?? '')).'</td>
                <td class="text-end fw-semibold">'.MoneyHelper::format($valor).'</td>
                <td>'.FormatHelper::dateBr((string)($b['data_vencimento'] ?? '')).'</td>
                <td>'.FormatHelper::statusBoletoBadge((string)($b['status'] ?? '')).'</td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="'.URL.'/gerador/boletos/'.(int)$b['id'].'"><i class="fas fa-eye me-1"></i> Detalhes</a>
                    '.$pdfBtn.'
                </td>
            </tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="text-muted text-center py-4">Nenhum boleto emitido.</td></tr>';
        }

        $content = View::render('gerador/boletos/index', [
            'rows' => $rows,
            'pagination_nav' => self::paginationNav(
                (int)$result['pagination']['page'],
                (int)$result['pagination']['pages'],
                '/gerador/boletos'
            ),
        ]);

        return self::render('Financeiro', $content, 'boletos');
    }

    public static function show($request, int $id): string
    {
        $b = GeradorPortalService::boletoDetalhe($id);
        if ($b === null) {
            $request->getRouter()->redirect('/gerador/boletos');
        }

        $valorCobrado = (float)($b['valor_cobrado'] ?? 0);
        $valorCalculado = (float)($b['valor_calculado'] ?? 0);
        $ajusteHtml = '';
        if ($valorCalculado > 0 && abs($valorCobrado - $valorCalculado) > 0.009) {
            $ajusteHtml = '<div class="alert alert-info py-2 small mb-0">
                Valor calculado pelo plano: '.MoneyHelper::format($valorCalculado).'
                · Valor cobrado (ajustado): <strong>'.MoneyHelper::format($valorCobrado).'</strong>
            </div>';
        }

        $obsHtml = !empty($b['observacao_ajuste'])
            ? '<div class="mt-2 small text-muted"><strong>Observação:</strong> '.CrudHelper::e((string)$b['observacao_ajuste']).'</div>'
            : '';

        $detalhesHtml = '';
        $valorFixo = (float)($b['valor_fixo'] ?? 0);
        $valorResiduos = (float)($b['valor_residuos'] ?? 0);
        if ($valorFixo > 0 || $valorResiduos > 0) {
            $detalhesHtml = '<table class="table table-sm mb-0">
                <tbody>
                    <tr><td>Mensalidade / valor fixo</td><td class="text-end">'.MoneyHelper::format($valorFixo).'</td></tr>
                    <tr><td>Resíduos excedentes</td><td class="text-end">'.MoneyHelper::format($valorResiduos).'</td></tr>
                    <tr class="fw-bold"><td>Total calculado</td><td class="text-end">'.MoneyHelper::format($valorCalculado).'</td></tr>
                </tbody>
            </table>';
        }

        $itensHtml = '';
        $itens = is_array($b['detalhes']['itens'] ?? null) ? $b['detalhes']['itens'] : [];
        foreach ($itens as $item) {
            if (!is_array($item)) {
                continue;
            }
            $itensHtml .= '<tr>
                <td>'.CrudHelper::e((string)($item['nome'] ?? $item['tipo'] ?? '—')).'</td>
                <td class="text-end">'.CrudHelper::e((string)($item['quantidade'] ?? '—')).'</td>
                <td class="text-end">'.MoneyHelper::format((float)($item['valor'] ?? 0)).'</td>
            </tr>';
        }

        $pixHtml = !empty($b['pix_copia_cola'])
            ? '<div class="mb-3"><label class="form-label small text-muted">PIX copia e cola</label>
               <textarea class="form-control form-control-sm" rows="3" readonly onclick="this.select()">'.CrudHelper::e((string)$b['pix_copia_cola']).'</textarea></div>'
            : '';

        $linhaHtml = !empty($b['linha_digitavel'])
            ? '<div class="mb-3"><label class="form-label small text-muted">Linha digitável</label>
               <input type="text" class="form-control form-control-sm" readonly value="'.CrudHelper::e((string)$b['linha_digitavel']).'" onclick="this.select()"/></div>'
            : '';

        $pdfBtn = !empty($b['pdf_url'])
            ? '<a class="btn btn-primary" target="_blank" href="'.CrudHelper::e((string)$b['pdf_url']).'"><i class="fas fa-file-pdf me-1"></i> Baixar PDF</a>'
            : '';

        if ($detalhesHtml === '' && $itensHtml === '') {
            $detalhesHtml = '<p class="text-muted small mb-0">Composição detalhada não disponível para este boleto.</p>';
        }

        $content = View::render('gerador/boletos/show', [
            'competencia' => FormatHelper::competenciaBr((string)($b['competencia'] ?? '')),
            'valor_cobrado' => MoneyHelper::format($valorCobrado),
            'data_vencimento' => FormatHelper::dateBr((string)($b['data_vencimento'] ?? '')),
            'status_badge' => FormatHelper::statusBoletoBadge((string)($b['status'] ?? '')),
            'ajuste_html' => $ajusteHtml,
            'obs_html' => $obsHtml,
            'detalhes_html' => $detalhesHtml,
            'itens_rows' => $itensHtml,
            'pix_html' => $pixHtml,
            'linha_html' => $linhaHtml,
            'pdf_btn' => $pdfBtn,
        ]);

        return self::render('Boleto '.$id, $content, 'boletos');
    }

    public static function pdf($request, int $id): Response
    {
        $boleto = GeradorPortalService::getBoleto($id);
        if ($boleto === null) {
            return new Response(404, View::render('erros/404', ['URL' => URL]));
        }

        $path = $boleto->pdfAbsolutePath();
        if ($path === null || !is_readable($path)) {
            return new Response(404, View::render('erros/404', ['URL' => URL]));
        }

        return new Response(200, file_get_contents($path), 'application/pdf');
    }
}
