<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Model\Entity\Funcao as EntityFuncao;
use App\Model\Entity\FuncaoModulo as EntityFuncaoModulo;
use App\Model\Entity\Modulo as EntityModulo;
use App\Utils\View;

class Funcoes extends Page
{
    public static function index($request): string
    {
        $content = View::render('admin/modules/funcoes/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
        ]);
        $scripts = self::crudScripts('/painel/funcoes')
            . '<script src="'.URL.'/resources/js/crud-funcoes.js"></script>';
        return self::getPage('Funções e Módulos', $content, 'funcoes', $scripts);
    }

    public static function list($request): string
    {
        $funcoes = EntityFuncao::getAll();
        $itens = '';
        foreach ($funcoes as $f) {
            $admin = $f->is_admin ? ' <span class="badge bg-warning text-dark">Admin</span>' : '';
            $modCount = count(EntityFuncaoModulo::getSlugsByFuncaoId($f->id));
            $itens .= '<tr>
                <td>'.CrudHelper::e($f->nome).$admin.'</td>
                <td>'.CrudHelper::e($f->slug).'</td>
                <td>'.$modCount.' módulos</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editar('.$f->id.')"><i class="fas fa-edit"></i> Módulos</button>
                </td>
            </tr>';
        }

        return self::jsonLista(['success' => true, 'itens' => $itens, 'pagination' => '']);
    }

    public static function get($request): string
    {
        $id = (int)($request->getPostVars()['id'] ?? 0);
        $funcao = EntityFuncao::getById($id);
        if (!$funcao) {
            return CrudHelper::jsonError('Função não encontrada.');
        }

        $selected = EntityFuncaoModulo::getModuloIdsByFuncaoId($id);
        $modulosHtml = '';
        $grupoAtual = '';
        foreach (EntityModulo::getAll() as $m) {
            if ($m->slug === 'perfil' || $m->slug === 'dashboard') {
                continue;
            }
            if ($m->grupo !== $grupoAtual) {
                $grupoAtual = $m->grupo;
                $modulosHtml .= '<div class="col-12 mt-2"><strong>'.CrudHelper::e($grupoAtual).'</strong></div>';
            }
            $checked = in_array($m->id, $selected, true) ? ' checked' : '';
            $disabled = $funcao->is_admin ? ' disabled checked' : '';
            $modulosHtml .= '<div class="col-md-4"><div class="form-check">
                <input class="form-check-input" type="checkbox" name="modulos[]" value="'.$m->id.'" id="mod_'.$m->id.'"'.$checked.$disabled.'>
                <label class="form-check-label" for="mod_'.$m->id.'">'.CrudHelper::e($m->label).'</label>
            </div></div>';
        }

        return CrudHelper::jsonOk([
            'id' => $funcao->id,
            'nome' => $funcao->nome,
            'descricao' => $funcao->descricao,
            'is_admin' => $funcao->is_admin,
            'modulos_html' => $modulosHtml,
        ]);
    }

    public static function save($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $id = (int)($post['id'] ?? 0);
        $funcao = EntityFuncao::getById($id);
        if (!$funcao || $funcao->is_admin) {
            return CrudHelper::jsonError('Função administradora não pode ser alterada.');
        }

        $modulos = $post['modulos'] ?? [];
        if (!is_array($modulos)) {
            $modulos = [];
        }
        $moduloIds = array_map('intval', $modulos);

        EntityFuncao::syncModulos($id, $moduloIds);
        return CrudHelper::jsonOk(['message' => 'Módulos atualizados.']);
    }
}
