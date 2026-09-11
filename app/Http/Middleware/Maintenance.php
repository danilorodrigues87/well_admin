<?php

namespace App\Http\Middleware;

class Maintenance
{
    public function handle($request, $next)
    {
        if (getenv('MAINTENANCE') === 'true') {
            throw new \Exception('Sistema em manutenção. Tente novamente mais tarde.', 503);
        }
        return $next($request);
    }
}
