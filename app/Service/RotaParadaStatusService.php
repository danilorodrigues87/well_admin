<?php

namespace App\Service;

use App\Common\OperadoraScope;
use App\Model\Db\Database;
use PDO;

class RotaParadaStatusService
{
    /** @return array<int,string> cliente_id => status */
    public static function mapaStatus(int $coletorId, string $data): array
    {
        if ($coletorId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return [];
        }

        $db = new Database();
        $stmt = $db->execute(
            'SELECT cliente_id, status FROM rota_dia_parada_status
             WHERE operadora_id = ? AND coletor_id = ? AND data = ?',
            [OperadoraScope::getOperadoraId(), $coletorId, $data]
        );

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[(int)$row['cliente_id']] = (string)$row['status'];
        }

        return $map;
    }

    public static function definirStatus(int $coletorId, string $data, int $clienteId, string $status): void
    {
        if ($coletorId <= 0 || $clienteId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            throw new \InvalidArgumentException('Dados inválidos.');
        }
        if (!in_array($status, ['pendente', 'coletado', 'pulado'], true)) {
            throw new \InvalidArgumentException('Status inválido.');
        }

        $opId = OperadoraScope::getOperadoraId();
        $db = new Database();

        if ($status === 'pendente') {
            $db->execute(
                'DELETE FROM rota_dia_parada_status
                 WHERE operadora_id = ? AND coletor_id = ? AND data = ? AND cliente_id = ?',
                [$opId, $coletorId, $data, $clienteId]
            );

            return;
        }

        $db->execute(
            'INSERT INTO rota_dia_parada_status (operadora_id, coletor_id, data, cliente_id, status)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), atualizado_em = CURRENT_TIMESTAMP',
            [$opId, $coletorId, $data, $clienteId, $status]
        );
    }

    /** @param list<array<string,mixed>> $paradas */
    public static function enriquecerParadas(array $paradas, int $coletorId, string $data): array
    {
        if ($paradas === []) {
            return $paradas;
        }

        $map = self::mapaStatus($coletorId, $data);
        foreach ($paradas as &$p) {
            $cid = (int)($p['cliente_id'] ?? 0);
            $p['status_parada'] = $map[$cid] ?? 'pendente';
        }
        unset($p);

        return $paradas;
    }
}
