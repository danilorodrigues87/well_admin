<?php

require __DIR__.'/includes/app.php';

use App\Http\Router;

$obRouter = new Router(URL);

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (str_contains($requestUri, '/api/')) {
    $obRouter->setContentType('application/json');
}

include __DIR__.'/routes/api.php';
include __DIR__.'/routes/admin.php';

$obRouter->run()->sendResponse();
