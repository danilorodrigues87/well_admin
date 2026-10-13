<?php

namespace App\Service;

use App\Common\GeradorScope;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\ClienteUsuario;

class GeradorPerfilService
{
    /** @return array{success:bool, message?:string} */
    public static function trocarSenha(int $usuarioId, string $senhaAtual, string $novaSenha, string $confirmacao): array
    {
        $usuario = ClienteUsuario::getById($usuarioId);
        if (!$usuario || $usuario->id !== $usuarioId) {
            return ['success' => false, 'message' => 'Usuário não encontrado.'];
        }

        if ((int)$usuario->cliente_id !== GeradorScope::getClienteId()) {
            return ['success' => false, 'message' => 'Acesso negado.'];
        }

        if (!password_verify($senhaAtual, $usuario->senha_hash)) {
            return ['success' => false, 'message' => 'Senha atual incorreta.'];
        }

        if (strlen($novaSenha) < 8) {
            return ['success' => false, 'message' => 'A nova senha deve ter no mínimo 8 caracteres.'];
        }

        if ($novaSenha !== $confirmacao) {
            return ['success' => false, 'message' => 'A confirmação não confere com a nova senha.'];
        }

        ClienteUsuario::update($usuarioId, [
            'senha_hash' => password_hash($novaSenha, PASSWORD_DEFAULT),
        ]);

        return ['success' => true, 'message' => 'Senha alterada com sucesso.'];
    }

    /**
     * Atualiza unidade + token SINIR do cliente logado no portal gerador.
     *
     * @param array<string,mixed> $post
     * @return array{success:bool, message?:string}
     */
    public static function salvarSinir(int $clienteId, array $post): array
    {
        if ($clienteId <= 0 || $clienteId !== GeradorScope::getClienteId()) {
            return ['success' => false, 'message' => 'Sessão inválida.'];
        }

        $cliente = EntityCliente::getById($clienteId);
        if (!$cliente) {
            return ['success' => false, 'message' => 'Cliente não encontrado.'];
        }

        $data = [];
        $unidade = (int)($post['sinir_cod_unidade'] ?? 0);
        $data['sinir_cod_unidade'] = $unidade > 0 ? $unidade : null;

        if (!empty($post['limpar_sinir_token'])) {
            $data['sinir_integration_token'] = null;
        } else {
            $token = trim((string)($post['sinir_integration_token'] ?? ''));
            if ($token !== '') {
                if (strlen($token) < 20) {
                    return ['success' => false, 'message' => 'Token API WS inválido (muito curto).'];
                }
                $data['sinir_integration_token'] = $token;
            }
        }

        EntityCliente::update($clienteId, $data);

        return ['success' => true, 'message' => 'Dados SINIR salvos.'];
    }
}
