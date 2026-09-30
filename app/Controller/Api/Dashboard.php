<?php

namespace App\Controller\Api;

use App\Common\Helpers\ApiHelper;
use App\Http\ApiContext;
use App\Service\DashboardAppPresenter;

class Dashboard extends BaseApi
{
    public static function resumo($request): \App\Http\Response
    {
        $user = ApiContext::user();
        if ($user === null) {
            return ApiHelper::fail('unauthorized', 'Não autenticado.', 401);
        }

        return ApiHelper::ok(DashboardAppPresenter::resumo($user));
    }
}
