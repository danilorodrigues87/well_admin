<?php

namespace App\Controller\Gerador;

use App\Common\CompanyConfig;
use App\Common\Helpers\CsrfHelper;
use App\Common\OperadoraScope;
use App\Controller\Admin\Alert;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\ClienteUsuario;
use App\Session\Gerador\Login as GeradorSession;
use App\Utils\View;

class Login
{
    public static function getLogin($request, ?string $errorMessage = null): string
    {
        GeradorSession::isLogged();
        $status = $errorMessage !== null ? Alert::getError($errorMessage) : '';

        $content = View::render('gerador/login', [
            'status' => $status,
            'csrf_field' => CsrfHelper::field(),
        ]);

        return View::render('gerador/auth_page', [
            'title' => 'Portal do Gerador — '.SITE,
            'content' => $content,
            'company_name' => htmlspecialchars(CompanyConfig::name(), ENT_QUOTES, 'UTF-8'),
        ]);
    }

    public static function setLogin($request): string
    {
        $post = $request->getPostVars();
        if (!CsrfHelper::validate($post['_csrf'] ?? '')) {
            return self::getLogin($request, 'Sessão expirada. Tente novamente.');
        }

        $email = trim(strtolower((string)($post['email'] ?? '')));
        $senha = (string)($post['senha'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return self::getLogin($request, 'E-mail ou senha inválidos.');
        }

        $usuario = ClienteUsuario::getByEmail($email);
        if (!$usuario) {
            return self::getLogin($request, 'E-mail não cadastrado no portal. Peça à operadora para liberar o acesso em Clientes → cadeado.');
        }
        if (!$usuario->ativo) {
            return self::getLogin($request, 'Seu acesso ao portal está inativo. Contate a operadora.');
        }
        if (!password_verify($senha, $usuario->senha_hash)) {
            return self::getLogin($request, 'Senha incorreta. Use a senha informada pela operadora ou peça reset (padrão: 12345678).');
        }

        OperadoraScope::setOverride($usuario->operadora_id);
        $cliente = EntityCliente::getById($usuario->cliente_id);
        if (!$cliente || $cliente->status === 'inativo') {
            return self::getLogin($request, 'Cliente inativo. Contate a operadora.');
        }

        ClienteUsuario::touchUltimoLogin($usuario->id);
        GeradorSession::login($usuario, $cliente);
        session_regenerate_id(true);
        $request->getRouter()->redirect('/gerador');
    }

    public static function setLogout($request): string
    {
        GeradorSession::logout();
        $request->getRouter()->redirect('/gerador/login');
    }
}
