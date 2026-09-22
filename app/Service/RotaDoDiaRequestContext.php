<?php

namespace App\Service;

use App\Common\Helpers\ColetorSelectHelper;

/** Resolve coletor/data/rota para rota do dia (painel web e API v1). */
class RotaDoDiaRequestContext
{
    /**
     * @param array<string,mixed> $usuario
     * @param array<string,mixed> $params query ou body
     * @return array{0:int,1:bool} coletorId, isAdminScope (segundo arg de RotaDoDiaService)
     */
    public static function resolveColetor(array $usuario, array $params): array
    {
        $userId = (int)($usuario['id'] ?? 0);
        if (ColetorSelectHelper::isColetorSession($usuario)) {
            return [$userId, false];
        }

        // API / app pode informar coletor_id; painel web usa só a sessão.
        $requested = (int)($params['coletor_id'] ?? 0);
        if ($requested > 0 && ColetorSelectHelper::isColetorAtivo($requested)) {
            return [$requested, false];
        }

        return [$userId, true];
    }

    /** @param array<string,mixed> $params */
    public static function resolveData(array $params): string
    {
        $data = trim((string)($params['data'] ?? ''));
        if ($data !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return $data;
        }

        return date('Y-m-d');
    }

    /** @param array<string,mixed> $params */
    public static function resolveRotaId(array $params): ?int
    {
        $raw = (int)($params['rota_id'] ?? 0);

        return $raw > 0 ? $raw : null;
    }

    /** @return array<string,mixed> */
    public static function mergeQueryAndBody(object $request): array
    {
        return array_merge($request->getQueryParams(), $request->getPostVars());
    }
}
