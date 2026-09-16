<?php

namespace App\Service;

use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\ColetaEvidencia as EntityColetaEvidencia;
use App\Model\Entity\ColetaItem as EntityColetaItem;
use App\Model\Entity\ColetaSnapshot as EntityColetaSnapshot;
use App\Model\Entity\InterCobranca as EntityInterCobranca;

class GeradorApiPresenter
{
    /** @param array<string,mixed> $user */
    public static function usuario(array $user): array
    {
        return [
            'id' => (int)($user['cliente_usuario_id'] ?? $user['id'] ?? 0),
            'nome' => (string)($user['nome'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
            'cliente_id' => (int)($user['cliente_id'] ?? 0),
            'cliente_nome' => (string)($user['cliente_nome'] ?? ''),
        ];
    }

    public static function coletaResumo(EntityColeta $c): array
    {
        return [
            'id' => $c->id,
            'numero_mtr' => $c->numero_mtr,
            'status' => $c->status,
            'data_coleta' => $c->data_coleta,
            'hora' => $c->hora,
            'situacao_recebimento' => $c->situacao_recebimento,
            'sinir_status' => $c->sinir_status,
        ];
    }

    /** @param array{coleta:EntityColeta,snapshot:?EntityColetaSnapshot,itens:EntityColetaItem[],evidencias:EntityColetaEvidencia[]} $detalhe */
    public static function coletaDetalhe(array $detalhe): array
    {
        $c = $detalhe['coleta'];
        $s = $detalhe['snapshot'];
        $base = ColetaApiPresenter::coletaDetalhe($detalhe);
        $base['coleta']['mtr_url'] = $c->numero_mtr
            ? URL.'/gerador/coletas/'.$c->id.'/mtr'
            : null;

        foreach ($base['evidencias'] as $i => $ev) {
            $base['evidencias'][$i]['url'] = URL.'/api/v1/gerador/coletas/'.$c->id.'/evidencias/'.$ev['ordem'];
        }

        unset($base['tratamentos']);

        return $base;
    }

    public static function boleto(EntityInterCobranca $b): array
    {
        $detalhes = $b->getDetalhesParsed();

        return [
            'id' => $b->id,
            'competencia' => $b->competencia,
            'valor_nominal' => $b->valor_nominal,
            'valor_calculado' => $b->valor_calculado,
            'valor_cobrado' => $b->valorCobrado(),
            'data_vencimento' => $b->data_vencimento,
            'status' => $b->status,
            'linha_digitavel' => $b->linha_digitavel,
            'pix_copia_cola' => $b->pix_copia_cola,
            'pdf_url' => $b->pdf_path ? URL.'/gerador/boletos/'.$b->id.'/pdf' : null,
            'observacao_ajuste' => $b->observacao_ajuste,
            'detalhes' => $detalhes,
            'valor_fixo' => (float)($detalhes['valor_fixo'] ?? 0),
            'valor_residuos' => (float)($detalhes['valor_residuos'] ?? 0),
        ];
    }
}
