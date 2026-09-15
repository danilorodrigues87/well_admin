<?php

use App\Common\Helpers\ApiHelper;
use App\Controller\Api\Auth;
use App\Controller\Api\Agendamentos;
use App\Controller\Api\Catalogos;
use App\Controller\Api\Clientes;
use App\Controller\Api\Coletas;
use App\Controller\Api\Dashboard;
use App\Controller\Api\Perfil;
use App\Controller\Api\Webhooks\InterCobranca as InterCobrancaWebhook;
use App\Http\Response;

$apiAuth = ['api-cors', 'required-api-auth'];
$apiPublic = ['api-cors'];
$mod = static fn (string $slug): array => array_merge($apiAuth, ["required-api-module:{$slug}"]);

$obRouter->get('/api/v1/health', [
    'middlewares' => $apiPublic,
    function ($request) {
        return ApiHelper::ok(['status' => 'ok', 'version' => 'v1']);
    },
]);

$obRouter->options('/api/v1/{path+}', [
    'middlewares' => $apiPublic,
    function ($request) {
        return new Response(204, '', 'application/json');
    },
]);

$obRouter->post('/api/v1/auth/login', [
    'middlewares' => $apiPublic,
    function ($request) {
        return Auth::login($request);
    },
]);

$obRouter->post('/api/v1/webhooks/inter/cobranca', [
    'middlewares' => $apiPublic,
    function ($request) {
        return InterCobrancaWebhook::receber($request);
    },
]);

$obRouter->get('/api/v1/auth/me', [
    'middlewares' => $apiAuth,
    function ($request) {
        return Auth::me($request);
    },
]);

$obRouter->get('/api/v1/dashboard/resumo', [
    'middlewares' => $mod('dashboard'),
    function ($request) {
        return Dashboard::resumo($request);
    },
]);

$obRouter->get('/api/v1/agendamentos', [
    'middlewares' => $mod('agendamentos'),
    function ($request) {
        return Agendamentos::index($request);
    },
]);

$obRouter->get('/api/v1/perfil', [
    'middlewares' => $mod('perfil'),
    function ($request) {
        return Perfil::show($request);
    },
]);

$obRouter->post('/api/v1/perfil/senha', [
    'middlewares' => $mod('perfil'),
    function ($request) {
        return Perfil::trocarSenha($request);
    },
]);

$obRouter->get('/api/v1/clientes/coleta', [
    'middlewares' => $mod('coleta_nova'),
    function ($request) {
        return Clientes::paraColeta($request);
    },
]);

$obRouter->get('/api/v1/catalogos/veiculos', [
    'middlewares' => $mod('coleta_nova'),
    function ($request) {
        return Catalogos::veiculos($request);
    },
]);

$obRouter->get('/api/v1/catalogos/tipos-residuos', [
    'middlewares' => $mod('coleta_nova'),
    function ($request) {
        return Catalogos::tiposResiduos($request);
    },
]);

$obRouter->get('/api/v1/catalogos/tratamentos', [
    'middlewares' => $mod('coleta_nova'),
    function ($request) {
        return Catalogos::tratamentos($request);
    },
]);

$obRouter->get('/api/v1/coletas', [
    'middlewares' => $mod('coletas'),
    function ($request) {
        return Coletas::index($request);
    },
]);

$obRouter->post('/api/v1/coletas', [
    'middlewares' => $mod('coleta_nova'),
    function ($request) {
        return Coletas::store($request);
    },
]);

$obRouter->get('/api/v1/coletas/{id}', [
    'middlewares' => $mod('coletas'),
    function ($request, int $id) {
        return Coletas::show($request, $id);
    },
]);

$obRouter->patch('/api/v1/coletas/{id}/transporte', [
    'middlewares' => $mod('coleta_nova'),
    function ($request, int $id) {
        return Coletas::updateTransporte($request, $id);
    },
]);

$obRouter->post('/api/v1/coletas/{id}/itens', [
    'middlewares' => $mod('coleta_nova'),
    function ($request, int $id) {
        return Coletas::addItem($request, $id);
    },
]);

$obRouter->delete('/api/v1/coletas/{id}/itens/{itemId}', [
    'middlewares' => $mod('coleta_nova'),
    function ($request, int $id, int $itemId) {
        return Coletas::removeItem($request, $id, $itemId);
    },
]);

$obRouter->post('/api/v1/coletas/{id}/finalizar', [
    'middlewares' => $mod('coleta_nova'),
    function ($request, int $id) {
        return Coletas::finalizar($request, $id);
    },
]);

$obRouter->post('/api/v1/coletas/{id}/cancelar', [
    'middlewares' => $mod('coleta_nova'),
    function ($request, int $id) {
        return Coletas::cancelar($request, $id);
    },
]);

$obRouter->get('/api/v1/coletas/{id}/evidencias/{ordem}', [
    'middlewares' => $mod('coletas'),
    function ($request, int $id, int $ordem) {
        return Coletas::evidencia($request, $id, $ordem);
    },
]);
