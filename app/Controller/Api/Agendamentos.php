<?php

namespace App\Controller\Api;

use App\Common\Helpers\ApiHelper;
use App\Http\ApiContext;
use App\Service\AgendamentoService;

class Agendamentos extends BaseApi
{
    public static function index($request): \App\Http\Response
    {
        $query = $request->getQueryParams();
        $busca = trim((string)($query['busca'] ?? ''));
        $page = max(1, (int)($query['page'] ?? 1));
        $perPage = min(50, max(1, (int)($query['per_page'] ?? 20)));

        $result = AgendamentoService::listar(
            $busca,
            $page,
            $perPage,
            ApiContext::userId(),
            ApiContext::isAdmin()
        );

        return ApiHelper::ok([
            'items' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }
}
