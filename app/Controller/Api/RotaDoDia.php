<?php

namespace App\Controller\Api;

use App\Common\Helpers\ApiHelper;
use App\Http\ApiContext;
use App\Service\RotaDoDiaService;

class RotaDoDia extends BaseApi
{
    public static function paradas($request): \App\Http\Response
    {
        try {
            return ApiHelper::ok([
                'paradas' => RotaDoDiaService::listarParadas(
                    ApiContext::userId(),
                    ApiContext::isAdmin()
                ),
            ]);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiRotaDoDia::paradas');
        }
    }

    public static function otimizar($request): \App\Http\Response
    {
        $body = $request->getPostVars();
        $originLat = (float)($body['origin_lat'] ?? 0);
        $originLng = (float)($body['origin_lng'] ?? 0);
        if ($originLat === 0.0 && $originLng === 0.0) {
            return ApiHelper::fail('validation', 'origin_lat e origin_lng são obrigatórios.', 422);
        }

        $clienteIds = [];
        if (!empty($body['cliente_ids']) && is_array($body['cliente_ids'])) {
            $clienteIds = array_map('intval', $body['cliente_ids']);
        }

        try {
            $result = RotaDoDiaService::otimizar(
                ApiContext::userId(),
                ApiContext::isAdmin(),
                $originLat,
                $originLng,
                $clienteIds
            );

            return ApiHelper::ok($result);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiRotaDoDia::otimizar');
        }
    }

    public static function salvarOrdem($request): \App\Http\Response
    {
        $body = $request->getPostVars();
        $ordem = [];
        if (!empty($body['ordem']) && is_array($body['ordem'])) {
            foreach ($body['ordem'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $ordem[] = [
                    'cliente_id' => (int)($item['cliente_id'] ?? 0),
                    'ordem' => (int)($item['ordem'] ?? 0),
                ];
            }
        }

        try {
            RotaDoDiaService::salvarOrdem(ApiContext::userId(), $ordem, 'manual');

            return ApiHelper::ok(['message' => 'Ordem salva.']);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiRotaDoDia::salvarOrdem');
        }
    }
}
