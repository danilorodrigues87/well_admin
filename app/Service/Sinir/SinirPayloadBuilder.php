<?php

namespace App\Service\Sinir;

use App\Common\SinirConfig;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\ColetaItem as EntityColetaItem;
use App\Model\Entity\ColetaSnapshot as EntityColetaSnapshot;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;

/**
 * Monta payload para POST /salvarManifestoLote (formato manifestoJSONDtos).
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

        $cliente = EntityCliente::getById($coleta->cliente_id);
        if (!$cliente) {
            return ['ok' => false, 'payload' => null, 'errors' => ['Cliente da coleta não encontrado.']];
        }

        $geradorCnpj = preg_replace('/\D/', '', (string)$snapshot->gerador_cnpj);
        if ($geradorCnpj === '') {
            return ['ok' => false, 'payload' => null, 'errors' => ['CNPJ do gerador ausente no snapshot.']];
        }

        $geradorUnidade = (int)($cliente->sinir_cod_unidade ?? 0);
        if ($geradorUnidade <= 0) {
            return ['ok' => false, 'payload' => null, 'errors' => ['Cliente sem código unidade SINIR (cadastro do cliente).']];
        }

        $transportadorCnpj = preg_replace('/\D/', '', (string)$snapshot->transportador_cnpj);
        $destinadorCnpj = preg_replace('/\D/', '', (string)$snapshot->destinador_cnpj);
        if ($transportadorCnpj === '' || $destinadorCnpj === '') {
            return ['ok' => false, 'payload' => null, 'errors' => ['CNPJ transportador/destinador ausente no snapshot.']];
        }

        $itens = EntityColetaItem::getByColetaId($coletaId);
        if ($itens === []) {
            return ['ok' => false, 'payload' => null, 'errors' => ['Coleta sem itens de resíduo.']];
        }

        $errors = [];
        $itemManifestoJSONs = [];
        foreach ($itens as $item) {
            $tipo = $item->tipo_residuo_id ? EntityTipoResiduo::getById($item->tipo_residuo_id) : null;
            if (!$tipo || !$this->tipoTemCodigosSinir($tipo)) {
                $errors[] = 'Item "'.($item->nome ?: ('#'.$item->id)).'" sem mapeamento SINIR completo.';
                continue;
            }

            $codIbama = preg_replace('/\D/', '', (string)($item->cod_ibama ?: $tipo->cod_ibama));
            if ($codIbama === '') {
                $errors[] = 'Item "'.($item->nome ?: ('#'.$item->id)).'" sem código IBAMA.';
                continue;
            }

            [$densidadeValor, $densidadeUnidade] = $this->densidadeFromUnidade($item->unidade);

            $itemManifestoJSONs[] = [
                'quantidade' => (float)$item->quantidade,
                'residuo' => $codIbama,
                'codigoAcondicionamento' => (int)$tipo->tia_codigo,
                'codigoClasse' => (int)$tipo->cla_codigo,
                'codigoTecnologia' => (int)$tipo->tra_codigo,
                'codigoTipoEstado' => (int)$tipo->tie_codigo,
                'codigoUnidade' => (int)$tipo->uni_codigo,
                'manifestoItemObservacao' => mb_substr((string)$item->nome, 0, 500),
                'manifestoItemCodInterno' => (string)$item->id,
                'manifestoItemCodInternoDestinador' => '',
                'tipoDensidadeValor' => $densidadeValor,
                'tipoDensidadeUnidade' => $densidadeUnidade,
            ];
        }

        if ($errors !== []) {
            return ['ok' => false, 'payload' => null, 'errors' => $errors];
        }

        $dataExpedicao = $coleta->data_coleta
            ? date('Ymd', strtotime($coleta->data_coleta))
            : date('Ymd');

        $payload = [
            'manifestoJSONDtos' => [
                [
                    'cnpGerador' => $geradorCnpj,
                    'codUnidadeGerador' => (string)$geradorUnidade,
                    'cnpTransportador' => $transportadorCnpj,
                    'codUnidadeTransportador' => (string)SinirConfig::unidade(),
                    'cnpDestinador' => $destinadorCnpj,
                    'codUnidadeDestinador' => (string)SinirConfig::destinadorUnidade(),
                    'cnpArmazenador' => null,
                    'codUnidadeArmazenador' => null,
                    'seuCodigoReferencia' => (string)($coleta->numero_mtr ?: $coleta->id),
                    'manifObservacao' => mb_substr(trim((string)($coleta->relatorio ?? '')), 0, 4000),
                    'manifGeradorNomeResponsavel' => mb_substr(
                        trim((string)($snapshot->gerador_responsavel ?: $snapshot->gerador_nome_fantasia)),
                        0,
                        300
                    ),
                    'manifGeradorCargoResponsavel' => 'Responsável',
                    'manifTransportadorNomeMotorista' => mb_substr(trim((string)$snapshot->motorista_nome), 0, 300),
                    'manifTransportadorPlacaVeiculo' => mb_substr(trim((string)$snapshot->veiculo_placa), 0, 10),
                    'manifTransportadorDataExpedicao' => $dataExpedicao,
                    'itemManifestoJSONs' => $itemManifestoJSONs,
                ],
            ],
        ];

        return ['ok' => true, 'payload' => $payload, 'errors' => []];
    }

    /** @return array{0:int,1:int} */
    private function densidadeFromUnidade(string $unidade): array
    {
        return match (strtolower(trim($unidade))) {
            'l' => [1, 2],
            default => [1, 1],
        };
    }

    private function tipoTemCodigosSinir(EntityTipoResiduo $tipo): bool
    {
        return (int)($tipo->tra_codigo ?? 0) > 0
            && (int)($tipo->tie_codigo ?? 0) > 0
            && (int)($tipo->tia_codigo ?? 0) > 0
            && (int)($tipo->cla_codigo ?? 0) > 0
            && (int)($tipo->uni_codigo ?? 0) > 0;
    }
}
