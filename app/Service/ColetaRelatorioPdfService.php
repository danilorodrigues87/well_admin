<?php

namespace App\Service;

use App\Common\Helpers\ColetaMtrHelper;
use App\Common\Helpers\CrudHelper;
use App\Model\Entity\Coleta as EntityColeta;
use App\Utils\View;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Relatório de coleta (HTML impressão + PDF Dompdf) — Admin e API.
 */
class ColetaRelatorioPdfService
{
    /**
     * @param array{coleta: EntityColeta, snapshot: mixed, itens: array} $detalhe
     * @param array{auto_print?: bool, rotulo?: string, status_aviso?: bool, for_pdf?: bool} $opts
     */
    public static function viewVars(array $detalhe, array $opts = []): array
    {
        $c = $detalhe['coleta'];
        $s = $detalhe['snapshot'];
        $forPdf = !empty($opts['for_pdf']);

        $itensHtml = '';
        $totalKg = 0.0;
        foreach ($detalhe['itens'] as $i) {
            $qtd = number_format((float)$i->quantidade, 3, ',', '.').' '.strtoupper((string)$i->unidade);
            $itensHtml .= '<tr><td>'.CrudHelper::e($i->nome).'</td><td style="text-align:right;">'.CrudHelper::e($qtd).'</td></tr>';
            if ((string)$i->unidade === 'kg') {
                $totalKg += (float)$i->quantidade;
            }
        }
        if ($itensHtml === '') {
            $itensHtml = '<tr><td colspan="2" class="text-muted">Sem itens registrados.</td></tr>';
        }

        $rotulo = $opts['rotulo'] ?? ColetaMtrHelper::rotuloImpressao($c);
        $autoPrint = !$forPdf && !empty($opts['auto_print'])
            ? '<script>window.addEventListener("load", function () { window.print(); });</script>'
            : '';

        $statusAviso = '';
        if (($opts['status_aviso'] ?? true) && ($c->status ?? '') === 'rascunho') {
            $statusAviso = '<p class="mtr-rascunho-aviso'.($forPdf ? '' : ' no-print').'"><strong>Rascunho</strong> — documento provisório até concluir o relatório e, se aplicável, registro no SINIR.</p>';
        }

        $logoPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'imgs'.DIRECTORY_SEPARATOR.'logo.png';
        if ($forPdf && is_readable($logoPath)) {
            $logoSrc = 'data:image/png;base64,'.base64_encode((string)file_get_contents($logoPath));
        } else {
            $logoSrc = URL.'/resources/assets/imgs/logo.png';
        }

        $cssPath = dirname(__DIR__, 2).'/resources/css/mtr-print.css';
        if ($forPdf && is_readable($cssPath)) {
            $css = (string)file_get_contents($cssPath);
            $cssLinkOrInline = '<style>'.$css
                .'body.mtr-print-page{background:#fff;padding:8px;}'
                .'.mtr-toolbar,.no-print{display:none!important;}'
                .'.mtr-table{max-width:100%;margin:0;}'
                .'</style>';
        } else {
            $cssLinkOrInline = '<link href="'.URL.'/resources/css/mtr-print.css" rel="stylesheet" />';
        }

        $pdfUrl = array_key_exists('pdf_download_url', $opts)
            ? (string)$opts['pdf_download_url']
            : URL.'/painel/coletas/mtr/'.$c->id.'/pdf';
        $pdfBtn = $pdfUrl !== ''
            ? '<a class="btn btn-outline-danger" href="'.CrudHelper::e($pdfUrl).'">Baixar PDF</a>'
            : '';

        return [
            'URL' => URL,
            'css_link_or_inline' => $cssLinkOrInline,
            'logo_src' => $logoSrc,
            'status_impressao_aviso' => $statusAviso,
            'pdf_download_url' => $pdfUrl,
            'pdf_download_btn' => $pdfBtn,
            'numero_mtr' => CrudHelper::e((string)$rotulo),
            'gerador_nome' => CrudHelper::e(mb_strtoupper((string)($s?->gerador_nome_fantasia ?? $c->cliente_nome ?? ''), 'UTF-8')),
            'gerador_cnpj' => CrudHelper::e($s?->gerador_cnpj ?? ''),
            'gerador_plano' => CrudHelper::e(mb_strtoupper((string)($s?->gerador_plano ?? ''), 'UTF-8')),
            'gerador_endereco' => CrudHelper::e(mb_strtoupper((string)($s?->gerador_endereco ?? ''), 'UTF-8')),
            'gerador_responsavel' => CrudHelper::e(mb_strtoupper((string)($s?->gerador_responsavel ?? ''), 'UTF-8')),
            'doc_referencia' => $c->doc_referencia ? date('d/m/Y', strtotime($c->doc_referencia)) : '—',
            'data_coleta' => $c->data_coleta ? date('d/m/Y', strtotime($c->data_coleta)) : '—',
            'hora' => $c->hora ? substr((string)$c->hora, 0, 5) : '',
            'relatorio' => nl2br(CrudHelper::e($c->relatorio ?? '')),
            'transportador_nome' => CrudHelper::e(mb_strtoupper((string)($s?->transportador_nome ?? ''), 'UTF-8')),
            'transportador_cnpj' => CrudHelper::e($s?->transportador_cnpj ?? ''),
            'motorista_nome' => CrudHelper::e(mb_strtoupper((string)($s?->motorista_nome ?? ''), 'UTF-8')),
            'veiculo_descricao' => CrudHelper::e(mb_strtoupper((string)($s?->veiculo_descricao ?? ''), 'UTF-8')),
            'veiculo_placa' => CrudHelper::e(mb_strtoupper((string)($s?->veiculo_placa ?? ''), 'UTF-8')),
            'destinador_nome' => CrudHelper::e(mb_strtoupper((string)($s?->destinador_nome ?? ''), 'UTF-8')),
            'destinador_cnpj' => CrudHelper::e($s?->destinador_cnpj ?? ''),
            'destinador_endereco' => CrudHelper::e(mb_strtoupper((string)($s?->destinador_endereco ?? ''), 'UTF-8')),
            'destinador_telefone' => CrudHelper::e($s?->destinador_telefone ?? ''),
            'destinador_responsavel' => CrudHelper::e(mb_strtoupper((string)($s?->destinador_responsavel ?? ''), 'UTF-8')),
            'data_recebimento' => $c->data_recebimento ? date('d/m/Y', strtotime($c->data_recebimento)) : '—',
            'situacao_recebimento' => $c->situacao_recebimento === 'recebido' ? 'RECEBIDO' : 'NÃO RECEBIDO',
            'tratamento' => CrudHelper::e(mb_strtoupper((string)($c->tratamento ?? ''), 'UTF-8')),
            'itens_html' => $itensHtml,
            'total_peso' => number_format($totalKg, 3, ',', '.').' KG',
            'auto_print_script' => $autoPrint,
        ];
    }

