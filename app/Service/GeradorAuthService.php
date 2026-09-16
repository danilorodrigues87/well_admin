<?php

namespace App\Service;

use App\Common\ApiConfig;
use App\Common\OperadoraScope;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\ClienteUsuario;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;

class GeradorAuthService
{
    private const ALGORITHM = 'HS256';
    private const TIPO = 'gerador';

    /** @return array{token:string,expires_at:int,user:array<string,mixed>}|null */
    public static function login(string $email, string $password): ?array
    {
        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            return null;
        }

        $usuario = ClienteUsuario::getByEmail($email);
        if (!$usuario || !$usuario->ativo || !password_verify($password, $usuario->senha_hash)) {
            return null;
        }

        OperadoraScope::setOverride($usuario->operadora_id);
        $cliente = EntityCliente::getById($usuario->cliente_id);
        if (!$cliente || $cliente->status === 'inativo') {
            throw new \InvalidArgumentException('Cliente inativo. Contate a operadora.');
        }

        ClienteUsuario::touchUltimoLogin($usuario->id);

        $sessionUser = self::userToArray($usuario, $cliente);
        $expiresAt = time() + ApiConfig::jwtTtlSeconds();
        $token = JWT::encode([
            'sub' => $usuario->id,
            'tipo' => self::TIPO,
            'cliente_id' => $cliente->id,
            'operadora_id' => $usuario->operadora_id,
            'email' => $usuario->email,
            'iat' => time(),
            'exp' => $expiresAt,
        ], ApiConfig::jwtSecret(), self::ALGORITHM);

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
            'user' => GeradorApiPresenter::usuario($sessionUser),
        ];
    }

    /** @return array<string,mixed>|null */
    public static function userFromToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(ApiConfig::jwtSecret(), self::ALGORITHM));
            if (($decoded->tipo ?? '') !== self::TIPO) {
                return null;
            }
            $userId = (int)($decoded->sub ?? 0);
            $operadoraId = (int)($decoded->operadora_id ?? 1);
            if ($userId <= 0) {
                return null;
            }

            OperadoraScope::setOverride($operadoraId);
            $usuario = ClienteUsuario::getById($userId);
            if (!$usuario || !$usuario->ativo) {
                return null;
            }

            $cliente = EntityCliente::getById($usuario->cliente_id);
            if (!$cliente || $cliente->status === 'inativo') {
                return null;
            }

            return self::userToArray($usuario, $cliente);
        } catch (ExpiredException|SignatureInvalidException|\UnexpectedValueException|\DomainException) {
            return null;
        }
    }

    /** @return array<string,mixed> */
    private static function userToArray(ClienteUsuario $usuario, EntityCliente $cliente): array
    {
        return [
            'cliente_usuario_id' => $usuario->id,
            'id' => $usuario->id,
            'nome' => $usuario->nome,
            'email' => $usuario->email,
            'cliente_id' => $cliente->id,
            'cliente_nome' => $cliente->nome_fantasia,
            'operadora_id' => $usuario->operadora_id,
        ];
    }
}
