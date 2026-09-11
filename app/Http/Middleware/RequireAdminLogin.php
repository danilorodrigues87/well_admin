<?php

namespace App\Http\Middleware;

use App\Session\User\Login as SessionLogin;

class RequireAdminLogin
{
    public function handle($request, $next)
    {
        if (!SessionLogin::isUserLogged()) {
            $request->getRouter()->redirect('/');
        }
        if (!SessionLogin::syncSessionFromDatabase()) {
            $request->getRouter()->redirect('/');
        }
        return $next($request);
    }
}