    /**
     * @param array{coleta: EntityColeta, snapshot: mixed, itens: array} $detalhe
     */
    public static function renderHtml(array $detalhe, array $opts = []): string
    {
        return View::render('admin/modules/coletas/mtr_print', self::viewVars($detalhe, $opts));
    }

    /**
     * @param array{coleta: EntityColeta, snapshot: mixed, itens: array} $detalhe
     */
    public static function renderPdfBytes(array $detalhe, array $opts = []): string
    {
        $opts['for_pdf'] = true;
        $opts['auto_print'] = false;
        $html = self::renderHtml($detalhe, $opts);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->setChroot(dirname(__DIR__, 2));
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public static function downloadFilename(EntityColeta $c): string
    {
        $num = ColetaMtrHelper::numeroRelatorioExibicao($c)
            ?? ColetaMtrHelper::numeroExibicao($c)
            ?? $c->id;

        return 'relatorio-coleta-'.$num.'.pdf';
    }

    /**
     * @param array{coleta: EntityColeta, snapshot: mixed, itens: array} $detalhe
     */
    public static function pdfResponse(array $detalhe, array $opts = []): \App\Http\Response
    {
        $bytes = self::renderPdfBytes($detalhe, $opts);
        $filename = self::downloadFilename($detalhe['coleta']);
        $response = new \App\Http\Response(200, $bytes, 'application/pdf');
        $response->addHeader('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->addHeader('Content-Length', (string)strlen($bytes));
        $response->addHeader('Cache-Control', 'private, no-store');

        return $response;
    }
}
