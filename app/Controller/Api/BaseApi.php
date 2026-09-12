<?php

namespace App\Controller\Api;

use App\Common\Helpers\ApiHelper;
use App\Http\ApiContext;
use App\Model\Entity\Coleta as EntityColeta;

abstract class BaseApi
{
    /** @return array<string,mixed> */
    protected static function user(): array
    {
        return ApiContext::user() ?? [];
    }

    protected static function assertColetaAccess(EntityColeta $coleta): ?\App\Http\Response
    {
        $userId = ApiContext::userId();
        if (ApiContext::isAdmin()) {
            return null;
        }
        if ((int)$coleta->coletor_id !== $userId) {
            return ApiHelper::fail('forbidden', 'Sem permissão para esta coleta.', 403);
        }

        return null;
    }

    protected static function handleInvalidArgument(\InvalidArgumentException $e): \App\Http\Response
    {
        return ApiHelper::fail('validation_error', $e->getMessage(), 422);
    }

    protected static function handleThrowable(\Throwable $e, string $context): \App\Http\Response
    {
        error_log('['.$context.'] '.$e->getMessage());
        return ApiHelper::fail('server_error', 'Erro ao processar solicitação.', 500);
    }
}
