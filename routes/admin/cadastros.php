<?php

use App\Controller\Admin;
use App\Http\Response;

$crudRoutes = [
    ['path' => '/painel/usuarios', 'ctrl' => Admin\Usuarios::class, 'module' => 'usuarios'],
    ['path' => '/painel/funcionarios', 'ctrl' => Admin\Funcionarios::class, 'module' => 'funcionarios'],
    ['path' => '/painel/funcoes', 'ctrl' => Admin\Funcoes::class, 'module' => 'funcoes'],
    ['path' => '/painel/clientes', 'ctrl' => Admin\Clientes::class, 'module' => 'clientes'],
    ['path' => '/painel/veiculos', 'ctrl' => Admin\Veiculos::class, 'module' => 'veiculos'],
    ['path' => '/painel/transportadoras', 'ctrl' => Admin\Transportadoras::class, 'module' => 'transportadoras'],
    ['path' => '/painel/destinadores', 'ctrl' => Admin\Destinadores::class, 'module' => 'destinadores'],
    ['path' => '/painel/planos', 'ctrl' => Admin\Planos::class, 'module' => 'planos'],
    ['path' => '/painel/residuo-classes', 'ctrl' => Admin\ResiduoClasses::class, 'module' => 'residuo_classes'],
    ['path' => '/painel/residuo-grupos', 'ctrl' => Admin\ResiduoGrupos::class, 'module' => 'residuo_grupos'],
    ['path' => '/painel/tipos-residuos', 'ctrl' => Admin\TiposResiduos::class, 'module' => 'tipos_residuos'],
    ['path' => '/painel/rotas', 'ctrl' => Admin\Rotas::class, 'module' => 'rotas'],
];

$extraActions = [
    '/painel/tipos-residuos' => [
        'grupos_por_classe' => Admin\TiposResiduos::class.'::gruposPorClasse',
    ],
    '/painel/clientes' => [
        'portal_acesso' => Admin\Clientes::class.'::getPortalAcesso',
        'salvar_portal_usuario' => Admin\Clientes::class.'::savePortalUsuario',
        'resetar_senha_portal' => Admin\Clientes::class.'::resetSenhaPortal',
    ],
];

$obRouter->get('/painel/rotas/atribuicoes/{id}', [
    'middlewares' => ['required-admin-login', 'required-module:rotas'],
    function ($request, int $id) {
        return new Response(200, Admin\Rotas::atribuicoesIndex($request, $id));
    },
]);

$obRouter->post('/painel/rotas/atribuicoes/{id}', [
    'middlewares' => ['required-admin-login', 'required-module:rotas'],
    function ($request, int $id) {
        $acao = $request->getPostVars()['acao'] ?? '';
        $content = match ($acao) {
            'listar_atribuicoes' => Admin\Rotas::listAtribuicoes($request, $id),
            'salvar_atribuicao' => Admin\Rotas::saveAtribuicao($request, $id),
            'update_atribuicao_coletor' => Admin\Rotas::updateAtribuicaoColetor($request, $id),
            'excluir_atribuicao' => Admin\Rotas::deleteAtribuicao($request, $id),
            'bulk_coletor' => Admin\Rotas::bulkColetor($request, $id),
            'clientes_disponiveis' => Admin\Rotas::clientesDisponiveis($request, $id),
            default => json_encode(['success' => false, 'message' => 'Ação inválida']),
        };
        return new Response(200, $content, 'application/json');
    },
]);

foreach ($crudRoutes as $route) {
    $ctrl = $route['ctrl'];
    $module = $route['module'];
    $path = $route['path'];

    $obRouter->get($path, [
        'middlewares' => ['required-admin-login', 'required-module:'.$module],
        function ($request) use ($ctrl) {
            return new Response(200, $ctrl::index($request));
        },
    ]);

    $obRouter->post($path, [
        'middlewares' => ['required-admin-login', 'required-module:'.$module],
        function ($request) use ($ctrl, $path, $extraActions) {
            $acao = $request->getPostVars()['acao'] ?? 'listar';
            $extra = $extraActions[$path][$acao] ?? null;
            if ($extra && is_string($extra) && str_contains($extra, '::')) {
                [$extraCtrl, $extraMethod] = explode('::', $extra, 2);
                $content = $extraCtrl::$extraMethod($request);
            } else {
                $content = match ($acao) {
                    'listar' => $ctrl::list($request),
                    'get' => $ctrl::get($request),
                    'salvar' => $ctrl::save($request),
                    'excluir' => $ctrl::delete($request),
                    'resetar_senha' => method_exists($ctrl, 'resetSenha')
                        ? $ctrl::resetSenha($request)
                        : json_encode(['success' => false, 'message' => 'Ação inválida']),
                    default => json_encode(['success' => false, 'message' => 'Ação inválida']),
                };
            }
            return new Response(200, $content, 'application/json');
        },
    ]);
}
