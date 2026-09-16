<?php

namespace App\Common;

class SystemModules
{
    /** @var array<string, array{label:string,link:string,icon:string,grupo:string,ordem:int}> */
    private static array $modules = [
        'dashboard' => ['label' => 'Dashboard', 'link' => '/painel', 'icon' => 'fa-chart-line', 'grupo' => 'Principal', 'ordem' => 10],
        'coletas' => ['label' => 'Coletas / MTR', 'link' => '/painel/coletas', 'icon' => 'fa-truck', 'grupo' => 'Operação', 'ordem' => 20],
        'coleta_nova' => ['label' => 'Lançar Coleta', 'link' => '/painel/coleta/nova', 'icon' => 'fa-plus-circle', 'grupo' => 'Operação', 'ordem' => 21],
        'agendamentos' => ['label' => 'Agendamentos', 'link' => '/painel/agendamentos', 'icon' => 'fa-calendar', 'grupo' => 'Operação', 'ordem' => 22],
        'rota_dia' => ['label' => 'Rota do dia', 'link' => '/painel/rota-do-dia', 'icon' => 'fa-map-location-dot', 'grupo' => 'Operação', 'ordem' => 23],
        'clientes' => ['label' => 'Clientes', 'link' => '/painel/clientes', 'icon' => 'fa-building', 'grupo' => 'Cadastros', 'ordem' => 30],
        'funcionarios' => ['label' => 'Funcionários', 'link' => '/painel/funcionarios', 'icon' => 'fa-users', 'grupo' => 'Cadastros', 'ordem' => 31],
        'veiculos' => ['label' => 'Veículos', 'link' => '/painel/veiculos', 'icon' => 'fa-car', 'grupo' => 'Cadastros', 'ordem' => 32],
        'planos' => ['label' => 'Planos', 'link' => '/painel/planos', 'icon' => 'fa-file-contract', 'grupo' => 'Cadastros', 'ordem' => 33],
        'residuo_classes' => ['label' => 'Classes de Resíduo', 'link' => '/painel/residuo-classes', 'icon' => 'fa-layer-group', 'grupo' => 'Cadastros', 'ordem' => 34],
        'residuo_grupos' => ['label' => 'Grupos de Resíduo', 'link' => '/painel/residuo-grupos', 'icon' => 'fa-tags', 'grupo' => 'Cadastros', 'ordem' => 35],
        'tipos_residuos' => ['label' => 'Tipos de Resíduos', 'link' => '/painel/tipos-residuos', 'icon' => 'fa-recycle', 'grupo' => 'Cadastros', 'ordem' => 36],
        'rotas' => ['label' => 'Rotas', 'link' => '/painel/rotas', 'icon' => 'fa-route', 'grupo' => 'Cadastros', 'ordem' => 37],
        'pagamentos' => ['label' => 'Pagamentos', 'link' => '/painel/pagamentos', 'icon' => 'fa-money-bill', 'grupo' => 'Financeiro', 'ordem' => 40],
        'relatorios' => ['label' => 'Relatórios', 'link' => '/painel/relatorios', 'icon' => 'fa-chart-bar', 'grupo' => 'Financeiro', 'ordem' => 41],
        'usuarios' => ['label' => 'Usuários', 'link' => '/painel/usuarios', 'icon' => 'fa-user-gear', 'grupo' => 'Sistema', 'ordem' => 50],
        'funcoes' => ['label' => 'Funções e Módulos', 'link' => '/painel/funcoes', 'icon' => 'fa-shield-halved', 'grupo' => 'Sistema', 'ordem' => 51],
        'operadora' => ['label' => 'Operadora', 'link' => '/painel/operadora', 'icon' => 'fa-building-circle-check', 'grupo' => 'Sistema', 'ordem' => 52],
        'ajuda' => ['label' => 'Ajuda', 'link' => '/painel/ajuda', 'icon' => 'fa-circle-question', 'grupo' => 'Ajuda', 'ordem' => 62],
        'contratos' => ['label' => 'Contratos', 'link' => '/painel/contratos', 'icon' => 'fa-file-signature', 'grupo' => 'Comercial', 'ordem' => 35],
        'termos_de_uso' => ['label' => 'Termos de Uso', 'link' => '/painel/termos-de-uso', 'icon' => 'fa-file-contract', 'grupo' => 'Sistema', 'ordem' => 98],
        'perfil' => ['label' => 'Perfil', 'link' => '/painel/perfil', 'icon' => 'fa-id-badge', 'grupo' => 'Sistema', 'ordem' => 99],
    ];

