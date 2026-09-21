<?php

namespace App\Controller\Gerador;

use App\Common\GeradorScope;
use App\Common\Helpers\ColetaMtrHelper;
use App\Common\Helpers\CrudHelper;
use App\Common\Helpers\FormatHelper;
use App\Http\Response;
use App\Model\Entity\ColetaEvidencia as EntityColetaEvidencia;
use App\Service\ColetaService;
use App\Service\GeradorPortalService;
use App\Utils\View;

class Coletas extends Page
{
    public static function index($request): string
    {
        $query = $request->getQueryParams();
        $page = max(1, (int)($query['page'] ?? 1));
        $statusFiltro = trim((string)($query['status'] ?? ''));
        $result = GeradorPortalService::listColetas($page, 20, $statusFiltro !== '' ? $statusFiltro : null);

        $rows = '';
        foreach ($result['items'] as $c) {
            $mtr = !empty($c['mtr_disponivel']) && !empty($c['numero_mtr'])
                ? '<span class="fw-semibold">'.CrudHelper::e((string)$c['numero_mtr']).'</span>'
                : '<span class="text-muted">'.CrudHelper::e((string)($c['mtr_rotulo'] ?? '—')).'</span>';
            $rows .= '<tr>
                <td>'.$mtr.'</td>
                <td>'.FormatHelper::dateBr((string)($c['data_coleta'] ?? '')).'</td>
                <td>'.FormatHelper::horaBr((string)($c['hora'] ?? '')).'</td>
                <td>'.FormatHelper::statusColetaBadge((string)($c['status'] ?? '')).'</td>
                <td>'.FormatHelper::situacaoRecebimentoBadge((string)($c['situacao_recebimento'] ?? 'pendente')).'</td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="'.URL.'/gerador/coletas/'.(int)$c['id'].'">
                        <i class="fas fa-eye me-1"></i> Detalhes
                    </a>
                </td>
            </tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="text-muted text-center py-4">Nenhuma coleta encontrada.</td></tr>';
        }

        $content = View::render('gerador/coletas/index', [
            'rows' => $rows,
            'pagination_nav' => self::paginationNav(
                (int)$result['pagination']['page'],
                (int)$result['pagination']['pages'],
                '/gerador/coletas',
                $statusFiltro !== '' ? ['status' => $statusFiltro] : []
            ),
            'status_options' => self::statusFilterOptions($statusFiltro),
            'total_page' => count($result['items']),
        ]);

        return self::render('Minhas coletas', $content, 'coletas');
    }

