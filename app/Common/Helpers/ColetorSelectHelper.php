<?php

namespace App\Common\Helpers;

use App\Model\Entity\Usuario as EntityUsuario;

class ColetorSelectHelper
{
    /** Coletor (não admin) — motorista fixo na sessão. */
    public static function isColetorSession(array $usuario): bool
    {
        if (!empty($usuario['is_admin'])) {
            return false;
        }

        return mb_strtolower(trim((string)($usuario['funcao_nome'] ?? ''))) === 'coletor';
    }

    public static function optionsHtml(int $selectedId = 0, bool $emptyOption = false): string
    {
        $html = $emptyOption ? '<option value="">— Selecione —</option>' : '';
        foreach (EntityUsuario::getColetoresAtivos() as $c) {
            $sel = $c->id === $selectedId ? ' selected' : '';
            $html .= '<option value="'.$c->id.'"'.$sel.'>'.CrudHelper::e($c->nome).'</option>';
        }

        return $html;
    }

    public static function nomeById(int $id): string
    {
        if ($id <= 0) {
            return '';
        }
        $u = EntityUsuario::getById($id);

        return $u ? trim($u->nome) : '';
    }

    public static function isColetorAtivo(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        foreach (EntityUsuario::getColetoresAtivos() as $c) {
            if ($c->id === $id) {
                return true;
            }
        }

        return false;
    }

    public static function resolveSelectedId(?string $motoristaNome, int $fallbackColetorId): int
    {
        $nome = trim((string)$motoristaNome);
        if ($nome !== '') {
            foreach (EntityUsuario::getColetoresAtivos() as $c) {
                if (strcasecmp($c->nome, $nome) === 0) {
                    return $c->id;
                }
            }
        }

        return $fallbackColetorId > 0 ? $fallbackColetorId : 0;
    }
}