    /** Estrutura do menu lateral (padrão CTI — dropdowns com submenus). */
    private static array $menuGroups = [
        [
            'type' => 'link',
            'slug' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => 'fas fa-chart-line',
            'link' => '/painel',
        ],
        [
            'type' => 'dropdown',
            'label' => 'Operação',
            'icon' => 'fas fa-truck',
            'collapse_id' => 'Layouts-operacao',
            'items' => [
                ['slug' => 'coletas', 'label' => 'Coletas / MTR', 'link' => '/painel/coletas'],
                ['slug' => 'coleta_nova', 'label' => 'Lançar Coleta', 'link' => '/painel/coleta/nova'],
                ['slug' => 'agendamentos', 'label' => 'Agendamentos', 'link' => '/painel/agendamentos'],
                ['slug' => 'rota_dia', 'label' => 'Rota do dia', 'link' => '/painel/rota-do-dia'],
            ],
        ],
        [
            'type' => 'dropdown',
            'label' => 'Cadastros',
            'icon' => 'fas fa-database',
            'collapse_id' => 'Layouts-cadastros',
            'items' => [
                ['slug' => 'clientes', 'label' => 'Clientes', 'link' => '/painel/clientes'],
                ['slug' => 'funcionarios', 'label' => 'Funcionários', 'link' => '/painel/funcionarios'],
                ['slug' => 'veiculos', 'label' => 'Veículos', 'link' => '/painel/veiculos'],
                ['slug' => 'planos', 'label' => 'Planos', 'link' => '/painel/planos'],
                ['slug' => 'residuo_classes', 'label' => 'Classes de Resíduo', 'link' => '/painel/residuo-classes'],
                ['slug' => 'residuo_grupos', 'label' => 'Grupos de Resíduo', 'link' => '/painel/residuo-grupos'],
                ['slug' => 'tipos_residuos', 'label' => 'Tipos de Resíduos', 'link' => '/painel/tipos-residuos'],
                ['slug' => 'rotas', 'label' => 'Rotas', 'link' => '/painel/rotas'],
            ],
        ],
        [
            'type' => 'dropdown',
            'label' => 'Financeiro',
            'icon' => 'fas fa-coins',
            'collapse_id' => 'Layouts-financeiro',
            'items' => [
                ['slug' => 'pagamentos', 'label' => 'Pagamentos', 'link' => '/painel/pagamentos'],
                ['slug' => 'relatorios', 'label' => 'Relatórios', 'link' => '/painel/relatorios'],
            ],
        ],
        [
            'type' => 'dropdown',
            'label' => 'Comercial',
            'icon' => 'fas fa-handshake',
            'collapse_id' => 'Layouts-comercial',
            'items' => [
                ['slug' => 'contratos', 'label' => 'Contratos', 'link' => '/painel/contratos'],
                ['slug' => 'contratos', 'label' => 'Modelo de contrato', 'link' => '/painel/config/contrato'],
            ],
        ],
        [
            'type' => 'link',
            'slug' => 'ajuda',
            'label' => 'Central de ajuda',
            'icon' => 'fas fa-circle-question',
            'link' => '/painel/ajuda',
        ],
        [
            'type' => 'dropdown',
            'label' => 'Sistema',
            'icon' => 'fas fa-gear',
            'collapse_id' => 'Layouts-sistema',
            'items' => [
                ['slug' => 'usuarios', 'label' => 'Usuários', 'link' => '/painel/usuarios'],
                ['slug' => 'funcoes', 'label' => 'Funções e Módulos', 'link' => '/painel/funcoes'],
                ['slug' => 'operadora', 'label' => 'Operadora', 'link' => '/painel/operadora'],
                ['slug' => 'perfil', 'label' => 'Perfil', 'link' => '/painel/perfil'],
            ],
        ],
    ];

    public static function getModules(): array
    {
        return self::$modules;
    }

    public static function getMenuGroups(): array
    {
        return self::$menuGroups;
    }

    public static function getSlugs(): array
    {
        return array_keys(self::$modules);
    }

    public static function getBySlug(string $slug): ?array
    {
        return self::$modules[$slug] ?? null;
    }

    public static function slugValido(string $slug): bool
    {
        return isset(self::$modules[$slug]);
    }
}
