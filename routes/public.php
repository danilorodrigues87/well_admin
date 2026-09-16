<?php

use App\Controller\PublicPages;
use App\Http\Response;

$obRouter->get('/privacidade', [
    function ($request) {
        return new Response(200, PublicPages\Privacidade::index($request));
    },
]);
