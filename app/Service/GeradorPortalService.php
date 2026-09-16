<?php

namespace App\Service;

use App\Common\GeradorScope;
use App\Model\Db\Pagination;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\InterCobranca as EntityInterCobranca;

class GeradorPortalService
{
    /** @return array{items:array<int,array<string,mixed>>,pagination:array<string,mixed>} */
    public static function listColetas(int $page = 1, int $perPage = 15, ?string $status = null): array
    {
        $clienteId = GeradorScope::getClienteId();
        $where = 'c.cliente_id = ? AND c.status != ?';
        $params = [$clienteId, 'cancelada'];

        $status = trim((string)$status);
        if ($status !== '' && in_array($status, ['rascunho', 'finalizada'], true)) {
            $where .= ' AND c.status = ?';
            $params[] = $status;
        }

        $pagination = new Pagination(EntityColeta::count($where, $params), $page, $perPage);
        $rows = EntityColeta::list($where, $params, $pagination->getLimit());

        return [
            'items' => array_map(fn ($c) => GeradorApiPresenter::coletaResumo($c), $rows),
            'pagination' => [
                'page' => $pagination->getCurrentPage(),
                'pages' => $pagination->getTotalPages(),
            ],
        ];
    }

    /** @return array<string,mixed>|null */
    public static function coletaDetalhe(int $coletaId): ?array
    {
        if (!GeradorScope::pertenceColeta($coletaId)) {
            return null;
        }

        try {
            return GeradorApiPresenter::coletaDetalhe(ColetaService::detalhar($coletaId));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** @return array{items:array<int,array<string,mixed>>,pagination:array<string,mixed>} */
    public static function listBoletos(int $page = 1, int $perPage = 15): array
    {
        $clienteId = GeradorScope::getClienteId();
        $where = 'ic.cliente_id = ?';
        $params = [$clienteId];

        $pagination = new Pagination(EntityInterCobranca::countHistorico($where, $params), $page, $perPage);
        $rows = EntityInterCobranca::listHistorico($where, $params, $pagination->getLimit());

        return [
            'items' => array_map(fn ($b) => GeradorApiPresenter::boleto($b), $rows),
            'pagination' => [
                'page' => $pagination->getCurrentPage(),
                'pages' => $pagination->getTotalPages(),
            ],
        ];
    }

    public static function getBoleto(int $id): ?EntityInterCobranca
    {
        $b = EntityInterCobranca::getById($id);
        if (!$b || (int)$b->cliente_id !== GeradorScope::getClienteId()) {
            return null;
        }

        return $b;
    }

    /** @return array<string,mixed>|null */
    public static function boletoDetalhe(int $id): ?array
    {
        $b = self::getBoleto($id);
        if (!$b) {
            return null;
        }

        return GeradorApiPresenter::boleto($b);
    }
}
