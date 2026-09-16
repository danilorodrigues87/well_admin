<?php

namespace App\Http\Middleware;

use App\Service\TermosDeUsoService;
use App\Session\User\Login as SessionLogin;

class RequireAdminLogin
{
    private const TERMOS_WHITELIST = [
        '/painel/termos-de-uso',
        '/painel/aceita-termos',
        '/logout',
    ];

    public function handle($request, $next)
    {
        if (!SessionLogin::isUserLogged()) {
            $request->getRouter()->redirect('/');
        }
        if (!SessionLogin::syncSessionFromDatabase()) {
            $request->getRouter()->redirect('/');
        }

        $uri = $request->getRouter()->getUri();
        if (!in_array($uri, self::TERMOS_WHITELIST, true)) {
            $userData = SessionLogin::getUserLogedData();
            $usuarioId = (int)($userData['usuario']['id'] ?? 0);
            if ($usuarioId > 0 && !TermosDeUsoService::usuarioAceitouVersaoAtual($usuarioId)) {
                $request->getRouter()->redirect('/painel/termos-de-uso');
            }
        }

        return $next($request);
    }
}
