<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\DmrDeclaracao as EntityDmrDeclaracao;
use App\Service\DmrService;
use App\Utils\View;

class Dmr extends Page
{
    private static function clientesOptions(): string
    {
        $html = '<option value="">Selecione o gerador…</option>';
        foreach (EntityCliente::list("c.status = 'ativo'", [], '500') as $c) {
            $html .= '<option value="'.$c->id.'">'.CrudHelper::e($c->nome_fantasia).'</option>';
        }

        return $html;
    }

    public static function index($request): string
    {
        $content = View::render('admin/modules/dmr/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
            'clientes_options' => self::clientesOptions(),
            'competencia_default' => date('Y-m'),
        ]);

        return self::getPage(
            'DMR Geradores',
            $content,
            'dmr',
            self::crudScripts('/painel/dmr')
        );
    }

    public static function list($request): string
    {
        $post = $request->getPostVars();
        $page = (int)($post['page'] ?? 1);
        $clienteId = (int)($post['cliente_id'] ?? 0);
        $competencia = trim((string)($post['competencia'] ?? ''));

        $where = '1=1';
        $params = [];
        if ($clienteId > 0) {
            $where .= ' AND d.cliente_id = ?';
            $params[] = $clienteId;
        }
        $compNorm = DmrService::normalizarCompetencia($competencia);
        if ($compNorm !== null) {
            $where .= ' AND d.competencia = ?';
            $params[] = $compNorm;
        }

        $pagination = new Pagination(EntityDmrDeclaracao::count($where, $params), $page, 15);
        $rows = EntityDmrDeclaracao::list($where, $params, $pagination->getLimit());

        $itens = '';
        foreach ($rows as $d) {
            $badge = $d->status === 'fechada' ? 'success' : 'warning';
            $itens .= '<tr>
                <td>'.CrudHelper::e($d->cliente_nome).'</td>
                <td>'.CrudHelper::e($d->competencia).'</td>
                <td><span class="badge bg-'.$badge.'">'.CrudHelper::e($d->status).'</span></td>
                <td class="text-end">'.$d->tot_coletas.'</td>
                <td class="text-end">'.number_format($d->tot_kg, 3, ',', '.').'</td>
                <td class="text-end">'.$d->tot_com_mtr.'</td>
                <td class="text-end">'.$d->tot_com_cdf.'</td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="dmrDetalhe('.$d->id.')">Ver</button>
                </td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="8" class="text-center text-muted">Nenhuma DMR salva. Gere a partir dos filtros acima.</td></tr>';
        }

        return self::jsonLista([
            'success' => true,
            'itens' => $itens,
            'pagination' => Pagination::renderNav($pagination),
        ]);
    }

    public static function gerar($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        $clienteId = (int)($post['cliente_id'] ?? 0);
        $competencia = trim((string)($post['competencia'] ?? ''));
        $obs = trim((string)($post['observacao'] ?? ''));

        if ($clienteId <= 0) {
            return CrudHelper::jsonError('Selecione o cliente (gerador).');
        }

        $result = DmrService::gerarOuAtualizar($clienteId, $competencia, $obs !== '' ? $obs : null);
        if (!$result['ok']) {
            return CrudHelper::jsonError($result['message']);
        }

        return CrudHelper::jsonOk(['message' => $result['message'], 'id' => $result['id'] ?? null]);
    }

    public static function fechar($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        $id = (int)($post['id'] ?? 0);
        if ($id <= 0) {
            return CrudHelper::jsonError('DMR inválida.');
        }

        $result = DmrService::fechar($id);
        if (!$result['ok']) {
            return CrudHelper::jsonError($result['message']);
        }

        return CrudHelper::jsonOk(['message' => $result['message']]);
    }

    public static function detalhe($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        $id = (int)($post['id'] ?? 0);
        $decl = EntityDmrDeclaracao::getById($id);
        if (!$decl) {
            return CrudHelper::jsonError('DMR não encontrada.');
        }

        $agg = DmrService::agregarColetas($decl->cliente_id, $decl->competencia);
        $linhas = '';
        foreach ($agg['linhas'] as $l) {
            $linhas .= '<tr>
                <td>#'.(int)$l['id'].'</td>
                <td>'.($l['numero_relatorio'] ? '#'.(int)$l['numero_relatorio'] : '—').'</td>
                <td>'.($l['numero_mtr'] ? '#'.(int)$l['numero_mtr'] : '—').'</td>
                <td>'.($l['data_coleta'] ? date('d/m/Y', strtotime((string)$l['data_coleta'])) : '—').'</td>
                <td class="text-end">'.number_format((float)$l['peso_kg'], 3, ',', '.').'</td>
                <td>'.($l['tem_mtr'] ? 'Sim' : 'Não').'</td>
                <td>'.CrudHelper::e((string)($l['sinir_cdf_codigo'] ?: ($l['cdf_tipo'] ?? '—'))).'</td>
            </tr>';
        }
        if ($linhas === '') {
            $linhas = '<tr><td colspan="7" class="text-muted text-center">Nenhuma coleta finalizada no período.</td></tr>';
        }

        $fecharBtn = $decl->status === 'rascunho'
            ? '<button type="button" class="btn btn-sm btn-success" onclick="dmrFechar('.$decl->id.')">Fechar DMR</button>'
            : '';

        $html = '<p><strong>Cliente:</strong> '.CrudHelper::e($decl->cliente_nome).'</p>
            <p><strong>Competência:</strong> '.CrudHelper::e($decl->competencia).' · <strong>Status:</strong> '.CrudHelper::e($decl->status).'</p>
            <p class="small text-muted">Apoio à declaração do gerador — lançamento no portal SINIR continua manual até integração futura.</p>
            <div class="mb-2">'.$fecharBtn.'
            <a class="btn btn-sm btn-outline-success" href="'.URL.'/painel/dmr/export?id='.$decl->id.'">Exportar CSV</a></div>
            <table class="table table-sm"><thead><tr>
                <th>Coleta</th><th>Relatório</th><th>MTR</th><th>Data</th><th>Kg</th><th>MTR ok</th><th>CDF</th>
            </tr></thead><tbody>'.$linhas.'</tbody></table>';

        return CrudHelper::jsonOk(['html' => $html]);
    }

    public static function exportCsv($request): void
    {
        $id = (int)($request->getQueryParams()['id'] ?? 0);
        $decl = EntityDmrDeclaracao::getById($id);
        if (!$decl) {
            http_response_code(404);
            echo 'DMR não encontrada';

            return;
        }

        $agg = DmrService::agregarColetas($decl->cliente_id, $decl->competencia);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="dmr-'.$decl->cliente_id.'-'.$decl->competencia.'.csv"');
        $out = fopen('php://output', 'w');
        if ($out === false) {
            return;
        }
        fputcsv($out, ['coleta_id', 'numero_relatorio', 'numero_mtr', 'data_coleta', 'peso_kg', 'tem_mtr', 'cdf_tipo', 'sinir_cdf_codigo'], ';');
        foreach ($agg['linhas'] as $l) {
            fputcsv($out, [
                $l['id'],
                $l['numero_relatorio'] ?? '',
                $l['numero_mtr'] ?? '',
                $l['data_coleta'] ?? '',
                $l['peso_kg'],
                $l['tem_mtr'] ? '1' : '0',
                $l['cdf_tipo'] ?? '',
                $l['sinir_cdf_codigo'] ?? '',
            ], ';');
        }
        fclose($out);
        exit;
    }
}
