<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Rota as EntityRota;
use App\Utils\View;

class Agendamentos extends Page
{
    public static function index($request): string
    {
        $rotasOptions = '<option value="">Todas as rotas</option>';
        foreach (EntityRota::getAllActive() as $r) {
            $rotasOptions .= '<option value="'.$r->id.'">'.CrudHelper::e($r->nome).'</option>';
        }

        $content = View::render('admin/modules/agendamentos/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
            'rotas_options' => $rotasOptions,
        ]);
        return self::getPage('Agendamentos', $content, 'agendamentos', self::crudScripts('/painel/agendamentos'));
    }

    public static function list($request): string
    {
        $post = $request->getPostVars();
        $page = (int)($post['page'] ?? 1);
        $busca = trim((string)($post['busca'] ?? ''));
        $rotaId = (int)($post['rota_id'] ?? 0);
        $prioridade = trim((string)($post['prioridade'] ?? ''));

        $where = "c.status = 'ativo'";
        $params = [];
        $join = '';

        if ($rotaId > 0) {
            $join = ' INNER JOIN rota_atribuicoes ra ON ra.cliente_id = c.id AND ra.rota_id = ?';
            $params[] = $rotaId;
        }
        if ($prioridade === 'normal' || $prioridade === 'urgente') {
            $where .= ' AND c.prioridade = ?';
            $params[] = $prioridade;
        }
        if ($busca !== '') {
            $where .= ' AND (c.nome_fantasia LIKE ? OR c.cidade LIKE ?)';
            $params[] = '%'.$busca.'%';
            $params[] = '%'.$busca.'%';
        }

        $db = new \App\Model\Db\Database();
        $countSql = 'SELECT COUNT(DISTINCT c.id) AS qtd FROM clientes c'.$join.' WHERE '.$where;
        $count = (int)$db->execute($countSql, $params)->fetch(\PDO::FETCH_ASSOC)['qtd'];

        $pagination = new Pagination($count, $page, 15);
        $limit = $pagination->getLimit();

        $sql = 'SELECT DISTINCT c.*, p.nome AS plano_nome FROM clientes c
                LEFT JOIN planos p ON p.id = c.plano_id'.$join.'
                WHERE '.$where.' ORDER BY c.prioridade DESC, c.proxima_coleta ASC LIMIT '.$limit;
        $stmt = $db->execute($sql, $params);

        $itens = '';
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $c = EntityCliente::fromRow($row);
            $urgente = $c->prioridade === 'urgente' ? ' <span class="badge bg-danger">Urgente</span>' : '';
            $data = $c->proxima_coleta ? date('d/m/Y', strtotime($c->proxima_coleta)) : '—';
            $itens .= '<tr>
                <td>'.CrudHelper::e($c->nome_fantasia).$urgente.'</td>
                <td>'.CrudHelper::e($c->cidade).'</td>
                <td>'.$data.'</td>
                <td>'.number_format($c->saldo_residuo, 2, ',', '.').'</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editar('.$c->id.')"><i class="fas fa-calendar"></i></button>
                </td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="5" class="text-center text-muted">Nenhum cliente.</td></tr>';
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
            'proxima_coleta' => $c->proxima_coleta ?? '',
            'prioridade' => $c->prioridade,
            'saldo_residuo' => $c->saldo_residuo,
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
            'saldo_residuo' => (float)str_replace(',', '.', (string)($post['saldo_residuo'] ?? 0)),
        ]);

        return CrudHelper::jsonOk(['message' => 'Agendamento atualizado.']);
    }
}
