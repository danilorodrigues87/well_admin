<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Veiculo as EntityVeiculo;
use App\Utils\View;

class Veiculos extends Page
{
    public static function index($request): string
    {
        $content = View::render('admin/modules/veiculos/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
        ]);
        return self::getPage('Veículos', $content, 'veiculos', self::crudScripts('/painel/veiculos'));
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
            $where .= ' AND (placa LIKE ? OR modelo LIKE ? OR marca LIKE ?)';
            $params = array_merge($params, array_fill(0, 3, '%'.$busca.'%'));
        }

        $pagination = new Pagination(EntityVeiculo::count($where, $params), $page, 10);
        $rows = EntityVeiculo::list($where, $params, $pagination->getLimit());

        $itens = '';
        foreach ($rows as $v) {
            $itens .= '<tr>
                <td>'.CrudHelper::e($v->placa).'</td>
                <td>'.CrudHelper::e($v->marca).'</td>
                <td>'.CrudHelper::e($v->modelo).'</td>
                <td>'.CrudHelper::e($v->ano).'</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editar('.$v->id.')"><i class="fas fa-edit"></i></button>
                    '.CrudHelper::btnDesativar($v->id).'
                </td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="5" class="text-center text-muted">Nenhum veículo.</td></tr>';
        }

        return self::jsonLista(['success' => true, 'itens' => $itens, 'pagination' => Pagination::renderNav($pagination)]);
    }

    public static function get($request): string
    {
        $id = (int)($request->getPostVars()['id'] ?? 0);
        $v = EntityVeiculo::getById($id);
        if (!$v) {
            return CrudHelper::jsonError('Veículo não encontrado.');
        }
        return CrudHelper::jsonOk([
            'id' => $v->id, 'placa' => $v->placa, 'marca' => $v->marca,
            'modelo' => $v->modelo, 'ano' => $v->ano, 'cor' => $v->cor, 'ativo' => $v->ativo,
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
            'placa' => strtoupper(trim((string)($post['placa'] ?? ''))),
            'marca' => trim((string)($post['marca'] ?? '')),
            'modelo' => trim((string)($post['modelo'] ?? '')),
            'ano' => trim((string)($post['ano'] ?? '')),
            'cor' => trim((string)($post['cor'] ?? '')),
            'ativo' => CrudHelper::parseAtivo($post, $id <= 0),
        ];
        if ($data['placa'] === '' || $data['marca'] === '' || $data['modelo'] === '') {
            return CrudHelper::jsonError('Placa, marca e modelo são obrigatórios.');
        }

        if ($id > 0) {
            EntityVeiculo::update($id, $data);
        } else {
            EntityVeiculo::insert($data);
        }
        return CrudHelper::jsonOk(['message' => 'Salvo com sucesso.']);
    }

    public static function delete($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        EntityVeiculo::update((int)($post['id'] ?? 0), ['ativo' => 0]);
        return CrudHelper::jsonOk(['message' => 'Veículo desativado.']);
    }
}
