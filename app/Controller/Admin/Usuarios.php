<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Funcao as EntityFuncao;
use App\Model\Entity\Usuario as EntityUsuario;
use App\Utils\View;

class Usuarios extends Page
{
    public static function index($request): string
    {
        $funcoes = EntityFuncao::getAll();
        $options = '';
        foreach ($funcoes as $f) {
            $options .= '<option value="'.$f->id.'">'.CrudHelper::e($f->nome).'</option>';
        }
        $content = View::render('admin/modules/usuarios/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
            'funcoes_options' => $options,
            'titulo_pagina' => 'Usuários',
        ]);
        return self::getPage('Usuários', $content, 'usuarios', self::crudScripts('/painel/usuarios'));
    }

    public static function list($request): string
    {
        $post = $request->getPostVars();
        $page = (int)($post['page'] ?? 1);
        $busca = trim((string)($post['busca'] ?? ''));

        $where = '1=1';
        $params = [];
        $funcaoId = (int)($post['funcao_id'] ?? 0);
        if ($funcaoId > 0) {
            $where .= ' AND u.funcao_id = ?';
            $params[] = $funcaoId;
        }
        $ativo = trim((string)($post['ativo'] ?? ''));
        if ($ativo === 's' || $ativo === 'n') {
            $where .= ' AND u.ativo = ?';
            $params[] = $ativo;
        }
        if ($busca !== '') {
            $where .= ' AND (u.nome LIKE ? OR u.email LIKE ?)';
            $params[] = '%'.$busca.'%';
            $params[] = '%'.$busca.'%';
        }

        $total = EntityUsuario::count($where, $params);
        $pagination = new Pagination($total, $page, 10);
        $rows = EntityUsuario::list($where, $params, $pagination->getLimit());

        $itens = '';
        foreach ($rows as $u) {
            $ativo = $u->ativo === 's' ? 'Ativo' : 'Inativo';
            $itens .= '<tr>
                <td>'.CrudHelper::e($u->nome).'</td>
                <td>'.CrudHelper::e($u->email).'</td>
                <td>'.CrudHelper::e($u->funcao_nome).'</td>
                <td>'.$ativo.'</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editar('.$u->id.')"><i class="fas fa-edit"></i></button>
                    '.CrudHelper::btnDesativar($u->id).'
                </td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="5" class="text-center text-muted">Nenhum usuário encontrado.</td></tr>';
        }

        return self::jsonLista([
            'success' => true,
            'itens' => $itens,
            'pagination' => Pagination::renderNav($pagination),
        ]);
    }

    public static function get($request): string
    {
        $id = (int)($request->getPostVars()['id'] ?? 0);
        $u = EntityUsuario::getById($id);
        if (!$u) {
            return CrudHelper::jsonError('Usuário não encontrado.');
        }
        return CrudHelper::jsonOk([
            'id' => $u->id,
            'nome' => $u->nome,
            'email' => $u->email,
            'funcao_id' => $u->funcao_id,
            'ativo' => $u->ativo,
        ]);
    }

    public static function save($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $id = (int)($post['id'] ?? 0);
        $nome = trim((string)($post['nome'] ?? ''));
        $email = filter_var(trim((string)($post['email'] ?? '')), FILTER_SANITIZE_EMAIL);
        $funcaoId = (int)($post['funcao_id'] ?? 0);
        $ativo = ($post['ativo'] ?? 's') === 'n' ? 'n' : 's';
        $senha = (string)($post['senha'] ?? '');

        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $funcaoId <= 0) {
            return CrudHelper::jsonError('Preencha nome, e-mail válido e função.');
        }

        $exists = EntityUsuario::getByEmail($email);
        if ($exists && ($id === 0 || $exists->id !== $id)) {
            return CrudHelper::jsonError('E-mail já cadastrado.');
        }

        if ($id > 0) {
            $data = ['nome' => $nome, 'email' => $email, 'funcao_id' => $funcaoId, 'ativo' => $ativo];
            if ($senha !== '') {
                if (strlen($senha) < 8) {
                    return CrudHelper::jsonError('Senha deve ter no mínimo 8 caracteres.');
                }
                $data['senha'] = password_hash($senha, PASSWORD_DEFAULT);
            }
            EntityUsuario::update($id, $data);
        } else {
            if (strlen($senha) < 8) {
                return CrudHelper::jsonError('Senha inicial deve ter no mínimo 8 caracteres.');
            }
            EntityUsuario::insert([
                'nome' => $nome,
                'email' => $email,
                'senha' => password_hash($senha, PASSWORD_DEFAULT),
                'funcao_id' => $funcaoId,
                'ativo' => $ativo,
            ]);
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
        if ($id <= 0) {
            return CrudHelper::jsonError('ID inválido.');
        }
        EntityUsuario::update($id, ['ativo' => 'n']);
        return CrudHelper::jsonOk(['message' => 'Usuário desativado.']);
    }
}