    public static function show($request, int $id): string
    {
        $detalhe = GeradorPortalService::coletaDetalhe($id);
        if ($detalhe === null) {
            $request->getRouter()->redirect('/gerador/coletas');
        }

        $coleta = $detalhe['coleta'];
        $snapshot = $detalhe['snapshot'] ?? null;

        $itensHtml = '';
        foreach ($detalhe['itens'] as $item) {
            $itensHtml .= '<tr>
                <td>'.CrudHelper::e((string)$item['nome']).'</td>
                <td>'.CrudHelper::e((string)($item['classe_nome'] ?? '—')).'</td>
                <td class="text-end fw-semibold">'.FormatHelper::quantidade((float)$item['quantidade'], (string)$item['unidade']).'</td>
            </tr>';
        }
        if ($itensHtml === '') {
            $itensHtml = '<tr><td colspan="3" class="text-muted text-center py-3">Sem itens registrados.</td></tr>';
        }

        $mtrLink = !empty($coleta['mtr_disponivel'])
            ? '<a class="btn btn-primary" target="_blank" href="'.URL.'/gerador/coletas/'.$id.'/mtr"><i class="fas fa-print me-1"></i> Imprimir MTR</a>'
            : '<span class="text-muted small">MTR disponível após registro no SINIR.</span>';

        $evidenciasHtml = '';
        foreach ($detalhe['evidencias'] ?? [] as $ev) {
            $ordem = (int)($ev['ordem'] ?? 0);
            if ($ordem <= 0) {
                continue;
            }
            $url = CrudHelper::e(URL.'/gerador/coletas/'.$id.'/evidencias/'.$ordem);
            $evidenciasHtml .= '<div class="col-sm-6 col-lg-4 mb-3">
                <a href="'.$url.'" target="_blank" class="d-block card shadow-sm overflow-hidden text-decoration-none h-100">
                    <img src="'.$url.'" alt="Evidência '.$ordem.'" class="img-fluid" style="object-fit:cover;min-height:160px;max-height:220px;width:100%" loading="lazy"/>
                    <div class="card-footer small py-2 text-center text-muted">Evidência #'.$ordem.'</div>
                </a>
            </div>';
        }
        if ($evidenciasHtml === '') {
            $evidenciasHtml = '<p class="text-muted mb-0">Nenhuma evidência registrada para esta coleta.</p>';
        } else {
            $evidenciasHtml = '<div class="row g-3">'.$evidenciasHtml.'</div>';
        }

        $relatorio = trim((string)($coleta['relatorio'] ?? ''));
        $relatorioHtml = $relatorio !== ''
            ? '<div class="card shadow-sm mb-4"><div class="card-header bg-transparent">Relatório da coleta</div><div class="card-body">'.nl2br(CrudHelper::e($relatorio)).'</div></div>'
            : '';

        $transportadorHtml = '';
        if (is_array($snapshot)) {
            $transportadorHtml = '<div class="col-md-6"><div class="small text-muted">Transportador</div><div>'.CrudHelper::e((string)($snapshot['transportador_nome'] ?? '—')).'</div></div>
                <div class="col-md-6"><div class="small text-muted">Motorista</div><div>'.CrudHelper::e((string)($snapshot['motorista_nome'] ?? '—')).'</div></div>
                <div class="col-md-6"><div class="small text-muted">Destinador</div><div>'.CrudHelper::e((string)($snapshot['destinador_nome'] ?? '—')).'</div></div>
                <div class="col-md-6"><div class="small text-muted">Tratamento</div><div>'.CrudHelper::e((string)($coleta['tratamento'] ?? '—')).'</div></div>';
        }

        $content = View::render('gerador/coletas/show', [
            'numero_mtr' => CrudHelper::e(
                !empty($coleta['mtr_disponivel']) && !empty($coleta['numero_mtr'])
                    ? (string)$coleta['numero_mtr']
                    : (!empty($coleta['status']) && $coleta['status'] === 'finalizada'
                        ? 'Aguard. registro SINIR'
                        : '—')
            ),
            'data_coleta' => FormatHelper::dateBr((string)($coleta['data_coleta'] ?? '')),
            'hora' => FormatHelper::horaBr((string)($coleta['hora'] ?? '')),
            'status_badge' => FormatHelper::statusColetaBadge((string)($coleta['status'] ?? '')),
            'recebimento_badge' => FormatHelper::situacaoRecebimentoBadge((string)($coleta['situacao_recebimento'] ?? 'pendente')),
            'data_recebimento' => FormatHelper::dateBr((string)($coleta['data_recebimento'] ?? '')),
            'itens_rows' => $itensHtml,
            'mtr_link' => $mtrLink,
            'evidencias_html' => $evidenciasHtml,
            'relatorio_html' => $relatorioHtml,
            'transportador_html' => $transportadorHtml,
            'coleta_id' => $id,
        ]);

        return self::render('Coleta #'.$id, $content, 'coletas');
    }

    public static function evidencia($request, int $id, int $ordem): Response
    {
        if (!GeradorScope::pertenceColeta($id)) {
            return new Response(404, View::render('erros/404', ['URL' => URL]));
        }

        $match = EntityColetaEvidencia::getByColetaOrdem($id, $ordem);
        if ($match === null) {
            return new Response(404, View::render('erros/404', ['URL' => URL]));
        }

        $path = dirname(__DIR__, 3).'/storage/'.$match->arquivo;
        if (!is_readable($path)) {
            return new Response(404, View::render('erros/404', ['URL' => URL]));
        }

        return new Response(200, file_get_contents($path), (string)$match->mime);
    }

