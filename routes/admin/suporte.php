<?php

use App\Controller\Admin;
use App\Http\Response;

$obRouter->get('/painel/termos-de-uso', [
    'middlewares' => ['required-admin-login'],
    function ($request) {
        return new Response(200, Admin\TermosDeUso::index($request));
    },
]);

$obRouter->post('/painel/aceita-termos', [
    'middlewares' => ['required-admin-login'],
    function ($request) {
        return new Response(200, Admin\TermosDeUso::aceitaTermo($request));
    },
]);

$obRouter->get('/painel/suporte', [
    'middlewares' => ['required-admin-login', 'required-module:suporte'],
    function ($request) {
        return new Response(200, Admin\Suporte::index($request));
    },
]);

$obRouter->post('/painel/suporte', [
    'middlewares' => ['required-admin-login', 'required-module:suporte'],
    function ($request) {
        return new Response(200, Admin\Suporte::getInfo($request), 'application/json');
    },
]);

$obRouter->get('/painel/suporte/anexo/{id}', [
    'middlewares' => ['required-admin-login', 'required-module:suporte'],
    function ($request, int $id) {
        return Admin\Suporte::downloadAnexo($request, $id);
    },
]);

$obRouter->get('/painel/ajuda', [
    'middlewares' => ['required-admin-login', 'required-module:ajuda'],
    function ($request) {
        return new Response(200, Admin\Ajuda::index($request));
    },
]);

$obRouter->get('/painel/ajuda/{slug}', [
    'middlewares' => ['required-admin-login', 'required-module:ajuda'],
    function ($request, string $slug) {
        return new Response(200, Admin\Ajuda::artigo($request, $slug));
    },
]);

$obRouter->get('/painel/config/contrato', [
    'middlewares' => ['required-admin-login', 'required-module:contratos'],
    function ($request) {
        return new Response(200, Admin\ConfigContrato::index($request));
    },
]);

$obRouter->post('/painel/config/contrato', [
    'middlewares' => ['required-admin-login', 'required-module:contratos'],
    function ($request) {
        Admin\ConfigContrato::save($request);
    },
]);

$obRouter->get('/painel/contratos', [
    'middlewares' => ['required-admin-login', 'required-module:contratos'],
    function ($request) {
        return new Response(200, Admin\ContratosClientes::index($request));
    },
]);

$obRouter->get('/painel/clientes/{id}/contratos', [
    'middlewares' => ['required-admin-login', 'required-module:contratos'],
    function ($request, int $id) {
        return new Response(200, Admin\ContratosClientes::listByCliente($request, $id));
    },
]);

$obRouter->get('/painel/clientes/{id}/contrato/novo', [
    'middlewares' => ['required-admin-login', 'required-module:contratos'],
    function ($request, int $id) {
        return new Response(200, Admin\ContratosClientes::novo($request, $id));
    },
]);

$obRouter->post('/painel/clientes/{id}/contrato/novo', [
    'middlewares' => ['required-admin-login', 'required-module:contratos'],
    function ($request, int $id) {
        Admin\ContratosClientes::salvar($request, $id);
    },
]);

$obRouter->get('/painel/contratos/{id}', [
    'middlewares' => ['required-admin-login', 'required-module:contratos'],
    function ($request, int $id) {
        return new Response(200, Admin\ContratosClientes::show($request, $id));
    },
]);

$obRouter->post('/painel/contratos/{id}/enviar', [
    'middlewares' => ['required-admin-login', 'required-module:contratos'],
    function ($request, int $id) {
        Admin\ContratosClientes::enviar($request, $id);
    },
]);

$obRouter->get('/painel/contratos/{id}/imprimir', [
    'middlewares' => ['required-admin-login', 'required-module:contratos'],
    function ($request, int $id) {
        return new Response(200, Admin\ContratosClientes::imprimir($request, $id));
    },
]);
