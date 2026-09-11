<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class ColetaEvidencia
{
    public int $id = 0;
    public int $coleta_id = 0;
    public int $ordem = 1;
    public string $arquivo = '';
    public string $mime = '';

    /** @return self[] */
    public static function getByColetaId(int $coletaId): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT * FROM coleta_evidencias WHERE coleta_id = ? ORDER BY ordem',
            [$coletaId]
        );
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }
        return $items;
    }

    public static function insert(array $data): int
    {
        return (int)(new Database('coleta_evidencias'))->insert($data);
    }

    private static function fromArray(array $row): self
    {
        $e = new self();
        $e->id = (int)$row['id'];
        $e->coleta_id = (int)$row['coleta_id'];
        $e->ordem = (int)$row['ordem'];
        $e->arquivo = (string)$row['arquivo'];
        $e->mime = (string)($row['mime'] ?? '');
        return $e;
    }
}
