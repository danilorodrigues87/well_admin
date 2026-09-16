<?php

namespace App\Service;

use App\Common\OperadoraScope;
use App\Model\Db\Database;
use App\Model\Entity\Cliente as EntityCliente;
use PDO;

class RotaDoDiaService
{
    /** @return list<array<string,mixed>> */
    public static function listarParadas(int $coletorId, bool $isAdmin): array
    {
        $clientes = RotaScopeService::paradasDoDia($coletorId, $isAdmin);
        $paradas = array_map(fn (EntityCliente $c) => self::paradaFromCliente($c), $clientes);
        $paradas = self::aplicarOrdemSalva($paradas, $coletorId);

        return $paradas;
    }

    /**
     * @param list<int> $clienteIds
     * @return array<string,mixed>
     */
    public static function otimizar(
        int $coletorId,
        bool $isAdmin,
        float $originLat,
        float $originLng,
        array $clienteIds = []
    ): array {
        $paradas = self::listarParadas($coletorId, $isAdmin);
        if ($clienteIds !== []) {
            $ids = array_flip(array_map('intval', $clienteIds));
            $paradas = array_values(array_filter($paradas, fn ($p) => isset($ids[(int)$p['cliente_id']])));
        }

        $stops = [];
        $semCoords = [];
        foreach ($paradas as $p) {
            $lat = $p['latitude'] ?? null;
            $lng = $p['longitude'] ?? null;
            if ($lat === null || $lng === null) {
                if (GoogleMapsService::geocodeCliente((int)$p['cliente_id'])) {
                    $c = EntityCliente::getById((int)$p['cliente_id']);
                    $lat = $c?->latitude;
                    $lng = $c?->longitude;
                    $p['latitude'] = $lat;
                    $p['longitude'] = $lng;
                    $p['geocode_status'] = 'ok';
                }
            }
            if ($lat === null || $lng === null) {
                $semCoords[] = $p;

                continue;
            }
            $stops[] = [
                'cliente_id' => (int)$p['cliente_id'],
                'lat' => (float)$lat,
                'lng' => (float)$lng,
                'nome' => (string)$p['nome_fantasia'],
                'endereco' => (string)$p['endereco'],
                'prioridade' => (string)$p['prioridade'],
                'proxima_coleta' => $p['proxima_coleta'],
            ];
        }

        if ($stops === [] && $semCoords !== []) {
            throw new \RuntimeException('Nenhuma parada com coordenadas válidas. Verifique endereços ou links do Maps nos clientes.');
        }

        $result = GoogleMapsService::optimizeRoute($originLat, $originLng, $stops);

        if (!empty($result['ordem'])) {
            self::salvarOrdem($coletorId, $result['ordem'], 'otimizada');
        }

        foreach ($semCoords as $extra) {
            $result['paradas'][] = array_merge($extra, [
                'ordem' => count($result['paradas']) + 1,
                'sem_coordenadas' => true,
            ]);
        }

        $result['origin'] = ['lat' => $originLat, 'lng' => $originLng];
        $result['sem_coordenadas'] = count($semCoords);

        return $result;
    }

    /** @param list<array{cliente_id:int,ordem:int}> $ordem */
    public static function salvarOrdem(int $coletorId, array $ordem, string $origem = 'manual'): void
    {
        if ($coletorId <= 0 || $ordem === []) {
            return;
        }
        $data = date('Y-m-d');
        $db = new Database();
        $db->beginTransaction();
        try {
            $opId = OperadoraScope::getOperadoraId();
            $db->execute(
                'DELETE FROM rota_dia_ordem WHERE coletor_id = ? AND data = ? AND operadora_id = ?',
                [$coletorId, $data, $opId]
            );
            foreach ($ordem as $item) {
                $clienteId = (int)($item['cliente_id'] ?? 0);
                $pos = (int)($item['ordem'] ?? 0);
                if ($clienteId <= 0 || $pos <= 0) {
                    continue;
                }
                $db->execute(
                    'INSERT INTO rota_dia_ordem (operadora_id, coletor_id, data, cliente_id, ordem, origem) VALUES (?,?,?,?,?,?)',
                    [$opId, $coletorId, $data, $clienteId, $pos, in_array($origem, ['otimizada', 'manual'], true) ? $origem : 'manual']
                );
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** @param list<array<string,mixed>> $paradas */
    private static function aplicarOrdemSalva(array $paradas, int $coletorId): array
    {
        if ($coletorId <= 0 || $paradas === []) {
            return $paradas;
        }
        $db = new Database();
        $stmt = $db->execute(
            'SELECT cliente_id, ordem FROM rota_dia_ordem
             WHERE coletor_id = ? AND data = ? AND operadora_id = ?
             ORDER BY ordem ASC',
            [$coletorId, date('Y-m-d'), OperadoraScope::getOperadoraId()]
        );
        $ordemMap = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ordemMap[(int)$row['cliente_id']] = (int)$row['ordem'];
        }
        if ($ordemMap === []) {
            return $paradas;
        }

        usort($paradas, function (array $a, array $b) use ($ordemMap): int {
            $oa = $ordemMap[(int)$a['cliente_id']] ?? 9999;
            $ob = $ordemMap[(int)$b['cliente_id']] ?? 9999;
            if ($oa === $ob) {
                return strcmp((string)$a['nome_fantasia'], (string)$b['nome_fantasia']);
            }

            return $oa <=> $ob;
        });

        foreach ($paradas as $i => &$p) {
            $p['ordem'] = $i + 1;
        }
        unset($p);

        return $paradas;
    }

    /** @return array<string,mixed> */
    private static function paradaFromCliente(EntityCliente $c): array
    {
        $lat = $c->latitude;
        $lng = $c->longitude;

        return [
            'cliente_id' => $c->id,
            'nome_fantasia' => $c->nome_fantasia,
            'endereco' => EntityCliente::enderecoCompleto($c),
            'cidade' => $c->cidade,
            'uf' => $c->uf,
            'prioridade' => $c->prioridade,
            'proxima_coleta' => $c->proxima_coleta,
            'latitude' => $lat,
            'longitude' => $lng,
            'geocode_status' => $c->geocode_status,
            'maps_url' => ($lat !== null && $lng !== null)
                ? GoogleMapsService::buildNavigationUrl((float)$lat, (float)$lng)
                : null,
        ];
    }
}
