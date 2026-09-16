<?php

namespace App\Service;

use App\Common\MapsConfig;
use App\Model\Entity\Cliente as EntityCliente;

class GoogleMapsService
{
    /** @return array{lat:float,lng:float}|null */
    public static function geocodeEndereco(string $endereco): ?array
    {
        $endereco = trim($endereco);
        if ($endereco === '' || !MapsConfig::isConfigured()) {
            return null;
        }

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?'.http_build_query([
            'address' => $endereco,
            'key' => MapsConfig::apiKey(),
            'language' => 'pt-BR',
            'region' => 'br',
        ]);

        $result = self::curlJson('GET', $url);
        if (!$result['ok'] || !is_array($result['body'])) {
            return null;
        }

        $status = (string)($result['body']['status'] ?? '');
        if ($status !== 'OK' || empty($result['body']['results'][0]['geometry']['location'])) {
            return null;
        }

        $loc = $result['body']['results'][0]['geometry']['location'];

        return [
            'lat' => (float)$loc['lat'],
            'lng' => (float)$loc['lng'],
        ];
    }

    /** @return array{lat:float,lng:float}|null */
    public static function extractCoordsFromMapsLink(string $link): ?array
    {
        $link = trim($link);
        if ($link === '') {
            return null;
        }

        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $link, $m)) {
            return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
        }
        if (preg_match('/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/', $link, $m)) {
            return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
        }
        if (preg_match('/[?&]query=(-?\d+\.\d+),(-?\d+\.\d+)/', $link, $m)) {
            return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
        }

        return null;
    }

    public static function geocodeCliente(int $clienteId): bool
    {
        $cliente = EntityCliente::getById($clienteId);
        if (!$cliente) {
            return false;
        }

        $coords = null;
        if ($cliente->maps_link !== '') {
            $coords = self::extractCoordsFromMapsLink($cliente->maps_link);
        }
        if ($coords === null) {
            $endereco = EntityCliente::enderecoCompleto($cliente);
            if ($endereco === '') {
                EntityCliente::update($clienteId, ['geocode_status' => 'erro']);

                return false;
            }
            $coords = self::geocodeEndereco($endereco);
        }

        if ($coords === null) {
            EntityCliente::update($clienteId, ['geocode_status' => 'erro']);

            return false;
        }

        EntityCliente::update($clienteId, [
            'latitude' => $coords['lat'],
            'longitude' => $coords['lng'],
            'geocode_status' => 'ok',
            'geocoded_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Otimiza ordem de paradas a partir da origem (GPS).
     *
     * @param list<array{cliente_id:int,lat:float,lng:float,nome:string}> $stops
     * @return array{
     *   ordem:list<array{cliente_id:int,ordem:int}>,
     *   polyline:?string,
     *   distancia_metros:?int,
     *   duracao_segundos:?int,
     *   paradas:list<array<string,mixed>>
     * }
     */
    public static function optimizeRoute(float $originLat, float $originLng, array $stops): array
    {
        if ($stops === []) {
            return [
                'ordem' => [],
                'polyline' => null,
                'distancia_metros' => null,
                'duracao_segundos' => null,
                'paradas' => [],
            ];
        }

        if (!MapsConfig::isConfigured()) {
            throw new \RuntimeException('Google Maps não configurado (GOOGLE_MAPS_API_KEY).');
        }

        if (count($stops) === 1) {
            $stop = $stops[0];

            return [
                'ordem' => [['cliente_id' => (int)$stop['cliente_id'], 'ordem' => 1]],
                'polyline' => null,
                'distancia_metros' => null,
                'duracao_segundos' => null,
                'paradas' => [array_merge($stop, ['ordem' => 1])],
            ];
        }

        $intermediates = [];
        foreach ($stops as $stop) {
            $intermediates[] = [
                'location' => [
                    'latLng' => [
                        'latitude' => (float)$stop['lat'],
                        'longitude' => (float)$stop['lng'],
                    ],
                ],
            ];
        }

        $latLng = static fn (float $lat, float $lng): array => [
            'location' => ['latLng' => ['latitude' => $lat, 'longitude' => $lng]],
        ];

        $body = [
            'origin' => $latLng($originLat, $originLng),
            'destination' => $latLng($originLat, $originLng),
            'intermediates' => $intermediates,
            'travelMode' => 'DRIVE',
            'optimizeWaypointOrder' => true,
            'languageCode' => 'pt-BR',
            'units' => 'METRIC',
        ];

        $url = 'https://routes.googleapis.com/directions/v2:computeRoutes';
        $result = self::curlJson('POST', $url, $body, [
            'Content-Type: application/json',
            'X-Goog-Api-Key: '.MapsConfig::apiKey(),
            'X-Goog-FieldMask: routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline,routes.optimizedIntermediateWaypointIndex',
        ]);

        if (!$result['ok'] || empty($result['body']['routes'][0])) {
            $msg = is_array($result['body']) ? json_encode($result['body'], JSON_UNESCAPED_UNICODE) : ($result['error'] ?? 'Erro desconhecido');

            throw new \RuntimeException('Falha ao calcular rota: '.$msg);
        }

        $route = $result['body']['routes'][0];
        $indices = $route['optimizedIntermediateWaypointIndex'] ?? range(0, count($stops) - 1);

        $ordered = [];
        $ordem = [];
        foreach ($indices as $i => $idx) {
            $stop = $stops[(int)$idx] ?? null;
            if ($stop === null) {
                continue;
            }
            $n = $i + 1;
            $ordem[] = ['cliente_id' => (int)$stop['cliente_id'], 'ordem' => $n];
            $ordered[] = array_merge($stop, ['ordem' => $n]);
        }

        $duracao = null;
        if (!empty($route['duration']) && preg_match('/^(\d+)s$/', (string)$route['duration'], $m)) {
            $duracao = (int)$m[1];
        }

        return [
            'ordem' => $ordem,
            'polyline' => $route['polyline']['encodedPolyline'] ?? null,
            'distancia_metros' => isset($route['distanceMeters']) ? (int)$route['distanceMeters'] : null,
            'duracao_segundos' => $duracao,
            'paradas' => $ordered,
        ];
    }

    public static function buildNavigationUrl(float $lat, float $lng): string
    {
        return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($lat.','.$lng);
    }

    /**
     * @param array<string,mixed>|null $body
     * @param list<string> $headers
     * @return array{ok:bool,status:int,body:?array,raw:string,error:?string}
     */
    private static function curlJson(string $method, string $url, ?array $body = null, array $headers = []): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'raw' => '', 'error' => 'curl_init falhou'];
        }

        $payload = $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE) : null;
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ];
        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = $payload;
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'status' => $status, 'body' => null, 'raw' => '', 'error' => $error ?: 'curl_exec falhou'];
        }

        $decoded = json_decode($raw, true);

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => is_array($decoded) ? $decoded : null,
            'raw' => (string)$raw,
            'error' => $error !== '' ? $error : null,
        ];
    }
}
