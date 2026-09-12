<?php

namespace App\Controller\Api;

use App\Common\Helpers\ApiHelper;
use App\Http\ApiContext;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\ColetaEvidencia as EntityColetaEvidencia;
use App\Model\Entity\ColetaItem as EntityColetaItem;
use App\Service\ColetaApiPresenter;
use App\Service\ColetaService;

class Coletas extends BaseApi
{
    public static function index($request): \App\Http\Response
    {
        $query = $request->getQueryParams();
        $page = max(1, (int)($query['page'] ?? 1));
        $perPage = min(50, max(1, (int)($query['per_page'] ?? 15)));
        $status = trim((string)($query['status'] ?? ''));

        $where = '1=1';
        $params = [];

        if (!ApiContext::isAdmin()) {
            $where .= ' AND c.coletor_id = ?';
            $params[] = ApiContext::userId();
        }
        if (in_array($status, ['rascunho', 'finalizada', 'cancelada'], true)) {
            $where .= ' AND c.status = ?';
            $params[] = $status;
        }

        $total = EntityColeta::count($where, $params);
        $offset = ($page - 1) * $perPage;
        $items = EntityColeta::list($where, $params, $offset.','.$perPage);

        return ApiHelper::ok([
            'items' => array_map(fn ($c) => ColetaApiPresenter::coletaResumo($c), $items),
            'pagination' => ApiHelper::paginationMeta($total, $page, $perPage),
        ]);
    }

    public static function show($request, int $id): \App\Http\Response
    {
        try {
            $detalhe = ColetaService::detalhar($id);
            if ($err = self::assertColetaAccess($detalhe['coleta'])) {
                return $err;
            }

            return ApiHelper::ok(ColetaApiPresenter::coletaDetalhe($detalhe));
        } catch (\InvalidArgumentException $e) {
            return ApiHelper::fail('not_found', $e->getMessage(), 404);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiColetas::show');
        }
    }

    public static function store($request): \App\Http\Response
    {
        $body = $request->getPostVars();
        $clienteId = (int)($body['cliente_id'] ?? 0);

        try {
            $coletaId = ColetaService::iniciarRascunho($clienteId, ApiContext::userId());
            $detalhe = ColetaService::detalhar($coletaId);

            return ApiHelper::ok(ColetaApiPresenter::coletaDetalhe($detalhe), 201);
        } catch (\InvalidArgumentException $e) {
            return self::handleInvalidArgument($e);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiColetas::store');
        }
    }

    public static function updateTransporte($request, int $id): \App\Http\Response
    {
        $coleta = EntityColeta::getById($id);
        if (!$coleta) {
            return ApiHelper::fail('not_found', 'Coleta não encontrada.', 404);
        }
        if ($err = self::assertColetaAccess($coleta)) {
            return $err;
        }

        try {
            ColetaService::salvarTransporte($id, $request->getPostVars());
            $detalhe = ColetaService::detalhar($id);

            return ApiHelper::ok(ColetaApiPresenter::coletaDetalhe($detalhe));
        } catch (\InvalidArgumentException $e) {
            return self::handleInvalidArgument($e);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiColetas::updateTransporte');
        }
    }

    public static function addItem($request, int $id): \App\Http\Response
    {
        $coleta = EntityColeta::getById($id);
        if (!$coleta) {
            return ApiHelper::fail('not_found', 'Coleta não encontrada.', 404);
        }
        if ($err = self::assertColetaAccess($coleta)) {
            return $err;
        }

        $body = $request->getPostVars();

        try {
            ColetaService::adicionarItem(
                $id,
                (int)($body['tipo_residuo_id'] ?? 0),
                (float)str_replace(',', '.', (string)($body['quantidade'] ?? 0)),
                (string)($body['unidade'] ?? 'kg')
            );

            return ApiHelper::ok([
                'itens' => array_map(
                    fn ($i) => ColetaApiPresenter::item($i),
                    EntityColetaItem::getByColetaId($id)
                ),
            ]);
        } catch (\InvalidArgumentException $e) {
            return self::handleInvalidArgument($e);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiColetas::addItem');
        }
    }

    public static function removeItem($request, int $id, int $itemId): \App\Http\Response
    {
        $coleta = EntityColeta::getById($id);
        if (!$coleta) {
            return ApiHelper::fail('not_found', 'Coleta não encontrada.', 404);
        }
        if ($err = self::assertColetaAccess($coleta)) {
            return $err;
        }

        try {
            ColetaService::removerItem($id, $itemId);

            return ApiHelper::ok([
                'itens' => array_map(
                    fn ($i) => ColetaApiPresenter::item($i),
                    EntityColetaItem::getByColetaId($id)
                ),
            ]);
        } catch (\InvalidArgumentException $e) {
            return self::handleInvalidArgument($e);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiColetas::removeItem');
        }
    }

    public static function finalizar($request, int $id): \App\Http\Response
    {
        $coleta = EntityColeta::getById($id);
        if (!$coleta) {
            return ApiHelper::fail('not_found', 'Coleta não encontrada.', 404);
        }
        if ($err = self::assertColetaAccess($coleta)) {
            return $err;
        }

        $files = [];
        foreach (['evidencia_1', 'evidencia_2', 'evidencia_3'] as $key) {
            if (!empty($_FILES[$key]['tmp_name'])) {
                $files[] = $_FILES[$key];
            }
        }

        try {
            $numeroMtr = ColetaService::finalizar($id, $files);
            $detalhe = ColetaService::detalhar($id);

            return ApiHelper::ok([
                'numero_mtr' => $numeroMtr,
                'coleta' => ColetaApiPresenter::coletaDetalhe($detalhe),
            ]);
        } catch (\InvalidArgumentException $e) {
            return self::handleInvalidArgument($e);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiColetas::finalizar');
        }
    }

    public static function cancelar($request, int $id): \App\Http\Response
    {
        $coleta = EntityColeta::getById($id);
        if (!$coleta) {
            return ApiHelper::fail('not_found', 'Coleta não encontrada.', 404);
        }
        if ($err = self::assertColetaAccess($coleta)) {
            return $err;
        }

        try {
            ColetaService::cancelar($id);

            return ApiHelper::ok(['message' => 'Coleta cancelada.']);
        } catch (\InvalidArgumentException $e) {
            return self::handleInvalidArgument($e);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiColetas::cancelar');
        }
    }

    public static function evidencia($request, int $id, int $ordem): \App\Http\Response
    {
        $coleta = EntityColeta::getById($id);
        if (!$coleta) {
            return ApiHelper::fail('not_found', 'Coleta não encontrada.', 404);
        }
        if ($err = self::assertColetaAccess($coleta)) {
            return $err;
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

        $path = dirname(__DIR__, 3).'/storage/'.$match->arquivo;
        if (!is_file($path)) {
            return ApiHelper::fail('not_found', 'Arquivo não encontrado.', 404);
        }

        $mime = $match->mime !== '' ? $match->mime : (mime_content_type($path) ?: 'application/octet-stream');

        return new \App\Http\Response(200, file_get_contents($path), $mime);
    }
}
