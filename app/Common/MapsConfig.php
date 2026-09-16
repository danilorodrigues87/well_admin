<?php

namespace App\Common;

class MapsConfig
{
    public static function apiKey(): string
    {
        return trim((string)Environment::get('GOOGLE_MAPS_API_KEY', ''));
    }

    public static function isConfigured(): bool
    {
        return self::apiKey() !== '';
    }

    /** @return array{lat:float,lng:float}|null */
    public static function originFallback(): ?array
    {
        $lat = trim((string)Environment::get('MAPS_ORIGIN_FALLBACK_LAT', ''));
        $lng = trim((string)Environment::get('MAPS_ORIGIN_FALLBACK_LNG', ''));
        if ($lat === '' || $lng === '' || !is_numeric($lat) || !is_numeric($lng)) {
            return null;
        }

        return ['lat' => (float)$lat, 'lng' => (float)$lng];
    }
}
