<?php

namespace App\Common;

class MaintenanceMode
{
    public static function isActive(): bool
    {
        $raw = Environment::get('MAINTENANCE', 'false');
        if (is_bool($raw)) {
            return $raw;
        }

        $v = strtolower(trim((string)$raw));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}
