<?php

namespace App\Session\Gerador;

use App\Common\SessionBootstrap;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\ClienteUsuario;

class Login
{
    private const SESSION_KEY = 'well-eco-gerador';

    private static function init(): void
    {
        SessionBootstrap::start();
    }

    public static function login(ClienteUsuario $usuario, EntityCliente $cliente): bool
    {
        self::init();
        if ($usuario->id <= 0 || $cliente->id <= 0) {
            return false;
        }

        $_SESSION[self::SESSION_KEY] = [
            'cliente_usuario_id' => $usuario->id,
            'cliente_id' => $cliente->id,
            'operadora_id' => $usuario->operadora_id,
            'nome' => $usuario->nome,
            'email' => $usuario->email,
            'cliente_nome' => $cliente->nome_fantasia,
        ];

        return true;
    }

    public static function isLogged(): bool
    {
        self::init();

        return isset($_SESSION[self::SESSION_KEY]['cliente_usuario_id']);
    }

    /** @return array<string, mixed>|null */
    public static function getData(): ?array
    {
        self::init();
        if (!self::isLogged()) {
            return null;
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function logout(): void
    {
        self::init();
        unset($_SESSION[self::SESSION_KEY]);
    }
}
