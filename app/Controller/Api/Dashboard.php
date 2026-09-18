<?php

namespace App\Controller\Api;

use App\Common\Helpers\ApiHelper;
use App\Http\ApiContext;
use App\Service\DashboardService;

class Dashboard extends BaseApi
{
    public static function resumo($request): \App\Http\Response
    {
        $userId = ApiContext::userId();
        $isAdmin = ApiContext::isAdmin();

        return ApiHelper::ok([
            'kpis' => DashboardService::kpisColetor($userId, $isAdmin),
            'graficos' => [
                'coletas_por_mes' => DashboardService::coletasPorMesColetor($userId, $isAdmin, 6),
            ],
        ]);
    }
}
