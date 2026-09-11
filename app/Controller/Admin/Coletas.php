<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\ColetaItem as EntityColetaItem;
use App\Model\Entity\ColetaSnapshot as EntityColetaSnapshot;
use App\Model\Entity\ColetaEvidencia as EntityColetaEvidencia;
use App\Service\ColetaService;
use App\Service\Sinir\SinirService;
use App\Utils\View;

class Coletas extends Page
{
    public static function index($request): string
    {
        $content = View::render('admin/modules/coletas/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
        ]);
        return self::getPage('Coletas / MTR', $content, 'coletas', self::crudScripts('/painel/coletas'));
    }

    public static function list($request): string
    {
        $post = $request->getPostVars();
        $page = (int)($post['page'] ?? 1);
        $busca = trim((string)($post['busca'] ?? ''));
        $status = trim((string)($post['status'] ?? ''));
        $recebimento = trim((string)($post['situacao_recebimento'] ?? ''));

        $where = "c.status != 'cancelada'";
        $params = [];
        if ($busca !== '') {
            if (ctype_digit($busca)) {
                $where .= ' AND c.numero_mtr = ?';
                $params[] = (int)$busca;
            } else {
                $where .= ' AND c.cliente_id IN (SELECT id FROM clientes WHERE nome_fantasia LIKE ? OR cnpj LIKE ?)';
                $params[] = '%'.$busca.'%';
                $params[] = '%'.$busca.'%';
            }
        }
        if (in_array($status, ['rascunho', 'finalizada'], true)) {
            $where .= ' AND c.status = ?';
            $params[] = $status;
        }
        if (in_array($recebimento, ['pendente', 'recebido'], true)) {
            $where .= ' AND c.situacao_recebimento = ?';
            $params[] = $recebimento;
        }

        $pagination = new Pagination(EntityColeta::count($where, $params), $page, 15);
        $rows = EntityColeta::list($where, $params, $pagination->getLimit());

        $itens = '';
        foreach ($rows as $c) {
            $mtr = $c->numero_mtr ? '#'.$c->numero_mtr : '<span class="text-muted">Rascunho</span>';
            $badge = match ($c->status) {
                'finalizada' => 'success',
                'rascunho' => 'warning',
                default => 'secondary',
            };
            $data = $c->data_coleta ? date('d/m/Y', strtotime($c->data_coleta)) : '—';
            $sinirBadge = SinirService::renderStatusBadge($c->sinir_status, $c->status);
            $itens .= '<tr>
                <td>'.$mtr.'</td>
                <td>'.CrudHelper::e($c->cliente_nome).'</td>
                <td>'.$data.'</td>
                <td>'.CrudHelper::e($c->coletor_nome).'</td>
                <td><span class="badge bg-'.$badge.'">'.CrudHelper::e($c->status).'</span></td>
                <td>'.$sinirBadge.'</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="detalhar('.$c->id.')" title="Detalhe"><i class="fas fa-eye"></i></button>
                    '.($c->status === 'finalizada' && $c->numero_mtr
                        ? '<a class="btn btn-sm btn-outline-secondary" href="'.URL.'/painel/coletas/mtr/'.$c->id.'" target="_blank" title="Imprimir MTR"><i class="fas fa-print"></i></a>'
                        : '').'
                    '.($c->status === 'rascunho' ? '<a class="btn btn-sm btn-outline-warning" href="'.URL.'/painel/coleta/nova/'.$c->id.'" title="Continuar"><i class="fas fa-edit"></i></a>' : '').'
                </td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="7" class="text-center text-muted">Nenhuma coleta encontrada.</td></tr>';
        }

        return self::jsonLista(['success' => true, 'itens' => $itens, 'pagination' => Pagination::renderNav($pagination)]);
    }

    public static function get($request): string
    {
        $id = (int)($request->getPostVars()['id'] ?? 0);
        try {
            $det = ColetaService::detalhar($id);
        } catch (\InvalidArgumentException $e) {
            return CrudHelper::jsonError($e->getMessage());
        }

        $c = $det['coleta'];
        $s = $det['snapshot'];
        $itensHtml = '';
        foreach ($det['itens'] as $i) {
            $itensHtml .= '<tr><td>'.CrudHelper::e($i->nome).'</td><td>'.CrudHelper::e($i->classe_nome).'</td><td>'.number_format($i->quantidade, 3, ',', '.').' '.$i->unidade.'</td></tr>';
        }
        if ($itensHtml === '') {
            $itensHtml = '<tr><td colspan="3" class="text-muted">Sem itens.</td></tr>';
        }

        $evidHtml = '';
        foreach ($det['evidencias'] as $e) {
            $parts = explode('/', $e->arquivo);
            $url = URL.'/storage/coletas/'.(int)($parts[1] ?? 0).'/'.basename($e->arquivo);
            $evidHtml .= '<div class="col-md-4"><a href="'.$url.'" target="_blank"><img src="'.$url.'" class="img-fluid rounded border" alt="Evidência '.$e->ordem.'"/></a></div>';
        }

        $mtrPrintUrl = ($c->status === 'finalizada' && $c->numero_mtr)
            ? URL.'/painel/coletas/mtr/'.$c->id
            : '';

        $mtrPrintBtn = $mtrPrintUrl !== ''
            ? '<a href="'.$mtrPrintUrl.'" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i> Imprimir MTR</a>'
            : '';

        $html = View::render('admin/modules/coletas/detalhe', [
            'mtr_print_btn' => $mtrPrintBtn,
            'numero_mtr' => $c->numero_mtr ? '#'.$c->numero_mtr : 'Rascunho',
            'cliente' => CrudHelper::e($c->cliente_nome),
            'coletor' => CrudHelper::e($c->coletor_nome),
            'data_coleta' => $c->data_coleta ? date('d/m/Y H:i', strtotime($c->data_coleta.' '.($c->hora ?? '00:00:00'))) : '—',
            'status' => CrudHelper::e($c->status),
            'gerador' => CrudHelper::e($s->gerador_nome_fantasia ?? ''),
            'endereco' => CrudHelper::e($s->gerador_endereco ?? ''),
            'transportador' => CrudHelper::e($s->transportador_nome ?? '').' — '.CrudHelper::e($s->transportador_cnpj ?? ''),
            'motorista' => CrudHelper::e($s->motorista_nome ?? ''),
            'veiculo' => CrudHelper::e(($s->veiculo_descricao ?? '').' '.($s->veiculo_placa ?? '')),
            'destinador' => CrudHelper::e($s->destinador_nome ?? ''),
            'tratamento' => CrudHelper::e($c->tratamento ?? ''),
            'relatorio' => nl2br(CrudHelper::e($c->relatorio ?? '')),
            'itens_html' => $itensHtml,
            'evidencias_html' => $evidHtml ?: '<p class="text-muted">Sem evidências.</p>',
        ]);

        return CrudHelper::jsonOk(['html' => $html]);
    }

    public static function mtrPrint($request, int $id): string
    {
        try {
            $det = ColetaService::detalhar($id);
        } catch (\InvalidArgumentException $e) {
            return View::render('erros/404', ['URL' => URL]);
        }

        $c = $det['coleta'];
        $s = $det['snapshot'];
        if ($c->status !== 'finalizada' || !$c->numero_mtr) {
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
            'numero_mtr' => (string)$c->numero_mtr,
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
}
