<?php

namespace App\Common\Helpers;

use App\Common\SystemModules;
use App\Model\Entity\FuncaoModulo as EntityFuncaoModulo;
use App\Model\Entity\UsuarioModulo as EntityUsuarioModulo;

class ModuleGateHelper
{
    private static array $cacheFuncao = [];

    public static function getSlugsFuncao(int $funcaoId, bool $isAdmin = false): array
    {
        if ($isAdmin) {
            return SystemModules::getSlugs();
        }

        if (isset(self::$cacheFuncao[$funcaoId])) {
            return self::$cacheFuncao[$funcaoId];
        }

        $slugs = EntityFuncaoModulo::getSlugsByFuncaoId($funcaoId);
        if (!in_array('perfil', $slugs, true)) {
            $slugs[] = 'perfil';
        }
        if (!in_array('dashboard', $slugs, true)) {
            $slugs[] = 'dashboard';
        }

        self::$cacheFuncao[$funcaoId] = array_values(array_unique($slugs));
        return self::$cacheFuncao[$funcaoId];
    }

    public static function getModulosEfetivos(array $userSession): array
    {
        $funcaoId = (int)($userSession['funcao_id'] ?? 0);
        $isAdmin = !empty($userSession['is_admin']);
        $base = self::getSlugsFuncao($funcaoId, $isAdmin);

        $usuarioId = (int)($userSession['id'] ?? 0);
        if ($usuarioId <= 0) {
            return $base;
        }

        $overrides = EntityUsuarioModulo::getOverridesByUsuarioId($usuarioId);
        foreach ($overrides['grant'] as $slug) {
            if (SystemModules::slugValido($slug) && !in_array($slug, $base, true)) {
                $base[] = $slug;
            }
        }
        foreach ($overrides['revoke'] as $slug) {
            $base = array_values(array_filter($base, fn ($s) => $s !== $slug));
        }

        return array_values(array_unique($base));
    }

    public static function podeAcessar(string $slug, array $userSession): bool
    {
        if (!SystemModules::slugValido($slug)) {
            return false;
        }
        if (!empty($userSession['is_admin'])) {
            return true;
        }
        return in_array($slug, self::getModulosEfetivos($userSession), true);
    }

    public static function limparCache(?int $funcaoId = null): void
    {
        if ($funcaoId === null) {
            self::$cacheFuncao = [];
            return;
        }
        unset(self::$cacheFuncao[$funcaoId]);
    }
}
