<?php

namespace App\Controller\Api;

use App\Common\Helpers\ApiHelper;
use App\Http\ApiContext;
use App\Service\FrotaLocalizacaoService;

class Frota extends BaseApi
{
    public static function registrarPosicao($request): \App\Http\Response
    {
        $body = $request->getPostVars();
        $lat = (float)($body['latitude'] ?? $body['lat'] ?? 0);
        $lng = (float)($body['longitude'] ?? $body['lng'] ?? 0);

        try {
            FrotaLocalizacaoService::registrarPosicao(ApiContext::userId(), $lat, $lng, [
                'accuracy_m' => isset($body['accuracy_m']) ? (float)$body['accuracy_m'] : null,
                'heading' => isset($body['heading']) ? (float)$body['heading'] : null,
                'speed_mps' => isset($body['speed_mps']) ? (float)$body['speed_mps'] : null,
                'fonte' => 'app',
            ]);

            return ApiHelper::ok(['message' => 'Posição registrada.']);
        } catch (\InvalidArgumentException $e) {
            return ApiHelper::fail('validation', $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiFrota::registrarPosicao');
        }
    }

    public static function posicoes($request): \App\Http\Response
    {
        $params = $request->getQueryParams();
        $minutos = (int)($params['minutos'] ?? 120);

        try {
            return ApiHelper::ok([
                'posicoes' => FrotaLocalizacaoService::listarUltimasPosicoes($minutos),
            ]);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiFrota::posicoes');
        }
    }
}
