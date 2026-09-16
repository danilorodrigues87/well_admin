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

    public static function getByColetaOrdem(int $coletaId, int $ordem): ?self
    {
        $db = new Database();
        $row = $db->execute(
            'SELECT * FROM coleta_evidencias WHERE coleta_id = ? AND ordem = ? LIMIT 1',
            [$coletaId, $ordem]
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::fromArray($row) : null;
    }

    public static function countByColeta(int $coletaId): int
    {
        $db = new Database();
        $row = $db->execute(
            'SELECT COUNT(*) AS qtd FROM coleta_evidencias WHERE coleta_id = ?',
            [$coletaId]
        )->fetch(PDO::FETCH_ASSOC);

        return (int)($row['qtd'] ?? 0);
    }

    public static function deleteById(int $id): void
    {
        (new Database())->execute('DELETE FROM coleta_evidencias WHERE id = ?', [$id]);
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
