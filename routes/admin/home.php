<?php

use App\Controller\Admin;
use App\Http\Response;

$obRouter->get('/painel', [
    'middlewares' => ['required-admin-login', 'required-module:dashboard'],
    function ($request) {
        return new Response(200, Admin\Home::index($request));
    },
]);