    public static function mtr($request, int $id): string
    {
        if (!GeradorPortalService::coletaDetalhe($id)) {
            $request->getRouter()->redirect('/gerador/coletas');
        }

        try {
            $det = ColetaService::detalhar($id);
        } catch (\InvalidArgumentException) {
            return View::render('erros/404', ['URL' => URL]);
        }

        $c = $det['coleta'];
        $s = $det['snapshot'];
        if (!ColetaMtrHelper::temMtr($c)) {
            return View::render('erros/405', ['URL' => URL]);
        }

        $itensHtml = '';
        $totalKg = 0.0;
        foreach ($det['itens'] as $i) {
            $qtd = number_format($i->quantidade, 3, ',', '.').' '.strtoupper($i->unidade);
            $itensHtml .= '<tr><td>'.CrudHelper::e($i->nome).'</td><td style="text-align:right;">'.CrudHelper::e($qtd).'</td></tr>';
            if ($i->unidade === 'kg') {
                $totalKg += (float)$i->quantidade;
            }
        }
        if ($itensHtml === '') {
            $itensHtml = '<tr><td colspan="2" class="text-muted">Sem itens registrados.</td></tr>';
        }

        $autoPrint = ($request->getQueryParams()['print'] ?? '') === '1'
            ? '<script>window.addEventListener("load", function () { window.print(); });</script>'
            : '';

        return View::render('admin/modules/coletas/mtr_print', [
            'URL' => URL,
            'numero_mtr' => (string)(ColetaMtrHelper::numeroExibicao($c) ?? ''),
            'gerador_nome' => CrudHelper::e(mb_strtoupper((string)($s->gerador_nome_fantasia ?? ''), 'UTF-8')),
            'gerador_cnpj' => CrudHelper::e($s->gerador_cnpj ?? ''),
            'gerador_plano' => CrudHelper::e(mb_strtoupper((string)($s->gerador_plano ?? ''), 'UTF-8')),
            'gerador_endereco' => CrudHelper::e(mb_strtoupper((string)($s->gerador_endereco ?? ''), 'UTF-8')),
            'gerador_responsavel' => CrudHelper::e(mb_strtoupper((string)($s->gerador_responsavel ?? ''), 'UTF-8')),
            'doc_referencia' => $c->doc_referencia ? date('d/m/Y', strtotime($c->doc_referencia)) : '—',
            'data_coleta' => $c->data_coleta ? date('d/m/Y', strtotime($c->data_coleta)) : '—',
            'hora' => $c->hora ? substr((string)$c->hora, 0, 5) : '',
            'relatorio' => nl2br(CrudHelper::e($c->relatorio ?? '')),
            'transportador_nome' => CrudHelper::e(mb_strtoupper((string)($s->transportador_nome ?? ''), 'UTF-8')),
            'transportador_cnpj' => CrudHelper::e($s->transportador_cnpj ?? ''),
            'motorista_nome' => CrudHelper::e(mb_strtoupper((string)($s->motorista_nome ?? ''), 'UTF-8')),
            'veiculo_descricao' => CrudHelper::e(mb_strtoupper((string)($s->veiculo_descricao ?? ''), 'UTF-8')),
            'veiculo_placa' => CrudHelper::e(mb_strtoupper((string)($s->veiculo_placa ?? ''), 'UTF-8')),
            'destinador_nome' => CrudHelper::e(mb_strtoupper((string)($s->destinador_nome ?? ''), 'UTF-8')),
            'destinador_cnpj' => CrudHelper::e($s->destinador_cnpj ?? ''),
            'destinador_endereco' => CrudHelper::e(mb_strtoupper((string)($s->destinador_endereco ?? ''), 'UTF-8')),
            'destinador_telefone' => CrudHelper::e($s->destinador_telefone ?? ''),
            'destinador_responsavel' => CrudHelper::e(mb_strtoupper((string)($s->destinador_responsavel ?? ''), 'UTF-8')),
            'data_recebimento' => $c->data_recebimento ? date('d/m/Y', strtotime($c->data_recebimento)) : '—',
            'situacao_recebimento' => $c->situacao_recebimento === 'recebido' ? 'RECEBIDO' : 'NÃO RECEBIDO',
            'tratamento' => CrudHelper::e(mb_strtoupper((string)($c->tratamento ?? ''), 'UTF-8')),
            'itens_html' => $itensHtml,
            'total_peso' => number_format($totalKg, 3, ',', '.').' KG',
            'auto_print_script' => $autoPrint,
        ]);
    }

    private static function statusFilterOptions(string $current): string
    {
        $options = [
            '' => 'Todos os status',
            'finalizada' => 'Finalizadas',
            'rascunho' => 'Rascunho',
        ];
        $html = '';
        foreach ($options as $value => $label) {
            $selected = $value === $current ? ' selected' : '';
            $html .= '<option value="'.CrudHelper::e($value).'"'.$selected.'>'.CrudHelper::e($label).'</option>';
        }

        return $html;
    }
}
