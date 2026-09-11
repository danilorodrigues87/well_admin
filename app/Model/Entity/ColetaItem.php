<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class ColetaItem
{
    public int $id = 0;
    public int $coleta_id = 0;
    public ?int $tipo_residuo_id = null;
    public string $nome = '';
    public string $classe_nome = '';
    public string $grupo_codigo = '';
    public string $cod_ibama = '';
    public float $quantidade = 0.0;
    public string $unidade = 'kg';

    /** @return self[] */
    public static function getByColetaId(int $coletaId): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT * FROM coleta_itens WHERE coleta_id = ? ORDER BY id',
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
        return (int)(new Database('coleta_itens'))->insert($data);
    }

    public static function delete(int $id, int $coletaId): void
    {
        (new Database())->execute(
            'DELETE FROM coleta_itens WHERE id = ? AND coleta_id = ?',
            [$id, $coletaId]
        );
    }

    public static function countByColeta(int $coletaId): int
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT COUNT(*) AS qtd FROM coleta_itens WHERE coleta_id = ?',
            [$coletaId]
        );
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    private static function fromArray(array $row): self
    {
        $i = new self();
        $i->id = (int)$row['id'];
        $i->coleta_id = (int)$row['coleta_id'];
        $i->tipo_residuo_id = isset($row['tipo_residuo_id']) ? (int)$row['tipo_residuo_id'] : null;
        $i->nome = (string)$row['nome'];
        $i->classe_nome = (string)($row['classe_nome'] ?? '');
        $i->grupo_codigo = (string)($row['grupo_codigo'] ?? '');
        $i->cod_ibama = (string)($row['cod_ibama'] ?? '');
        $i->quantidade = (float)$row['quantidade'];
        $i->unidade = (string)$row['unidade'];
        return $i;
    }
}
