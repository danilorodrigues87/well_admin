<?php

namespace App\Controller\Api\Gerador;

use App\Common\GeradorScope;
use App\Common\Helpers\ApiHelper;
use App\Controller\Api\BaseApi;
use App\Model\Entity\ColetaEvidencia as EntityColetaEvidencia;
use App\Service\GeradorPortalService;
use App\Http\Response;

class Coletas extends BaseApi
{
    public static function index($request): Response
    {
        $page = max(1, (int)($request->getQueryParams()['page'] ?? 1));
        $perPage = min(50, max(1, (int)($request->getQueryParams()['per_page'] ?? 20)));

        return ApiHelper::ok(GeradorPortalService::listColetas($page, $perPage));
    }

    public static function show($request, int $id): Response
    {
        $detalhe = GeradorPortalService::coletaDetalhe($id);
        if ($detalhe === null) {
            return ApiHelper::fail('not_found', 'Coleta não encontrada.', 404);
        }

        return ApiHelper::ok($detalhe);
    }

    public static function evidencia($request, int $id, int $ordem): Response
    {
        if (!GeradorScope::pertenceColeta($id)) {
            return ApiHelper::fail('not_found', 'Coleta não encontrada.', 404);
        }

        $evidencias = EntityColetaEvidencia::getByColetaId($id);
        $match = null;
        foreach ($evidencias as $ev) {
            if ((int)$ev->ordem === $ordem) {
                $match = $ev;
                break;
            }
        }
        if ($match === null) {
            return ApiHelper::fail('not_found', 'Evidência não encontrada.', 404);
        }

        $path = dirname(__DIR__, 4).'/storage/'.$match->arquivo;
        if (!is_readable($path)) {
            return ApiHelper::fail('not_found', 'Arquivo não encontrado.', 404);
        }

        return new Response(200, file_get_contents($path), (string)$match->mime);
    }
}
