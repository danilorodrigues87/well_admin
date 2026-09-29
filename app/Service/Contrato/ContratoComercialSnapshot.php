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
        $plano = Plano::getById($planoId);
        $itens = PlanoItem::getByPlanoId($planoId);
        usort($itens, fn (PlanoItem $a, PlanoItem $b) => $a->ordem <=> $b->ordem);

        $rows = [];
        foreach ($itens as $item) {
            $rows[] = [
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

        return [
            'valor_mensal' => round($valorMensal, 2),
            'taxa_adesao' => $taxaAdesao !== null ? round($taxaAdesao, 2) : null,
            'plano_id' => $planoId,
            'plano_nome' => $plano?->nome ?? '',
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
