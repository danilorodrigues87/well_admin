<?php

namespace App\Controller\Autentication;

use App\Common\Helpers\ColetorSelectHelper;
use App\Common\Helpers\CsrfHelper;
use App\Common\Helpers\ModuleGateHelper;
use App\Controller\Admin\Alert;
use App\Model\Entity\Usuario as EntityUsuario;
use App\Session\User\Login as SessionLogin;
use App\Utils\View;

class Login
{
    public static function getLogin($request, ?string $errorMessage = null): string
    {
        $status = $errorMessage !== null ? Alert::getError($errorMessage) : '';

        $content = View::render('login/login', [
            'status' => $status,
            'csrf_field' => CsrfHelper::field(),
        ]);

        return View::render('login/page', [
            'title' => 'Login — '.SITE,
            'content' => $content,
        ]);
    }

    public static function setLogin($request): string
    {
        $post = $request->getPostVars();
        $csrf = $post['_csrf'] ?? '';

        if (!CsrfHelper::validate($csrf)) {
            return self::getLogin($request, 'Sessão expirada. Tente novamente.');
        }

        $email = filter_var($post['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $senha = (string)($post['senha'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return self::getLogin($request, 'E-mail ou senha inválidos.');
        }

        $usuario = EntityUsuario::getByEmail($email);
        if (!$usuario || !password_verify($senha, $usuario->senha)) {
            return self::getLogin($request, 'E-mail ou senha inválidos.');
        }

        if ($usuario->ativo !== 's') {
            return self::getLogin($request, 'Seu acesso está inativo. Contate o administrador.');
        }

        SessionLogin::login($usuario);
        $session = SessionLogin::getUserLogedData();
        $u = $session['usuario'] ?? [];
        if (ColetorSelectHelper::isColetorSession($u) && ModuleGateHelper::podeAcessar('rota_dia', $u)) {
            $request->getRouter()->redirect('/painel/rota-do-dia');
        }
        $request->getRouter()->redirect('/painel');
    }

    public static function setLogout($request): string
    {
        SessionLogin::logout();
        $request->getRouter()->redirect('/');
    }
}
