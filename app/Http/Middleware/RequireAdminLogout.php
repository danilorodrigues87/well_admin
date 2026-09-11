<?php

namespace App\Http\Middleware;

use App\Session\User\Login as SessionLogin;

class RequireAdminLogout
{
    public function handle($request, $next)
    {
        if (SessionLogin::isUserLogged()) {
            $request->getRouter()->redirect('/painel');
        }
        return $next($request);
    }
}
