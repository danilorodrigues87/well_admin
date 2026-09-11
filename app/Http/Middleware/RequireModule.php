<?php

namespace App\Http\Middleware;

use App\Common\Helpers\ModuleGateHelper;
use App\Session\User\Login as SessionLogin;

class RequireModule
{
    private string $slug;

    public function __construct(string $slug)
    {
        $this->slug = $slug;
    }

    public function handle($request, $next)
    {
        $userData = SessionLogin::getUserLogedData();
        $usuario = $userData['usuario'] ?? null;
        if (!$usuario || !ModuleGateHelper::podeAcessar($this->slug, $usuario)) {
            $request->getRouter()->redirect('/painel');
        }
        return $next($request);
    }
}
