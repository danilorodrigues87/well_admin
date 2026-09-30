<?php

namespace App\Service\Contrato;

use App\Model\Entity\Plano;
use App\Model\Entity\PlanoItem;

/**
 * Congela mensalidade + itens do plano no contrato (faturamento e cláusula de preço).
 */
final class ContratoComercialSnapshot
{
    /** @return array<string,mixed> */
    public static function fromPlano(int $planoId, float $valorMensal, ?float $taxaAdesao): array
    {
        $ids = array_map(
            fn (PlanoItem $i) => $i->tipo_residuo_id,
            PlanoItem::getByPlanoId($planoId)
        );

        return self::fromPlanoItensSelecionados($planoId, $ids, $valorMensal, $taxaAdesao);
    }

    /**
     * Snapshot com subconjunto de tipos do plano (tarifas copiadas do plano; mensalidade do contrato).
     *
     * @param list<int> $tipoResiduoIds
     *
     * @return array<string,mixed>
     */
    public static function fromPlanoItensSelecionados(
        int $planoId,
        array $tipoResiduoIds,
        float $valorMensal,
        ?float $taxaAdesao
    ): array {
        $plano = Plano::getById($planoId);
        $allowed = [];
        foreach ($tipoResiduoIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $allowed[$id] = true;
            }
        }

        $itens = PlanoItem::getByPlanoId($planoId);
        usort($itens, fn (PlanoItem $a, PlanoItem $b) => $a->ordem <=> $b->ordem);

        $rows = [];
        foreach ($itens as $item) {
            if ($allowed !== [] && !isset($allowed[$item->tipo_residuo_id])) {
                continue;
            }
            $rows[] = self::rowFromPlanoItem($item);
        }

        return self::envelope($planoId, $plano?->nome ?? '', $valorMensal, $taxaAdesao, $rows);
    }

    /**
     * Atualiza franquia/excedente dos tipos já presentes no snapshot a partir do plano vigente.
     *
     * @param array<string,mixed> $snapshot
     *
     * @return array<string,mixed>
     */
    public static function refreshItensTarifasFromPlano(array $snapshot): array
    {
        $planoId = (int)($snapshot['plano_id'] ?? 0);
        if ($planoId <= 0) {
            return $snapshot;
        }

        $selectedIds = [];
        foreach ($snapshot['itens'] ?? [] as $row) {
            if (is_array($row) && !empty($row['tipo_residuo_id'])) {
                $selectedIds[] = (int)$row['tipo_residuo_id'];
            }
        }

        $valor = (float)($snapshot['valor_mensal'] ?? 0);
        $taxa = isset($snapshot['taxa_adesao']) ? (float)$snapshot['taxa_adesao'] : null;

        return self::fromPlanoItensSelecionados($planoId, $selectedIds, $valor, $taxa);
    }

    /** @return list<int> */
    public static function tipoResiduoIdsFromSnapshot(?array $snapshot): array
    {
        if ($snapshot === null) {
            return [];
        }
        $ids = [];
        foreach ($snapshot['itens'] ?? [] as $row) {
            if (is_array($row) && !empty($row['tipo_residuo_id'])) {
                $ids[] = (int)$row['tipo_residuo_id'];
            }
        }

        return $ids;
    }

    /** @return array<string,mixed> */
    private static function rowFromPlanoItem(PlanoItem $item): array
    {
        return [
            'plano_item_id' => $item->id,
            'tipo_residuo_id' => $item->tipo_residuo_id,
            'tipo_nome' => $item->tipo_nome,
            'tipo_cod_ibama' => $item->tipo_cod_ibama,
            'saldo_incluso' => $item->saldo_incluso,
            'unidade' => $item->unidade ?: 'kg',
            'valor_excedente' => $item->valor_excedente,
            'saldo_compartilhado' => $item->saldo_compartilhado,
            'gera_credito' => $item->gera_credito,
            'ordem' => $item->ordem,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     *
     * @return array<string,mixed>
     */
    private static function envelope(
        int $planoId,
        string $planoNome,
        float $valorMensal,
        ?float $taxaAdesao,
        array $rows
    ): array {
        return [
            'valor_mensal' => round($valorMensal, 2),
            'taxa_adesao' => $taxaAdesao !== null ? round($taxaAdesao, 2) : null,
            'plano_id' => $planoId,
            'plano_nome' => $planoNome,
            'gerado_em' => date('c'),
            'itens' => $rows,
        ];
    }

    /** @return array<string,mixed>|null */
    public static function decode(?string $json): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }
        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    public static function encode(array $snapshot): string
    {
        return json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @param array<string,mixed> $snapshot @return list<PlanoItem> */
    public static function itensAsPlanoItems(array $snapshot): array
    {
        $out = [];
        foreach ($snapshot['itens'] ?? [] as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = new PlanoItem();
            $item->id = (int)($row['plano_item_id'] ?? ($i + 1));
            $item->plano_id = (int)($snapshot['plano_id'] ?? 0);
            $item->tipo_residuo_id = (int)($row['tipo_residuo_id'] ?? 0);
            $item->tipo_nome = (string)($row['tipo_nome'] ?? '');
            $item->tipo_cod_ibama = (string)($row['tipo_cod_ibama'] ?? '');
            $item->saldo_incluso = (float)($row['saldo_incluso'] ?? 0);
            $item->unidade = (string)($row['unidade'] ?? 'kg');
            $item->valor_excedente = (float)($row['valor_excedente'] ?? 0);
            $item->saldo_compartilhado = !empty($row['saldo_compartilhado']);
            $item->gera_credito = !empty($row['gera_credito']);
            $item->ordem = (int)($row['ordem'] ?? $i);
            $out[] = $item;
        }

        return $out;
    }
}
