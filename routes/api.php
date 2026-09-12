<?php

use App\Common\Helpers\ApiHelper;
use App\Controller\Api\Auth;
use App\Controller\Api\Catalogos;
use App\Controller\Api\Clientes;
use App\Controller\Api\Coletas;
use App\Http\Response;

$apiAuth = ['api-cors', 'required-api-auth', 'required-api-module:coleta_nova'];
$apiPublic = ['api-cors'];

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

$obRouter->get('/api/v1/auth/me', [
    'middlewares' => $apiAuth,
    function ($request) {
        return Auth::me($request);
    },
]);

$obRouter->get('/api/v1/clientes/coleta', [
    'middlewares' => $apiAuth,
    function ($request) {
        return Clientes::paraColeta($request);
    },
]);

$obRouter->get('/api/v1/catalogos/veiculos', [
    'middlewares' => $apiAuth,
    function ($request) {
        return Catalogos::veiculos($request);
    },
]);

$obRouter->get('/api/v1/catalogos/tipos-residuos', [
    'middlewares' => $apiAuth,
    function ($request) {
        return Catalogos::tiposResiduos($request);
    },
]);

$obRouter->get('/api/v1/catalogos/tratamentos', [
    'middlewares' => $apiAuth,
    function ($request) {
        return Catalogos::tratamentos($request);
    },
]);

$obRouter->get('/api/v1/coletas', [
    'middlewares' => $apiAuth,
    function ($request) {
        return Coletas::index($request);
    },
]);

$obRouter->post('/api/v1/coletas', [
    'middlewares' => $apiAuth,
    function ($request) {
        return Coletas::store($request);
    },
]);

$obRouter->get('/api/v1/coletas/{id}', [
    'middlewares' => $apiAuth,
    function ($request, int $id) {
        return Coletas::show($request, $id);
    },
]);

$obRouter->patch('/api/v1/coletas/{id}/transporte', [
    'middlewares' => $apiAuth,
    function ($request, int $id) {
        return Coletas::updateTransporte($request, $id);
    },
]);

$obRouter->post('/api/v1/coletas/{id}/itens', [
    'middlewares' => $apiAuth,
    function ($request, int $id) {
        return Coletas::addItem($request, $id);
    },
]);

$obRouter->delete('/api/v1/coletas/{id}/itens/{itemId}', [
    'middlewares' => $apiAuth,
    function ($request, int $id, int $itemId) {
        return Coletas::removeItem($request, $id, $itemId);
    },
]);

$obRouter->post('/api/v1/coletas/{id}/finalizar', [
    'middlewares' => $apiAuth,
    function ($request, int $id) {
        return Coletas::finalizar($request, $id);
    },
]);

$obRouter->post('/api/v1/coletas/{id}/cancelar', [
    'middlewares' => $apiAuth,
    function ($request, int $id) {
        return Coletas::cancelar($request, $id);
    },
]);

$obRouter->get('/api/v1/coletas/{id}/evidencias/{ordem}', [
    'middlewares' => $apiAuth,
    function ($request, int $id, int $ordem) {
        return Coletas::evidencia($request, $id, $ordem);
    },
]);
