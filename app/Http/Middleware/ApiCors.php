<?php

namespace App\Http\Middleware;

use App\Common\ApiConfig;
use App\Http\Response;

class ApiCors
{
    public function handle($request, $next)
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = ApiConfig::corsOrigins();

        if (in_array('*', $allowed, true)) {
            header('Access-Control-Allow-Origin: *');
        } elseif ($origin !== '' && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: '.$origin);
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
        header('Access-Control-Max-Age: 86400');

        if ($request->getHttpMethod() === 'OPTIONS') {
            return new Response(204, '', 'application/json');
        }

        return $next($request);
    }
}
