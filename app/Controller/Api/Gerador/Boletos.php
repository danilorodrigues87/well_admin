<?php

namespace App\Controller\Api\Gerador;

use App\Common\Helpers\ApiHelper;
use App\Controller\Api\BaseApi;
use App\Service\GeradorApiPresenter;
use App\Service\GeradorPortalService;
use App\Http\Response;

class Boletos extends BaseApi
{
    public static function index($request): Response
    {
        $page = max(1, (int)($request->getQueryParams()['page'] ?? 1));
        $perPage = min(50, max(1, (int)($request->getQueryParams()['per_page'] ?? 20)));

        return ApiHelper::ok(GeradorPortalService::listBoletos($page, $perPage));
    }

    public static function show($request, int $id): Response
    {
        $boleto = GeradorPortalService::getBoleto($id);
        if ($boleto === null) {
            return ApiHelper::fail('not_found', 'Boleto não encontrado.', 404);
        }

        return ApiHelper::ok(GeradorApiPresenter::boleto($boleto));
    }
}
