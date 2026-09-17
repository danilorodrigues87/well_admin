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
        if ($endereco === '' || !MapsConfig::isServerConfigured()) {
            return null;
        }

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?'.http_build_query([
            'address' => $endereco,
            'key' => MapsConfig::serverApiKey(),
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

        if (preg_match('/[!]3d(-?\d+(?:\.\d+)?)[!]4d(-?\d+(?:\.\d+)?)/', $link, $m)) {
            return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
        }

        if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $link, $m)) {
            return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
        }
        if (preg_match('/[?&]q=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $link, $m)) {
            return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
        }
        if (preg_match('/[?&]query=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $link, $m)) {
            return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
        }
        if (preg_match('/[?&]ll=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $link, $m)) {
            return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
        }

        return null;
    }

    /**
     * Resolve links encurtados (maps.app.goo.gl) e extrai coordenadas.
     *
     * @return array{lat:float,lng:float}|null
     */
    public static function coordsFromMapsLink(string $link): ?array
    {
        $link = trim($link);
        if ($link === '') {
            return null;
        }

        $coords = self::extractCoordsFromMapsLink($link);
        if ($coords !== null) {
            return $coords;
        }

        $resolved = self::resolveMapsLinkFinalUrl($link);
        if ($resolved !== '' && $resolved !== $link) {
            return self::extractCoordsFromMapsLink($resolved);
        }

        return null;
    }

    /** Segue redirects HTTP até a URL final do Google Maps. */
    public static function resolveMapsLinkFinalUrl(string $link): string
    {
        $link = trim($link);
        if ($link === '' || !preg_match('#^https?://#i', $link)) {
            return $link;
        }

        if (self::extractCoordsFromMapsLink($link) !== null) {
            return $link;
        }

        if (!preg_match('#(?:maps\.app\.goo\.gl|goo\.gl/maps|google\.com/maps|maps\.google\.com)#i', $link)) {
            return $link;
        }

        $ch = curl_init($link);
        if ($ch === false) {
            return $link;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPGET => true,
        ]);
        curl_exec($ch);
        $final = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        return $final !== '' ? $final : $link;
    }

    public static function geocodeCliente(int $clienteId): bool
    {
        $cliente = EntityCliente::getById($clienteId);
        if (!$cliente) {
            return false;
        }

        $coords = self::resolverCoordenadasCliente($cliente);

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

    /** @return array{lat:float,lng:float}|null */
    private static function resolverCoordenadasCliente(EntityCliente $cliente): ?array
    {
        if ($cliente->maps_link !== '') {
            $coords = self::coordsFromMapsLink($cliente->maps_link);
            if ($coords !== null) {
                return $coords;
            }
        }

        $tentativas = [];
        $completo = EntityCliente::enderecoCompleto($cliente);
        if ($completo !== '') {
            $tentativas[] = $completo;
        }
        $rua = trim($cliente->logradouro);
        $num = trim($cliente->numero);
        $cid = trim($cliente->cidade);
        $uf = trim($cliente->uf);
        if ($rua !== '' && $cid !== '') {
            $tentativas[] = trim($rua.($num !== '' ? ', '.$num : '').', '.$cid.($uf !== '' ? ', '.$uf : '').', Brasil');
        }
        if ($cid !== '' && $uf !== '') {
            $tentativas[] = $cid.', '.$uf.', Brasil';
        }

        foreach (array_unique(array_filter($tentativas)) as $endereco) {
            $coords = self::geocodeEndereco($endereco);
            if ($coords !== null) {
                return $coords;
            }
        }

        return null;
    }

    /**
     * Normaliza parada da API de rotas para o formato do painel (latitude/longitude).
     *
     * @param array<string,mixed> $parada
     * @return array<string,mixed>
     */
    public static function normalizarParadaCoords(array $parada): array
    {
        if (!isset($parada['latitude']) && isset($parada['lat'])) {
            $parada['latitude'] = (float)$parada['lat'];
            $parada['longitude'] = (float)($parada['lng'] ?? 0);
        }
        if (!isset($parada['nome_fantasia']) && isset($parada['nome'])) {
            $parada['nome_fantasia'] = (string)$parada['nome'];
        }
        if (isset($parada['latitude'], $parada['longitude'])) {
            $parada['geocode_status'] = 'ok';
            if (empty($parada['maps_url'])) {
                $parada['maps_url'] = self::buildNavigationUrl(
                    (float)$parada['latitude'],
                    (float)$parada['longitude']
                );
            }
        }

        return $parada;
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

        if (!MapsConfig::isServerConfigured()) {
            throw new \RuntimeException('Google Maps não configurado (GOOGLE_MAPS_API_KEY / GOOGLE_MAPS_SERVER_API_KEY).');
        }

        if (count($stops) === 1) {
            $stop = $stops[0];

            return [
                'ordem' => [['cliente_id' => (int)$stop['cliente_id'], 'ordem' => 1]],
                'polyline' => null,
                'distancia_metros' => null,
                'duracao_segundos' => null,
                'paradas' => [self::normalizarParadaCoords(array_merge($stop, ['ordem' => 1]))],
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
            'X-Goog-Api-Key: '.MapsConfig::serverApiKey(),
            'X-Goog-FieldMask: routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline,routes.optimizedIntermediateWaypointIndex',
        ]);

        if (!$result['ok'] || empty($result['body']['routes'][0])) {
            throw new \RuntimeException(self::mensagemErroRoutesApi($result));
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
            $ordered[] = self::normalizarParadaCoords(array_merge($stop, ['ordem' => $n]));
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

    /** @param array{ok:bool,status:int,body:?array,raw:string,error:?string} $result */
    private static function mensagemErroRoutesApi(array $result): string
    {
        $body = $result['body'] ?? null;
        if (is_array($body) && isset($body['error']['message'])) {
            $details = $body['error']['details'] ?? [];
            foreach ($details as $d) {
                if (!is_array($d)) {
                    continue;
                }
                if (($d['reason'] ?? '') === 'API_KEY_HTTP_REFERER_BLOCKED') {
                    return 'A chave do Google Maps está restrita ao navegador. Crie uma chave de servidor '
                        .'(APIs Routes + Geocoding, restrição por IP do servidor ou sem referrer) e defina '
                        .'GOOGLE_MAPS_SERVER_API_KEY no .env.';
                }
            }

            return 'Não foi possível calcular a rota: '.(string)$body['error']['message'];
        }

        $fallback = $result['error'] ?? 'Erro desconhecido';

        return 'Não foi possível calcular a rota. '.$fallback;
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
