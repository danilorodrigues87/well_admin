<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Plano as EntityPlano;
use App\Utils\View;

class Clientes extends Page
{
    private static function planosOptions(bool $comTodos = false): string
    {
        $html = $comTodos ? '' : '<option value="">— Sem plano —</option>';
        foreach (EntityPlano::getAllActive() as $p) {
            $html .= '<option value="'.$p->id.'">'.CrudHelper::e($p->nome).'</option>';
        }
        return $html;
    }

    public static function index($request): string
    {
        $content = View::render('admin/modules/clientes/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
            'planos_options' => self::planosOptions(true),
            'planos_options_modal' => self::planosOptions(false),
        ]);
        return self::getPage('Clientes', $content, 'clientes', self::crudScripts('/painel/clientes'));
    }

    public static function list($request): string
    {
        $post = $request->getPostVars();
        $page = (int)($post['page'] ?? 1);
        $busca = trim((string)($post['busca'] ?? ''));

        $where = '1=1';
        $params = [];
        $statusFiltro = trim((string)($post['status'] ?? ''));
        if (in_array($statusFiltro, ['ativo', 'suspenso', 'inativo'], true)) {
            $where .= ' AND c.status = ?';
            $params[] = $statusFiltro;
        } else {
            $where .= " AND c.status != 'inativo'";
        }
        $planoId = (int)($post['plano_id'] ?? 0);
        if ($planoId > 0) {
            $where .= ' AND c.plano_id = ?';
            $params[] = $planoId;
        }
        $prioridade = trim((string)($post['prioridade'] ?? ''));
        if (in_array($prioridade, ['normal', 'urgente'], true)) {
            $where .= ' AND c.prioridade = ?';
            $params[] = $prioridade;
        }
        if ($busca !== '') {
            $where .= ' AND (c.nome_fantasia LIKE ? OR c.cnpj LIKE ? OR c.cidade LIKE ?)';
            $params = array_merge($params, array_fill(0, 3, '%'.$busca.'%'));
        }

        $pagination = new Pagination(EntityCliente::count($where, $params), $page, 10);
        $rows = EntityCliente::list($where, $params, $pagination->getLimit());

        $itens = '';
        foreach ($rows as $c) {
            $itens .= '<tr>
                <td>'.CrudHelper::e($c->nome_fantasia).'</td>
                <td>'.CrudHelper::e($c->cnpj).'</td>
                <td>'.CrudHelper::e($c->cidade).'/'.CrudHelper::e($c->uf).'</td>
                <td>'.CrudHelper::e($c->plano_nome).'</td>
                <td>'.CrudHelper::e($c->status).'</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editar('.$c->id.')"><i class="fas fa-edit"></i></button>
                    '.CrudHelper::btnDesativar($c->id).'
                </td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="6" class="text-center text-muted">Nenhum cliente.</td></tr>';
        }

        return self::jsonLista(['success' => true, 'itens' => $itens, 'pagination' => Pagination::renderNav($pagination)]);
    }

    public static function get($request): string
    {
        $id = (int)($request->getPostVars()['id'] ?? 0);
        $c = EntityCliente::getById($id);
        if (!$c) {
            return CrudHelper::jsonError('Cliente não encontrado.');
        }
        return CrudHelper::jsonOk([
            'id' => $c->id,
            'nome_fantasia' => $c->nome_fantasia,
            'razao_social' => $c->razao_social,
            'cnpj' => $c->cnpj,
            'sinir_cod_unidade' => $c->sinir_cod_unidade,
            'email' => $c->email,
            'telefone' => $c->telefone,
            'plano_id' => $c->plano_id,
            'status' => $c->status,
            'logradouro' => $c->logradouro,
            'numero' => $c->numero,
            'bairro' => $c->bairro,
            'cep' => $c->cep,
            'cidade' => $c->cidade,
            'uf' => $c->uf,
            'responsavel' => $c->responsavel,
            'telefone_resp' => $c->telefone_resp,
        ]);
    }

    public static function save($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $id = (int)($post['id'] ?? 0);
        $planoId = (int)($post['plano_id'] ?? 0);

        $data = [
            'nome_fantasia' => trim((string)($post['nome_fantasia'] ?? '')),
            'razao_social' => trim((string)($post['razao_social'] ?? '')),
            'cnpj' => trim((string)($post['cnpj'] ?? '')),
            'sinir_cod_unidade' => ($v = (int)($post['sinir_cod_unidade'] ?? 0)) > 0 ? $v : null,
            'email' => trim((string)($post['email'] ?? '')),
            'telefone' => trim((string)($post['telefone'] ?? '')),
            'plano_id' => $planoId > 0 ? $planoId : null,
            'status' => in_array($post['status'] ?? '', ['ativo', 'suspenso', 'inativo'], true) ? $post['status'] : 'ativo',
            'logradouro' => trim((string)($post['logradouro'] ?? '')),
            'numero' => trim((string)($post['numero'] ?? '')),
            'bairro' => trim((string)($post['bairro'] ?? '')),
            'cep' => trim((string)($post['cep'] ?? '')),
            'cidade' => trim((string)($post['cidade'] ?? '')),
            'uf' => strtoupper(substr(trim((string)($post['uf'] ?? '')), 0, 2)),
            'responsavel' => trim((string)($post['responsavel'] ?? '')),
            'telefone_resp' => trim((string)($post['telefone_resp'] ?? '')),
        ];

        if ($data['nome_fantasia'] === '' || $data['razao_social'] === '') {
            return CrudHelper::jsonError('Nome fantasia e razão social são obrigatórios.');
        }

        if ($id > 0) {
            EntityCliente::update($id, $data);
        } else {
            EntityCliente::insert($data);
        }
        return CrudHelper::jsonOk(['message' => 'Cliente salvo com sucesso.']);
    }

    public static function delete($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        EntityCliente::delete((int)($post['id'] ?? 0));
        return CrudHelper::jsonOk(['message' => 'Cliente desativado.']);
    }
}
