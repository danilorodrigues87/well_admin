<?php

namespace App\Http\Middleware;

use App\Common\Helpers\ApiHelper;
use App\Http\ApiContext;
use App\Service\ApiAuthService;

class RequireApiAuth
{
    public function handle($request, $next)
    {
        $token = ApiHelper::bearerToken($request);
        if ($token === null) {
            return ApiHelper::fail('unauthorized', 'Token de autenticação ausente.', 401);
        }

        $user = ApiAuthService::userFromToken($token);
        if ($user === null) {
            return ApiHelper::fail('unauthorized', 'Token inválido ou expirado.', 401);
        }

        ApiContext::setUser($user);

        return $next($request);
    }
}
