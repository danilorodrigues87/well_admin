<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Common\Helpers\MoneyHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Plano as EntityPlano;
use App\Model\Entity\PlanoItem as EntityPlanoItem;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;
use App\Service\PlanoService;
use App\Utils\View;

class Planos extends Page
{
    private static function tiposResiduosOptions(int $selected = 0): string
    {
        $html = '<option value="">— Resíduo do catálogo (opcional) —</option>';
        foreach (EntityTipoResiduo::list('t.ativo = 1', [], '9999') as $t) {
            $label = $t->nome;
            if ($t->cod_ibama !== '') {
                $label = $t->cod_ibama.' — '.$label;
            }
            $sel = $t->id === $selected ? ' selected' : '';
            $html .= '<option value="'.$t->id.'" data-nome="'.CrudHelper::e($t->nome).'" data-cod="'.CrudHelper::e($t->cod_ibama).'"'.$sel.'>'.CrudHelper::e($label).'</option>';
        }
        return $html;
    }

    public static function index($request): string
    {
        $content = View::render('admin/modules/planos/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
            'tipos_residuos_options' => self::tiposResiduosOptions(),
        ]);
        $scripts = self::crudScripts('/painel/planos')
            . '<script src="'.URL.'/resources/js/crud-planos.js"></script>';
        return self::getPage('Planos', $content, 'planos', $scripts);
    }

    public static function list($request): string
    {
        $post = $request->getPostVars();
        $page = (int)($post['page'] ?? 1);
        $busca = trim((string)($post['busca'] ?? ''));
        $where = '1=1';
        $params = [];
        $ativo = trim((string)($post['ativo'] ?? ''));
        if ($ativo === '1' || $ativo === '0') {
            $where .= ' AND ativo = ?';
            $params[] = (int)$ativo;
        } else {
            $where .= ' AND ativo = 1';
        }
        if ($busca !== '') {
            $where .= ' AND (nome LIKE ? OR tipo LIKE ? OR descricao LIKE ?)';
            $params = array_merge($params, ['%'.$busca.'%', '%'.$busca.'%', '%'.$busca.'%']);
        }
        $pagination = new Pagination(EntityPlano::count($where, $params), $page, 10);
        $rows = EntityPlano::list($where, $params, $pagination->getLimit());

        $itens = '';
        foreach ($rows as $p) {
            $itens .= '<tr>
                <td>'.CrudHelper::e($p->nome).'</td>
                <td class="small">'.CrudHelper::e($p->descricao).'</td>
                <td>'.MoneyHelper::format($p->valor_mensal).'</td>
                <td class="small">'.PlanoService::renderResumoSaldo($p->id).'</td>
                <td class="small">'.PlanoService::renderResumoExcedente($p->id).'</td>
                <td>'.CrudHelper::e($p->tipo).'</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editar('.$p->id.')"><i class="fas fa-edit"></i></button>
                    '.CrudHelper::btnDesativar($p->id).'
                </td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="7" class="text-center text-muted">Nenhum plano.</td></tr>';
        }

        return self::jsonLista(['success' => true, 'itens' => $itens, 'pagination' => Pagination::renderNav($pagination)]);
    }

    public static function get($request): string
    {
        $id = (int)($request->getPostVars()['id'] ?? 0);
        $p = EntityPlano::getById($id);
        if (!$p) {
            return CrudHelper::jsonError('Plano não encontrado.');
        }
        return CrudHelper::jsonOk([
            'id' => $p->id,
            'nome' => $p->nome,
            'descricao' => $p->descricao,
            'valor_mensal' => $p->valor_mensal,
            'coletas_mensais' => $p->coletas_mensais,
            'tipo' => $p->tipo,
            'ativo' => $p->ativo,
            'itens' => PlanoService::serializeItensForForm($p->id),
        ]);
    }

    public static function save($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $id = (int)($post['id'] ?? 0);
        $data = [
            'nome' => trim((string)($post['nome'] ?? '')),
            'descricao' => trim((string)($post['descricao'] ?? '')),
            'valor_mensal' => MoneyHelper::parse((string)($post['valor_mensal'] ?? '0')),
            'coletas_mensais' => (float)str_replace(',', '.', (string)($post['coletas_mensais'] ?? '0')),
            'tipo' => trim((string)($post['tipo'] ?? '')),
            'ativo' => CrudHelper::parseAtivo($post, $id <= 0),
        ];
        if ($data['nome'] === '') {
            return CrudHelper::jsonError('Nome do plano é obrigatório.');
        }

        $itens = PlanoService::enrichItensWithTipoResiduo(
            PlanoService::decodeItensFromPost($post['itens_json'] ?? '')
        );

        if ($id > 0) {
            EntityPlano::update($id, $data);
            EntityPlanoItem::replaceForPlano($id, $itens);
        } else {
            $id = EntityPlano::insert($data);
            EntityPlanoItem::replaceForPlano($id, $itens);
        }
        return CrudHelper::jsonOk(['message' => 'Salvo com sucesso.']);
    }

    public static function delete($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        EntityPlano::delete((int)($post['id'] ?? 0));
        return CrudHelper::jsonOk(['message' => 'Plano desativado.']);
    }
}
