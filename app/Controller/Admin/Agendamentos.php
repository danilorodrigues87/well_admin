<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Common\Helpers\ColetorSelectHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Rota as EntityRota;
use App\Service\AgendamentoService;
use App\Service\PlanoService;
use App\Session\User\Login as SessionUser;
use App\Utils\View;

class Agendamentos extends Page
{
    private static function rotasOptionsHtml(bool $emptyOption = true): string
    {
        $html = $emptyOption ? '<option value="">Todas as rotas</option>' : '';
        foreach (EntityRota::getAllActive() as $r) {
            $html .= '<option value="'.$r->id.'">'.CrudHelper::e($r->nome).'</option>';
        }

        return $html;
    }

    /** @return array{0:int,1:bool} */
    private static function resolveColetorContext(): array
    {
        $usuario = SessionUser::getUserLogedData()['usuario'] ?? [];
        $isAdmin = !empty($usuario['is_admin']);
        if (ColetorSelectHelper::isColetorSession($usuario)) {
            return [(int)($usuario['id'] ?? 0), false];
        }

        return [0, $isAdmin];
    }

    public static function index($request): string
    {
        $content = View::render('admin/modules/agendamentos/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
            'rotas_options' => self::rotasOptionsHtml(true),
            'rotas_options_lote' => self::rotasOptionsHtml(false),
            'data_hoje' => date('Y-m-d'),
        ]);

        return self::getPage(
            'Agendamentos',
            $content,
            'agendamentos',
            self::crudScripts('/painel/agendamentos').'<script src="'.URL.'/resources/js/agendamentos.js?v=20260917"></script>'
        );
    }

    public static function list($request): string
    {
        $post = $request->getPostVars();
        $page = (int)($post['page'] ?? 1);
        $busca = trim((string)($post['busca'] ?? ''));
        $rotaId = (int)($post['rota_id'] ?? 0);
        $prioridade = trim((string)($post['prioridade'] ?? ''));

        [$coletorId, $isAdmin] = self::resolveColetorContext();

        $result = AgendamentoService::listar(
            $busca,
            $page,
            15,
            $coletorId,
            $isAdmin,
            $rotaId,
            $prioridade,
            false
        );

        $itens = '';
        foreach ($result['items'] as $row) {
            $urgente = ($row['prioridade'] ?? '') === 'urgente' ? ' <span class="badge bg-danger">Urgente</span>' : '';
            $proxima = $row['proxima_coleta'] ?? null;
            $data = $proxima ? date('d/m/Y', strtotime((string)$proxima)) : '—';
            $saldoPlano = (float)($row['saldo_plano'] ?? 0);
            $itens .= '<tr>
                <td>'.CrudHelper::e((string)$row['nome_fantasia']).$urgente.'</td>
                <td class="small">'.CrudHelper::e((string)($row['plano_nome'] ?: '—')).'</td>
                <td>'.CrudHelper::e((string)$row['cidade']).'</td>
                <td>'.$data.'</td>
                <td>'.($saldoPlano > 0 ? number_format($saldoPlano, 2, ',', '.').' kg' : '—').'</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editar('.(int)$row['id'].')"><i class="fas fa-calendar"></i></button>
                </td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="6" class="text-center text-muted">Nenhum cliente.</td></tr>';
        }

        $pagination = new Pagination($result['meta']['total'], $page, 15);

        return self::jsonLista(['success' => true, 'itens' => $itens, 'pagination' => Pagination::renderNav($pagination)]);
    }

    public static function get($request): string
    {
        $id = (int)($request->getPostVars()['id'] ?? 0);
        $c = EntityCliente::getById($id);
        if (!$c) {
            return CrudHelper::jsonError('Cliente não encontrado.');
        }
        $planoId = (int)($c->plano_id ?? 0);

        return CrudHelper::jsonOk([
            'id' => $c->id,
            'nome_fantasia' => $c->nome_fantasia,
            'plano_nome' => $c->plano_nome,
            'saldo_plano' => $planoId > 0 ? PlanoService::totalSaldoIncluso($planoId) : 0,
            'proxima_coleta' => $c->proxima_coleta ?? '',
            'prioridade' => $c->prioridade,
        ]);
    }

    public static function save($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $id = (int)($post['id'] ?? 0);
        $prioridade = in_array($post['prioridade'] ?? '', ['normal', 'urgente'], true) ? $post['prioridade'] : 'normal';
        $proxima = trim((string)($post['proxima_coleta'] ?? ''));

        EntityCliente::update($id, [
            'proxima_coleta' => $proxima !== '' ? $proxima : null,
            'prioridade' => $prioridade,
        ]);

        return CrudHelper::jsonOk(['message' => 'Agendamento atualizado.']);
    }

    public static function agendarRota($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $rotaId = (int)($post['rota_id'] ?? 0);
        $data = trim((string)($post['proxima_coleta'] ?? ''));
        $alterarPrioridade = !empty($post['alterar_prioridade']);
        $prioridade = $alterarPrioridade
            ? (in_array($post['prioridade'] ?? '', ['normal', 'urgente'], true) ? $post['prioridade'] : 'normal')
            : null;

        try {
            $result = AgendamentoService::agendarRotaEmLote($rotaId, $data, $prioridade);
        } catch (\InvalidArgumentException $e) {
            return CrudHelper::jsonError($e->getMessage());
        }

        return CrudHelper::jsonOk([
            'message' => $result['atualizados'].' cliente(s) da rota "'.$result['rota_nome'].'" agendados para '
                .date('d/m/Y', strtotime($data)).'.',
            'atualizados' => $result['atualizados'],
        ]);
    }
}
