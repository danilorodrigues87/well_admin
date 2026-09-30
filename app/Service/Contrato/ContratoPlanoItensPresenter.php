<?php

namespace App\Service\Contrato;

use App\Common\Contrato\ContractType;
use App\Common\Contrato\ContratoModeloCatalog;
use App\Model\Entity\Plano;
use App\Model\Entity\PlanoItem;

/**
 * Dados de plano_itens para UI de criação/edição de contrato.
 */
final class ContratoPlanoItensPresenter
{
    /** @return array{ok:bool,error?:string,plano?:array<string,mixed>,itens?:list<array<string,mixed>>} */
    public static function payloadForPlano(int $planoId): array
    {
        $plano = Plano::getById($planoId);
        if (!$plano) {
            return ['ok' => false, 'error' => 'Plano não encontrado'];
        }

        $slug = ContractType::normalize($plano->contrato_modelo_tipo ?? ContractType::GENERICO);
        $meta = ContratoModeloCatalog::meta($slug);
        $itens = PlanoItem::getByPlanoId($planoId);
        usort($itens, fn (PlanoItem $a, PlanoItem $b) => $a->ordem <=> $b->ordem);

        $rows = [];
        foreach ($itens as $item) {
            $flags = [];
            if ($item->saldo_compartilhado) {
                $flags[] = 'Saldo compartilhado';
            }
            if ($item->gera_credito) {
                $flags[] = 'Crédito';
            }
            $rows[] = [
                'tipo_residuo_id' => $item->tipo_residuo_id,
                'tipo_nome' => $item->tipo_nome,
                'tipo_cod_ibama' => $item->tipo_cod_ibama,
                'saldo_incluso' => $item->saldo_incluso,
                'unidade' => $item->unidade ?: 'kg',
                'valor_excedente' => $item->valor_excedente,
                'saldo_compartilhado' => $item->saldo_compartilhado,
                'gera_credito' => $item->gera_credito,
                'flags_label' => $flags !== [] ? implode(' · ', $flags) : '—',
            ];
        }

        return [
            'ok' => true,
            'plano' => [
                'id' => $plano->id,
                'nome' => $plano->nome,
                'valor_mensal' => (float)$plano->valor_mensal,
                'contrato_modelo_tipo' => $slug,
                'contrato_modelo_label' => ContractType::label($slug),
                'default_meses' => (int)($meta['default_meses'] ?? 36),
                'pricing_model' => (string)($meta['pricing_model'] ?? 'FRANQUIA_EXCEDENTE_KG'),
            ],
            'itens' => $rows,
        ];
    }
}
