<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Destinador as EntityDestinador;
use App\Utils\View;

class Destinadores extends Page
{
    public static function index($request): string
    {
        $content = View::render('admin/modules/destinadores/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
        ]);

        return self::getPage('Destinadores', $content, 'destinadores', self::crudScripts('/painel/destinadores'));
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

        $pagination = new Pagination(EntityDestinador::count($where, $params), $page, 10);
        $rows = EntityDestinador::list($where, $params, $pagination->getLimit());

        $itens = '';
        foreach ($rows as $d) {
            $padrao = $d->is_padrao ? ' <span class="badge bg-primary">Padrão</span>' : '';
            $unidade = $d->sinir_cod_unidade ? CrudHelper::e((string)$d->sinir_cod_unidade) : '—';
            $itens .= '<tr>
                <td>'.CrudHelper::e($d->nome).$padrao.'</td>
                <td>'.CrudHelper::e($d->cnpj).'</td>
                <td>'.$unidade.'</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editar('.$d->id.')"><i class="fas fa-edit"></i></button>
                    '.($d->is_padrao ? '' : CrudHelper::btnDesativar($d->id)).'
                </td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="4" class="text-center text-muted">Nenhum destinador.</td></tr>';
        }

        return self::jsonLista(['success' => true, 'itens' => $itens, 'pagination' => Pagination::renderNav($pagination)]);
    }

    public static function get($request): string
    {
        $id = (int)($request->getPostVars()['id'] ?? 0);
        $d = EntityDestinador::getById($id);
        if (!$d) {
            return CrudHelper::jsonError('Destinador não encontrado.');
        }

        return CrudHelper::jsonOk([
            'id' => $d->id,
            'nome' => $d->nome,
            'cnpj' => $d->cnpj,
            'endereco' => $d->endereco,
            'telefone' => $d->telefone,
            'responsavel' => $d->responsavel,
            'sinir_cod_unidade' => $d->sinir_cod_unidade ?? '',
            'is_padrao' => $d->is_padrao,
            'ativo' => $d->ativo,
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
            'endereco' => trim((string)($post['endereco'] ?? '')),
            'telefone' => trim((string)($post['telefone'] ?? '')),
            'responsavel' => trim((string)($post['responsavel'] ?? '')),
            'sinir_cod_unidade' => ($post['sinir_cod_unidade'] ?? '') !== '' ? (int)$post['sinir_cod_unidade'] : null,
            'ativo' => CrudHelper::parseAtivo($post, $id <= 0),
        ];

        if ($data['nome'] === '' || $data['cnpj'] === '') {
            return CrudHelper::jsonError('Nome e CNPJ são obrigatórios.');
        }

        if ($id > 0) {
            $existing = EntityDestinador::getById($id);
            if ($existing && $existing->is_padrao) {
                unset($data['ativo']);
            }
            EntityDestinador::update($id, $data);
        } else {
            $data['is_padrao'] = 0;
            EntityDestinador::insert($data);
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
        $d = EntityDestinador::getById($id);
        if (!$d || $d->is_padrao) {
            return CrudHelper::jsonError('Não é possível desativar o destinador padrão.');
        }
        EntityDestinador::update($id, ['ativo' => 0]);

        return CrudHelper::jsonOk(['message' => 'Destinador desativado.']);
    }
}
