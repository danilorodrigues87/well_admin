<?php

namespace App\Common;

/**
 * Módulos operacionais do app mobile (coletor / gestor).
 * Login exige ao menos um destes slugs (admin tem todos via RBAC).
 */
class ApiAppModules
{
    /** @return list<string> */
    public static function slugsOperacionais(): array
    {
        return [
            'dashboard',
            'coletas',
            'coleta_nova',
            'agendamentos',
            'rota_dia',
            'clientes',
            'rotas',
            'relatorios',
            'perfil',
        ];
    }

    public static function temAcessoApp(array $modulos): bool
    {
        foreach (self::slugsOperacionais() as $slug) {
            if (in_array($slug, $modulos, true)) {
                return true;
            }
        }

        return false;
    }
}
