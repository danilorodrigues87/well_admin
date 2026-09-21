<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Rota as EntityRota;
use App\Model\Entity\RotaAtribuicao as EntityRotaAtribuicao;
use App\Utils\View;

class Rotas extends Page
{
    private static function clientesDisponiveisOptionsHtml(int $rotaId): string
    {
        $html = '';
        foreach (EntityRotaAtribuicao::clientesDisponiveis($rotaId) as $c) {
            $label = $c['nome'];
            if ($c['cidade'] !== '') {
                $label .= ' — '.$c['cidade'];
            }
            $html .= '<option value="'.$c['id'].'">'.CrudHelper::e($label).'</option>';
        }

        return $html;
    }

    public static function index($request): string
    {
        $content = View::render('admin/modules/rotas/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
        ]);
        return self::getPage('Rotas', $content, 'rotas', self::crudScripts('/painel/rotas'));
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
            $where .= ' AND (nome LIKE ? OR descricao LIKE ?)';
            $params = array_merge($params, ['%'.$busca.'%', '%'.$busca.'%']);
        }
        $pagination = new Pagination(EntityRota::count($where, $params), $page, 10);
        $rows = EntityRota::list($where, $params, $pagination->getLimit());

        $itens = '';
        foreach ($rows as $r) {
            $stats = EntityRotaAtribuicao::statsByRota($r->id);
            $itens .= '<tr>
                <td>'.CrudHelper::e($r->nome).'</td>
                <td>'.CrudHelper::e($r->descricao).'</td>
                <td class="text-center">'.$stats['total'].'</td>
                <td>
                    <a href="'.URL.'/painel/rotas/atribuicoes/'.$r->id.'" class="btn btn-sm btn-outline-secondary" title="Clientes da rota"><i class="fas fa-users"></i></a>
                    <button class="btn btn-sm btn-outline-primary" onclick="editar('.$r->id.')"><i class="fas fa-edit"></i></button>
                    '.CrudHelper::btnDesativar($r->id).'
                </td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="4" class="text-center text-muted">Nenhuma rota.</td></tr>';
        }

        return self::jsonLista(['success' => true, 'itens' => $itens, 'pagination' => Pagination::renderNav($pagination)]);
    }

    public static function get($request): string
    {
        $id = (int)($request->getPostVars()['id'] ?? 0);
        $r = EntityRota::getById($id);
        if (!$r) {
            return CrudHelper::jsonError('Rota não encontrada.');
        }
        return CrudHelper::jsonOk(['id' => $r->id, 'nome' => $r->nome, 'descricao' => $r->descricao, 'ativo' => $r->ativo]);
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
            'ativo' => CrudHelper::parseAtivo($post, $id <= 0),
        ];
        if ($data['nome'] === '') {
            return CrudHelper::jsonError('Nome da rota é obrigatório.');
        }

        if ($id > 0) {
            EntityRota::update($id, $data);
        } else {
            EntityRota::insert($data);
        }
        return CrudHelper::jsonOk(['message' => 'Salvo com sucesso.']);
    }

    public static function delete($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        EntityRota::delete((int)($post['id'] ?? 0));
        return CrudHelper::jsonOk(['message' => 'Rota desativada.']);
    }

    public static function atribuicoesIndex($request, int $rotaId): string
    {
        $rota = EntityRota::getById($rotaId);
        if (!$rota || !$rota->ativo) {
            return self::getPage('Rota não encontrada', '<div class="alert alert-danger">Rota não encontrada.</div>', 'rotas');
        }
        $stats = EntityRotaAtribuicao::statsByRota($rotaId);
        $content = View::render('admin/modules/rotas/atribuicoes', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
            'rota_id' => $rotaId,
            'rota_nome' => CrudHelper::e($rota->nome),
            'stats_total' => $stats['total'],
            'clientes_options' => self::clientesDisponiveisOptionsHtml($rotaId),
        ]);
        $scripts = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.1/dist/css/tom-select.bootstrap5.min.css">'
            .'<link rel="stylesheet" href="'.URL.'/resources/css/tom-select-well.css?v=20260916">'
            .'<script>window.ROTA_ATRIB = { rotaId: '.$rotaId.', baseUrl: "/painel/rotas/atribuicoes/'.$rotaId.'" };</script>'
            .'<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.1/dist/js/tom-select.complete.min.js"></script>'
            .'<script src="'.URL.'/resources/js/crud-rota-atribuicoes.js?v=20260921"></script>';

        return self::getPage('Atribuições — '.$rota->nome, $content, 'rotas', $scripts);
    }

    public static function listAtribuicoes($request, int $rotaId): string
    {
        $post = $request->getPostVars();
        $page = (int)($post['page'] ?? 1);
        $busca = trim((string)($post['busca'] ?? ''));
        $whereExtra = '';
        $params = [];
        if ($busca !== '') {
            $whereExtra .= ' AND (c.nome_fantasia LIKE ? OR c.cidade LIKE ?)';
            $params = array_merge($params, array_fill(0, 2, '%'.$busca.'%'));
        }

        $pagination = new Pagination(EntityRotaAtribuicao::countByRota($rotaId, $whereExtra, $params), $page, 20);
        $rows = EntityRotaAtribuicao::listByRota($rotaId, $whereExtra, $params, $pagination->getLimit());

        $itens = '';
        foreach ($rows as $a) {
            $itens .= '<tr>
                <td>'.CrudHelper::e($a->cliente_nome).'</td>
                <td>'.CrudHelper::e($a->cliente_cidade).'</td>
                <td><button class="btn btn-sm btn-outline-danger" onclick="removerAtrib('.$a->id.')"><i class="fas fa-trash"></i></button></td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="3" class="text-center text-muted">Nenhum cliente nesta rota.</td></tr>';
        }

        $stats = EntityRotaAtribuicao::statsByRota($rotaId);

        return self::jsonLista([
            'success' => true,
            'itens' => $itens,
            'pagination' => Pagination::renderNav($pagination),
            'stats' => $stats,
        ]);
    }

    public static function saveAtribuicao($request, int $rotaId): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $clienteId = (int)($post['cliente_id'] ?? 0);
        if ($clienteId <= 0) {
            return CrudHelper::jsonError('Selecione um cliente.');
        }
        if (EntityRotaAtribuicao::exists($rotaId, $clienteId)) {
            return CrudHelper::jsonError('Cliente já está nesta rota.');
        }

        EntityRotaAtribuicao::insert($rotaId, $clienteId, null);

        return CrudHelper::jsonOk(['message' => 'Cliente adicionado à rota.']);
    }

    public static function updateAtribuicaoColetor($request, int $rotaId): string
    {
        return CrudHelper::jsonError('Atribuição de coletor por rota foi descontinuada. O coletor escolhe a coleta ao lançar.');
    }

    public static function deleteAtribuicao($request, int $rotaId): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $id = (int)($post['id'] ?? 0);
        $atrib = EntityRotaAtribuicao::getById($id);
        if (!$atrib || $atrib->rota_id !== $rotaId) {
            return CrudHelper::jsonError('Atribuição não encontrada.');
        }

        EntityRotaAtribuicao::delete($id);

        return CrudHelper::jsonOk(['message' => 'Cliente removido da rota.']);
    }

    public static function bulkColetor($request, int $rotaId): string
    {
        return CrudHelper::jsonError('Atribuição de coletor por rota foi descontinuada.');
    }

    public static function clientesDisponiveis($request, int $rotaId): string
    {
        return CrudHelper::jsonOk([
            'options_html' => self::clientesDisponiveisOptionsHtml($rotaId),
        ]);
    }
}
