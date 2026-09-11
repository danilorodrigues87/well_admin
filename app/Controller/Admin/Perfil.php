<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Common\Helpers\CsrfHelper;
use App\Model\Entity\Usuario as EntityUsuario;
use App\Service\PerfilService;
use App\Session\User\Login as SessionUser;
use App\Utils\View;

class Perfil extends Page
{
    public static function index($request): string
    {
        $session = SessionUser::getUserLogedData()['usuario'] ?? [];
        $userId = (int)($session['id'] ?? 0);
        $usuario = EntityUsuario::getById($userId);

        if (!$usuario) {
            return self::getPage('Perfil', '<div class="alert alert-danger">Usuário não encontrado.</div>', 'perfil');
        }

        $msg = '';
        $msgType = 'success';
        if (isset($_GET['saved'])) {
            $msg = 'Dados atualizados com sucesso.';
        } elseif (isset($_GET['senha'])) {
            $msg = 'Senha alterada com sucesso.';
        } elseif (isset($_GET['erro'])) {
            $msgType = 'danger';
            $msg = htmlspecialchars((string)($_GET['erro'] ?? 'Erro'), ENT_QUOTES, 'UTF-8');
        }

        $msgAlert = $msg !== ''
            ? '<div class="alert alert-'.$msgType.' alert-dismissible fade show" role="alert">'
                .htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
                .'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'
            : '';

        $content = View::render('admin/modules/perfil/index', [
            'csrf_field' => CsrfHelper::field(),
            'nome' => CrudHelper::e($usuario->nome),
            'email' => CrudHelper::e($usuario->email),
            'funcao' => CrudHelper::e($usuario->funcao_nome),
            'msg_alert' => $msgAlert,
        ]);

        return self::getPage('Meu Perfil', $content, 'perfil');
    }

    public static function save($request): never
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            self::redirectErro($request, $err);
        }

        $session = SessionUser::getUserLogedData()['usuario'] ?? [];
        $userId = (int)($session['id'] ?? 0);
        if ($userId <= 0) {
            self::redirectErro($request, 'Sessão inválida.');
        }

        $acao = (string)($post['acao'] ?? 'dados');

        if ($acao === 'senha') {
            $result = PerfilService::trocarSenha(
                $userId,
                (string)($post['senha_atual'] ?? ''),
                (string)($post['nova_senha'] ?? ''),
                (string)($post['confirmar_senha'] ?? '')
            );
            if (!$result['success']) {
                self::redirectErro($request, $result['message'] ?? 'Erro ao alterar senha.');
            }

            $request->getRouter()->redirect('/painel/perfil?senha=1');
        }

        $result = PerfilService::atualizarDados(
            $userId,
            (string)($post['nome'] ?? ''),
            (string)($post['email'] ?? '')
        );
        if (!$result['success']) {
            self::redirectErro($request, $result['message'] ?? 'Erro ao salvar.');
        }

        $request->getRouter()->redirect('/painel/perfil?saved=1');
    }

    private static function redirectErro($request, string $msg): never
    {
        $request->getRouter()->redirect('/painel/perfil?erro='.urlencode($msg));
    }
}
