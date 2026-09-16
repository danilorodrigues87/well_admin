<?php

namespace App\Http\Middleware;

use App\Session\Gerador\Login as GeradorSession;

class RequireGeradorLogout
{
    public function handle($request, $next)
    {
        if (GeradorSession::isLogged()) {
            $request->getRouter()->redirect('/gerador');
        }

        return $next($request);
    }
}
