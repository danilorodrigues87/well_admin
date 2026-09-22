<?php

namespace App\Http\Middleware;

use App\Common\MaintenanceMode;

class Maintenance
{
    public function handle($request, $next)
    {
        if (MaintenanceMode::isActive()) {
            throw new \Exception('Sistema em manutenção. Tente novamente mais tarde.', 503);
        }

        return $next($request);
    }
}
