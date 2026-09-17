<?php

namespace App\Service;

use App\Common\OperadoraScope;
use App\Model\Db\Database;
use PDO;

class FrotaLocalizacaoService
{
    /** @param array{accuracy_m?:float,heading?:float,speed_mps?:float,veiculo_id?:int,fonte?:string} $meta */
    public static function registrarPosicao(int $usuarioId, float $latitude, float $longitude, array $meta = []): void
    {
        if ($usuarioId <= 0) {
            throw new \InvalidArgumentException('Usuário inválido.');
        }
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new \InvalidArgumentException('Coordenadas inválidas.');
        }
        if ($latitude === 0.0 && $longitude === 0.0) {
            throw new \InvalidArgumentException('Coordenadas inválidas.');
        }

        $fonte = (string)($meta['fonte'] ?? 'web');
        if (!in_array($fonte, ['web', 'app', 'rastreador'], true)) {
            $fonte = 'web';
        }

        $veiculoId = isset($meta['veiculo_id']) ? (int)$meta['veiculo_id'] : null;
        if ($veiculoId !== null && $veiculoId <= 0) {
            $veiculoId = null;
        }

        $db = new Database();
        $db->execute(
            'INSERT INTO frota_posicoes
                (operadora_id, usuario_id, veiculo_id, latitude, longitude, accuracy_m, heading, speed_mps, fonte)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [
                OperadoraScope::getOperadoraId(),
                $usuarioId,
                $veiculoId,
                round($latitude, 7),
                round($longitude, 7),
                isset($meta['accuracy_m']) ? round((float)$meta['accuracy_m'], 2) : null,
                isset($meta['heading']) ? round((float)$meta['heading'], 2) : null,
                isset($meta['speed_mps']) ? round((float)$meta['speed_mps'], 3) : null,
                $fonte,
            ]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function listarUltimasPosicoes(int $minutos = 120): array
    {
        $minutos = max(5, min(720, $minutos));
        $opId = OperadoraScope::getOperadoraId();
        $desde = date('Y-m-d H:i:s', time() - ($minutos * 60));

        $db = new Database();
        $stmt = $db->execute(
            'SELECT fp.id, fp.usuario_id, fp.latitude, fp.longitude, fp.accuracy_m, fp.heading,
                    fp.speed_mps, fp.fonte, fp.registrado_em, u.nome AS usuario_nome
             FROM frota_posicoes fp
             INNER JOIN (
                SELECT usuario_id, MAX(id) AS max_id
                FROM frota_posicoes
                WHERE operadora_id = ? AND registrado_em >= ?
                GROUP BY usuario_id
             ) ult ON ult.max_id = fp.id
             INNER JOIN usuarios u ON u.id = fp.usuario_id AND u.operadora_id = fp.operadora_id
             WHERE fp.operadora_id = ?
             ORDER BY u.nome ASC',
            [$opId, $desde, $opId]
        );

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
                'usuario_id' => (int)$row['usuario_id'],
                'usuario_nome' => (string)$row['usuario_nome'],
                'latitude' => (float)$row['latitude'],
                'longitude' => (float)$row['longitude'],
                'accuracy_m' => $row['accuracy_m'] !== null ? (float)$row['accuracy_m'] : null,
                'heading' => $row['heading'] !== null ? (float)$row['heading'] : null,
                'speed_mps' => $row['speed_mps'] !== null ? (float)$row['speed_mps'] : null,
                'fonte' => (string)$row['fonte'],
                'registrado_em' => (string)$row['registrado_em'],
            ];
        }

        return $rows;
    }
}
