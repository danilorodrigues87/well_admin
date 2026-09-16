<?php

namespace App\Session\User;

use App\Common\Helpers\ModuleGateHelper;
use App\Common\SessionBootstrap;
use App\Model\Entity\Operadora as EntityOperadora;
use App\Model\Entity\Usuario as EntityUsuario;

class Login
{
    private const SESSION_KEY = 'well-eco-user';
    private static bool $sessionSynced = false;

    private static function init(): void
    {
        SessionBootstrap::start();
    }

    public static function login(object $usuario): bool
    {
        self::init();
        if (!isset($usuario->id, $usuario->email)) {
            return false;
        }

        $operadoraId = (int)($usuario->operadora_id ?? 1);
        $operadoraSnap = null;
        $operadora = EntityOperadora::getById($operadoraId > 0 ? $operadoraId : 1);
        if ($operadora) {
            $operadoraSnap = $operadora->toSessionSnapshot();
        }

        $_SESSION[self::SESSION_KEY] = [
            'id' => (int)$usuario->id,
            'nome' => (string)$usuario->nome,
            'email' => (string)$usuario->email,
            'funcao_id' => (int)$usuario->funcao_id,
            'funcao_nome' => (string)($usuario->funcao_nome ?? ''),
            'is_admin' => !empty($usuario->is_admin),
            'operadora_id' => $operadoraId > 0 ? $operadoraId : 1,
            'operadora' => $operadoraSnap,
        ];

        return true;
    }

    public static function syncSessionFromDatabase(): bool
    {
        self::init();
        if (self::$sessionSynced) {
            return isset($_SESSION[self::SESSION_KEY]);
        }
        self::$sessionSynced = true;

        if (!isset($_SESSION[self::SESSION_KEY]['id'])) {
            return false;
        }

        $usuario = EntityUsuario::getById((int)$_SESSION[self::SESSION_KEY]['id']);
        if (!$usuario || $usuario->ativo !== 's') {
            unset($_SESSION[self::SESSION_KEY]);
            return false;
        }

        $_SESSION[self::SESSION_KEY]['nome'] = $usuario->nome;
        $_SESSION[self::SESSION_KEY]['email'] = $usuario->email;
        $_SESSION[self::SESSION_KEY]['funcao_id'] = (int)$usuario->funcao_id;
        $_SESSION[self::SESSION_KEY]['funcao_nome'] = (string)($usuario->funcao_nome ?? '');
        $_SESSION[self::SESSION_KEY]['is_admin'] = !empty($usuario->is_admin);
        $_SESSION[self::SESSION_KEY]['operadora_id'] = (int)($usuario->operadora_id ?? 1);
        $operadora = EntityOperadora::getById((int)($_SESSION[self::SESSION_KEY]['operadora_id'] ?? 1));
        $_SESSION[self::SESSION_KEY]['operadora'] = $operadora ? $operadora->toSessionSnapshot() : null;

        return true;
    }

    public static function isUserLogged(): bool
    {
        self::init();
        return isset($_SESSION[self::SESSION_KEY]);
    }

    public static function getUserLogedData(): ?array
    {
        self::init();
        if (!self::isUserLogged() || !self::syncSessionFromDatabase()) {
            return null;
        }

        $usuario = $_SESSION[self::SESSION_KEY];
        $usuario['modulos'] = ModuleGateHelper::getModulosEfetivos($usuario);

        return ['usuario' => $usuario];
    }

    public static function logout(): bool
    {
        self::init();
        unset($_SESSION[self::SESSION_KEY]);
        return true;
    }
}
