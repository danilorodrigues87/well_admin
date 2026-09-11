<?php

namespace App\Service;

use App\Model\Entity\Usuario as EntityUsuario;
use App\Session\User\Login as SessionUser;

class PerfilService
{
    /** @return array{success:bool, message?:string} */
    public static function atualizarDados(int $userId, string $nome, string $email): array
    {
        $nome = trim($nome);
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);

        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Informe nome e e-mail válidos.'];
        }

        $exists = EntityUsuario::getByEmail($email);
        if ($exists && $exists->id !== $userId) {
            return ['success' => false, 'message' => 'Este e-mail já está em uso.'];
        }

        EntityUsuario::update($userId, ['nome' => $nome, 'email' => $email]);
        SessionUser::syncSessionFromDatabase();

        return ['success' => true, 'message' => 'Dados atualizados com sucesso.'];
    }

    /** @return array{success:bool, message?:string} */
    public static function trocarSenha(int $userId, string $senhaAtual, string $novaSenha, string $confirmacao): array
    {
        $usuario = EntityUsuario::getById($userId);
        if (!$usuario) {
            return ['success' => false, 'message' => 'Usuário não encontrado.'];
        }

        if (!password_verify($senhaAtual, $usuario->senha)) {
            return ['success' => false, 'message' => 'Senha atual incorreta.'];
        }

        if (strlen($novaSenha) < 8) {
            return ['success' => false, 'message' => 'A nova senha deve ter no mínimo 8 caracteres.'];
        }

        if ($novaSenha !== $confirmacao) {
            return ['success' => false, 'message' => 'A confirmação não confere com a nova senha.'];
        }

        EntityUsuario::update($userId, [
            'senha' => password_hash($novaSenha, PASSWORD_DEFAULT),
        ]);

        return ['success' => true, 'message' => 'Senha alterada com sucesso.'];
    }
}
