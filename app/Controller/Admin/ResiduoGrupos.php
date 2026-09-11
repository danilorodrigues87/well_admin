<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\ResiduoClasse as EntityResiduoClasse;
use App\Model\Entity\ResiduoGrupo as EntityResiduoGrupo;
use App\Utils\View;

class ResiduoGrupos extends Page
{
    private static function classesOptions(int $selected = 0, string $emptyLabel = '— Selecione —'): string
    {
        $html = '<option value="">'.CrudHelper::e($emptyLabel).'</option>';
        foreach (EntityResiduoClasse::getAllActive() as $c) {
            $sel = $c->id === $selected ? ' selected' : '';
            $html .= '<option value="'.$c->id.'"'.$sel.'>'.CrudHelper::e($c->nome).'</option>';
        }
        return $html;
    }

    public static function index($request): string
    {
        $content = View::render('admin/modules/residuo_grupos/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
            'classes_options' => self::classesOptions(),
            'classes_filter_options' => self::classesOptions(0, 'Classe: todas'),
        ]);
        return self::getPage('Grupos de Resíduo', $content, 'residuo_grupos', self::crudScripts('/painel/residuo-grupos'));
    }

    public static function list($request): string
    {
        $post = $request->getPostVars();
        $page = (int)($post['page'] ?? 1);
        $busca = trim((string)($post['busca'] ?? ''));
        $where = 'g.ativo = 1';
        $params = [];
        $classeId = (int)($post['classe_id'] ?? 0);
        if ($classeId > 0) {
            $where .= ' AND g.classe_id = ?';
            $params[] = $classeId;
        }
        if ($busca !== '') {
            $where .= ' AND (g.codigo LIKE ? OR g.nome LIKE ? OR c.nome LIKE ?)';
            $params = array_merge($params, array_fill(0, 3, '%'.$busca.'%'));
        }

        $pagination = new Pagination(EntityResiduoGrupo::count($where, $params), $page, 15);
        $rows = EntityResiduoGrupo::list($where, $params, $pagination->getLimit());

        $itens = '';
        foreach ($rows as $g) {
            $itens .= '<tr>
                <td>'.CrudHelper::e($g->classe_nome).'</td>
                <td><strong>'.CrudHelper::e($g->codigo).'</strong></td>
                <td>'.CrudHelper::e($g->nome).'</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editar('.$g->id.')"><i class="fas fa-edit"></i></button>
                    '.CrudHelper::btnDesativar($g->id).'
                </td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="4" class="text-center text-muted">Nenhum grupo cadastrado.</td></tr>';
        }

        return self::jsonLista(['success' => true, 'itens' => $itens, 'pagination' => Pagination::renderNav($pagination)]);
    }

    public static function get($request): string
    {
        $id = (int)($request->getPostVars()['id'] ?? 0);
        $g = EntityResiduoGrupo::getById($id);
        if (!$g) {
            return CrudHelper::jsonError('Grupo não encontrado.');
        }
        return CrudHelper::jsonOk([
            'id' => $g->id,
            'classe_id' => $g->classe_id,
            'codigo' => $g->codigo,
            'nome' => $g->nome,
            'descricao' => $g->descricao,
            'classes_options' => self::classesOptions($g->classe_id),
            'ativo' => $g->ativo,
        ]);
    }

    public static function save($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $id = (int)($post['id'] ?? 0);
        $classeId = (int)($post['classe_id'] ?? 0);
        $codigo = strtoupper(trim((string)($post['codigo'] ?? '')));
        if ($classeId <= 0 || $codigo === '') {
            return CrudHelper::jsonError('Classe e código são obrigatórios.');
        }

        $data = [
            'classe_id' => $classeId,
            'codigo' => $codigo,
            'nome' => trim((string)($post['nome'] ?? '')),
            'descricao' => trim((string)($post['descricao'] ?? '')),
            'ativo' => CrudHelper::parseAtivo($post, $id <= 0),
        ];

        if ($id > 0) {
            EntityResiduoGrupo::update($id, $data);
        } else {
            EntityResiduoGrupo::insert($data);
        }
        return CrudHelper::jsonOk(['message' => 'Grupo salvo com sucesso.']);
    }

    public static function delete($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        EntityResiduoGrupo::delete((int)($post['id'] ?? 0));
        return CrudHelper::jsonOk(['message' => 'Grupo desativado.']);
    }

}
