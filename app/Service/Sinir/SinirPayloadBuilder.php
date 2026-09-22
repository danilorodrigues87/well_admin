<?php

namespace App\Service\Sinir;

use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Destinador as EntityDestinador;
use App\Model\Entity\Transportadora as EntityTransportadora;
use App\Service\Sinir\SinirCredentialsResolver;
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

        $credentials = (new SinirCredentialsResolver())->forColeta($coletaId);
        if (!$credentials['ok']) {
            return ['ok' => false, 'payload' => null, 'errors' => $credentials['errors']];
        }

        $transportadora = $coleta->transportadora_id
            ? EntityTransportadora::getById((int)$coleta->transportadora_id)
            : EntityTransportadora::getPadrao();
        $destinadorEnt = $coleta->destinador_id
            ? EntityDestinador::getById((int)$coleta->destinador_id)
            : EntityDestinador::getPadrao();
        if (!$transportadora || !$destinadorEnt) {
            return ['ok' => false, 'payload' => null, 'errors' => ['Transportadora ou destinador não cadastrado na coleta.']];
        }

        $dataExpedicao = $coleta->data_coleta
            ? date('Ymd', strtotime($coleta->data_coleta))
            : date('Ymd');

        $refInterna = $coleta->numero_relatorio ?: $coleta->numero_mtr ?: $coleta->id;

        $payload = [
            'manifestoJSONDtos' => [
                [
                    'cnpGerador' => $geradorCnpj,
                    'codUnidadeGerador' => (string)$geradorUnidade,
                    'cnpTransportador' => $transportadorCnpj,
                    'codUnidadeTransportador' => (string)$credentials['transportador_unidade'],
                    'cnpDestinador' => $destinadorCnpj,
                    'codUnidadeDestinador' => (string)$credentials['destinador_unidade'],
                    'cnpArmazenador' => null,
                    'codUnidadeArmazenador' => null,
                    'seuCodigoReferencia' => (string)$refInterna,
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

    /**
     * Payload POST /receberManifestoLote (manifestoRecebimentoJSONs).
     *
     * @return array{ok:bool,payload:?array,errors:string[]}
     */
    public function buildRecebimentoLote(int $coletaId, ?string $responsavel = null, ?string $cargo = null): array
    {
        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'finalizada') {
            return ['ok' => false, 'payload' => null, 'errors' => ['Coleta não encontrada ou não finalizada.']];
        }
        if (($coleta->sinir_status ?? '') !== 'enviado') {
            return ['ok' => false, 'payload' => null, 'errors' => ['Somente MTRs registrados no SINIR podem ser recebidos.']];
        }

        $manifestoCodigo = trim((string)($coleta->sinir_man_numero ?? ''));
        if ($manifestoCodigo === '' && $coleta->numero_mtr) {
            $manifestoCodigo = (string)(int)$coleta->numero_mtr;
        }
        if ($manifestoCodigo === '') {
            return ['ok' => false, 'payload' => null, 'errors' => ['Código do manifesto SINIR ausente.']];
        }

        $snapshot = EntityColetaSnapshot::getByColetaId($coletaId);
        if (!$snapshot) {
            return ['ok' => false, 'payload' => null, 'errors' => ['Snapshot da coleta ausente.']];
        }

        $geradorCnpj = preg_replace('/\D/', '', (string)$snapshot->gerador_cnpj);
        $transportadorCnpj = preg_replace('/\D/', '', (string)$snapshot->transportador_cnpj);
        if ($geradorCnpj === '' || $transportadorCnpj === '') {
            return ['ok' => false, 'payload' => null, 'errors' => ['CNPJ gerador/transportador ausente no snapshot.']];
        }

        $dataReceb = $coleta->data_recebimento
            ? date('Ymd', strtotime($coleta->data_recebimento))
            : date('Ymd');
        $dataTransporte = $coleta->data_coleta
            ? date('Ymd', strtotime($coleta->data_coleta))
            : $dataReceb;

        $resp = trim((string)($responsavel ?? ''));
        if ($resp === '') {
            $destinador = $coleta->destinador_id
                ? EntityDestinador::getById((int)$coleta->destinador_id)
                : EntityDestinador::getPadrao();
            $resp = trim((string)($destinador->responsavel ?? $snapshot->destinador_responsavel ?? 'Responsável'));
        }
        $cargoFinal = trim((string)($cargo ?? '')) !== '' ? trim((string)$cargo) : 'Responsável';

        $itens = EntityColetaItem::getByColetaId($coletaId);
        if ($itens === []) {
            return ['ok' => false, 'payload' => null, 'errors' => ['Coleta sem itens de resíduo.']];
        }

        $errors = [];
        $itemRecebimento = [];
        $seq = 1;
        foreach ($itens as $item) {
            $tipo = $item->tipo_residuo_id ? EntityTipoResiduo::getById($item->tipo_residuo_id) : null;
            if (!$tipo || !$this->tipoTemCodigosSinir($tipo)) {
                $errors[] = 'Item "'.($item->nome ?: ('#'.$item->id)).'" sem mapeamento SINIR.';
                continue;
            }
            $codIbama = preg_replace('/\D/', '', (string)($item->cod_ibama ?: $tipo->cod_ibama));
            if ($codIbama === '') {
                $errors[] = 'Item "'.($item->nome ?: ('#'.$item->id)).'" sem código IBAMA.';
                continue;
            }
            $itemRecebimento[] = [
                'codigoSequencial' => $seq,
                'justificativa' => null,
                'codigoInterno' => null,
                'qtdRecebida' => (float)$item->quantidade,
                'residuo' => $codIbama,
                'codigoTecnologia' => (int)$tipo->tra_codigo,
                'codigoTipoEstado' => (int)$tipo->tie_codigo,
            ];
            ++$seq;
        }
        if ($errors !== []) {
            return ['ok' => false, 'payload' => null, 'errors' => $errors];
        }

        $motorista = mb_substr(trim((string)$snapshot->motorista_nome), 0, 100);
        $placa = mb_substr(trim((string)$snapshot->veiculo_placa), 0, 10);

        $payload = [
            'manifestoRecebimentoJSONs' => [
                [
                    'manifestoCodigo' => $manifestoCodigo,
                    'cnpGerador' => $geradorCnpj,
                    'cnpTransportador' => $transportadorCnpj,
                    'recebimentoMtrResponsavel' => mb_substr($resp, 0, 150),
                    'recebimentoMtrCargo' => mb_substr($cargoFinal, 0, 100),
                    'recebimentoMtrData' => $dataReceb,
                    'recebimentoMtrObs' => mb_substr(trim((string)($coleta->relatorio ?? '')), 0, 255),
                    'nomeMotorista' => $motorista !== '' ? $motorista : 'Motorista',
                    'placaVeiculo' => $placa !== '' ? $placa : 'S/PLACA',
                    'transporteMtrData' => $dataTransporte,
                    'itemManifestoRecebimentoJSONs' => $itemRecebimento,
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
