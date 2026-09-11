<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Rota as EntityRota;
use App\Model\Entity\RotaAtribuicao as EntityRotaAtribuicao;
use App\Model\Entity\Usuario as EntityUsuario;
use App\Utils\View;

class Rotas extends Page
{
    private static function coletoresOptions(int $selected = 0, bool $emptyLabel = true): string
    {
        $html = $emptyLabel ? '<option value="">— Sem coletor —</option>' : '';
        foreach (EntityUsuario::getColetoresAtivos() as $c) {
            $sel = $c->id === $selected ? ' selected' : '';
            $html .= '<option value="'.$c->id.'"'.$sel.'>'.CrudHelper::e($c->nome).'</option>';
        }

        return $html;
    }

    private static function coletorSelect(int $atribId, ?int $selected): string
    {
        $html = '<select class="form-select form-select-sm coletor-select" data-id="'.$atribId.'">';
        $html .= '<option value=""'.($selected === null ? ' selected' : '').'>— Sem coletor —</option>';
        foreach (EntityUsuario::getColetoresAtivos() as $c) {
            $sel = ($selected === $c->id) ? ' selected' : '';
            $html .= '<option value="'.$c->id.'"'.$sel.'>'.CrudHelper::e($c->nome).'</option>';
        }
        $html .= '</select>';

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
            $badge = $stats['sem_coletor'] > 0
                ? ' <span class="badge bg-warning text-dark" title="Sem coletor">'.$stats['sem_coletor'].'</span>'
                : '';
            $itens .= '<tr>
                <td>'.CrudHelper::e($r->nome).'</td>
                <td>'.CrudHelper::e($r->descricao).'</td>
                <td class="text-center">'.$stats['total'].$badge.'</td>
                <td>
                    <a href="'.URL.'/painel/rotas/atribuicoes/'.$r->id.'" class="btn btn-sm btn-outline-secondary" title="Clientes e coletores"><i class="fas fa-users"></i></a>
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
            'stats_sem_coletor' => $stats['sem_coletor'],
            'coletores_options' => self::coletoresOptions(0, false),
        ]);
        $scripts = '<script>window.ROTA_ATRIB = { rotaId: '.$rotaId.', baseUrl: "/painel/rotas/atribuicoes/'.$rotaId.'" };</script>'
            .'<script src="'.URL.'/resources/js/crud-rota-atribuicoes.js"></script>';

        return self::getPage('Atribuições — '.$rota->nome, $content, 'rotas', $scripts);
    }

    public static function listAtribuicoes($request, int $rotaId): string
    {
        $post = $request->getPostVars();
        $page = (int)($post['page'] ?? 1);
        $busca = trim((string)($post['busca'] ?? ''));
        $whereExtra = '';
        $params = [];
        $semColetor = trim((string)($post['sem_coletor'] ?? ''));
        if ($semColetor === '1') {
            $whereExtra .= ' AND ra.coletor_id IS NULL';
        }
        if ($busca !== '') {
            $whereExtra .= ' AND (c.nome_fantasia LIKE ? OR c.cidade LIKE ? OR u.nome LIKE ?)';
            $params = array_merge($params, array_fill(0, 3, '%'.$busca.'%'));
        }

        $pagination = new Pagination(EntityRotaAtribuicao::countByRota($rotaId, $whereExtra, $params), $page, 20);
        $rows = EntityRotaAtribuicao::listByRota($rotaId, $whereExtra, $params, $pagination->getLimit());

        $itens = '';
        foreach ($rows as $a) {
            $itens .= '<tr>
                <td>'.CrudHelper::e($a->cliente_nome).'</td>
                <td>'.CrudHelper::e($a->cliente_cidade).'</td>
                <td>'.self::coletorSelect($a->id, $a->coletor_id).'</td>
                <td><button class="btn btn-sm btn-outline-danger" onclick="removerAtrib('.$a->id.')"><i class="fas fa-trash"></i></button></td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="4" class="text-center text-muted">Nenhum cliente nesta rota.</td></tr>';
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

        $coletorId = (int)($post['coletor_id'] ?? 0);
        EntityRotaAtribuicao::insert($rotaId, $clienteId, $coletorId > 0 ? $coletorId : null);

        return CrudHelper::jsonOk(['message' => 'Cliente adicionado à rota.']);
    }

    public static function updateAtribuicaoColetor($request, int $rotaId): string
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

        $coletorId = (int)($post['coletor_id'] ?? 0);
        EntityRotaAtribuicao::updateColetor($id, $coletorId > 0 ? $coletorId : null);

        return CrudHelper::jsonOk(['message' => 'Coletor atualizado.']);
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
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $coletorId = (int)($post['coletor_id'] ?? 0);
        if ($coletorId <= 0) {
            return CrudHelper::jsonError('Selecione um coletor.');
        }

        $onlySem = ($post['only_sem_coletor'] ?? '1') !== '0';
        $qtd = EntityRotaAtribuicao::setColetorEmLote($rotaId, $coletorId, $onlySem);

        return CrudHelper::jsonOk(['message' => $qtd.' atribuição(ões) atualizada(s).', 'qtd' => $qtd]);
    }

    public static function clientesDisponiveis($request, int $rotaId): string
    {
        $post = $request->getPostVars();
        $busca = trim((string)($post['busca'] ?? ''));
        $items = EntityRotaAtribuicao::clientesDisponiveis($rotaId, $busca);
        $options = '<option value="">— Selecione —</option>';
        foreach ($items as $c) {
            $options .= '<option value="'.$c['id'].'">'.CrudHelper::e($c['nome']).'</option>';
        }

        return CrudHelper::jsonOk(['options' => $options]);
    }
}
