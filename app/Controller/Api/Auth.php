<?php

namespace App\Controller\Api;

use App\Common\ApiAppModules;
use App\Common\ApiConfig;
use App\Common\Helpers\ApiHelper;
use App\Service\ApiAuthService;
use App\Service\ColetaApiPresenter;

class Auth extends BaseApi
{
    public static function login($request): \App\Http\Response
    {
        if (!ApiConfig::isEnabled()) {
            return ApiHelper::fail('api_disabled', 'API desabilitada.', 503);
        }

        $body = $request->getPostVars();
        $email = trim((string)($body['email'] ?? ''));
        $password = (string)($body['password'] ?? $body['senha'] ?? '');

        try {
            $result = ApiAuthService::login($email, $password);
            if ($result === null) {
                return ApiHelper::fail('invalid_credentials', 'E-mail ou senha inválidos.', 401);
            }

            return ApiHelper::ok($result);
        } catch (\InvalidArgumentException $e) {
            return ApiHelper::fail('login_denied', $e->getMessage(), 403);
        } catch (\RuntimeException $e) {
            return ApiHelper::fail('server_error', $e->getMessage(), 500);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiAuth::login');
        }
    }

    public static function me($request): \App\Http\Response
    {
        $user = self::user();
        if ($user === []) {
            return ApiHelper::fail('unauthorized', 'Não autenticado.', 401);
        }

        $modulos = $user['modulos'] ?? [];
        if (!ApiAppModules::temAcessoApp($modulos)) {
            return ApiHelper::fail('forbidden', 'Sem permissão para o app.', 403);
        }

        return ApiHelper::ok([
            'user' => ColetaApiPresenter::usuario($user),
        ]);
    }
}
