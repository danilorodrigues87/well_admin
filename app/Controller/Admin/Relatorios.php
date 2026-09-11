<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Cliente as EntityCliente;
use App\Service\RelatorioService;
use App\Utils\View;

class Relatorios extends Page
{
    private static function clientesOptions(): string
    {
        $html = '<option value="">Cliente: todos</option>';
        foreach (EntityCliente::list("c.status = 'ativo'", [], '500') as $c) {
            $html .= '<option value="'.$c->id.'">'.CrudHelper::e($c->nome_fantasia).'</option>';
        }

        return $html;
    }

    public static function index($request): string
    {
        $mesInicio = date('Y-m-01');
        $hoje = date('Y-m-d');

        $content = View::render('admin/modules/relatorios/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
            'clientes_options' => self::clientesOptions(),
            'data_inicio_default' => $mesInicio,
            'data_fim_default' => $hoje,
        ]);

        $scripts = self::crudScripts('/painel/relatorios')
            .'<script>window.onCrudListLoaded=function(r){if(r&&r.total!==undefined){document.getElementById("relatorio-total").textContent=r.total+" registro(s) encontrado(s).";}};</script>';

        return self::getPage('Relatórios', $content, 'relatorios', $scripts);
    }

    public static function list($request): string
    {
        $post = $request->getPostVars();
        $page = (int)($post['page'] ?? 1);
        $filtros = [
            'data_inicio' => trim((string)($post['data_inicio'] ?? '')),
            'data_fim' => trim((string)($post['data_fim'] ?? '')),
            'cliente_id' => (int)($post['cliente_id'] ?? 0),
            'status' => trim((string)($post['status'] ?? '')),
        ];

        $result = RelatorioService::coletas($filtros, $page, 20);
        $itens = '';
        foreach ($result['rows'] as $r) {
            $mtr = $r['numero_mtr'] ? '#'.$r['numero_mtr'] : '—';
            $data = $r['data_coleta'] ? date('d/m/Y', strtotime((string)$r['data_coleta'])) : '—';
            $peso = number_format((float)($r['peso_total'] ?? 0), 2, ',', '.');
            $badge = ($r['status'] ?? '') === 'finalizada' ? 'success' : 'warning';
            $itens .= '<tr>
                <td>'.CrudHelper::e((string)$mtr).'</td>
                <td>'.CrudHelper::e((string)($r['cliente_nome'] ?? '')).'</td>
                <td>'.$data.'</td>
                <td>'.CrudHelper::e((string)($r['coletor_nome'] ?? '')).'</td>
                <td>'.$peso.'</td>
                <td><span class="badge bg-'.$badge.'">'.CrudHelper::e((string)($r['status'] ?? '')).'</span></td>
                <td>'.CrudHelper::e((string)($r['situacao_recebimento'] ?? '')).'</td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="7" class="text-center text-muted">Nenhuma coleta no período.</td></tr>';
        }

        /** @var Pagination $pagination */
        $pagination = $result['pagination'];

        return self::jsonLista([
            'success' => true,
            'itens' => $itens,
            'pagination' => Pagination::renderNav($pagination),
            'total' => $result['total'],
        ]);
    }

    public static function exportCsv($request): never
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            http_response_code(400);
            echo $err;
            exit;
        }

        $filtros = [
            'data_inicio' => trim((string)($post['data_inicio'] ?? '')),
            'data_fim' => trim((string)($post['data_fim'] ?? '')),
            'cliente_id' => (int)($post['cliente_id'] ?? 0),
            'status' => trim((string)($post['status'] ?? '')),
        ];

        $csv = RelatorioService::csvColetas($filtros);
        $filename = 'coletas_'.date('Y-m-d_His').'.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        echo "\xEF\xBB\xBF".$csv;
        exit;
    }
}
