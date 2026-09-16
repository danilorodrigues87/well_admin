<?php

namespace App\Controller\Api\Gerador;

use App\Common\ApiConfig;
use App\Common\Helpers\ApiHelper;
use App\Controller\Api\BaseApi;
use App\Http\GeradorContext;
use App\Service\GeradorAuthService;
use App\Service\GeradorApiPresenter;

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
            $result = GeradorAuthService::login($email, $password);
            if ($result === null) {
                return ApiHelper::fail('invalid_credentials', 'E-mail ou senha inválidos.', 401);
            }

            return ApiHelper::ok($result);
        } catch (\InvalidArgumentException $e) {
            return ApiHelper::fail('login_denied', $e->getMessage(), 403);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'GeradorAuth::login');
        }
    }

    public static function me($request): \App\Http\Response
    {
        $user = GeradorContext::user();
        if ($user === null) {
            return ApiHelper::fail('unauthorized', 'Não autenticado.', 401);
        }

        return ApiHelper::ok([
            'user' => GeradorApiPresenter::usuario($user),
        ]);
    }
}
