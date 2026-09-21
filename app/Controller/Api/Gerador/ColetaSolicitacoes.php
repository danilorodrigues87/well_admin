<?php

namespace App\Controller\Api\Gerador;

use App\Common\GeradorScope;
use App\Common\Helpers\ApiHelper;
use App\Controller\Api\BaseApi;
use App\Http\Response;
use App\Model\Entity\ColetaSolicitacao;
use App\Service\ColetaSolicitacaoService;
use App\Session\Gerador\Login as GeradorSession;
use InvalidArgumentException;

class ColetaSolicitacoes extends BaseApi
{
    public static function index($request): Response
    {
        $clienteId = GeradorScope::getClienteId();
        $items = [];
        foreach (ColetaSolicitacao::listByCliente($clienteId, 50) as $s) {
            $items[] = self::serialize($s);
        }

        return ApiHelper::ok([
            'items' => $items,
            'cota' => ColetaSolicitacaoService::resumoCota($clienteId),
        ]);
    }

    public static function store($request): Response
    {
        $body = $request->getPostVars();
        $session = GeradorSession::getData() ?? [];
        try {
            $id = ColetaSolicitacaoService::criar(
                GeradorScope::getClienteId(),
                (int)($session['cliente_usuario_id'] ?? 0),
                trim((string)($body['data_desejada'] ?? '')),
                trim((string)($body['motivo'] ?? ''))
            );
            $s = ColetaSolicitacao::getById($id);

            return ApiHelper::ok(['item' => $s ? self::serialize($s) : null], 201);
        } catch (InvalidArgumentException $e) {
            return ApiHelper::fail('validation', $e->getMessage(), 422);
        }
    }

    public static function cancel($request, int $id): Response
    {
        try {
            ColetaSolicitacaoService::cancelar($id, GeradorScope::getClienteId());

            return ApiHelper::ok(['message' => 'Cancelada.']);
        } catch (InvalidArgumentException $e) {
            return ApiHelper::fail('validation', $e->getMessage(), 422);
        }
    }

    /** @return array<string, mixed> */
    private static function serialize(ColetaSolicitacao $s): array
    {
        return [
            'id' => $s->id,
            'data_desejada' => $s->data_desejada,
            'data_aprovada' => $s->data_aprovada,
            'status' => $s->status,
            'tipo' => $s->tipo,
            'motivo_gerador' => $s->motivo_gerador,
            'resposta_admin' => $s->resposta_admin,
            'valor_cobranca_extra' => $s->valor_cobranca_extra,
            'created_at' => $s->created_at,
        ];
    }
}
