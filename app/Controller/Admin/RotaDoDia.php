<?php

namespace App\Controller\Admin;

use App\Common\Helpers\ColetorSelectHelper;
use App\Common\Helpers\CrudHelper;
use App\Common\Helpers\CsrfHelper;
use App\Common\MapsConfig;
use App\Model\Entity\Rota as EntityRota;
use App\Model\Entity\Usuario as EntityUsuario;
use App\Service\FrotaLocalizacaoService;
use App\Service\RotaDoDiaService;
use App\Service\RotaParadaStatusService;
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
        $rotaSelect = '';
        if (!$isColetor) {
            $coletores = EntityUsuario::getColetoresAtivos();
            if ($coletorId <= 0 && $coletores !== []) {
                $coletorId = $coletores[0]->id;
            }
            $coletorSelect = '<div class="col-md-3 mb-2">
                <label class="form-label">Coletor</label>
                <select id="rota-coletor-id" class="form-select">'.ColetorSelectHelper::optionsHtml($coletorId, true).'</select>
            </div>';
            $rotasOpts = '<option value="">Todas as rotas</option>';
            foreach (EntityRota::getAllActive() as $r) {
                $rotasOpts .= '<option value="'.$r->id.'">'.htmlspecialchars($r->nome, ENT_QUOTES, 'UTF-8').'</option>';
            }
            $rotaSelect = '<div class="col-md-3 mb-2">
                <label class="form-label">Rota cadastral</label>
                <select id="rota-filtro-id" class="form-select">'.$rotasOpts.'</select>
            </div>';
        }

        $fallback = MapsConfig::originFallback();
        $content = View::render('admin/modules/rota_do_dia/index', [
            'csrf_field' => CsrfHelper::field(),
            'coletor_select' => $coletorSelect,
            'rota_select' => $rotaSelect,
            'coletor_id' => $coletorId,
            'is_coletor' => $isColetor ? '1' : '0',
            'maps_configured' => MapsConfig::isConfigured() ? '1' : '0',
            'maps_server_key_alert_class' => MapsConfig::hasDedicatedServerKey() ? 'd-none' : '',
            'maps_key' => MapsConfig::apiKey(),
            'fallback_lat' => $fallback['lat'] ?? '',
            'fallback_lng' => $fallback['lng'] ?? '',
            'data_hoje' => date('Y-m-d'),
        ]);

        $scripts = '<script src="'.URL.'/resources/js/rota-mapa.js?v=20260918b"></script>';

        return self::getPage('Rota do dia', $content, 'rota_dia', $scripts);
    }

    public static function paradas($request): string
    {
        [$coletorId, $isAdmin] = self::resolveColetor($request);
        $data = self::resolveData($request);
        $rotaId = self::resolveRotaId($request);

        $paradas = RotaDoDiaService::listarParadas($coletorId, $isAdmin, $data, $rotaId);

        return CrudHelper::jsonOk([
            'paradas' => $paradas,
            'coletor_id' => $coletorId,
            'data' => $data,
            'total' => count($paradas),
            'rota_id' => $rotaId ?? 0,
            'sem_rota' => !$isAdmin && $coletorId > 0 && !\App\Service\RotaScopeService::coletorTemRota($coletorId),
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

        $data = self::resolveData($request, $post);
        $rotaId = self::resolveRotaId($request, $post);

        try {
            $result = RotaDoDiaService::otimizar($coletorId, $isAdmin, $originLat, $originLng, $clienteIds, $data, $rotaId);

            return CrudHelper::jsonOk($result);
        } catch (\Throwable $e) {
            return CrudHelper::jsonError($e->getMessage());
        }
    }

    public static function geocodeParadas($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        [$coletorId, $isAdmin] = self::resolveColetor($request, $post);
        $data = self::resolveData($request, $post);
        $rotaId = self::resolveRotaId($request, $post);

        if (!MapsConfig::isServerConfigured()) {
            return CrudHelper::jsonError(
                'Configure GOOGLE_MAPS_SERVER_API_KEY no .env (Geocoding API + Routes API).'
            );
        }

        $result = RotaDoDiaService::atualizarGeocodeParadas($coletorId, $isAdmin, $data, $rotaId);
        $msg = $result['ok'].' cliente(s) geolocalizado(s).';
        if ($result['falha'] > 0) {
            $msg .= ' '.$result['falha'].' sem coordenadas — confira link Maps ou endereço no cadastro.';
        }

        return CrudHelper::jsonOk([
            'message' => $msg,
            'paradas' => $result['paradas'],
            'geocode_ok' => $result['ok'],
            'geocode_falha' => $result['falha'],
        ]);
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

        $data = self::resolveData($request, $post);

        try {
            RotaDoDiaService::salvarOrdem($coletorId, $ordem, 'manual', $data);

            return CrudHelper::jsonOk(['message' => 'Ordem salva.']);
        } catch (\Throwable $e) {
            return CrudHelper::jsonError($e->getMessage());
        }
    }

    public static function registrarPosicao($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $usuario = self::usuario();
        $userId = (int)($usuario['id'] ?? 0);
        if ($userId <= 0) {
            return CrudHelper::jsonError('Sessão inválida.');
        }

        $lat = (float)($post['latitude'] ?? $post['lat'] ?? 0);
        $lng = (float)($post['longitude'] ?? $post['lng'] ?? 0);

        try {
            FrotaLocalizacaoService::registrarPosicao($userId, $lat, $lng, [
                'accuracy_m' => isset($post['accuracy_m']) ? (float)$post['accuracy_m'] : null,
                'heading' => isset($post['heading']) ? (float)$post['heading'] : null,
                'speed_mps' => isset($post['speed_mps']) ? (float)$post['speed_mps'] : null,
                'fonte' => 'web',
            ]);

            return CrudHelper::jsonOk(['message' => 'Posição registrada.']);
        } catch (\InvalidArgumentException $e) {
            return CrudHelper::jsonError($e->getMessage());
        } catch (\Throwable $e) {
            return CrudHelper::jsonError('Não foi possível registrar a posição.');
        }
    }

    public static function paradaStatus($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        [$coletorId, $isAdmin] = self::resolveColetor($request, $post);
        $data = self::resolveData($request, $post);
        $clienteId = (int)($post['cliente_id'] ?? 0);
        $status = trim((string)($post['status'] ?? ''));

        try {
            RotaParadaStatusService::definirStatus($coletorId, $data, $clienteId, $status);
            $paradas = RotaDoDiaService::listarParadas(
                $coletorId,
                $isAdmin,
                $data,
                self::resolveRotaId($request, $post)
            );

            return CrudHelper::jsonOk([
                'message' => 'Status atualizado.',
                'paradas' => $paradas,
            ]);
        } catch (\InvalidArgumentException $e) {
            return CrudHelper::jsonError($e->getMessage());
        } catch (\Throwable $e) {
            return CrudHelper::jsonError('Não foi possível atualizar o status.');
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

    private static function resolveData($request, ?array $post = null): string
    {
        $data = trim((string)(($post ?? $request->getQueryParams())['data'] ?? ''));
        if ($data !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return $data;
        }

        return date('Y-m-d');
    }

    private static function resolveRotaId($request, ?array $post = null): ?int
    {
        $raw = (int)(($post ?? $request->getQueryParams())['rota_id'] ?? 0);

        return $raw > 0 ? $raw : null;
    }
}
