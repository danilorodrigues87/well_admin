<?php

namespace App\Http\Middleware;

use App\Common\GeradorScope;
use App\Common\Helpers\ApiHelper;
use App\Common\OperadoraScope;
use App\Http\GeradorContext;
use App\Service\GeradorAuthService;

class RequireGeradorApiAuth
{
    public function handle($request, $next)
    {
        $token = ApiHelper::bearerToken($request);
        if ($token === null) {
            return ApiHelper::fail('unauthorized', 'Token de autenticação ausente.', 401);
        }

        $user = GeradorAuthService::userFromToken($token);
        if ($user === null) {
            return ApiHelper::fail('unauthorized', 'Token inválido ou expirado.', 401);
        }

        GeradorContext::setUser($user);
        OperadoraScope::setOverride((int)($user['operadora_id'] ?? 1));
        GeradorScope::setOverride((int)($user['cliente_id'] ?? 0), (int)($user['operadora_id'] ?? 1));

        return $next($request);
    }
}
