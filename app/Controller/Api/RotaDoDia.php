<?php

namespace App\Controller\Api;

use App\Common\Helpers\ApiHelper;
use App\Service\RotaDoDiaRequestContext;
use App\Service\RotaDoDiaService;
use App\Service\RotaParadaStatusService;
use App\Service\RotaScopeService;

class RotaDoDia extends BaseApi
{
    public static function paradas($request): \App\Http\Response
    {
        $user = self::user();
        if ($user === []) {
            return ApiHelper::fail('unauthorized', 'Não autenticado.', 401);
        }

        $params = $request->getQueryParams();
        [$coletorId, $isAdminScope] = RotaDoDiaRequestContext::resolveColetor($user, $params);
        $data = RotaDoDiaRequestContext::resolveData($params);
        $rotaId = RotaDoDiaRequestContext::resolveRotaId($params);

        try {
            $paradas = RotaDoDiaService::listarParadas($coletorId, $isAdminScope, $data, $rotaId);

            return ApiHelper::ok([
                'paradas' => $paradas,
                'coletor_id' => $coletorId,
                'data' => $data,
                'total' => count($paradas),
                'rota_id' => $rotaId ?? 0,
                'sem_rota' => !RotaScopeService::operadoraTemClientesEmRotas(),
            ]);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiRotaDoDia::paradas');
        }
    }

    public static function otimizar($request): \App\Http\Response
    {
        $user = self::user();
        if ($user === []) {
            return ApiHelper::fail('unauthorized', 'Não autenticado.', 401);
        }

        $params = RotaDoDiaRequestContext::mergeQueryAndBody($request);
        [$coletorId, $isAdminScope] = RotaDoDiaRequestContext::resolveColetor($user, $params);

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

        $data = RotaDoDiaRequestContext::resolveData($params);
        $rotaId = RotaDoDiaRequestContext::resolveRotaId($params);

        try {
            $result = RotaDoDiaService::otimizar(
                $coletorId,
                $isAdminScope,
                $originLat,
                $originLng,
                $clienteIds,
                $data,
                $rotaId
            );

            return ApiHelper::ok($result);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiRotaDoDia::otimizar');
        }
    }

    public static function salvarOrdem($request): \App\Http\Response
    {
        $user = self::user();
        if ($user === []) {
            return ApiHelper::fail('unauthorized', 'Não autenticado.', 401);
        }

        $params = RotaDoDiaRequestContext::mergeQueryAndBody($request);
        [$coletorId] = RotaDoDiaRequestContext::resolveColetor($user, $params);

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

        $data = RotaDoDiaRequestContext::resolveData($params);

        try {
            RotaDoDiaService::salvarOrdem($coletorId, $ordem, 'manual', $data);

            return ApiHelper::ok(['message' => 'Ordem salva.']);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiRotaDoDia::salvarOrdem');
        }
    }

    public static function paradaStatus($request): \App\Http\Response
    {
        $user = self::user();
        if ($user === []) {
            return ApiHelper::fail('unauthorized', 'Não autenticado.', 401);
        }

        $params = RotaDoDiaRequestContext::mergeQueryAndBody($request);
        [$coletorId, $isAdminScope] = RotaDoDiaRequestContext::resolveColetor($user, $params);
        $data = RotaDoDiaRequestContext::resolveData($params);
        $rotaId = RotaDoDiaRequestContext::resolveRotaId($params);

        $body = $request->getPostVars();
        $clienteId = (int)($body['cliente_id'] ?? 0);
        $status = trim((string)($body['status'] ?? ''));

        try {
            RotaParadaStatusService::definirStatus($coletorId, $data, $clienteId, $status);
            $paradas = RotaDoDiaService::listarParadas($coletorId, $isAdminScope, $data, $rotaId);

            return ApiHelper::ok([
                'message' => 'Status atualizado.',
                'paradas' => $paradas,
            ]);
        } catch (\InvalidArgumentException $e) {
            return ApiHelper::fail('validation', $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiRotaDoDia::paradaStatus');
        }
    }

}
