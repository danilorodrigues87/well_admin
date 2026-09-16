<?php

namespace App\Common;

use App\Common\Helpers\CsrfHelper;

class SessionBootstrap
{
    private static bool $configured = false;

    public static function configure(): void
    {
        if (self::$configured) {
            return;
        }

        $tempo = 86400;
        $path = parse_url(defined('URL') ? URL : '', PHP_URL_PATH) ?: '';
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $cookiePath = ($path === '' || $path === '/') ? '/' : $path.'/';

        session_set_cookie_params([
            'lifetime' => $tempo,
            'path' => $cookiePath,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.gc_maxlifetime', (string)$tempo);

        $savePath = dirname(__DIR__).'/sessions';
        if (!is_dir($savePath)) {
            mkdir($savePath, 0777, true);
        }
        session_save_path($savePath);

        self::$configured = true;
    }

    public static function start(): void
    {
        self::configure();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
            CsrfHelper::init();
        }
    }
}
