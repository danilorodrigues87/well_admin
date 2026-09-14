<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class SinirEnvio
{
    public int $id = 0;
    public int $coleta_id = 0;
    public int $tentativa = 1;
    public string $status = 'pendente';
    public ?string $mensagem_erro = null;

    public static function proximaTentativa(int $coletaId): int
    {
        $db = new Database();
        $row = $db->execute(
            'SELECT COALESCE(MAX(tentativa), 0) AS m FROM sinir_envios WHERE coleta_id = ?',
            [$coletaId]
        )->fetch(PDO::FETCH_ASSOC);

        return (int)($row['m'] ?? 0) + 1;
    }

    public static function registrar(
        int $coletaId,
        int $tentativa,
        string $status,
        ?array $requestPayload,
        ?array $responsePayload,
        ?string $mensagemErro
    ): int {
        $db = new Database();
        $db->execute(
            'INSERT INTO sinir_envios (coleta_id, tentativa, status, request_payload, response_payload, mensagem_erro)
             VALUES (?,?,?,?,?,?)',
            [
                $coletaId,
                $tentativa,
                $status,
                $requestPayload !== null ? json_encode($requestPayload, JSON_UNESCAPED_UNICODE) : null,
                $responsePayload !== null ? json_encode($responsePayload, JSON_UNESCAPED_UNICODE) : null,
                $mensagemErro !== null ? mb_substr($mensagemErro, 0, 500) : null,
            ]
        );

        return (int)$db->lastInsertId();
    }

    /** @return self[] */
    public static function listByColeta(int $coletaId, int $limit = 5): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT * FROM sinir_envios WHERE coleta_id = ? ORDER BY id DESC LIMIT '.(int)$limit,
            [$coletaId]
        );
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }

        return $items;
    }

    private static function fromArray(array $row): self
    {
        $e = new self();
        $e->id = (int)$row['id'];
        $e->coleta_id = (int)$row['coleta_id'];
        $e->tentativa = (int)$row['tentativa'];
        $e->status = (string)$row['status'];
        $e->mensagem_erro = isset($row['mensagem_erro']) ? (string)$row['mensagem_erro'] : null;

        return $e;
    }
}
