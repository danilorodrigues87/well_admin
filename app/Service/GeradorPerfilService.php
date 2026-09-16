<?php

namespace App\Service;

use App\Common\GeradorScope;
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
}
