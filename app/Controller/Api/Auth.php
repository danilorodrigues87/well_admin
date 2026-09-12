<?php

namespace App\Controller\Api;

use App\Common\ApiConfig;
use App\Common\Helpers\ApiHelper;
use App\Common\Helpers\ModuleGateHelper;
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

        // #region agent log
        $debugLog = static fn (string $message, array $data = [], string $hypothesisId = 'H3') => @file_put_contents(
            dirname(__DIR__, 3).'/debug-644d61.log',
            json_encode([
                'sessionId' => '644d61',
                'runId' => 'pre-fix',
                'hypothesisId' => $hypothesisId,
                'location' => 'Auth.php:login',
                'message' => $message,
                'data' => $data,
                'timestamp' => (int) round(microtime(true) * 1000),
            ], JSON_UNESCAPED_UNICODE)."\n",
            FILE_APPEND
        );
        $debugLog('login_request_received', [
            'method' => $request->getHttpMethod(),
            'origin' => $_SERVER['HTTP_ORIGIN'] ?? '',
            'userAgent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120),
            'emailProvided' => $email !== '',
            'passwordProvided' => $password !== '',
            'bodyKeys' => array_keys(is_array($body) ? $body : []),
        ], 'H1');
        // #endregion

        try {
            $result = ApiAuthService::login($email, $password);
            if ($result === null) {
                // #region agent log
                $debugLog('login_failed', ['reason' => 'invalid_credentials'], 'H3');
                // #endregion
                return ApiHelper::fail('invalid_credentials', 'E-mail ou senha inválidos.', 401);
            }

            // #region agent log
            $debugLog('login_success', [
                'userId' => (int)($result['user']['id'] ?? 0),
                'hasToken' => ($result['token'] ?? '') !== '',
            ], 'H3');
            // #endregion

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

        if (!ModuleGateHelper::podeAcessar('coleta_nova', $user)) {
            return ApiHelper::fail('forbidden', 'Sem permissão para o app coletor.', 403);
        }

        return ApiHelper::ok([
            'user' => ColetaApiPresenter::usuario($user),
        ]);
    }
}
