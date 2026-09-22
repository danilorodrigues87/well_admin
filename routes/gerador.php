<?php

use App\Controller\Gerador;
use App\Http\Response;

$geradorAuth = ['required-gerador-login'];

$obRouter->get('/gerador/login', [
    'middlewares' => ['required-gerador-logout'],
    function ($request) {
        return new Response(200, Gerador\Login::getLogin($request));
    },
]);

$obRouter->post('/gerador/login', [
    'middlewares' => ['required-gerador-logout'],
    function ($request) {
        return new Response(200, Gerador\Login::setLogin($request));
    },
]);

$obRouter->get('/gerador/logout', [
    'middlewares' => $geradorAuth,
    function ($request) {
        return new Response(200, Gerador\Login::setLogout($request));
    },
]);

$obRouter->get('/gerador/termos-de-uso', [
    'middlewares' => $geradorAuth,
    function ($request) {
        return new Response(200, Gerador\TermosDeUso::index($request));
    },
]);

$obRouter->post('/gerador/aceita-termos', [
    'middlewares' => $geradorAuth,
    function ($request) {
        return new Response(200, Gerador\TermosDeUso::aceitaTermo($request));
    },
]);

$obRouter->get('/gerador/contrato', [
    'middlewares' => $geradorAuth,
    function ($request) {
        return new Response(200, Gerador\Contrato::index($request));
    },
]);

$obRouter->post('/gerador/aceita-contrato', [
    'middlewares' => $geradorAuth,
    function ($request) {
        return new Response(200, Gerador\Contrato::aceita($request));
    },
]);

$obRouter->get('/gerador', [
    'middlewares' => $geradorAuth,
    function ($request) {
        return new Response(200, Gerador\Dashboard::index($request));
    },
]);

$obRouter->get('/gerador/agendamentos', [
    'middlewares' => $geradorAuth,
    function ($request) {
        return new Response(200, Gerador\Agendamentos::index($request));
    },
]);

$obRouter->post('/gerador/agendamentos', [
    'middlewares' => $geradorAuth,
    function ($request) {
        return new Response(200, Gerador\Agendamentos::post($request), 'application/json');
    },
]);

$obRouter->get('/gerador/coletas', [
    'middlewares' => $geradorAuth,
    function ($request) {
        return new Response(200, Gerador\Coletas::index($request));
    },
]);

$obRouter->get('/gerador/coletas/{id}/evidencias/{ordem}', [
    'middlewares' => $geradorAuth,
    function ($request, int $id, int $ordem) {
        return Gerador\Coletas::evidencia($request, $id, $ordem);
    },
]);

$obRouter->get('/gerador/coletas/{id}/mtr', [
    'middlewares' => $geradorAuth,
    function ($request, int $id) {
        return new Response(200, Gerador\Coletas::mtr($request, $id));
    },
]);

$obRouter->get('/gerador/coletas/{id}/cdf', [
    'middlewares' => $geradorAuth,
    function ($request, int $id) {
        $out = Gerador\Coletas::cdf($request, $id);
        return $out instanceof Response ? $out : new Response(200, $out);
    },
]);

$obRouter->get('/gerador/coletas/{id}', [
    'middlewares' => $geradorAuth,
    function ($request, int $id) {
        return new Response(200, Gerador\Coletas::show($request, $id));
    },
]);

$obRouter->get('/gerador/perfil', [
    'middlewares' => $geradorAuth,
    function ($request) {
        return new Response(200, Gerador\Perfil::index($request));
    },
]);

$obRouter->post('/gerador/perfil', [
    'middlewares' => $geradorAuth,
    function ($request) {
        Gerador\Perfil::saveSenha($request);
    },
]);

$obRouter->get('/gerador/boletos', [
    'middlewares' => $geradorAuth,
    function ($request) {
        return new Response(200, Gerador\Boletos::index($request));
    },
]);

$obRouter->get('/gerador/boletos/{id}', [
    'middlewares' => $geradorAuth,
    function ($request, int $id) {
        return new Response(200, Gerador\Boletos::show($request, $id));
    },
]);

$obRouter->get('/gerador/boletos/{id}/pdf', [
    'middlewares' => $geradorAuth,
    function ($request, int $id) {
        return Gerador\Boletos::pdf($request, $id);
    },
]);
