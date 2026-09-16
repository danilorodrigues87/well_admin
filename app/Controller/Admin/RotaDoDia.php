<?php

namespace App\Controller\Admin;

use App\Common\Helpers\ColetorSelectHelper;
use App\Common\Helpers\CrudHelper;
use App\Common\Helpers\CsrfHelper;
use App\Common\MapsConfig;
use App\Model\Entity\Usuario as EntityUsuario;
use App\Service\RotaDoDiaService;
use App\Session\User\Login as SessionUser;
use App\Utils\View;

class RotaDoDia extends Page
{
    /** @return array<string,mixed> */
    private static function usuario(): array
    {
        return SessionUser::getUserLogedData()['usuario'] ?? [];
    }

    public static function index($request): string
    {
        $usuario = self::usuario();
        $isColetor = ColetorSelectHelper::isColetorSession($usuario);
        $coletorId = (int)($usuario['id'] ?? 0);

        $coletorSelect = '';
        if (!$isColetor) {
            $coletores = EntityUsuario::getColetoresAtivos();
            if ($coletorId <= 0 && $coletores !== []) {
                $coletorId = $coletores[0]->id;
            }
            $coletorSelect = '<div class="col-md-4 mb-2">
                <label class="form-label">Coletor</label>
                <select id="rota-coletor-id" class="form-select">'.ColetorSelectHelper::optionsHtml($coletorId, true).'</select>
            </div>';
        }

        $fallback = MapsConfig::originFallback();
        $content = View::render('admin/modules/rota_do_dia/index', [
            'csrf_field' => CsrfHelper::field(),
            'coletor_select' => $coletorSelect,
            'coletor_id' => $coletorId,
            'is_coletor' => $isColetor ? '1' : '0',
            'maps_configured' => MapsConfig::isConfigured() ? '1' : '0',
            'maps_key' => MapsConfig::apiKey(),
            'fallback_lat' => $fallback['lat'] ?? '',
            'fallback_lng' => $fallback['lng'] ?? '',
        ]);

        $scripts = '<script src="'.URL.'/resources/js/rota-mapa.js?v=20260916"></script>';

        return self::getPage('Rota do dia', $content, 'rota_dia', $scripts);
    }

    public static function paradas($request): string
    {
        [$coletorId, $isAdmin] = self::resolveColetor($request);

        return CrudHelper::jsonOk([
            'paradas' => RotaDoDiaService::listarParadas($coletorId, $isAdmin),
            'coletor_id' => $coletorId,
        ]);
    }

    public static function otimizar($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        [$coletorId, $isAdmin] = self::resolveColetor($request, $post);

        $originLat = (float)($post['origin_lat'] ?? 0);
        $originLng = (float)($post['origin_lng'] ?? 0);
        if ($originLat === 0.0 && $originLng === 0.0) {
            return CrudHelper::jsonError('Informe a localização de origem (GPS ou fallback).');
        }

        $clienteIds = [];
        if (!empty($post['cliente_ids']) && is_array($post['cliente_ids'])) {
            $clienteIds = array_map('intval', $post['cliente_ids']);
        }

        try {
            $result = RotaDoDiaService::otimizar($coletorId, $isAdmin, $originLat, $originLng, $clienteIds);

            return CrudHelper::jsonOk($result);
        } catch (\Throwable $e) {
            return CrudHelper::jsonError($e->getMessage());
        }
    }

    public static function salvarOrdem($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        [$coletorId] = self::resolveColetor($request, $post);
        $ordem = [];
        if (!empty($post['ordem']) && is_array($post['ordem'])) {
            foreach ($post['ordem'] as $item) {
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
            RotaDoDiaService::salvarOrdem($coletorId, $ordem, 'manual');

            return CrudHelper::jsonOk(['message' => 'Ordem salva.']);
        } catch (\Throwable $e) {
            return CrudHelper::jsonError($e->getMessage());
        }
    }

    /** @return array{0:int,1:bool} */
    private static function resolveColetor($request, ?array $post = null): array
    {
        $usuario = self::usuario();
        $isAdmin = !empty($usuario['is_admin']);
        $isColetor = ColetorSelectHelper::isColetorSession($usuario);

        if ($isColetor) {
            return [(int)($usuario['id'] ?? 0), false];
        }

        $data = $post ?? $request->getQueryParams();
        $coletorId = (int)($data['coletor_id'] ?? 0);
        if ($coletorId <= 0) {
            $coletores = EntityUsuario::getColetoresAtivos();
            $coletorId = $coletores !== [] ? $coletores[0]->id : (int)($usuario['id'] ?? 0);
        }

        if ($coletorId > 0 && ColetorSelectHelper::isColetorAtivo($coletorId)) {
            return [$coletorId, false];
        }

        return [$coletorId, $isAdmin];
    }
}
