<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Transportadora as EntityTransportadora;
use App\Utils\View;

class Transportadoras extends Page
{
    public static function index($request): string
    {
        $content = View::render('admin/modules/transportadoras/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
        ]);

        return self::getPage('Transportadoras', $content, 'transportadoras', self::crudScripts('/painel/transportadoras'));
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
            $where .= ' AND (nome LIKE ? OR cnpj LIKE ?)';
            $params = array_merge($params, ['%'.$busca.'%', '%'.$busca.'%']);
        }

        $pagination = new Pagination(EntityTransportadora::count($where, $params), $page, 10);
        $rows = EntityTransportadora::list($where, $params, $pagination->getLimit());

        $itens = '';
        foreach ($rows as $t) {
            $padrao = $t->is_padrao ? ' <span class="badge bg-primary">Padrão</span>' : '';
            $unidade = $t->sinir_cod_unidade ? CrudHelper::e((string)$t->sinir_cod_unidade) : '—';
            $itens .= '<tr>
                <td>'.CrudHelper::e($t->nome).$padrao.'</td>
                <td>'.CrudHelper::e($t->cnpj).'</td>
                <td>'.$unidade.'</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editar('.$t->id.')"><i class="fas fa-edit"></i></button>
                    '.($t->is_padrao ? '' : CrudHelper::btnDesativar($t->id)).'
                </td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="4" class="text-center text-muted">Nenhuma transportadora.</td></tr>';
        }

        return self::jsonLista(['success' => true, 'itens' => $itens, 'pagination' => Pagination::renderNav($pagination)]);
    }

    public static function get($request): string
    {
        $id = (int)($request->getPostVars()['id'] ?? 0);
        $t = EntityTransportadora::getById($id);
        if (!$t) {
            return CrudHelper::jsonError('Transportadora não encontrada.');
        }

        return CrudHelper::jsonOk([
            'id' => $t->id,
            'nome' => $t->nome,
            'cnpj' => $t->cnpj,
            'sinir_cod_unidade' => $t->sinir_cod_unidade ?? '',
            'sinir_integration_token' => $t->sinir_integration_token ?? '',
            'is_padrao' => $t->is_padrao,
            'ativo' => $t->ativo,
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
            'cnpj' => trim((string)($post['cnpj'] ?? '')),
            'sinir_cod_unidade' => ($post['sinir_cod_unidade'] ?? '') !== '' ? (int)$post['sinir_cod_unidade'] : null,
            'ativo' => CrudHelper::parseAtivo($post, $id <= 0),
        ];
        $token = trim((string)($post['sinir_integration_token'] ?? ''));
        if ($token !== '') {
            $data['sinir_integration_token'] = $token;
        }

        if ($data['nome'] === '' || $data['cnpj'] === '') {
            return CrudHelper::jsonError('Nome e CNPJ são obrigatórios.');
        }

        if ($id > 0) {
            $existing = EntityTransportadora::getById($id);
            if ($existing && $existing->is_padrao) {
                unset($data['ativo']);
            }
            EntityTransportadora::update($id, $data);
        } else {
            $data['is_padrao'] = 0;
            EntityTransportadora::insert($data);
        }

        return CrudHelper::jsonOk(['message' => 'Salvo com sucesso.']);
    }

    public static function delete($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        $id = (int)($post['id'] ?? 0);
        $t = EntityTransportadora::getById($id);
        if (!$t || $t->is_padrao) {
            return CrudHelper::jsonError('Não é possível desativar a transportadora padrão.');
        }
        EntityTransportadora::update($id, ['ativo' => 0]);

        return CrudHelper::jsonOk(['message' => 'Transportadora desativada.']);
    }
}
