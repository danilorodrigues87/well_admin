<?php

namespace App\Controller\Gerador;

use App\Common\Helpers\CrudHelper;
use App\Common\Helpers\CsrfHelper;
use App\Service\GeradorPerfilService;
use App\Session\Gerador\Login as GeradorSession;
use App\Utils\View;

class Perfil extends Page
{
    public static function index($request): string
    {
        $session = GeradorSession::getData() ?? [];

        $msg = '';
        $msgType = 'success';
        if (isset($_GET['senha'])) {
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

        $content = View::render('gerador/perfil/index', [
            'csrf_field' => CsrfHelper::field(),
            'msg_alert' => $msgAlert,
            'nome' => CrudHelper::e((string)($session['nome'] ?? '')),
            'email' => CrudHelper::e((string)($session['email'] ?? '')),
            'cliente_nome' => CrudHelper::e((string)($session['cliente_nome'] ?? '')),
        ]);

        return self::render('Meu perfil', $content, 'perfil');
    }

    public static function saveSenha($request): never
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            self::redirectErro($request, $err);
        }

        $session = GeradorSession::getData() ?? [];
        $userId = (int)($session['cliente_usuario_id'] ?? $session['id'] ?? 0);
        if ($userId <= 0) {
            self::redirectErro($request, 'Sessão inválida.');
        }

        $result = GeradorPerfilService::trocarSenha(
            $userId,
            (string)($post['senha_atual'] ?? ''),
            (string)($post['nova_senha'] ?? ''),
            (string)($post['confirmar_senha'] ?? '')
        );

        if (!$result['success']) {
            self::redirectErro($request, $result['message'] ?? 'Erro ao alterar senha.');
        }

        $request->getRouter()->redirect('/gerador/perfil?senha=1');
    }

    private static function redirectErro($request, string $msg): never
    {
        $request->getRouter()->redirect('/gerador/perfil?erro='.urlencode($msg));
    }
}
