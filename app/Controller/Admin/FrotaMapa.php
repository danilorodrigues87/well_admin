<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Common\MapsConfig;
use App\Service\FrotaLocalizacaoService;
use App\Utils\View;

class FrotaMapa extends Page
{
    public static function index($request): string
    {
        $fallback = MapsConfig::originFallback();
        $content = View::render('admin/modules/frota/mapa/index', [
            'maps_configured' => MapsConfig::isConfigured() ? '1' : '0',
            'maps_key' => MapsConfig::apiKey(),
            'fallback_lat' => $fallback['lat'] ?? '',
            'fallback_lng' => $fallback['lng'] ?? '',
        ]);

        $scripts = '<script src="'.URL.'/resources/js/frota-mapa.js?v=20260918b"></script>';

        return self::getPage('Mapa da frota', $content, 'frota_mapa', $scripts);
    }

    public static function post($request): string
    {
        $post = $request->getPostVars();
        $acao = (string)($post['acao'] ?? '');

        return match ($acao) {
            'posicoes' => self::posicoes($post),
            default => CrudHelper::jsonError('Ação inválida'),
        };
    }

    /** @param array<string,mixed> $post */
    private static function posicoes(array $post): string
    {
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $minutos = (int)($post['minutos'] ?? 120);

        return CrudHelper::jsonOk([
            'posicoes' => FrotaLocalizacaoService::listarUltimasPosicoes($minutos),
        ]);
    }
}
