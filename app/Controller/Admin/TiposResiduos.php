<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Common\Helpers\IbamaCodigoHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\ResiduoClasse as EntityResiduoClasse;
use App\Model\Entity\ResiduoGrupo as EntityResiduoGrupo;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;
use App\Utils\View;

class TiposResiduos extends Page
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

    private static function gruposOptions(int $classeId, int $selected = 0): string
    {
        $html = '<option value="">— Selecione —</option>';
        if ($classeId > 0) {
            foreach (EntityResiduoGrupo::getByClasseId($classeId) as $g) {
                $sel = $g->id === $selected ? ' selected' : '';
                $html .= '<option value="'.$g->id.'"'.$sel.'>'.CrudHelper::e($g->codigo).' — '.CrudHelper::e($g->nome).'</option>';
            }
        }
        return $html;
    }

    public static function index($request): string
    {
        $content = View::render('admin/modules/tipos_residuos/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
            'classes_options' => self::classesOptions(),
            'classes_filter_options' => self::classesOptions(0, 'Classe: todas'),
        ]);
        $scripts = self::crudScripts('/painel/tipos-residuos')
            . '<script src="'.URL.'/resources/js/crud-tipos-residuos.js"></script>';
        return self::getPage('Tipos de Resíduos', $content, 'tipos_residuos', $scripts);
    }

    public static function list($request): string
    {
        $post = $request->getPostVars();
        $page = (int)($post['page'] ?? 1);
        $busca = trim((string)($post['busca'] ?? ''));
        $where = 't.ativo = 1';
        $params = [];
        $classeId = (int)($post['classe_id'] ?? 0);
        if ($classeId > 0) {
            $where .= ' AND t.classe_id = ?';
            $params[] = $classeId;
        }
        $grupoId = (int)($post['grupo_id'] ?? 0);
        if ($grupoId > 0) {
            $where .= ' AND t.grupo_id = ?';
            $params[] = $grupoId;
        }
        if ($busca !== '') {
            $where .= ' AND (t.nome LIKE ? OR t.cod_ibama LIKE ? OR g.codigo LIKE ? OR c.nome LIKE ?)';
            $params = array_merge($params, array_fill(0, 4, '%'.$busca.'%'));
        }

        $pagination = new Pagination(EntityTipoResiduo::count($where, $params), $page, 15);
        $rows = EntityTipoResiduo::list($where, $params, $pagination->getLimit());

        $itens = '';
        foreach ($rows as $t) {
            $itens .= '<tr>
                <td>'.CrudHelper::e($t->nome).'</td>
                <td>'.CrudHelper::e($t->classe_nome).'</td>
                <td>'.CrudHelper::e($t->grupo_codigo).'</td>
                <td>'.CrudHelper::e($t->cod_ibama).'</td>
                <td>'.self::sinirMapBadge($t).'</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editar('.$t->id.')"><i class="fas fa-edit"></i></button>
                    '.CrudHelper::btnDesativar($t->id).'
                </td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="6" class="text-center text-muted">Nenhum tipo cadastrado.</td></tr>';
        }

        return self::jsonLista(['success' => true, 'itens' => $itens, 'pagination' => Pagination::renderNav($pagination)]);
    }

    public static function get($request): string
    {
        $id = (int)($request->getPostVars()['id'] ?? 0);
        $t = EntityTipoResiduo::getById($id);
        if (!$t) {
            return CrudHelper::jsonError('Tipo não encontrado.');
        }
        $classeId = (int)($t->classe_id ?? 0);
        return CrudHelper::jsonOk([
            'id' => $t->id,
            'nome' => $t->nome,
            'classe_id' => $classeId,
            'grupo_id' => (int)($t->grupo_id ?? 0),
            'cod_ibama' => $t->cod_ibama,
            'tra_codigo' => $t->tra_codigo,
            'tie_codigo' => $t->tie_codigo,
            'tia_codigo' => $t->tia_codigo,
            'cla_codigo' => $t->cla_codigo,
            'uni_codigo' => $t->uni_codigo,
            'grupos_options' => self::gruposOptions($classeId, (int)($t->grupo_id ?? 0)),
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
        $classeId = (int)($post['classe_id'] ?? 0);
        $grupoId = (int)($post['grupo_id'] ?? 0);
        $nome = trim((string)($post['nome'] ?? ''));
        if ($nome === '') {
            return CrudHelper::jsonError('Nome é obrigatório.');
        }
        if ($classeId <= 0 || $grupoId <= 0) {
            return CrudHelper::jsonError('Selecione classe e grupo.');
        }

        $grupo = EntityResiduoGrupo::getById($grupoId);
        if (!$grupo || $grupo->classe_id !== $classeId) {
            return CrudHelper::jsonError('Grupo não pertence à classe selecionada.');
        }

        $data = [
            'nome' => $nome,
            'classe_id' => $classeId,
            'grupo_id' => $grupoId,
            'cod_ibama' => IbamaCodigoHelper::normalize(trim((string)($post['cod_ibama'] ?? ''))) ?: null,
            'tra_codigo' => self::parseOptionalInt($post['tra_codigo'] ?? null),
            'tie_codigo' => self::parseOptionalInt($post['tie_codigo'] ?? null),
            'tia_codigo' => self::parseOptionalInt($post['tia_codigo'] ?? null),
            'cla_codigo' => self::parseOptionalInt($post['cla_codigo'] ?? null),
            'uni_codigo' => self::parseOptionalInt($post['uni_codigo'] ?? null),
            'ativo' => CrudHelper::parseAtivo($post, $id <= 0),
        ];

        if ($id > 0) {
            EntityTipoResiduo::update($id, $data);
        } else {
            EntityTipoResiduo::insert($data);
        }
        return CrudHelper::jsonOk(['message' => 'Salvo com sucesso.']);
    }

    public static function delete($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        EntityTipoResiduo::delete((int)($post['id'] ?? 0));
        return CrudHelper::jsonOk(['message' => 'Tipo removido.']);
    }

    private static function parseOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = (int)$value;
        return $n > 0 ? $n : null;
    }

    private static function sinirMapBadge(EntityTipoResiduo $t): string
    {
        $ok = ($t->tra_codigo ?? 0) > 0
            && ($t->tie_codigo ?? 0) > 0
            && ($t->tia_codigo ?? 0) > 0
            && ($t->cla_codigo ?? 0) > 0
            && ($t->uni_codigo ?? 0) > 0;
        return $ok
            ? '<span class="badge bg-success">SINIR</span>'
            : '<span class="badge bg-secondary">Pendente</span>';
    }

    public static function gruposPorClasse($request): string
    {
        $classeId = (int)($request->getPostVars()['classe_id'] ?? 0);
        $options = '<option value="">— Selecione —</option>';
        if ($classeId > 0) {
            foreach (EntityResiduoGrupo::getByClasseId($classeId) as $g) {
                $options .= '<option value="'.$g->id.'">'.CrudHelper::e($g->codigo).' — '.CrudHelper::e($g->nome).'</option>';
            }
        }
        return CrudHelper::jsonOk(['options' => $options]);
    }
}
