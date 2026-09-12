<?php

namespace App\Controller\Api;

use App\Common\Helpers\ApiHelper;
use App\Http\ApiContext;
use App\Service\ColetaApiPresenter;
use App\Service\ColetaService;

class Clientes extends BaseApi
{
    public static function paraColeta($request): \App\Http\Response
    {
        $query = $request->getQueryParams();
        $page = max(1, (int)($query['page'] ?? 1));
        $perPage = min(50, max(1, (int)($query['per_page'] ?? 15)));
        $busca = trim((string)($query['busca'] ?? ''));
        $prioridade = trim((string)($query['prioridade'] ?? ''));
        $escopo = trim((string)($query['escopo'] ?? 'pendentes'));
        $somentePendentes = $escopo !== 'todos';

        $user = self::user();
        $result = ColetaService::clientesParaColetaPaginado(
            ApiContext::userId(),
            ApiContext::isAdmin(),
            $page,
            $perPage,
            $busca,
            $prioridade,
            $somentePendentes
        );

        $items = array_map(
            fn ($c) => ColetaApiPresenter::cliente($c),
            $result['items']
        );

        return ApiHelper::ok([
            'items' => $items,
            'pagination' => ApiHelper::paginationMeta($result['total'], $page, $perPage),
        ]);
    }
}
