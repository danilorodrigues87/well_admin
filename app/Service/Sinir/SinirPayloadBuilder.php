<?php

namespace App\Service\Sinir;

use App\Common\SinirConfig;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\ColetaItem as EntityColetaItem;
use App\Model\Entity\ColetaSnapshot as EntityColetaSnapshot;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;

/**
 * Monta payload para POST /salvarManifestoLote (Fase B).
 * Fase A: estrutura mínima + validação de mapeamento SINIR nos itens.
 */
class SinirPayloadBuilder
{
    /** @return array{ok:bool,payload:?array,errors:string[]} */
    public function buildManifestoLote(int $coletaId): array
    {
        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'finalizada') {
            return ['ok' => false, 'payload' => null, 'errors' => ['Coleta não encontrada ou não finalizada.']];
        }

        $snapshot = EntityColetaSnapshot::getByColetaId($coletaId);
        if (!$snapshot) {
            return ['ok' => false, 'payload' => null, 'errors' => ['Snapshot da coleta ausente.']];
        }

        $itens = EntityColetaItem::getByColetaId($coletaId);
        if ($itens === []) {
            return ['ok' => false, 'payload' => null, 'errors' => ['Coleta sem itens de resíduo.']];
        }

        $errors = [];
        $residuos = [];
        foreach ($itens as $idx => $item) {
            $tipo = $item->tipo_residuo_id ? EntityTipoResiduo::getById($item->tipo_residuo_id) : null;
            if (!$tipo || !$this->tipoTemCodigosSinir($tipo)) {
                $errors[] = 'Item "'.($item->nome ?: ('#'.$item->id)).'" sem mapeamento SINIR completo.';
                continue;
            }
            $residuos[] = [
                'traCodigo' => (int)$tipo->tra_codigo,
                'tieCodigo' => (int)$tipo->tie_codigo,
                'tiaCodigo' => (int)$tipo->tia_codigo,
                'claCodigo' => (int)$tipo->cla_codigo,
                'uniCodigo' => (int)$tipo->uni_codigo,
                'quantidade' => (float)$item->quantidade,
                'observacao' => $item->nome,
            ];
        }

        if ($errors !== []) {
            return ['ok' => false, 'payload' => null, 'errors' => $errors];
        }

        $payload = [
            'unidade' => SinirConfig::unidade(),
            'cnpj' => SinirConfig::cnpj(),
            'manifesto' => [
                'numeroMtrLocal' => $coleta->numero_mtr,
                'dataColeta' => $coleta->data_coleta,
                'gerador' => [
                    'cnpj' => preg_replace('/\D/', '', (string)$snapshot->gerador_cnpj),
                    'nome' => $snapshot->gerador_nome_fantasia ?: $snapshot->gerador_razao_social,
                ],
                'transportador' => [
                    'cnpj' => preg_replace('/\D/', '', (string)$snapshot->transportador_cnpj),
                ],
                'destinador' => [
                    'cnpj' => preg_replace('/\D/', '', (string)$snapshot->destinador_cnpj),
                ],
                'residuos' => $residuos,
            ],
        ];

        return ['ok' => true, 'payload' => $payload, 'errors' => []];
    }

    private function tipoTemCodigosSinir(EntityTipoResiduo $tipo): bool
    {
        return $tipo->tra_codigo > 0
            && $tipo->tie_codigo > 0
            && $tipo->tia_codigo > 0
            && $tipo->cla_codigo > 0
            && $tipo->uni_codigo > 0;
    }
}
