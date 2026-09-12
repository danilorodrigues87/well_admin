<?php

namespace App\Service;

use App\Common\ApiConfig;
use App\Common\Helpers\ModuleGateHelper;
use App\Model\Entity\Usuario as EntityUsuario;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class ApiAuthService
{
    private const ALGORITHM = 'HS256';

    /** @return array{token:string,expires_at:int,user:array<string,mixed>}|null */
    public static function login(string $email, string $password): ?array
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            return null;
        }

        $usuario = EntityUsuario::getByEmail($email);
        if (!$usuario || !password_verify($password, $usuario->senha)) {
            return null;
        }
        if ($usuario->ativo !== 's') {
            throw new \InvalidArgumentException('Seu acesso está inativo. Contate o administrador.');
        }

        $sessionUser = self::userToSessionArray($usuario);
        if (!ModuleGateHelper::podeAcessar('coleta_nova', $sessionUser)) {
            throw new \InvalidArgumentException('Usuário sem permissão para o app coletor.');
        }
        $sessionUser['modulos'] = ModuleGateHelper::getModulosEfetivos($sessionUser);

        $expiresAt = time() + ApiConfig::jwtTtlSeconds();
        $token = JWT::encode([
            'sub' => (int)$usuario->id,
            'email' => (string)$usuario->email,
            'iat' => time(),
            'exp' => $expiresAt,
        ], ApiConfig::jwtSecret(), self::ALGORITHM);

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
            'user' => ColetaApiPresenter::usuario($sessionUser),
        ];
    }

    public static function resolveUserIdFromToken(string $token): ?int
    {
        try {
            $decoded = JWT::decode($token, new Key(ApiConfig::jwtSecret(), self::ALGORITHM));
            $sub = (int)($decoded->sub ?? 0);

            return $sub > 0 ? $sub : null;
        } catch (ExpiredException|SignatureInvalidException|\UnexpectedValueException|\DomainException) {
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    public static function userFromToken(string $token): ?array
    {
        $userId = self::resolveUserIdFromToken($token);
        if ($userId === null) {
            return null;
        }

        $usuario = EntityUsuario::getById($userId);
        if (!$usuario || $usuario->ativo !== 's') {
            return null;
        }

        $sessionUser = self::userToSessionArray($usuario);
        $sessionUser['modulos'] = ModuleGateHelper::getModulosEfetivos($sessionUser);

        return $sessionUser;
    }

    /** @return array<string,mixed> */
    private static function userToSessionArray(object $usuario): array
    {
        return [
            'id' => (int)$usuario->id,
            'nome' => (string)$usuario->nome,
            'email' => (string)$usuario->email,
            'funcao_id' => (int)$usuario->funcao_id,
            'funcao_nome' => (string)($usuario->funcao_nome ?? ''),
            'is_admin' => !empty($usuario->is_admin),
        ];
    }
}
