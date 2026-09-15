<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class PlanoItem
{
    public int $id = 0;
    public int $plano_id = 0;
    public int $tipo_residuo_id = 0;
    public string $tipo_nome = '';
    public string $tipo_cod_ibama = '';
    public float $saldo_incluso = 0;
    public string $unidade = 'kg';
    public float $valor_excedente = 0;
    public bool $saldo_compartilhado = false;
    public bool $gera_credito = false;
    public int $ordem = 0;

    /** @return self[] */
    public static function getByPlanoId(int $planoId): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT pi.*, t.nome AS tipo_nome, t.cod_ibama AS tipo_cod_ibama
             FROM plano_itens pi
             INNER JOIN tipos_residuos t ON t.id = pi.tipo_residuo_id
             WHERE pi.plano_id = ?
             ORDER BY pi.ordem, pi.id',
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
                $tipoId = (int)($row['tipo_residuo_id'] ?? 0);
                if ($tipoId <= 0) {
                    continue;
                }
                $geraCredito = !empty($row['gera_credito']);
                $db->execute(
                    'INSERT INTO plano_itens
                     (plano_id, tipo_residuo_id, saldo_incluso, unidade, valor_excedente, saldo_compartilhado, gera_credito, ordem)
                     VALUES (?,?,?,?,?,?,?,?)',
                    [
                        $planoId,
                        $tipoId,
                        $geraCredito ? 0.0 : (float)($row['saldo_incluso'] ?? 0),
                        self::normalizeUnit((string)($row['unidade'] ?? 'kg')),
                        abs((float)($row['valor_excedente'] ?? 0)),
                        $geraCredito ? 0 : (!empty($row['saldo_compartilhado']) ? 1 : 0),
                        $geraCredito ? 1 : 0,
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

    private static function normalizeUnit(string $unit): string
    {
        $u = mb_strtolower(trim($unit));

        return match ($u) {
            'l' => 'l',
            'un', 'unid', 'unidade', 'und' => 'un',
            default => 'kg',
        };
    }

    private static function fromArray(array $row): self
    {
        $i = new self();
        $i->id = (int)$row['id'];
        $i->plano_id = (int)$row['plano_id'];
        $i->tipo_residuo_id = (int)$row['tipo_residuo_id'];
        $i->tipo_nome = (string)($row['tipo_nome'] ?? '');
        $i->tipo_cod_ibama = (string)($row['tipo_cod_ibama'] ?? '');
        $i->saldo_incluso = (float)$row['saldo_incluso'];
        $i->unidade = (string)$row['unidade'];
        $i->valor_excedente = (float)$row['valor_excedente'];
        $i->saldo_compartilhado = (bool)($row['saldo_compartilhado'] ?? false);
        $i->gera_credito = (bool)($row['gera_credito'] ?? false);
        $i->ordem = (int)($row['ordem'] ?? 0);

        return $i;
    }
}
