<?php

namespace App\Controller\PublicPages;

class Pwa
{
    public static function manifest(): string
    {
        $base = rtrim(URL, '/');
        $icons = [
            ['src' => $base.'/resources/assets/imgs/logo.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => $base.'/resources/assets/imgs/logo.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ];

        return json_encode([
            'name' => 'Well S.A. — Operacional',
            'short_name' => 'Well Ops',
            'description' => 'Painel operacional Well Soluções Ambientais',
            'start_url' => $base.'/painel',
            'scope' => $base.'/',
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#1B5E20',
            'theme_color' => '#1B5E20',
            'lang' => 'pt-BR',
            'icons' => $icons,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function serviceWorker(): string
    {
        $path = dirname(__DIR__, 3).'/resources/sw.js';
        if (!is_file($path)) {
            return "// service worker missing\n";
        }

        $js = file_get_contents($path);
        if ($js === false) {
            return "// service worker unreadable\n";
        }

        $cacheVer = 'well-pwa-v1';
        $js = str_replace('__WELL_PWA_CACHE__', $cacheVer, $js);
        $js = str_replace('__WELL_PWA_BASE__', rtrim(URL, '/'), $js);

        return $js;
    }
}
