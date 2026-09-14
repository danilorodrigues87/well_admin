<?php

namespace App\Controller\Api;

use App\Common\Helpers\ApiHelper;
use App\Http\ApiContext;
use App\Service\ColetaApiPresenter;
use App\Service\PerfilService;

class Perfil extends BaseApi
{
    public static function show($request): \App\Http\Response
    {
        $user = self::user();

        return ApiHelper::ok([
            'user' => ColetaApiPresenter::usuario($user),
        ]);
    }

    public static function trocarSenha($request): \App\Http\Response
    {
        $body = $request->getPostVars();
        $senhaAtual = (string)($body['senha_atual'] ?? '');
        $novaSenha = (string)($body['nova_senha'] ?? '');
        $confirmacao = (string)($body['confirmacao'] ?? '');

        if ($senhaAtual === '' || $novaSenha === '') {
            return ApiHelper::fail('validation_error', 'Informe senha atual e nova senha.', 422);
        }

        $result = PerfilService::trocarSenha(
            ApiContext::userId(),
            $senhaAtual,
            $novaSenha,
            $confirmacao
        );

        if (!$result['success']) {
            return ApiHelper::fail('validation_error', (string)$result['message'], 422);
        }

        return ApiHelper::ok(['message' => $result['message']]);
    }
}
