<?php

namespace App\Service\Inter;

use App\Model\Entity\InterCobranca as EntityInterCobranca;

class InterWebhookService
{
    /**
     * @return array{ok:bool,processed:int,skipped:int,errors:list<string>}
     */
    public static function processCobrancaPayload(string $rawBody): array
    {
        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'processed' => 0, 'skipped' => 0, 'errors' => ['JSON inválido']];
        }

        $events = self::normalizeEvents($decoded);
        $processed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($events as $event) {
            $codigo = trim((string)($event['codigoSolicitacao'] ?? $event['codigo_solicitacao'] ?? ''));
            if ($codigo === '') {
                $skipped++;
                continue;
            }

            $entity = EntityInterCobranca::getByCodigo($codigo);
            if (!$entity) {
                $skipped++;
                continue;
            }

            $situacao = (string)($event['situacao'] ?? $event['status'] ?? '');
            $novoStatus = self::mapSituacaoInter($situacao);
            if ($novoStatus === null) {
                $skipped++;
                continue;
            }

            if ($entity->status === $novoStatus) {
                $skipped++;
                continue;
            }

            $entity->update(['status' => $novoStatus]);
            $processed++;
        }

        return ['ok' => true, 'processed' => $processed, 'skipped' => $skipped, 'errors' => $errors];
    }

    /** @return list<array<string,mixed>> */
    private static function normalizeEvents(array $decoded): array
    {
        if ($decoded === []) {
            return [];
        }
        if (isset($decoded['codigoSolicitacao']) || isset($decoded['codigo_solicitacao'])) {
            return [$decoded];
        }
        if (isset($decoded['cobrancas']) && is_array($decoded['cobrancas'])) {
            return $decoded['cobrancas'];
        }
        if (isset($decoded[0]) && is_array($decoded[0])) {
            return $decoded;
        }

        return [$decoded];
    }

    private static function mapSituacaoInter(string $situacao): ?string
    {
        $s = mb_strtoupper(trim($situacao));

        return match ($s) {
            'RECEBIDO', 'MARCADO_RECEBIDO', 'PAGO', 'LIQUIDADO' => 'PAGO',
            'CANCELADO', 'CANCELADA' => 'CANCELADO',
            'EXPIRADO', 'ATRASADO', 'VENCIDO', 'VENCIDA' => 'VENCIDO',
            default => null,
        };
    }
}
