<?php

namespace App\Http\Middleware;

use App\Common\Helpers\ApiHelper;
use App\Common\Helpers\ModuleGateHelper;
use App\Http\ApiContext;

class RequireApiModule
{
    public function __construct(private string $slug)
    {
    }

    public function handle($request, $next)
    {
        $user = ApiContext::user();
        if ($user === null) {
            return ApiHelper::fail('unauthorized', 'Não autenticado.', 401);
        }

        if (!ModuleGateHelper::podeAcessar($this->slug, $user)) {
            return ApiHelper::fail('forbidden', 'Sem permissão para este recurso.', 403);
        }

        return $next($request);
    }
}
