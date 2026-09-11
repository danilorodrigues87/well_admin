<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class PlanoItem
{
    public int $id = 0;
    public int $plano_id = 0;
    public ?int $tipo_residuo_id = null;
    public string $nome = '';
    public ?string $cod_ibama = null;
    public float $saldo_incluso = 0;
    public string $unidade = 'kg';
    public float $valor_excedente = 0;
    public int $ordem = 0;

    /** @return self[] */
    public static function getByPlanoId(int $planoId): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT * FROM plano_itens WHERE plano_id = ? ORDER BY ordem, id',
            [$planoId]
        );
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }
        return $items;
    }

    public static function replaceForPlano(int $planoId, array $rows): void
    {
        $db = new Database();
        $db->beginTransaction();
        try {
            $db->execute('DELETE FROM plano_itens WHERE plano_id = ?', [$planoId]);
            $ordem = 0;
            foreach ($rows as $row) {
                $nome = trim((string)($row['nome'] ?? ''));
                if ($nome === '') {
                    continue;
                }
                $db->execute(
                    'INSERT INTO plano_itens
                     (plano_id, tipo_residuo_id, nome, cod_ibama, saldo_incluso, unidade, valor_excedente, ordem)
                     VALUES (?,?,?,?,?,?,?,?)',
                    [
                        $planoId,
                        !empty($row['tipo_residuo_id']) ? (int)$row['tipo_residuo_id'] : null,
                        $nome,
                        self::nullableString($row['cod_ibama'] ?? null),
                        (float)($row['saldo_incluso'] ?? 0),
                        self::normalizeUnit((string)($row['unidade'] ?? 'kg')),
                        (float)($row['valor_excedente'] ?? 0),
                        $ordem++,
                    ]
                );
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private static function nullableString(mixed $value): ?string
    {
        $s = trim((string)$value);
        return $s === '' ? null : $s;
    }

    private static function normalizeUnit(string $unit): string
    {
        return in_array($unit, ['kg', 'l', 'un'], true) ? $unit : 'kg';
    }

    private static function fromArray(array $row): self
    {
        $i = new self();
        $i->id = (int)$row['id'];
        $i->plano_id = (int)$row['plano_id'];
        $i->tipo_residuo_id = isset($row['tipo_residuo_id']) ? (int)$row['tipo_residuo_id'] : null;
        $i->nome = (string)$row['nome'];
        $i->cod_ibama = $row['cod_ibama'] ?? null;
        $i->saldo_incluso = (float)$row['saldo_incluso'];
        $i->unidade = (string)$row['unidade'];
        $i->valor_excedente = (float)$row['valor_excedente'];
        $i->ordem = (int)($row['ordem'] ?? 0);
        return $i;
    }
}
