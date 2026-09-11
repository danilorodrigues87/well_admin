<?php

use App\Controller\Admin;
use App\Http\Response;

$modulosPendentes = [
    ['/painel/pagamentos', 'pagamentos', 'Pagamentos'],
];

foreach ($modulosPendentes as [$path, $module, $title]) {
    $obRouter->get($path, [
        'middlewares' => ['required-admin-login', 'required-module:'.$module],
        function ($request) use ($module, $title) {
            return new Response(200, Admin\EmBreve::show($request, $module, $title));
        },
    ]);
}

$obRouter->get('/painel/perfil', [
    'middlewares' => ['required-admin-login', 'required-module:perfil'],
    function ($request) {
        return new Response(200, Admin\Perfil::index($request));
    },
]);

$obRouter->post('/painel/perfil', [
    'middlewares' => ['required-admin-login', 'required-module:perfil'],
    function ($request) {
        Admin\Perfil::save($request);
    },
]);

$obRouter->get('/painel/relatorios', [
    'middlewares' => ['required-admin-login', 'required-module:relatorios'],
    function ($request) {
        return new Response(200, Admin\Relatorios::index($request));
    },
]);

$obRouter->post('/painel/relatorios', [
    'middlewares' => ['required-admin-login', 'required-module:relatorios'],
    function ($request) {
        $acao = $request->getPostVars()['acao'] ?? 'listar';
        $content = match ($acao) {
            'listar' => Admin\Relatorios::list($request),
            default => json_encode(['success' => false, 'message' => 'Ação inválida']),
        };

        return new Response(200, $content, 'application/json');
    },
]);

$obRouter->post('/painel/relatorios/export', [
    'middlewares' => ['required-admin-login', 'required-module:relatorios'],
    function ($request) {
        Admin\Relatorios::exportCsv($request);
    },
]);
