<?php

use App\Controller\Autentication;
use App\Http\Response;

$obRouter->get('/', [
    'middlewares' => ['required-admin-logout'],
    function ($request) {
        return new Response(200, Autentication\Login::getLogin($request));
    },
]);

$obRouter->post('/', [
    'middlewares' => ['required-admin-logout'],
    function ($request) {
        return new Response(200, Autentication\Login::setLogin($request));
    },
]);

$obRouter->get('/logout', [
    'middlewares' => ['required-admin-login'],
    function ($request) {
        return new Response(200, Autentication\Login::setLogout($request));
    },
]);
