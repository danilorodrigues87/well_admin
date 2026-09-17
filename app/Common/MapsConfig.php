<?php

namespace App\Common;

class MapsConfig
{
    /** Chave exposta ao navegador (Maps JavaScript). Restringir por referrer HTTP. */
    public static function apiKey(): string
    {
        return trim((string)Environment::get('GOOGLE_MAPS_API_KEY', ''));
    }

    /** Geocoding / Routes API (PHP/cURL — sem referrer). Use chave separada no Google Cloud. */
    public static function serverApiKey(): string
    {
        $server = trim((string)Environment::get('GOOGLE_MAPS_SERVER_API_KEY', ''));

        return $server !== '' ? $server : self::apiKey();
    }

    public static function isConfigured(): bool
    {
        return self::apiKey() !== '';
    }

    public static function isServerConfigured(): bool
    {
        return self::serverApiKey() !== '';
    }

    public static function hasDedicatedServerKey(): bool
    {
        return trim((string)Environment::get('GOOGLE_MAPS_SERVER_API_KEY', '')) !== '';
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
