<?php

use App\Controller\Admin\Pagamentos;
use App\Http\Response;

$obRouter->get('/painel/pagamentos', [
    'middlewares' => ['required-admin-login', 'required-module:pagamentos'],
    function ($request) {
        return new Response(200, Pagamentos::index($request));
    },
]);

$obRouter->post('/painel/pagamentos', [
    'middlewares' => ['required-admin-login', 'required-module:pagamentos'],
    function ($request) {
        $acao = $request->getPostVars()['acao'] ?? 'relatorio';
        $content = match ($acao) {
            'relatorio' => Pagamentos::relatorio($request),
            'historico' => Pagamentos::historico($request),
            'emitir' => Pagamentos::emitirLote($request),
            'config' => Pagamentos::saveConfig($request),
            default => json_encode(['success' => false, 'message' => 'Ação inválida']),
        };

        return new Response(200, $content, 'application/json');
    },
]);

$obRouter->get('/painel/pagamentos/{id}/detalhe', [
    'middlewares' => ['required-admin-login', 'required-module:pagamentos'],
    function ($request, int $id) {
        return new Response(200, Pagamentos::detalhe($request, $id), 'application/json');
    },
]);

$obRouter->get('/painel/pagamentos/{id}/pdf', [
    'middlewares' => ['required-admin-login', 'required-module:pagamentos'],
    function ($request, int $id) {
        Pagamentos::downloadPdf($request, $id);
    },
]);

$obRouter->post('/painel/pagamentos/{id}/email', [
    'middlewares' => ['required-admin-login', 'required-module:pagamentos'],
    function ($request, int $id) {
        return new Response(200, Pagamentos::enviarEmail($request, $id), 'application/json');
    },
]);

$obRouter->post('/painel/pagamentos/{id}/sync', [
    'middlewares' => ['required-admin-login', 'required-module:pagamentos'],
    function ($request, int $id) {
        return new Response(200, Pagamentos::sincronizarStatus($request, $id), 'application/json');
    },
]);

$obRouter->post('/painel/pagamentos/{id}/baixa', [
    'middlewares' => ['required-admin-login', 'required-module:pagamentos'],
    function ($request, int $id) {
        return new Response(200, Pagamentos::baixaManual($request, $id), 'application/json');
    },
]);
