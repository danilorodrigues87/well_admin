<?php

namespace App\Common\Helpers;

class CrudHelper
{
    public static function jsonOk(array $data = []): string
    {
        return json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    }

    public static function jsonError(string $message): string
    {
        return json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    }

    public static function requireCsrf(array $post): ?string
    {
        if (!CsrfHelper::validate($post['_csrf'] ?? null)) {
            return 'Sessão expirada. Recarregue a página.';
        }
        return null;
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    public static function parseAtivo(array $post, bool $novo = false): int
    {
        if ($novo) {
            return 1;
        }
        $v = trim((string)($post['ativo'] ?? '1'));

        return $v === '0' ? 0 : 1;
    }

    public static function btnDesativar(int $id): string
    {
        return '<button class="btn btn-sm btn-outline-warning" onclick="excluir('.$id.')" title="Desativar"><i class="fas fa-ban"></i></button>';
    }

    public static function btnResetarSenha(int $id): string
    {
        return '<button class="btn btn-sm btn-outline-secondary" onclick="resetarSenha('.$id.')" title="Resetar senha"><i class="fas fa-key"></i></button>';
    }
}
