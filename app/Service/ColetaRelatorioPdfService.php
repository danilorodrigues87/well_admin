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
     * @param array{coleta: EntityColeta, snapshot: mixed, itens: array, evidencias?: array} $detalhe
     * @param array{auto_print?: bool, rotulo?: string, status_aviso?: bool, for_pdf?: bool, pdf_download_url?: string, evidencia_img_url?: callable(int,int,string):string} $opts
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

        $evidenciasHtml = self::buildEvidenciasHtml(
            $detalhe['evidencias'] ?? [],
            (int)$c->id,
            $forPdf,
            $opts
        );

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
            'situacao_recebimento' => !empty($c->sinir_recebido_em) ? 'RECEBIDO NO SINIR' : 'AGUARDANDO SINIR',
            'tratamento' => CrudHelper::e(mb_strtoupper((string)($c->tratamento ?? ''), 'UTF-8')),
            'itens_html' => $itensHtml,
            'total_peso' => number_format($totalKg, 3, ',', '.').' KG',
            'evidencias_html' => $evidenciasHtml,
            'auto_print_script' => $autoPrint,
        ];
    }

    /**
     * @param iterable<\App\Model\Entity\ColetaEvidencia> $evidencias
     * @param array{evidencia_img_url?: callable(int,int,string):string} $opts
     */
    private static function buildEvidenciasHtml(iterable $evidencias, int $coletaId, bool $forPdf, array $opts = []): string
    {
        $items = '';
        $imgUrlFn = $opts['evidencia_img_url'] ?? null;
        foreach ($evidencias as $e) {
            $coletaIdEv = (int)($e->coleta_id ?: $coletaId);
            $ordem = (int)$e->ordem;
            $arquivo = (string)$e->arquivo;
            if ($forPdf) {
                $src = EvidenceStorageService::dataUriForEmbed(
                    $arquivo,
                    $coletaIdEv,
                    (string)($e->mime ?? '')
                );
            } else {
                if (!EvidenceStorageService::fileExists($arquivo, $coletaIdEv)) {
                    continue;
                }
                if (is_callable($imgUrlFn)) {
                    $src = (string)$imgUrlFn($coletaIdEv, $ordem, $arquivo);
                } else {
                    $src = EvidenceStorageService::publicUrl($coletaIdEv, $arquivo);
                }
            }
            if ($src === null || $src === '') {
                continue;
            }

            $items .= '<div class="mtr-evidencia-item">'
                .'<img src="'.CrudHelper::e($src).'" alt="Evidência '.$ordem.'" />'
                .'<div class="mtr-evidencia-caption">Foto '.$ordem.'</div>'
                .'</div>';
        }

        if ($items === '') {
            return '';
        }

        return '<tr><td colspan="15" style="border:none;height:6px;"></td></tr>'
            .'<tr class="mtr-section"><td colspan="15">EVIDÊNCIAS</td></tr>'
            .'<tr><td colspan="15" class="mtr-evidencias-cell"><div class="mtr-evidencias">'.$items.'</div></td></tr>';
    }

    /**
     * @param array{coleta: EntityColeta, snapshot: mixed, itens: array, evidencias?: array} $detalhe
     */
    public static function renderHtml(array $detalhe, array $opts = []): string
    {
        return View::render('admin/modules/coletas/mtr_print', self::viewVars($detalhe, $opts));
    }

    /**
     * @param array{coleta: EntityColeta, snapshot: mixed, itens: array, evidencias?: array} $detalhe
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
     * @param array{coleta: EntityColeta, snapshot: mixed, itens: array, evidencias?: array} $detalhe
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

    /**
     * Comprovante estreito (80 mm) para o compartilhamento/impressão do celular.
     *
     * @param array{coleta: EntityColeta, snapshot: mixed, itens: array, evidencias?: array} $detalhe
     */
    public static function comprovanteResponse(array $detalhe): \App\Http\Response
    {
        $bytes = self::renderComprovantePdfBytes($detalhe);
        $filename = self::comprovanteFilename($detalhe['coleta']);
        $response = new \App\Http\Response(200, $bytes, 'application/pdf');
        $response->addHeader('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->addHeader('Content-Length', (string)strlen($bytes));
        $response->addHeader('Cache-Control', 'private, no-store');

        return $response;
    }

    public static function comprovanteFilename(EntityColeta $c): string
    {
        $num = ColetaMtrHelper::numeroRelatorioExibicao($c) ?? $c->id;

        return 'comprovante-'.$num.'.pdf';
    }

    /**
     * @param array{coleta: EntityColeta, snapshot: mixed, itens: array} $detalhe
     */
    public static function renderComprovantePdfBytes(array $detalhe): string
    {
        $c = $detalhe['coleta'];
        $s = $detalhe['snapshot'];
        $marca = \App\Common\CompanyConfig::name((int)($c->operadora_id ?? 0));
        $cliente = (string)($s?->gerador_nome_fantasia ?? $c->cliente_nome ?? '');
        $numero = ColetaMtrHelper::numeroRelatorioExibicao($c) ?? (string)$c->id;
        $data = $c->data_coleta ? date('d/m/Y', strtotime((string)$c->data_coleta)) : '—';
        $hora = $c->hora ? substr((string)$c->hora, 0, 5) : '';
        $quando = trim($data.' '.$hora);

        $linhas = '';
        $alturaMm = 78 + (int)ceil(max(1, mb_strlen($cliente)) / 24) * 5;
        foreach ($detalhe['itens'] as $i) {
            $qtd = number_format((float)$i->quantidade, 3, ',', '.').' '.strtoupper((string)$i->unidade);
            $nome = (string)$i->nome;
            $linhas .= '<tr><td>'.CrudHelper::e($nome).'</td><td class="qtd">'.CrudHelper::e($qtd).'</td></tr>';
            $alturaMm += 8 + 4 * max(0, (int)ceil(mb_strlen($nome) / 22) - 1);
        }
        if ($linhas === '') {
            $linhas = '<tr><td colspan="2">Sem resíduos</td></tr>';
            $alturaMm += 8;
        }
        $alturaMm = max(120, $alturaMm);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .'@page{margin:8px;}'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#111;margin:0;}'
            .'h1{font-size:13px;text-align:center;margin:0 0 2px;}'
            .'.marca{text-align:center;font-size:9px;margin:0 0 6px;}'
            .'.linha{border-top:1px dashed #333;margin:6px 0;}'
            .'.rotulo{font-size:8px;color:#444;margin:0;}'
            .'.valor{font-size:11px;font-weight:bold;margin:0 0 4px;}'
            .'table{width:100%;border-collapse:collapse;}'
            .'td{padding:2px 0;vertical-align:top;}'
            .'.qtd{text-align:right;white-space:nowrap;padding-left:6px;}'
            .'.rodape{text-align:center;font-size:8px;margin:8px 0 0;}'
            .'</style></head><body>'
            .'<h1>COMPROVANTE</h1>'
            .'<p class="marca">'.CrudHelper::e($marca).'</p>'
            .'<div class="linha"></div>'
            .'<p class="rotulo">Relatório</p><p class="valor">'.CrudHelper::e($numero).'</p>'
            .'<p class="rotulo">Cliente</p><p class="valor">'.CrudHelper::e($cliente !== '' ? $cliente : '—').'</p>'
            .'<p class="rotulo">Data</p><p class="valor">'.CrudHelper::e($quando).'</p>'
            .'<div class="linha"></div>'
            .'<p class="rotulo">Resíduos</p>'
            .'<table>'.$linhas.'</table>'
            .'<div class="linha"></div>'
            .'<p class="rodape">'.CrudHelper::e($marca).'</p>'
            .'</body></html>';

        $larguraPt = 80 * 72 / 25.4;
        $alturaPt = $alturaMm * 72 / 25.4;
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper([0, 0, $larguraPt, $alturaPt]);
        $dompdf->render();

        return $dompdf->output();
    }
}
