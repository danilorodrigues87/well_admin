<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\ResiduoClasse as EntityResiduoClasse;
use App\Utils\View;

class ResiduoClasses extends Page
{
    public static function index($request): string
    {
        $content = View::render('admin/modules/residuo_classes/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
        ]);
        return self::getPage('Classes de Resíduo', $content, 'residuo_classes', self::crudScripts('/painel/residuo-classes'));
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
            $where .= ' AND (nome LIKE ? OR slug LIKE ?)';
            $params = array_merge($params, ['%'.$busca.'%', '%'.$busca.'%']);
        }

        $pagination = new Pagination(EntityResiduoClasse::count($where, $params), $page, 15);
        $rows = EntityResiduoClasse::list($where, $params, $pagination->getLimit());

        $itens = '';
        foreach ($rows as $c) {
            $itens .= '<tr>
                <td>'.CrudHelper::e($c->nome).'</td>
                <td><code>'.CrudHelper::e($c->slug).'</code></td>
                <td>'.$c->ordem.'</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editar('.$c->id.')"><i class="fas fa-edit"></i></button>
                    '.CrudHelper::btnDesativar($c->id).'
                </td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="4" class="text-center text-muted">Nenhuma classe cadastrada.</td></tr>';
        }

        return self::jsonLista(['success' => true, 'itens' => $itens, 'pagination' => Pagination::renderNav($pagination)]);
    }

    public static function get($request): string
    {
        $id = (int)($request->getPostVars()['id'] ?? 0);
        $c = EntityResiduoClasse::getById($id);
        if (!$c) {
            return CrudHelper::jsonError('Classe não encontrada.');
        }
        return CrudHelper::jsonOk([
            'id' => $c->id,
            'nome' => $c->nome,
            'slug' => $c->slug,
            'descricao' => $c->descricao,
            'ordem' => $c->ordem,
            'ativo' => $c->ativo,
        ]);
    }

    public static function save($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $id = (int)($post['id'] ?? 0);
        $nome = trim((string)($post['nome'] ?? ''));
        if ($nome === '') {
            return CrudHelper::jsonError('Nome é obrigatório.');
        }

        $slug = trim((string)($post['slug'] ?? ''));
        if ($slug === '') {
            $slug = EntityResiduoClasse::slugFromNome($nome);
        }

        $data = [
            'nome' => $nome,
            'slug' => $slug,
            'descricao' => trim((string)($post['descricao'] ?? '')),
            'ordem' => (int)($post['ordem'] ?? 0),
            'ativo' => CrudHelper::parseAtivo($post, $id <= 0),
        ];

        if ($id > 0) {
            EntityResiduoClasse::update($id, $data);
        } else {
            EntityResiduoClasse::insert($data);
        }
        return CrudHelper::jsonOk(['message' => 'Classe salva com sucesso.']);
    }

    public static function delete($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        EntityResiduoClasse::delete((int)($post['id'] ?? 0));
        return CrudHelper::jsonOk(['message' => 'Classe desativada. Use o filtro "Inativas" para reativar via edição.']);
    }
}
