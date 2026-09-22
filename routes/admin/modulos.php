<?php

use App\Controller\Admin;
use App\Http\Response;

$modulosPendentes = [
];

foreach ($modulosPendentes as [$path, $module, $title]) {
    $obRouter->get($path, [
        'middlewares' => ['required-admin-login', 'required-module:'.$module],
        function ($request) use ($module, $title) {
            return new Response(200, Admin\EmBreve::show($request, $module, $title));
        },
    ]);
}

$obRouter->get('/painel/operadora', [
    'middlewares' => ['required-admin-login', 'required-module:operadora'],
    function ($request) {
        return new Response(200, Admin\Operadora::index($request));
    },
]);

$obRouter->post('/painel/operadora', [
    'middlewares' => ['required-admin-login', 'required-module:operadora'],
    function ($request) {
        Admin\Operadora::save($request);
    },
]);

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

$obRouter->get('/painel/dmr', [
    'middlewares' => ['required-admin-login', 'required-module:dmr'],
    function ($request) {
        return new Response(200, Admin\Dmr::index($request));
    },
]);

$obRouter->get('/painel/dmr/export', [
    'middlewares' => ['required-admin-login', 'required-module:dmr'],
    function ($request) {
        Admin\Dmr::exportCsv($request);
    },
]);

$obRouter->post('/painel/dmr', [
    'middlewares' => ['required-admin-login', 'required-module:dmr'],
    function ($request) {
        $acao = $request->getPostVars()['acao'] ?? 'listar';
        $content = match ($acao) {
            'listar' => Admin\Dmr::list($request),
            'gerar' => Admin\Dmr::gerar($request),
            'fechar' => Admin\Dmr::fechar($request),
            'detalhe' => Admin\Dmr::detalhe($request),
            default => json_encode(['success' => false, 'message' => 'Ação inválida']),
        };

        return new Response(200, $content, 'application/json');
    },
]);
