<?php

use App\Controller\PublicPages;
use App\Http\Response;

$obRouter->get('/privacidade', [
    function ($request) {
        return new Response(200, PublicPages\Privacidade::index($request));
    },
]);

$obRouter->get('/manifest.webmanifest', [
    function ($request) {
        return new Response(200, PublicPages\Pwa::manifest(), 'application/manifest+json');
    },
]);

$obRouter->get('/sw.js', [
    function ($request) {
        return new Response(200, PublicPages\Pwa::serviceWorker(), 'application/javascript; charset=utf-8');
    },
]);
